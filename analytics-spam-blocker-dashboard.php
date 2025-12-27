<?php
/* ------------------------------------------------------------------------------------
*  COPYRIGHT NOTICE
*  Copyright 2016-2026 Arnan de Gans. All Rights Reserved.

*  COPYRIGHT NOTICES AND ALL THE COMMENTS SHOULD REMAIN INTACT.
*  By using this code you agree to indemnify Arnan de Gans from any
*  liability that might arise from its use.
------------------------------------------------------------------------------------ */

defined('ABSPATH') or die();

$blocklist = get_option('ajdg_spamblocker_domains');
$remotelist = spamblocker_load_remote_domains();
?>

<form name="analytics-spam-blocker" method="post">
<?php wp_nonce_field('analytics-spam-blocker','spamblocker_nonce'); ?>

<div class="ajdg-box-wrap">
	<div class="ajdg-box-three">

		<div class="ajdg-box">
			<h2 class="ajdg-box-title"><?php _e("Add a Domain", 'analytics-spam-blocker'); ?></h2>
			<div class="ajdg-box-content">

				<p><img src="<?php echo plugins_url('/images/icon-report.png', __FILE__); ?>" class="alignleft report-icon" /><?php _e("Please add any domains that you think are referral spam domains via this form. This is a list of domains you spot yourself. Supplemented to this a new list of known spam domains is downloaded every week and added to your list for the best protection. This list is also used in Analytics tools and is distributed from Github.", 'analytics-spam-blocker'); ?></p>

				<p><label for="spamblocker_report_domain"><strong><?php _e("Referral spam domain:", 'analytics-spam-blocker'); ?></strong><br /><input tabindex="1" name="spamblocker_report_domain" type="text" class="search-input" style="width:100%;" value="" autocomplete="off" /><br /><em><?php _e("One domain at a time. Eg: example.com or abc.example.com.", 'analytics-spam-blocker'); ?></em></label></p>

				<p class="submit">
					<input tabindex="8" type="submit" name="spamblocker_report_submit" class="button-primary" value="<?php _e("Add domain to your custom blocklist", 'analytics-spam-blocker'); ?>" />
				</p>

			</div>
		</div>

		<div class="ajdg-box">
			<h2 class="ajdg-box-title"><?php _e("Stats", 'analytics-spam-blocker'); ?></h2>
			<div class="ajdg-box-content">

				<table width="100%">
					<tr>
						<td width="50%"><strong><?php _e("Domains in your custom blocklist", 'analytics-spam-blocker'); ?><br /><div class="content_large"><?php echo $blocklist['domain_count']; ?></div></strong></td>
						<td><strong><?php _e("Domains in the downloaded blocklist", 'analytics-spam-blocker'); ?><br /><div class="content_large"><?php echo $remotelist['domain_count']; ?></div></strong></td>
					</tr>
					<tr>
						<td width="50%">&nbsp;</td>
						<td><strong><?php _e("Updated:", 'analytics-spam-blocker'); ?></strong> <?php echo wp_date(date('Y-m-d H:i:s', $remotelist['updated'])); ?></td>
					</tr>
				</table>

			</div>
		</div>

		<div class="ajdg-box">
			<h2 class="ajdg-box-title"><?php _e("Spotting referrer spam in your analytics", 'analytics-spam-blocker'); ?></h2>
			<div class="ajdg-box-content">

				<p><?php _e("Referrer spam, also called refferal spam, is usually easily spotted in Google Analytics and Matomo Analytics. Often these domains have stupid names with common words or numbers in them designed to make you curious why anyone would visit you from that site.", 'analytics-spam-blocker'); ?></p>

				<p><?php _e("For example domains such as: 1-best-seo.com or autoseo-expert.com or simple-share-buttons.com. These names are designed to make you lure you to visit the website.", 'analytics-spam-blocker'); ?></p>

				<p><strong><?php _e("Finding referrer spam:", 'analytics-spam-blocker'); ?></strong><br />
				<?php _e("In Matomo navigate to Acquisition -> Websites", 'analytics-spam-blocker'); ?><br />
				<?php _e("In Google Analytics navigate to Acquisition -> All Traffic -> Referrals", 'analytics-spam-blocker'); ?></p>
				<p><?php _e("Most, if not all, analytics platforms have a referral or incoming traffic overview. If this tab refers to which website your visitors came from, then you can expect to find referrer spam there.", 'analytics-spam-blocker'); ?></p>

				<p><strong><?php _e("What happens to blocked domains?", 'analytics-spam-blocker'); ?></strong><br />
    			<?php _e("Analytics Spam Blocker has a simple to use system to block referral spam straight from your dashboard. Enter the domain name click the Add button to add the domain to your custom blocklist. This list is stored in the database for reference, but the real work happens in your .htaccess file. This is a server configuration file that, among other things, can control who or what accesses your website.", 'analytics-spam-blocker'); ?></p>

			</div>
		</div>

	</div>
	<div class="ajdg-box-one">

		<?php include_once(__DIR__.'/analytics-spam-blocker-sidebar.php'); ?>

	</div>
</div>

</form>