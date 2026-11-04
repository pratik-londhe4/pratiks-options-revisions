/**
 * Pratiks Options Revisions Admin Script
 */
( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		var $modal = $( '#optrev-diff-modal' );
		var $modalTitle = $( '#optrev-modal-title' );
		var $modalDiff = $modal.find( '.optrev-modal-diff' );
		var $modalSpinner = $modal.find( '.optrev-modal-spinner' );
		var previousActiveElement = null;

		function openModal( optionName ) {
			previousActiveElement = document.activeElement;
			$modalTitle.text( optionName + ' - ' + optionsRevisionsL10n.loading );
			$modalDiff.empty();
			$modalSpinner.show();
			$modal.fadeIn( 150 );
			$modal.find( '.optrev-modal-close' ).focus();

			if ( window.wp && window.wp.hooks ) {
				window.wp.hooks.doAction( 'optionsRevisions.onDiffOpen', optionName );
			}
		}

		function closeModal() {
			$modal.fadeOut( 150, function() {
				$modalDiff.empty();
				if ( previousActiveElement ) {
					previousActiveElement.focus();
				}
			} );

			if ( window.wp && window.wp.hooks ) {
				window.wp.hooks.doAction( 'optionsRevisions.onDiffClose' );
			}
		}

		$( document ).on( 'click', '.optrev-view-diff', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var id = $btn.data( 'id' );
			var $row = $btn.closest( 'tr' );
			var optionName = $row.find( '.column-option_name strong' ).text() || 'Option';

			openModal( optionName );

			$.post( optionsRevisionsL10n.ajaxUrl, {
				action: 'pratiks_options_revisions_get_diff',
				id: id,
				nonce: optionsRevisionsL10n.diffNonce
			} ).done( function( response ) {
				$modalSpinner.hide();
				if ( response.success && response.data ) {
					$modalTitle.text( response.data.option );
					$modalDiff.html( response.data.diff_html );
				} else {
					$modalDiff.html( '<p class="notice notice-error">' + optionsRevisionsL10n.error + '</p>' );
				}
			} ).fail( function() {
				$modalSpinner.hide();
				$modalDiff.html( '<p class="notice notice-error">' + optionsRevisionsL10n.error + '</p>' );
			} );
		} );

		$( document ).on( 'click', '.optrev-modal-close, .optrev-modal-overlay', function( e ) {
			e.preventDefault();
			closeModal();
		} );

		$( document ).on( 'keydown', function( e ) {
			if ( 27 === e.which && $modal.is( ':visible' ) ) {
				closeModal();
			}
		} );
	} );
} )( jQuery );
