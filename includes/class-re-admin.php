<?php
/**
 * Admin settings page for RE Property Lookup.
 *
 * Adds a "Property Lookup" page under WP Admin > Settings where the
 * administrator can set the team access password, tool title, and
 * instructions text.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RE_PLU_Admin {

    public function __construct() {
        add_action( 'admin_menu',  [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',  [ $this, 'register_settings' ] );
        add_filter( 'plugin_action_links_' . RE_PLU_BASENAME, [ $this, 'add_plugin_action_links' ] );
    }

    /* -----------------------------------------------------------------------
     * Menu
     * -------------------------------------------------------------------- */

    public function add_settings_page() {
        add_options_page(
            'RE Property Lookup Settings',
            'Property Lookup',
            'manage_options',
            're-property-lookup',
            [ $this, 'render_settings_page' ]
        );
    }

    /* -----------------------------------------------------------------------
     * Settings registration
     * -------------------------------------------------------------------- */

    public function register_settings() {

        /* ---- Section: Access Control ---- */
        add_settings_section(
            're_plu_access_section',
            'Access Control',
            [ $this, 'section_access_description' ],
            're-property-lookup'
        );

        register_setting( 're-property-lookup', 're_plu_password_hash', [
            'sanitize_callback' => [ $this, 'sanitize_password' ],
        ] );

        add_settings_field(
            're_plu_password',
            'Tool Access Password',
            [ $this, 'field_password' ],
            're-property-lookup',
            're_plu_access_section'
        );

        /* ---- Section: Tool Appearance ---- */
        add_settings_section(
            're_plu_appearance_section',
            'Tool Appearance',
            '__return_false',
            're-property-lookup'
        );

        register_setting( 're-property-lookup', 're_plu_title', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );

        add_settings_field(
            're_plu_title',
            'Tool Title',
            [ $this, 'field_title' ],
            're-property-lookup',
            're_plu_appearance_section'
        );

        register_setting( 're-property-lookup', 're_plu_instructions', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ] );

        add_settings_field(
            're_plu_instructions',
            'Instructions Text',
            [ $this, 'field_instructions' ],
            're-property-lookup',
            're_plu_appearance_section'
        );

        /* ---- Section: Integrations ---- */
        add_settings_section(
            're_plu_integrations_section',
            'Integrations',
            [ $this, 'section_integrations_description' ],
            're-property-lookup'
        );

        register_setting( 're-property-lookup', 're_plu_gmaps_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );

        add_settings_field(
            're_plu_gmaps_key',
            'Google Maps API Key',
            [ $this, 'field_gmaps_key' ],
            're-property-lookup',
            're_plu_integrations_section'
        );

        register_setting( 're-property-lookup', 're_plu_smarty_auth_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );

        add_settings_field(
            're_plu_smarty_auth_id',
            'Smarty Auth ID',
            [ $this, 'field_smarty_auth_id' ],
            're-property-lookup',
            're_plu_integrations_section'
        );

        register_setting( 're-property-lookup', 're_plu_smarty_auth_token', [
            'sanitize_callback' => 'sanitize_text_field',
        ] );

        add_settings_field(
            're_plu_smarty_auth_token',
            'Smarty Auth Token',
            [ $this, 'field_smarty_auth_token' ],
            're-property-lookup',
            're_plu_integrations_section'
        );
    }

    /* -----------------------------------------------------------------------
     * Field renderers
     * -------------------------------------------------------------------- */

    public function section_access_description() {
        echo '<p>Set a shared password for your team. Anyone with this password can access the property lookup tool.</p>';
    }

    public function field_password() {
        $has_password = ! empty( get_option( 're_plu_password_hash' ) );
        ?>
        <div class="re-admin-password-wrap">
            <input
                type="password"
                name="re_plu_password_hash"
                id="re_plu_password"
                class="regular-text"
                value=""
                placeholder="<?php echo $has_password ? 'Password is set — enter a new one to change it' : 'Enter a password for your team'; ?>"
                autocomplete="new-password"
            >
            <p class="description">
                <?php if ( $has_password ) : ?>
                    <strong style="color:#2e7d32;">&#10003; A password is currently set.</strong>
                    Leave blank to keep the existing password.
                <?php else : ?>
                    <strong style="color:#c62828;">&#9888; No password set.</strong>
                    The tool will display a configuration notice until a password is saved.
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    public function field_title() {
        $value = get_option( 're_plu_title', 'Commercial Property Lookup' );
        echo '<input type="text" name="re_plu_title" class="regular-text" value="' . esc_attr( $value ) . '">';
        echo '<p class="description">Displayed as the heading of the tool on the front end.</p>';
    }

    public function field_instructions() {
        $value = get_option( 're_plu_instructions', 'Enter a full property address to retrieve listing links and publicly available property data.' );
        echo '<textarea name="re_plu_instructions" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
        echo '<p class="description">Short description shown above the address input on the tool page.</p>';
    }

    public function section_integrations_description() {
        echo '<p>Connect Google Maps for address autocomplete and Smarty for address validation and standardization. Smarty improves the accuracy of platform links and unlocks address-level property data.</p>';
    }

    public function field_gmaps_key() {
        $value   = get_option( 're_plu_gmaps_key', '' );
        $has_key = ! empty( $value );
        ?>
        <input
            type="text"
            name="re_plu_gmaps_key"
            class="regular-text"
            value="<?php echo esc_attr( $value ); ?>"
            placeholder="AIzaSy..."
            autocomplete="off"
            spellcheck="false"
        >
        <p class="description">
            <?php if ( $has_key ) : ?>
                <strong style="color:#2e7d32;">&#10003; API key is configured.</strong><br>
            <?php endif; ?>
            Requires <strong>Maps JavaScript API</strong> and <strong>Places API (New)</strong> enabled
            in your <a href="https://console.cloud.google.com/apis/library" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>.
            Leave blank to disable autocomplete — free-text address entry will still work normally.
        </p>
        <?php
    }

    public function field_smarty_auth_id() {
        $value   = get_option( 're_plu_smarty_auth_id', '' );
        $has_val = ! empty( $value );
        ?>
        <input
            type="text"
            name="re_plu_smarty_auth_id"
            class="regular-text"
            value="<?php echo esc_attr( $value ); ?>"
            placeholder="Your Smarty Auth ID"
            autocomplete="off"
            spellcheck="false"
        >
        <p class="description">
            <?php if ( $has_val ) : ?>
                <strong style="color:#2e7d32;">&#10003; Auth ID is set.</strong><br>
            <?php endif; ?>
            Found in your <a href="https://www.smarty.com/account/keys" target="_blank" rel="noopener noreferrer">Smarty account dashboard</a> under API Keys.
            Use a <strong>Secret Key</strong> — this value is only used server-side and is never sent to the browser.
        </p>
        <?php
    }

    public function field_smarty_auth_token() {
        $value   = get_option( 're_plu_smarty_auth_token', '' );
        $has_val = ! empty( $value );
        ?>
        <input
            type="password"
            name="re_plu_smarty_auth_token"
            class="regular-text"
            value="<?php echo esc_attr( $value ); ?>"
            placeholder="<?php echo $has_val ? 'Token is set — enter to replace' : 'Your Smarty Auth Token'; ?>"
            autocomplete="new-password"
        >
        <p class="description">
            <?php if ( $has_val ) : ?>
                <strong style="color:#2e7d32;">&#10003; Auth Token is set.</strong>
            <?php else : ?>
                The secret token paired with the Auth ID above.
            <?php endif; ?>
        </p>
        <?php
    }

    /* -----------------------------------------------------------------------
     * Password sanitization — hash only if a new value was entered
     * -------------------------------------------------------------------- */

    public function sanitize_password( $value ) {
        $value = trim( $value );
        if ( empty( $value ) ) {
            /* No new password submitted — return existing hash unchanged */
            return get_option( 're_plu_password_hash', '' );
        }
        return password_hash( $value, PASSWORD_BCRYPT );
    }

    /* -----------------------------------------------------------------------
     * Plugin action links
     * -------------------------------------------------------------------- */

    public function add_plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=re-property-lookup' ) . '">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /* -----------------------------------------------------------------------
     * Settings page HTML
     * -------------------------------------------------------------------- */

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap re-admin-wrap">
            <h1>
                <span class="re-admin-icon">&#127968;</span>
                RE Property Lookup &mdash; Settings
            </h1>

            <div class="re-admin-intro">
                <p>
                    Use the <code>[property_lookup]</code> shortcode on any page to embed the tool.
                    Team members will be prompted for the password before they can run lookups.
                </p>
                <p>
                    The tool generates direct search links for <strong>Zillow</strong>, <strong>Redfin</strong>,
                    and <strong>LoopNet</strong>, and retrieves publicly available property data including
                    year built, building type, and permit portal links.
                </p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields( 're-property-lookup' );
                do_settings_sections( 're-property-lookup' );
                submit_button( 'Save Settings' );
                ?>
            </form>

            <hr>

            <div class="re-admin-usage">
                <h2>Usage</h2>
                <table class="widefat re-admin-table">
                    <thead>
                        <tr><th>Shortcode</th><th>Description</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[property_lookup]</code></td>
                            <td>Embeds the full password-protected property lookup tool.</td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:24px;">Data Sources</h2>
                <table class="widefat re-admin-table">
                    <thead>
                        <tr><th>Data Point</th><th>Source</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Address Autocomplete</td>
                            <td>Google Maps Places API</td>
                            <td>Requires API key (see Integrations above). Falls back to free-text with override option.</td>
                        </tr>
                        <tr>
                            <td>Address Validation &amp; Standardization</td>
                            <td>Smarty US Street Address API</td>
                            <td>Validates address, standardizes components, provides lat/lng, county, ZIP+4, and commercial/residential classification. Uses <strong>Secret Key</strong> — server-side only.</td>
                        </tr>
                        <tr>
                            <td>Platform Links</td>
                            <td>Generated from address</td>
                            <td>Always available — Zillow, Redfin, LoopNet search URLs</td>
                        </tr>
                        <tr>
                            <td>Year Built / Building Type</td>
                            <td>OpenStreetMap (Overpass API)</td>
                            <td>Available for many properties; best for urban areas</td>
                        </tr>
                        <tr>
                            <td>Address Normalization</td>
                            <td>OpenStreetMap (Nominatim)</td>
                            <td>Free, no API key required</td>
                        </tr>
                        <tr>
                            <td>Permit Portal Links</td>
                            <td>Curated city/county open data portals</td>
                            <td>Links to the relevant public permit search for the property&rsquo;s city</td>
                        </tr>
                        <tr>
                            <td>Square Footage / Tenants</td>
                            <td>Linked platforms (manual review)</td>
                            <td>Available via LoopNet/CoStar API — add API key in a future update</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
            .re-admin-wrap h1 { display: flex; align-items: center; gap: 10px; }
            .re-admin-icon { font-size: 1.4em; }
            .re-admin-intro { background: #fff; border-left: 4px solid #2563eb; padding: 14px 18px; margin-bottom: 24px; border-radius: 0 4px 4px 0; }
            .re-admin-intro p { margin: 0 0 8px; }
            .re-admin-intro p:last-child { margin: 0; }
            .re-admin-table th { font-weight: 600; }
            .re-admin-table td code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
            .re-admin-password-wrap input { margin-bottom: 6px; }
        </style>
        <?php
    }
}
