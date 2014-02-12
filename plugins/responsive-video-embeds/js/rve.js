/**
 * Responsive Video Embeds
 *
 * Create and maintained by Kevin Leary, www.kevinleary.net, WordPress development in Boston, MA
 */
( function ( $ ) {

	var responsiveVideos = {
		config: {
			container: $( '.rve' ),
			selector: 'object, embed, iframe'
		},

		init: function ( config ) {
			if ( responsiveVideos.config.container.length > 0 ) {
				$( window ).on( 'resize load', responsiveVideos.resize );
			}
		},

		resize: function () {
			$( responsiveVideos.config.selector, responsiveVideos.config.container ).each( function () {

				// Attrs
				var $this = $( this );
				var width = $this.parent().width();
				var height = Math.round( width * 0.5625 );
				$this.attr( 'height', height );
				$this.attr( 'width', width );
			} );
		}
	};

	responsiveVideos.init();

} )( jQuery );
