<?php
namespace Starter_Sites;

defined( 'ABSPATH' ) || exit;

class Activate {

	/**
	 * Returns plugin page base link according to user setting.
	 *
	 * @return string
	 */
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
	 * Processes extensions (theme and plugins).
	 *
	 * @param array  $site_values demo site parameters.
	 * Redirects back to plugin page if successful, outputs error message otherwise.
	 */
	public function site_extensions( $site_values ) {
		$site_slug = sanitize_key( $site_values['starter_site_activate'] );
		$site_nonce = $site_values['_wpnonce'];
		if ( isset( $site_values['starter_sites_file'] ) ) {
			$import_file = $site_values['starter_sites_file'];
		} else {
			$import_file = STARTER_SITES_PATH . 'content/sites/' . $site_slug . '/content.xml';
		}
		if ( file_exists( $import_file ) ) {
			// Process theme
			$process_theme_log = $this->process_theme( $site_values );
			if ( isset( $process_theme_log['is_error'] ) && $process_theme_log['is_error'] == true ) {
				$process_theme_log['time_end'] = current_time( 'timestamp' );
				wp_insert_post(
					array(
					'post_content'		=> maybe_serialize( wp_unslash($process_theme_log) ),
					'post_title'		=> __( 'Starter Sites Error', 'starter-sites' ),
					'post_status'		=> 'private',
					'comment_status'	=> 'closed',
					'ping_status'		=> 'closed',
					'post_name'			=> 'starter-sites-error',
					'post_type'			=> 'starter_sites_error'
					)
				);
				if ( isset( $process_theme_log['error_code'] ) ) {
					?>
					<div class="starter-sites-error"><p><?php echo esc_html( $this->error_codes( $process_theme_log['error_code'] ) );?></p></div>
					<?php
				} else {
					?>
					<div class="starter-sites-error"><p><?php echo esc_html__( 'There was an unknown error with the required theme.', 'starter-sites' );?></p></div>
					<?php
				}
			} else {
				// Process plugin(s)
				$process_plugins_log = $this->process_plugins( $process_theme_log, $site_values );
				$process_plugins_log['time_end'] = current_time( 'timestamp' );
				if ( isset( $process_plugins_log['is_error'] ) && $process_plugins_log['is_error'] == true ) {
					wp_insert_post(
						array(
						'post_content'		=> maybe_serialize( wp_unslash($process_plugins_log) ),
						'post_title'		=> __( 'Starter Sites Error', 'starter-sites' ),
						'post_status'		=> 'private',
						'comment_status'	=> 'closed',
						'ping_status'		=> 'closed',
						'post_name'			=> 'starter-sites-error',
						'post_type'			=> 'starter_sites_error'
						)
					);
					if ( isset( $process_plugins_log['error_code'] ) ) {
						?>
						<div class="starter-sites-error"><p><?php echo esc_html( $this->error_codes( $process_plugins_log['error_code'] ) );?></p></div>
						<?php
					} else {
						?>
						<div class="starter-sites-error"><p><?php echo esc_html__( 'There was an unknown error with a required plugin.', 'starter-sites' );?></p></div>
						<?php
					}
				} else {
					$extensions_log_id = wp_insert_post(
						array(
						'post_content'		=> maybe_serialize( wp_unslash($process_plugins_log) ), // wp_insert_post expects unslashed content, prevents unserializing offset errors
						'post_title'		=> __( 'Starter Sites Log', 'starter-sites' ),
						'post_status'		=> 'private',
						'comment_status'	=> 'closed',
						'ping_status'		=> 'closed',
						'post_name'			=> 'starter-sites-log',
						'post_type'			=> 'starter_sites_log',
						'meta_input'		=> array( 'starter_sites_file' => wp_normalize_path( $import_file ) )
						)
					);
					// as we may need functionality from installed plugin(s) we will redirect back to plugin page, then process content
					wp_safe_redirect( add_query_arg( [ 'page' => 'starter-sites', 'activate' => $site_slug, 'process' => 'content', 'id' => $extensions_log_id ], admin_url( $this->base_link() ) ) );
					exit;
				}
			}
		} else {
			?>
			<div class="starter-sites-error"><p><?php echo esc_html( $this->error_codes( 1 ) );?></p></div>
			<?php
		}
	}

	/**
	 * Processes demo site content and outputs result.
	 *
	 * @param array  $process_plugins_log_id WP post id.
	 */
	public function site_content( $process_plugins_log_id ) {
		$log_post = get_post($process_plugins_log_id);
		if ( $log_post && $log_post->post_type === 'starter_sites_log' ) {
			$process_plugins_log = maybe_unserialize( wp_unslash( $log_post->post_content ) );
			$site_slug = $process_plugins_log['site']['demo_slug'];
			$import_file = get_post_meta( $process_plugins_log_id, 'starter_sites_file', true );
			if ( !isset($import_file) && $import_file === '' ) {
				$import_file = STARTER_SITES_PATH . 'content/sites/' . $site_slug . '/content.xml';
			}
			if ( file_exists( $import_file ) ) {
				// Process content
				$process_content_log = $this->process_content( $process_plugins_log, $import_file );
				// Mapping
				(new Mapping)->content( $process_content_log );
				$process_content_log['time_end'] = current_time( 'timestamp' );
				$log_id = wp_update_post(
					array(
						'ID'			=> $process_plugins_log_id,
						'post_content'	=> maybe_serialize( wp_unslash($process_content_log) ), // wp_update_post expects unslashed content, prevents unserializing offset errors
					)
				);
				?>
				<p><?php echo sprintf(
					/* translators: %s = title of the activated starter site */
					__( 'Congratulations. You have successfully activated the %s starter site!', 'starter-sites' ),
					'<span class="success-site-title">' . esc_html( $process_content_log['site']['demo_title'] ) . '</span>'
				);?></p>
				<ul class="text-list">
					<li><a class="text-link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View your new site' );?></a></li>
					<li><a class="text-link" href="<?php echo esc_url( admin_url( 'site-editor.php' ) ); ?>"><?php esc_html_e( 'Edit your new site' );?></a></li>
					<?php if ( $log_id ) {
						?>
						<li><a class="text-link" href="<?php echo esc_url( admin_url( $this->base_link() . '?page=starter-sites&tab=logs&log_id=' . $log_id ) ); ?>"><?php esc_html_e( 'View the activation log' );?></a></li>
						<?php
					}
					?>
				</ul>
				<?php
			} else {
				?>
				<div class="starter-sites-error"><p><?php echo esc_html( $this->error_codes( 1 ) );?></p></div>
				<?php
			}
		} else {
			?>
			<div class="starter-sites-error"><p><?php echo esc_html( $this->error_codes( 4 ) );?></p></div>
			<?php
		}
	}

