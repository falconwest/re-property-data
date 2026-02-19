<?php
/**
 * Generates address-specific search URLs for Zillow, Redfin, and LoopNet.
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
	 * zillow.com/homes/{slug}_rb/ resolves directly to the property page
	 * when the address exists in their database.
	 * -------------------------------------------------------------------- */

	public function get_zillow_url() {
		$slug = strtolower( $this->address );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
		$slug = trim( $slug, '-' );
		return 'https://www.zillow.com/homes/' . $slug . '_rb/';
	}

	/* -----------------------------------------------------------------------
	 * Redfin
	 * -------------------------------------------------------------------- */

	public function get_redfin_url() {
		return 'https://www.redfin.com/search?q=' . rawurlencode( $this->address );
	}

	/* -----------------------------------------------------------------------
	 * LoopNet (commercial)
	 * -------------------------------------------------------------------- */

	public function get_loopnet_url() {
		return 'https://www.loopnet.com/search/?q=' . rawurlencode( $this->address ) . '&propertyType=all';
	}

	/* -----------------------------------------------------------------------
	 * Helper: city-level LoopNet browse URL
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
