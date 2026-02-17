/**
 * RE Property Lookup — Frontend JavaScript
 *
 * Handles:
 *   - Google Maps Places Autocomplete (when API key is configured)
 *   - Override mode for addresses not found on Google Maps
 *   - Password form submission (AJAX → PHP session)
 *   - Address lookup form submission (AJAX → PHP data fetcher)
 *   - Dynamic results rendering
 */

/* =========================================================================
 * Google Maps Places Autocomplete
 *
 * This function is called as the Maps JS API callback. It must be a global.
 * When no API key is configured, this function is never called.
 * ======================================================================= */

window.initRePlacesAutocomplete = function () {
    var input = document.getElementById( 're-plu-address' );
    if ( ! input ) { return; }

    var autocomplete = new google.maps.places.Autocomplete( input, {
        types:                [ 'address' ],
        componentRestrictions: { country: 'us' },
        fields:               [ 'formatted_address' ],
    } );

    /* When a suggestion is chosen, write the formatted address into the field */
    autocomplete.addListener( 'place_changed', function () {
        var place = autocomplete.getPlace();
        if ( place && place.formatted_address ) {
            input.value = place.formatted_address;
            /* Remove the override state if the user picks a Maps suggestion */
            if ( window.rePluOverrideActive ) {
                window.rePluDisableOverride();
            }
        }
    } );

    /* Store on window so the override toggle can reach it */
    window.rePlacesAutocomplete = autocomplete;

    /* Reveal the override row now that autocomplete is live */
    var overrideRow = document.getElementById( 're-plu-override-row' );
    if ( overrideRow ) { overrideRow.style.display = 'flex'; }
};

/* =========================================================================
 * jQuery plugin logic
 * ======================================================================= */
