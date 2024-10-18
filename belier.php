<?php
/*
Plugin Name:        BELIER
Plugin URI:         https://github.com/AGllardoB/BELIER
Description:        A plugin for handling analytics, access logs and watermarking functionality.
Version:            20241018 - Release Candidate 2
Requires at least:  5.4
Requires PHP:       7.3
Author:             Ana Gallardo
Author URI:         https://liebr.es
License:            GPLv3
*/

/*
===========================================================================
===========================================================================
===========================================================================
						ABOUT THIS SOFTWARE
===========================================================================
===========================================================================
===========================================================================

A non-nonsense WordPress plugin that logs user access, performs viewership
analytics, and watermarks images as they're loaded on a post.

... At least, that was the original intention. It has since grown in scope
and desperately needs a refactor. But things work without much breakage.
It just can be done more elegantly.

BELIER is a bespoke plugin made for a very specific task for a very
specific type of user. It is not meant to be used by the average admin.

If your site requires similar functionality to the one offered with BELIER,
please inquire about licensing a less (or more) bespokeversion of this
plugin. There may be features you may need, or other features you do not.

BELIER depends on the following to perform some of its functions:
    -- ImageMagick and it's PHP interface, Imagick
	-- PHP-FPM
	-- GD Library
    -- WordPress
*/

/*
===========================================================================
===========================================================================
===========================================================================
						BELIER WP PLUGIN MAIN MODULE
						   Release Candidate Two
===========================================================================
===========================================================================
===========================================================================

Welcome to version 20241018!

... As they say. The first 90% of the work takes 10% of the time, the last
10% of the work takes the remaining 90% of the total time.

This would be release 0.9.6 from the original repository. This release does
not bring much to the table. Collab Marker is still in a primigenial soup
state, and the set of functions that should watermark and log things as
they load is not working. All the individual components worked separately
but they aren't working together.

I am now free to continue working on the project in the open, even if the
development still will be tailored for those who asked for this originally

===========================================================================
						   WHAT HAS BEEN IMPLEMENTED
===========================================================================
 -- Nothing, Compared to 0.9.5

===========================================================================
								TO - DO
===========================================================================
 -- Finish the Collab Marker.
 -- Integrate collaborations into the Quarterly Reports.
 -- Finish debugging the plugin
 -- Formally document this plugin.

===========================================================================
					NICE-TO-HAVE'S FOR FUTURE VERSIONS
===========================================================================
 -- Rework all of the SQL. It's a shame to admit that we've just discovered
		prepared SQL sentences this late into the project. This will be the
		first thing to be done as soon as v1.0 is done.

 -- Develop the BELIER Notices functionality to handle admin-facing admins
		and warnings. Design so far leaves very little room for failure,
        and all the potential pain points are being dealt with
        transparently for the administrators. It might, however, be wise
        to move all the dashboard errors to BELIER Notices to show
        respective badges to users. This needs serious thinking

 -- Start Generizating the BELIER software. The recent partial refactors
        are well on the way to make BELIER a much more generic plugin,
        however, there are still hardcoded details pointing to it's
        original and main user. These hard-coded things may need to be put
        in different modules and a config file ( belier_settings? ).
        Unsure if this will ever be used at large, but it doesn't hurt
        to keep things tidy and hope for the best.

 -- Implement a real settings page. Hardcoded settings & editable files
        are good and all if you're the site admin and plugin developer.
        But things change if you intend to help more people out with this
        ... Perhaps for BELIER-NG

 -- Refactor the whole plugin: A lot of work has been done already, but
        the more one changes, the more one realizes that has to change.
        Definitely this is out of scope for version 1.0, as we're already
        pressed for time & there's been a lot of detours to implement
        improvements and upgrades over the last few months. We don't
        think this project will ever be finished before burnout catches
        up to us. But we're close to finishing BELIER as-is.
        BELIER-NG is merely but a dream, and might be worked from
        scratch once this works fully for its intended purpose


/*
===========================================================================
							DEBUGGING FLAGS			
===========================================================================

	Placeholder for future expansion ideas. With BELIER reaching completion
	of it's first technical specification, future features do not need to be
	necessarily available to the end users, but development still needs to
	happen on them, maybe even enabled for QA purposes.

	For this, a simple debugging/retail flag will be created.

	If set to true. Unfinished/debugging features will appear on the menu.
*/

