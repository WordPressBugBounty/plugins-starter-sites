jQuery(document).ready( function($) {

	if ( $('.success-site-title').length ) {
		var siteTitle = $('.success-site-title').text();
		if ( 40 < siteTitle.length ) {
			siteTitle = siteTitle.substring( 0, 40 ) + '\u2026';
		}
		$( '#wp-admin-bar-site-name' ).children( 'a' ).first().text( siteTitle );
	}

	// Toggle & update screen options
	$('button.screen-option').on('click', function() {
		var setting = $(this).data('id');
		if (setting == 'fullpage' || setting == 'darkmode') {
			$('body').toggleClass('starter-sites-' + setting);
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'starter_sites_update_screen_prefs',
					'setting': setting,
					'starter-sites-options-nonce-name': starter_sites_screen_settings.options_update_nonce
				}
			});
		}
	});

	$('.button.activate-site').on('click', function() {
		$('.starter-sites-main').addClass('activating');
	});

	$('.button.upload-site').on('click', function() {
		$('.starter-sites-main').addClass('activating');
	});

	// Demo modals
	const demoModal = $('.starter-sites-demo-modal');
	const openClasses = $('.starter-sites-demos-grid, #wpfooter');
	$( '.starter-sites-demos-grid .starter-sites-demo' ).each( function() {
		var thisDemoID = $(this).data('demo-id');
		// Open modal
		$('button.modal-open', this).on('click', function() {
			var headerHeight = $('#wpadminbar').outerHeight();
			var toTop = $(window).scrollTop();
			if ( toTop > headerHeight ) {
				toTop = toTop - headerHeight;
			}
			if ( toTop === 0 ) {
				toTop = '0px';
			}
			var modalClass = '.starter-sites-demo-modal.demo-modal-id-' + thisDemoID;
			demoModal.removeClass('is-open');
			openClasses.removeClass('has-open-modal');
			$(modalClass).addClass('is-open');
			demoModal.css({'top': toTop});
			openClasses.addClass('has-open-modal');
		});
	});

	// Close all modals
	$('button.modal-close', demoModal).on('click', function() {
		demoModal.removeClass('is-open');
		demoModal.css({'top': 'auto'});
		openClasses.removeClass('has-open-modal');
	});

	// Prev & next buttons
	demoModal.each( function() {
		var thisDemoID = $(this).data('demo-modal-id');
		var prevDemoID = $(this).prev().data('demo-modal-id');
		var nextDemoID = $(this).next().data('demo-modal-id');
		if (prevDemoID == null) {
			prevDemoID = demoModal.last().data('demo-modal-id');
		}
		if (nextDemoID == null) {
			nextDemoID = demoModal.first().data('demo-modal-id');
		}
		$('button.modal-previous', this).on('click', function() {
			var modalClass = '.starter-sites-demo-modal.demo-modal-id-' + thisDemoID;
			$(modalClass).removeClass('is-open');
			var prevModalClass = '.starter-sites-demo-modal.demo-modal-id-' + prevDemoID;
			$(prevModalClass).addClass('is-open');
		});
		$('button.modal-next', this).on('click', function() {
			var modalClass = '.starter-sites-demo-modal.demo-modal-id-' + thisDemoID;
			$(modalClass).removeClass('is-open');
			var nextModalClass = '.starter-sites-demo-modal.demo-modal-id-' + nextDemoID;
			$(nextModalClass).addClass('is-open');
		});
		$('button.toggle-options', this).on('click', function() {
			var modalClass = '.starter-sites-demo-modal.demo-modal-id-' + thisDemoID + ' .starter-sites-demo-options';
			$(modalClass).toggleClass('is-open');
		});
	});

	// Open post editor & site editor links
	$('.toggle-edit-link').each( function() {
		$(this).on('click', function() {
			var thisPostID = $(this).data('id');
			var pageClass = 'tr.post-id-' + thisPostID;
			$(pageClass).toggleClass('is-open');
		});
	});

});
