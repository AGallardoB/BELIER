<?php
/* 
===========================================================================
===========================================================================
===========================================================================
					SITE-WIDE DATA ACQUISITION MODULE
						  (Release Candidate 1)
===========================================================================
===========================================================================
===========================================================================

Interim BELIER-NG Rewrite of the Data Acquisition module.

NEW: FUNKY MODE! ( Should be invoked from everywhere )

===========================================================================
					 			TO-DO
===========================================================================

- Rewrite it all again

- Write sane commentary.

- Finish integration with image_procesing (On the way, as part of send_headers)

- Finish integration with collab_marker (It is now aware that collabs exist
        and will attempt to programatically add the relevant data to the DB.
        Needs testing since the collab-related modules aren't complete yet)

*/

/*
===========================================================================
						DATA GATHERING FUNCTION		
===========================================================================

    This function gathers the data. It should now work regardless
    of whichever hook it depends on. This is needed so it can be called by the
    image_processing shim to be placed in /wp-content/uploads. This will also
    work if called directly through the WP hook.
*/
// Enable maximum debugging.
// error_reporting(E_ALL);
// ini_set("display_errors", 1);
require('belier_maintenance.php');
if (!defined('ABSPATH')) {
    // Load the WP env if that is not the case.
    require_once('./wp-load.php');
}
function belier_get_data($shim_filename = '')
{
    // This was working earlier, but i may have gotten a bit aggressive with inheriting
    // Loaded variables... let's see if this works.
    $post = get_post();
    // If this still fails, belier_data_sanity_checks will kill WP
    // Get the Post ID
    $post_id = get_the_ID();
    // Check if we're inside the WordPress Main Loop by checking the existence
    // of the "ABSPATH" constant.
    if (!defined('ABSPATH')) {
        // Load the WP env if that is not the case.
        require_once('./wp-load.php');
    }
    // Ensure Maintenance is loaded to use belier_is_set
    if (!function_exists('belier_is_set')) {
        include('belier_maintenance.php');
        require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_maintenance.php');
    }
    // Acquire the current date.
    $date_viewed = date('Y-m-d H:i');
    // Get visitor's IP
    // $ip_address=$_SERVER['REMOTE_ADDR'];
    // Get the current logged-in user
    $username = wp_get_current_user()->user_login;
    // Get the post_image, first by checking the filename given by our shim
    // if ((isset($shim_filename) || (strlen(trim($shim_filename)) > 8))) {
    if (belier_is_set($shim_filename,'chars') >= 6) {
        $post_image = (explode("uploads/", $shim_filename));
        unset($shim_filename);
    } else {
        //If that fails, check the post metadata for the image in question.
        $post_image = (explode("uploads/", get_post_meta($post_id)['post_image'][0])[1]);
        // If that still fails, and in the event this is a direct-image load
        // Get the post image if we're getting the image directly through a request.
        //if((str_contains($_SERVER['REQUEST_URI'], array ('.png', '.jpeg', '.jpg', '.gif')))){
        // slower but the probably correct way.
        if (belier_is_set($post_image) === false){
            if (belier_is_set($_SERVER['REQUEST_URI'], 'chars') >= 6) {
                $filetypes = array('jpg', 'jpeg', 'gif', 'png');
                foreach ($filetypes as $filetype) {
                    if (str_contains($post_image, $filetype)) {
                        $post_image = (explode("uploads/", $_SERVER['REQUEST_URI']));
                    }
                }
            }
        }
    }
    // Extract year from post image
    // $post_year = intval((explode("/", $post_image)[0]));
    // Get the author.
    $author = get_the_author_meta('user_nicename', $post->post_author);
    // Make the array with the data we got so far
    $data_collected = array(
        'date_viewed' => $date_viewed,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'username' => $username,
        'post_image' => $post_image,
        //We aren't inserting the post ID anymore but it may prove useful for recovery
        'post_id' => $post_id,
        //'post_year' => $post_year,
        'author' => $author,
        'collabs' => '',
        'red' => '',
        'green' => '',
        'blue' => '',
    );
    // A few... Somewhat horrifying ways to add the rest of the data and sanity-check it.
    $fixed_data_collected = belier_data_sanity_checks($data_collected);
    // update array with the fixes
    $data_collected = $fixed_data_collected;
    // If WP has not died, we're still missing the tint data, which will be handled by
    // the database handler, which will also insert the data.
    $data_collected_with_rgb = belier_img_db_handler($data_collected);
    // update array with the RGB data
    $data_collected = $data_collected_with_rgb;
    // unset these temp variables
    unset ($fixed_data_collected);
    unset ($data_collected_with_rgb);
    return $data_collected;
}