	/**
	 * Processes theme.
	 *
	 * @param array   $site_values demo site parameters.
	 * @return array  $log activation log.
	 */
	public function process_theme( $site_values ) {
		$site_slug = $site_values['starter_site_activate'];
		$site_title = $site_slug;
		$demo_list = starter_sites_demo_list();
		$theme_list = starter_sites_theme_list();
		if ( isset($demo_list[$site_slug]['title']) && $demo_list[$site_slug]['title'] !== '' ) {
			$site_title = $demo_list[$site_slug]['title'];
		}
		$log = array(
			'user_id' => get_current_user_id(),
			'time_start' => current_time( 'timestamp' ),
			'site' => array(
				'demo_slug' => $site_slug,
			),
			'theme' => array(),
			'theme_parent' => '',
			'plugins' => array(),
			'terms' => array(),
			'content' => array(),
			'design' => array(),
			'other' => array(),
		);
		// if child theme is selected
		if ( isset($site_values['theme']) && isset($site_values['child_theme_slug']) && isset($site_values['child_theme_name']) && $site_values['theme'] === $site_values['child_theme_slug'] ) {
			$log['theme_parent'] = $site_values['parent_theme_slug'];
			$theme = $site_values['child_theme_slug'];
			$theme_title = $site_values['child_theme_name'];
		} else {
			if ( isset($demo_list[$site_slug]['theme']) && $demo_list[$site_slug]['theme'] !== '' ) {
				$theme = $demo_list[$site_slug]['theme'];
			} else {
				$theme = STARTER_SITES_THEME_DEFAULT;
			}
			if ( isset($theme_list[$theme]['title']) && $theme_list[$theme]['title'] !== '' ) {
				$theme_title = $theme_list[$theme]['title'];
			} else {
				$theme_title = $theme;
			}
			$log['theme_parent'] = $theme;
		}
		$active_theme = wp_get_theme();
		$active_theme_slug = $active_theme->get_stylesheet();
		// check if required theme is already active
		if ( $theme === $active_theme_slug ) {
			$log['theme'] = array(
				'slug' => $theme,
				'title' => wp_slash($theme_title),
				'pre_status' => 'active',
				'result' => 'none'
			);
		} else {
			if ( wp_get_theme( $theme )->exists() ) {
				$theme_activate = switch_theme( $theme );
				$log['theme'] = array(
					'slug' => $theme,
					'title' => wp_slash($theme_title),
					'pre_status' => 'inactive',
					'result' => 'activated'
				);
			} else {
				// try to install required theme
				$theme_install = $this->install_theme( $theme );
				if ( $theme_install['success'] ) {
					$theme_activate = switch_theme( $theme );
					$log['theme'] = array(
						'slug' => $theme,
						'title' => wp_slash($theme_title),
						'pre_status' => 'not installed',
						'result' => 'installed and activated'
					);
				} else {
					$log['is_error'] = true;
					$log['error_code'] = 2;
					$log['theme'] = array(
						'slug' => $theme,
						'title' => wp_slash($theme_title),
						'pre_status' => 'not installed',
						'result' => 'not installed'
					);
				}
			}
		}
		return $log;
	}

