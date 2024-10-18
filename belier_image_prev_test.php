<?php

/* 
===========================================================================
===========================================================================
===========================================================================
					    IMAGE  PREVIEW  SUBMODULE
						   Release Candidate 1
===========================================================================
===========================================================================
===========================================================================

A boilerplate to interactively test the functionality of image_procesing
before i move it's functionality to itself. A safe way to develop the
Drawing routines needed. Such as copying an image onto another. Writing.
Coalescing images, so on, so forth.

image_processing will need different bits of code, to work with the data
gathered rather than using database queries to that effect. and other things.

Most of the functionality is contained in belier_iprev_modify_image, so it
should be trivial to modify inside image_processing so it's self-contained
and unencumbered.
*/

/*
===========================================================================
					    IMAGE PREVIEW FLOW CONTROL
===========================================================================

Unsurprisingly, it controls the flow. It basically fancily wraps around all the
other ancillary functions.
*/
function belier_image_preview(): void
{

    // Print an introduction
    echo "
    <div class=\"wrap\"> 
	    <h2>Image Processing Debugging Page</h2>
        <p>This will preview how the images can be seen by the image rendering mechanism.</p>
    </div>
    <hr>
    ";
    // Call our form renderer
    belier_iprev_render_form();
    // If there's a post selected on the dropdown. Generate the modified image block
    // from the modify_image function below. Then print the resulting blob of passing
    // it to render_image
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['form_post_image']) && (strlen(trim($_POST['form_post_image'])) > 0))) {
        [$output_urlname, $output_filename] = belier_iprev_modify_image();
        echo "<img src='{$output_urlname}' />";
    }

    if (file_exists($output_filename)) {
        unlink($output_filename);
    }
    //require_once('belier.php');


    //If the folder exists.
    /*
            $temp_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'belier-tmp';
        if(file_exists($temp_dir)){
            sleep(30);
            array_map('unlink', array_filter((array) glob("{$temp_dir}/*")));
        }
    if(file_exists($temp_dir)){
        sleep(30);
        // Wizardry i've found on StackOverflow that escapes my mind. Possibly runs through all the subdirectories
        $drectoryIterator = new RecursiveDirectoryIterator($temp_dir, FilesystemIterator::SKIP_DOTS);
        // And finds all the files on each directory
        $recursiveIterator = new RecursiveIteratorIterator($drectoryIterator, RecursiveIteratorIterator::CHILD_FIRST);
        // For each currently held file, delete regardless of file or folder.
        foreach ( $recursiveIterator as $file ) {
            $file->isDir() ?  rmdir($file) : unlink($file);
        }
    }
    */
}

/*
===========================================================================
					    FORM WRAPPER FUNCTION
===========================================================================
*/
/*
    A wrapper for the form itself. Proof of concept for an eventual rewrite.
    Since CSS technically will style the form based on it's classes. I figure
    it's more legible to just have a wrapper with merely the start and end headers
    of the form. and for each individual, discrete part of the form,
    call to a function. Helps divide the file into more manageable chunks. I hope.
*/
function belier_iprev_render_form(): void
{
    // Style the form as an one-liner
    echo " <style> form{ display: inline; } form input { text-align: center; margin-left: 0.25%; margin-right: 1%; } input.settings { text-align: left; } </style> ";
    // Create the boilerplate of the form, as well as the submit button.
    echo "
    <form name=\"img_preview_form\" class=\"img_preview_form\" method=\"post\" action=\"\" enctype=\"multipart/form-data\"> <br>";
    // Move the complexity of the dropdown off to a different function.
    belier_iprev_image_selector();
    belier_iprev_user_selector();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['form_post_image']) && (strlen(trim($_POST['form_post_image'])) > 0))) {
        belier_iprev_ip_selector();
    }
    //print the submit button and the end of the form   
    echo " <br> <label for=\"submit-form\" class=\"submit-form\">
	<input type=\"submit\" id=\"submit-form\" name=\"submit-form\" class=\"button-primary\" value=\" Show Results on Image \"/></label>
    </form> <br>";
}

