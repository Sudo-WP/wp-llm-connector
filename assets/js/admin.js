jQuery( document ).ready( function( $ ) {
	'use strict';

	// Confirm before disabling connector if it's currently enabled.
	var $enabledCheckbox = $( 'input[name="wp_llm_connector_settings[enabled]"]' );
	$enabledCheckbox.data( 'initial-state', $enabledCheckbox.is( ':checked' ) );

	$( 'form' ).on( 'submit', function( e ) {
		var wasEnabled = $enabledCheckbox.data( 'initial-state' );

		if ( wasEnabled && ! $enabledCheckbox.is( ':checked' ) ) {
			if ( ! confirm( 'Are you sure you want to disable the LLM Connector? All API access will be blocked.' ) ) {
				e.preventDefault();
				$enabledCheckbox.prop( 'checked', true );
				return false;
			}
		}

		$enabledCheckbox.data( 'initial-state', $enabledCheckbox.is( ':checked' ) );
	} );

	// Warning when disabling read-only mode.
	$( 'input[name="wp_llm_connector_settings[read_only_mode]"]' ).on( 'change', function() {
		if ( ! $( this ).is( ':checked' ) ) {
			if ( ! confirm( 'WARNING: Disabling read-only mode will allow LLMs to make changes to your site. This is not recommended unless you fully trust the LLM provider and have backups in place.\n\nContinue anyway?' ) ) {
				$( this ).prop( 'checked', true );
			}
		}
	} );
} );
