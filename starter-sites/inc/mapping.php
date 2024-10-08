<?php
namespace Starter_Sites;

defined( 'ABSPATH' ) || exit;

class Mapping {

	/**
	 * Check if a template design pattern exists.
	 *
	 * @param string     $slug WP post name.
	 * @param string     $type WP post type.
	 * @param array      $meta An associative array of WP_Meta_Query arguments
	 * @return int/bool  WP post id if exists, false otherwise.
	 */
	public function template_design_exists( $slug, $type, $meta ) {
		$args = array(
			'post_type' => $type,
			'post_status' => 'private',
			'name' => $slug,
			'meta_query' => $meta,
			'posts_per_page' => 1,
		);
		$posts = new \WP_Query( $args );
		if ( $posts->have_posts() ) {
			$posts->the_post();
			return $posts->post->ID;
		} else {
			return false;
		}
	}

	public function content( $log ) {
		// map options
		if ( isset($log['map_options']) && !empty($log['map_options']) ) {
			foreach ( $log['map_options'] as $option_name => $option_value ) {
				if ( $option_name === 'show_on_front' ) {
					update_option( 'show_on_front', $option_value );
				} else {
					if ( $option_name === 'page_on_front' || $option_name === 'page_for_posts' ) {
						$option_map = 'map_posts';
					} else {
						$option_map = 'map_attachments';
					}
					if ( isset( $log[$option_map][$option_value]['new_id'] ) ) {
						update_option( $option_name, (int) $log[$option_map][$option_value]['new_id'] );
					}
				}
			}
		}
		// create an array of urls for mapping reference
		$demo_site_url = $log['site']['demo_url'];
		$urls_map = array();
		$image_sizes_map = array();
		if ( isset( $log['map_posts'] ) ) {
			foreach ( $log['map_posts'] as $item ) {
				if ( $item['demo_url'] !== $demo_site_url ) {
					$urls_map[$item['demo_url']] = $item['new_url'];
				}
			}
		}
		if ( isset( $log['map_attachments'] ) ) {
			foreach ( $log['map_attachments'] as $item ) {
				$urls_map[$item['demo_link']] = $item['new_link'];
				$urls_map[$item['demo_url']] = $item['new_url'];

				if ( isset( $item['sizes'] ) && is_array( $item['sizes'] ) ) {
					foreach ( $item['sizes'] as $image_url_old => $image_url_new ) {
						$image_sizes_map[$image_url_old] = $image_url_new;
					}
				}

			}
		}
		if ( isset( $log['map_font_faces'] ) ) {
			foreach ( $log['map_font_faces'] as $item ) {
				$urls_map[$item['demo_url']] = $item['new_url'];
			}
		}
		if ( isset( $log['map_terms'] ) ) {
			foreach ( $log['map_terms'] as $item ) {
				if ( isset($item['demo_url']) && $item['demo_url'] !== '' && isset($item['new_url']) && $item['new_url'] !== '' ) {
					$urls_map[$item['demo_url']] = $item['new_url'];
				}
			}
		}
		$urls_map[$demo_site_url] = $log['site']['new_url'];
		// loop thru all the added/updated content
		$content_and_design = array_replace( $log['content'], $log['design'] );
		foreach ( $content_and_design as $content_key ) {
			if ( isset($content_key['new_id']) ) {
				$post = get_post( $content_key['new_id'] );
				$content = $post->post_content;
				$post_type = $post->post_type;
				$post_parent = $post->post_parent;
				if ( $post_parent ) {
					if ( isset( $content_and_design[$post_parent]['new_id'] ) ) {
						$new_post_parent = $content_and_design[$post_parent]['new_id'];
					} else {
						$new_post_parent = NULL;
					}
				} else {
					$new_post_parent = NULL;
				}
				if ( !empty($content)) {
					if ( 'wp_font_face' === $post_type ) { // wp_font_face does not contain block markup, but does contain JSON encoded data
						$new_content = $this->replace_font_face_src( $content, $urls_map );
					} else {
						// parse blocks
						$content_parsed = parse_blocks( $content );
						// map content in blocks
						$new_content_parsed = $this->renovate_blocks( $content_parsed, $log, $urls_map, $image_sizes_map );
						// serialize new content
						$new_content = serialize_blocks( $new_content_parsed ); // can we use traverse_and_serialize_blocks() here? In testing there seems to be no difference.
						$new_content = $this->quoted_element_attr( $new_content );
					}
					$post_args = array(
						'ID' => $content_key['new_id'],
						'post_content' => $new_content,
						'post_parent' => $new_post_parent
					);
					wp_update_post( wp_slash( $post_args ), true );
				} elseif ( $post_parent && $new_post_parent ) {
					$post_args = array(
						'ID' => $content_key['new_id'],
						'post_parent' => $new_post_parent
					);
					wp_update_post( wp_slash( $post_args ), true );
				}
				// update post_meta such as featured image, font face file etc.
				$postmeta = get_post_meta( $content_key['new_id'] );
				$postmeta = array_combine(array_keys($postmeta), array_column($postmeta, '0'));
				foreach ( $postmeta as $meta_key => $meta_value ) {
					if ( $meta_key === '_thumbnail_id' && $meta_value !== '' ) {
						if ( isset( $log['map_attachments'][$meta_value]['new_id'] ) ) {
							update_post_meta( $content_key['new_id'], '_thumbnail_id', $log['map_attachments'][$meta_value]['new_id'] );
						}
					}
					if ( $meta_key === '_wp_font_face_file' && $meta_value !== '' ) {
						if ( isset( $log['map_font_faces'][$meta_value]['new_id'] ) && isset( $log['map_font_faces'][$meta_value]['new_url'] ) ) {
							update_post_meta( $content_key['new_id'], '_wp_font_face_file', wp_basename($log['map_font_faces'][$meta_value]['new_url']) );
						}
					}
				}
			}
		}

		// fix template & part slugs
		if ( isset($log['fix_slugs']) ) {
			foreach ($log['fix_slugs'] as $t_id => $t_slug) {
				$this->slug_fix( $t_id, $t_slug );
			}
		}

		// replace font face URLs in global styles post(s)
		if ( isset($log['design']) && isset($log['map_font_faces']) ) {
			$this->styles_font_urls_replace( $log['design'], $log['map_font_faces'] );
		}

		// create design patterns for templates and template parts
		if ( isset( $log['theme_parent'] ) && '' !== $log['theme_parent'] ) {
			$theme_parent = $log['theme_parent'];
		} else {
			$theme_parent = wp_get_theme()->get_template();
		}
		foreach ( $log['design'] as $design_post ) {
			if ( 'wp_template' === $design_post['post_type'] || 'wp_template_part' === $design_post['post_type'] ) {
				$design_post_parent = get_post( $design_post['new_id'] );
				$design_post_title = $design_post_parent->post_title;
				$design_post_name = $design_post_parent->post_name;
				$design_post_content = $design_post_parent->post_content;
				// parse blocks
				$design_post_content_parsed = parse_blocks( $design_post_content );
				// remove e.g. "theme":"theme_slug" from template part block attrs
				$new_design_post_content_parsed = $this->detheme_template_part( $design_post_content_parsed );
				// serialize new content
				$new_design_post_content = serialize_blocks( $new_design_post_content_parsed );
				$design_post_type = 'starter_sites_td';
				if ( 'wp_template_part' === $design_post['post_type'] ) {
					$design_post_type = 'starter_sites_pd';
				}
				$design_post_meta = array(
					'relation' => 'AND',
					array(
						'key' => 'starter_sites_import_title',
						'value' => $log['site']['demo_title'],
						'compare' => '='
					),
					array(
						'key' => 'starter_sites_import_parent_theme',
						'value' => $theme_parent,
						'compare' => '='
					)
				);
				$exists_design_post_id = $this->template_design_exists( $design_post_name, $design_post_type, $design_post_meta );
				if ( $exists_design_post_id ) {
					$design_post_args = array(
						'ID' => $exists_design_post_id,
						'post_content' => $new_design_post_content,
					);
					wp_update_post( wp_slash( $design_post_args ), true );
				} else {
					$design_post_meta_input = array(
						'starter_sites_import_title' => $log['site']['demo_title'],
						'starter_sites_import_parent_theme' => $theme_parent
					);
					$design_post_args = array(
						'post_content' => $new_design_post_content,
						'post_title' => $design_post_title,
						'post_status' => 'private',
						'post_name' => $design_post_name,
						'post_type' => $design_post_type,
						'meta_input' => $design_post_meta_input
					);
					wp_insert_post( wp_slash( $design_post_args ), true );
				}
			}
		}

	}

