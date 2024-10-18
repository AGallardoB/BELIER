<?php
/* 
===========================================================================
===========================================================================
===========================================================================
					      BELIER HEADER MODULE
					      (Proof of Concept 1)
===========================================================================
===========================================================================
===========================================================================

This will simplify our header-sending-related tasks, primarily to check
and validate our users, and for the image watermarking shim.

These functions expect an array with this content:

$param_array=array(
            'FileName' => '',
            'MIMEType' => '',
            'Size' => '',
        );

There is a good chance this does not work at all. these validations seem to
be done well past the initial headers are sent. The 301 redirect works tho.
*/


/*
===========================================================================
					    Generic Headers Function
===========================================================================
Does what it says on the tin. This is the one that will be fired more often
*/
function belier_generic_headers($param_array): void
{
    // If there's no parameter array set (IE, it's being called from init)
    $probe_user = wp_get_current_user();
    if (empty($param_array)) {
        // Maybe for our user checking function?
        //if($probe_user->roles[0] != 'subscriber') {
        //    return;
        //}
        if (!$probe_user->exists()) {
            header("HTTP/1.0 403 Forbidden");
            wp_die('Access Forbidden.');
        }
        nocache_headers();
        unset($probe_user);
    }
}

// These do exactly as they say on the tin.
// Handle Shim's 404-type errors.
function belier_headers_nofile(): void
{
    header("HTTP/1.0 404 Not Found");
    wp_die('The requested file could not be found. HTTP 404');
}

// Handle shim's 403-type errors.
function belier_headers_nouser(): void
{
    header("HTTP/1.0 403 Forbidden");
    wp_die('Access is Forbidden. HTTP 403');
}

// Send headers for images discarded by the shim. Thumbnails and smaller UI elements
function belier_headers_unprocessed($param_array): void
{
    nocache_headers();
    header("Content-Type: " . $param_array['MIMEType']);
    header("Content-Length: " . filesize($param_array['FileName']));
}

// Headers in use when watermarking modified image
function belier_headers_images($param_array): void
{
    nocache_headers();
    header("Content-Type: " . $param_array['MIMEType']);
    // If the size is set and it's larger than zero, it means it's a modified image
    if ((isset($param_array['Size']) && (strlen(trim($param_array['Size'])) > 0)) || ($param_array['Size'] != "")) {
        header("Content-Length: " . $param_array['Size']);
    } // If not, it means it's an unmodified image
    else {
        header("Content-Length: " . filesize($param_array['FileName']));
    }
}

// Not used for our shim, funnily enough. But it'll do. This one works
// beautifully for the check_users function
function belier_headers_redirect($redirect_url): void
{
    nocache_headers();
    wp_safe_redirect($redirect_url, 301);
    exit;
}

?>