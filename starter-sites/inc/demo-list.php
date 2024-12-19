<?php

function starter_sites_demo_list() {

	$demos = get_transient( 'starter_sites_demo_list' );
	if ( false === $demos ) {
		$request = wp_remote_get( STARTER_SITES_HOME_URL . 'demos-list.json' );
		if ( ! is_wp_error( $request ) && 200 === $request['response']['code'] ) {
			$request_body = wp_remote_retrieve_body( $request );
			set_transient( 'starter_sites_demo_list', $request_body, 3600 );
			$demo_list = json_decode( $request_body, true );
		} else {
			set_transient( 'starter_sites_demo_list', '', 30 );
			$demo_list = '';
		}
	} else {
		$demo_list = json_decode( $demos, true );
	}
	if ( $demo_list && isset( $demo_list['sites'] ) ) {
		$sites = starter_sites_demos_order( $demo_list['sites'] );
	} else {
		$demo_list = wp_json_file_decode( STARTER_SITES_PATH . 'assets/json/demos-list.json', array( 'associative' => true ) );
		if ( $demo_list && isset( $demo_list['sites'] ) ) {
			$sites = starter_sites_demos_order( $demo_list['sites'] );
		} else {
			$sites = array();
		}
	}

	return $sites;
}


function starter_sites_demos_order( $sites ) {

	$readme = get_file_data( get_stylesheet_directory() . '/readme.txt', array(
		'starter_site' => 'Starter Site'
	) );
	if ( isset( $readme['starter_site'] ) && $readme['starter_site'] !== '' ) {
		$theme_starter_site = $readme['starter_site'];
		$site_move = array();
		foreach ( $sites as $site => $values ) {
			if ( $site === $theme_starter_site ) {
				$values['promote'] = true;
				$site_move[$site] = $values;
				unset( $sites[$theme_starter_site] );
			}
		}
		$sites = array_merge( $site_move, $sites );
	}

	return $sites;
}


function starter_sites_theme_list() {

	return array(

		'eternal' => array(
			'title' => __( 'Eternal', 'starter-sites' ),
		),

	);

}


function starter_sites_plugin_list() {

	return array(

		'animations-for-blocks' => array(
			'file' => 'animations-for-blocks/animations-for-blocks.php',
			'title' => __( 'Animations for Blocks', 'starter-sites' ),
		),
		'block-visibility' => array(
			'file' => 'block-visibility/block-visibility.php',
			'title' => __( 'Block Visibility', 'starter-sites' ),
		),
		'gutena-forms' => array(
			'file' => 'gutena-forms/gutena-forms.php',
			'title' => __( 'Gutena Forms - Contact Forms Block', 'starter-sites' ),
		),
		'icon-block' => array(
			'file' => 'icon-block/icon-block.php',
			'title' => __( 'The Icon Block', 'starter-sites' ),
		),
		'woocommerce' => array(
			'file' => 'woocommerce/woocommerce.php',
			'title' => __( 'WooCommerce', 'starter-sites' ),
		),
		'social-sharing-block' => array(
			'file' => 'social-sharing-block/social-sharing-block.php',
			'title' => __( 'Social Sharing Block', 'starter-sites' ),
		),
		'wp-map-block' => array(
			'file' => 'wp-map-block/wp-map-block.php',
			'title' => __( 'WP Map Block', 'starter-sites' ),
		),
		'carousel-block' => array(
			'file' => 'carousel-block/plugin.php',
			'title' => __( 'Carousel Slider Block', 'starter-sites' ),
		),
		'yith-woocommerce-wishlist' => array(
			'file' => 'yith-woocommerce-wishlist/init.php',
			'title' => __( 'YITH WooCommerce Wishlist', 'starter-sites' ),
		),

	);

}
