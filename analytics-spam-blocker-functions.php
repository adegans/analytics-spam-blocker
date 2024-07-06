<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT NOTICE
*  Copyright 2016-2023 Arnan de Gans. All Rights Reserved.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from its use.
------------------------------------------------------------------------------------ */

defined('ABSPATH') or die();

/*-------------------------------------------------------------
 Name:      spamblocker_dashboard_styles
 Purpose:	add dashboard scripts/styles
-------------------------------------------------------------*/
function spamblocker_dashboard_styles() {
	wp_enqueue_style('asb-admin-stylesheet', plugins_url('library/dashboard.css', __FILE__));
}

/*-------------------------------------------------------------
 Name:      spamblocker_activate
 Purpose:	plugin activation routine
-------------------------------------------------------------*/
function spamblocker_activate() {
	// Defaults
	add_option('ajdg_spamblocker_domains', array('updated' => 0, 'domain_count' => 0, 'domains' => array()));
	update_option('ajdg_spamblocker_hide_review', current_time('timestamp'));
	update_option('ajdg_spamblocker_hide_birthday', current_time('timestamp'));

	if(!wp_next_scheduled('spamblocker_get_spam_domains')) wp_schedule_event(time() + 120, 'weekly', 'spamblocker_get_spam_domains');
	spamblocker_get_spam_domains();

	// Set up htaccess
	if((!file_exists(ABSPATH.'.htaccess') AND is_writable(ABSPATH)) OR is_writable(ABSPATH.'.htaccess')) {
		// Edit htaccess
		spamblocker_edit_htaccess();
	} else {
		// Not writable
		wp_die('Analytics Spam Blocker can not add the referral spam rules to your .htaccess file. Make sure it is writable. Contact your hosting provider if you are not sure how to resolve this issue.<br /><a href="'. get_option('siteurl').'/wp-admin/plugins.php">Back to dashboard</a>.');
	}
}

/*-------------------------------------------------------------
 Name:      spamblocker_deactivate
 Purpose:	plugin de-activation routine
-------------------------------------------------------------*/
function spamblocker_deactivate() {
	wp_clear_scheduled_hook('spamblocker_get_spam_domains');
	delete_option('ajdg_spamblocker_domains');
	delete_option('ajdg_spamblocker_hide_review');
	delete_option('ajdg_spamblocker_hide_birthday');

	// Obsolete in version 4.0
	wp_clear_scheduled_hook('ajdg_api_stats_update');
	delete_option('ajdg_spamblocker_updates');
	delete_option('ajdg_activate_analytics-spam-blocker');
	delete_option('ajdg_spamblocker_user');
	delete_option('ajdg_spamblocker_stats');
	delete_option('ajdg_spamblocker_error');
	delete_option('ajdg_spamblocker_subscription');
	delete_option('ajdg_spamblocker_reports');
	delete_option('ajdg_spamblocker_hide_register');
	delete_option('ajdg_spamblocker_version');

	// Delete domain cache
	unlink(WP_CONTENT_DIR.'/spamblocker_remote_domains.data');

	// Clean .htaccess
	if(is_writable(ABSPATH.'.htaccess')) {
		spamblocker_clean_htaccess('# Analytics Spam Blocker - Start', '# Analytics Spam Blocker - End');
	} else {
		// Not writable
		wp_die(_e('Your .htaccess file is not writable!'));
	}
}

/*-------------------------------------------------------------
 Name:      spamblocker_check_config
 Purpose:   Verify or reset settings
-------------------------------------------------------------*/
function spamblocker_check_config() {
	$two_days = current_time('timestamp') + (2 * 86400);

	$domains = get_option('ajdg_spamblocker_domains');
	if(!$domains) update_option('ajdg_spamblocker_domains', array('updated' => 0, 'domain_count' => 0, 'domains' => array()));

	$review = get_option('ajdg_spamblocker_hide_review');
	if(!$review) update_option('ajdg_spamblocker_hide_review', $two_days);

	$birthday = get_option('ajdg_spamblocker_hide_birthday');
	if(!$birthday) update_option('ajdg_spamblocker_hide_birthday', current_time('timestamp'));
}

