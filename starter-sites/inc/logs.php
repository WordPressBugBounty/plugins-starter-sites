<?php
namespace Starter_Sites;

defined( 'ABSPATH' ) || exit;

class Logs {

	public function base_link() {
		$settings = get_option( 'starter_sites_settings' );
		$base_link = 'admin.php';
		if ( isset($settings['menu_location']) ) {
			if ( 'appearance' === $settings['menu_location'] ) {
				$base_link = 'themes.php';
			} elseif ( 'tools' === $settings['menu_location'] ) {
				$base_link = 'tools.php';
			}
		}
		return $base_link;
	}

	/**
	 * The logs table view.
	 */
	public function get_logs() {
		$args = [
			'numberposts' => -1,
			'order' => 'DESC',
			'orderby' => 'date',
			'post_status' => 'private',
			'post_type' => 'starter_sites_log',
		];
		$posts = get_posts( $args );
		if ( $posts ) {
			$base_link = admin_url( $this->base_link() );
			$site_date_format = get_option( 'date_format' );
			$site_time_format = get_option( 'time_format' );
			?>
			<table class="starter-sites-log-table">
				<thead>
					<tr class="section-heading">
						<th colspan="4"><?php esc_html_e( 'Activation Logs', 'starter-sites' );?></th>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Date', 'starter-sites' );?></th>
						<th><?php esc_html_e( 'User', 'starter-sites' );?></th>
						<th colspan="2"><?php esc_html_e( 'Site', 'starter-sites' );?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $posts as $post ) {
					$view_url = add_query_arg(
						[
							'page' => 'starter-sites',
							'tab' => 'logs',
							'log_id' => $post->ID
						],
						$base_link
					);
					$post_log = maybe_unserialize( $post->post_content );
					$site_title = '';
					if ( isset( $post_log['site']['demo_title'] ) ) {
						$site_title = $post_log['site']['demo_title'];
					}
					?>
					<tr>
						<td><span class="log-date"><?php echo esc_html( mysql2date( $site_date_format, $post->post_date ) );?></span> <span class="log-time"><?php echo esc_html( mysql2date( $site_time_format, $post->post_date ) );?></span></td>
						<td><?php echo esc_html( get_userdata($post->post_author)->display_name );?></td>
						<td><?php echo esc_html( $site_title );?></td>
						<td><a class="text-link" href="<?php echo esc_url( $view_url );?>"><?php esc_html_e( 'View Log', 'starter-sites' );?></a></td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
			<?php
			wp_reset_postdata();
		} else {
			?>
			<p><?php esc_html_e( 'There are no activation logs to display.', 'starter-sites' );?></p>
			<?php
		}
	}