/*
===========================================================================
				    IMAGE DATABASE HANDLER FUNCTION		
===========================================================================
    As the title suggest, this handles all of the tasks related with the
    database. From probing if the image has tints associated with it, to
    inserting the current view onto the table.
	
*/
function belier_img_db_handler(&$data_collected)
{
    $probe_user = wp_get_current_user();
    if ($probe_user->roles[0] != 'subscriber') {
        //abort if the user isn't a subscriber and don't write to the DB
        return('not a subscriber');
    }
    // Import the wpdb handler and check for offsets
    global $wpdb;
    // Specify he table on which we will work on.
    $offsets_table = "{$wpdb->prefix}belier_hue_offsets";
    // Check for the existence of tints for this username - image combo.
    $sql = "SELECT r,g,b FROM $offsets_table WHERE username = '" . $data_collected['username'] . "' AND post_image = '" . $data_collected['post_image'] . "' LIMIT 1;";
    //Store our tints in an object.
    $query_results = $wpdb->get_results($sql);
    // Copy signed values into variables if our query is not empty.
    if ((!empty($query_results)) && (count($query_results) > 0)) {
        // The resulting object from get_results is an 1 dimension-array with objects inside named after the columns on the table.
        $data_collected['red'] = $query_results[0]->r;
        $data_collected['green'] = $query_results[0]->g;
        $data_collected['blue'] = $query_results[0]->b;
    }
    // If the query was empty, generate random values, add them to our array
    // and add them to the database while we're at it.
    else {
        $red = rand(-8, 8);
        if ($red == -0) {
            $red = 0;
        }
        $data_collected['red'] = $red;
        $green = rand(-8, 8);
        if ($green == -0) {
            $green = 0;
        }
        $data_collected['green'] = $green;
        $blue = rand(-8, 8);
        if ($blue == -0) {
            $blue = 0;
        }
        $data_collected['blue'] = $blue;
        $wpdb->insert(
            $offsets_table, array(
                'username' => $data_collected['username'],
                'post_image' => $data_collected['post_image'],
                'r' => $red,
                'g' => $green,
                'b' => $blue,
            )
        );
    }
    /*
        Insert the current visualization to the database.
        Or print on screen what would be inserted otherwise.
    */
    // If an admin is watching this, Print what would be inserted into the database.
    if (current_user_can('manage_options')) {
        echo "<pre> <p>debug array</p> <br>";
        print_r($data_collected);
        echo "</pre>";
    } // If a regular user is viewing this Insert our data into the database
    else {
        $analytics_table = "{$wpdb->prefix}belier_post_analytics";
        $wpdb->insert(
            $analytics_table, array(
                'date_viewed'   => $data_collected['date_viewed'],
                'ip_address'    => $data_collected['ip_address'],
                'username'      => $data_collected['username'],
                'post_image'    => $data_collected['post_image'],
                //'post_id'     => $data_collected['post_id'],
                //'post_year'   => $data_collected['post_year'],
                'author'        => $data_collected['author'],
                //This will have to be added when collabs are implemented
                'collabs'       => $data_collected['collabs'],
            )
        );
    }
    // Return our modified array with the updated tints.
    return $data_collected;
}

