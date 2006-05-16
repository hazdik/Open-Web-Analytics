<?php 

/*
Plugin Name: Open Web Analytics
Plugin URI: http://www.openwebanalytics
Description: This plugin enables Wordpress blog owners to use the Open Web Analytics Framework.
Author: Peter Adams
Version: v1.0
Author URI: http://www.openwebanalytics.com
*/

require_once 'owa_env.php';
require_once 'owa_settings_class.php';
require_once 'owa_wp.php';

/**
 * WORDPRESS Constants
 * You should not need to change these.
 */

// URL special requests can be intercepted on
define ('OWA_BASE_URL', get_bloginfo('url').'/index.php');

// URL used for graph generation requests
define ('OWA_GRAPH_URL', OWA_BASE_URL);

// URL stem used for inter report navigation
define ('OWA_REPORTING_URL', $_SERVER['PHP_SELF'].'?page=owa/reports');

// Path to images used in reports
define ('OWA_IMAGES_PATH', '../wp-content/plugins/owa/reports/i/');

// Check to see what version of wordpress is running
$owa_wp_version = owa_parse_version($wp_version);

// check to see if OWA is installed
$current_plugins = get_option('active_plugins');

// Needed to avoid a fetch of configuration from db during installation
if (($_GET['action'] == 'activate') && ($_GET['plugin'] == 'owa/wp_plugin.php')):
	$owa_config['fetch_config_from_db'] = false;
endif;


// Caller Configuration overides
$owa_config['report_wrapper'] = 'wordpress.tpl';
$owa_config['db_name'] = DB_NAME;     // The name of the database
$owa_config['db_user'] = DB_USER;     // Your db username
$owa_config['db_password'] = DB_PASSWORD; // ...and password
$owa_config['db_host'] = DB_HOST;     // The host of your db
$owa_config['db_type'] = 'wordpress';     // The host of your db

// Create new instance of caller class object
$owa_wp = new owa_wp($owa_config);
// WORDPRESS Filter and action hook assignment


if ($owa_wp_version[0] == '1'):

	if (isset($_GET['activate']) && $_GET['activate'] == 'true'):

	add_action('init', 'owa_install_1');
  
	endif;

elseif ($owa_wp_version[0] == '2'):
	add_action('activate_owa/wp_plugin.php', 'owa_install_2');

endif;


add_action('template_redirect', 'owa_main');
add_action('wp_footer', array(&$owa_wp, 'add_tag'));
add_filter('post_link', 'owa_post_link');
add_filter('bloginfo', 'add_feed_sid');
add_action('admin_menu', 'owa_dashboard_view');
add_action('init', array(&$owa_wp, 'init_action'));
add_action('comment_post', array(&$owa_wp, 'process_comment'));
add_action('admin_menu', 'owa_options');

////////// FORM HANDLERS

//if (is_plugin_page()):
		if (isset($_POST['wa_update_options'])):
						
			$owa_wp->save_config($_POST);
		endif;
		
		if (isset($_POST['wa_reset_options'])):
		
			$owa_wp->reset_config();	
			
		endif;
	//endif;


	
/**
 * This is the main logger function that calls wa on each normal web request.
 * Application specific request data should be set here. as part of the $app_params array.
 */
function owa_main() {
	
	// WORDPRESS SPECIFIC DATA //
	
	//Load config from wp_database
	//owa_fetch_config();
	
	// Get the type of page
	$app_params['page_type'] = owa_get_page_type();
	
	//Check to see if this is a Feed Reeder
	if(is_feed()):
		$app_params['is_feedreader'] = true;
	endif;
	
	// Track users by the email address of that they used when posting a comment
	$app_params['user_email'] = $_COOKIE['comment_author_email_'.COOKIEHASH]; 
	
	// Track users who have a named account
	$app_params['user_name'] = $_COOKIE['wordpressuser_'.COOKIEHASH];
	
	// Get Title of Page
	$app_params['page_title'] = owa_get_title($app_params['page_type']);
	
	// Get Feed Tracking Code
	//$app_params['feed_subscription_id'] = ''
	
	// Get Source Tracking code
	//$app_params['source'] = '';
	
	// Provide an ID for this instance in case you want to track multiple blogs/sites seperately
	//$app_params['site_id'] = '';
	
	// Process the request by calling owa
	$owa_wp = & new owa_wp;
	$owa_wp->process_request($app_params);
	return;
}

