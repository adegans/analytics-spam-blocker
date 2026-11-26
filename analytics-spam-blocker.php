<?php
/*
Plugin Name: Analytics Spam Blocker
Plugin URI: https://ajdg.solutions/product/analytics-spam-blocker/
Author: Arnan de Gans
Author URI: https://www.arnan.me/
Description: Stop referrer spam from affecting your website analytics. Easily create a local blocklist and report new domains to stay on top of the issue.
Version: 4.2.0.1
License: GPLv3

Text Domain: analytics-spam-blocker
Domain Path: /languages

Requires at least: 5.8
Requires PHP: 8.0
Requires CP: 2.0
Tested CP: 2.7.4
Premium URI: https://ajdg.solutions/
GooseUp: compatible
*/

/* ------------------------------------------------------------------------------------
*  COPYRIGHT NOTICE
*  Copyright 2016-2026 Arnan de Gans. All Rights Reserved.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from its use.
------------------------------------------------------------------------------------ */

defined('ABSPATH') or die();

/*--- Load Files --------------------------------------------*/
include_once(plugin_dir_path(__FILE__).'/library/common.php');
include_once(plugin_dir_path(__FILE__).'/analytics-spam-blocker-functions.php');
/*-----------------------------------------------------------*/

/*--- Core --------------------------------------------------*/
register_activation_hook(__FILE__, 'spamblocker_activate');
register_uninstall_hook(__FILE__, 'spamblocker_deactivate');
add_action('init', 'spamblocker_init');

if(is_admin()) {
	spamblocker_check_config();
	/*--- Dashboard ---------------------------------------------*/
	add_action('admin_menu', 'spamblocker_dashboard_menu');
	add_action("admin_print_styles", 'spamblocker_dashboard_styles');
	add_filter('plugin_row_meta', 'spamblocker_meta_links', 10, 2);
	/*--- Actions -----------------------------------------------*/
	if(isset($_POST['spamblocker_report_submit'])) add_action('init', 'spamblocker_report_submit');
}
/*-----------------------------------------------------------*/

/*-------------------------------------------------------------
 Name:      spamblocker_dashboard_menu
 Purpose:   Add pages to admin menus
-------------------------------------------------------------*/
function spamblocker_dashboard_menu() {
	add_management_page(__('Analytics Spam Blocker', 'analytics-spam-blocker'), __('Analytics Spam Blocker', 'analytics-spam-blocker'), 'manage_options', 'analytics-spam-blocker', 'spamblocker_dashboard');
}

/*-------------------------------------------------------------
 Name:      spamblocker_info
 Purpose:   Admin general info page
-------------------------------------------------------------*/
function spamblocker_dashboard() {
	$status = $action = '';
	$status = (isset($_GET['status'])) ? esc_attr($_GET['status']) : '';
	$action = (isset($_GET['action'])) ? esc_attr($_GET['action']) : '';

	$current_user = wp_get_current_user();
	?>

	<div class="wrap">
		<h1><?php _e('Analytics Spam Blocker', 'analytics-spam-blocker'); ?></h1>

		<?php
		if($status > 0) spamblocker_status($status);
		include("analytics-spam-blocker-dashboard.php");
		?>

		<br class="clear" />
	</div>
<?php
}
