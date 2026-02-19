/**
 * RE Property Lookup — Frontend JavaScript
 *
 * Handles:
 *   - Google Maps Places PlaceAutocompleteElement (when API key is configured)
 *   - Override mode for addresses not found on Google Maps
 *   - Password form submission (AJAX → PHP session)
 *   - Address lookup form submission (AJAX → PHP data fetcher)
 *   - Dynamic results rendering
 */

/* =========================================================================
 * Google Maps Places — PlaceAutocompleteElement
 *
 * This function is called as the Maps JS API callback. It must be a global.
 * When no API key is configured this function is never called.
 * ======================================================================= */

window.initRePlacesAutocomplete = function () {
    var streetInput = document.getElementById( 're-plu-street' );
    if ( ! streetInput ) { return; }

    try {
        var autocomplete = new google.maps.places.Autocomplete( streetInput, {
            types:                 [ 'address' ],
            componentRestrictions: { country: 'us' },
            fields:                [ 'address_components' ],
        } );

        autocomplete.addListener( 'place_changed', function () {
            var place = autocomplete.getPlace();
            if ( ! place || ! place.address_components ) { return; }

            var streetNumber = '', route = '', city = '', state = '', zip = '';

            place.address_components.forEach( function ( c ) {
                if ( c.types.indexOf( 'street_number' ) !== -1 ) { streetNumber = c.long_name; }
                if ( c.types.indexOf( 'route' ) !== -1 )          { route        = c.long_name; }
                if ( c.types.indexOf( 'locality' ) !== -1 )        { city         = c.long_name; }
                if ( c.types.indexOf( 'administrative_area_level_1' ) !== -1 ) { state = c.short_name; }
                if ( c.types.indexOf( 'postal_code' ) !== -1 )     { zip          = c.long_name; }
            } );

            /* Set street input to just the street portion */
            streetInput.value = streetNumber ? ( streetNumber + ' ' + route ) : route;

            /* Populate the other fields */
            var cityEl  = document.getElementById( 're-plu-city' );
            var stateEl = document.getElementById( 're-plu-state' );
            var zipEl   = document.getElementById( 're-plu-zip' );
            if ( cityEl )  { cityEl.value  = city;  }
            if ( stateEl ) { stateEl.value = state; }
            if ( zipEl )   { zipEl.value   = zip;   }
        } );

        window.rePlacesAutocomplete = autocomplete;

    } catch ( e ) {
        /*
         * Maps API failed to initialize — degrade gracefully: all four fields
         * work as plain text inputs and the lookup continues without autocomplete.
         */
        console.warn( '[RE Property Lookup] Google Maps autocomplete failed — falling back to plain text input.', e.message || e );
    }
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

    /* Assemble address from the four separate fields */
    function getAddressValue() {
        var street = ( ( document.getElementById( 're-plu-street' ) || {} ).value || '' ).trim();
        var city   = ( ( document.getElementById( 're-plu-city' )   || {} ).value || '' ).trim();
        var state  = ( ( document.getElementById( 're-plu-state' )  || {} ).value || '' ).trim();
        var zip    = ( ( document.getElementById( 're-plu-zip' )    || {} ).value || '' ).trim();

        if ( ! street ) { return ''; }

        /* Build: "350 N Orleans St, Chicago, IL 60654" */
        var address = street;
        if ( city )  { address += ', ' + city; }
        if ( state ) { address += ', ' + state; }
        if ( zip )   { address += ' ' + zip; }

        return address;
    }

    function focusAddressInput() {
        var el = document.getElementById( 're-plu-street' );
        if ( el ) { el.focus(); }
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
     * Property lookup
     * -------------------------------------------------------------------- */

    $( '#re-plu-lookup' ).on( 'click', function () {
        runLookup();
    } );

    /* Enter key on any address text field triggers lookup */
    $( '#re-plu-street, #re-plu-city, #re-plu-zip' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) { runLookup(); }
    } );

    function runLookup() {
        var $btn     = $( '#re-plu-lookup' );
        var $err     = $( '#re-plu-lookup-error' );
        var $results = $( '#re-plu-results' );

        var address = getAddressValue();

        if ( ! address ) {
            $err.text( 'Please enter a street address.' ).show();
            focusAddressInput();
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
            $( '.re-plu-address-banner' ).remove();
            return (
                '<div class="re-plu-address-banner">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
                '<span>Results for: ' + esc( displayAddr ) + '</span>' +
                '</div>'
            );
        } );

        renderPlatformLinks( urls );
        renderPropertyDetails( prop, geo, urls );
        renderPermits( prop.permits || [] );
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

    /* ---- Property details grid (all Smarty fields + OSM building data) ----
     *
     * Sections:
     *   1. Location          — Smarty components + metadata
     *   2. USPS Classification — Smarty metadata
     *   3. Address Validation  — Smarty analysis
     *   4. Geocoding           — Smarty metadata
     *   5. Building Data       — OpenStreetMap / Overpass API
     *   6. Square Footage & Tenants notice (merged section)
     * -------------------------------------------------------------------- */
    function renderPropertyDetails( prop, geo, urls ) {

        var dpvLabels = {
            'Y': '✓ Validated (full match)',
            'S': '⚠ Partial match',
            'D': '⚠ Default match',
            'N': '✗ Not found',
        };
        var validationVal = geo.dpv_status
            ? ( dpvLabels[ geo.dpv_status ] || geo.dpv_status )
            : null;

        var zipDisplay = geo.zip || null;
        if ( geo.zip && geo.zip_plus4 ) { zipDisplay = geo.zip + '-' + geo.zip_plus4; }

        var recordTypes = {
            'F': 'Firm',
            'G': 'General Delivery',
            'H': 'High-rise / Multi-unit',
            'P': 'PO Box',
            'R': 'Rural Route',
            'S': 'Street',
        };
        var recordTypeVal = geo.record_type
            ? ( recordTypes[ geo.record_type ] || geo.record_type )
            : null;

        /* Helper: map Y/N/true/false to readable labels */
        function yn( val, yesLabel, noLabel ) {
            if ( val === 'Y' || val === 'true' || val === true )  { return yesLabel || 'Yes'; }
            if ( val === 'N' || val === 'false' || val === false ) { return noLabel  || 'No';  }
            return null;
        }

        var utcDisplay = null;
        if ( geo.utc_offset !== undefined && geo.utc_offset !== null && geo.utc_offset !== '' ) {
            utcDisplay = 'UTC' + ( Number( geo.utc_offset ) >= 0 ? '+' : '' ) + geo.utc_offset;
        }

        /* ── All available Smarty + OSM fields ── */
        var cells = [

            /* Location */
            { label: 'City',                   value: geo.city                      },
            { label: 'Default City Name',       value: geo.default_city              },
            { label: 'County',                  value: geo.county                    },
            { label: 'County FIPS',             value: geo.county_fips               },
            { label: 'State',                   value: geo.state                     },
            { label: 'ZIP Code',                value: zipDisplay                    },
            { label: 'Congressional District',  value: geo.congressional_district    },

            /* USPS Classification */
            { label: 'Delivery Type',           value: recordTypeVal                 },
            { label: 'ZIP Type',                value: geo.zip_type                  },
            { label: 'Property Class (RDI)',    value: geo.rdi,    na_note: 'Not determined' },
            { label: 'Geocode Precision',       value: geo.precision                 },
            { label: 'Carrier Route',           value: geo.carrier_route             },
            { label: 'Multi-delivery Building', value: yn( geo.building_default, 'Yes', 'No' ) },

            /* Address Validation */
            { label: 'Address Status',          value: validationVal                 },
            { label: 'DPV Footnotes',           value: geo.dpv_footnotes             },
            { label: 'CMRA Address',            value: yn( geo.dpv_cmra,    'Yes (Commercial Mail Receiving Agency)', 'No' ) },
            { label: 'Vacant',                  value: yn( geo.dpv_vacant,  'Yes', 'No' ) },
            { label: 'No-Stat Address',         value: yn( geo.dpv_no_stat, 'Yes (not currently deliverable)', 'No' ) },
            { label: 'Active',                  value: yn( geo.active,      'Yes', 'No' ) },
            { label: 'EWS Match',               value: yn( geo.ews_match,   'Yes (address change pending)', 'No' ) },

            /* Geocoding */
            { label: 'Latitude',                value: geo.lat                       },
            { label: 'Longitude',               value: geo.lon                       },
            { label: 'Time Zone',               value: geo.time_zone                 },
            { label: 'UTC Offset',              value: utcDisplay                    },
            { label: 'Daylight Saving',         value: yn( geo.dst, 'Yes', 'No' )   },

            /* Building data (OpenStreetMap) */
            { label: 'Year Built',              value: prop.year_built               },
            { label: 'Building Type',           value: prop.building_type            },
            { label: 'Floors / Levels',         value: prop.building_levels ? prop.building_levels + ' floor(s)' : null },
        ];

        var html = '';
        cells.forEach( function ( c ) {
            var val = ( c.value !== undefined && c.value !== null && String( c.value ).trim() !== '' )
                ? c.value : null;
            var valHtml;
            if ( val ) {
                valHtml = '<div class="re-plu-detail-value">' + esc( String( val ) ) + '</div>';
            } else {
                valHtml = '<div class="re-plu-detail-value not-available">' + esc( c.na_note || 'Not available' ) + '</div>';
            }
            html +=
                '<div class="re-plu-detail-cell">' +
                '<div class="re-plu-detail-label">' + esc( c.label ) + '</div>' +
                valHtml +
                '</div>';
        } );

        $( '#re-plu-property-details' ).html( html );

        /* Square Footage & Tenants notice — merged into this section */
        $( '#re-plu-sqft-note' ).html(
            '<div class="re-plu-api-notice">' +
            '<div class="re-plu-api-notice-icon">&#9432;</div>' +
            '<div>' +
            '<strong>Square Footage &amp; Available Tenants</strong>' +
            'Not available from Smarty. Review ' +
            '<a href="' + esc( urls.loopnet || '#' ) + '" target="_blank" rel="noopener noreferrer">LoopNet</a>' +
            ' for available units, tenants, and lease rates for this property.' +
            '</div>' +
            '</div>'
        );
    }

    /* ---- Permit links ---- */
    function renderPermits( permits ) {
        if ( ! permits || ! permits.length ) {
            $( '#re-plu-permits-section' ).html(
                '<p style="color:var(--re-gray-400);font-size:13px;margin:0;">No permit portals found for this location.</p>'
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