/**
 * Determines the title of the page being requested
 *
 * @param string $page_type
 * @return string $title
 */
function owa_get_title($page_type) {

	if ($page_type == "Home"):
		$title = get_bloginfo('name');
	elseif ($page_type == "Search Results"):
		$title = "Search Results for \"".$_GET['s']."\"";	
	elseif ($page_type == "Page" || "Post"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Author"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Category"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Month"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Day"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Year"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Time"):
		$title = wp_title($sep = '', $display = 0);
	elseif ($page_type == "Feed"):
		$title = wp_title($sep = '', $display = 0);
	endif;	
	
	return $title;
}

/**
 * Determines the type of page being requested
 *
 * @return string $type
 */
function owa_get_page_type() {	
	
	if (is_home()):
		$type = "Home";
	elseif (is_single()):
		$type = "Post";
	elseif (is_page()):
		$type = "Page";
	elseif (is_author()):
		$type = "Author";
	elseif (is_category()):
		$type = "Category";
	elseif (is_search()):
		$type = "Search Results";
	elseif (is_month()):
		$type = "Month";
	elseif (is_day()):
		$type = "Day";
	elseif (is_year()):
		$type = "Year";
	elseif (is_time()):
		$type = "Time";
	elseif (is_archive()):
		$type = "Archive";
	elseif (is_feed()):
		$type = "Feed";
	endif;
	
	return $type;
}

/**
 * Adds a GUID to the feed URL.
 *
 * @param array $binfo
 * @return string $newbinfo
 */
function add_feed_sid($binfo) {

	global $doing_rss;
	
	if (strstr($binfo, "feed=")):

		$owa_wp = &new owa_wp;
		$newbinfo = $owa_wp->add_feed_tracking($binfo);
		
	else: 
	
		$newbinfo = $binfo;
	
	endif;
	
	return $newbinfo;

}

/**
 * Adds tracking source param to links in feeds
 *
 * @param string $link
 * @return string
 */
function owa_post_link($link) {

	global $doing_rss;
	if($doing_rss):
	
		$owa_wp = new owa_wp;
		$tracked_link = $owa_wp->add_link_tracking($link);
		return $tracked_link;
	else:
		return $link;
	endif;
	

}

/**
 * Schema and setting installation
 *
 */
function owa_install_1() {

	global $user_level;
	
	//check to see if the user has permissions to install or not...
	get_currentuserinfo();
	
	if ($user_level < 8):
   
    	return;
    else:
    	$owa_wp = &new owa_wp;
    	$owa_wp->install('mysql');
	endif;

	return;
}

/**
 * Schema and setting installation
 *
 */
function owa_install_2() {

		$conf = &owa_settings::get_settings();
		$conf['fetch_config_from_db'] = false;
    	$owa_wp = &new owa_wp;
    	$owa_wp->install('mysql');

	return;
}

/**
 * Adds Analytics sub tab to admin dashboard screens.
 *
 */
function owa_dashboard_view() {

	if (function_exists('add_submenu_page')):
		add_submenu_page('index.php', 'OWA Dashboard', 'Analytics', 8, dirname(__FILE__) . '/reports/dashboard_report.php');
    endif;
    
    return;

}

/**
 * Adds Options page to admin interface
 *
 */
function owa_options() {
	
	if (function_exists('add_options_page')):
		add_options_page('Options', 'OWA', 8, basename(__FILE__), 'owa_options_page');
	endif;
    
    return;
}

/**
 * Generates Options Management Page
 *
 */
function owa_options_page() {
	
	$owa_wp = &new owa_wp;
	$owa_wp->options_page();
	
	return;
}

/**
 * Parses string to get the major and minor version of the 
 * instance of wordpress that is running
 *
 * @param string $version
 * @return array
 */
function owa_parse_version($version) {
	
	$version_array = explode(".", $version);
   
   return $version_array;
	
}

?>