	/**
	 * The single log view.
	 */
	public function view_log( $log ) {
		$admin_link = admin_url( $this->base_link() );
		$site_editor_link = admin_url( 'site-editor.php' );
		$posts_link = admin_url( 'post.php' );
		$terms_link = admin_url( 'term.php' );
		$log = maybe_unserialize( wp_unslash( $log ) );
		$site_date_format = get_option( 'date_format' );
		$site_time_format = get_option( 'time_format' );
		?>
		<table class="starter-sites-log-table">
		<tr class="section-heading">
			<td colspan="5"><?php esc_html_e('Activation Log', 'starter-sites');?></td>
		</tr>
		<?php
		if ( isset($log['user_id']) ) {
			?>
			<tr>
				<td class="row-type"><?php esc_html_e('User', 'starter-sites');?></td>
				<td colspan="4"><?php esc_html_e( get_userdata($log['user_id'])->display_name );?></td>
			</tr>
			<?php
		}
		if ( isset($log['time_start']) ) {
			?>
			<tr>
				<td class="row-type"><?php esc_html_e('Start', 'starter-sites');?></td>
				<td colspan="4"><?php esc_html_e( wp_date( $site_date_format, $log['time_start'] ) );?> <?php esc_html_e( wp_date( $site_time_format, $log['time_start'] ) );?></td>
			</tr>
			<?php
		}
		if ( isset($log['time_end']) ) {
			?>
			<tr>
				<td class="row-type"><?php esc_html_e('End', 'starter-sites');?></td>
				<td colspan="4"><?php esc_html_e( wp_date( $site_date_format, $log['time_end'] ) );?> <?php esc_html_e( wp_date( $site_time_format, $log['time_end'] ) );?></td>
			</tr>
			<?php
		}
		if ( isset($log['time_start']) && isset($log['time_end']) ) {
			?>
			<tr>
				<td class="row-type"><?php esc_html_e('Processing time', 'starter-sites');?></td>
				<td colspan="4"><?php esc_html_e( human_time_diff( $log['time_start'], $log['time_end'] ) );?></td>
			</tr>
			<?php
		}
		?>
		<tr class="is-empty">
			<td class="spacer" colspan="5"></td>
		</tr>
		<?php
		if ( isset($log['site']) && !empty($log['site']) ) {
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Site', 'starter-sites');?></td>
			</tr>
			<?php
			if ( isset($log['site']['demo_title']) && isset($log['site']['title_result']) ) {
				?>
				<tr>
					<td class="row-type"><?php esc_html_e('Site Title', 'starter-sites');?></td>
					<td><?php echo esc_html( $log['site']['demo_title'] );?></td>
					<td colspan="3"><?php echo wp_kses( $this->output_log_result( $log['site']['title_result'] ), $this->allowed_html() );?></td>
				</tr>
				<?php
			}
			if ( isset($log['site']['demo_tagline']) && isset($log['site']['tagline_result']) ) {
				?>
				<tr>
					<td class="row-type"><?php esc_html_e('Site Tagline', 'starter-sites');?></td>
					<td><?php echo esc_html( $log['site']['demo_tagline'] );?></td>
					<td colspan="3"><?php echo wp_kses( $this->output_log_result( $log['site']['tagline_result'] ), $this->allowed_html() );?></td>
				</tr>
				<?php
			}
			?>
			<tr class="is-empty">
				<td class="spacer" colspan="5"></td>
			</tr>
			<?php
		}
		if ( isset($log['theme']) && !empty($log['theme']) ) {
			$theme_slug = $log['theme']['slug'];
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Theme', 'starter-sites');?></td>
			</tr>
			<tr>
				<td></td>
				<td><?php echo esc_html( $log['theme']['title'] );?></td>
				<td colspan="3"><?php echo wp_kses( $this->output_log_result( $log['theme']['result'] ), $this->allowed_html() );?></td>
			</tr>
			<tr class="is-empty">
				<td class="spacer" colspan="5"></td>
			</tr>
			<?php
		} else {
			$theme_slug = '';
		}
		if ( isset($log['plugins']) && !empty($log['plugins']) ) {
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Plugins', 'starter-sites');?></td>
			</tr>
			<?php
			foreach ( $log['plugins'] as $plugins_key ) {
				?>
				<tr>
					<td></td>
					<td><?php echo esc_html( $plugins_key['title'] );?></td>
					<td colspan="3"><?php echo wp_kses( $this->output_log_result( $plugins_key['result'] ), $this->allowed_html() );?></td>
				</tr>
				<?php
			}
			?>
			<tr class="is-empty">
				<td class="spacer" colspan="5"></td>
			</tr>
			<?php
		}
		if ( isset($log['design']) && !empty($log['design']) ) {
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Design', 'starter-sites');?></td>
			</tr>
			<?php
			foreach ( $log['design'] as $design_key ) {
				if ( $design_key['post_type'] === 'wp_font_family' || $design_key['post_type'] === 'wp_font_face' ) {
					if ( $design_key['post_type'] === 'wp_font_family' ) {
						?>
						<tr class="post-id-<?php echo esc_attr($design_key['new_id']);?>">
							<td class="row-type"><?php echo esc_html( $this->output_log_type($design_key['post_type']) );?></td>
							<td><?php echo esc_html( $design_key['title'] );?></td>
							<td><?php echo wp_kses( $this->output_log_result( $design_key['result'] ), $this->allowed_html() );?></td>
							<td colspan="2"></td>
						</tr>
						<?php
					}
				} else {
					if ( $design_key['post_type'] === 'wp_global_styles' ) {
						$design_link = "?path=/wp_global_styles&canvas=edit";
					} elseif ( $design_key['post_type'] === 'wp_navigation' ) {
						$design_link = "?postId=" . $design_key['new_id'] . "&postType=wp_navigation&canvas=edit";
					} elseif ( $design_key['post_type'] === 'wp_template' || $design_key['post_type'] === 'wp_template_part' ) {
						$design_link = "?postType=" . $design_key['post_type'] . "&postId=" . $theme_slug . "//" . $design_key['slug'] . "&canvas=edit";
					} elseif ( $design_key['post_type'] === 'wp_block' ) {
						$design_link = "?postId=" . $design_key['new_id'] . "&postType=wp_block&canvas=edit";
					} else {
						$design_link = "";
					}
					?>
					<tr class="post-id-<?php echo esc_attr($design_key['new_id']);?>">
						<td class="row-type"><?php echo esc_html( $this->output_log_type($design_key['post_type']) );?></td>
						<td><?php echo esc_html( $design_key['title'] );?></td>
						<td><?php echo wp_kses( $this->output_log_result( $design_key['result'] ), $this->allowed_html() );?></td>
						<?php
						if ( $design_link === '' ) {
							?>
							<td colspan="2"></td>
							<?php
						} else {
							?>
							<td colspan="2"><a class="text-link" href="<?php echo esc_url( $site_editor_link . $design_link );?>"><?php esc_html_e( 'Edit', 'starter-sites' );?></a></td>
							<?php
						}
					?>
					</tr>
					<?php
				}
			}
			?>
			<tr class="is-empty">
				<td class="spacer" colspan="5"></td>
			</tr>
			<?php
		}
		if ( isset($log['content']) && !empty($log['content']) ) {
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Content', 'starter-sites');?></td>
			</tr>
			<?php
			foreach ( $log['content'] as $content_key ) {
				// when the core (not Gutenberg plugin) site editor supports post_type:post we will add this back in
				//if ( 'page' === $content_key['post_type'] || 'post' === $content_key['post_type'] ) {
				if ( 'page' === $content_key['post_type'] ) {
					$edit_class = ' toggle-edit-link';
					$edit_link = '';
					$edit_extra = '<tr class="edit-link-extra post-id-' . esc_attr($content_key['new_id']) . '">
						<td></td>
						<td colspan="4" style="text-align:right;">' .
							sprintf(
								/* translators: %1$s: Link to post editor, %2$s: Link to site editor, %3$s: Post type, %4$s: closing </a> tag. */
								__( 'Open in %1$s%3$s Editor%4$s or %2$sSite Editor%4$s', 'starter-sites' ),
								'<a class="text-link" href="' . esc_url( $posts_link . '?post=' . $content_key['new_id'] . '&action=edit' ) . '">',
								'<a class="text-link" href="' . esc_url( $site_editor_link . '?postId=' . $content_key['new_id'] . '&postType=' . $content_key['post_type'] . '&canvas=edit' ) . '">',
								$this->output_log_type($content_key['post_type']),
								'</a>'
							)
						. '</td>
					</tr>';
				} elseif ( 'post' === $content_key['post_type'] || 'product' === $content_key['post_type'] ) {
					$edit_class = '';
					$edit_link = ' href="' . esc_url( $posts_link . '?post=' . $content_key['new_id'] . '&action=edit' ) . '"';
					$edit_extra = '';
				} else {
					$edit_class = '';
					$edit_link = '';
					$edit_extra = '';
				}
				?>
				<tr class="post-id-<?php echo esc_attr($content_key['new_id']);?>">
					<td class="row-type"><?php echo esc_html( $this->output_log_type($content_key['post_type']) );?></td>
					<td><?php echo esc_html( $content_key['title'] );?></td>
					<td><?php echo wp_kses( $this->output_log_result( $content_key['result'] ), $this->allowed_html() );?></td>
					<td><a class="text-link<?php echo esc_attr($edit_class);?>"<?php echo $edit_link;?> data-id="<?php echo esc_attr($content_key['new_id']);?>"><?php esc_html_e( 'Edit', 'starter-sites' );?></a></td>
					<td><a class="text-link" href="<?php echo esc_url( get_permalink( $content_key['new_id'] ) );?>"><?php esc_html_e( 'View', 'starter-sites' );?></a></td>
				</tr><?php echo wp_kses_post($edit_extra);
			}
			?>
			<tr class="is-empty">
				<td class="spacer" colspan="5"></td>
			</tr>
			<?php
		}
		if ( isset($log['attachments']) && !empty($log['attachments']) ) {
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Media', 'starter-sites');?></td>
			</tr>
			<?php
			foreach ( $log['attachments'] as $attachment_key ) {
				?>
				<tr>
					<td class="row-type"><?php echo esc_html( $this->output_log_type($attachment_key['post_type']) );?></td>
					<td><?php echo esc_html( $attachment_key['title'] );?><br><span class="filename"><?php echo esc_html( wp_basename(get_attached_file($attachment_key['new_id'])) );?></span></td>
					<td><?php echo wp_kses( $this->output_log_result( $attachment_key['result'] ), $this->allowed_html() );?></td>
					<td><a class="text-link" href="<?php echo esc_url( $posts_link . '?post=' . $attachment_key['new_id'] . '&action=edit' );?>"><?php esc_html_e( 'Edit', 'starter-sites' );?></a></td>
					<td><a class="text-link" href="<?php echo esc_url( get_permalink( $attachment_key['new_id'] ) );?>"><?php esc_html_e( 'View', 'starter-sites' );?></a></td>
				</tr>
				<?php
			}
			?>
			<tr class="is-empty">
				<td class="spacer" colspan="5"></td>
			</tr>
			<?php
		}
		if ( isset($log['terms']) && !empty($log['terms']) ) {
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Terms', 'starter-sites');?></td>
			</tr>
			<?php
			foreach ( $log['terms'] as $term_key ) {
				?>
				<tr>
					<td class="row-type"><?php echo esc_html( $this->output_log_type($term_key['taxonomy']) );?></td>
					<td><?php echo esc_html( $term_key['title'] );?></td>
					<td><?php echo wp_kses( $this->output_log_result( $term_key['result'] ), $this->allowed_html() );?></td>

				<?php if ( is_taxonomy_viewable( $term_key['taxonomy'] ) ) { ?>
					<td><a class="text-link" href="<?php echo esc_url( $terms_link . '?taxonomy=' . $term_key['taxonomy'] . '&tag_ID=' . $term_key['new_id'] );?>"><?php esc_html_e( 'Edit', 'starter-sites' );?></a></td>
					<td><a class="text-link" href="<?php echo esc_url( get_term_link( (int) $term_key['new_id'] ) );?>"><?php esc_html_e( 'View', 'starter-sites' );?></a></td>
				<?php } else { ?>
					<td class="spacer" colspan="2"></td>
				<?php } ?>
				</tr>
				<?php
			}
			?>
			<tr class="is-empty">
				<td class="spacer" colspan="5"></td>
			</tr>
			<?php
		}
		if ( isset($log['other']) && !empty($log['other']) ) {
			?>
			<tr class="section-heading">
				<td colspan="5"><?php esc_html_e('Other', 'starter-sites');?></td>
			</tr>
			<?php
			foreach ( $log['other'] as $other_key ) {
				?>
				<tr>
					<td class="row-type"><?php echo esc_html( $this->output_log_type($other_key['post_type']) );?></td>
					<td><?php echo esc_html( $other_key['title'] );?></td>
					<td colspan="3"><?php echo wp_kses( $this->output_log_result( $other_key['result'] ), $this->allowed_html() );?></td>
				</tr>
				<?php
			}
		}
		?>
		</table>
		<?php
	}

