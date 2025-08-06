<?php
/**
 * Register block patterns from original imported templates & parts.
 * This serves as a backup template (or template part) "Design".
 * Example usage: if the user resets a template back to the theme default, or if activating a child theme.
 */
function starter_sites_template_design_patterns() {

	register_block_pattern_category(
		'starter_sites_content',
		array(
			'label'       => _x( 'Starter Sites: Content', 'Block pattern category', 'starter-sites' ),
			'description' => __( 'Imported Starter Sites patterns, typically used as page content.', 'starter-sites' ),
		)
	);

	register_block_pattern_category(
		'starter_sites_template_designs',
		array(
			'label'       => _x( 'Starter Sites: Template Designs', 'Block pattern category', 'starter-sites' ),
			'description' => __( 'Backup patterns for imported Starter Sites templates.', 'starter-sites' ),
		)
	);

	register_block_pattern_category(
		'starter_sites_template_part_designs',
		array(
			'label'       => _x( 'Starter Sites: Template Part Designs', 'Block pattern category', 'starter-sites' ),
			'description' => __( 'Backup patterns for imported Starter Sites template parts.', 'starter-sites' ),
		)
	);

	$parent_theme = wp_get_theme()->get_template();

	$args = [
		'numberposts' => -1,
		'order' => 'DESC',
		'orderby' => 'date',
		'post_status' => 'private',
		'post_type' => array( 'starter_sites_td', 'starter_sites_pd' )
	];
	$posts = get_posts( $args );
	if ( $posts ) {
		foreach ( $posts as $post ) {
			$site_theme = get_post_meta( $post->ID, 'starter_sites_import_parent_theme', true );

			if ( $parent_theme === $site_theme ) {

				$site_title = get_post_meta( $post->ID, 'starter_sites_import_title', true );
				if ( '' !== $site_title ) {
					$site_slug = sanitize_title( $site_title );
					$site_title = ' "' . $site_title . '" ';
				} else {
					$site_slug = '';
					$site_title = ' - ';
				}

				$category = 'starter_sites_template_designs';
				$prefix = 'template-';
				$block_types = '';
				if ( $post->post_type === 'starter_sites_pd' ) {
					$category = 'starter_sites_template_part_designs';
					$prefix = 'template-part-';
					$part = get_block_template( $site_theme . '//' . $post->post_name, 'wp_template_part' );
					if ( $part ) {
						$block_types = 'core/template-part/' . $part->area;
					}
				}

				register_block_pattern(
					'starter-sites/' . $prefix . $post->post_name . $site_slug,
					array(
						'title'			=> __( 'Starter Sites', 'starter-sites' ) . $site_title . $post->post_title,
						'content'		=> $post->post_content,
						'inserter'		=> true,
						'categories'	=> array( $category ),
						'blockTypes'	=> array( $block_types ),
						'templateTypes'	=> array( $post->post_name )
					)
				);

			}

		}
	}
}
/**
 * Initialize the template design patterns.
 * Priority of 9 so they appear before the theme default patterns.
 */
add_action( 'init', 'starter_sites_template_design_patterns', 9 );
