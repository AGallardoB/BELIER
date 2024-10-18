<?php

/* 
===========================================================================
===========================================================================
===========================================================================
					    CLERICAL MAINTENANCE MODULE
						    (Proof of Concept 1)
===========================================================================
===========================================================================
===========================================================================

Handles miscelaneous things related to BELIER maintenance and safekeeping.

Checks for users, does GC Cleanup, and so on.

*/
/*
===========================================================================
						GARBAGE COLLECTION CLEANUP		
===========================================================================
	This will clean up every PHP session older than 30 minutes. And it'll 
	be run each time an user loads any page. This will help us clean up the
	/tmp issues we've been having somehow for a while at a negligible cost
	of performance. Beats a crontab or getting incredibly risque with the
	proposed "find /tmp -mtime +1 -user {$our_wordpress_user} -exec rm -rf {} \;" solution
*/

function garbage_collection_cleanup(): void
{
    // This can only be executed on the init hook. If it's ran after
    // belier_send_headers, it'll not work. So we're limited to the init script
    // Note: session_gc() is recommended to be used by task manager script,
    // but it may be used as follows.

    // Used for last GC time check
    $bel_gc_time = '/tmp/php_session_last_gc';
    $bel_gc_period = 1800;
    session_start();
    // Execute GC only when GC period elapsed.
    // i.e. Calling session_gc() every request is waste of resources.
    if (file_exists($bel_gc_time)) {
        // added parenthesis to the time and bel_gc_period because it didn't sit well with me
        // should not matter but we're mixing comparators and arithmetics.
        // If our sacrificial file's time delta is infarior to 30 minutes
        if (filemtime($bel_gc_time) < (time() - $bel_gc_period)) {
            session_gc();
            touch($bel_gc_time);
        }
    } else {
        touch($bel_gc_time);
    }
    session_destroy();
}

/*
===========================================================================
						USER REDIRECTION FUNCTION		
===========================================================================
	Does what it says on the tin. It redirects users away from a few select
	pages, right onto the preview page, with a 301 http error.

	User validation is fine, but our brand of user-validation by redirection
	needs to be changed for the generic version of BELIER. Perhaps write
	a dashboard panel that writes the array to disk.
*/

function belier_check_users(): void
{
    /*
        A massive hack that prevents logged out users from seeing our website...?
    */
    // Assumes a lot about the website this plugin runs on. BAD FOR PORTABILITY. DOES NOT WORK ON BELIER SITE.
    // MAKE THIS A FILE ONE CAN EDIT
    //$redirect_url = esc_url( home_url( $path = '/preview', $scheme = 'https' ) );
    $redirect_url = esc_url(home_url('/preview', 'https'));
    //$current_url = explode("?", $_SERVER['REQUEST_URI']);
    /*
    ===========================================================================
                        WE ARE REDIRECTING EVERYONE
    ===========================================================================

    if ( !is_admin() ){
        nocache_headers();
        wp_safe_redirect( $redirect_url, 301 );
        exit;
    }
    */

    // Find a way to redirect people programatically. Fill that array somewhere in a file, or some persistant variable.
    if ((!is_user_logged_in()) && (!is_page(array('preview', 'about', 'join_page', 'apply', 'contact', 'faq', 'landing_page', 'terms_of_service', 'renewal', 'homepage')))) {
        //nocache_headers();
        //wp_safe_redirect( $redirect_url, 301 );
        belier_headers_redirect($redirect_url);
    }

    // redirect everyone if this is an image
    //if (! current_user_can( 'manage_options' ) && (str_contains($current_url, array ('.png', '.jpeg', '.jpg', '.gif')))){
    //if ((str_contains($current_url, array ('.png', '.jpeg', '.jpg', '.gif')))){
    //	belier_intercept_file();
    //	exit;
    //}
    // Run the following if we're NOT (in the admin page or the front page) AND (the current page is a single post or has/contains an attatchment)
    // Maybe i can use "is_singular" instead of the conditonal?
    if (is_user_logged_in() && (!(is_admin() || is_front_page()) && (is_attachment() || is_single()))) {
        belier_get_data();
    }

    /* USE THIS ONE WHEN REFERENCING DATA ACQUISITION IN THE THEME
    require_once (WP_PLUGIN_DIR . '/BELIER/data_acquisition.php');
    require_once (WP_PLUGIN_DIR . '/BELIER/image_processing.php');
    $image_params = belier_get_data();
    // Do your post replacing magic here?
    // insert the image when ready.
    [$output_url, $output_file]=belier_image_processing_main($image_params);
    echo "<img src='{$output_url}' />";
    if(file_exists($output_file)){
        unlink($output_file);
    }
    */
}