	/*
	 * Install theme from wp.org
	 */
	public function install_theme( $slug ) {
		$status = [
			'success' => false,
		];
		if ( ! current_user_can( 'install_themes' ) ) {
			if ( is_multisite() ) {
				$status['errorMessage'] = __( 'Please ask your network administrator to enable theme installation capabilities for your user account.', 'starter-sites' );
			} else {
				$status['errorMessage'] = __( 'Please ask your site administrator to enable theme installation capabilities for your user account.', 'starter-sites' );
			}
			return $status;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/theme-install.php';
		$api = themes_api(
			'theme_information',
			[
				'slug'   => $slug,
				'fields' => [ 'sections' => false ],
			]
		);
		if ( is_wp_error( $api ) ) {
			$status['errorMessage'] = $api->get_error_message();
			return $status;
		}
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) ) {
			$status['errorCode']    = $result->get_error_code();
			$status['errorMessage'] = $result->get_error_message();
			return $status;
		} elseif ( is_wp_error( $skin->result ) ) {
			$status['errorCode']    = $skin->result->get_error_code();
			$status['errorMessage'] = $skin->result->get_error_message();
			return $status;
		} elseif ( $skin->get_errors()->has_errors() ) {
			$status['errorMessage'] = $skin->get_error_messages();
			return $status;
		} elseif ( is_null( $result ) ) {
			global $wp_filesystem;
			$status['errorCode']    = 'unable_to_connect_to_filesystem';
			$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );
			// Pass through the error from WP_Filesystem if one was raised.
			if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
				$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
			}
			return $status;
		}
		$status['themeName'] = wp_get_theme( $slug )->get( 'Name' );
		$status['success'] = true;
		return $status;
	}

	/**
	 * Processes plugins.
	 *
	 * @param array   $log activation log.
	 * @param array   $site_values demo site parameters.
	 * @return array  $log appended activation log.
	 */
	public function process_plugins( $log, $site_values ) {
		$site_slug = $site_values['starter_site_activate'];
		$demo_list = starter_sites_demo_list();
		$plugin_list = starter_sites_plugin_list();
		if ( isset($demo_list[$site_slug]['plugins']) && $demo_list[$site_slug]['plugins'] !== '' ) {
			$plugins = $demo_list[$site_slug]['plugins'];
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			foreach ( $plugins as $plugin ) {
				if ( isset($plugin_list[$plugin]['title']) && $plugin_list[$plugin]['title'] !== '' ) {
					$plugin_title = $plugin_list[$plugin]['title'];
				} else {
					$plugin_title = $plugin;
				}
				if ( isset($plugin_list[$plugin]['file']) && $plugin_list[$plugin]['file'] !== '' ) {
					$plugin_file = $plugin_list[$plugin]['file'];
					if ( is_plugin_active( $plugin_file ) ) {
						$log['plugins'][$plugin] = array(
							'title' => wp_slash($plugin_title),
							'pre_status' => 'active',
							'result' => 'none'
						);
					} else {
						// try to install plugin (also checks if installed but not active)
						$plugin_install = $this->install_plugin( $plugin_file );
						if ( $plugin_install['success'] ) {
							if ( $plugin_install['pre_status'] === 'installed' ) {
								$result = 'activated';
							} else {
								$result = 'installed and activated';
							}
							$log['plugins'][$plugin] = array(
								'title' => wp_slash($plugin_title),
								'pre_status' => $plugin_install['pre_status'],
								'result' => $result
							);
						} else {
							$log['is_error'] = true;
							$log['error_code'] = 3;
							$log['plugins'][$plugin] = array(
								'title' => wp_slash($plugin_title),
								'pre_status' => 'not installed',
								'result' => 'not installed'
							);
						}
					}
				}
			}
		}
		return $log;
	}

	/*
	 * Activate a single plugin if user has required permission.
	 */
	public function activate_plugin( $file ) {
		if ( current_user_can( 'activate_plugin', $file ) && is_plugin_inactive( $file ) ) {
			$result = activate_plugin( $file, false, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				return true;
			}
		}
		return false;
	}

	/*
	 * Install plugin from wp.org
	 */
	public function install_plugin( $plugin_file ) {
		$status = array();
		$plugin_slug = dirname( $plugin_file );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$all_plugins = get_plugins();
		$is_plugin_already_installed = false;
		foreach ( array_keys( $all_plugins ) as $plugin ) {
			if ( strpos( $plugin, $plugin_file ) !== false ) {
				$is_plugin_already_installed = true;
			}
		}
		if ( $is_plugin_already_installed ) {
			$activate_status = $this->activate_plugin( $plugin_file );
			$status['pre_status'] = 'installed'; 
		} else {
			$status['success'] = false;
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => sanitize_key( wp_unslash( $plugin_slug ) ),
					'fields' => array(
						'sections' => false,
					),
				)
			);
			if ( is_wp_error( $api ) ) {
				$status['errorMessage'] = $api->get_error_message();
				return $status;
			}
			$status['pluginName'] = $api->name;
			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $api->download_link );
			if ( is_wp_error( $result ) ) {
				$status['errorCode']    = $result->get_error_code();
				$status['errorMessage'] = $result->get_error_message();
				return $status;
			} elseif ( is_wp_error( $skin->result ) ) {
				$status['errorCode']    = $skin->result->get_error_code();
				$status['errorMessage'] = $skin->result->get_error_message();
				return $status;
			} elseif ( $skin->get_errors()->has_errors() ) {
				$status['errorMessage'] = $skin->get_error_messages();
				return $status;
			} elseif ( is_null( $result ) ) {
				global $wp_filesystem;
				$status['errorCode']    = 'unable_to_connect_to_filesystem';
				$status['errorMessage'] = __( 'Unable to connect to the filesystem. Please confirm your credentials.' );
				// Pass through the error from WP_Filesystem if one was raised.
				if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
					$status['errorMessage'] = esc_html( $wp_filesystem->errors->get_error_message() );
				}
				return $status;
			}
			$install_status = install_plugin_install_status( $api );
			$activate_status = $this->activate_plugin( $install_status['file'] );
			$status['pre_status'] = 'not installed';
		}
		if ( $activate_status && ! is_wp_error( $activate_status ) ) {
			$status['success'] = true;
		}
		return $status;
	}

	/**
	 * Processes content from xml file.
	 *
	 * @param array   $log activation log.
	 * @param array   $file xml file.
	 * @return array  $log appended activation log.
	 */
	public function process_content( $log, $file ) {
		$site_slug = $log['site']['demo_slug'];
		$theme = $log['theme']['slug'];
		$theme_parent = $log['theme_parent'];
		if ( $theme === $theme_parent ) {
			$is_child_theme = false;
		} else {
			$is_child_theme = true;
		}
		$orig_site_title = get_bloginfo('name');
		$orig_site_tagline = get_bloginfo('description');
		$xml = simplexml_load_file( $file );
		$namespaces = $xml->getDocNamespaces();
		if ( ! isset( $namespaces['wp'] ) ) {
			$namespaces['wp'] = 'http://wordpress.org/export/1.2/';
		}
		if ( ! isset( $namespaces['dc'] ) ) {
			$namespaces['dc'] = 'http://purl.org/dc/elements/1.1/';
		}
		if ( ! isset( $namespaces['content'] ) ) {
			$namespaces['content'] = 'http://purl.org/rss/1.0/modules/content/';
		}
		if ( ! isset( $namespaces['excerpt'] ) ) {
			$namespaces['excerpt'] = 'http://wordpress.org/export/1.2/excerpt/';
		}
		if ( ! isset( $namespaces['wfw'] ) ) {
			$namespaces['wfw'] = 'http://wellformedweb.org/CommentAPI/';
		}
		if ( ! isset( $namespaces['wc'] ) ) {
			$namespaces['wc'] = 'http://example.org/export/woocommerce/';
		}
		$channel_namespace = $xml->channel->children( $namespaces['wp'] );
		// Site Title
		$site_title = (string) $xml->channel->title;
		$log['site']['demo_title'] = wp_slash($site_title);
		$log['site']['orig_title'] = wp_slash( get_bloginfo('name') );
		if ( $site_title === $orig_site_title ) {
			$log['site']['title_result'] = 'none';
		} else {
			update_option( 'blogname', wp_unslash( $site_title ) );
			$log['site']['title_result'] = 'updated';
		}
		// Site Link
		$site_link = (string) $xml->channel->link;
		$log['site']['demo_url'] = wp_slash( trailingslashit( $site_link ) );
		$log['site']['new_url'] = wp_slash( trailingslashit( get_bloginfo('url') ) );
		// Site Description
		$site_description = (string) $xml->channel->description;
		$log['site']['demo_tagline'] = wp_slash($site_description);
		$log['site']['orig_tagline'] = wp_slash( get_bloginfo('description') );
		if ( $site_description === $orig_site_tagline ) {
			$log['site']['tagline_result'] = 'none';
		} else {
			update_option( 'blogdescription', wp_unslash( $site_description ) );
			$log['site']['tagline_result'] = 'updated';
		}
		// Site Options
		$site_options = $channel_namespace->options;
		$log['map_options'] = array(
			'site_logo' => $this->sanitize_data( $site_options->site_logo, 'site_logo' ),
			'site_icon' => $this->sanitize_data( $site_options->site_icon, 'site_icon' ),
			'show_on_front' => $this->sanitize_data( $site_options->show_on_front, 'show_on_front' ),
			'page_on_front' => $this->sanitize_data( $site_options->page_on_front, 'page_on_front' ),
			'page_for_posts' => $this->sanitize_data( $site_options->page_for_posts, 'page_for_posts' )
		);
		$log['site']['demo_title'] = wp_slash($site_title);
		$log['site']['orig_title'] = wp_slash( get_bloginfo('name') );
		if ( $site_title === $orig_site_title ) {
			$log['site']['title_result'] = 'none';
		} else {
			update_option( 'blogname', wp_unslash( $site_title ) );
			$log['site']['title_result'] = 'updated';
		}
		// WooCommerce product attributes
		if ( class_exists( 'WooCommerce' ) ) {
			foreach ( $channel_namespace->wc_attribute as $wc_attribute ) {
				$wc_attribute_namespace = $wc_attribute->children( $namespaces['wp'] );
				$wc_attribute_id = $this->sanitize_data( $wc_attribute_namespace->wc_attribute_id, 'wc_attribute_id' );
				$wc_attribute_name = $this->sanitize_data( $wc_attribute_namespace->wc_attribute_name, 'wc_attribute_name' );
				$wc_attribute_label = $this->sanitize_data( $wc_attribute_namespace->wc_attribute_label, 'wc_attribute_label' );
				$wc_attribute_type = $this->sanitize_data( $wc_attribute_namespace->wc_attribute_type, 'wc_attribute_type' );
				$wc_attribute_orderby = $this->sanitize_data( $wc_attribute_namespace->wc_attribute_orderby, 'wc_attribute_orderby' );
				$wc_attribute_public = $this->sanitize_data( $wc_attribute_namespace->wc_attribute_public, 'wc_attribute_public' );
				$exists_wc_attribute_id = wc_attribute_taxonomy_id_by_name( $wc_attribute_name );
				if ( $exists_wc_attribute_id ) {
					$log['wc_taxonomies'][$wc_attribute_id] = array(
						'title' => wp_slash($wc_attribute_label),
						'slug' => $wc_attribute_name,
						'new_id' => $exists_wc_attribute_id,
						'pre_status' => 'exists',
						'result' => 'none'
					);
				} else {
					$args = array(
						'name'         => $wc_attribute_label,
						'slug'         => $wc_attribute_name,
						'type'         => $wc_attribute_type,
						'order_by'     => $wc_attribute_orderby,
						'has_archives' => (int) $wc_attribute_public
					);
					$new_wc_attribute_id = wc_create_attribute( $args );
					if ( !is_wp_error($new_wc_attribute_id) ) {
						$taxonomy_name = wc_attribute_taxonomy_name( $wc_attribute_name );
						register_taxonomy(
							$taxonomy_name,
							apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
							apply_filters(
								'woocommerce_taxonomy_args_' . $taxonomy_name,
								array(
									'labels'       => array(
										'name' => $wc_attribute_label,
									),
									'hierarchical' => true,
									'show_ui'      => false,
									'query_var'    => true,
									'rewrite'      => false,
								)
							)
						);
						$log['wc_taxonomies'][$wc_attribute_id] = array(
							'title' => wp_slash($wc_attribute_label),
							'slug' => $wc_attribute_name,
							'new_id' => $new_wc_attribute_id,
							'pre_status' => 'new',
							'result' => 'added'
						);
					} else {
						$log['wc_taxonomies'][$wc_attribute_id] = array(
							'title' => wp_slash($wc_attribute_label),
							'slug' => $wc_attribute_name,
							'new_id' => null,
							'pre_status' => 'new',
							'result' => 'not added',
							'wp_error' => $new_wc_attribute_id
						);
					}
				}
			}
		}
		// START - terms
		foreach ( $channel_namespace->term as $term ) {
			$term_namespace = $term->children( $namespaces['wp'] );
			$term_id = $this->sanitize_data( $term_namespace->term_id, 'term_id' );
			$term_taxonomy = $this->sanitize_data( $term_namespace->term_taxonomy, 'term_taxonomy' );
			$term_slug = $this->sanitize_data( $term_namespace->term_slug, 'term_slug' );
			$term_parent = $this->sanitize_data( $term_namespace->term_parent, 'term_parent' );
			$term_link = $this->sanitize_data( $term_namespace->term_link, 'term_link' );
			$term_name = $this->sanitize_data( $term_namespace->term_name, 'term_name' );
			$term_description = $this->sanitize_data( $term_namespace->term_description, 'term_description' );
			// if child theme selected
			if ( $is_child_theme ) {
				$term_slug = $theme;
				$term_name = $theme;
			}
			$term_exists = term_exists( $term_slug, $term_taxonomy );
			if ( $term_exists ) {
				$log['terms'][$term_id] = array(
					'title' => wp_slash($term_name),
					'slug' => $term_slug,
					'taxonomy' => $term_taxonomy,
					'new_id' => $term_exists['term_id'],
					'pre_status' => 'exists',
					'result' => 'none'
				);
				if ( $term_link !== '' ) {
					$log['map_terms'][$term_id] = array(
						'new_id' => $term_exists['term_id'],
						'demo_url' => $term_link,
						'new_url' => get_term_link( (int) $term_exists['term_id'] )
					);
				} else {
					$log['map_terms'][$term_id] = array(
						'new_id' => $term_exists['term_id']
					);
				}
				$log['map_term_names'][$term_slug] = array(
					'taxonomy' => $term_taxonomy,
					'new_id' => $term_exists['term_id']
				);
			} else {
				$term_args = array(
					'alias_of' => '',
					'description' => wp_slash( $term_description ),
					'parent' => '',
					'slug' => $term_slug
				);
				if ( taxonomy_exists($term_taxonomy) ) {
					$insert_term = wp_insert_term( wp_slash( $term_name ), $term_taxonomy, $term_args );
					if ( !is_wp_error($insert_term) ) {
						$log['terms'][$term_id] = array(
							'title' => wp_slash($term_name),
							'slug' => $term_slug,
							'taxonomy' => $term_taxonomy,
							'new_id' => $insert_term['term_id'],
							'pre_status' => 'new',
							'result' => 'added'
						);
						if ( $term_link !== '' ) {
							$log['map_terms'][$term_id] = array(
								'new_id' => $insert_term['term_id'],
								'demo_url' => $term_link,
								'new_url' => get_term_link( (int) $insert_term['term_id'] )
							);
						} else {
							$log['map_terms'][$term_id] = array(
								'new_id' => $insert_term['term_id']
							);
						}
						$log['map_term_names'][$term_slug] = array(
							'taxonomy' => $term_taxonomy,
							'new_id' => $insert_term['term_id']
						);
					} else {
						$log['terms'][$term_id] = array(
							'title' => wp_slash($term_name),
							'slug' => $term_slug,
							'taxonomy' => $term_taxonomy,
							'new_id' => null,
							'pre_status' => 'new',
							'result' => 'not added',
							'wp_error' => $insert_term
						);
					}
				}
			}
		}
		// END - terms
		// START - items (content)
		foreach ( $xml->channel->item as $item ) {
			$namespace_wp = $item->children( $namespaces['wp'] );
			$namespace_content = $item->children( $namespaces['content'] );
			$namespace_excerpt = $item->children( $namespaces['excerpt'] );
			$post_title = $this->sanitize_data( $item->title, 'title' );
			$post_url = $this->sanitize_data( $item->link, 'link' );
			$post_id = $this->sanitize_data( $namespace_wp->post_id, 'post_id' );
			$post_parent = $this->sanitize_data( $namespace_wp->post_parent, 'post_parent' );
			$post_menu_order = $this->sanitize_data( $namespace_wp->menu_order, 'menu_order' );
			$post_name = $this->sanitize_data( $namespace_wp->post_name, 'post_name' );
			$post_type = $this->sanitize_data( $namespace_wp->post_type, 'post_type' );
			$post_content = $this->sanitize_data( $namespace_content->encoded, 'post_content', false );
			$post_excerpt = $this->sanitize_data( $namespace_excerpt->encoded, 'post_excerpt', false );
			$post_status = $this->sanitize_data( $namespace_wp->status, 'post_status' );
			// if child theme selected, then adjust template part args in content
			if ( $is_child_theme ) {
				$post_content = str_replace( '"theme":"'.$theme_parent.'"', '"theme":"'.$theme.'"', $post_content );
			}
			$post_terms = array();
			foreach ( $item->category as $c ) {
				$att = $c->attributes();
				if ( isset( $att['nicename'] ) ) {
					// if 'domain' is 'wp_theme' and child theme selected, then change 'nicename'
					if ( $att['domain'] === 'wp_theme' && $is_child_theme ) {
						$att['nicename'] = $theme;
					}
					$post_terms[(string) $att['nicename']] = (string) $att['domain'];
				}
			}
			$post_meta = array();
			if ( !empty($namespace_wp->postmeta)) {
				foreach ($namespace_wp->postmeta as $meta) {
					$meta_key = $this->sanitize_data( $meta->meta_key, 'meta_key' );
					$meta_value = $this->sanitize_data( $meta->meta_value, 'meta_value' );
					$meta_array = array(
						$meta_key => $meta_value
					);
					$post_meta = array_merge( $post_meta, $meta_array );
				}
			}
			// start - page, post, product
			if ( 'page' === $post_type || 'post' === $post_type || 'product' === $post_type ) {
				$log_post_id = $this->post_slug_exists( $post_name, $post_type );
				if ( $log_post_id ) {
					$post_args = array(
						'ID' => $log_post_id,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						//'tax_input' => $tax_input,
						'meta_input' => $post_meta
					);
					wp_update_post( wp_slash( $post_args ), true );
					// insert post terms
					if ( !empty($post_terms) ) {
						$terms_insert = array();
						$terms_list = '';
						foreach ( $post_terms as $term => $taxonomy ) {
							if ( isset( $log['map_term_names'][$term] ) ) {
								$terms_list .= $log['map_term_names'][$term]['new_id'] . ' ';
							}
							$terms_insert[$taxonomy] = array_map( 'intval', explode( ' ', trim($terms_list) ) );
						}
						foreach ( $terms_insert as $taxonomy => $term_ids ) {
							wp_set_object_terms( $log_post_id, $term_ids, $taxonomy );
						}
					}
					$log['content'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $log_post_id,
						'pre_status' => 'exists',
						'result' => 'updated'
					);
					$log['map_posts'][$post_id] = array(
						'new_id' => $log_post_id,
						'demo_url' => $post_url,
						'new_url' => get_permalink($log_post_id)
					);
				} else {
					if ( 'post' === $post_type || 'product' === $post_type ) {
						$post_date = date( 'Y-m-d H:i:s', time() - mt_rand(1,864000) );
					} else {
						$post_date = '';
					}
					$post_args = array(
						'post_date' => $post_date,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'post_type' => $post_type,
						//'tax_input' => $tax_input,
						'meta_input' => $post_meta
					);
					$log_post_id = wp_insert_post( wp_slash( $post_args ), true );
					// insert post terms
					if ( !empty($post_terms) ) {
						$terms_insert = array();
						$terms_list = '';
						foreach ( $post_terms as $term => $taxonomy ) {
							if ( isset( $log['map_term_names'][$term] ) ) {
								$terms_list .= $log['map_term_names'][$term]['new_id'] . ' ';
							}
							$terms_insert[$taxonomy] = array_map( 'intval', explode( ' ', trim($terms_list) ) );
						}
						foreach ( $terms_insert as $taxonomy => $term_ids ) {
							wp_set_object_terms( $log_post_id, $term_ids, $taxonomy );
						}
					}
					$log['content'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $log_post_id,
						'pre_status' => 'none',
						'result' => 'added'
					);
					$log['map_posts'][$post_id] = array(
						'new_id' => $log_post_id,
						'demo_url' => $post_url,
						'new_url' => get_permalink($log_post_id)
					);
				}
				// sub-start - product - extra processing
				if ( 'product' === $post_type && !empty($post_terms) && isset($post_meta['_product_attributes']) ) {
					$product_attributes = maybe_unserialize($post_meta['_product_attributes']);
					$term_attributes = array();
					// build an array of taxonomy (product attribute) => terms (options)
					foreach ( $post_terms as $term => $taxonomy ) {
						if ( str_starts_with($taxonomy, 'pa_') ) {
							if ( isset($term_attributes[$taxonomy]) ) {
								$term_attributes[$taxonomy] .= '|' . $term;
							} else {
								$term_attributes[$taxonomy] = $term;
							}
						}
					}
					foreach ( $term_attributes as $key => $value ) {
						// if a product attribute (from the post_meta '_product_attributes' value)
						// e.g. 'pa_color' matches with a taxonomy of the post terms
						if ( isset($product_attributes[$key]) ) {
							$product_attributes[$key]['options'] = explode('|', $value);
						}
					}
					// **save this until after all content, and before adding variations**
					$log['product_attrs_to_add'][$log_post_id] = $product_attributes;
				}
				// sub-end - product - extra processing
			}
			// end - page, post, product
			// start - product variation
			elseif ( 'product_variation' === $post_type ) {
				if ( isset($log['map_posts'][$post_parent]['new_id']) ) {
					$new_prod_var_parent_id = $log['map_posts'][$post_parent]['new_id'];
					$prod_var_props = $post_meta;
					$prod_var_props['parent_id'] = $new_prod_var_parent_id;
					$prod_var_props['status'] = $post_status;
					$prod_var_props['menu_order'] = $post_menu_order;
					$prod_var_attrs = array();
					foreach ($post_meta as $key => $value) {
						//if key starts with attribute_ then add it to $prod_var_attrs
						if ( str_starts_with($key, 'attribute_') ) {
							$prod_var_attrs[$key] = $value;
						}
					}
					$prod_var_props['attributes'] = $prod_var_attrs;
					$prod_var_props['display_slug'] = $post_name;
					$prod_var_props['display_excerpt'] = $post_excerpt;
					// **save this until after all content as we need to make sure it is processed AFTER the parent product**
					$log['product_vars_to_add'][$post_id] = $prod_var_props;
				}
			}
			// end - product variation
			// start - patterns
			elseif ( 'wp_block' === $post_type ) {
				$exists_post_id = $this->post_slug_exists( $post_name, $post_type );
				if ( $exists_post_id ) {
					$post_args = array(
						'ID' => $exists_post_id,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'meta_input' => $post_meta
					);
					wp_update_post( wp_slash( $post_args ), true );
					// insert post terms
					if ( !empty($post_terms) ) {
						$terms_insert = array();
						$terms_list = '';
						foreach ( $post_terms as $term => $taxonomy ) {
							if ( isset( $log['map_term_names'][$term] ) ) {
								$terms_list .= $log['map_term_names'][$term]['new_id'] . ' ';
							}
							$terms_insert[$taxonomy] = array_map( 'intval', explode( ' ', trim($terms_list) ) );
						}
						foreach ( $terms_insert as $taxonomy => $term_ids ) {
							wp_set_object_terms( $exists_post_id, $term_ids, $taxonomy );
						}
					}
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $exists_post_id,
						'pre_status' => 'exists',
						'result' => 'updated'
					);
					$log['map_patterns'][$post_id] = array(
						'new_id' => $exists_post_id
					);
				} else {
					$post_args = array(
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'post_type' => $post_type,
						'meta_input' => $post_meta
					);
					$new_post_id = wp_insert_post( wp_slash( $post_args ), true );
					// insert post terms
					if ( !empty($post_terms) ) {
						$terms_insert = array();
						$terms_list = '';
						foreach ( $post_terms as $term => $taxonomy ) {
							if ( isset( $log['map_term_names'][$term] ) ) {
								$terms_list .= $log['map_term_names'][$term]['new_id'] . ' ';
							}
							$terms_insert[$taxonomy] = array_map( 'intval', explode( ' ', trim($terms_list) ) );
						}
						foreach ( $terms_insert as $taxonomy => $term_ids ) {
							wp_set_object_terms( $new_post_id, $term_ids, $taxonomy );
						}
					}
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $new_post_id,
						'pre_status' => 'none',
						'result' => 'added'
					);
					$log['map_patterns'][$post_id] = array(
						'new_id' => $new_post_id
					);
				}
			}
			// end - patterns
			// start - wp_navigation
			elseif ( 'wp_navigation' === $post_type ) {
				$exists_post_id = $this->post_slug_exists( $post_name, $post_type );
				if ( $exists_post_id ) {
					$post_args = array(
						'ID' => $exists_post_id,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'meta_input' => $post_meta
					);
					wp_update_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $exists_post_id,
						'pre_status' => 'exists',
						'result' => 'updated'
					);
					$log['map_navigation'][$post_id] = array(
						'new_id' => $exists_post_id
					);
				} else {
					$post_args = array(
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'post_type' => $post_type,
						'meta_input' => $post_meta
					);
					$new_post_id = wp_insert_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $new_post_id,
						'pre_status' => 'none',
						'result' => 'added'
					);
					$log['map_navigation'][$post_id] = array(
						'new_id' => $new_post_id
					);
				}
			}
			// end - wp_navigation
			// start - wp_global_styles, custom_css
			elseif ( 'wp_global_styles' === $post_type || 'custom_css' === $post_type ) {
				// if child theme selected, then change $post_title and $post_name values
				if ( $is_child_theme ) {
					$post_title = str_replace( $theme_parent, $theme, $post_title );
					$post_name = str_replace( $theme_parent, $theme, $post_name );
				}
				$tax_input = array();
				if ( $item->category ) {
					foreach ( $item->category as $category ) {
						$category_domain = $this->sanitize_data( $category['domain'], '' );
						$category_slug = $this->sanitize_data( $category['nicename'], '' );
						// if child theme selected, then change 'nicename'
						if ( 'wp_theme' === $category_domain && $theme_parent === $category_slug && $is_child_theme ) {
							$tax_input[$category_domain] = $theme;
						} else {
							$tax_input[$category_domain] = $category_slug;
						}
					}
				}
				$exists_post_id = $this->post_slug_exists( $post_name, $post_type );
				if ( $exists_post_id ) {
					$post_args = array(
						'ID' => $exists_post_id,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'tax_input' => $tax_input,
						'meta_input' => $post_meta
					);
					wp_update_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $exists_post_id,
						'pre_status' => 'exists',
						'result' => 'updated'
					);
				} else {
					$post_args = array(
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'post_type' => $post_type,
						'tax_input' => $tax_input,
						'meta_input' => $post_meta
					);
					$new_post_id = wp_insert_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $new_post_id,
						'pre_status' => 'none',
						'result' => 'added'
					);
				}
			}
			// end - wp_global_styles, custom_css
			// start - wp_template, wp_template_part
			elseif ( 'wp_template' === $post_type || 'wp_template_part' === $post_type ) {
				// Process templates & template parts, tied to the theme
				$tax_input = array();
				if ( $item->category ) {
					foreach ( $item->category as $category ) {
						$category_domain = $this->sanitize_data( $category['domain'], '' );
						$category_slug = $this->sanitize_data( $category['nicename'], '' );
						// if child theme selected, then change 'nicename'
						if ( 'wp_theme' === $category_domain && $theme_parent === $category_slug && $is_child_theme ) {
							$tax_input[$category_domain] = $theme;
						} else {
							$tax_input[$category_domain] = $category_slug;
						}
					}
				}
				$exists_post_id = $this->theme_template_in_db( $post_name, $post_type, $theme );
				if ( $exists_post_id ) {
					$post_args = array(
						'ID' => $exists_post_id,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'tax_input' => $tax_input,
						'meta_input' => $post_meta
					);
					wp_update_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $exists_post_id,
						'pre_status' => 'exists',
						'result' => 'updated'
					);
				} else {
					// is template/part included in theme?
					if ( get_block_template( $theme.'//'.$post_name, $post_type ) ) {
						$log_result = 'updated';
					} else {
						$log_result = 'added';
					}
					$post_args = array(
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'post_type' => $post_type,
						'tax_input' => $tax_input,
						'meta_input' => $post_meta
					);
					$new_post_id = wp_insert_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $new_post_id,
						'pre_status' => 'none',
						'result' => $log_result
					);
					$log['fix_slugs'][$new_post_id] = $post_name;
				}
			}
			// end - wp_template, wp_template_part
			// start - font families
			elseif ( 'wp_font_family' === $post_type ) {
				$exists_post_id = $this->post_slug_exists( $post_name, $post_type );
				if ( $exists_post_id ) {
					$post_args = array(
						'ID' => $exists_post_id,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'meta_input' => $post_meta
					);
					wp_update_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $exists_post_id,
						'pre_status' => 'exists',
						'result' => 'none'
					);
					$log['map_fonts'][$post_id] = array(
						'new_id' => $exists_post_id
					);
				} else {
					$post_args = array(
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'post_type' => $post_type,
						'meta_input' => $post_meta
					);
					$new_post_id = wp_insert_post( wp_slash( $post_args ), true );
					$log['design'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $new_post_id,
						'pre_status' => 'none',
						'result' => 'added'
					);
					$log['map_fonts'][$post_id] = array(
						'new_id' => $new_post_id
					);
				}
			}
			// end - font families
			// start - font faces
			elseif ( 'wp_font_face' === $post_type ) {
				$content_decoded = json_decode( $post_content, true );
				if ( isset( $content_decoded['src'] ) && $content_decoded['src'] !== '' ) {
					$font_face_url = $content_decoded['src'];
					$exists_post_id = $this->font_face_exists( $font_face_url, $post_name );
					if ( $exists_post_id ) {
						$log['design'][$post_id] = array(
							'title' => wp_slash($post_title),
							'slug' => $post_name,
							'post_type' => $post_type,
							'new_id' => $exists_post_id,
							'pre_status' => 'exists',
							'result' => 'none'
						);
						$log['map_font_faces'][$post_id] = array(
							'new_id' => $exists_post_id,
							'demo_url' => $font_face_url,
							'new_url' => $this->font_face_new_url( $font_face_url )
						);
					} else {
						$post = array(
							'title' => $post_title,
							'slug' => $post_name
						);
						// add new font face
						$font_face_upload = $this->font_face_upload( $font_face_url, $post_title );
						if ( $font_face_upload ) {
							$post_args = array(
								'post_content' => $post_content,
								'post_title' => $post_title,
								'post_excerpt' => $post_excerpt,
								'post_status' => $post_status,
								'post_name' => $post_name,
								'post_parent' => $post_parent,
								'menu_order' => $post_menu_order,
								'post_type' => $post_type,
								'meta_input' => $post_meta
							);
							$new_post_id = wp_insert_post( wp_slash( $post_args ), true );
							$log['design'][$post_id] = array(
								'title' => wp_slash($post_title),
								'slug' => $post_name,
								'post_type' => $post_type,
								'new_id' => $new_post_id,
								'pre_status' => 'none',
								'result' => 'added'
							);
							$log['map_font_faces'][$post_id] = array(
								'new_id' => $new_post_id,
								'demo_url' => $font_face_url,
								'new_url' => $font_face_upload['url']
							);
						}
					}
				}
			}
			// end - font faces
			// start - attachment
			elseif ( 'attachment' === $post_type ) {
				$attachment_url = $this->sanitize_data( $namespace_wp->attachment_url, 'attachment_url' );
				$attachment_mime_type = $this->sanitize_data( $namespace_wp->post_mime_type, 'post_mime_type' );
				$exists_post_id = $this->attachment_exists( $attachment_url, $attachment_mime_type );
				if ( $exists_post_id ) {
					$log['attachments'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $exists_post_id,
						'pre_status' => 'exists',
						'result' => 'none'
					);
					$new_attachment_url = wp_get_attachment_image_url( $exists_post_id, 'full' );
					$log['map_attachments'][$post_id] = array(
						'new_id' => $exists_post_id,
						'demo_link' => $post_url,
						'demo_url' => $attachment_url,
						'new_link' => get_permalink($exists_post_id),
						'new_url' => $new_attachment_url,
						'sizes' => $this->attachment_sizes_map( $exists_post_id, $attachment_url, $new_attachment_url ,$post_meta )
					);
				} else {
					// add new attachment
					$new_post_id = $this->attachment_upload( $attachment_url, $post_title );
					$log['attachments'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $new_post_id,
						'pre_status' => 'none',
						'result' => 'added'
					);
					$new_attachment_url = wp_get_attachment_image_url( $new_post_id, 'full' );
					$log['map_attachments'][$post_id] = array(
						'new_id' => $new_post_id,
						'demo_link' => $post_url,
						'demo_url' => $attachment_url,
						'new_link' => get_permalink($new_post_id),
						'new_url' => $new_attachment_url,
						'sizes' => $this->attachment_sizes_map( $new_post_id, $attachment_url, $new_attachment_url ,$post_meta )
					);
				}
			}
			// end - attachment
			// start - other
			else {
				$exists_post_id = $this->post_slug_exists( $post_name, $post_type );
				if ( $exists_post_id ) {
					$post_args = array(
						'ID' => $exists_post_id,
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'meta_input' => $post_meta
					);
					wp_update_post( wp_slash( $post_args ), true );
					// insert post terms
					if ( !empty($post_terms) ) {
						$terms_insert = array();
						$terms_list = '';
						foreach ( $post_terms as $term => $taxonomy ) {
							if ( isset( $log['map_term_names'][$term] ) ) {
								$terms_list .= $log['map_term_names'][$term]['new_id'] . ' ';
							}
							$terms_insert[$taxonomy] = array_map( 'intval', explode( ' ', trim($terms_list) ) );
						}
						foreach ( $terms_insert as $taxonomy => $term_ids ) {
							wp_set_object_terms( $exists_post_id, $term_ids, $taxonomy );
						}
					}
					$log['other'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $exists_post_id,
						'pre_status' => 'exists',
						'result' => 'updated'
					);
					$log['map_other'][$post_id] = array(
						'new_id' => $exists_post_id,
						'demo_url' => $post_url,
						'new_url' => get_permalink($exists_post_id)
					);
				} else {
					$post_args = array(
						'post_content' => $post_content,
						'post_title' => $post_title,
						'post_excerpt' => $post_excerpt,
						'post_status' => $post_status,
						'post_name' => $post_name,
						'post_parent' => $post_parent,
						'menu_order' => $post_menu_order,
						'post_type' => $post_type,
						'meta_input' => $post_meta
					);
					$new_post_id = wp_insert_post( wp_slash( $post_args ), true );
					// insert post terms
					if ( !empty($post_terms) ) {
						$terms_insert = array();
						$terms_list = '';
						foreach ( $post_terms as $term => $taxonomy ) {
							if ( isset( $log['map_term_names'][$term] ) ) {
								$terms_list .= $log['map_term_names'][$term]['new_id'] . ' ';
							}
							$terms_insert[$taxonomy] = array_map( 'intval', explode( ' ', trim($terms_list) ) );
						}
						foreach ( $terms_insert as $taxonomy => $term_ids ) {
							wp_set_object_terms( $new_post_id, $term_ids, $taxonomy );
						}
					}
					$log['other'][$post_id] = array(
						'title' => wp_slash($post_title),
						'slug' => $post_name,
						'post_type' => $post_type,
						'new_id' => $new_post_id,
						'pre_status' => 'none',
						'result' => 'added'
					);
					$log['map_other'][$post_id] = array(
						'new_id' => $new_post_id,
						'demo_url' => $post_url,
						'new_url' => get_permalink($new_post_id)
					);
				}
			}
			// end - other
		}
		// END - items (content)
		// start - process product attributes and variations
		if ( class_exists('WooCommerce') ) {
			// add the product attributes
			if ( isset($log['product_attrs_to_add']) ) {
				foreach ( $log['product_attrs_to_add'] as $key => $value ) {
					foreach ( $value as $attrs ) {
						$this->set_product_attribute( $key, $attrs );
					}
				}
			}
			// add the product variations
			if ( isset($log['product_vars_to_add']) ) {
				foreach ( $log['product_vars_to_add'] as $key => $props ) {
					if ( in_array( $props['display_slug'], $this->get_product_variations( $props['parent_id'] ) ) ) {
						$log['product_variations'][$props['parent_id']][$key] = array(
							'title' => wp_slash($props['display_excerpt']),
							'slug' => $props['display_slug'],
							'pre_status' => 'exists',
							'result' => 'none'
						);
					} else {
						$variation_id = $this->set_product_variation( $props );
						if ( !is_wp_error($variation_id) ) {
							$log['product_variations'][$props['parent_id']][$key] = array(
								'title' => wp_slash($props['display_excerpt']),
								'slug' => $props['display_slug'],
								'pre_status' => 'none',
								'result' => 'added'
							);
						} else {
							$log['product_variations'][$props['parent_id']][$key] = array(
								'title' => wp_slash($props['display_excerpt']),
								'slug' => $props['display_slug'],
								'pre_status' => 'none',
								'result' => 'not added',
								'wp_error' => $variation_id
							);
						}
					}
				}
			}
		}
		// end - process product attributes and variations
		return $log;
	}

	/**
	 * Save product attribute.
	 *
	 * @param int     $product_id WP post id.
	 * @param array   $attrs product attributes.
	 */
	public function set_product_attribute( $product_id, $attrs ) {
		$product = wc_get_product($product_id);
		if ( $product ) {
			$attribute = new \WC_Product_Attribute();
			if ( $attrs['is_taxonomy'] ) {
				$attribute->set_id( wc_attribute_taxonomy_id_by_name( $attrs['name'] ) );
			}
			$attribute->set_name( $attrs['name'] );
			$attribute->set_options( $attrs['options'] );
			$attribute->set_position( $attrs['position'] );
			$attribute->set_visible( $attrs['is_visible'] );
			$attribute->set_variation( $attrs['is_variation'] );
			$product->set_attributes( array( $attribute ) );
			$product->save();
		}
	}

	/**
	 * Get product variations slugs.
	 *
	 * @param int     $product_id WP post id.
	 * @return array
	 */
	public function get_product_variations( $product_id ) {
		$product_variations = array();
		$variations = wc_get_products( array(
			'parent' => $product_id,
			'type' => 'variation',
		) );
		if ( $variations ) {
			foreach ( $variations as $variation ) {
				$product_variations[] = $variation->get_slug();
			}
		}
		return $product_variations;
	}

	/**
	 * Save product variation.
	 *
	 * @param array   $props variation properties.
	 * @return int    $variation_id WP post id.
	 */
	public function set_product_variation( $props ) {
		$variation = new \WC_Product_Variation();
		foreach ( $props as $key => $value ) {
			if ( $key === 'parent_id' ) {
				$variation->set_parent_id( $value );
			} elseif ( $key === 'status' ) {
				$variation->set_status( $value );
			} elseif ( $key === 'menu_order' ) {
				$variation->set_menu_order( $value );
			} elseif ( $key === '_price' ) {
				$variation->set_price( $value );
			} elseif ( $key === '_regular_price' ) {
				$variation->set_regular_price( $value );
			} elseif ( $key === '_sale_price' ) {
				$variation->set_sale_price( $value );
			} elseif ( $key === '_virtual' ) {
				$variation->set_virtual( $value );
			} elseif ( $key === '_downloadable' ) {
				$variation->set_downloadable( $value );
			} elseif ( $key === '_sku' ) {
				$variation->set_sku( $value );
			} elseif ( $key === '_weight' ) {
				$variation->set_weight( $value );
			} elseif ( $key === '_length' ) {
				$variation->set_length( $value );
			} elseif ( $key === '_width' ) {
				$variation->set_width( $value );
			} elseif ( $key === '_height' ) {
				$variation->set_height( $value );
			} /*elseif ( $key === '_product_image_gallery' ) {
				$variation->set_gallery_image_ids( $value );//????
			}*/ elseif ( $key === '_variation_description' ) {
				$variation->set_description( $value );
			} elseif ( $key === '_thumbnail_id' ) {
				$variation->set_image_id( $value );// need to map to new attachment ID
			} elseif ( $key === 'attributes' ) {
				$variation->set_attributes( $value );
			}
		}
		$variation_id = $variation->save();
		return $variation_id;
	}

	/**
	 * Upload and save media attachment.
	 *
	 * @param string  $url media URI.
	 * @param string  $title media title.
	 * @return int    WP post id.
	 */
	public function attachment_upload( $url, $title ) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		$temp_file = download_url( $url );
		if( is_wp_error( $temp_file ) ) {
			return false;
		}
		$file = array(
			'name'     => wp_basename( $url ),
			'type'     => wp_check_filetype( $temp_file ),
			'tmp_name' => $temp_file,
			'size'     => filesize( $temp_file ),
		);
		$sideload = wp_handle_sideload(
			$file,
			array(
				'test_form'   => false
			)
		);
		if( ! empty( $sideload[ 'error' ] ) ) {
			return false;
		}
		if ( $title === '' ) {
			$title = wp_basename( $sideload[ 'file' ] );
		}
		$attachment_id = wp_insert_attachment(
			array(
				'guid'           => $sideload[ 'url' ],
				'post_mime_type' => $sideload[ 'type' ],
				'post_title'     => $title,
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$sideload[ 'file' ]
		);
		if( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return false;
		}
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $sideload[ 'file' ] )
		);
		return $attachment_id;
	}

	/**
	 * Check if a given attachment exists.
	 *
	 * @param string     $attachment_url attachment URI.
	 * @param string     $mime_type mime type
	 * @return int/bool  WP post id if exists, bool false otherwise.
	 */
	public function attachment_exists( $attachment_url, $mime_type = 'image/jpeg' ) {
		$uploads_info = wp_get_upload_dir();
		$file_name = wp_basename( $attachment_url );
		$check_file = trailingslashit( $uploads_info['url'] ) . $file_name;
		global $wpdb;
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid = '%1s' AND post_type = 'attachment' AND post_mime_type = '%2s' LIMIT 1",
				$check_file,
				$mime_type
			)
		);
		if ( $result ) {
			return $result[0]->ID;
		} else {
			return false;
		}
	}

	public function attachment_sizes_map( $id, $demo_attachment_url, $new_attachment_url, $post_meta ) {
		if ( isset( $post_meta['_wp_attachment_metadata'] ) && !empty( $post_meta['_wp_attachment_metadata'] ) ) {
			$demo_img_dir = trailingslashit( dirname( $demo_attachment_url ) );
			$attachment_sizes = $post_meta['_wp_attachment_metadata'];
			$demo_img_meta = maybe_unserialize($attachment_sizes);
			$image_map = array();
			$image_map[$demo_attachment_url] = $new_attachment_url;
			$new_img_meta = wp_get_attachment_metadata( $id );
			if ( isset( $demo_img_meta['sizes'] ) && is_array( $demo_img_meta['sizes'] ) ) {
				foreach ( $demo_img_meta['sizes'] as $size => $size_data ) {
					if ( isset( $size_data['file'] ) && '' !== $size_data['file'] ) {
						if ( isset( $new_img_meta['sizes'][$size]['file'] ) && '' !== $new_img_meta['sizes'][$size]['file'] ) {
							$image_map[$demo_img_dir . $size_data['file']] = wp_get_attachment_image_url( $id, $size );
						} else {
							$image_map[$demo_img_dir . $size_data['file']] = $new_attachment_url;
						}
					}
				}

			}
		} else {
			$image_map = '';
		}
		return $image_map;
	}

	public function handle_font_file_upload_error( $file, $message ) {
		$status = 500;
		$code   = 'rest_font_upload_unknown_error';
		if ( __( 'Sorry, you are not allowed to upload this file type.', 'starter-sites' ) === $message ) {
			$status = 400;
			$code   = 'rest_font_upload_invalid_file_type';
		}
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	public function handle_font_file_upload( $file ) {
		add_filter( 'upload_mimes', array( 'WP_Font_Utils', 'get_allowed_font_mime_types' ) );
		// Filter the upload directory to return the fonts directory.
		add_filter( 'upload_dir', '_wp_filter_font_directory' );
		$overrides = array(
			'upload_error_handler' => array( $this, 'handle_font_file_upload_error' ),
			'action'               => 'wp_handle_font_upload',
			'test_form'            => false,
			// Only allow uploading font files for this request.
			'mimes'                => \WP_Font_Utils::get_allowed_font_mime_types(),
		);
		$uploaded_file = wp_handle_upload( $file, $overrides );
		remove_filter( 'upload_dir', '_wp_filter_font_directory' );
		remove_filter( 'upload_mimes', array( 'WP_Font_Utils', 'get_allowed_font_mime_types' ) );
		return $uploaded_file;
	}

	/**
	 * Upload a font face.
	 *
	 * @param string     $url font URI.
	 * @param string     $title font title.
	 * @return int/bool  WP post id, false otherwise.
	 */
	public function font_face_upload( $url, $title ) {
		$temp_file = download_url( $url );
		if ( is_wp_error( $temp_file ) ) {
			return false;
		} else {
			$file = array(
				'name'     => wp_basename( $url ),
				'type'     => wp_check_filetype( $temp_file ),
				'tmp_name' => $temp_file,
				'size'     => filesize( $temp_file ),
			);
			$file_upload = $this->handle_font_file_upload( $file );
			return $file_upload;
		}
	}

	/**
	 * Check if a font face exists.
	 *
	 * @param string     $font_face_url font URI.
	 * @param string     $post_name WP post name.
	 * @return int/bool  WP post id if exists, false otherwise.
	 */
	public function font_face_exists( $font_face_url, $post_name ) {
		$font_dir = wp_get_font_dir();
		$file_name = wp_basename( $font_face_url );
		$check_file = trailingslashit( $font_dir['basedir'] ) . $file_name;
		if ( file_exists($check_file) ) {
			$post_id = $this->post_slug_exists( $post_name, 'wp_font_face' );
			if ( $post_id ) {
				return $post_id;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Returns a renovated font face URI.
	 *
	 * @param string   $font_face_url font URI.
	 * @return string  modified font URI, demo site URI replaced with this installation's font upload directory.
	 */
	public function font_face_new_url( $font_face_url ) {
		$font_dir = wp_get_font_dir();
		$file_name = wp_basename( $font_face_url );
		return trailingslashit( $font_dir['baseurl'] ) . $file_name;
	}

	/**
	 * Check if a post exists.
	 *
	 * @param string     $slug WP post name.
	 * @param string     $type WP post type.
	 * @return int/bool  WP post id if exists, false otherwise.
	 */
	public function post_slug_exists( $slug, $type = 'post' ) {
		$args = array(
			'post_type' => $type,
			'name' => $slug,
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

	/**
	 * Check if a theme template/part exists as a user customized post (an edited template/part).
	 *
	 * @param string  $slug WP post name.
	 * @param string  $type WP post type.
	 * @param string  $theme_slug theme slug.
	 * @return int    WP post id if exists.
	 */
	public function theme_template_in_db( $slug = '', $type = '', $theme_slug = '' ) {
		$post_id = 0;
		$args = array(
			'post_type' => $type,
			'name' => $slug, // Returns all templates with this slug (from all themes) whether using or not using 'tax_query'
			//'post_name' => $slug, // Returns all templates of the theme if using 'tax_query' || Returns all templates from all themes if not using 'tax_query'
			'nopaging' => true,
			'posts_per_page' => -1,
			/*'tax_query' => array(
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'slug',
					'terms'    => array( $theme_slug )
				)
			)*/ // Leave these comments, as helps explain the weirdness above.
		);
		$posts = new \WP_Query( $args );
		if ( $posts->have_posts() ) {
			while ( $posts->have_posts() ) {
				$posts->the_post();
				$terms = get_the_terms( $posts->post->ID, 'wp_theme' );
				if ( $terms && $terms[0]->slug === $theme_slug ) {
					$post_id = $posts->post->ID;
					break;
				}
			}
		}
		return $post_id;
	}

	/**
	 * Sanitize data, and return strings that can be used for conditional checks.
	 */
	public function sanitize_data( $input, $field, $unslash = true ) {
		$input = str_replace( [ '<![CDATA[', ']]>' ], '', $input );
		if ( $unslash ) {
			$output = wp_unslash( sanitize_post_field( $field, $input, 0, 'db' ) );
		} else {
			$output = $input;
		}
		return $output;
	}

	/**
	 * Returns an error text string.
	 *
	 * @param int     $id
	 * @return string
	 */
	public function error_codes( $id = 0 ) {
		$text_strings = array(
			0 => __( 'There was an unknown error.', 'starter-sites' ),
			1 => __( 'The requested import file could not be found.', 'starter-sites' ),
			2 => __( 'The required theme could not be installed.', 'starter-sites' ),
			3 => __( 'A required plugin could not be installed.', 'starter-sites' ),
			4 => __( 'This is not a valid starter site.', 'starter-sites' ),
		);
		return $text_strings[$id];
	}

	/**
	 * Include files.
	 */
	public function includes() {
		require_once STARTER_SITES_PATH . 'inc/mapping.php';
	}

	public function __construct() {
		$this->includes();
	}

}
