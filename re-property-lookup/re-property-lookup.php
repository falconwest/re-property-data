<?php
/**
 * Plugin Name:  RE Property Lookup
 * Plugin URI:   https://github.com/falconwest/re-property-data
 * Description:  Commercial real estate property data lookup tool for insurance brokerage teams. Enter an address to instantly retrieve Zillow, Redfin, and LoopNet links plus publicly available property data including year built, building type, and permit portal links.
 * Version:      1.0.0
 * Author:       Your Brokerage
 * License:      GPL-2.0+
 * Text Domain:  re-property-lookup
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RE_PLU_VERSION',  '1.0.0' );
define( 'RE_PLU_PATH',     plugin_dir_path( __FILE__ ) );
define( 'RE_PLU_URL',      plugin_dir_url( __FILE__ ) );
define( 'RE_PLU_BASENAME', plugin_basename( __FILE__ ) );

require_once RE_PLU_PATH . 'includes/class-re-admin.php';
require_once RE_PLU_PATH . 'includes/class-re-url-generator.php';
require_once RE_PLU_PATH . 'includes/class-re-data-fetcher.php';

/**
 * Main plugin class — singleton.
 */
class RE_Property_Lookup {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        /* Session must be started before any output */
        add_action( 'init', [ $this, 'start_session' ], 1 );

        /* Assets */
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        /* Shortcode */
        add_shortcode( 'property_lookup', [ $this, 'shortcode_output' ] );

        /* AJAX — authenticated and non-authenticated users both hit these endpoints */
        add_action( 'wp_ajax_re_password_check',        [ $this, 'ajax_password_check' ] );
        add_action( 'wp_ajax_nopriv_re_password_check', [ $this, 'ajax_password_check' ] );

        add_action( 'wp_ajax_re_property_lookup',        [ $this, 'ajax_lookup' ] );
        add_action( 'wp_ajax_nopriv_re_property_lookup', [ $this, 'ajax_lookup' ] );

        add_action( 'wp_ajax_re_clear_session',          [ $this, 'ajax_clear_session' ] );
        add_action( 'wp_ajax_nopriv_re_clear_session',   [ $this, 'ajax_clear_session' ] );

        /* Admin */
        new RE_PLU_Admin();
    }

    /* -----------------------------------------------------------------------
     * Session helpers
     * -------------------------------------------------------------------- */

    public function start_session() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }

    private function is_authenticated() {
        return isset( $_SESSION['re_plu_auth'] ) && true === $_SESSION['re_plu_auth'];
    }

    /* -----------------------------------------------------------------------
     * Asset enqueueing
     * -------------------------------------------------------------------- */

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            're-property-lookup',
            RE_PLU_URL . 'assets/css/re-property-lookup.css',
            [],
            RE_PLU_VERSION
        );

        wp_enqueue_script(
            're-property-lookup',
            RE_PLU_URL . 'assets/js/re-property-lookup.js',
            [ 'jquery' ],
            RE_PLU_VERSION,
            true
        );

        wp_localize_script( 're-property-lookup', 'rePropLookup', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 're_plu_nonce' ),
        ] );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_re-property-lookup' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            're-property-lookup-admin',
            RE_PLU_URL . 'assets/css/re-property-lookup.css',
            [],
            RE_PLU_VERSION
        );
    }

    /* -----------------------------------------------------------------------
     * Shortcode
     * -------------------------------------------------------------------- */

    public function shortcode_output( $atts ) {
        ob_start();

        if ( $this->is_authenticated() ) {
            include RE_PLU_PATH . 'templates/lookup-tool.php';
        } else {
            include RE_PLU_PATH . 'templates/password-form.php';
        }

        return ob_get_clean();
    }

    /* -----------------------------------------------------------------------
     * AJAX: password check
     * -------------------------------------------------------------------- */

    public function ajax_password_check() {
        check_ajax_referer( 're_plu_nonce', 'nonce' );

        $password    = sanitize_text_field( wp_unslash( $_POST['password'] ?? '' ) );
        $stored_hash = get_option( 're_plu_password_hash', '' );

        if ( empty( $stored_hash ) ) {
            wp_send_json_error( [
                'message' => 'The tool has not been configured yet. Please contact your administrator.',
            ] );
        }

        if ( password_verify( $password, $stored_hash ) ) {
            $_SESSION['re_plu_auth'] = true;
            wp_send_json_success( [ 'message' => 'Access granted.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Incorrect password. Please try again.' ] );
        }
    }

    /* -----------------------------------------------------------------------
     * AJAX: property lookup
     * -------------------------------------------------------------------- */

    public function ajax_lookup() {
        check_ajax_referer( 're_plu_nonce', 'nonce' );

        if ( ! $this->is_authenticated() ) {
            wp_send_json_error( [ 'message' => 'Session expired. Please refresh the page and enter the password.' ] );
        }

        $address = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );

        if ( empty( $address ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a property address.' ] );
        }

        $url_gen      = new RE_PLU_URL_Generator( $address );
        $data_fetcher = new RE_PLU_Data_Fetcher( $address );

        $urls = [
            'zillow'  => $url_gen->get_zillow_url(),
            'redfin'  => $url_gen->get_redfin_url(),
            'loopnet' => $url_gen->get_loopnet_url(),
        ];

        $property_data = $data_fetcher->fetch_all_data();

        wp_send_json_success( [
            'address'       => $address,
            'urls'          => $urls,
            'property_data' => $property_data,
        ] );
    }

    /* -----------------------------------------------------------------------
     * AJAX: clear session (sign out)
     * -------------------------------------------------------------------- */

    public function ajax_clear_session() {
        check_ajax_referer( 're_plu_nonce', 'nonce' );
        unset( $_SESSION['re_plu_auth'] );
        wp_send_json_success();
    }

    /* -----------------------------------------------------------------------
     * Activation / deactivation
     * -------------------------------------------------------------------- */

    public static function activate() {
        /* Set default options only on first activation */
        add_option( 're_plu_title',        'Commercial Property Lookup' );
        add_option( 're_plu_instructions', 'Enter a full property address to retrieve listing links and publicly available property data.' );
        /* Password hash intentionally not set — admin must configure via Settings */
    }

    public static function deactivate() {
        /* Preserve settings on deactivate; data removed only on uninstall */
    }
}

register_activation_hook( __FILE__, [ 'RE_Property_Lookup', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'RE_Property_Lookup', 'deactivate' ] );

RE_Property_Lookup::get_instance();
