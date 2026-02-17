<?php
/**
 * Password gate template — rendered by the [property_lookup] shortcode
 * when the visitor has not yet authenticated for the current session.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$tool_title = esc_html( get_option( 're_plu_title', 'Commercial Property Lookup' ) );
?>
<div class="re-plu-wrap" id="re-plu-root">
    <div class="re-plu-card re-plu-password-card">

        <div class="re-plu-header">
            <div class="re-plu-logo" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
            </div>
            <h2 class="re-plu-title"><?php echo $tool_title; ?></h2>
            <p class="re-plu-subtitle">Team access is password protected.</p>
        </div>

        <div class="re-plu-form-body">
            <div class="re-plu-field">
                <label for="re-plu-password" class="re-plu-label">Access Password</label>
                <input
                    type="password"
                    id="re-plu-password"
                    class="re-plu-input"
                    placeholder="Enter your team password"
                    autocomplete="current-password"
                >
            </div>

            <button type="button" id="re-plu-pw-submit" class="re-plu-btn re-plu-btn-primary re-plu-btn-full">
                <span class="re-plu-btn-label">Access Tool</span>
                <span class="re-plu-spinner" style="display:none;"></span>
            </button>

            <div id="re-plu-pw-message" class="re-plu-message" role="alert" aria-live="polite"></div>
        </div>

    </div>
</div>
