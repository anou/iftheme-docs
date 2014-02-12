<?php
/*
Plugin Name: Responsive Video Embeds
Version: 1.2.5
Plugin URI: http://www.kevinleary.net/
Description: This plugin will automatically resize video embeds, objects and other iframes in a responsive fashion.
Author: Kevin Leary
Author URI: http://www.kevinleary.net
License: GPL2

Copyright 2013 Kevin Leary  (email : info@kevinleary.net)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'KplResponsiveVideoEmbeds' ) ) {

	/**
	 * Responsive Embeds in WordPress
	 *
	 * Custom embed sizing for basic listing template
	 */
	class KplResponsiveVideoEmbeds {

		// Constants & variables
		static $do_enqueue;
		static $v = '1.2.4';

		/**
		 * Setup the object
		 *
		 * Attached filters and actions to hook into WordPress
		 */
		function __construct() {
			add_filter( 'embed_oembed_html', array( __CLASS__, 'embed_output' ), 1, 3 );
			add_action( 'init', array( __CLASS__, 'register_enqueue' ) );
			add_filter( 'wp_footer', array( __CLASS__, 'print_enqueue' ) );

			// BuddyPress support; thanks Alex Berthelsen
			if ( has_filter( 'bp_embed_oembed_html' ) )
				add_filter( 'bp_embed_oembed_html', array( __CLASS__, 'embed_output' ), 1, 3 );
		}


		/**
		 * Load JS & CSS
		 */
		static function register_enqueue() {
			wp_register_script( 'responsive-video-js', plugins_url( 'js/rve.min.js', __FILE__ ), array( 'jquery' ), self::$v, true );
		}


		/**
		 * Load JS
		 */
		static function print_enqueue() {
			if ( ! self::$do_enqueue )
				return;

			wp_print_scripts( 'responsive-video-js' );
		}

		/**
		 * Modify embeds output
		 *
		 * Wrap the video embed in a container for scaling
		 */
		static function embed_output( $html, $url, $attr ) {

			// Queue up our CSS/JS
			self::$do_enqueue = true;

			// Only run this process for embeds that don't required fixed dimensions
			$resize = false;
			$accepted_providers = array(
				'youtube',
				'vimeo',
				'slideshare',
				'dailymotion',
				'viddler.com',
				'hulu.com',
				'blip.tv',
				'revision3.com',
				'funnyordie.com',
				'wordpress.tv',
				'scribd.com',
			);

			// Check each provider
			foreach ( $accepted_providers as $provider ) {
				if ( strstr( $url, $provider ) ) {
					$resize = true;
					break;
				}
			}

			// Cleanup output to avoid wpautop() conflicts
			$embed = preg_replace( '/\s+/', '', $html ); // Clean-up whitespace
			$embed = trim( $embed );
			global $content_width;

			$html = '<div class="rve" data-content-width="' . $content_width . '">' . $html . '</div>';
			$html .= '<!-- Responsive Video Embeds plugin by www.kevinleary.net -->';

			return $html;
		}
	}

	// Autoload the class
	$responsive_video_embeds = new KplResponsiveVideoEmbeds();

}
