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
