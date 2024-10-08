<?php
namespace Starter_Sites;

defined( 'ABSPATH' ) || exit;

class Main {

	private $settings;

	/*
	 * Filters.
	 */
	public function filters() {
		add_filter( 'woocommerce_enable_setup_wizard', [ $this, 'wc_disable_wizard' ] );
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', [ $this, 'wc_prevent_wizard_redirect' ] );
		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
	}

	/*
	 * Do required actions.
	 */
	public function actions() {
		add_action( 'admin_menu', [ $this, 'admin_page_menu' ] );
		add_action( 'admin_init', [ $this, 'settings_register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'wp_ajax_starter_sites_update_screen_prefs', [ $this, 'update_screen_prefs' ] );
		add_action( 'updated_option', [ $this, 'settings_updated' ], 10, 3 );
		add_action( 'added_option', [ $this, 'settings_added' ], 10, 2 );
		add_action( 'init', [ $this, 'output_buffer' ] );
	}

	function output_buffer() {
		ob_start();
	}

	public function wc_disable_wizard() {
		if ( isset($_GET['page']) && 'starter-sites' === $_GET['page'] ) {
			return false;
		} else {
			return true;
		}
	}

	public function wc_prevent_wizard_redirect() {
		if ( isset($_GET['page']) && 'starter-sites' === $_GET['page'] ) {
			return true;
		} else {
			return false;
		}
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
	 * Register plugin menu page.
	 */
	public function admin_page_menu() {
		$page_title = apply_filters( 'starter_sites_page_title', __( 'Starter Sites', 'starter-sites' ) );
		if ( $this->base_link() === 'themes.php' ) {
			add_theme_page( 
				$page_title,
				$page_title,
				'manage_options',
				'starter-sites',
				[ $this, 'admin_page' ],
			);
		} elseif ( $this->base_link() === 'tools.php' ) {
			add_management_page( 
				$page_title,
				$page_title,
				'manage_options',
				'starter-sites',
				[ $this, 'admin_page' ],
			);
		} elseif ( $this->base_link() === 'options-general.php' ) {
			add_options_page( 
				$page_title,
				$page_title,
				'manage_options',
				'starter-sites',
				[ $this, 'admin_page' ],
			);
		} else {
			$priority = 59;
			$settings = get_option( 'starter_sites_settings' );
			if ( isset($settings['menu_location']) && str_starts_with($settings['menu_location'], 'top-level-') ) {
				$priority = (int) str_replace('top-level-', '', $settings['menu_location']);
			}
			add_menu_page( 
				$page_title,
				$page_title,
				'manage_options',
				'starter-sites',
				[ $this, 'admin_page' ],
				$this->logo_icon(),
				$priority
			);
		}
	}

	/**
	 * Register plugin settings.
	 */
	public function settings_register() {
		register_setting(
			'starter_sites_settings_group',
			'starter_sites_settings',
			[ $this, 'settings_option_sanitize' ]
		);
		add_settings_section(
			'starter_sites_settings_section',
			__( 'Settings', 'starter-sites' ),
			[ $this, 'settings_section_info' ],
			'starter-sites-settings'
		);
		add_settings_field(
			'menu_location',
			__( 'Menu Location', 'starter-sites' ),
			[ $this, 'settings_menu_location' ],
			'starter-sites-settings',
			'starter_sites_settings_section'
		);
		add_settings_field(
			'is_minimal',
			__( 'Minimal Mode', 'starter-sites' ),
			[ $this, 'settings_is_minimal' ],
			'starter-sites-settings',
			'starter_sites_settings_section'
		);
	}

	public function settings_option_sanitize($input) {
		$sanitary_values = [];
		if ( isset( $input['is_minimal'] ) ) {
			$sanitary_values['is_minimal'] = $input['is_minimal'];
		}
		if ( isset( $input['menu_location'] ) ) {
			$sanitary_values['menu_location'] = $input['menu_location'];
		}
		return $sanitary_values;
	}

	public function settings_section_info() {
	}

	public function settings_menu_location() {
		/*
			2 = Dashboard
			5 = Posts
			10 = Media
			15 = Links
			20 = Pages
			25 = Comments
			60 = Appearance
			65 = Plugins
			70 = Users/Profile
			75 = Tools
			80 = Settings
			99 = separator-last
		*/
		$selected_default = 'selected';
		if ( isset( $this->settings['menu_location'] ) ) {
			$selected_default = '';
		}
		?>
		<select name="starter_sites_settings[menu_location]" id="menu_location">
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-1') ? 'selected' : '' ; ?>
			<option value="top-level-1" <?php echo $selected; ?>><?php esc_html_e( 'Before Dashboard', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-4') ? 'selected' : '' ; ?>
			<option value="top-level-4" <?php echo $selected; ?>><?php esc_html_e( 'Before Posts', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-9') ? 'selected' : '' ; ?>
			<option value="top-level-9" <?php echo $selected; ?>><?php esc_html_e( 'Before Media', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-19') ? 'selected' : '' ; ?>
			<option value="top-level-19" <?php echo $selected; ?>><?php esc_html_e( 'Before Pages', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-24') ? 'selected' : '' ; ?>
			<option value="top-level-24" <?php echo $selected; ?>><?php esc_html_e( 'Before Comments', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-59') ? 'selected' : '' ; ?>
			<option value="top-level-59" <?php echo $selected . $selected_default; ?>><?php esc_html_e( 'Before Appearance', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-64') ? 'selected' : '' ; ?>
			<option value="top-level-64" <?php echo $selected; ?>><?php esc_html_e( 'Before Plugins', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-69') ? 'selected' : '' ; ?>
			<option value="top-level-69" <?php echo $selected; ?>>
				<?php
				if ( current_user_can( 'list_users' ) ) {
					esc_html_e( 'Before Users', 'starter-sites' );
				} else {
					esc_html_e( 'Before Profile', 'starter-sites' );
				}
				?>
			</option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-74') ? 'selected' : '' ; ?>
			<option value="top-level-74" <?php echo $selected; ?>><?php esc_html_e( 'Before Tools', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-79') ? 'selected' : '' ; ?>
			<option value="top-level-79" <?php echo $selected; ?>><?php esc_html_e( 'Before Settings', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'top-level-81') ? 'selected' : '' ; ?>
			<option value="top-level-81" <?php echo $selected; ?>><?php esc_html_e( 'After Settings', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'appearance') ? 'selected' : '' ; ?>
			<option value="appearance" <?php echo $selected; ?>><?php esc_html_e( 'Sub-item in Appearance', 'starter-sites' );?></option>
			<?php $selected = (isset( $this->settings['menu_location'] ) && $this->settings['menu_location'] === 'tools') ? 'selected' : '' ; ?>
			<option value="tools" <?php echo $selected; ?>><?php esc_html_e( 'Sub-item in Tools', 'starter-sites' );?></option>
		</select><br>
		<label for="menu_location"><?php esc_html_e( 'Choose where to place the plugin menu item.', 'starter-sites' );?></label>
		<?php
	}

	public function settings_is_minimal() {
		printf(
			'<input type="checkbox" name="starter_sites_settings[is_minimal]" id="is_minimal" value="yes" %1$s> <label for="is_minimal">%2$s</label>',
			( isset( $this->settings['is_minimal'] ) && $this->settings['is_minimal'] === 'yes' ) ? 'checked' : '',
			esc_html__( 'Disable the site activation/importer and move the plugin settings to a sub-item in the Settings page of your dashboard. This also overrides the Menu Location setting.', 'starter-sites' )
		);
	}

	public function settings_updated( $option, $old_value = '', $new_value = '' ) {
		if ( 'starter_sites_settings' === $option ) {
			if ( isset($new_value['is_minimal']) && 'yes' === $new_value['is_minimal'] ) {
				$base_link = 'options-general.php';
				$query_args = [ 'page' => 'starter-sites' ];
			} else {
				$base_link = 'admin.php';
				if ( isset($new_value['menu_location']) ) {
					if ( 'appearance' === $new_value['menu_location'] ) {
						$base_link = 'themes.php';
					} elseif ( 'tools' === $new_value['menu_location'] ) {
						$base_link = 'tools.php';
					}
				}
				$query_args = [ 'page' => 'starter-sites', 'tab' => 'settings' ];
			}
			wp_safe_redirect( add_query_arg( $query_args, admin_url( $base_link ) ) );
			exit;
		}
	}

	public function settings_added( $option, $value = '' ) {
		if ( 'starter_sites_settings' === $option ) {
			if ( isset($value['is_minimal']) && 'yes' === $value['is_minimal'] ) {
				$base_link = 'options-general.php';
				$query_args = [ 'page' => 'starter-sites' ];
			} else {
				$base_link = 'admin.php';
				if ( isset($value['menu_location']) ) {
					if ( 'appearance' === $value['menu_location'] ) {
						$base_link = 'themes.php';
					} elseif ( 'tools' === $value['menu_location'] ) {
						$base_link = 'tools.php';
					}
				}
				$query_args = [ 'page' => 'starter-sites', 'tab' => 'settings' ];
			}
			wp_safe_redirect( add_query_arg( $query_args, admin_url( $base_link ) ) );
			exit;
		}
	}

	/**
	 * Enqueue scripts/styles.
	 */
	public function scripts() {
		$screen = get_current_screen();
		if ( $screen->base === 'toplevel_page_starter-sites' || $screen->base === 'appearance_page_starter-sites' || $screen->base === 'tools_page_starter-sites' || $screen->base === 'settings_page_starter-sites' ) {
			wp_enqueue_script( 'starter-sites-main', STARTER_SITES_URL . 'assets/js/main.js', [ 'jquery' ], STARTER_SITES_VERSION, false );
			wp_enqueue_style( 'starter-sites-main', STARTER_SITES_URL . 'assets/css/main.css', [] , STARTER_SITES_VERSION );
			wp_localize_script( 'starter-sites-main', 'starter_sites_screen_settings', [ 'options_update_nonce' => wp_create_nonce( 'starter-sites-options-nonce' ) ] );
		}
	}

	/**
	 * The main admin page.
	 */
	public function admin_page() {
		if ( $this->is_minimal() ) {
			?>
			<div class="starter-sites-header">
				<div class="starter-sites-branding">
					<a class="navigation-tab fullpage-dash-link" href="<?php echo esc_url( admin_url() ); ?>" title="<?php esc_html_e( 'Back to Dashboard', 'starter-sites' ); ?>"><span class="dashicons dashicons-arrow-left-alt2 starter-sites-link-dashboard"></span></a>
					<div class="branding-inner">
						<?php echo $this->logo_icon( 'svg-path' ) . apply_filters( 'starter_sites_page_title', esc_html__( 'Starter Sites', 'starter-sites' ) ); ?>
					</div>
				</div>
				<div class="starter-sites-options">
					<div class="option-wrap">
						<button class="navigation-tab screen-option" href="#" data-id="fullpage" role="tab" title="<?php esc_html_e( 'Full Page On/Off', 'starter-sites' ); ?>"><span class="toggle-fullpage"></span></button>
					</div>
					<div class="option-wrap">
						<button class="navigation-tab screen-option" href="#" data-id="darkmode" role="tab" title="<?php esc_html_e( 'Light/Dark Mode', 'starter-sites' ); ?>"><span class="toggle-darkmode"></span></button>
					</div>
				</div>
			</div>
			<div class="wrap starter-sites-main">
				<?php $this->view_settings();?>
			</div>
			<?php
		} else {
			if ( isset($_GET['activate']) && $_GET['activate'] !== '' && isset($_GET['process']) && $_GET['process'] === 'content' && isset($_GET['id']) && $_GET['id'] !== '' ) {
				$is_doing_content = true;
			} else {
				$is_doing_content = false;
			}
			$tab = '';
			if ( isset( $_GET['tab'] ) ) {
				$tab = sanitize_key( $_GET['tab'] );
			}
			$is_current_settings = '';
			$is_current_logs = '';
			$is_current_upload = '';
			$is_current_browse = '';
			if ( 'settings' === $tab ) {
				$is_current_settings = ' is-current';
			} elseif ( 'logs' === $tab ) {
				$is_current_logs = ' is-current';
			} elseif ( 'upload' === $tab ) {
				$is_current_upload = ' is-current';
			} else {
				$is_current_browse = ' is-current';
			}
			$admin_link = admin_url( $this->base_link() );
			?>
			<div class="starter-sites-header">
				<div class="starter-sites-branding">
					<a class="navigation-tab fullpage-dash-link" href="<?php echo esc_url( admin_url() ); ?>" title="<?php esc_html_e( 'Back to Dashboard', 'starter-sites' ); ?>"><span class="dashicons dashicons-arrow-left-alt2 starter-sites-link-dashboard"></span></a>
					<div class="branding-inner">
						<?php echo $this->logo_icon( 'svg-path' ) . apply_filters( 'starter_sites_page_title', esc_html__( 'Starter Sites', 'starter-sites' ) ); ?>
					</div>
				</div>
				<div class="starter-sites-menu">
					<ul>
						<li><a class="menu-item<?php echo $is_current_browse;?>" href="<?php echo esc_url( add_query_arg( [ 'page' => 'starter-sites' ], $admin_link ) );?>"><?php esc_html_e( 'Browse Sites', 'starter-sites' ); ?></a></li>
						<li><a class="menu-item<?php echo $is_current_upload;?>" href="<?php echo esc_url( add_query_arg( [ 'page' => 'starter-sites', 'tab' => 'upload' ], $admin_link ) );?>"><?php esc_html_e( 'Upload', 'starter-sites' ); ?></a></li>
						<li><a class="menu-item<?php echo $is_current_logs;?>" href="<?php echo esc_url( add_query_arg( [ 'page' => 'starter-sites', 'tab' => 'logs' ], $admin_link ) );?>"><?php esc_html_e( 'Logs', 'starter-sites' ); ?></a></li>
						<li><a class="menu-item<?php echo $is_current_settings;?>" href="<?php echo esc_url( add_query_arg( [ 'page' => 'starter-sites', 'tab' => 'settings' ], $admin_link ) );?>"><?php esc_html_e( 'Settings', 'starter-sites' ); ?></a></li>
					</ul>
				</div>
				<div class="starter-sites-options">
					<div class="option-wrap">
						<button class="navigation-tab screen-option" href="#" data-id="fullpage" role="tab" title="<?php esc_html_e( 'Full Page On/Off', 'starter-sites' ); ?>"><span class="toggle-fullpage"></span></button>
					</div>
					<div class="option-wrap">
						<button class="navigation-tab screen-option" href="#" data-id="darkmode" role="tab" title="<?php esc_html_e( 'Light/Dark Mode', 'starter-sites' ); ?>"><span class="toggle-darkmode"></span></button>
					</div>
				</div>
			</div>
			<div class="wrap starter-sites-main">
				<?php
				if ( isset($_POST['starter_site_activate']) && $_POST['starter_site_activate'] !== '' && isset( $_POST['_wpnonce'] ) ) {
					$site_values = $_POST;
					$site_slug = sanitize_key( $site_values['starter_site_activate'] );
					check_admin_referer( 'activate_site_' . $site_slug, '_wpnonce' );
					(new Activate)->site_extensions( $site_values );
				} elseif ( $is_doing_content ) {
					//$site_slug = sanitize_key( $_GET['activate'] );
					$log_id = sanitize_key( $_GET['id'] );
					(new Activate)->site_content( $log_id );
				} else {
					if ( 'settings' === $tab ) {
						$this->view_settings();
					} elseif ( 'logs' === $tab ) {
						$this->view_logs();
					} elseif ( 'upload' === $tab ) {
						$this->view_upload();
					} else {
						$this->view_sites_grid();
						$this->view_sites_modals();
					}
				}
				?>
				<div class="activating-wrap">
					<div class="activating-notice">
						<div class="starter-sites-progress">
							<div class="starter-sites-progress-bar starter-sites-progress-bar-animated" style="width: 100%" role="progressbar">
							</div>
						</div>
						<p><?php esc_html_e( 'Activating the required theme and plugin(s).', 'starter-sites' ); ?></p>
						<p><?php esc_html_e( 'Importing the content and design elements.', 'starter-sites' ); ?></p>
						<p><?php esc_html_e( 'This process may take a while on some hosts, so please be patient.', 'starter-sites' ); ?></p>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Permalink notice.
	 */
	public function view_permalink_notice() {
		if ( ! $this->is_minimal() && ! get_option( 'permalink_structure' ) ) {
			?>
			<div class="starter-sites-permalink">
				<p><?php echo sprintf( esc_html__( 'It is highly recommended to %s for your website before importing a starter site.', 'starter-sites' ), '<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . __( 'select the permalink structure', 'starter-sites' ) . '</a>'); ?></p>
				<p><?php esc_html_e( 'If you are really sure you want plain links for your pages, posts, categories, navigation menus etc. you can ignore this notice.', 'starter-sites' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * The demos grid view.
	 */
	public function view_sites_grid() {
		$this->view_permalink_notice();
		?>
		<div class="starter-sites-demos-grid">
		<?php
		foreach ( starter_sites_demo_list() as $demo_site => $demo_site_data ) {
			if ( file_exists( STARTER_SITES_PATH . 'content/sites/' . $demo_site . '/screenshot.jpg' ) ) {
				$demo_site_img_url = STARTER_SITES_URL . 'content/sites/' . $demo_site . '/screenshot.jpg';
			} else {
				$demo_site_img_url = STARTER_SITES_URL . 'assets/images/screenshot-placeholder.png';
			}
			$demo_site_img_url = $demo_site_img_url . '?ver=' . STARTER_SITES_VERSION;
			?>
			<div class="starter-sites-demo demo-id-<?php echo esc_attr( $demo_site );?>" data-demo-id="<?php echo esc_attr( $demo_site );?>">
				<div class="badges">
				<?php
				if ( 'premium' === $demo_site_data['type'] ) {
					?>
					<span class="label-badge premium"><?php esc_html_e( 'Premium', 'starter-sites' );?></span>
					<?php
				} elseif ( 'included' === $demo_site_data['type'] ) {
					?>
					<span class="label-badge included"><?php esc_html_e( 'Free', 'starter-sites' );?></span>
					<?php
				}
				if ( in_array('woocommerce', $demo_site_data['plugins']) ) {
					?>
					<span class="label-badge ecommerce"><?php esc_html_e( 'Ecommerce', 'starter-sites' );?></span>
					<?php
				}
				?>
				</div>
				<div class="starter-sites-demo-screenshot"><button class="image modal-open"><img src="<?php echo esc_url( $demo_site_img_url );?>" alt="<?php echo esc_attr( $demo_site_data['title'] );?>"/></button></div>
				<div class="starter-sites-demo-cta">
					<div class="starter-sites-demo-title"><button class="title modal-open"><?php esc_html_e( $demo_site_data['title'] );?></button></div>
					<div class="starter-sites-demo-more-info"><button class="button button-tertiary starter-sites-button modal-open"><i class="dashicons dashicons-info-outline"></i> <?php esc_html_e( 'Details', 'starter-sites' );?></button></div>
				</div>
			</div>
			<?php
		}
		?>
			<div class="starter-sites-demo is-info">
				<p><strong><?php esc_html_e( 'Not seeing any sites you like?', 'starter-sites' );?></strong></p>
				<p><?php esc_html_e( 'We welcome your suggestions for new sites.', 'starter-sites' );?></p>
				<p><?php esc_html_e( 'Please let us know what new Starter Sites you would like to see included in the plugin.', 'starter-sites' );?></p>
				<p class="has-button"><a class="button button-tertiary starter-sites-button" href="https://wpstartersites.com/suggest-a-site/" target="_blank"><?php esc_html_e( 'Suggest a Site', 'starter-sites' );?> <i class="dashicons dashicons-external"></i></a></p>
			</div>
			<div class="starter-sites-demo is-info">
				<p><strong><?php esc_html_e( 'Need help with Starter Sites?', 'starter-sites' );?></strong></p>
				<p><?php esc_html_e( 'We welcome your support questions.', 'starter-sites' );?></p>
				<p><?php esc_html_e( 'Please ask us and we will do our best to help you with any queries regarding this plugin.', 'starter-sites' );?></p>
				<p class="has-button"><a class="button button-tertiary starter-sites-button" href="https://wordpress.org/support/plugin/starter-sites/" target="_blank"><?php esc_html_e( 'Support', 'starter-sites' );?> <i class="dashicons dashicons-external"></i></a></p>
			</div>
			<div class="starter-sites-demo is-info">
				<p class="is-stars">★★★★★</p>
				<p><strong><?php esc_html_e( 'Please rate or review this plugin.', 'starter-sites' );?></strong></p>
				<p><?php esc_html_e( 'This helps us grow, and that means we can create even more Starter Sites for you.', 'starter-sites' );?></p>
				<p class="has-button"><a class="button button-tertiary starter-sites-button" href="https://wordpress.org/support/plugin/starter-sites/reviews/#new-post" target="_blank"><?php esc_html_e( 'Rate or Review', 'starter-sites' );?> <i class="dashicons dashicons-external"></i></a></p>
			</div>
		</div>
		<?php
	}

	/**
	 * The demos expanded modals.
	 */
	public function view_sites_modals() {
		$admin_link = admin_url( $this->base_link() );
		$active_theme = wp_get_theme();
		$active_theme_name = $active_theme->get( 'Name' );
		$active_theme_slug = $active_theme->get_stylesheet();
		$active_theme_template = $active_theme->get_template();
		$theme_list = starter_sites_theme_list();
		$plugin_list = starter_sites_plugin_list();
		?>
		<div class="starter-sites-demos-modals">
		<?php
		foreach ( starter_sites_demo_list() as $demo_site => $demo_site_data ) {
			if ( file_exists( STARTER_SITES_PATH . 'content/sites/' . $demo_site . '/screenshot-full.jpg' ) ) {
				$demo_site_img_url = STARTER_SITES_URL . 'content/sites/' . $demo_site . '/screenshot-full.jpg';
			} else {
				if ( file_exists( STARTER_SITES_PATH . 'content/sites/' . $demo_site . '/screenshot.jpg' ) ) {
					$demo_site_img_url = STARTER_SITES_URL . 'content/sites/' . $demo_site . '/screenshot.jpg';
				} else {
					$demo_site_img_url = STARTER_SITES_URL . 'assets/images/screenshot-placeholder.png';
				}
			}
			$demo_site_img_url = $demo_site_img_url . '?ver=' . STARTER_SITES_VERSION;
			$form_activate_url = add_query_arg( [ 'page' => 'starter-sites' ], $admin_link );
			?>
			<div class="starter-sites-demo-modal demo-modal-id-<?php echo esc_attr( $demo_site );?>" data-demo-modal-id="<?php echo esc_attr( $demo_site );?>">
				<div class="starter-sites-demo-modal-header">
					<div class="starter-sites-demo-modal-nav">
						<button class="button starter-sites-button-nav modal-previous"><i class="dashicons dashicons-arrow-left-alt2"></i></button>
						<button class="button starter-sites-button-nav modal-next"><i class="dashicons dashicons-arrow-right-alt2"></i></button>
					</div>
					<div class="starter-sites-demo-modal-nav-close">
						<button class="button starter-sites-button-nav modal-close"><i class="dashicons dashicons-no-alt"></i></button>
					</div>
				</div>
				<div class="starter-sites-demo-modal-body">
					<div class="starter-sites-demo-details">
						<div class="badges">
						<?php
						if ( 'premium' === $demo_site_data['type'] ) {
							?>
							<span class="label-badge premium"><?php esc_html_e( 'Premium', 'starter-sites' );?></span>
							<?php
						} elseif ( 'included' === $demo_site_data['type'] ) {
							?>
							<span class="label-badge included"><?php esc_html_e( 'Free', 'starter-sites' );?></span>
							<?php
						}
						if ( in_array('woocommerce', $demo_site_data['plugins']) ) {
							?>
							<span class="label-badge ecommerce"><?php esc_html_e( 'Ecommerce', 'starter-sites' );?></span>
							<?php
						}
						?>
						</div>
						<div class="starter-sites-demo-title"><?php echo esc_html( $demo_site_data['title'] );?></div>
						<?php
						if ( 'included' === $demo_site_data['type'] ) {
						?>
						<form method="post" action="<?php echo esc_url( $form_activate_url );?>" novalidate="novalidate">
							<input type="hidden" id="starter_site_activate" name="starter_site_activate" value="<?php echo esc_attr($demo_site);?>">
							<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo wp_create_nonce( 'activate_site_' . $demo_site );?>">
						<?php
						}
						// Products
						if ( isset( $demo_site_data['products'] ) && !empty($demo_site_data['products']) ) {
							?>
							<div class="starter-sites-demo-sub-section">
								<p class="starter-sites-sub-heading products"><?php echo sprintf(
									/* translators: %d = number of products */
									__( 'Products (%d):', 'starter-sites' ),
									count($demo_site_data['products'])
								);?></p>
								<ul class="starter-sites-feature-list products">
								<?php
								foreach ( $demo_site_data['products'] as $product ) {
									?>
									<li class="wppss-feature-item product"><?php echo esc_html( wp_unslash($product) );?></li>
									<?php
								}
								?>
								</ul>
							</div>
							<?php
						}
						// Pages
						if ( isset( $demo_site_data['pages'] ) && !empty($demo_site_data['pages']) ) {
							?>
							<div class="starter-sites-demo-sub-section">
								<p class="starter-sites-sub-heading pages"><?php echo sprintf(
									/* translators: %d = number of pages */
									__( 'Pages (%d):', 'starter-sites' ),
									count($demo_site_data['pages'])
								);?></p>
								<ul class="starter-sites-feature-list pages">
								<?php
								foreach ( $demo_site_data['pages'] as $page ) {
									?>
									<li class="wppss-feature-item page"><?php echo esc_html( wp_unslash($page) );?></li>
									<?php
								}
								?>
								</ul>
							</div>
							<?php
						}
						// Posts
						if ( isset( $demo_site_data['posts'] ) && !empty($demo_site_data['posts']) ) {
							?>
							<div class="starter-sites-demo-sub-section">
								<p class="starter-sites-sub-heading posts"><?php echo sprintf(
									/* translators: %d = number of posts */
									__( 'Posts (%d):', 'starter-sites' ),
									count($demo_site_data['posts'])
								);?></p>
								<ul class="starter-sites-feature-list posts">
								<?php
								foreach ( $demo_site_data['posts'] as $post ) {
									?>
									<li class="wppss-feature-item post"><?php echo esc_html( wp_unslash($post) );?></li>
									<?php
								}
								?>
								</ul>
							</div>
							<?php
						}
						// Patterns
						if ( isset( $demo_site_data['patterns'] ) && !empty($demo_site_data['patterns']) ) {
							?>
							<div class="starter-sites-demo-sub-section">
								<p class="starter-sites-sub-heading patterns"><?php echo sprintf(
									/* translators: %d = number of patterns */
									__( 'Patterns (%d):', 'starter-sites' ),
									count($demo_site_data['patterns'])
								);?></p>
								<ul class="starter-sites-feature-list patterns">
								<?php
								foreach ( $demo_site_data['patterns'] as $pattern ) {
									?>
									<li class="wppss-feature-item pattern"><?php echo esc_html( wp_unslash($pattern) );?></li>
									<?php
								}
								?>
								</ul>
							</div>
							<?php
						}
						// Fonts
						if ( isset( $demo_site_data['fonts'] ) && !empty($demo_site_data['fonts']) ) {
							?>
							<div class="starter-sites-demo-sub-section">
								<p class="starter-sites-sub-heading fonts"><?php echo sprintf(
									/* translators: %d = number of fonts */
									__( 'Fonts (%d):', 'starter-sites' ),
									count($demo_site_data['fonts'])
								);?></p>
								<ul class="starter-sites-feature-list fonts">
								<?php
								foreach ( $demo_site_data['fonts'] as $font ) {
									?>
									<li class="wppss-feature-item font"><?php echo esc_html( wp_unslash($font) );?></li>
									<?php
								}
								?>
								</ul>
							</div>
							<?php
						}
						// Theme
						if ( isset( $demo_site_data['theme'] ) && !empty($demo_site_data['theme']) ) {
							$theme_slug = $demo_site_data['theme'];
							if ( isset($theme_list[$theme_slug]['title']) && $theme_list[$theme_slug]['title'] !== '' ) {
								$theme_title = $theme_list[$theme_slug]['title'];
							} else {
								$theme_title = $theme_slug;
							}
							?>
							<div class="starter-sites-demo-sub-section">
								<p class="starter-sites-sub-heading theme"><?php esc_html_e( 'Theme:', 'starter-sites' );?></p>
								<ul class="starter-sites-feature-list theme">
									<li class="wppss-feature-item theme">
										<?php
										if ( $active_theme_template === $theme_slug && $active_theme_name !== $theme_title && 'included' === $demo_site_data['type'] ) {
											?><b><?php echo esc_html( $theme_title );?></b>
											<br><span class="subtext"><?php echo sprintf(
												/* translators: %s = name of currently active child theme */
												__( '(or the currently active <b>%s</b> child theme, choose below)', 'starter-sites' ),
												$active_theme_name
											);?></span>
											<input type="hidden" id="parent_theme_slug" name="parent_theme_slug" value="<?php echo esc_attr($theme_slug);?>">
											<input type="hidden" id="child_theme_slug" name="child_theme_slug" value="<?php echo esc_attr($active_theme_slug);?>">
											<input type="hidden" id="child_theme_name" name="child_theme_name" value="<?php echo esc_attr($active_theme_name);?>">
											<table class="form-table">
												<tbody>
													<tr>
														<td>
															<fieldset>
															<label><input type="radio" name="theme" value="<?php echo esc_attr( $theme_slug );?>" checked="checked"><?php echo esc_html( $theme_title );?></label><br>
															<label><input type="radio" name="theme" value="<?php echo esc_attr( $active_theme_slug );?>"><?php echo esc_html( $active_theme_name );?></label><br>
															</fieldset>
														</td>
													</tr>
												</tbody>
											</table>
											<?php
										} else {
											echo esc_html( $theme_title );
										}
										?>
									</li>
								</ul>
							</div>
							<?php
						}
						// Plugins
						if ( isset( $demo_site_data['plugins'] ) && !empty($demo_site_data['plugins']) ) {
							?>
							<div class="starter-sites-demo-sub-section">
								<p class="starter-sites-sub-heading plugins"><?php esc_html_e( 'Plugins:', 'starter-sites' );?></p>
								<ul class="starter-sites-feature-list plugins">
								<?php
								foreach ( $demo_site_data['plugins'] as $plugin ) {
									if ( isset($plugin_list[$plugin]['title']) && $plugin_list[$plugin]['title'] !== '' ) {
										$plugin_title = $plugin_list[$plugin]['title'];
									} else {
										$plugin_title = $plugin;
									}
									?>
									<li class="wppss-feature-item plugin"><?php echo esc_html( $plugin_title );?></li>
									<?php
								}
								?>
								</ul>
							</div>
							<?php
						}
						if ( 'premium' === $demo_site_data['type'] ) {
						?>
							<div class="starter-sites-demo-purchase-info">
								<p><?php esc_html_e( 'This Starter Site can be purchased individually, or you can upgrade to access all Premium Starter Sites.', 'starter-sites' );?></p>
							</div>
						<?php
						}
						?>
							<div class="starter-sites-demo-cta">
								<?php
								if ( 'premium' === $demo_site_data['type'] ) {
								?>
								<a class="button button-primary starter-sites-button" href="<?php echo esc_url( 'https://wpstartersites.com/product/' . $demo_site . '/' );?>" target="_blank"><?php esc_html_e( 'Buy', 'starter-sites' );?> <i class="dashicons dashicons-external"></i></a>
								<a class="button button-secondary starter-sites-button" href="https://wpstartersites.com/pricing/" target="_blank"><?php esc_html_e( 'Upgrade to Premium', 'starter-sites' );?> <i class="dashicons dashicons-external"></i></a>
								<?php
								} else {
								?>
								<input type="submit" name="submit" id="submit" class="button button-primary starter-sites-button activate-site" value="<?php esc_html_e( 'Activate', 'starter-sites' );?>"></input>
								<?php
								}
								?>
								<a class="button button-tertiary starter-sites-button" href="<?php echo esc_url( STARTER_SITES_PREVIEW_URL . $demo_site . '/' );?>" target="_blank"><?php esc_html_e( 'Preview', 'starter-sites' );?> <i class="dashicons dashicons-external"></i></a>
							</div>
						<?php
						if ( 'included' === $demo_site_data['type'] ) {
						?>
						</form>
						<?php
						}
						?>
					</div>
					<div class="starter-sites-demo-screenshot"><img src="<?php echo esc_url( $demo_site_img_url );?>" alt="<?php echo esc_attr( $demo_site_data['title'] );?>"/></div>
				</div>
			</div>
			<?php
		}
		?>
		</div>
		<?php
	}

	/**
	 * The upload view.
	 */
	public function view_upload() {
		if ( isset( $_GET['starter_sites_upload'] ) && 'yes' === $_GET['starter_sites_upload'] ) {
			check_admin_referer( 'starter-sites-upload' );
			if ( isset( $_FILES['starter-site-xml']['name'] ) && ! str_ends_with( strtolower( $_FILES['starter-site-xml']['name'] ), '.xml' ) ) {
				wp_die( esc_html__( 'Only .xml files may be uploaded.', 'starter-sites' ) );
			}
			if ( $_FILES['starter-site-xml'] ) {
				$overrides = array(
					'test_form' => false,
					'test_type' => false
				);
				$file_uploaded = wp_handle_upload( $_FILES['starter-site-xml'], $overrides );
				if ( isset( $file_uploaded['error'] ) ) {
					wp_die( $file_uploaded['error'] );
				} else {
					$headers = array(
						'plugin' => 'Plugin',
						'site' => 'Site',
						'site_id' => 'Site ID',
						'url' => 'Link'
					);
					$file_headers = get_file_data( $file_uploaded['file'], $headers );
					if ( 'Starter Sites' === $file_headers['plugin'] && isset( starter_sites_demo_list()[$file_headers['site_id']] ) ) {
						$site_values = array(
							'starter_site_activate' => $file_headers['site_id'],
							'starter_sites_file' => $file_uploaded['file'],
						);
						(new Activate)->site_extensions( $site_values );
					} else {
						wp_die( '<p>' . esc_html__( 'The uploaded file does not appear to contain a genuine Starter Site. You may need to update the plugin to the latest version.', 'starter-sites' ) . '</p><p>' . esc_html__( 'If you are sure it is a genuine Starter Sites file, please reach out to our support team.', 'starter-sites' ) . '</p><p><a target="_blank" href="https://wordpress.org/support/plugin/starter-sites/" class="button button-primary">' . esc_html__( 'Get Help', 'starter-sites' ) . '</a></p>' );
					}
				}
			}
		} else {
			$this->view_permalink_notice();
		?>
			<div class="starter-sites-upload">
				<p class="upload-helper"><?php esc_html_e( 'Occasionally we may release limited edition individual Starter Sites that are not available within the plugin. If you have such a site in a .xml file format, you may import and activate it by uploading it here.', 'starter-sites' );?></p>
				<form method="post" enctype="multipart/form-data" class="wp-upload-form" action="<?php echo esc_url( add_query_arg( [ 'page' => 'starter-sites', 'tab' => 'upload', 'starter_sites_upload' => 'yes' ], admin_url( $this->base_link() ) ) ); ?>">
					<?php wp_nonce_field( 'starter-sites-upload' ); ?>
					<input type="file" id="starter-site-xml" name="starter-site-xml" accept=".xml" />
					<?php submit_button( esc_html__( 'Import and Activate', 'starter-sites' ), 'upload-site', 'starter-site-upload-submit', false ); ?>
				</form>
			</div>
		<?php
		}
	}

	/**
	 * The logs view.
	 */
	public function view_logs() {
		if ( isset( $_GET['log_id'] ) ) {
			$log_id = sanitize_key( $_GET['log_id'] );
			(new Logs)->view_log( get_post($log_id)->post_content );
		} else {
			(new Logs)->get_logs();
		}
	}

	/**
	 * The settings view.
	 */
	public function view_settings() {
		$this->view_permalink_notice();
		?>
		<div class="starter-sites-settings">
		<?php $this->settings = get_option( 'starter_sites_settings' );
			settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
					settings_fields( 'starter_sites_settings_group' );
					do_settings_sections( 'starter-sites-settings' );
					submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Update user display preference(s).
	 * 'Full Page' and/or 'Dark Mode'.
	 */
	public function update_screen_prefs() {
		check_ajax_referer( 'starter-sites-options-nonce', 'starter-sites-options-nonce-name' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1 );
		}
		$setting = (string) filter_input( INPUT_POST, 'setting' );
		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, 'starter_sites_admin_'.$setting, true ) === 'yes' ) {
			$new_setting = '';
		} else {
			$new_setting = 'yes';
		}
		update_user_meta( $user_id, 'starter_sites_admin_'.$setting, $new_setting );
		wp_die( 1 );
	}

	/**
	 * Add body classes.
	 */
	public function admin_body_class( $classes ) {
		$screen = get_current_screen();
		if ( $screen->base === 'toplevel_page_starter-sites' || $screen->base === 'appearance_page_starter-sites' || $screen->base === 'tools_page_starter-sites' || $screen->base === 'settings_page_starter-sites'  ) {
			$user_id = get_current_user_id();
			if ( get_user_meta( $user_id, 'starter_sites_admin_fullpage', true ) === 'yes' ) {
				$classes .= ' starter-sites-fullpage ';
			}
			if ( get_user_meta( $user_id, 'starter_sites_admin_darkmode', true ) === 'yes' ) {
				$classes .= ' starter-sites-darkmode ';
			}
		}
		return $classes . ' starter-sites';
	}

	/**
	 * The plugin icon/logo.
	 */
	public function logo_icon( $type = 'svg-base64' ) {
		$icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgd2lkdGg9IjgwMHB4IiBoZWlnaHQ9IjgwMHB4IiBzdHlsZT0ic2hhcGUtcmVuZGVyaW5nOmdlb21ldHJpY1ByZWNpc2lvbjsgdGV4dC1yZW5kZXJpbmc6Z2VvbWV0cmljUHJlY2lzaW9uOyBpbWFnZS1yZW5kZXJpbmc6b3B0aW1pemVRdWFsaXR5OyBmaWxsLXJ1bGU6ZXZlbm9kZDsgY2xpcC1ydWxlOmV2ZW5vZGQiIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIj4KPGc+PHBhdGggc3R5bGU9Im9wYWNpdHk6MC45OTMiIGZpbGw9IiMwMDAwMDAiIGQ9Ik0gLTAuNSw2NDguNSBDIC0wLjUsNDgyLjUgLTAuNSwzMTYuNSAtMC41LDE1MC41QyAxMi4wNjE2LDg3LjEwNzEgNTAuMzk0OSw1MC45NDA0IDExNC41LDQyQyAxNDkuODMzLDQxLjMzMzMgMTg1LjE2Nyw0MS4zMzMzIDIyMC41LDQyQyAyNDcuODg1LDQ3LjQxNzggMjYyLjU1Miw2NC4wODQ1IDI2NC41LDkyQyAyNjMuNTUsMTA4LjgxNCAyNjMuMjE3LDEyNS42NDcgMjYzLjUsMTQyLjVDIDIxNS40OTksMTQyLjMzMyAxNjcuNDk5LDE0Mi41IDExOS41LDE0M0MgMTA4LjEzMywxNDUuNzAzIDEwMS42MzMsMTUyLjg2OSAxMDAsMTY0LjVDIDk5LjMzMzMsMzIxLjE2NyA5OS4zMzMzLDQ3Ny44MzMgMTAwLDYzNC41QyAxMDEuNjIzLDY0Ni4xMjIgMTA4LjEyMyw2NTMuMjg5IDExOS41LDY1NkMgMTY3LjE2Niw2NTYuNSAyMTQuODMyLDY1Ni42NjcgMjYyLjUsNjU2LjVDIDI2Mi45NDIsNjc3LjU0NSAyNjIuNDQyLDY5OC41NDUgMjYxLDcxOS41QyAyNTQuNjQ3LDc0MC44NTIgMjQwLjQ4MSw3NTMuMzUyIDIxOC41LDc1N0MgMTgzLjgzMyw3NTcuNjY3IDE0OS4xNjcsNzU3LjY2NyAxMTQuNSw3NTdDIDUwLjM5NDksNzQ4LjA2IDEyLjA2MTYsNzExLjg5MyAtMC41LDY0OC41IFoiLz48L2c+CjxnPjxwYXRoIHN0eWxlPSJvcGFjaXR5OjAuOTkzIiBmaWxsPSIjMDAwMDAwIiBkPSJNIDc5OS41LDE1MS41IEMgNzk5LjUsMzE2LjgzMyA3OTkuNSw0ODIuMTY3IDc5OS41LDY0Ny41QyA3ODcuMzk2LDcxMS40MzUgNzQ5LjA2Miw3NDcuOTM1IDY4NC41LDc1N0MgNjQ5LjgzMyw3NTcuNjY3IDYxNS4xNjcsNzU3LjY2NyA1ODAuNSw3NTdDIDU1OC41MDIsNzUzLjMzNCA1NDQuMzM2LDc0MC44MzQgNTM4LDcxOS41QyA1MzYuNTU4LDY5OC41NDUgNTM2LjA1OCw2NzcuNTQ1IDUzNi41LDY1Ni41QyA1ODQuMTY4LDY1Ni42NjcgNjMxLjgzNCw2NTYuNSA2NzkuNSw2NTZDIDY5MC44NzcsNjUzLjI4OSA2OTcuMzc3LDY0Ni4xMjIgNjk5LDYzNC41QyA2OTkuNjY3LDQ3Ny44MzMgNjk5LjY2NywzMjEuMTY3IDY5OSwxNjQuNUMgNjk3LjM2NywxNTIuODY5IDY5MC44NjcsMTQ1LjcwMyA2NzkuNSwxNDNDIDYzMS41MDEsMTQyLjUgNTgzLjUwMSwxNDIuMzMzIDUzNS41LDE0Mi41QyA1MzUuNzgzLDEyNS42NDcgNTM1LjQ1LDEwOC44MTQgNTM0LjUsOTJDIDUzNi40NDgsNjQuMDg0NSA1NTEuMTE1LDQ3LjQxNzggNTc4LjUsNDJDIDYxMy44MzMsNDEuMzMzMyA2NDkuMTY3LDQxLjMzMzMgNjg0LjUsNDJDIDc0OS4wNjIsNTEuMDY0NSA3ODcuMzk2LDg3LjU2NDUgNzk5LjUsMTUxLjUgWiIvPjwvZz4KPGc+PHBhdGggc3R5bGU9Im9wYWNpdHk6MC45ODciIGZpbGw9IiMwMDAwMDAiIGQ9Ik0gMzMyLjUsMTg0LjUgQyAzODkuMTY4LDE4NC4zMzMgNDQ1LjgzNCwxODQuNSA1MDIuNSwxODVDIDUzNy41MjUsMTg5LjM3IDU1OS4zNTgsMjA4LjUzNiA1NjgsMjQyLjVDIDU2OS4zMTcsMjUxLjc5IDU3MC4xNTEsMjYxLjEyNCA1NzAuNSwyNzAuNUMgNDkzLjgzMywyNzAuMzMzIDQxNy4xNjYsMjcwLjUgMzQwLjUsMjcxQyAzMjMuODA4LDI3NC42OTEgMzEzLjMwOCwyODQuODU3IDMwOSwzMDEuNUMgMzA1LjkyMSwzMjEuNTA1IDMxMy4wODgsMzM2LjMzOCAzMzAuNSwzNDZDIDMzNC4wNTcsMzQ3LjQwOCAzMzcuNzI0LDM0OC40MDggMzQxLjUsMzQ5QyAzODAuMTY3LDM0OS4zMzMgNDE4LjgzMywzNDkuNjY3IDQ1Ny41LDM1MEMgNTA4LjM0NiwzNTMuMjM3IDU0NS44NDYsMzc3LjA3IDU3MCw0MjEuNUMgNTg0LjIxNCw0NTkuMTA3IDU4NS4yMTQsNDk3LjEwNyA1NzMsNTM1LjVDIDU1OS4xNjcsNTY4IDUzNiw1OTEuMTY3IDUwMy41LDYwNUMgNDkxLjgxMyw2MDkuMjU1IDQ3OS44MTMsNjEyLjI1NSA0NjcuNSw2MTRDIDQzNy4zNTQsNjE1LjEyMSA0MDcuMTg3LDYxNS42MjEgMzc3LDYxNS41QyAzNDcuODA3LDYxNS42MzMgMzE4LjY0MSw2MTUuMTMzIDI4OS41LDYxNEMgMjU5LjIzNCw2MDguOTY2IDIzOS43MzQsNTkxLjc5OSAyMzEsNTYyLjVDIDIyOC44NDMsNTUxLjYwNSAyMjcuNjc2LDU0MC42MDUgMjI3LjUsNTI5LjVDIDMwMS41MDEsNTI5LjY2NyAzNzUuNTAxLDUyOS41IDQ0OS41LDUyOUMgNDc1LjA1OSw1MjQuMDc5IDQ4OC43MjYsNTA4LjU3OSA0OTAuNSw0ODIuNUMgNDg4LjY2MSw0NTcuNjYyIDQ3NS42NjEsNDQyLjE2MiA0NTEuNSw0MzZDIDQxMS44MzMsNDM1LjY2NyAzNzIuMTY3LDQzNS4zMzMgMzMyLjUsNDM1QyAyOTAuNjA0LDQzMi4xNDYgMjU3Ljc3MSw0MTMuNjQ2IDIzNCwzNzkuNUMgMjIxLjk1NCwzNTguNDQyIDIxNi40NTQsMzM1Ljc3NSAyMTcuNSwzMTEuNUMgMjE2LjI2NiwyNTEuNjE1IDI0My41OTksMjExLjQ0OSAyOTkuNSwxOTFDIDMxMC40NzMsMTg3Ljk3MiAzMjEuNDczLDE4NS44MDUgMzMyLjUsMTg0LjUgWiIvPjwvZz4KPC9zdmc+Cg==';
		if ( $type === 'svg-path' ) {
			$icon = '<svg class="logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" aria-hidden="true" focusable="false"><path d="M -0.5,648.5 C -0.5,482.5 -0.5,316.5 -0.5,150.5C 12.0616,87.1071 50.3949,50.9404 114.5,42C 149.833,41.3333 185.167,41.3333 220.5,42C 247.885,47.4178 262.552,64.0845 264.5,92C 263.55,108.814 263.217,125.647 263.5,142.5C 215.499,142.333 167.499,142.5 119.5,143C 108.133,145.703 101.633,152.869 100,164.5C 99.0072,322.868 99.3405,481.201 101,639.5C 104.184,648.342 110.351,653.842 119.5,656C 166.832,656.5 214.165,656.667 261.5,656.5C 261.667,677.503 261.5,698.503 261,719.5C 254.647,740.852 240.481,753.352 218.5,757C 183.833,757.667 149.167,757.667 114.5,757C 50.3949,748.06 12.0616,711.893 -0.5,648.5 Z M 799.5,151.5 C 799.5,316.833 799.5,482.167 799.5,647.5C 787.396,711.435 749.062,747.935 684.5,757C 649.833,757.667 615.167,757.667 580.5,757C 556.261,752.428 541.595,738.095 536.5,714C 537.493,694.91 537.826,675.743 537.5,656.5C 584.835,656.667 632.168,656.5 679.5,656C 688.649,653.842 694.816,648.342 698,639.5C 699.66,481.201 699.993,322.868 699,164.5C 697.367,152.869 690.867,145.703 679.5,143C 631.501,142.5 583.501,142.333 535.5,142.5C 535.783,125.647 535.45,108.814 534.5,92C 536.448,64.0845 551.115,47.4178 578.5,42C 613.833,41.3333 649.167,41.3333 684.5,42C 749.062,51.0645 787.396,87.5645 799.5,151.5 Z M 332.5,184.5 C 389.168,184.333 445.834,184.5 502.5,185C 537.525,189.37 559.358,208.536 568,242.5C 569.317,251.79 570.151,261.124 570.5,270.5C 493.833,270.333 417.166,270.5 340.5,271C 323.808,274.691 313.308,284.857 309,301.5C 305.921,321.505 313.088,336.338 330.5,346C 334.057,347.408 337.724,348.408 341.5,349C 380.167,349.333 418.833,349.667 457.5,350C 508.346,353.237 545.846,377.07 570,421.5C 584.214,459.107 585.214,497.107 573,535.5C 559.167,568 536,591.167 503.5,605C 491.813,609.255 479.813,612.255 467.5,614C 437.354,615.121 407.187,615.621 377,615.5C 347.807,615.633 318.641,615.133 289.5,614C 259.234,608.966 239.734,591.799 231,562.5C 228.843,551.605 227.676,540.605 227.5,529.5C 301.501,529.667 375.501,529.5 449.5,529C 475.059,524.079 488.726,508.579 490.5,482.5C 488.661,457.662 475.661,442.162 451.5,436C 411.833,435.667 372.167,435.333 332.5,435C 290.604,432.146 257.771,413.646 234,379.5C 221.954,358.442 216.454,335.775 217.5,311.5C 216.266,251.615 243.599,211.449 299.5,191C 310.473,187.972 321.473,185.805 332.5,184.5 Z"/></svg>';
		}
		return $icon;
	}

	/**
	 * Include files.
	 */
	public function includes() {
		require STARTER_SITES_PATH . 'inc/demo-list.php';
		require STARTER_SITES_PATH . 'inc/logs.php';
		require STARTER_SITES_PATH . 'inc/activate.php';
	}

	public function __construct() {
		$this->filters();
		$this->actions();
		$this->includes();
	}

}

new Main();
