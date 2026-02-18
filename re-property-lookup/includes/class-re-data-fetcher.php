<?php
/**
 * Fetches publicly available property data for a given address.
 *
 * Data sources (in priority order):
 *   1. Smarty US Street Address API — address validation, standardization,
 *      lat/lng, county, ZIP+4, commercial/residential classification (secret key, server-side)
 *   2. Nominatim (OpenStreetMap) — fallback geocoding when Smarty is not configured
 *   3. Overpass API (OpenStreetMap) — building year, type, levels
 *   4. Curated permit portal directory — links to public city/county permit searches
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RE_PLU_Data_Fetcher {

    private $address;

    /* Nominatim usage policy: max 1 req/s, must include User-Agent */
    private const USER_AGENT = 'RE-Property-Lookup-WP-Plugin/1.0 (Commercial Real Estate Insurance Tool)';

    public function __construct( $address ) {
        $this->address = trim( $address );
    }

    /* -----------------------------------------------------------------------
     * Public: orchestrate all data fetching
     * -------------------------------------------------------------------- */

    public function fetch_all_data() {
        $result = [
            'geocoded'          => null,
            'year_built'        => null,
            'building_type'     => null,
            'building_levels'   => null,
            'square_footage'    => null,   // Requires paid API (placeholder)
            'tenants'           => null,   // Requires paid API (placeholder)
            'permits'           => [],
            'data_sources'      => [],
            'notes'             => [],
            /* Smarty-specific — consumed by URL generator */
            'smarty_components' => null,
            'smarty_formatted'  => null,
            'address_valid'     => null,
        ];

        /* ----------------------------------------------------------------
         * Step 1 — Validate & geocode via Smarty (primary)
         *           Fall back to Nominatim when Smarty is not configured.
         * -------------------------------------------------------------- */
        $smarty = $this->validate_with_smarty();

        if ( $smarty ) {
            $components = $smarty['components'] ?? [];
            $metadata   = $smarty['metadata']   ?? [];
            $analysis   = $smarty['analysis']   ?? [];

            $city   = $components['city_name']         ?? null;
            $state  = $components['state_abbreviation'] ?? null;
            $zip    = $components['zipcode']            ?? null;
            $county = $metadata['county_name']          ?? null;
            $lat    = $metadata['latitude']             ?? null;
            $lon    = $metadata['longitude']            ?? null;

            /* DPV match codes: Y = full, S = partial, D = default, N = no match */
            $dpv   = $analysis['dpv_match_code'] ?? '';
            $valid = in_array( $dpv, [ 'Y', 'S' ], true );

            $result['smarty_components'] = $components;
            $result['smarty_formatted']  = trim( ( $smarty['delivery_line_1'] ?? '' ) . ', ' . ( $smarty['last_line'] ?? '' ) );
            $result['address_valid']     = $valid;

            $result['geocoded'] = [
                'display_name' => $result['smarty_formatted'],
                'lat'          => $lat,
                'lon'          => $lon,
                'city'         => $city,
                'county'       => $county,
                'state'        => $state,
                'zip'          => $zip,
                'zip_plus4'    => $components['plus4_code'] ?? null,
                'rdi'          => $metadata['rdi']          ?? null,  // 'Commercial' | 'Residential'
                'time_zone'    => $metadata['time_zone']    ?? null,
                'dpv_status'   => $dpv,
            ];

            $result['data_sources'][] = 'Smarty US Street Address API (validation & geocoding)';

        } else {
            /* Nominatim fallback */
            $geocode = $this->geocode_address_nominatim();

            if ( ! $geocode ) {
                $result['notes'][] = 'Could not validate this address. Verify it is complete (street, city, state, ZIP) and try again.';
                $result['notes'][] = 'Platform links are still generated and can be used for manual research.';
                return $result;
            }

            $addr_parts = $geocode['address'] ?? [];
            $city   = $addr_parts['city']    ?? $addr_parts['town'] ?? $addr_parts['village'] ?? null;
            $state  = $addr_parts['state']   ?? null;
            $zip    = $addr_parts['postcode'] ?? null;
            $county = $addr_parts['county']  ?? null;
            $lat    = $geocode['lat']         ?? null;
            $lon    = $geocode['lon']         ?? null;

            $result['geocoded'] = [
                'display_name' => $geocode['display_name'] ?? $this->address,
                'lat'          => $lat,
                'lon'          => $lon,
                'city'         => $city,
                'county'       => $county,
                'state'        => $state,
                'zip'          => $zip,
                'zip_plus4'    => null,
                'rdi'          => null,
                'time_zone'    => null,
                'dpv_status'   => null,
            ];

            $result['data_sources'][] = 'OpenStreetMap / Nominatim (geocoding fallback)';
        }

        /* ----------------------------------------------------------------
         * Step 2 — OSM building data (uses Smarty lat/lng when available
         *           for higher accuracy than Nominatim)
         * -------------------------------------------------------------- */
        $lat = $result['geocoded']['lat'];
        $lon = $result['geocoded']['lon'];

        if ( $lat && $lon ) {
            $osm = $this->get_osm_building_data( $lat, $lon );
            if ( $osm ) {
                $result['year_built']      = $osm['year_built']      ?? null;
                $result['building_type']   = $osm['building_type']   ?? null;
                $result['building_levels'] = $osm['building_levels'] ?? null;
                $result['data_sources'][]  = 'OpenStreetMap / Overpass API (building attributes)';
            }
        }

        /* Step 3 — Permit portal links */
        $result['permits'] = $this->get_permit_portal_links(
            $result['geocoded']['city'],
            $result['geocoded']['state'],
            $result['geocoded']['county'],
            $result['geocoded']['zip']
        );

        /* Step 4 — Transparency notes */
        $result['notes'][] = 'Square footage and tenant data are available via the linked platforms (Zillow, Redfin, LoopNet) for manual review.';
        $result['notes'][] = 'An API connection (ATTOM Data, CoStar, etc.) can be added for automated square footage and tenant retrieval.';

        return $result;
    }

    /* -----------------------------------------------------------------------
     * Validate and standardize the address using the Smarty US Street API.
     *
     * Uses the Secret Key stored in wp_options — never exposed to the browser.
     * Returns the first candidate array or null if the address is not found
     * or if Smarty credentials are not configured.
     * -------------------------------------------------------------------- */

    private function validate_with_smarty() {
        $auth_id    = get_option( 're_plu_smarty_auth_id',    '' );
        $auth_token = get_option( 're_plu_smarty_auth_token', '' );

        if ( empty( $auth_id ) || empty( $auth_token ) ) {
            return null;
        }

        $endpoint = add_query_arg( [
            'street'     => $this->address,
            'candidates' => 1,
            'auth-id'    => $auth_id,
            'auth-token' => $auth_token,
        ], 'https://us-street.api.smarty.com/street-address' );

        $response = wp_remote_get( $endpoint, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        /* An empty array means the address was not found / invalid */
        return ( ! empty( $data ) && isset( $data[0] ) ) ? $data[0] : null;
    }

    /* -----------------------------------------------------------------------
     * Geocode the address using Nominatim (fallback when Smarty not configured)
     * -------------------------------------------------------------------- */

    private function geocode_address_nominatim() {
        $endpoint = add_query_arg( [
            'q'              => $this->address,
            'format'         => 'json',
            'addressdetails' => 1,
            'limit'          => 1,
        ], 'https://nominatim.openstreetmap.org/search' );

        $response = wp_remote_get( $endpoint, [
            'timeout' => 10,
            'headers' => [ 'User-Agent' => self::USER_AGENT ],
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return ( ! empty( $data ) && isset( $data[0] ) ) ? $data[0] : null;
    }

    /* -----------------------------------------------------------------------
     * Query the Overpass API for building tags at the geocoded location
     * -------------------------------------------------------------------- */

    private function get_osm_building_data( $lat, $lon ) {
        /*
         * Search for any OSM way tagged as a building within 60 m of the
         * geocoded point. This radius keeps results tight to the specific
         * parcel while handling slight geocoding offsets.
         */
        $query = sprintf(
            '[out:json][timeout:12];(way["building"](around:60,%s,%s););out body;>;out skel qt;',
            (float) $lat,
            (float) $lon
        );

        $endpoint = 'https://overpass-api.de/api/interpreter?' . http_build_query( [ 'data' => $query ] );

        $response = wp_remote_get( $endpoint, [
            'timeout' => 15,
            'headers' => [ 'User-Agent' => self::USER_AGENT ],
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data['elements'] ) ) {
            return null;
        }

        $result = [];

        foreach ( $data['elements'] as $element ) {
            if ( 'way' !== $element['type'] || empty( $element['tags'] ) ) {
                continue;
            }

            $tags = $element['tags'];

            /* Year built — stored as 'start_date' in OSM */
            if ( ! empty( $tags['start_date'] ) ) {
                /* start_date can be "2003", "2003-06", "2003-06-15" etc. */
                $result['year_built'] = substr( $tags['start_date'], 0, 4 );
            }

            /* Number of above-ground floors */
            if ( ! empty( $tags['building:levels'] ) ) {
                $result['building_levels'] = (int) $tags['building:levels'];
            }

            /* Building classification (office, retail, commercial, etc.) */
            if ( ! empty( $tags['building'] ) && 'yes' !== $tags['building'] ) {
                $result['building_type'] = ucwords( str_replace( '_', ' ', $tags['building'] ) );
            }

            /* amenity or shop tag for known commercial uses */
            if ( empty( $result['building_type'] ) && ! empty( $tags['amenity'] ) ) {
                $result['building_type'] = ucwords( str_replace( '_', ' ', $tags['amenity'] ) );
            }

            /* Take first matched element and stop */
            if ( ! empty( $result ) ) {
                break;
            }
        }

        return ! empty( $result ) ? $result : null;
    }

    /* -----------------------------------------------------------------------
     * Return relevant permit portal links for the address's city/state
     * -------------------------------------------------------------------- */

    private function get_permit_portal_links( $city, $state, $county = null, $zip = null ) {
        $links = [];

        /* ---- City-specific open permit portals ---- */
        $city_portals = [
            /* California */
            'los angeles'   => [ 'label' => 'LA Department of Building & Safety', 'url' => 'https://www.ladbsservices2.lacity.org/onlineservices/default.aspx' ],
            'san francisco' => [ 'label' => 'SF DBI Permit Search',               'url' => 'https://dbiweb02.sfgov.org/dbipts/default.aspx?page=BuildingPermitSearch' ],
            'san diego'     => [ 'label' => 'San Diego Permit Status',            'url' => 'https://www.sandiego.gov/development-services/permits/permit-status' ],
            'sacramento'    => [ 'label' => 'Sacramento Building Permits',        'url' => 'https://cityofsacramento.org/Community-Development/Building-Permits' ],
            'san jose'      => [ 'label' => 'San José Permit Center',             'url' => 'https://www.sanjoseca.gov/business/permits-licenses/building-permits' ],
            /* Texas */
            'houston'       => [ 'label' => 'Houston Permitting Center',          'url' => 'https://www.houston.permittingcenter.org/' ],
            'dallas'        => [ 'label' => 'Develop Dallas',                     'url' => 'https://developdallas.dallascityhall.com/' ],
            'austin'        => [ 'label' => 'Austin Development Services',        'url' => 'https://austintexas.gov/department/development-services-permits' ],
            'san antonio'   => [ 'label' => 'San Antonio Development Services',   'url' => 'https://www.sanantonio.gov/DSD/Permits' ],
            /* New York */
            'new york'      => [ 'label' => 'NYC DOB NOW Build',                  'url' => 'https://a810-bisweb.nyc.gov/bisweb/bsqpm01.jsp' ],
            'new york city' => [ 'label' => 'NYC DOB NOW Build',                  'url' => 'https://a810-bisweb.nyc.gov/bisweb/bsqpm01.jsp' ],
            /* Illinois */
            'chicago'       => [ 'label' => 'Chicago Building Records',           'url' => 'https://webapps1.chicago.gov/buildingrecords/' ],
            /* Florida */
            'miami'         => [ 'label' => 'Miami-Dade Building Permits',        'url' => 'https://www.miamidade.gov/permits/' ],
            'orlando'       => [ 'label' => 'Orlando Building Division',          'url' => 'https://www.orlando.gov/Building-Development/Building-Division/Permits' ],
            'tampa'         => [ 'label' => 'Tampa Permits',                      'url' => 'https://www.tampagov.net/building-construction/permits' ],
            'jacksonville'  => [ 'label' => 'Jacksonville Building Permits',      'url' => 'https://buildingpermits.jacksonvillefl.gov/' ],
            /* Georgia */
            'atlanta'       => [ 'label' => 'Atlanta Office of Buildings',        'url' => 'https://conapps.atlantaga.gov/OccupantServices/permitSearch' ],
            /* Arizona */
            'phoenix'       => [ 'label' => 'Phoenix Planning & Development',     'url' => 'https://phoenix.gov/pdd/permits' ],
            'tucson'        => [ 'label' => 'Tucson Development Services',        'url' => 'https://www.tucsonaz.gov/Departments/Development-Services' ],
            /* Washington */
            'seattle'       => [ 'label' => 'Seattle Permit Search',              'url' => 'https://cosaccela.seattle.gov/portal/welcome.aspx' ],
            /* Colorado */
            'denver'        => [ 'label' => 'Denver Development Services',        'url' => 'https://www.denvergov.org/online-services-and-information/online-services/apply-for-a-building-permit' ],
            /* Nevada */
            'las vegas'     => [ 'label' => 'Las Vegas Development Center',       'url' => 'https://lvdcd.com/building-permits' ],
            'henderson'     => [ 'label' => 'Henderson Building Permits',         'url' => 'https://www.cityofhenderson.com/city-services/building-development' ],
            /* Ohio */
            'columbus'      => [ 'label' => 'Columbus Permit Center',             'url' => 'https://permits.columbus.gov/' ],
            'cleveland'     => [ 'label' => 'Cleveland Building Permits',         'url' => 'https://www.clevelandohio.gov/CityofCleveland/Home/Government/CityAgencies/BuildingandHousing' ],
            /* Oregon */
            'portland'      => [ 'label' => 'Portland Development Services',      'url' => 'https://www.portland.gov/bds/permits' ],
            /* Michigan */
            'detroit'       => [ 'label' => 'Detroit BSEED Permits',              'url' => 'https://www.detroitmi.gov/government/departments-and-agencies/buildings-safety-engineering-and-environmental-department/permits' ],
            /* North Carolina */
            'charlotte'     => [ 'label' => 'Charlotte Online Permit Portal',     'url' => 'https://charlottes-portal.tylerhost.net/NC_Charlotte/' ],
            'raleigh'       => [ 'label' => 'Raleigh Online Services',            'url' => 'https://raleighnc.gov/permits-inspections' ],
            /* Tennessee */
            'nashville'     => [ 'label' => 'Nashville Codes Administration',     'url' => 'https://nashville.gov/Government/Agencies/Codes-Administration/Planning-Zones-and-Permits.aspx' ],
            /* Minnesota */
            'minneapolis'   => [ 'label' => 'Minneapolis e-Services',             'url' => 'https://eservices.ci.minneapolis.mn.us/OccupancyPermits/' ],
            /* Missouri */
            'kansas city'   => [ 'label' => 'Kansas City Permits',                'url' => 'https://www.kcmo.gov/city-hall/departments/city-development/permits' ],
            'st. louis'     => [ 'label' => 'St. Louis Building Division',        'url' => 'https://www.stlouis-mo.gov/government/departments/building/' ],
            /* Indiana */
            'indianapolis'  => [ 'label' => 'Indianapolis Permit Center',         'url' => 'https://www.indy.gov/agency/department-of-metropolitan-development' ],
            /* Wisconsin */
            'milwaukee'     => [ 'label' => 'Milwaukee DPCED Permits',            'url' => 'https://city.milwaukee.gov/DPCED/permits' ],
            /* Maryland */
            'baltimore'     => [ 'label' => 'Baltimore City Permits',             'url' => 'https://bchd.baltimorecity.gov/permits' ],
            /* Virginia */
            'virginia beach' => [ 'label' => 'Virginia Beach Permits',            'url' => 'https://www.vbgov.com/government/departments/permits-inspections/' ],
        ];

        $city_key = strtolower( trim( $city ?? '' ) );
        if ( $city_key && isset( $city_portals[ $city_key ] ) ) {
            $links[] = array_merge( $city_portals[ $city_key ], [ 'type' => 'city' ] );
        }

        /* ---- Always include PermitData.com (national aggregator) ---- */
        $links[] = [
            'label' => 'PermitData.com — National Permit Search',
            'url'   => 'https://www.permitdata.com/',
            'type'  => 'national',
        ];

        /* ---- OpenDataSoft building permits dataset ---- */
        $search_term = $city ? $city . ' building permits' : 'building permits';
        $links[] = [
            'label' => 'OpenDataSoft — Building Permit Datasets',
            'url'   => 'https://public.opendatasoft.com/explore/?q=' . rawurlencode( $search_term ) . '&sort=modified',
            'type'  => 'national',
        ];

        /* ---- FOIA / public records note ---- */
        if ( $city && $state ) {
            $links[] = [
                'label' => 'Submit a public records (FOIA) request — ' . esc_html( $city ) . ', ' . esc_html( $state ),
                'url'   => 'https://www.google.com/search?q=' . rawurlencode( $city . ' ' . $state . ' building permit public records request' ),
                'type'  => 'foia',
            ];
        }

        return $links;
    }
}
