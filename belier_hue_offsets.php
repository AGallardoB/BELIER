<?php
/*
===========================================================================
===========================================================================
===========================================================================
					 HUE OFFSETS COMPARATOR SUB-MODULE
						  (Release  Candidate  1)
===========================================================================
===========================================================================
===========================================================================

As the name and tittle might suggest. This file shows the contents of the
hue_offsets database table, as well as comparing an uploaded file to it's
matching post image, to try to detect who might have leaked it to the world.

This sub-module is production ready, other than some visual quirks here and there.
The UX is finished and all of the queries work as expected, with no known
major bugs outside of the potential for SQL injection described below.

===========================================================================
					 			TO-DO
===========================================================================

- Make it so the trim by date option is it's own function, with a dissapearing
		button. It will not solve the giant hack that it is having to POST
		twice, but it will massively improve User Experience.
- Harden the form validation to prevent unwanted SQL injection from ill-
		intentioned administrators.
- Study the possibilty to clean up all the segments of the code in charge
		of printing badges to the screen, and pretty up some of the debugging
		prompts. Integrate everything onto error_notices
- Actually do not integrate everything into error notices. Create a generic error
		handler that can be called from every function.
- Perhaps try to un-spaghettize the file comparison function. There's not
		much that can be done within the scope of how it actually works, but
		there's always a chance it can be simplified further.
- Maybe rewrite the way render_table operates by using the built-in function
		WP_List_Tables(). Even if it's more complex than our straight approach
		to writting information directly into a table. This is nice-to-have
		for WP compliance sake. But it doesn't affect functionality.
*/

/*
===========================================================================
						MAIN FUNCTION		
===========================================================================

	The function that glues every other function together. Outside of it's glue logic
	features, it performs form validation.
*/