/*
===========================================================================
				    	SANITY CHECKING FUNCTION		
===========================================================================

	Alternatively, how many ways can i creatively extrapolate data
    using heuristics to ensure we don't get a single blank entry? Hopefully
    this does not start adding ghost data.

    THIS TRICK ONLY WORKS IF THIS IMAGE HAS BEEN FIRST VIEWED CORRECTLY AND
    THE FIRST ENTRY ON THE DATABASE IS NOT EMPTY. THIS MAKES SOME SERIOUS
    ASSUMPTIONS AND YOU KNOW WHAT HAPPENS WHEN YOU ASSUME
*/

function belier_data_sanity_checks(&$data_collected)
{
    $post = get_post();
    global $wpdb;
    $analytics_table = "{$wpdb->prefix}belier_post_analytics";
    belier_sanity_collabs($data_collected, $wpdb, $analytics_table);
    belier_sanity_authors($data_collected, $wpdb, $analytics_table);
    belier_sanity_ids($data_collected, $post,$wpdb, $analytics_table);
    belier_sanity_images($data_collected, $post,$wpdb, $analytics_table);
        /*
            ===========================================================================
                                        THE DOOMSDAY DEVICE
            ===========================================================================

            To use if none of the collected fields has any data.
        */
    foreach ($data_collected as $current_key => $current_value) {
        if ((belier_is_set($current_value) == true) && (($current_key != "collabs") || ($current_key != "post_id"))) {
            if (current_user_can('manage_options')) {
                wp_die('Empty field on ' . $current_key . '. Dumping field array and post object.' . '<br>' . '<pre>' . print_r($data_collected, true) . '</pre>' . '<br> <pre>' . var_export($post, true) . '</pre>');
            }
            // Get the numerical index of the array key
            $key_index = array_search($current_key, array_keys($data_collected));
            // A better approach that should NEVER fail (even though our array is not mixed) at the expense of performance
            // $key_index=array_search($current_key, array_map("strval", array_keys($data_collected)));
            // There's probably no reason to run this.
            // belier_headers_nouser();
            wp_die('Value on Key Index ' . $key_index . ' could not be found. Please write a report to the admin team, letting them know what page triggered this, alongside browser, device, URL, method of access, and any and all information that can be used to track down the issue.');
        }
    }
    return ($data_collected);
}
function belier_sanity_ids(&$data_collected, $post,$wpdb, $analytics_table){
    /*
    ===========================================================================
                        TRYING TO FIND A MISSING ID
    ===========================================================================
*/
    // If we got no ID, try checking the other method from within wordpress.
    // This ought to fail most of the time for direct-image-access, but... Saftey Braces.
    //if (!(isset($data_collected['post_id']) && ((strlen(trim($data_collected['post_id'])) > 0) || ($data_collected['post_id'] == "")))) {
    if (belier_is_set($data_collected['post_id']) === false){
        $post_id_backup = $post->ID;
        if (belier_is_set($post_id_backup) === false) {
            // If we're not getting the post ID i guess we're
            // Grabbing it from the DB's old records.
            $emergency_sql_1 = "SELECT post_id
            FROM " . $analytics_table . "
            WHERE CHAR_LENGTH(post_id)>2
            AND post_image = '" . $data_collected['post_image'] . "'
            LIMIT 1;
            ";
            $data_collected['post_id'] = $wpdb->get_results($emergency_sql_1);
            unset ($emergency_sql_1);
        } else {
            $data_collected['post_id'] = $post_id_backup;
        }
    }
}
function belier_sanity_images(&$data_collected, $post,$wpdb, $analytics_table){
    //global $wpdb;
    //global $post = get_post();
    //$analytics_table = "{$wpdb->prefix}belier_post_analytics";
    /*
        ===========================================================================
		        			TRYING TO FIND A MISSING IMAGE
        ===========================================================================
    */
    // This should never be ran? But it does not really hurt trying

    // if (!(isset($data_collected['post_image']) && (strlen(trim($data_collected['post_image'])) > 0) || ($data_collected['post_image'] == ""))) {
    if (belier_is_set($data_collected['post_image']) === false){

        /*
            If the post image value cannot be found, let's try the old way
            that worked semi-reliably on revoltz, but seemed to work OK
            in twenty-twenty-three. This relies on querying the DB for
            the current post's attatchment GUID ( why is the GUID the filename
            is beyond my comprehension ) and could work.
        */
        $media_query = new WP_Query(
            array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => 1,
                'post_parent' => $data_collected['post_id'],
            )
        );
        $post_image_temp='';
        $post_objIterator = new ArrayIterator($media_query->posts);
        $post_image_temp=(explode("uploads/",($post_objIterator->current()->guid))[1]);
        // An alternative way to do the same, this may work with some other themes
        if (belier_is_set($post_image_temp) === false){
            $post_image_temp=explode("uploads/",get_attached_file($post->ID))[1];
        }
        $data_collected['post_image']=$post_image_temp;
        unset ($media_query);
        unset ($post_objIterator);
        /*
            Hail mary to get a missing post iamge.
            Not just a hail mary, but also absurdly wrong.
            This will just patch in holes for older pictures that have been
            recorded on the DB.
        */

        if (belier_is_set($data_collected['post_image']) === false){
            $emergency_sql_1 = "SELECT post_image
            FROM " . $analytics_table . "
            WHERE CHAR_LENGTH(post_image)>2
            AND post_id = '" . $data_collected['post_id'] . "'
            LIMIT 1;
            ";
            $data_collected['post_image'] = $wpdb->get_results($emergency_sql_1);
            unset ($emergency_sql_1);
        }
    }
}
function belier_sanity_authors(&$data_collected, &$wpdb, $analytics_table){
    /*
    ===========================================================================
                        TRYING TO FIND A MISSING AUTHOR
    ===========================================================================
*/
    // Try to heuristically add an author back.
    if (belier_is_set($data_collected['author']) === false) {
        /*
            ATTEMPT ONE:
            Trying to get our author by checking the first author with more than two characters
            with an image that matches our image.
        */
        $emergency_sql_1 = "SELECT author
        FROM " . $analytics_table . "
        WHERE CHAR_LENGTH(author)>2
        AND post_image = '" . $data_collected['post_image'] . "'
        LIMIT 1;
        ";
        /*
            ATTEMPT TWO:
            Get that author from the post id instead.
        */
        $emergency_sql_2 = "SELECT author
        FROM " . $analytics_table . "
        WHERE CHAR_LENGTH(author)>2
        AND post_id = '" . $data_collected['post_id'] . "'
        LIMIT 1;
        ";
        // Get the results of our selects into two variables
        $temp_author_1 = $wpdb->get_results($emergency_sql_1)[0];
        $temp_author_2 = $wpdb->get_results($emergency_sql_2)[0];
        //Sanity check to ensure both results give us the same author
        if ($temp_author_1 == $temp_author_2) {
            $data_collected['post_id'] = $temp_author_1;
        } // If it does not. Here's hope at least one of the queries gave us something...
        else {
            if (strlen(trim($temp_author_1)) > strlen(trim($temp_author_2))) {
                $data_collected['post_id'] = $temp_author_1;
            } else {
                $data_collected['post_id'] = $temp_author_2;
            }
        }
        unset ($emergency_sql_1);
        unset ($emergency_sql_2);
        unset ($temp_author_1);
        unset ($temp_author_2);
    }
}
function belier_sanity_collabs(&$data_collected, &$wpdb, $analytics_table){
    /*
    ===========================================================================
                            ADD COLLABS PROGRAMMATICALLY
    ===========================================================================

    In the future this will handle adding collabs if there are none, assuming one has been added already.
*/

    if (belier_is_set($data_collected['collabs']) === false) {
        $collab_sql = "SELECT collabs
            FROM " . $analytics_table . "
            WHERE CHAR_LENGTH(collabs)>2
            AND post_image = '" . $data_collected['post_image'] . "'
            LIMIT 5";
        // Store the first row of results of the query
        $collab_results = $wpdb->get_results($collab_sql)[0];
        // Apply them if there's results
        if (count($wpdb->get_results($collab_sql)) > 0) {
            $data_collected['collabs'] = $collab_results;
        }
    }
}
?>