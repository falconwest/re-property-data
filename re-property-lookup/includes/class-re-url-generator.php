<?php
/**
 * Generates search URLs for Zillow, Redfin, and LoopNet from a raw address.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RE_PLU_URL_Generator {

    private $address;

    public function __construct( $address ) {
        $this->address = trim( $address );
    }

    /* -----------------------------------------------------------------------
     * Zillow
     *
     * Formats the address as a slug and appends the Zillow search suffix.
     * e.g. "123 Main St, Chicago, IL 60601" → zillow.com/homes/123-main-st-chicago-il-60601_rb/
     * -------------------------------------------------------------------- */

    public function get_zillow_url() {
        $slug = strtolower( $this->address );
        $slug = preg_replace( '/[^a-z0-9\s]/', '', $slug );
        $slug = preg_replace( '/\s+/', '-', trim( $slug ) );
        $slug = rtrim( $slug, '-' );

        return 'https://www.zillow.com/homes/' . $slug . '_rb/';
    }

    /* -----------------------------------------------------------------------
     * Redfin
     *
     * Uses the Redfin search endpoint with the address as the query parameter.
     * -------------------------------------------------------------------- */

    public function get_redfin_url() {
        return 'https://www.redfin.com/search?q=' . rawurlencode( $this->address );
    }

    /* -----------------------------------------------------------------------
     * LoopNet
     *
     * Uses LoopNet's search with a property-type=all filter so both
     * for-sale and for-lease commercial listings appear.
     * -------------------------------------------------------------------- */

    public function get_loopnet_url() {
        return 'https://www.loopnet.com/search/?q=' . rawurlencode( $this->address ) . '&propertyType=all';
    }

    /* -----------------------------------------------------------------------
     * Helper: extract city/state slug for LoopNet browse links
     *
     * When only a city/state is known, LoopNet has structured browse URLs.
     * e.g. Chicago, IL → loopnet.com/search/commercial-real-estate/chicago-il/for-sale/
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
