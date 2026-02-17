<?php
/**
 * Main tool template — rendered by the [property_lookup] shortcode
 * after the visitor has authenticated.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$tool_title   = esc_html( get_option( 're_plu_title',        'Commercial Property Lookup' ) );
$instructions = esc_html( get_option( 're_plu_instructions', 'Enter a full property address to retrieve listing links and publicly available property data.' ) );
?>
<div class="re-plu-wrap" id="re-plu-root">

    <!-- ─── Toolbar ─── -->
    <div class="re-plu-toolbar">
        <div class="re-plu-toolbar-brand">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span><?php echo $tool_title; ?></span>
        </div>
        <button type="button" id="re-plu-signout" class="re-plu-btn re-plu-btn-ghost re-plu-btn-sm">
            Sign Out
        </button>
    </div>

    <!-- ─── Search Card ─── -->
    <div class="re-plu-card re-plu-search-card">
        <?php if ( $instructions ) : ?>
            <p class="re-plu-instructions"><?php echo $instructions; ?></p>
        <?php endif; ?>

        <div class="re-plu-search-row">
            <div class="re-plu-field re-plu-field-grow">
                <label for="re-plu-address" class="re-plu-label">Property Address</label>
                <input
                    type="text"
                    id="re-plu-address"
                    class="re-plu-input"
                    placeholder="e.g. 350 N Orleans St, Chicago, IL 60654"
                    autocomplete="off"
                    autocorrect="off"
                    spellcheck="false"
                >
            </div>
            <div class="re-plu-search-action">
                <button type="button" id="re-plu-lookup" class="re-plu-btn re-plu-btn-primary">
                    <span class="re-plu-btn-label">Look Up Property</span>
                    <span class="re-plu-spinner" style="display:none;"></span>
                </button>
            </div>
        </div>

        <!--
            Override row — hidden until initRePlacesAutocomplete() reveals it.
            If no Maps API key is configured, this row stays hidden permanently.
        -->
        <div id="re-plu-override-row" class="re-plu-override-row" style="display:none;" aria-live="polite">
            <span id="re-plu-override-label" class="re-plu-override-label">Address not on Google Maps?</span>
            <button type="button" id="re-plu-override-toggle-btn" class="re-plu-override-btn">
                Enter manually
            </button>
        </div>

        <div id="re-plu-lookup-error" class="re-plu-message re-plu-message-error" role="alert" style="display:none;"></div>
    </div>

    <!-- ─── Results (injected by JS) ─── -->
    <div id="re-plu-results" style="display:none;">

        <!-- Platform Links -->
        <div class="re-plu-card">
            <div class="re-plu-section-header">
                <h3 class="re-plu-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/></svg>
                    Listing Platform Links
                </h3>
                <p class="re-plu-section-note">Click to open search results for this address on each platform.</p>
            </div>
            <div class="re-plu-platform-grid" id="re-plu-platform-links"></div>
        </div>

        <!-- Property Details -->
        <div class="re-plu-card">
            <div class="re-plu-section-header">
                <h3 class="re-plu-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>
                    Property Details
                </h3>
            </div>
            <div class="re-plu-detail-grid" id="re-plu-property-details"></div>
        </div>

        <!-- Square Footage & Tenants -->
        <div class="re-plu-card">
            <div class="re-plu-section-header">
                <h3 class="re-plu-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                    Square Footage &amp; Available Tenants
                </h3>
            </div>
            <div id="re-plu-tenants-section"></div>
        </div>

        <!-- Permits -->
        <div class="re-plu-card">
            <div class="re-plu-section-header">
                <h3 class="re-plu-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                    Public Permits &amp; Records
                </h3>
                <p class="re-plu-section-note">Links to official city/county permit portals and national databases.</p>
            </div>
            <div id="re-plu-permits-section"></div>
        </div>

        <!-- Data Sources & Notes -->
        <div class="re-plu-card re-plu-card-muted">
            <h3 class="re-plu-section-title re-plu-section-title-sm">
                Data Sources &amp; Notes
            </h3>
            <div id="re-plu-notes-section"></div>
        </div>

    </div><!-- /#re-plu-results -->

</div><!-- /.re-plu-wrap -->
