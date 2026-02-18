<?php
/**
 * Generates address-specific search URLs for Zillow, Redfin, and LoopNet.
 *
 * When Smarty components are provided the URLs are built from standardized
 * USPS address parts, producing a much more accurate direct-address link.
 * When components are absent the raw address string is used as a fallback.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RE_PLU_URL_Generator {

    private $address;
    private $components; /* Smarty components array, or null */

    public function __construct( $address, $components = null ) {
        $this->address    = trim( $address );
        $this->components = $components;
    }

    /* -----------------------------------------------------------------------
     * Zillow
     *
     * Zillow resolves the pattern:
     *   zillow.com/homes/{number}-{predirection}-{street}-{suffix}-{city}-{state}-{zip}_rb/
     * directly to the property page when it exists in their database.
     * -------------------------------------------------------------------- */

    public function get_zillow_url() {
        if ( $this->components ) {
            $c     = $this->components;
            $parts = array_filter( [
                $c['primary_number']       ?? '',
                $c['street_predirection']  ?? '',
                $c['street_name']          ?? '',
                $c['street_suffix']        ?? '',
                $c['secondary_designator'] ?? '',
                $c['secondary_number']     ?? '',
                $c['city_name']            ?? '',
                $c['state_abbreviation']   ?? '',
                $c['zipcode']              ?? '',
            ] );

            $slug = strtolower( implode( ' ', $parts ) );
            $slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
            $slug = trim( $slug, '-' );

            return 'https://www.zillow.com/homes/' . $slug . '_rb/';
        }

        /* Fallback: slug the raw address */
        $slug = strtolower( $this->address );
        $slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
        $slug = trim( $slug, '-' );
        return 'https://www.zillow.com/homes/' . $slug . '_rb/';
    }

    /* -----------------------------------------------------------------------
     * Redfin
     *
     * Redfin's search endpoint resolves a standardized address string to
     * the closest matching property listing.
     * -------------------------------------------------------------------- */

    public function get_redfin_url() {
        $query = $this->components
            ? $this->build_standardized_string()
            : $this->address;

        return 'https://www.redfin.com/search?q=' . rawurlencode( $query );
    }

    /* -----------------------------------------------------------------------
     * LoopNet
     *
     * LoopNet is the primary platform for commercial listings. The search
     * endpoint accepts a full address string and returns the closest match.
     * -------------------------------------------------------------------- */

    public function get_loopnet_url() {
        $query = $this->components
            ? $this->build_standardized_string()
            : $this->address;

        return 'https://www.loopnet.com/search/?q=' . rawurlencode( $query ) . '&propertyType=all';
    }

    /* -----------------------------------------------------------------------
     * Build a clean "123 N Main St, Chicago, IL 60601" string from Smarty
     * components — used for Redfin and LoopNet query parameters.
     * -------------------------------------------------------------------- */

    private function build_standardized_string() {
        $c = $this->components;

        $street = trim( implode( ' ', array_filter( [
            $c['primary_number']       ?? '',
            $c['street_predirection']  ?? '',
            $c['street_name']          ?? '',
            $c['street_suffix']        ?? '',
            $c['street_postdirection'] ?? '',
            $c['secondary_designator'] ?? '',
            $c['secondary_number']     ?? '',
        ] ) ) );

        $city_line = trim( implode( ' ', array_filter( [
            $c['city_name']          ?? '',
            $c['state_abbreviation'] ?? '',
            $c['zipcode']            ?? '',
        ] ) ) );

        return $street . ', ' . $city_line;
    }

    /* -----------------------------------------------------------------------
     * Helper: city-level LoopNet browse URL (used in permit links context)
     * -------------------------------------------------------------------- */

    public static function get_loopnet_city_url( $city, $state_abbr ) {
        if ( ! $city || ! $state_abbr ) {
            return '';
        }
        $slug = strtolower( preg_replace( '/\s+/', '-', trim( $city ) ) )
              . '-'
              . strtolower( trim( $state_abbr ) );
        return 'https://www.loopnet.com/search/commercial-real-estate/' . $slug . '/for-sale/';
    }
}