global $debug_flag;
$debug_flag = true;

/*
===========================================================================
							INITIAL FILE SETUP			
===========================================================================

	Load all the sub-modules to ensure they all exist and are present.
	Register the activation hook, add debugging display functionality and
	check the user is an administrator beforehand.
*/
require('belier_hue_offsets.php');
require('belier_analytics.php');
require('belier_access_logs.php');
require('belier_data_acquisition.php');
require('belier_image_processing.php');
require('belier_headers.php');
if ($debug_flag == true) {
    require('belier_collabs.php');
    require('belier_image_prev_test.php');
    require('belier_notices.php');
	// Enable maximum debugging.
	// error_reporting(E_ALL);
	// ini_set("display_errors", 1);
}

register_activation_hook(__FILE__, 'belier_install');




/*
===========================================================================
						THE INSTALL FUNCTION			
===========================================================================

	Upom first activation, this function will create the specific BELIER-Uploads
	directory, and set up our work table in the current WP Database.

	Currently, there is no uninstall function, as I deem it valuable to keep
	the tables intact in the event of a change or removal of our plugin.
*/

function belier_install(): void
{
// Check for user permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    /*
        Create folder to host temporary file uploads for the hue offsets comparator
        as well to handle image processing, if needed.
    */

    $uploads_dir = trailingslashit(wp_upload_dir()['basedir']) . 'belier-uploads';
    $temp_dir = trailingslashit(wp_upload_dir()['basedir']) . 'belier-tmp';
    // Make the temporary files folder only if it doesn't exist
    if (is_dir($uploads_dir) == false) {
        wp_mkdir_p($uploads_dir);
    }
    if (is_dir($temp_dir) == false) {
        wp_mkdir_p($temp_dir);
    }

    /*
        Create the hue offsets table. This first table is our first line of defense
        where we tie a specific RGB vector, to an unique combination of username
        and images. That way one can check what values have been assigned to all
        the images viewed to a specific user, and so on.
    */

    global $wpdb;
    //global $hue_table;
    $hue_table = $wpdb->prefix . "belier_hue_offsets";
    $init_hue_offsets_table = "CREATE TABLE IF NOT EXISTS $hue_table (
		username VARCHAR(128) NOT NULL,
		post_image VARCHAR(256) NOT NULL,
		r INT NOT NULL,
		g INT NOT NULL,
		b INT NOT NULL,
		PRIMARY KEY (post_image, username)
	);";

    /*
        Create the hue post analytics table. This will log the relationship
        between users, locations, and images viewed. It will also hold the
        post ID and the author of said image. This will help making the
        viewership analytics a lot more straightforward. (see which images
        or which users have the most views on any given pay period).
    */

    //global $analytics_table;
    $analytics_table = $wpdb->prefix . "belier_post_analytics";
    /*
    $init_analytics_table = "CREATE TABLE IF NOT EXISTS $analytics_table (
		date_viewed DATETIME NOT NULL,
		ip_address VARCHAR(256) NOT NULL,
		username VARCHAR(128) NOT NULL,
		post_image VARCHAR(256) NOT NULL,
		post_id BIGINT UNSIGNED NOT NULL,
		post_year SMALLINT UNSIGNED NOT NULL,
		author VARCHAR(128) NOT NULL,
		collabs VARCHAR(224),
		PRIMARY KEY (date_viewed, ip_address, username)
	);";
    */
    $init_analytics_table = "CREATE TABLE IF NOT EXISTS $analytics_table (
		date_viewed DATETIME NOT NULL,
		ip_address VARCHAR(256) NOT NULL,
		username VARCHAR(128) NOT NULL,
		post_image VARCHAR(512) NOT NULL,
		post_year SMALLINT UNSIGNED NOT NULL,
		author VARCHAR(128) NOT NULL,
		collabs VARCHAR(224),
		PRIMARY KEY (date_viewed, ip_address, username)
	);";

    // Fulfills the requirement to allow us to use dbDelta() function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Run our SQL syntax to create our tables on disk.

    dbDelta($init_hue_offsets_table);
    dbDelta($init_analytics_table);
}

