<?php

function starter_sites_list() {
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
	if ( $demo_list ) {
		$sites = $demo_list;
	} else {
		$demo_list = wp_json_file_decode( STARTER_SITES_PATH . 'assets/json/demos-list.json', array( 'associative' => true ) );
		if ( $demo_list) {
			$sites = $demo_list;
		} else {
			$sites = array();
		}
	}
	return $sites;
}

function starter_sites_demo_list() {
	$demos = starter_sites_list();
	if ( isset($demos['sites']) && !empty($demos['sites']) ) {
		$sites = starter_sites_demos_order( $demos['sites'] );
	} else {
		$sites = array();
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
	$demos = starter_sites_list();
	if ( isset($demos['themes']) && !empty($demos['themes']) ) {
		$themes = $demos['themes'];
	} else {
		$themes = array();
	}
	return $themes;
}

function starter_sites_plugin_list() {
	$demos = starter_sites_list();
	if ( isset($demos['plugins']) && !empty($demos['plugins']) ) {
		$plugins = $demos['plugins'];
	} else {
		$plugins = array();
	}
	return $plugins;
}