/*
===========================================================================
					    IMAGE SELECTOR DROPDOWN
===========================================================================
*/
/*
    Yes, i know. One whole function for a dropdown. But it neats the form properly.

    Lifted straight out of a portion of belier_hue_offsets.php, this will query the wordpress
    post database and give a result of every post ever given. It does not need to do more
    for this proof of concept module.
*/
function belier_iprev_image_selector(): void
{
    // Create the query results object for the names of all uploads.
    $media_query = new WP_Query(
        array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
        )
    );
    // Open the dropdown label as well as the dropdown itself.
    echo "
        <label for=\"form_post_image\" class=\"label\">Select Any Image.</label>	
        <input list=\"images\" id=\"form_post_image\" name=\"form_post_image\" class=\"img_preview_form\" value=\"\" />";
    echo "<datalist id=\"images\">";
    // Iterate through all the objects as entries on the dropdown's list
    foreach ($media_query->posts as $post) {
        // In the default installation of WordPress, files get "-scaled" on their post names.
        // However, the actual database entry still records the full name of the picture.
        // This ensures to remove the "-scaled" string from the list so the query works.
        $post_id = explode("uploads/", get_attached_file($post->ID))[1];
        if (str_contains($post_id, "-scaled")) {
            $post_id = str_replace("-scaled", "", $post_id);
        }
        echo "<option value='{$post_id}'>", $post_id, "</option>";
    }
    echo "</datalist>";
    // Close the select. Add a new line so the image can be shown with some space.
}

/*
===========================================================================
					    USER SELECTION FUNCTION
===========================================================================
*/
// Select an user at will
function belier_iprev_user_selector(): void
{
    //$users= get_users( array( 'role__in' => array( 'author', 'subscriber', 'administrator', 'editor' ) ) );
    $users = get_users();
    echo "
        <br>
        <label for=\"form_post_users\" class=\"label\">Select Any User.</label>	
        <select id=\"form_post_users\" name=\"form_post_users\" class=\"form_post_users\" value=\"\">
        <option value=\"\" disabled selected>Select an User</option> ";
    // Iterate through all the objects as entries on the dropdown's list
    foreach ($users as $current_user) {
        echo "<option value='" . esc_html($current_user->user_login) . "'>" . esc_html($current_user->user_login) . "</option>";
    }
    // Close the select.
    echo " </select>";
}

function belier_iprev_ip_selector(): void
{
    global $wpdb;
    $analytics_table = "{$wpdb->prefix}belier_post_analytics";
    $sql = "SELECT DISTINCT ip_address FROM {$analytics_table} WHERE username='" . $_POST['form_post_users'] . "'";
    $query_results = $wpdb->get_results($sql);
    echo "
        <br>
        <label for=\"form_post_ips\" class=\"label\">Select Any IP.</label>	
        <select id=\"form_post_ips\" name=\"form_post_ips\" class=\"form_post_ips\" value=\"\">
        <option value=\"\" disabled selected></option> ";
    // Iterate through all the objects as entries on the dropdown's list
    foreach ($query_results as $row) {
        echo "<option value='" . $row->ip_address . "'>" . $row->ip_address . "</option>";
    }
    // Close the select.
    echo " </select>";
}

/*
===========================================================================
					    IMAGE MODIFICATION FUNCTION
===========================================================================

This is it buns, we did it. We finally did it. An agnostic way to transform images
on-the-fly without writing them to disk... Now... How do we call this from the site.

Here's a description of what the code here does, in plain english:

When this function is called. It'll retrieve the current data from our belier_get_data
function. On the image_processing version, i need to find a way to pass it as a parameter.

Maybe passing it as "data_collected" given that it might need the same name...?

At any rate. With the viewership array on hand, it'll add the path to the image by adding
what we removed back then when we took the post.

Then we'll create 4 imagick objects. One for our image to modify, other two for the positive
and negative RGB values ( if you're reading this and know a better way to add and substract
rgb colors from the image as a whole, please let me know ), and a last object for the text
that we will be superimposing.

From there a few checks are done, the image's converted into a blob, and then returned as a string
so it can be called directly inside an img tag to replace the source. This only works on
the theme-side as far as i know. I still need to figture out how to feed it the data without
thrashing the DB or getting our data again. I might give up and eat that speed penalty just
for simplicity (of implementation)'s sake.
*/