/*
===========================================================================
						PLUGIN CONTROL FLOW
===========================================================================

	How are we handling the plugin at each stage of page load.
	In order, following the WP Hook firing sequence, it will.
		1: Initiate the GC and try and clear up old PHP sesson data.
		2: Send http headers asking please not be cached anywhere.
		4: If we're between the thirtieth and fiftyninth second on a minute. erase the temp folder.
		4: Ensure logged out users can't see beyond a few select pages.
			4b: If the users are logged in, give full access & record their viewership data.
			4c: If the logged-in user is an admin. Display a debug view of collected data.
		5: Provide the BELIER dashboard menu when wp-admin is loaded.
*/

// Clean up after ourselves when our session is over.
// belier_maintenance.php  -> garbage_collection_cleanup
add_action('init', 'garbage_collection_cleanup', 1);

// belier_headers.php -> belier_generic_headers
// sends headers to ensure it cannot be cached
// add_action('init', 'belier_generic_headers', 3);

// Nuke shit ASAP
// belier_maintenance.php -> belier_temp_file_cleanup
add_action('wp', 'belier_temp_file_cleanup', 5);

/*
	Tasks to perform before the post is displayed to the user.
ONE
	belier_maintenance.php -> belier_check_users
	Check for the validity of the current user. IF it doesn't work. Redirect to Preview.
	This user redirect function should not be on BELIER. this should be it's own plugin.
*/
add_action('wp', 'belier_check_users');
/*
TWO
	data_acquisition.php -> belier_get_data
	Commented out for the time being since data is going to be logged on image-load, not
	during page-load. may incur on lost data, but it also work
*/
add_action( 'wp', 'belier_get_data', 200 );
// Generate our admin menu whenever its loaded.
add_action('admin_menu', 'belier_menu');


/*
===========================================================================
						DASHBOARD MENU INTEGRATION			
===========================================================================

	Pretty mich what it says on the tin. It adds menu items to the dashboard
	each representing one of the four main user-accesible functions of BELIER

	- Hue Offsets: Checks the RGB offsets for any given uploaded image and compares
			them with the corresponding post image.

	- Post Analytics: Diplays a breakdown of which artists have been view the
			most on any pay period, and shows another list of which images have been
			the most viewed for said period.

	- Access Logs: Displays an unfiltered list spanning the last 30 days, by default,
			of every image viewed by any user accessing the site, as well as their IP.

	==========
	DEBUG MODE
	==========

		- Collab Marker: For those few images or stories where more than one person has
				contributed to. It allows to add their names to the 'authors' column
				on the analytics table. Done to prevent making obscenely complicated code
				to fetch on the analytics, since WP doesn't allow collabs natively.
		
		- Image Preview: The testing ground for our image manipulation routines down the line.

		- Notices pane: For testing error notices. Maybe for a more robust version or on BELIER-NG
*/
function belier_menu(): void
{
    global $debug_flag;
    add_menu_page('BELIER Menu', 'BELIER Menu', 'publish_posts', 'belier_menu', 'false', 'dashicons-carrot', 3);
    add_submenu_page('belier_menu', 'BELIER Post Analytics', 'BELIER Post Analytics', 'publish_posts', 'belier_analytics.php', 'belier_analytics');
    add_submenu_page('belier_menu', 'BELIER Hue Offsets', 'BELIER Hue Offsets', 'manage_options', 'belier_hue_offsets.php', 'belier_hues');
    add_submenu_page('belier_menu', 'BELIER Access Logs', 'BELIER Access Logs', 'manage_options', 'belier_access_logs.php', 'belier_access_logs');
    if ($debug_flag == true) {
        add_submenu_page('belier_menu', 'BELIER Collab Marker (Beta)', 'BELIER Collab Marker (Beta)', 'manage_options', 'belier_collabs.php', 'belier_collabs');
        add_submenu_page('belier_menu', 'BELIER Img Preview', 'BELIER Image Preview (Debug)', 'manage_options', 'belier_image_prev_test.php', 'belier_image_preview');
        add_submenu_page('belier_menu', 'BELIER Notices', 'BELIER Notices (Debug)', 'manage_options', 'belier_notices.php', 'belier_notices');
    }
    /*
        Hack to add a menu page without having "Belier Menu" repeating itself twice. I don't quite know
        how this works, but it does. I think the first parameter was to remove the item in the menu,
        followed by removing the associated slug...
        12/08/2023: PLEASE LOOK AT THIS AND UPDATE THE COMMENT.
        13/12/2023: No clue as of yet of why or how this works, but it does
    */
    remove_submenu_page('belier_menu', 'belier_menu');
}

?>