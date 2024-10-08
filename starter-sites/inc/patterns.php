<?php
/**
 * Register block patterns from original imported templates & parts.
 * This serves as a backup template (or template part) "Design".
 * Example usage: if the user resets a template back to the theme default, or if activating a child theme.
 */
function starter_sites_template_design_patterns() {

	$parent_theme = wp_get_theme()->get_template();

	$args = [
		'numberposts' => -1,
		'order' => 'ASC',
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
					$site_title = $site_title . ' - ';
				}
				register_block_pattern(
					'starter-sites/template-' . $post->post_name,
					array(
						'title'			=> __( 'Starter Sites - ', 'starter-sites' ) . $site_title . $post->post_title,
						'content'		=> $post->post_content,
						'inserter'		=> 'no',
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
