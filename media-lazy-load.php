<?php
/*
Plugin Name: Media Lazy Load
Plugin URI: https://wordpress.org/plugins/media-lazy-load/
Description: A simple plugin to help reduce initial page bandwidth for web; uses the browsers intersection API to load media when necessary rather than load all media on page load.
Version: 0.11
Text Domain: media-lazy-load
Domain Path: /languages
Author: Sean Hayes
Author URI: https://seanhayes.biz/
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'MLL_FILE', __FILE__ );

if ( ! class_exists( 'MediaLazyLoad' ) ) {
	class MediaLazyLoad {

		private static $instance;
		private $lazy_class;

		public static function get_instance() {

			if ( ! isset( self::$instance ) ) {
				self::$instance = new MediaLazyLoad();
				self::$instance->setup_actions();
				self::$instance->setup_filters();
			}

			return self::$instance;
		}

		public function __construct(  ) {
			$this->lazy_class = 'lazyload';
		}
		private function setup_actions() {
			add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_scripts' ) );
		}

		private function setup_filters() {
			if(!is_admin()) {
				add_filter( 'get_image_tag', array( $this, 'lazy_data_src' ), 10, 6 );
				add_filter( 'wp_get_attachment_image_attributes', array( $this, 'lazy_image_attributes' ), 10, 3 );
				add_filter( 'get_image_tag_class', array( $this, 'lazy_img_tag_markup' ), 10, 4 );
				add_filter( 'get_avatar', array( $this, 'lazy_img_avatar_tag_markup' ), 10, 8 );
				add_filter( 'the_content', array( $this, 'lazy_process_img_tags_content' ) );
				add_filter( 'wp_kses_allowed_html', array( $this, 'filter_wp_kses_allowed_custom_attributes' ) );
			}
		}

		public function action_enqueue_scripts() {
			wp_enqueue_script( 'lazy-load-unveilhooks', plugin_dir_url( __FILE__ ) . 'assets/js/ls.unveilhooks.min.js', [], null, true );
			wp_enqueue_script( 'lazy-load-loader', plugin_dir_url( __FILE__ ) . 'assets/js/lazysizes.min.js', [], null, true );
		}

		/**
		 * @param $html
		 * @param $id
		 * @param $alt
		 * @param $title
		 * @param $align
		 * @param $size
		 *
		 * @return mixed
		 *              Switch src attribute of image tag to data-src for lazy load
		 */
		public function lazy_data_src( $html, $id, $alt, $title, $align, $size ) {
			if ( is_admin() ) {
				return $html;
			}
			$html = str_replace( ' src=', ' data-src=', $html );

			return $html;
		}

		/**
		 * @param $html
		 *
		 * @return mixed|string|string[]|null
		 * When displaying the content find all img tags, save them
		 * process for lazy load and return modified html
		 *
		 */
		public function lazy_process_img_tags_content( $html ) {
			if ( is_feed() ) {
				return $html;
			}
			// Handle images
			$result = preg_match_all( '/<img [^>]+>/', $html, $matches ); // gets all image tags
			// Now search / replace tag with a hash to find later
			if ( $result ) {
				foreach ( $matches[0] as $img_to_process ) {
					$class = preg_match( '/class="([^"]+)"/', $img_to_process, $match_src ) ? $match_src[1] : '';
					if ( stristr( $class, $this->lazy_class ) === false ) {
						$saved_img_hash = '#' . md5( $img_to_process ) . '#';
						$html           = str_replace( $img_to_process, $saved_img_hash, $html ); // Save place of original markup
						$class_lazy     = $class . ' ' . $this->lazy_class;
						// If markup already includes a srcset then do not change src to data-src
						$img_to_process = preg_replace( '/srcset=/', 'data-srcset=', $img_to_process, -1, $number_replaced );
						if ( ! $number_replaced ) {
							$img_to_process = preg_replace( '/src=/', 'data-src=', $img_to_process );
						}
						if ( $number_replaced ) {
							$img_to_process = preg_replace( '/src=/', '', $img_to_process );
						}
						$img_to_process = preg_replace( '/sizes=/', 'data-sizes=', $img_to_process );
						$html_img       = empty($class) ? str_replace( 'img', 'img class="'.$this->lazy_class.'"', $img_to_process ) : str_replace( $class, $class_lazy, $img_to_process );
						$html           = str_replace( $saved_img_hash, $html_img, $html );
					}
				}
			}
			// Handle iframes
			$result = preg_match_all( '/<iframe [^>]+>/', $html, $matches ); // gets all iframe tags
			if ( $result ) {
				foreach ( $matches[0] as $iframe_to_process ) {
					$class = preg_match( '/class="([^"]+)"/', $iframe_to_process, $match_src ) ? $match_src[1] : '';
					if ( stristr( $class, $this->lazy_class ) === false ) {
						$saved_img_hash = '#' . md5( $iframe_to_process ) . '#';
						$html           = str_replace( $iframe_to_process, $saved_img_hash, $html ); // Save place of original markup
						$class_lazy     = $class . ' ' . $this->lazy_class;
						$iframe_to_process = preg_replace( '/src=/', 'data-src=', $iframe_to_process );
						$iframe_to_process = preg_replace( '/srcset=/', 'data-srcset=', $iframe_to_process );
						$iframe_to_process = preg_replace( '/sizes=/', 'data-sizes=', $iframe_to_process );
						if (empty($class)) {
							$iframe_to_process = str_replace( 'iframe', 'iframe class="'.$this->lazy_class.'"', $iframe_to_process );
						}
						$html_iframe       = str_replace( $class, $class_lazy, $iframe_to_process );
						$html           = str_replace( $saved_img_hash, $html_iframe, $html );
					}
				}
			}

			// Handle videos
			// Handle switch to WebP

			return $html;
		}

		/**
		 * @param $attr
		 * @param $attachment
		 * @param $size
		 *
		 * @return mixed
		 *              Add lazy loading class to image markup and related data-src too
		 */
		public function lazy_image_attributes( $attr, $attachment, $size ) {
			if ( is_admin() || is_feed() ) {
				return $attr;
			}
			$attr['data-src'] = $attr['src'];
			$attr['data-srcset'] = $attr['srcset'];
			unset( $attr['src'] );
			unset( $attr['srcset'] );
			if ( stristr( $attr['class'], $this->lazy_class ) === false ) {
				$attr['class'] .= ' '.$this->lazy_class;
			}

			return $attr;
		}

		/**
		 * @param $class
		 * @param $id
		 * @param $align
		 * @param $size
		 *
		 * @return string
		 *               Add lazy class to image tag markup
		 */
		public function lazy_img_tag_markup( $class, $id, $align, $size ) {
			if ( is_admin() || is_feed() ) {
				return $class;
			}
			if ( stristr( $class, $this->lazy_class ) === false ) {
				$class .= ' '.$this->lazy_class;
			}

			return $class;
		}

		/**
		 * @param $avatar
		 * @param $id_or_email
		 * @param $size
		 * @param $default
		 * @param $alt
		 * @param $args
		 *
		 * @return mixed
		 *              Add lazy load support to avatars
		 */
		public function lazy_img_avatar_tag_markup( $avatar, $id_or_email, $size, $default, $alt, $args ) {
			if ( is_admin() || is_feed() ) {
				return $avatar;
			}
			preg_match( '/class=[\'\"]([^\'|\"]+)/', $avatar, $matches );
			if ( stristr( $avatar, $this->lazy_class ) === false ) {
				$original = $matches[1];
				$replace = $matches[1].' '.$this->lazy_class;
				$avatar = str_replace($original,$replace,$avatar);
			}

			return $avatar;
		}

		/**
		 * @param $allowed_html
		 *
		 * @return array
		 *              Allow data-src through kses for image tags.
		 */
		public function filter_wp_kses_allowed_custom_attributes( $allowed_html ) {
			$mll_custom_element_attributes = [
				'img' => array_merge( $allowed_html['img'], [
					'data-src' => true,
				] ),
			];
			return array_merge( $allowed_html, $mll_custom_element_attributes );
		}
	}
	MediaLazyLoad::get_instance();
}
