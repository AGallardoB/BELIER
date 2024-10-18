<?php
/* 
===========================================================================
===========================================================================
===========================================================================
					      Image Processing Shim
					      (Proof of Concept  1)
===========================================================================
===========================================================================
===========================================================================

If this works as it should, it should get the data off the currently loaded
image, as per nginx's configuration/apache's .htaccess file, and get the
gears in motion to tint it properly, if certain criteria is met.

This needs to be redone to work with any wordpress theme, and that may be
the ultimate challenge.
*/

// DEBUG
echo "<pre>Made it through initial imports</pre>";
// call the main function since the nginx/apache config hasn't set which function to load

// DEBUG
echo "<h1>Do not panic!</h1>";
echo "<h2>You really should not be seeing this. But this isn't really an error</h2>";
echo "<h4>I mean... it kind of is an error? But i'm aware what's broken and when i'm done working i'll put things back before i can iron out why this is not working? But i digress</h4>";
echo "<h2>I'm working hard to improve things, move us from legacy code, and </h2>";
echo "<h2>Yours truly</h2>";
echo "<h2>-- The bunny that works in the shadows to make this site better for everyone</h2>";

belier_shim_main();

/*
===========================================================================
							MAIN SHIM LOGIC
===========================================================================

	This function will prepare all the work so watermarking can be done, if needed.
	it'll gather information about the image to be loaded, and call data_acquisition
	so it can hold the rest of the data, from there, it'll perform some theme-dependant
	checks and orchestrate if images need to be watermarked or not.
*/
function belier_shim_main(): void
{

    //DEBUG
    echo "<pre>Arrived at Shim</pre>";
    /*
        Using WP's constants to load the relevant bits from our plugin, now that we got access to then.
        Two illegal ways for completeness sake:
        1: Illegal by wordpress. Can't assume plugins live in /wp-content/plugins. as much sense as it makes.
                $plugin_dir= WP_PLUGIN_DIR . '/BELIER/';
        2: Somewhat better, but still illegal due to the same assumptions
                require_once ( trailingslashit( WP_PLUGIN_DIR . '/BELIER' ) . belier_data_acquisition.php );
        // Legal, but it will not work if being called from outside the plugin dir, like our shim.
                require_once ( plugin_dir_path( __FILE__ ) . '/belier_data_acquisition.php' );
    */
    require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_data_acquisition.php');
    require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_image_processing.php');
    require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_headers.php');
    require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_maintenance.php');
    
    //DEBUG
    echo "<pre>Imported all files.</pre>";      


    // We now have successfully loaded the needed bits of our plugin
    // Grab our MIMEType so it can be used for the headers
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    // Create our data-holding array.
    $param_array = array(
        // Filename is the file, relative to /wp-content/uploads. Think "2023/06/bunny.jpg"
        'FileName' => $_GET['image-file'],
        'MIMEType' => finfo_file($file_info, $_GET['image-file']),
        'Size' => '',
        // $_GET['size'] Is disingenious. It retrieves the image DIMENSIONS (thumbnail or slideshow), not it's size on disk.
        'Dimensions' => $_GET['size'],
    );

    //DEBUG
    echo "<pre>Grabbed Array</pre>";
    print_r($param_array);

    finfo_close($file_info);

    //DEBUG
    echo "<pre>Writing Extra Headers</pre>";
    // Check for the existence of the file, and die if it doesn't.
    if (!file_exists($param_array['FileName'])) {
        belier_headers_nofile();
    }
    // Log our image view, using $shim_filename, before we do anything else. Also facilitates extra info for the watermark
    // such as the tint values, user, IP, so on, so forth.

    echo "<pre> <p>Reaching First Dependency to get data.</p> <br>";

    $image_params = belier_get_data($param_array['FileName']);



    /*
    This piece of spaghetti is hardcoded logic from the ancient theme
    originally in use. This has to be gone once we get a new theme in position.
    Even better, make this theme-independant, somehow. This filth is inadmissible.
    Once we get thumbnails generated automatically, or ignore thumbs altogether a generic size-bound check will suffice.
    With a single call belier_shim_print or belier_shim_print_unmodified being enough after the dimensions-check
    */

    // Create hardcoded dimensions for the both dimension-types available to show on revoltz.
    $image_sizes = array(
        'thumb' => array(
            'w' => 387,
            'h' => 160,
            's' => false,
        ),
        'slideshow' => array(
            'w' => 1220,
            'h' => 450,
            's' => true,
        ),
    );
    //DEBUG
    echo "<pre>Evaluating wether or not we're printing the image</pre>";
    $dimensions_string = $image_sizes[$param_array['Dimensions']];
    // If $_GET['size'] did return aything other than empty
    if (belier_is_set($dimensions_string) === true) {
        // Creates a filesystem name for the thumbnail
        // Only know sizes are "1220x450" and "387x160", the hardcoded values of our array.
        $thumbname = preg_replace('/\.(jpg|jpeg|png|gif)/', '-' . $image_sizes[$param_array['Dimensions']]['w'] . 'x' . $image_sizes[$param_array['Dimensions']]['h'] . '.$1', $param_array['FileName']);
        // Check if the file does exist on the filesystem
        if (!file_exists($thumbname)) {
            // If it doesn't, load the image editor from WP
            $thumbnail = wp_get_image_editor($param_array['FileName']);
            // If this does not error out
            if (!is_wp_error($thumbnail)) {
                // Resize to whichever dimensions were indicated by $_GET['size'] and save them to the FS
                $thumbnail->resize($image_sizes[$param_array['Dimensions']]['w'], $image_sizes[$param_array['Dimensions']]['h'], true);
                $thumbnail->save($thumbname);
            }
        }
        // If regardless of size, the 's' attribute is set to true, watermark anyway
        if ($image_sizes[$param_array['Dimensions']]['s'] == true) {
            //DEBUG
            echo "<pre>Printing(Modified)</pre>";
            belier_shim_print($param_array, $image_params);
        } else {
            // Otherwise, print as-is
            //DEBUG
            echo "<pre>Printing(as-is)</pre>";
            belier_shim_print_unmodified($param_array);
        }
    }
    // A seemingly generic version of the former logic.
    // Check the dimensions of our currently-held image
    $image_dimensions = getimagesize($param_array['FileName']);
    // Watermark if the image's X dimension is larger than 400px

    //DEBUG
    echo "<pre> <p>Image's larger than 400?</p> <br>";

    if ($image_dimensions[0] > 400) {
        //DEBUG
        echo "<pre>Printing(Modified, >400)</pre>";
        belier_shim_print($param_array, $image_params);
    } else {
        //DEBUG
        echo "<pre>Printing(as-is, >400)</pre>";
        belier_shim_print_unmodified($param_array);
    }
}