function belier_hues(): void
{

    /*
    ===========================================================================
                                  ENVIRONMENT SETUP
    ===========================================================================
    */

    // Enable maximum debugging.
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
    // Check for user permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    /*
    Declare all variables
    */

    // Array keys (1, -1) match database columns for replacing later.
    // To be filled by render_form. These cannot be moved to the function
    // unlike the media query above since the array is used for validation.
    $form_fields = array(
        "file" => '',
        "post_image" => '',
        "username" => '',
        "r" => '',
        "g" => '',
        "b" => '',
        "file_error" => ''
    );

    /*
    ===========================================================================
                  ACTIONS TO TAKE UPON PRESSING "SHOW RESULTS"
    ===========================================================================
    */
    hue_render_form();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        //Use JavaScript to clear all the warnings and debug elements
        echo "
		<script>
			document.getElementById(file_notice).innerHTML = \"\";
			document.getElementById(file_loc_notice).innerHTML = \"\";
			document.getElementById(file_opn_notice).innerHTML = \"\";
			document.getElementById(no_data_notice).innerHTML = \"\";
		</script>
		";
        // Fill the form fields array from POST data.
        // Check to avoid writing the array with a NULL and throwing a warning if there's no file submitted.
        if (isset($_POST['form_selected_file']) && (strlen(trim($_POST['form_selected_file'])) > 0)) {
            $form_fields['file'] = $_POST['form_selected_file'];
        } else {
            $form_fields['file'] = "";
        }
        if (isset($_POST['form_post_image']) && (strlen(trim($_POST['form_post_image'])) > 0)) {
            $form_fields['post_image'] = $_POST['form_post_image'];
        } else {
            $form_fields['post_image'] = "";
        }
        $form_fields['username'] = $_POST['form_username'];
        $form_fields['r'] = $_POST['form_red'];
        $form_fields['g'] = $_POST['form_green'];
        $form_fields['b'] = $_POST['form_blue'];
        $form_fields['file_error'] = $_FILES['form_selected_file']['error'];
        $errNumber = $form_fields['file_error'];
        /*
        Convoluted approach to verifying if everything's filled without a massive IF
        This will slice off the file key and the error code key off the array.
        This simplifies the check from a one large if and multiple nested conditionals
        to a simple for each and an if.
        */
        $required_fields = array_slice($form_fields, 1, -1);
        $required_not_completed = true;
        //check if the current field is not empty
        foreach ($required_fields as $field) {
            if (isset($field) && (strlen(trim($field)) > 0)) {
                $required_not_completed = false;
            } else {
                $required_not_completed = true;
            }
        }
        // If there is a file present...
        if ($errNumber == 0 && $required_not_completed == false) {
            // hue_error_notices($errNumber);
            // Create an upload path for the file uploaded
            $uploads_dir = trailingslashit(wp_upload_dir()['basedir']) . "belier-uploads/" . $_FILES['form_selected_file']["name"];
            // If the binary blob of the file attatched in the form can be moved to the temporary folder
            if (move_uploaded_file($_FILES['form_selected_file']['tmp_name'], $uploads_dir)) {
                // Create an array to cointain the RGB deltas of the uploaded file.
                //$preffiled_rgb_data=array( "imagefile" => '', "username" => '', "r" => '', "g" => '', "b" => '');
                // Fill the RGB values from the compare_files function
                $prefilled_rgb_data = hue_compare_files($_POST["form_post_filename"], $uploads_dir);
                // Generate the final SQL query from the prefilled data.
                $header_with_file = array('r', 'g', 'b', 'username', 'image file');
                $complete_query = hue_process_query_from_form($prefilled_rgb_data);
                belier_dashboard_render_table($header_with_file, $complete_query);
            } else {
                //error code 10
                echo " <div id='no_data_notice' class='notice notice-error inline'> <p> <strong> File cannot be moved to temp location </strong> </p> </div> ";
            }
        }
        // Check if a file has been uploaded, but no post has been given for comparison.
        if ($errNumber == 0 && $required_not_completed == true) {
            //error code 11
            echo " <div id='no_data_notice' class='notice notice-error inline'> <p> <strong> Post Name is required for comparison with a file. </strong> </p> </div> ";
        }
        // Action to take if the required fields have not been completed.
        //if( $errNumber == 4 && $required_not_completed == true ){
        //error code 12
        //echo " <div id='no_data_notice' class='notice notice-error inline'> <p> <strong> Please fill at least one field if not using a file. </strong> </p> </div> ";
        //}
        // if( $errNumber == 4 && $required_not_completed == false ){
        if ($errNumber == 4) {
            // Action to take if there is no file, but the required fields have been fulfilled.
            // hue_error_notices($errNumber);
            $results = hue_process_query_from_form($required_fields);

            //Use the new function

            $headers = array(
                "Red", "Green", "Blue", "File Name", "User Name"
            );
            require('belier_dashboard_tables.php');
            belier_dashboard_render_table($headers, $results);
            unset($results);
            unset($headers);
        }
    }
}

/*
===========================================================================
							FORM GENERATOR		
===========================================================================

	This function renders the form to input data. Validation is done on the main function
*/