	public function output_log_type( $string ) {
		if ( $string === 'category' ) {
			$output = __( 'Category', 'starter-sites' );
		} elseif ( $string === 'tag' ) {
			$output = __( 'Tag', 'starter-sites' );
		} elseif ( $string === 'product_cat' ) {
			$output = __( 'Product category', 'starter-sites' );
		} elseif ( $string === 'wp_theme' ) {
			$output = __( 'Theme', 'starter-sites' );
		} elseif ( $string === 'wp_template_part_area' ) {
			$output = __( 'Template part area', 'starter-sites' );
		} elseif ( $string === 'page' ) {
			$output = __( 'Page', 'starter-sites' );
		} elseif ( $string === 'post' ) {
			$output = __( 'Post', 'starter-sites' );
		} elseif ( $string === 'product' ) {
			$output = __( 'Product', 'starter-sites' );
		} elseif ( $string === 'product_variation' ) {
			$output = __( 'Product Variation', 'starter-sites' );
		} elseif ( $string === 'attachment' ) {
			$output = __( 'Attachment', 'starter-sites' );
		} elseif ( $string === 'wp_navigation' ) {
			$output = __( 'Navigation', 'starter-sites' );
		} elseif ( $string === 'wp_global_styles' ) {
			$output = __( 'Styles', 'starter-sites' );
		} elseif ( $string === 'wp_template' ) {
			$output = __( 'Template', 'starter-sites' );
		} elseif ( $string === 'wp_template_part' ) {
			$output = __( 'Template part', 'starter-sites' );
		} elseif ( $string === 'wp_block' ) {
			$output = __( 'Pattern', 'starter-sites' );
		} elseif ( $string === 'wp_font_family' ) {
			$output = __( 'Font', 'starter-sites' );
		} else {
			$output = $string;
		}
		return $output;
	}

	public function output_log_result( $string ) {
		if ( $string === 'none' ) {
			$text = __( 'No change', 'starter-sites' );
			$icon = 'minus';
		} elseif ( $string === 'activated' ) {
			$text = __( 'Activated', 'starter-sites' );
			$icon = 'yes';
		} elseif ( $string === 'installed and activated' ) {
			$text = __( 'Installed and activated', 'starter-sites' );
			$icon = 'yes';
		} elseif ( $string === 'not installed' ) {
			$text = __( 'Not installed', 'starter-sites' );
			$icon = 'no-alt';
		} elseif ( $string === 'updated' ) {
			$text = __( 'Updated', 'starter-sites' );
			$icon = 'yes';
		} elseif ( $string === 'added' ) {
			$text = __( 'Added', 'starter-sites' );
			$icon = 'yes';
		} elseif ( $string === 'not added' ) {
			$text = __( 'Not added', 'starter-sites' );
			$icon = 'no-alt';
		} else {
			$text = $string;
			$icon = 'info-outline';
		}
		return '<span class="dashicons dashicons-' . $icon . '"></span> ' . $text;
	}

	public function allowed_html() {
		return [
			'span' => [
				'class' => []
			]
		];
	}

}
