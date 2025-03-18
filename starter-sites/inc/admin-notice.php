<?php
/**
 * Add notice action.
 */
function starter_sites_review_notice() {
	$notice_dismissed = get_user_meta( get_current_user_id(), 'starter_sites_review_notice_dismiss', true );
	if ( '1' !== $notice_dismissed ) {
		starter_sites_review_notice_html();
	}
}
add_action( 'admin_notices', 'starter_sites_review_notice' );

/**
 * Dismiss notice.
 */
function starter_sites_review_notice_dismiss() {
	check_ajax_referer( 'wpss-review-nonce', 'wpss-review-nonce-name' );
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( -1 );
	}
	update_user_meta( get_current_user_id(), 'starter_sites_review_notice_dismiss', 1 );
	wp_die( 1 );
}
add_action( 'wp_ajax_starter_sites_review_notice_dismiss', 'starter_sites_review_notice_dismiss' );

/**
 * Render the dismissable admin notice.
 */
function starter_sites_review_notice_html() {
	$screen = get_current_screen();
	if ( $screen->base === 'toplevel_page_starter-sites' || $screen->base === 'appearance_page_starter-sites' || $screen->base === 'tools_page_starter-sites' || $screen->base === 'settings_page_starter-sites' ) {
		return false;
	}
	$time_now = time();
	// default value for where plugin has been continually active before this notice existed in the plugin
	$time_activated = get_option( 'starter_sites_activated', $time_now - 604801 );
	// 604800 = 1 week
	if ( ($time_now - $time_activated) < 604800 ) {
		return false;
	}
	?>
	<div class="notice notice-info is-dismissible starter-sites-review-notice">
		<div class="starter-sites-admin-notice-wrapper">
			<p style="font-size:1.2em;font-weight:600;"><?php echo sprintf(
					/* translators: %s: user's display name. */
					esc_html__( 'Hi %s!', 'starter-sites' ),
					wp_get_current_user()->display_name
				); ?></p>
			<p><?php esc_html_e( 'Enjoying Starter Sites? We’d be super grateful if you could leave a quick review.', 'starter-sites' ); ?> <span style="font-size:1.2em;">★★★★★</span></p>
			<p><?php esc_html_e( 'Your feedback helps us improve the plugin and lets others know what they’re missing.', 'starter-sites' ); ?></p>
			<p><a class="button button-primary" href="https://wordpress.org/support/plugin/starter-sites/reviews/#new-post" target="_blank"><?php esc_html_e( 'Leave a Review', 'starter-sites' ); ?></a></p>
			<p><?php esc_html_e( 'Thanks for your support!', 'starter-sites' ); ?></p>
		</div>
	</div>
	<?php
}