function hue_render_form(): void
{

    /*
    Create a wordpress object containing information on all the posts on this website.
    Called only when rendering the form to populate the post droplist.
    */

    $media_query = new WP_Query(
        array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
        )
    );

    // $current_year = date('Y');
    // $target_year = $current_year;
    // $month = str_pad(1, 2, '0', STR_PAD_LEFT);

    //Styling of the entire form
    echo "<div class=\"wrap\"> <h2>Colour Shift Look-Up Table</h2> </div> <div class=\"wrap\"> <br> 
		<style>
		  label.button-secondary{ display: inline-block; padding-top: 0; padding-bottom: 0; }
		  label.settings{ display: inline-block; min-width: 7.25vh; max-width: 100%; text-align: right; margin-right: 0.4vh; margin-top: 0.15vh; margin-bottom: 0.15vh; }
		  p.settings, input.settings, select.settings{ display: inline-block; align: left; margin-left: 0.4vh; margin-left: 1.8vh; margin-top: 0.15vh; margin-bottom: 0.15vh; }
		  #file-chosen{ display: inline-block; margin-left: 1vh; margin-top: 0.15vh; margin-bottom: 0.15vh; text-align: center; padding-top: 0; padding-bottom: 0; }	
		</style> ";
    //The form declaration
    echo "
		<form name=\"hueform\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">";
    /* The file selector pseudo button. On which the actual button is hidden
    and an imposter using WP styling takes it's place */
    echo "<input type=\"file\" id=\"form_selected_file\" name=\"form_selected_file\" value=\"\" hidden/>
			<label for=\"form_selected_file\" class=\"button-secondary\">Upload File</label>
			<p id=\"file-chosen\" class=\"settings\">No file selected.</p>
			<hr> ";
    /*
        Script for the Native-ish button
        This will replace the label for the upload pseudo-button
        with the name of whichever file has been given.
    */
    echo "
			<script>
				const actualBtn = document.getElementById('form_selected_file');
				const fileChosen = document.getElementById('file-chosen');
				actualBtn.addEventListener('change', function(){
					fileChosen.textContent = this.files[0].name
				})
			</script> ";
    // NewFangled textbox-dropdown combined field. Finally solving the massive spaghetti from earlier.
    echo "<label for=\"form_post_image\" class=\"settings\">Post Image</label>	
			<input list=\"images\" id=\"form_post_image\" name=\"form_post_image\" class=\"settings\" value=\"\" />";
    echo "<datalist id=\"images\">";
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
    echo "<br>";
    // The Username Text Field
    echo "<label for=\"form_username\" class=\"settings\">User Name</label>";
    echo "<input list=\"users\" type=\"text\" id=\"form_username\" value=\"\" name=\"form_username\" size=\"20\" class=\"settings\" />";
    echo "<datalist id=\"users\">";
    $users = get_users();
    // Iterate through all the objects as entries on the dropdown's list
    foreach ($users as $current_user) {
        echo "<option value='" . esc_html($current_user->user_login) . "'>" . esc_html($current_user->user_login) . "</option>";
    }
    echo "</datalist> <br>";
    // The Red delta text field ( No, not a Red Delta Stradale, sadly. )
    echo "<label for=\"form_red\" class=\"settings\">Red Shift</label>
			<input type=\"text\" id=\"form_red\" value=\"\" name=\"form_red\" size=\"3\" class=\"settings\"></input>
			&nbsp &nbsp";
    // The Green delta text field
    echo "<label for=\"form_green\" class=\"settings\">Green Shift</label>
			<input type=\"text\" id=\"form_green\" value=\"\" name=\"form_green\" size=\"3\" class=\"settings\"></input>
			&nbsp &nbsp";
    // The Blue delta text field
    echo "<label for=\"form_blue\" class=\"settings\">Blue Shift</label>
			<input type=\"text\" id=\"form_blue\" value=\"\" name=\"form_blue\" size=\"3\" class=\"settings\"></input>
			<hr>";
    // The Submit button
    echo "<label for=\"submit\" class=\"submit\">
			<input type=\"submit\" id=\"submit\" name=\"submit\" class=\"button-primary\" value=\"";
    echo "Show Results";
    echo "\"/></label> </form> </div> <br> ";
    //if (($_SERVER['REQUEST_METHOD'] === 'POST') && ( (isset($_POST['form_year'])) && (strlen(trim($_POST['form_year']) > 0)) ) && ( (isset($_POST['form_month'])) && (strlen(trim($_POST['form_month']) > 0)) ) ) {
    //	echo "Show Results";
    //	echo "\"/></label> </form> </div> <br> ";
    //}
    //else{
    //	echo "Trim Post List";
    //	echo "\"/> (or show results if not narrowed by month) </label> </form> </div> <br> ";
    //}

}

/*
===========================================================================
						SQL QUERY PROCESSOR			
===========================================================================

	This function is responsible from taking the required form fields from the main
	hue_options function, after being validated, and generate the SQL query here.

	TODO: Harden the SQL Query to prevent exploitation.
*/

