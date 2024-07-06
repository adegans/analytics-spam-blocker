<?php
/*
Plugin Name: Analytics Spam Blocker
Plugin URI: https://ajdg.solutions/product/analytics-spam-blocker/?mtm_campaign=ajdg_spamblocker
Author: Arnan de Gans
Author URI: https://www.arnan.me/?mtm_campaign=ajdg_spamblocker
Description: Stop referrer spam from affecting your website analytics. Easily create a local blocklist and report new domains to stay on top of the issue.
Text Domain: analytics-spam-blocker
Domain Path: /languages/
Version: 4.0
License: GPLv3
*/

/* ------------------------------------------------------------------------------------
*  COPYRIGHT NOTICE
*  Copyright 2016-2024 Arnan de Gans. All Rights Reserved.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from its use.
------------------------------------------------------------------------------------ */

defined('ABSPATH') or die();

/*--- Load Files --------------------------------------------*/
$plugin_folder = plugin_dir_path(__FILE__);
require_once($plugin_folder.'/analytics-spam-blocker-functions.php');
/*-----------------------------------------------------------*/

/*--- Core --------------------------------------------------*/
register_activation_hook(__FILE__, 'spamblocker_activate');
register_deactivation_hook(__FILE__, 'spamblocker_deactivate');
load_plugin_textdomain('analytics-spam-blocker', false, 'analytics-spam-blocker/language');
/*-----------------------------------------------------------*/

/*--- Back end ----------------------------------------------*/
if(is_admin()) {
	spamblocker_check_config();
	/*--- Dashboard ---------------------------------------------*/
	add_action('admin_menu', 'spamblocker_dashboard_menu');
	add_action("admin_print_styles", 'spamblocker_dashboard_styles');
	add_action('admin_notices', 'spamblocker_notifications_dashboard');
	add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), 'spamblocker_action_links');
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
