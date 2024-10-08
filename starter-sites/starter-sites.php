<?php
/*
Plugin Name: Starter Sites
Plugin URI: https://wpstartersites.com/plugin/
Description: Ready to go WordPress full site editing starter sites and website demos, all with full pages of real content, and all created with the block editor.
Version: 2.0.1
Author: WP Starter Sites
Author URI: https://wpstartersites.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: starter-sites
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 7.4
*/

// Block direct access to the main plugin file.
defined( 'ABSPATH' ) || exit;

class Starter_Sites {

	protected static $instance = null;

	/**
	* Creates a new Starter_Sites object and implements singleton.
	*
	* @return Starter_Sites
	*/
	static function get_instance() {
		if ( !is_a( self::$instance, 'Starter_Sites' ) ) {
			self::$instance = new Starter_Sites();
		}
		return self::$instance;
	}

	/*
	 * Define constants.
	 */
	public function define_constants() {
		define( 'STARTER_SITES_VERSION', '2.0.1' );
		define( 'STARTER_SITES_PATH', plugin_dir_path( __FILE__ ) );
		define( 'STARTER_SITES_URL', plugin_dir_url( __FILE__ ) );
		define( 'STARTER_SITES_BASENAME', plugin_basename( __FILE__ ) );
		define( 'STARTER_SITES_THEME_DEFAULT', 'eternal' );
		define( 'STARTER_SITES_PREVIEW_URL', 'https://demo.wpstartersites.com/' );
	}

	/*
	 * Register hooks.
	 */
	public function register_hooks() {
		register_activation_hook( __FILE__, [ $this, 'activation' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivation' ] );
	}

	/*
	 * Filters.
	 */
	public function filters() {
		add_filter( 'plugin_action_links_' . STARTER_SITES_BASENAME, [ $this, 'action_links' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_table_meta' ], 10, 2 );
	}

	/*
	 * Do required actions.
	 */
	public function actions() {
		add_action( 'plugins_loaded', [ $this, 'textdomain' ] );
		add_action( 'admin_init', [ $this, 'redirect' ] );
	}

	/**
	 * Runs on activation.
	 */
	public function activation() {
		add_option( 'starter_sites_do_activation_redirect', true );
	}

	/**
	 * Runs on deactivation.
	 */
	public function deactivation() {

	}

	/**
	 * Runs on uninstallation.
	 */
	static function uninstall() {
		delete_option( 'starter_sites_do_activation_redirect' );
	}

	public function is_minimal() {
		$settings = get_option( 'starter_sites_settings' );
		if ( isset($settings['is_minimal']) && 'yes' === $settings['is_minimal'] ) {
			return true;
		} else {
			return false;
		}
	}

	public function base_link() {
		$settings = get_option( 'starter_sites_settings' );
		$base_link = 'admin.php';
		if ( isset($settings['is_minimal']) && 'yes' === $settings['is_minimal'] ) {
			$base_link = 'options-general.php';
		} elseif ( isset($settings['menu_location']) ) {
			if ( 'appearance' === $settings['menu_location'] ) {
				$base_link = 'themes.php';
			} elseif ( 'tools' === $settings['menu_location'] ) {
				$base_link = 'tools.php';
			}
		}
		return $base_link;
	}

	/**
	 * Redirect on single instance of plugin activation.
	 * Filterable e.g. to prevent automatic redirection upon activation,
	 * add the following to your theme's functions.php file,
	 * or your plugin's main file (replace themeslug prefix with your theme or plugin prefix):
	 * 
		function themeslug_prevent_starter_sites_redirect() {
			return true;
		}
		add_filter( 'starter_sites_prevent_redirect_on_activation', 'themeslug_prevent_starter_sites_redirect' );
	 *
	 */
	public function redirect() {
		if ( $this->is_minimal() ) {
			return;
		} else {
			if ( get_option( 'starter_sites_do_activation_redirect', false ) ) {
				delete_option( 'starter_sites_do_activation_redirect' );
				if ( is_network_admin() || isset($_GET['activate-multi']) || apply_filters( 'starter_sites_prevent_redirect_on_activation', false ) ) {
					return;
				}
				$admin_link = admin_url( $this->base_link() );
				wp_safe_redirect( add_query_arg( [ 'page' => 'starter-sites' ], $admin_link ) );
				exit;
			}
		}
	}

	/**
	 * Add a plugin action link.
	 */
	public function action_links( $links ) {
		if ( $this->is_minimal() ) {
			$browse = '<a href="' . esc_url( add_query_arg( [ 'page' => 'starter-sites' ], admin_url( 'options-general.php' ) ) ) . '">' . esc_html__( 'Settings', 'starter-sites' ) . '</a>';
		} else {
			$admin_link = admin_url( $this->base_link() );
			$browse = '<a href="' . esc_url( add_query_arg( [ 'page' => 'starter-sites' ], $admin_link ) ) . '">' . esc_html__( 'Browse Sites', 'starter-sites' ) . '</a>';
		}
		array_unshift( $links, $browse );
		return $links;
	}

	/*
	 * Add additional information in plugins table.
	 */
	public function plugin_table_meta( $plugin_meta, $plugin_file ) {
		if ( STARTER_SITES_BASENAME === $plugin_file ) {
			$plugin_meta['starter_sites_support'] = '<a target="_blank" href="https://wordpress.org/support/plugin/starter-sites/">' . __( 'Support', 'starter-sites' ) . '</a>';
			$plugin_meta['starter_sites_review'] = '<a target="_blank" href="https://wordpress.org/support/plugin/starter-sites/reviews/#new-post">' . __( 'Rate or Review Starter Sites ★★★★★', 'starter-sites' ) . '</a>';
			$plugin_meta['starter_sites_upgrade'] = '<a target="_blank" href="https://wpstartersites.com/pricing/">' . __( 'Upgrade to Premium', 'starter-sites' ) . '</a>';
		}
		return $plugin_meta;
	}

	/**
	 * Load the plugin textdomain for translations.
	 */
	public function textdomain() {
		load_plugin_textdomain( 'starter-sites', false, dirname( STARTER_SITES_BASENAME ) . '/languages/' );
	}

	/**
	 * Include files.
	 */
	public function includes() {
		require_once STARTER_SITES_PATH . 'inc/main.php';
		require STARTER_SITES_PATH . 'inc/patterns.php';
	}

	public function __construct() {
		$this->define_constants();
		$this->register_hooks();
		$this->filters();
		$this->actions();
		$this->includes();
	}

}

/**
 * Starter_Sites Class will be instantiated with a function.
 * @return Starter_Sites
 */
function starter_sites() {
    return Starter_Sites::get_instance();
}
starter_sites();

/*
 * Register uninstall hook outside the plugin class.
 */
register_uninstall_hook( __FILE__, [ 'Starter_Sites', 'uninstall' ] );