	/**
	 * Fix template and template part slugs (post_name).
	 * We don't want posts with e.g. 'header' from another theme AND 'header-2' from this theme!
	 */
	public function slug_fix( $id, $slug ) {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'posts', [ 'post_name' => $slug ], [ 'ID' => $id ], '%s', '%d' );
	}

	public function detheme_template_part( &$blocks ) {
		foreach ( $blocks as $key => &$block ) {
			if ( 'core/template-part' === $block['blockName'] ) {
				unset( $block['attrs']['theme'] );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->detheme_template_part( $block['innerBlocks'], );
			}
		}
		return $blocks;
	}

	public function renovate_blocks( &$blocks, $log, $urls_map, $image_sizes_map ) {
		foreach ( $blocks as $key => &$block ) {
			if ( 'core/group' === $block['blockName'] ) {
				if ( isset( $block['attrs']['style']['background']['backgroundImage']['id'] ) && isset( $block['attrs']['style']['background']['backgroundImage']['url'] ) ) {
					$new_attrs = $this->replace_attrs( $log, $block['attrs']['style']['background']['backgroundImage']['id'], 'media' );
					$block['attrs']['style']['background']['backgroundImage']['id'] = (int) $new_attrs['id'];
					$block['attrs']['style']['background']['backgroundImage']['url'] = $new_attrs['url'];
				}
			}
			elseif ( 'core/cover' === $block['blockName'] ) {
				if ( isset( $block['attrs']['id'] ) && isset( $block['attrs']['url'] ) ) {
					$new_attrs = $this->replace_attrs( $log, $block['attrs']['id'], 'media' );
					$block = $this->replace_content( $block, 'wp-image-'.$block['attrs']['id'], 'wp-image-'.$new_attrs['id'] );
					$block = $this->replace_content( $block, $block['attrs']['url'], $new_attrs['url'] );
					$block['attrs']['id'] = (int) $new_attrs['id'];
					$block['attrs']['url'] = $new_attrs['url'];
				}
			}
			elseif ( 'core/image' === $block['blockName'] ) {
				if ( isset( $block['attrs']['id'] ) ) {
					$new_attrs = $this->replace_attrs( $log, $block['attrs']['id'], 'media' );
					$block = $this->replace_content( $block, 'wp-image-'.$block['attrs']['id'], 'wp-image-'.$new_attrs['id'] );
					if ( isset( $new_attrs['old_url'] ) ) {
						$block = $this->replace_content( $block, $new_attrs['old_url'], $new_attrs['url'] );
					}
					$block['attrs']['id'] = (int) $new_attrs['id'];
				}
			}
			elseif ( 'core/media-text' === $block['blockName'] ) {
				if ( isset( $block['attrs']['mediaId'] ) ) {
					$new_attrs = $this->replace_attrs( $log, $block['attrs']['mediaId'], 'media' );
					$block = $this->replace_content( $block, 'wp-image-'.$block['attrs']['mediaId'], 'wp-image-'.$new_attrs['id'] );
					if ( isset( $new_attrs['old_url'] ) ) {
						$block = $this->replace_content( $block, $new_attrs['old_url'], $new_attrs['url'] );
					}
					$block['attrs']['mediaId'] = (int) $new_attrs['id'];
					$block['attrs']['mediaLink'] = $new_attrs['url'];
				}
			}
			elseif ( 'core/navigation-link' === $block['blockName'] ) {
				if ( isset( $block['attrs']['id'] ) ) {
					$new_attrs = $this->replace_attrs( $log, $block['attrs']['id'], $block['attrs']['kind'] );
					$block['attrs']['id'] = (int) $new_attrs['id'];
					$block['attrs']['url'] = $new_attrs['url'];
				}
			}
			elseif ( 'core/navigation' === $block['blockName'] ) {
				if ( isset( $block['attrs']['ref'] ) ) {
					$new_attrs = $this->replace_attrs( $log, $block['attrs']['ref'], 'navigation' );
					$block['attrs']['ref'] = (int) $new_attrs['id'];
				}
			}
			elseif ( 'core/block' === $block['blockName'] ) {
				if ( isset( $block['attrs']['ref'] ) ) {
					$new_attrs = $this->replace_attrs( $log, $block['attrs']['ref'], 'block' );
					$block['attrs']['ref'] = (int) $new_attrs['id'];
				}
			}
			// unset patternName if it refs a post ID
			if ( isset( $block['attrs']['metadata']['patternName'] ) && str_starts_with( $block['attrs']['metadata']['patternName'], 'core/block/' ) ) {
				unset( $block['attrs']['metadata']['patternName'] );
			}
			// replace any inline text links that contain "data-type" & "data-id" attributes
			$block = $this->replace_text_links( $log, $block );
			// replace image URLs
			foreach ( $image_sizes_map as $url_old => $url_new ) {
				$block = $this->replace_content( $block, $url_old, $url_new );
			}
			// replace any remaining relevant urls
			foreach ( $urls_map as $url_old => $url_new ) {
				$block = $this->replace_content( $block, $url_old, $url_new );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->renovate_blocks( $block['innerBlocks'], $log, $urls_map, $image_sizes_map );
			}
		}
		return $blocks;
	}

	public function quoted_element_attr( $content ) {
		preg_match_all('/"{(.*?)}"/', $content, $match);
		if ( isset($match[1]) && is_array($match[1]) ) {
			foreach ($match[1] as $sub_match) {
				if ( is_string($sub_match) ) {
					$new_string = str_replace('"', '&quot;', $sub_match);
					$content = str_replace( $sub_match, $new_string, $content );
				}
			}
		}
		return $content;
	}

	public function replace_text_links( $log, $block ) {
		$block['innerHTML'] = $this->process_link_attrs( $log, $block['innerHTML'] );
		if ( is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $i => $content ) {
				if ( !empty( $content ) ) {
					$block['innerContent'][$i] = $this->process_link_attrs( $log, $content );
				}
			}
		} else {
			if ( !empty($block['innerContent']) ) {
				$block['innerContent'] = $this->process_link_attrs( $log, $content );
			}
		}
		return $block;
	}

	public function process_link_attrs( $log, $content ) {
		$post_types = get_post_types( array ( 'public' => true ) );
		unset( $post_types['attachment'] );
		$taxonomies = get_taxonomies( array ( 'public' => true ) );
		$processor = new \WP_HTML_Tag_Processor( $content );
		while ( $processor->next_tag( 'a' ) ) {
			if ( $processor->get_attribute( 'data-type' ) && $processor->get_attribute( 'data-id' ) ) {
				$kind = $processor->get_attribute( 'data-type' );
				if ( $kind === 'attachment' ) {
					$map = 'map_attachments';
				} elseif ( in_array($kind, $post_types) ) {
					$map = 'map_posts';
				} elseif ( in_array($kind, $taxonomies) ) {
					$map = 'map_terms';
				} else {
					$map = NULL;
				}
				$id = $processor->get_attribute( 'data-id' );
				if ( $map ) {
					if ( isset( $log[$map][$id]['new_id'] ) ) {
						$new_id = $log[$map][$id]['new_id'];
					} else {
						$new_id = '';
					}
					if ( isset( $log[$map][$id]['new_url'] ) ) {
						$new_url = $log[$map][$id]['new_url'];
					} else {
						$new_url = '';
					}
				} else {
					$new_id = '';
					$new_url = '';
				}
				$processor->set_attribute( 'href', $new_url );
				$processor->set_attribute( 'data-id', $new_id );
			}
		}
		return $processor->get_updated_html();
	}

	public function replace_content( $block, $old_value, $new_value ) {
		foreach ( $block['attrs'] as $key => $value ) {
			if ( !is_array($value) && ( str_starts_with($value, 'https://') || str_starts_with($value, 'http://') ) ) {
				if ( isset($block['attrs'][$key]) ) {
					$block['attrs'][$key] = str_replace( $old_value, $new_value, $value );
				}
			}
		}
		$values = array(
			'"' . $old_value . '"' => '"' .$new_value . '"',
			"'" . $old_value . "'" => "'" .$new_value . "'",
			'(' . $old_value . ')' => '(' .$new_value . ')',
		);
		foreach ( $values as $key => $value ) {
			$block['innerHTML'] = str_replace( $key, $value, $block['innerHTML'] );
			if ( is_array( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as $i => $content ) {
					if ( !empty( $content ) ) {
						$block['innerContent'][$i] = str_replace( $key, $value, $content );
					}
				}
			} else {
				if ( !empty($block['innerContent']) ) {
					$block['innerContent'] = str_replace( $key, $value, $block['innerContent'] );
				}
			}
		}
		return $block;
	}

	public function replace_attrs( $log, $id, $kind = 'post-type' ) {
		if ( $kind === 'post-type' ) {
			$map = 'map_posts';
		} elseif ( $kind === 'taxonomy' ) {
			$map = 'map_terms';
		} elseif ( $kind === 'media' ) {
			$map = 'map_attachments';
		} elseif ( $kind === 'navigation' ) {
			$map = 'map_navigation';
		} elseif ( $kind === 'block' ) {
			$map = 'map_patterns';
		} else {
			$map = 'map_attachments';
		}
		if ( isset( $log[$map][$id]['new_id'] ) ) {
			$new_id = $log[$map][$id]['new_id'];
		} else {
			$new_id = $id;
		}
		if ( isset( $log[$map][$id]['new_url'] ) ) {
			$attrs = array(
				'id' => (int) $new_id,
				'url' => $log[$map][$id]['new_url']
			);
		} else {
			$attrs = array(
				'id' => (int) $new_id,
				'url' => '#'
			);
		}
		if ( isset( $log[$map][$id]['demo_url'] ) ) {
			// useful for image block where it only has img src in content, and does not have a "url" attr
			$attrs['old_url'] = $log[$map][$id]['demo_url'];
		}
		return $attrs;
	}

	/**
	 * Replace URLs in uploaded font face posts.
	 */
	public function replace_font_face_src( $content, $urls_map ) {
		$content_decoded = json_decode( $content, true );
		if ( isset( $urls_map[$content_decoded['src']] ) && isset( $content_decoded['src'] ) && $content_decoded['src'] !== '' ) {
			$content_decoded['src'] = $urls_map[$content_decoded['src']];
		}
		return wp_json_encode( $content_decoded );
	}

	/**
	 * Replace uploaded font face URLs in global styles.
	 */
	public function styles_font_urls_replace( $design = array(), $fonts = array() ) {

		$fonts_map = array();

		// get the font face URLs
		foreach ( $fonts as $font_face ) {
			if ( isset($font_face['demo_url']) && isset($font_face['new_url']) ) {
				$fonts_map[$font_face['demo_url']] = $font_face['new_url'];
			}
		}

		// loop the design items to find the global styles post(s)
		foreach ( $design as $item ) {

			// get the global style
			if ( isset($item['new_id']) && isset($item['post_type']) && $item['post_type'] === 'wp_global_styles' ) {
				$styles = json_decode( get_post( $item['new_id'] )->post_content, true );

				// start - replace the font URLs
				if ( isset( $styles['settings']['typography']['fontFamilies']['custom'] ) && is_array( $styles['settings']['typography']['fontFamilies']['custom'] ) ) {

					$font_families = $styles['settings']['typography']['fontFamilies']['custom'];

					foreach ( $font_families as $key_family => $font_family ) {

						foreach ( $font_family['fontFace'] as $key_face => $font_face ) {

							$font_face_src = $font_face['src'];

							if ( isset( $fonts_map[$font_face_src] ) && $fonts_map[$font_face_src] !== ''  ) {
								$font_families[$key_family]['fontFace'][$key_face]['src'] = $fonts_map[$font_face_src];
							}

						}

					}

					$styles['settings']['typography']['fontFamilies']['custom'] = $font_families;

				}
				// end - replace the font URLs

				$new_styles = wp_json_encode( $styles );

				$post_args = array(
					'ID' => $item['new_id'],
					'post_content' => $new_styles
				);

				wp_update_post( wp_slash( $post_args ), true );

			}

		}

	}

}
