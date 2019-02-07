<?php
/*
Plugin Name: Media Lazy Load
Plugin URI: https://wordpress.org/plugins/media-lazy-load/
Description: A simple plugin to help reduce initial page bandwidth for web; incorporates lazysizes.js which uses the browsers intersection API to load media when necessary rather than load all media on page load.
Version: 0.2.1
Text Domain: media-lazy-load
Domain Path: /languages
Author: Sean Hayes
Author URI: https://seanhayes.biz/
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace MediaLazyLoad;

use DOMDocument;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'MLL_FILE', __FILE__ );

if ( ! class_exists( 'MediaLazyLoad' ) ) {
	class MediaLazyLoad {

		private static $instance;
		private $mll_lazy_class;
		private $mll_scripts;

		public static function get_instance() {

			if ( ! isset( self::$instance ) ) {
				self::$instance = new MediaLazyLoad();
				self::$instance->setup_actions();
				self::$instance->setup_filters();
			}

			return self::$instance;
		}

		public function __construct(  ) {
			$this->mll_lazy_class = 'lazyload';
			$this->mll_scripts    = array(
				'media-lazy-load-loader',
				'media-lazy-load-unveilhooks',
			);
		}
		private function setup_actions() {
			add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_scripts' ) );
		}

		private function setup_filters() {
			if( !is_admin() ) {
				add_filter( 'script_loader_tag', array( $this, 'filter_script_async' ), 10, 6 );
				add_filter( 'get_image_tag', array( $this, 'lazy_image_data_src' ), 10, 6 );
				add_filter( 'wp_get_attachment_image_attributes', array( $this, 'lazy_image_attributes' ), 10, 3 );
				add_filter( 'get_image_tag_class', array( $this, 'lazy_img_tag_markup' ), 10, 4 );
				add_filter( 'get_avatar', array( $this, 'lazy_img_avatar_tag_markup' ), 10, 8 );
				add_filter( 'the_content', array( $this, 'lazy_process_media_tags_content' ) );
				add_filter( 'wp_video_shortcode', array( $this, 'lazy_process_video_tags_content' ), 10, 54 );
				add_filter( 'wp_kses_allowed_html', array( $this, 'filter_wp_kses_allowed_custom_attributes' ), 10, 2 );
			}
		}

		/**
		 * Enqueue Lazy sizes scripts
		 */
		public function action_enqueue_scripts() {
			if ( is_feed() || is_admin() || is_customize_preview() ) {
				return;
			}
			wp_enqueue_script( 'media-lazy-load-unveilhooks', plugin_dir_url( MLL_FILE ) . 'assets/js/ls.unveilhooks.min.js', [], null, true );
			wp_enqueue_script( 'media-lazy-load-loader', plugin_dir_url( MLL_FILE ) . 'assets/js/lazysizes.min.js', [], null, true );
			wp_add_inline_style( 'media-lazy-load-loader', '
/* fade image in after load */
.lazyload,
.lazyloading {
opacity: 0;
}
.lazyloaded {
opacity: 1;
transition: opacity 300ms;
}
' );
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
		public function lazy_image_data_src( $html, $id, $alt, $title, $align, $size ) {
			$doing_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
			if ( is_feed() || is_admin() || $doing_rest || is_customize_preview() ) {
				return $html;
			}
			$html = str_replace( ' src=', ' data-src=', $html );

			return $html;
		}

		/**
		 * @param $html
		 *
		 * @return mixed|string|string[]|null
		 * When displaying the_content find all img tags, save them.
		 * Then process for lazy load and return modified html
		 * Check for REST request and do not add the class so as not to
		 * disrupt the editing experience for Gutenberg (is_admin is not in play)
		 */
		public function lazy_process_media_tags_content( $html ) {
			$doing_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
			if ( is_feed() || is_admin() || $doing_rest || is_customize_preview() ) {
				return $html;
			}
			// Handle images
			$result = preg_match_all( '/<img [^>]+>/', $html, $matches ); // gets all image tags
			// Now search / replace tag with a hash to find later
			if ( $result ) {
				foreach ( $matches[0] as $img_to_process ) {
					$class = preg_match( '/class="([^"]+)"/', $img_to_process, $match_src ) ? $match_src[1] : '';
					if ( stristr( $class, $this->mll_lazy_class ) === false ) {
						$original_image = $img_to_process;
						$class_lazy     = $class . ' ' . $this->mll_lazy_class;
						// If markup already includes a srcset then do not change src to data-src
						$img_to_process = preg_replace( '/srcset=/', 'data-srcset=', $img_to_process, -1, $number_replaced );
						if ( ! $number_replaced ) {
							$img_to_process = preg_replace( '/src=/', 'data-src=', $img_to_process );
						}
						// Remove src entry to prevent duplicated img loading
						if ( $number_replaced ) {
							$img_to_process = preg_replace( '/src=/', '', $img_to_process );
						}
						$img_to_process = preg_replace( '/sizes=/', 'data-sizes=', $img_to_process );
						$html_img       = empty($class) ? str_replace( 'img', 'img class="'.$this->mll_lazy_class . '"', $img_to_process ) : str_replace( $class, $class_lazy, $img_to_process );
						$html           = str_replace( $original_image, $html_img, $html );
					}
				}
				unset( $result );
				unset( $matches );
				unset( $html_img );
				unset( $class_lazy );
				unset( $match_src );
			}
			// Handle iframes
			$result = preg_match_all( '/<iframe [^>]+>/', $html, $matches ); // gets all iframe tags
			if ( $result ) {
				foreach ( $matches[0] as $iframe_to_process ) {
					$class = preg_match( '/class="([^"]+)"/', $iframe_to_process, $match_src ) ? $match_src[1] : '';
					if ( stristr( $class, $this->mll_lazy_class ) === false ) {
						$original_iframe   = $iframe_to_process;
						$class_lazy        = $class . ' ' . $this->mll_lazy_class;
						$iframe_to_process = preg_replace( '/src=/', 'data-src=', $iframe_to_process );
						$iframe_to_process = preg_replace( '/srcset=/', 'data-srcset=', $iframe_to_process );
						$iframe_to_process = preg_replace( '/sizes=/', 'data-sizes=', $iframe_to_process );
						if (empty($class)) {
							$iframe_to_process = str_replace( 'iframe', 'iframe class="'.$this->mll_lazy_class . '"', $iframe_to_process );
						}
						$html_iframe       = str_replace( $class, $class_lazy, $iframe_to_process );
						$html              = str_replace( $original_iframe, $html_iframe, $html );
					}
				}
			}
			// Handle background / cover images from Gutenberg
			$html  = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");
			$document = new DOMDocument();
			libxml_use_internal_errors(true);
			$document->loadHTML(utf8_decode($html));
			$divs = $document->getElementsByTagName('div' );
			$bg_image_pattern = '/background(?:\-image)?\:(?:.*?url\((?:")?)([^\)|"]+)(?:\))*/';
			foreach( $divs as $div ) {
				if ( false !== stristr( $div->getAttribute( 'class' ), 'wp-block-cover' ) ) {
					$div_style = $div->getAttribute( 'style' );
					if ( $div_style ) {
						$url = preg_match( $bg_image_pattern, $div_style, $matches_div );
						if ( 1 === $url ) {
							$div->setAttribute( 'data-bg', $matches_div[1] );
							$replaced_style = str_replace( $matches_div[0], '', $div_style );
							$div->setAttribute( 'style', $replaced_style );
						}
						$div_classes = $div->getAttribute( 'class' );
						if ( stristr( $div_classes, $this->mll_lazy_class ) === false ) {
							$div_classes .= ' ' . $this->mll_lazy_class;
						}
						$div->setAttribute( 'class', $div_classes );
					}
				}
			}
			return $document->saveHTML();
		}

		/**
		 * @param $output
		 * @param $atts
		 * @param $video
		 * @param $post_id
		 * @param $library
		 *
		 * @return mixed
		 *              Handle standard video embed, see if we find a shortcode class entry, as poster
		 *              and a preload and set them to match lazysizes needs before returning
		 *              Or return original output untouched
		 */
		public function lazy_process_video_tags_content( $output, $atts, $video, $post_id, $library ) {
			$result = preg_match( '/(?:<video)(?:\s)+class=(?:"[^"]+)"/', $output, $matches );
			if( $result ) {
				$class_update = str_replace( 'wp-video-shortcode', 'wp-video-shortcode ' . $this->mll_lazy_class, $output, $count );
				if ( $count ) {
					unset( $count );
					$output = $class_update;
					$poster_update  = str_replace( 'poster', 'data-poster', $class_update, $count );
					if ( $count ) {
						unset( $count );
						$output = $poster_update;
						$preload_update = str_replace( 'preload="metadata"', 'preload="none"', $poster_update, $count );
					}
					if ( $count ) {
						$output = $preload_update;
					}
				}
			}
			return $output;
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
			$doing_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
			if ( is_admin() || is_feed() || $doing_rest || is_customize_preview() ) {
				return $attr;
			}
			$attr['data-src'] = $attr['src'];
			$attr['data-srcset'] = $attr['srcset'];
			unset( $attr['src'] );
			unset( $attr['srcset'] );
			if ( stristr( $attr['class'], $this->mll_lazy_class ) === false ) {
				$attr['class'] .= ' '.$this->mll_lazy_class;
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
			$doing_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
			if ( is_admin() || is_feed() || $doing_rest || is_customize_preview() ) {
				return $class;
			}
			if ( stristr( $class, $this->mll_lazy_class ) === false ) {
				$class .= ' '.$this->mll_lazy_class;
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
			$doing_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
			if ( is_admin() || is_feed() || $doing_rest || is_customize_preview() ) {
				return $avatar;
			}
			preg_match( '/class=[\'\"]([^\'|\"]+)/', $avatar, $matches );
			if ( stristr( $avatar, $this->mll_lazy_class ) === false ) {
				$original = $matches[1];
				$replace = $matches[1].' '.$this->mll_lazy_class;
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
		public function filter_wp_kses_allowed_custom_attributes( $allowed_html, $context ) {
			if ( is_array( $context ) ) {
				return $allowed_html;
			}
			$allowed_html = [
				'img' => array_merge( $allowed_html['img'], [
					'data-src' => true,
				] ),
			];
			return $allowed_html;
		}
		/**
		 * Load lazysizes scripts async
		 * @param $tag
		 * @param $handle
		 * @param $src
		 *
		 * @return string|string[]|null
		 */
		public function filter_script_async( $tag, $handle, $src ) {
			if ( ! in_array( $handle, $this->mll_scripts, true ) ) {
				return $tag;
			}

			return preg_replace( '/^<script /i', '<script async="async" ', $tag );
		}
	}
	MediaLazyLoad::get_instance();
}