function hue_process_query_from_form($required_fields): string
{
    // PHP is iffy about manipulating arrays or objects. A clone is made here
    // for adapting it's contents to a SQL friendly format.
    $query_fields = $required_fields;
    // importing the datbase connector object, and selecting the adequate table.
    global $wpdb;
    $offsets_table = "{$wpdb->prefix}belier_hue_offsets";
    /*
        As a refresher, here are the table fields.

        username VARCHAR(128) NOT NULL,
        post_image VARCHAR(256) NOT NULL,
        r INT NOT NULL,
        g INT NOT NULL,
        b INT NOT NULL,
        PRIMARY KEY (username, post_image)
    */
    // Adding single quotes to the beginning and end of the username field so
    // SQL can take it properly as a string.
    $query_fields['username'] = "'{$query_fields['username']}'";
    /* Similarly to the previous transformation, turns the imagefile key's value
    (a numerical value), into the representation of the value contained (a
    truncated path). Basename can't be used as we want the year and month as part
    of the query. "/yyyy/mm/filename.ext" is the format stored in the database. */
    if (isset($_POST['form_post_image']) && (strlen(trim($_POST['form_post_image'])) > 0)) {
        $query_fields['post_image'] = "'" . $_POST["form_post_image"] . "'";
    }
    // The base query
    $sql = "SELECT r, g, b, username, post_image FROM {$offsets_table}";
    // Important hack to build a query with an indeterminate amount of fields.
    $glue_word = "WHERE";
    /* The keys in the array are named as the columns in the SQL table. This allows
    us to iterate each key and value parametrically. Appending to the base query the
    name of the array key, and the value contained in the key. The glue word gets replaced
    from WHERE to AND to keep adding fields to refine the query. */
    foreach ($query_fields as $field_name => $field_value) {
        /* Kind of convoluted IF, but it needs to check if the examined field is not null, but
        also if it is not empty either, and it cannot just contain "''" either. However if it
        contains "0", we do need that because our query needs RGB values for the delta, even
        if the delta for the channel is zero. */
        if ((isset($field_value) && (strlen(trim($field_value)) > 0) && !($field_value == "''")) || ($field_value == "0")) {
            $sql .= " " . $glue_word . " " . $field_name . "=" . $field_value;
            $glue_word = "AND";
        }
    }
    $sql .= ";";
    echo "<h2>$sql</h2>";
    //returns the query as a string, to be used by the render_table function later.
    return $sql;
}

/*
===========================================================================
						FILE COMPARISON FUNCTION		
===========================================================================

	The function that compares the file uploaded with the file on the site
*/