/*-------------------------------------------------------------
 Name:      spamblocker_report_submit
 Purpose:   Add a domain to your blocklist
-------------------------------------------------------------*/
function spamblocker_report_submit() {
	if(wp_verify_nonce($_POST['spamblocker_nonce_report'], 'spamblocker_nonce_report')) {
		$new_domain = (isset($_POST['spamblocker_report_domain'])) ? $_POST['spamblocker_report_domain'] : "";

		// Cleanup
		$new_domain = esc_url_raw(trim(strtolower($new_domain), "\t\n "));
		$new_domain = parse_url($new_domain);
		$new_domain = $new_domain['host'];

		// Load existing domains
		$custom_domains = get_option('ajdg_spamblocker_domains');	
		$remote_domains = spamblocker_load_remote_domains();

		// Process and response
		if(strlen($new_domain) < 1) {
			spamblocker_return(400); // Incomplete form
		} else if(!preg_match("/^([-a-z0-9]{2,253})\.([a-z\.]{2,63})$/", $new_domain)) {
			spamblocker_return(401); // Invalid domain
		} else if(in_array($new_domain, $custom_domains['domains']) OR in_array($new_domain, $remote_domains['domains'])) {
			spamblocker_return(402); // Already exists
		} else {
			$custom_domains['updated'] = current_time('timestamp');
			$custom_domains['domain_count'] += 1;
			if(!in_array($new_domain, $custom_domains['domains'])) {
				$custom_domains['domains'][] = $new_domain;
			}
			
			update_option('ajdg_spamblocker_domains', $custom_domains);

			spamblocker_edit_htaccess();
			
			spamblocker_return(200);
		}
	} else {
		spamblocker_nonce_error();
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      spamblocker_edit_htaccess
 Purpose:	Add referral spam domains to your .htaccess file
-------------------------------------------------------------*/
function spamblocker_edit_htaccess() {
	if(file_exists(ABSPATH.'.htaccess')){
		$file_content = $new_content = array();

		// Create domain array
		$remote_domains = spamblocker_load_remote_domains();
		$custom_domains = get_option('ajdg_spamblocker_domains', array());
		$rules = array_merge($remote_domains['domains'], $custom_domains['domains']);
		$rules = array_filter(array_unique($rules));

		// Remove current rules
		spamblocker_clean_htaccess('# Analytics Spam Blocker - Start', '# Analytics Spam Blocker - End');

		$new_content[] = "\n\n# Analytics Spam Blocker - Start\n";
		foreach($rules as $rule) {
			$new_content[] = "SetEnvIfNoCase Referer $rule spambot=yes\n";
		}
		$new_content[] = "Order allow,deny\n";
		$new_content[] = "Allow from all\n";
		$new_content[] = "Deny from env=spambot\n";
		$new_content[] = "# Analytics Spam Blocker - End\n";

		// Read current file without rules
		$file_content = file(ABSPATH.'.htaccess');
		$file_content = array_merge($file_content, $new_content);

		// Write new rules
		$fp = fopen(ABSPATH.'.htaccess', 'w');
		foreach($file_content as $line){
			fwrite($fp, "$line");
		}
		fclose($fp);
		unset($standard_domains, $custom_domains, $rules, $fp);
	}
}

/*-------------------------------------------------------------
 Name:      spamblocker_clean_htaccess
 Purpose:	Remove (old) referral spam domains from your .htaccess file
-------------------------------------------------------------*/
function spamblocker_clean_htaccess($start, $end) {
	$file_content = file_get_contents(ABSPATH.'.htaccess');
	$beginning_position = strpos($file_content, $start);
	$ending_position = strpos($file_content, $end);

	if($beginning_position !== false AND $ending_position !== false) {
		$delete_old = substr($file_content, $beginning_position, ($ending_position + strlen($end)) - $beginning_position); // Determine what to delete

		$clean_content = str_replace($delete_old, '', $file_content); // Remove current/old rules
		$clean_content = trim($clean_content, " \t\n\r"); // Remove stray crap at the end of the file

		$fp = fopen(ABSPATH.'.htaccess', 'w');
		fwrite($fp, $clean_content);
		fclose($fp);

		unset($delete_old, $clean_content, $fp);
	}
	unset($file_content);
}

/*-------------------------------------------------------------
 Name:      spamblocker_load_remote_domains
 Purpose:	Load all downloaded domains
-------------------------------------------------------------*/
function spamblocker_load_remote_domains() {
	$domain_file = WP_CONTENT_DIR.'/spamblocker_remote_domains.data';
	
	if(!is_file($domain_file)) {
		// Create file if it doesn't exist
	    $domains = array('updated' => 0, 'domain_count' => 0, 'domains' => array());
	    file_put_contents($domain_file, serialize($domains));
	} else {
		// Read file
		$domains = unserialize(file_get_contents($domain_file));
	}
	
	return $domains;
}

/*-------------------------------------------------------------
 Name:      spamblocker_get_spam_domains
 Purpose:	Download the latest referral spam domains from Github
-------------------------------------------------------------*/
function spamblocker_get_spam_domains() {
	// Load remote domain file, or create it if it doesn't exist
	$current_domains = spamblocker_load_remote_domains();

	// Update check, every week
	if($current_domains['updated'] < current_time('timestamp') - 604800) {
		if(!function_exists('get_plugins')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		$plugin_version = $plugins['analytics-spam-blocker/analytics-spam-blocker.php']['Version'];

		// Get latest blocklist
		$args = array('headers' => array('Accept' => 'multipart/form-data'), 'user-agent' => 'analytics-spam-blocker/'.$plugin_version.';', 'sslverify' => false, 'timeout' => 5);
		$response = wp_remote_get('https://raw.githubusercontent.com/matomo-org/referrer-spam-list/master/spammers.txt', $args);
	
	    if(!is_wp_error($response) AND !empty($response['body'])) {
			$new_domains = array();
			
			// Turn string into an array
			$new_domains['domains'] = explode("\n", trim($response['body']));
			$how_many = count($new_domains['domains']);
			
			if($how_many > 0) {
				$domains['updated'] = current_time('timestamp');
				$domains['domain_count'] = $how_many;
				$domains['domains'] = array_filter(array_unique(array_merge($new_domains['domains'], $current_domains['domains'])));

				// Store the new list
				file_put_contents(WP_CONTENT_DIR.'/spamblocker_remote_domains.data', serialize($domains));
				unset($current_domains, $new_domains, $domains);
			}
		}
	}
}

/*-------------------------------------------------------------
 Name:      spamblocker_notifications_dashboard
 Purpose:	Dashboard notifications
-------------------------------------------------------------*/
function spamblocker_notifications_dashboard() {
	global $current_user;

	if(isset($_GET['hide'])) {
		if($_GET['hide'] == 1) update_option('ajdg_spamblocker_hide_review', 1);
		if($_GET['hide'] == 2) update_option('ajdg_spamblocker_hide_birthday', current_time('timestamp') + (11 * MONTH_IN_SECONDS));
	}

	$displayname = (strlen($current_user->user_firstname) > 0) ? $current_user->user_firstname : $current_user->display_name;

	// Review
	$review_banner = get_option('ajdg_spamblocker_hide_review');
	if($review_banner != 1 AND $review_banner < (current_time('timestamp') - 1214600)) {
		echo '<div class="ajdg-spamblocker-notification notice" style="">';
		echo '	<div class="ajdg-spamblocker-notification-logo" style="background-image: url(\''.plugins_url('/images/notification.png', __FILE__).'\');"><span></span></div>';
		echo '	<div class="ajdg-spamblocker-notification-message">If you like <strong>Analytics Spam Blocker</strong> let everyone know that you do. Thanks for your support!<br /><span>If you have questions, suggestions or something else that doesn\'t belong in a review, please <a href="https://wordpress.org/support/plugin/analytics-spam-blocker/" target="_blank">get in touch</a>!</span></div>';
		echo '	<div class="ajdg-spamblocker-notification-cta">';
		echo '		<a href="https://wordpress.org/support/plugin/analytics-spam-blocker/reviews?rate=5#postform" class="ajdg-notification-act button-primary">Write Review</a>';
		echo '		<a href="tools.php?page=analytics-spam-blocker&hide=1" class="ajdg-notification-dismiss">Maybe later</a>';
		echo '	</div>';
		echo '</div>';
	}

	// Birthday
	$birthday_banner = get_option('ajdg_spamblocker_hide_birthday');
	if($birthday_banner < current_time('timestamp') AND date('M', current_time('timestamp')) == 'Feb') {
		echo '<div class="ajdg-spamblocker-notification notice" style="">';
		echo '	<div class="ajdg-spamblocker-notification-logo" style="background-image: url(\''.plugins_url('/images/birthday.png', __FILE__).'\');"><span></span></div>';
		echo '	<div class="ajdg-spamblocker-notification-message">Hey <strong>'.$displayname.'</strong>! Did you know it is Arnan his birtyday this month? February 9th to be exact. Wish him a happy birthday via Telegram!<br />Who is Arnan? He made Analytics Spam Blocker for you - Check out his <a href="https://www.arnan.me/?mtm_campaign=ajdg_spamblocker&mtm_keyword=birthday_banner" target="_blank">website</a> or <a href="https://www.arnan.me/donate.html?mtm_campaign=ajdg_spamblocker&mtm_keyword=birthday_banner" target="_blank">send a gift</a>.</div>';
		echo '	<div class="ajdg-spamblocker-notification-cta">';
		echo '		<a href="https://t.me/arnandegans" target="_blank" class="ajdg-spamblocker-notification-act button-primary"><i class="icn-tg"></i>Wish Happy Birthday</a>';
		echo '		<a href="tools.php?page=analytics-spam-blocker&hide=2" class="ajdg-spamblocker-notification-dismiss">Done it</a>';
		echo '	</div>';
		echo '</div>';
	}
}

/*-------------------------------------------------------------
 Name:      spamblocker_action_links
 Purpose:	Useful links on the plugin page
-------------------------------------------------------------*/
function spamblocker_action_links($links) {
	$links['ajdg-spamblocker-settings'] = sprintf('<a href="%s">%s</a>', admin_url('tools.php?page=analytics-spam-blocker'), 'Settings');
	$links['ajdg-spamblocker-help'] = sprintf('<a href="%s" target="_blank">%s</a>', 'https://ajdg.solutions/forums/?mtm_campaign=ajdg_spamblocker', 'Support');
	$links['ajdg-spamblocker-more'] = sprintf('<a href="%s" target="_blank">%s</a>', 'https://ajdg.solutions/plugins/?mtm_campaign=ajdg_spamblocker', 'More plugins');

	return $links;
}

/*-------------------------------------------------------------
 Name:      spamblocker_return
 Purpose:	Dashbaord navigation with status messages
-------------------------------------------------------------*/
function spamblocker_return($status, $args = null) {
	if($status > 0 AND $status < 1000) {
		$defaults = array(
			'status' => $status
		);
		$arguments = wp_parse_args($args, $defaults);
		$redirect = 'tools.php?page=analytics-spam-blocker&'.http_build_query($arguments);
	} else {
		$redirect = 'tools.php?page=analytics-spam-blocker';
	}

	wp_redirect($redirect);
}

/*-------------------------------------------------------------
 Name:      spamblocker_status
 Since:		2.0
-------------------------------------------------------------*/
function spamblocker_status($status, $args = null) {
	switch($status) {
		case '200' :
			echo '<div class="ajdg-spamblocker-notification notice" style="">';
			echo '	<div class="ajdg-spamblocker-notification-logo" style="background-image: url(\''.plugins_url('/images/notification.png', __FILE__).'\');"><span></span></div>';
			echo '	<div class="ajdg-spamblocker-notification-message"><strong>'.__('Success!!', 'analytics-spam-blocker').'</strong><br /><span>'.__('The domain has been added to your custom block list.', 'analytics-spam-blocker').'</span></div>';
			echo '</div>';
		break;

		// (all) Error messages
		case '400' :
			echo '<div id="message" class="error"><p>'.__('A referral domain name is required. Please try again.', 'analytics-spam-blocker').'</p></div>';
		break;

		case '401' :
			echo '<div id="message" class="error"><p>'.__('The domain you are trying to add is not valid. Please try again.', 'analytics-spam-blocker').'</p></div>';
		break;

		case '402' :
			echo '<div id="message" class="error"><p>'.__('The domain you are adding is already in the list.', 'analytics-spam-blocker').'</p></div>';
		break;

		default :
			echo '<div id="message" class="error"><p>'.__('Unexpected error', 'analytics-spam-blocker').'</p></div>';
		break;
	}

	unset($args);
}

/*-------------------------------------------------------------
 Name:      spamblocker_nonce_error
 Purpose:   Display a formatted error if Nonce fails
-------------------------------------------------------------*/
function spamblocker_nonce_error() {
	echo '	<h2 style="text-align: center;">'.__('Oh no! Something went wrong!', 'analytics-spam-blocker').'</h2>';
	echo '	<p style="text-align: center;">'.__('WordPress was unable to verify the authenticity of the url you have clicked. Verify if the url used is valid or log in via your browser.', 'analytics-spam-blocker').'</p>';
	echo '	<p style="text-align: center;">'.__('If you have received the url you want to visit via email, you are probably being tricked!', 'analytics-spam-blocker').'</p>';
	echo '	<p style="text-align: center;">'.__('Contact support if the issue persists:', 'analytics-spam-blocker').' <a href="https://wordpress.org/support/plugin/analytics-spam-blocker/" title="AJdG Solutions Support" target="_blank">AJdG Solutions Support</a>.</p>';
}
?>