function belier_iprev_modify_image(): array
{
    $image_params = belier_iprev_condensed_data();
    // $image_params=belier_get_data();
    // Get the path to the selected image.
    // If post_image is present and valid.
    if ((isset($image_params['post_image']) || (strlen(trim($image_params['post_image'])) > 8))) {
        $image_path = trailingslashit(wp_upload_dir()['basedir']) . $image_params['post_image'];
    }
    //If we're on the admin page, forcefully get the data from the dropdown.
    if (is_admin()) {
        $image_path = trailingslashit(wp_upload_dir()['basedir']) . $_POST['form_post_image'];
    }
    // Grab the selected image. For compositing and Sizing
    $modified_image = new Imagick($image_path);
    // Grab the current image format. We'll need this so we can properly substract RGB later.
    $image_format = $modified_image->getImageFormat();
    // Array containing the X/Y dimensions of our image. This could've easily been two variables
    $image_dimensions = array('x' => $modified_image->getImageWidth(), 'y' => $modified_image->getImageHeight());
    // Copy the image parameters' RGB values ( sign and all ) to a new array.
    $iterable_rgb = array(
        $image_params['red'],
        $image_params['green'],
        $image_params['blue'],
    );
    // Arrays for storing positive and negative absolute values.
    $positive_rgb = array('0', '0', '0');
    $negative_rgb = array('0', '0', '0');
    // Copy the absolute value of the received RGB range to the positive or negative arrays.
    // Iterate through the iterable index and place the negative and positve values where they belong
    foreach ($iterable_rgb as $rgb_index => $current_value) {
        if ($current_value < 0) {
            $negative_rgb[$rgb_index] = abs($current_value);
        } else {
            $positive_rgb[$rgb_index] = abs($current_value);
        }
    }
    //Strings for ImagickPixel's color definition. Should work by sticking this directly. Not want to risk it.
    $pos_rgb_string = 'rgb(' . $positive_rgb[0] . ',' . $positive_rgb[1] . ',' . $positive_rgb[2] . ')';
    $neg_rgb_string = 'rgb(' . $negative_rgb[0] . ',' . $negative_rgb[1] . ',' . $negative_rgb[2] . ')';
    // If the sum of all the items on the negative array is above zero, create a new image and add the tint.
    if (($negative_rgb[0] + $negative_rgb[1] + $negative_rgb[2]) > 0) {
        $positive_values = new Imagick();
        // Create a solid square with the POSITIVE RGB values from our tints.
        $positive_values->newImage($image_dimensions['x'], $image_dimensions['y'], new ImagickPixel($pos_rgb_string));
        $modified_image->compositeImage($positive_values, Imagick::COMPOSITE_PLUS, 0, 0);
        // Flatten the image.
        // $modified_image->flattenImages();
        // using non-deprecated method
        $modified_image->setImageBackgroundColor('white');
        $modified_image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $modified_image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
    }
    // If the sum of all the items on the positive array is above zero, create a new image and add the tint.
    if (($negative_rgb[0] + $negative_rgb[1] + $negative_rgb[2]) > 0) {
        $negative_values = new Imagick();
        // Create another solid square with the NEGATIVE RGB values from our tints.
        $negative_values->newImage($image_dimensions['x'], $image_dimensions['y'], new ImagickPixel($neg_rgb_string));
        // Watch out for this, while Addition adds source to destination. We want to subtract destination from source.
        // I guess since we're doing the opposite of addition... The order of the images must be reversed, too.
        $negative_values->compositeImage($modified_image, Imagick::COMPOSITE_MINUS, 0, 0);
        // Set the format of our negative blob as the format of the original image, so when we stomp over it, it retains that data.
        $negative_values->setImageFormat($image_format);
        // Merge the layers. Should help with RAM.
        $negative_values->flattenImages();
        $modified_image = $negative_values;
    }
    // Prepare a new image for text printing.
    $text_image = new Imagick();
    $draw_text = new ImagickDraw();
    // A new image-sized image. Black background.
    $text_image->newImage($image_dimensions['x'], $image_dimensions['y'], new ImagickPixel('rgba(0,0,0, 0.0)'));
    // Set our color for printing text, the font family, and the text size
    // smaller values will be a lot less intense.
    $draw_text->setFillColor(new ImagickPixel('rgba(128,128,128, 0.1)'));
    // Font needs to be placed in the wp-admin folder for some unknown reason.
    $draw_text->setFont('Arvo-Regular.ttf');
    // Our font size. 30 seems good for me.
    $draw_text->setFontSize(30);
    // Write the text to the new image
    // Start at Y=0, Add 110px Vertically.
    for ($y_pos = 0; $y_pos <= $image_dimensions['y']; $y_pos += 110) {
        // On each new Y coordinate, position yourself on a random position outside the leftmost edge until
        // Start at X=0, add 185px Horizontally
        for ($x_pos = 0; $x_pos <= $image_dimensions['x']; $x_pos += 185) {
            // Start writing text, using the parameters from $draw_text, over at our coordinates at 
            // x_pos, y_pos, with a 45 degree clockwise angle. The text is self explainatory: "..User_Name, 10.20.30.40, dd-mm-yyyy hh:mm:ss.."
            // WORD OF WARNING, the date viewed might be up to 30 seconds behind the logged date on the database. This is what we get for not being unable to get
            // the data in-situ. Though i don't think we even need 30 second precision for this. I don't think the old img.php even included the date.
            $text_image->annotateImage($draw_text, $x_pos, $y_pos, 45, '..' . $image_params['username'] . ', ' . $image_params['ip_address'] . ', ' . $image_params['date_viewed'] . '..');
        }
    }
    // Compose the text over the tinted image, and flatten the image.
    //$modified_image->compositeImage($text_image, Imagick::COMPOSITE_PLUS, 0, 0);
    $modified_image->compositeImage($text_image, Imagick::COMPOSITE_OVER, 0, 0);
    $modified_image->flattenImages();
    // Force the format to be in lowercase, as imagick documentation is ambiguous. sometimes the format
    // appears in uppercase, others in lowercase. Safety braces.
    $image_format = strtolower($image_format);
    // Prepare format for insertion on img tag. Perhaps i should throw an error if the format is not jpeg, gif, or png
    // As they're the supported formats for the IMG Tag... 
    if ($image_format == array('jp2', 'jpt', 'j2c', 'j2k', 'jxr', 'jpeg')) {
        $image_format = 'jpg';
    }
    if ($image_format == array('png', 'png8', 'png00', 'png24', 'png32', 'png48', 'png64')) {
        $image_format = 'png';
    }
    // Convert the image into a binary blob to be given to any IMG tag
    // Not used because it absolutely tanks clients. Keeping here just in case.
    // $modified_image_blob=base64_encode($modified_image->getImageBlob());
    // Prepare write to disk.
    // Create our destination path so we can nuke it later from the FileSystem
    $temp_dir = trailingslashit(wp_upload_dir()['basedir']) . 'belier-tmp';
    // Create the URL to our file so it can be echoed in an IMG
    $temp_url = trailingslashit(wp_upload_dir()['baseurl']) . 'belier-tmp';
    $RNG = rand(0, 32000);
    // Append our filename to our URL or FS Path
    $output_file = "{$temp_dir}/" . $RNG . "-" . (strstr($image_params['post_image'], ".{$image_format}", true)) . ".{$image_format}";
    $output_url = "{$temp_url}/" . $RNG . "-" . (strstr($image_params['post_image'], ".{$image_format}", true)) . ".{$image_format}";
    // Create an empty file at our location with our given name.
    touch($output_file);
    // Dump the contents of our Imagick object to our FS File
    file_put_contents($output_file, $modified_image);
    // Erase Imagick Object once done.
    $modified_image->clear();
    // Clear params array.
    unset($image_params);
    // Return our FS Path and URL through an array.
    return [$output_url, $output_file];
}