/*
===========================================================================
						MODIFIED-IMAGE PRINTING LOGIC
===========================================================================

	Self explainatory. It'll generate the appropiate headers, and print
	a modified image in accordance to the gathered data.	
*/
function belier_shim_print(&$param_array, $image_params): void
{
    // Create our modified image object.
    // ( WP_PLUGIN_DIR . '/BELIER' . '/belier_image_processing.php' )
    $modified_image = belier_image_processing_main($image_params);
    // We cannot read until EOF, and this may be shooting myself in the foot
    // But we'll calculate the size in bytes of our modified image
    // By means of turning our binary into a string, and send that to our headers.
    $param_array['Size'] = mb_strlen((serialize($modified_image)), '8bit');
    belier_headers_images($param_array);
    // Print the resulting image and clear the variables from RAM
    print($modified_image);
    unset($param_array);
    unset($modified_image);
    exit();
}

/*
===========================================================================
					  UNMODIFIED-IMAGE PRINTING LOGIC
===========================================================================

	Passes the original image verbatim to the user.
*/
function belier_shim_print_unmodified($param_array): void
{
    // Generate HTTP Headers for our unprocessed image
    belier_headers_images($param_array);
    // Read our file, printing it as-is
    readfile($param_array['FileName']);
    // Clear our variables from RAM
    unset($param_array);
    exit();
}

?>