function hue_compare_files($post_id, $uploaded_file): array
{
    // get mime type of src
    $uploaded_mime_type = belier_mime_type($uploaded_file);

    // Sanity checks for missing or unsupported files, and GD Library presence.
    if ($uploaded_mime_type == "Unsupported") {
        //error code 14
        echo " <div id='file_loc_notice' class='notice notice-error inline'> <p> <strong> Unsupported File </strong> </p> </div> ";
        //return;
    }
    if (!strlen($uploaded_file) || !file_exists($uploaded_file)) {
        //error code 15
        echo " <div id='file_loc_notice' class='notice notice-error inline'> <p> <strong> Image doesn't exist, or uploaded file is empty/null. </strong> </p> </div> ";
        //return;
    }
    if (!function_exists('imagecreatetruecolor')) {
        //error code 16
        echo " <div id='file_loc_notice' class='notice notice-error inline'> <p> <strong> GD Library Error: Function ''imagecreatetruecolor()'' doesn't exist. </strong> </p> </div> ";
        //return;
    }
    // If all else goes well
    //error code 17
    // echo " <div id='file_loc_notice' class='notice notice-warning is-dismissible inline'> <p> <strong> Uploaded temp file ",basename($uploaded_file),". File type: {$uploaded_mime_type}</strong> </p> </div> ";

    $uploaded_image = belier_open_image($uploaded_mime_type, $uploaded_file);

    //if the gd createimagefrom* can't create an object, ie, returns "false"
    if ($uploaded_image == false) {
        //error code 18
        echo " <div id='file_loc_notice' class='notice notice-error inline'> <p> <strong> Image could not be uploaded. </strong> </p> </div> ";
    }

    // I don't know what this used to do at some point but this is unused, so commented.
    //$original_img_src = wp_get_attachment_image_src($post_id);
    $original_mime = belier_mime_type(get_attached_file($post_id));
    // The future GIF implementation requires me to check for the debugging state again. such as what image i'm opening
    $original_image = belier_open_image($original_mime, get_attached_file($post_id));

    // create variables containing only the numerical value of the x/y dimensions for both images.
    $orig_width = imagesx($original_image);
    $orig_height = imagesy($original_image);
    $uped_width = imagesx($uploaded_image);
    $uped_height = imagesy($uploaded_image);
    /*
        I brutally simplified the downscaling approach. Sometimes you just need a little less gun.
    */
    echo "<div class='card'><br>";
    if ($orig_width != $uped_width || $orig_height != $uped_height) {
        /* Make a copy of the original image with the uploaded image's dimensions */
        $new_image = imagecreatetruecolor($uped_width, $uped_height);
        //Create a new image using $new_image as destination, $original_image as the source.
        //Start at position 0,0 for both, the image wil have $uped_image's dimensions, pasting
        //A rectangle of $original_image dimensions. Resampling in the process.
        imagecopyresampled($new_image, $original_image, 0, 0, 0, 0, $uped_width, $uped_height, $orig_width, $orig_height);
        //it absolutely makes no sense why'd setting the contents of the variable here would
        //not work elsewhere. Only recourse is to overwrite the orignal.
        //Create $new_image and declare it empty? nope. Returns bool somehow.
        //Just living off the variable made here? nope. Returns null.
        //I genuinely have no clue.
        $original_image = $new_image;

        // Print the dimensions of the uploaded image, and the downscaled original, in the event
        // that downscaling is neccesary, ie, when the dimensions differ.
        echo "Original file dimensions: " . $orig_width . "px wide / " . $orig_height . "px tall.";
        echo "<br>Uploaded file dimensions: " . $uped_width . "px wide / " . $uped_height . "px tall.";
        echo "<br>Comparison sample dimensions: " . imagesx($original_image) . "px wide / " . imagesy($original_image) . "px tall.<br><br>";
    }
    $i = 0;
    // Generate arrays to insert our 3*1.02 million sample points.
    // Wonder if i could sample once per pixel and retrieve once
    // from the array, instead of once per channel.
    $arr_r = array();
    $arr_g = array();
    $arr_b = array();
    while ($i < 16) {
        // Choose a random location within bounds of the NEW original image
        $x = rand(0, (imagesx($original_image) - 1));
        $y = rand(0, (imagesy($original_image) - 1));
        // Pick the colour information of the specified pixel.
        $orig_rgb = imagecolorat($original_image, $x, $y);
        $uped_rgb = imagecolorat($uploaded_image, $x, $y);
        // For the R channel, move the value 16 bits to the right, and set all the bits to zero except the first 8 - Bitwise AND from 0x00 to 0xFF
        $arr_r[] = ((($uped_rgb >> 16) & 0xFF) - (($orig_rgb >> 16) & 0xFF));
        // For the G channel, move the value 8 bits to the right, and set all the bits to zero except the first 8 - Bitwise AND from 0x00 to 0xFF
        $arr_g[] = ((($uped_rgb >> 8) & 0xFF) - (($orig_rgb >> 8) & 0xFF));
        // For the B channel, set all the bits to zero except the first 8 - Bitwise AND from 0x00 to 0xFF
        $arr_b[] = (($uped_rgb & 0xFF) - ($orig_rgb & 0xFF));

        $i = $i + 1;
        //debug coordinate printout to verify if the rand function worked correctly.
        //if ( $i % 10000 == 0 ){
        //	echo "<p>" . $x . ", " . $y . "</p>";
        //}
    }

    /* Round all the delta variales to the nearest integer to be compatible with the database.
    More samples equals to less variance on the decimal points, thus, better rounding.
    That seems not to be the case for some test cases tho. does not matter if 32, 64, 1200
    or 1024000 samples, in some cases the accuracy is not improving. that 5.32 of the blue
    channel from the test file is not getting any closer to 6. */
    // Oh, also print the decimal values. Might be useful for those pesky exceptions.
    echo "<p>Hue Difference (Float): <strong>Red: </strong>" . bcdiv((array_sum($arr_r) / count($arr_r)), 1, 3) . " - ";
    $red_diff = round(array_sum($arr_r) / count($arr_r));
    if ($red_diff == -0) {
        $red_diff = 0;
    }
    echo "<strong>Green: </strong>" . bcdiv((array_sum($arr_g) / count($arr_g)), 1, 3) . " - ";
    $green_diff = round(array_sum($arr_g) / count($arr_g));
    if ($green_diff == -0) {
        $green_diff = 0;
    }
    echo "<strong>Blue: </strong>" . bcdiv((array_sum($arr_b) / count($arr_b)), 1, 3) . "</p>";
    $blue_diff = round(array_sum($arr_b) / count($arr_b));
    if ($blue_diff == -0) {
        $blue_diff = 0;
    }

    echo "<p>Hue Difference (Int): <strong>Red: </strong>" . $red_diff .
        " - <strong>Green: </strong>" . $green_diff .
        " - <strong>Blue: </strong>" . $blue_diff .
        "</p></div>";
    // Grab the rounded values and insert them in a format that process_query_from_form
    // will accept as if it were coming from the HTML form itself.
    $comparison_fields = array("imagefile" => '', "username" => '', "r" => $red_diff, "g" => $green_diff, "b" => $blue_diff);
    //Destroy the image blobs and close the file to allow deletion and RAM cleaning.
    imagedestroy($original_image);
    imagedestroy($uploaded_image);
    unlink($uploaded_file);

    return $comparison_fields;
}


