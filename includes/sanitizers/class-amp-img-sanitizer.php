<?php

require_once( AMP__DIR__ . '/includes/sanitizers/class-amp-base-sanitizer.php' );
require_once( AMP__DIR__ . '/includes/utils/class-amp-image-dimension-extractor.php' );

/**
 * Converts <img> tags to <amp-img> or <amp-anim>
 */
class AMP_Img_Sanitizer extends AMP_Base_Sanitizer {
	public static $tag = 'img';

	private static $anim_extension = '.gif';

	private static $script_slug = 'amp-anim';
	private static $script_src = 'https://cdn.ampproject.org/v0/amp-anim-0.1.js';

	public function sanitize() {
		$nodes = $this->dom->getElementsByTagName( self::$tag );
		$num_nodes = $nodes->length;
		if ( 0 === $num_nodes ) {
			return;
		}

		for ( $i = $num_nodes - 1; $i >= 0; $i-- ) {
			$node = $nodes->item( $i );
			$old_attributes = AMP_DOM_Utils::get_node_attributes_as_assoc_array( $node );

			/*
			 Added data-original for lazy-loaded imgs. TODO: change for the new theme
			 */
			if ( ! array_key_exists( 'src', $old_attributes ) && ! array_key_exists('data-original', $old_attributes)) {
				$node->parentNode->removeChild( $node );
				continue;
			}

			$new_attributes = $this->filter_attributes( $old_attributes );
			if ( ! isset( $new_attributes['width'] ) || ! isset( $new_attributes['height'] ) ) {
				$dimensions = AMP_Image_Dimension_Extractor::extract( $new_attributes['src'] );
				if ( is_array( $dimensions ) ) {
					$new_attributes['width'] = $dimensions[0];
					$new_attributes['height'] = $dimensions[1];
				}
			}

			$new_attributes = $this->enforce_sizes_attribute( $new_attributes );

			if ( $this->is_gif_url( $new_attributes['src'] ) ) {
				$this->did_convert_elements = true;
				$new_tag = 'amp-anim';
			} else {
				$new_tag = 'amp-img';
			}

			$new_node = AMP_DOM_Utils::create_node( $this->dom, $new_tag, $new_attributes );
			$node->parentNode->replaceChild( $new_node, $node );
		}
	}

	public function get_scripts() {
		if ( ! $this->did_convert_elements ) {
			return array();
		}

		return array( self::$script_slug => self::$script_src );
	}

	private function filter_attributes( $attributes ) {
		$out = array();

		foreach ( $attributes as $name => $value ) {
			switch ( $name ) {
				case 'src':
				case 'alt':
				case 'width':
				case 'height':
				case 'class':
				case 'srcset':
				case 'sizes':
					$out[ $name ] = $value;
					break;
				/* ADDED FOR LAZY LOADED IMAGES,
				  TODO: change for the new theme.
				*/
				case 'data-original':
					$out['src'] = $value;
					break;
				default;
					break;
			}
		}
		return $out;
	}

	private function is_gif_url( $url ) {
		$ext = self::$anim_extension;
		$path = parse_url( $url, PHP_URL_PATH );
		return $ext === substr( $path, -strlen( $ext ) );
	}
}