/*
===========================================================================
						TEMP FILE CLEANUP FUNCTION		
===========================================================================
	Writting to RAM is expensive and slow. Ironically, writting to disk
	is many times faster. Make sure to run this periodically.
*/

function belier_temp_file_cleanup(): void
{
    // Delete everything from seconds 00 to 40
    if (date('s') < '30') {
        // declare where our temp files are.
        $temp_dir = trailingslashit(wp_upload_dir()['basedir']) . 'belier-tmp';
        // fucking nuke them off the face of earth
        array_map('unlink', array_filter((array)glob("{$temp_dir}/*")));
    }
    // Untested and unproven method. This method did not seem to clear the files.
    // $temp_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'belier-tmp';
    /*
    // If our folder exists.
    if(file_exists($temp_dir)){
        // Sleep for 30 seconds. bad idea. Introduces delay. Use if the current second
        // is higher than 30 instead.
        sleep(30);
        // Wizardry i've found on StackOverflow that escapes my mind. Possibly runs through all the subdirectories
        $drectoryIterator = new RecursiveDirectoryIterator($temp_dir, FilesystemIterator::SKIP_DOTS);
        // And finds all the files on each directory
        $recursiveIterator = new RecursiveIteratorIterator($drectoryIterator, RecursiveIteratorIterator::CHILD_FIRST);
        // For each currently held file, delete regardless of file or folder.
        foreach ( $recursiveIterator as $file ) {
            $file->isDir() ?  rmdir($file) : unlink($file);
        }
        // Did not work so i'm not sure my description's correct here.
    }
    */
}

/*
===========================================================================
                        /!\ FILTH WARNING /!\		
===========================================================================
	These functions need to be moved outside of this file and into it's
	own file. (belier_collabs_debug?). Specially if you need to add support for
	adding collabs post facto on a scheduled basis. This is growing completely
	mental, you know? this fucking project is HUGE.
===========================================================================
                     /!\ END OF FILTH WARNING /!\		
===========================================================================
*/

/*
===========================================================================
						CHECK FOR LENGTH FUNCTION		
===========================================================================
	Really a small timesaver in the event i have some very very verbose-named variables/array keys-indices
	It's gonna be easier to just call "belier_is_set($thing)" than the alternative.

	Optionally takes a "needs length" variable, if set to chars, or bytes (belier_is_set($thing, true)), it will
	return an array with the fact it does indeed have content, and the length of the requested item, either in characters
	or number of bytes.
*/

// i can probably declare the function as int|bool, but that'd require PHP 8+
// so untyped function it is.
function belier_is_set($item, $length_type = 'none')
{
    // Check if the item has a byte or char length above zero
    if (((isset($item) && (strlen(trim($item)) > 0)) && !(($item == "''") || ($item != ""))) || ($length_type == 'bytes' && filesize($item) > 1)) {
        // If we have been asked for the length in characters
        if ($length_type == 'chars') {
            /*Not returning a bool here, since, it's assumed that if we're asking for not-emptyness AND if it has length
                If it has length clearly it is not empty, so you got to handle whenever you query for this
                to expect handling a non-bool type.
            */
            return strlen(trim($item));
            // If we've been asked for the length in bytes
        } else if ($length_type == 'bytes') {
            // Same reasoning as above.
            return filesize($item);
        }
        // If the first half of the check succeeds, and we've not been asked for the size in chars or bytes,
        // it means we're just being asked if whatever we're analyzing has content
        return true;
        // If this check did not succeed, there's no content on $item
    } else {
        return false;
    }
}

?>