/*
===========================================================================
						FILETYPE VERIFICATION			
===========================================================================

	Function to determine the type of file uploaded.
	Filetypes supported: png, jpeg, gif.
	It'll return unsupported if the mimetype is not one of these
*/

function belier_mime_type($file): string
{

    $mime_type = mime_content_type($file);
    if (!(($mime_type == "image/png") || ($mime_type == "image/jpeg") || ($mime_type == "image/gif"))) {
        $mime_type = "Unsupported";
    }

    return $mime_type;
}

/*
===========================================================================
						FILETYPE VERIFICATION			
===========================================================================

		It checks again if the file is valid and will print some debug
		information, as well as creating a copy of the image on RAM (?)
		to do further checks later. It'll print an error if the image is
		damaged, corrupted, or some how invalid despite checking having
		the correct MIME type.
*/

function belier_open_image($mime_type, $src)
{
    //Prepares the filetype from the mime string for nicer presentation.
    $image_type = substr($mime_type, strpos($mime_type, "/") + 1);
    //Informs the user that the current file is being opened. Could be disabled in the future.
    //error code 19
    //echo "<div id='file_opn_notice' class='notice notice-info is-dismissible inline'> <p> <strong> Trying to open $info $image_type image. </strong> </p> </div> ";
    //Shorthand to avoid repetition.
    //error code 20
    $image_object_error = "<div id='file_opn_notice' class='notice notice-error inline'> <p> <strong> No image object found. </strong> </p> </div> ";
    // Create an image from GIF, JPEG, and PNG to return to the calling method.
    // Print an error if that's not possible.
    if ($image_type == 'gif') {
        $image = imagecreatefromgif($src);
    } elseif ($image_type == 'jpeg') {
        //@ini_set('gd.jpeg_ignore_warning', 1);
        ini_set('gd.jpeg_ignore_warning', 1);
        $image = imagecreatefromjpeg($src);
    } elseif ($image_type == 'png') {
        $image = imagecreatefrompng($src);
    }
    require('belier_maintenance.php');
    // Return the image if it's a valid image. If it isn't. Print the error.
    if (!belier_is_set($image)) {
        echo $image_object_error;
    }
    return $image;
}

?>