function belier_iprev_condensed_data(): array
{
    // I'm done finding ways to massage the array across files. We're grabbing our data.
    // THIS MUST BE RAN AFTER `data_acquisition` FINISHED DOING IT'S THING.
    // Maybe document this when you're not mad even if it's the abridged version of data_acquisition
    // ===========================================================================
    //                          /!\ BREAKAGE WARNING /!\		
    // ===========================================================================
    // This hack should not exist. Ideally. $image_params should be received from getting
    // $data_collected from our main data collection function. I cannot get it to work.
    // For as long as i can't get $data_collected copied across files, i have to update how i get
    // the post image here. This is important.

    // Date Viewed
    //$date_viewed=new DateTime;
    //$date_viewed=$date_viewed->format('Y-m-d H:i:s') ;

    if (!isset ($media_query)) {
        $media_query = new WP_Query(
            array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
            )
        );
    }

    $date_viewed = date('Y-m-d H:i');
    // Get IP
    $ip_address = $_SERVER['REMOTE_ADDR'];
    // If we're on the admin page, we gotta cheat.
    if (is_admin()) {
        $post_image = trailingslashit(wp_upload_dir()['basedir']) . $_POST['form_post_image'];
        $post_image = (explode("uploads/", $post_image)[1]);
        $username = wp_get_current_user()->user_login;
        if ((isset($_POST['form_post_users']) && (strlen(trim($_POST['form_post_users'])) >= 2))) {
            $username = $_POST['form_post_users'];
        }
        if ((isset($_POST['form_post_ips']) && (strlen(trim($_POST['form_post_ips'])) >= 2))) {
            $ip_address = $_POST['form_post_ips'];
        }
    } else {
        // Get Post ID. Both ways.
        //$post=get_posts(array('numberposts' => -1, 'post_status' => 'inherit', 'post_type' => 'attachment'));
        $post = get_post();
        $post_id = explode("uploads/", get_attached_file($post->ID))[1];
        $post_objIterator = new ArrayIterator($media_query->posts);
        $post_image = (explode("uploads/", ($post_objIterator->current()->guid))[1]);
        if ((!isset($post_image) || (strlen(trim($post_image)) <= 8)) && (isset(get_post_meta($post_id)['post_image'][0]))) {
            $post_image = (explode("uploads/", get_post_meta($post_id)['post_image'][0])[1]);
        }
        // Get Name, Don't check. WP will fail on data_acquisition if true.
        $username = wp_get_current_user()->user_login;
    }
    // Get RGB values from Database.
    global $wpdb;
    $offsets_table = "{$wpdb->prefix}belier_hue_offsets";
    $sql = "SELECT r,g,b
    FROM $offsets_table
        WHERE username = '{$username}' 
        AND post_image = '{$post_image}'
        LIMIT 1;
    ";
    $query_results = $wpdb->get_results($sql);
    echo "<pre>";
    print_r($sql);
    echo "<br>";
    print_r($username);
    echo "<br>";
    print_r($post_image);
    echo "<br>";
    print_r($query_results);
    echo "<br>";
    // Copy signed values into variables.
    $red = $query_results[0]->r;
    $green = $query_results[0]->g;
    $blue = $query_results[0]->b;
    // Fill our truncated data array.
    $image_params = array(
        'date_viewed' => $date_viewed,
        'ip_address' => $ip_address,
        'username' => $username,
        'post_image' => $post_image,
        'red' => $red,
        'green' => $green,
        'blue' => $blue,
    );
    print_r($image_params);
    echo "</pre>";
    unset($probe_query);
    return $image_params;
}

?>