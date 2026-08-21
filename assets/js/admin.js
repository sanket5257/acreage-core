/* Acreage Core — gallery picker and the cattle/game field toggle. */
( function ( $ ) {
	'use strict';

	$( function () {
		var $list  = $( '#acreage-gallery' );
		var $input = $( '#acreage_gallery' );
		var frame;

		/* ---------------------------------------------------- gallery */

		function sync() {
			var ids = $list.children( '.acreage-gallery__item' ).map( function () {
				return $( this ).data( 'id' );
			} ).get();

			$input.val( ids.join( ',' ) );
		}

		$( '#acreage-gallery-add' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: acreageListings.chooseTitle,
				button: { text: acreageListings.chooseButton },
				library: { type: 'image' },
				multiple: 'add'
			} );

			frame.on( 'select', function () {
				var existing = $input.val() ? $input.val().split( ',' ) : [];

				frame.state().get( 'selection' ).each( function ( attachment ) {
					var data = attachment.toJSON();

					if ( existing.indexOf( String( data.id ) ) !== -1 ) {
						return; // Already in the gallery.
					}

					var src = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;

					$list.append(
						$( '<li class="acreage-gallery__item" />' )
							.attr( 'data-id', data.id )
							.append( $( '<img />' ).attr( 'src', src ).attr( 'alt', '' ) )
							.append( '<button type="button" class="acreage-gallery__remove">&times;</button>' )
					);
				} );

				sync();
			} );

			frame.open();
		} );

		$list.on( 'click', '.acreage-gallery__remove', function () {
			$( this ).closest( '.acreage-gallery__item' ).remove();
			sync();
		} );

		if ( $list.length && $.fn.sortable ) {
			$list.sortable( { items: '.acreage-gallery__item', cursor: 'move', update: sync } );
		}

		/* ------------------------------------------- cattle/game fields */

		/**
		 * Hide the Wildlife section on cattle farms, so the client never sees a
		 * field that does not apply to what they are entering.
		 */
		function applyCategory() {
			var isGame = false;
			var isSet  = false;

			$( '#listing_categorychecklist input:checked' ).each( function () {
				var label = $.trim( $( this ).parent().text() ).toLowerCase();
				isSet = true;
				if ( label.indexOf( 'game' ) !== -1 ) {
					isGame = true;
				}
			} );

			// With nothing chosen yet, show everything rather than hiding fields
			// the author has not had a chance to make relevant.
			$( '.acreage-section[data-applies="game"]' ).toggle( isGame || ! isSet );
		}

		$( document ).on( 'change', '#listing_categorychecklist input', applyCategory );
		applyCategory();
	} );
} )( jQuery );

/* Quick add — main photograph picker. */
( function ( $ ) {
	'use strict';

	$( function () {
		var $field   = $( '#acreage_thumbnail' );
		var $preview = $( '#acreage-quick-preview' );
		var $clear   = $( '#acreage-quick-clear' );
		var picker;

		if ( ! $field.length ) {
			return;
		}

		function show( id, src ) {
			$field.val( id || '' );
			$preview.html( src ? $( '<img />' ).attr( 'src', src ).attr( 'alt', '' ) : '' );
			$clear.prop( 'hidden', ! id );
		}

		$( '#acreage-quick-choose' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( ! picker ) {
				picker = wp.media( {
					title: acreageListings.chooseTitle,
					button: { text: acreageListings.chooseButton },
					library: { type: 'image' },
					multiple: false
				} );

				picker.on( 'select', function () {
					var data = picker.state().get( 'selection' ).first().toJSON();
					var src  = data.sizes && data.sizes.medium ? data.sizes.medium.url : data.url;
					show( data.id, src );
				} );
			}

			picker.open();
		} );

		$clear.on( 'click', function ( e ) {
			e.preventDefault();
			show( '', '' );
		} );
	} );
} )( jQuery );