( function ( $ ) {
    'use strict';

    /* -----------------------------------------------------------------------
     * Helpers
     * -------------------------------------------------------------------- */

    function esc( str ) {
        return $( '<span>' ).text( str ).html();
    }

    function setLoading( $btn, loading ) {
        $btn.find( '.re-plu-btn-label' ).toggle( ! loading );
        $btn.find( '.re-plu-spinner' ).toggle( loading );
        $btn.prop( 'disabled', loading );
    }

    function showMessage( $el, msg, type ) {
        $el
            .removeClass( 're-plu-message-error re-plu-message-success' )
            .addClass( type === 'error' ? 're-plu-message-error' : 're-plu-message-success' )
            .text( msg )
            .show();
    }

    /* -----------------------------------------------------------------------
     * Password form
     * -------------------------------------------------------------------- */

    $( '#re-plu-pw-submit' ).on( 'click', function () {
        var $btn = $( this );
        var $pw  = $( '#re-plu-password' );
        var $msg = $( '#re-plu-pw-message' );

        var password = $pw.val().trim();
        if ( ! password ) {
            showMessage( $msg, 'Please enter the access password.', 'error' );
            $pw.focus();
            return;
        }

        setLoading( $btn, true );
        $msg.hide().text( '' );

        $.post( rePropLookup.ajaxUrl, {
            action:   're_password_check',
            nonce:    rePropLookup.nonce,
            password: password,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                /* Reload the page — the PHP session flag will now show the tool */
                window.location.reload();
            } else {
                showMessage( $msg, res.data.message || 'Incorrect password.', 'error' );
                $pw.val( '' ).focus();
                setLoading( $btn, false );
            }
        } )
        .fail( function () {
            showMessage( $msg, 'A network error occurred. Please try again.', 'error' );
            setLoading( $btn, false );
        } );
    } );

    /* Allow pressing Enter in the password field */
    $( '#re-plu-password' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) { $( '#re-plu-pw-submit' ).trigger( 'click' ); }
    } );

    /* -----------------------------------------------------------------------
     * Sign-out button
     * -------------------------------------------------------------------- */

    $( '#re-plu-signout' ).on( 'click', function () {
        $.post( rePropLookup.ajaxUrl, {
            action: 're_clear_session',
            nonce:  rePropLookup.nonce,
        } ).always( function () {
            window.location.reload();
        } );
    } );

    /* -----------------------------------------------------------------------
     * Override toggle
     *
     * When Google Maps autocomplete is active, team members can click
     * "Enter manually" to bypass autocomplete for addresses not on Maps.
     * The toggle suppresses the Google suggestions dropdown via a body class,
     * adds a visual indicator on the input, and can be reversed.
     * -------------------------------------------------------------------- */

    window.rePluOverrideActive = false;

    window.rePluEnableOverride = function () {
        window.rePluOverrideActive = true;
        $( '#re-plu-address' ).addClass( 're-plu-input--override' );
        $( 'body' ).addClass( 're-plu-override-active' );
        $( '#re-plu-override-toggle-btn' ).text( 'Restore autocomplete' ).addClass( 're-plu-override-restore' );
        $( '#re-plu-override-label' ).text( 'Manual entry active —' );
        /* Re-focus the input so the team member can start typing immediately */
        $( '#re-plu-address' ).trigger( 'focus' );
    };

    window.rePluDisableOverride = function () {
        window.rePluOverrideActive = false;
        $( '#re-plu-address' ).removeClass( 're-plu-input--override' );
        $( 'body' ).removeClass( 're-plu-override-active' );
        $( '#re-plu-override-toggle-btn' ).text( 'Enter manually' ).removeClass( 're-plu-override-restore' );
        $( '#re-plu-override-label' ).text( 'Address not on Google Maps?' );
    };

    $( document ).on( 'click', '#re-plu-override-toggle-btn', function () {
        if ( window.rePluOverrideActive ) {
            window.rePluDisableOverride();
        } else {
            window.rePluEnableOverride();
        }
    } );

    /* -----------------------------------------------------------------------
     * Property lookup
     * -------------------------------------------------------------------- */

    $( '#re-plu-lookup' ).on( 'click', function () {
        runLookup();
    } );

    $( '#re-plu-address' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) { runLookup(); }
    } );

    function runLookup() {
        var $btn     = $( '#re-plu-lookup' );
        var $address = $( '#re-plu-address' );
        var $err     = $( '#re-plu-lookup-error' );
        var $results = $( '#re-plu-results' );

        var address = $address.val().trim();

        if ( ! address ) {
            $err.text( 'Please enter a property address.' ).show();
            $address.focus();
            return;
        }

        setLoading( $btn, true );
        $err.hide().text( '' );
        $results.hide();

        $.post( rePropLookup.ajaxUrl, {
            action:  're_property_lookup',
            nonce:   rePropLookup.nonce,
            address: address,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                renderResults( res.data );
                $results.show();
                /* Smooth scroll to results */
                $( 'html, body' ).animate(
                    { scrollTop: $results.offset().top - 80 },
                    400
                );
            } else {
                $err.text( res.data.message || 'Lookup failed. Please try again.' ).show();
            }
        } )
        .fail( function () {
            $err.text( 'A network error occurred. Please try again.' ).show();
        } )
        .always( function () {
            setLoading( $btn, false );
        } );
    }

    /* -----------------------------------------------------------------------
     * Render results
     * -------------------------------------------------------------------- */

    function renderResults( data ) {
        var urls  = data.urls          || {};
        var prop  = data.property_data || {};
        var geo   = prop.geocoded      || {};

        /* ---- Address banner ---- */
        var displayAddr = geo.display_name || esc( data.address );
        $( '#re-plu-results' ).prepend( function () {
            /* Remove any previous banner */
            $( '.re-plu-address-banner' ).remove();
            return (
                '<div class="re-plu-address-banner">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
                '<span>Results for: ' + esc( displayAddr ) + '</span>' +
                '</div>'
            );
        } );

        /* ---- Platform links ---- */
        renderPlatformLinks( urls );

        /* ---- Property details ---- */
        renderPropertyDetails( prop, geo );

        /* ---- Tenants & square footage ---- */
        renderTenants( urls );

        /* ---- Permits ---- */
        renderPermits( prop.permits || [] );

        /* ---- Notes & data sources ---- */
        renderNotes( prop.notes || [], prop.data_sources || [] );
    }

    /* ---- Platform links ---- */
    function renderPlatformLinks( urls ) {
        var platforms = [
            {
                key:     'zillow',
                label:   'Zillow',
                cls:     'zillow',
                icon:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2L2 9.5V22h6v-7h8v7h6V9.5L12 2z"/></svg>',
                tooltip: 'Search Zillow for this property',
            },
            {
                key:     'redfin',
                label:   'Redfin',
                cls:     'redfin',
                icon:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>',
                tooltip: 'Search Redfin for this property',
            },
            {
                key:     'loopnet',
                label:   'LoopNet',
                cls:     'loopnet',
                icon:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><path d="M8 21h8M12 17v4"/></svg>',
                tooltip: 'Search LoopNet for this commercial property',
            },
        ];

        var html = '';
        platforms.forEach( function ( p ) {
            if ( urls[ p.key ] ) {
                html +=
                    '<a href="' + esc( urls[ p.key ] ) + '" ' +
                    'target="_blank" rel="noopener noreferrer" ' +
                    'class="re-plu-platform-btn ' + p.cls + '" ' +
                    'title="' + p.tooltip + '">' +
                    p.icon + ' ' + p.label +
                    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>' +
                    '</a>';
            }
        } );

        $( '#re-plu-platform-links' ).html( html );
    }

    /* ---- Property details grid ---- */
    function renderPropertyDetails( prop, geo ) {
        var cells = [
            { label: 'City',           value: geo.city      || null },
            { label: 'County',         value: geo.county    || null },
            { label: 'State',          value: geo.state     || null },
            { label: 'ZIP Code',       value: geo.zip       || null },
            { label: 'Year Built',     value: prop.year_built     || null },
            { label: 'Building Type',  value: prop.building_type  || null },
            { label: 'Floors / Levels',value: prop.building_levels ? prop.building_levels + ' floor(s)' : null },
            { label: 'Square Footage', value: prop.square_footage || null, na_note: 'See platform links' },
        ];

        var html = '';
        cells.forEach( function ( c ) {
            var val = c.value;
            var valHtml;
            if ( val ) {
                valHtml = '<div class="re-plu-detail-value">' + esc( String( val ) ) + '</div>';
            } else {
                var naText = c.na_note || 'Not found via public data';
                valHtml = '<div class="re-plu-detail-value not-available">' + esc( naText ) + '</div>';
            }
            html +=
                '<div class="re-plu-detail-cell">' +
                '<div class="re-plu-detail-label">' + esc( c.label ) + '</div>' +
                valHtml +
                '</div>';
        } );

        $( '#re-plu-property-details' ).html( html );
    }

    /* ---- Tenants & square footage (API-required notice) ---- */
    function renderTenants( urls ) {
        var html =
            '<div class="re-plu-api-notice">' +
            '<div class="re-plu-api-notice-icon">&#9888;</div>' +
            '<div>' +
            '<strong>Available via linked platforms / future API connection</strong>' +
            'Square footage and current tenant data require either manual review on the ' +
            'platforms above or a data provider API connection (e.g., ATTOM, CoStar, LoopNet API). ' +
            'Review <a href="' + esc( urls.loopnet || '#' ) + '" target="_blank" rel="noopener noreferrer">LoopNet</a> ' +
            'for available units, tenants, and lease rates for this property.' +
            '</div>' +
            '</div>';

        $( '#re-plu-tenants-section' ).html( html );
    }

    /* ---- Permit links ---- */
    function renderPermits( permits ) {
        if ( ! permits || ! permits.length ) {
            $( '#re-plu-permits-section' ).html(
                '<p style="color:var(--re-gray-400);font-size:13px;margin:0;">No permit portals found for this location. Try searching PermitData.com directly.</p>'
            );
            return;
        }

        var html = '<ul class="re-plu-permit-list">';
        permits.forEach( function ( p ) {
            var badgeLabel = { city: 'City Portal', national: 'National', foia: 'FOIA / Records' }[ p.type ] || p.type;
            html +=
                '<li>' +
                '<a href="' + esc( p.url ) + '" target="_blank" rel="noopener noreferrer" class="re-plu-permit-link">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>' +
                esc( p.label ) +
                '<span class="re-plu-permit-badge ' + esc( p.type ) + '">' + esc( badgeLabel ) + '</span>' +
                '</a>' +
                '</li>';
        } );
        html += '</ul>';

        $( '#re-plu-permits-section' ).html( html );
    }

    /* ---- Notes & data sources ---- */
    function renderNotes( notes, sources ) {
        var html = '';

        if ( sources && sources.length ) {
            html += '<div style="margin-bottom:14px;">';
            html += '<div style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--re-gray-400);margin-bottom:6px;">Data Retrieved From</div>';
            html += '<div class="re-plu-sources-tags">';
            sources.forEach( function ( s ) {
                html += '<span class="re-plu-source-tag">' + esc( s ) + '</span>';
            } );
            html += '</div></div>';
        }

        if ( notes && notes.length ) {
            html += '<ul class="re-plu-notes-list">';
            notes.forEach( function ( n ) {
                html += '<li>' + esc( n ) + '</li>';
            } );
            html += '</ul>';
        }

        $( '#re-plu-notes-section' ).html( html );
    }

} )( jQuery );
