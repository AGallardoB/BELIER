<?php

/* 
===========================================================================
===========================================================================
===========================================================================
					    COLLABORATION MARKER SUBMODULE
						    (Proof of Concept 1)
===========================================================================
===========================================================================
===========================================================================

A boilerplate at this point.

*/

/*
===========================================================================
					    COLLAB MARKER CONTROL FLOW
===========================================================================

This function handles calling the form, performs validation, and dictates the
control flow for this submodule
*/
function belier_collabs(): void
{
    echo "<h2>Collab Marker</h2>";
    echo "<p>This file will select any file. Show the author, and add up to 4 collaborators.</p>";
    // Style the form to be inline
    echo " <style> form{ display: inline; } form input { text-align: center; margin-left: 0.25%; margin-right: 1%; } input.settings { text-align: left; } </style> ";

    // Part 1: Render the form to select an image, if
    // visiting for the first time AND post image is NOT set.
    if (!($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['form_post_image']) && (strlen(trim($_POST['form_post_image'])) > 0)))) {
        belier_collab_image_selector();
    } // Part 2: Render the form to add authors.
    else {
        belier_collab_collaborers_selector();
    }
}

/*
===========================================================================
					    IMAGE SELECTOR FUNCTION
===========================================================================

Select the image to select which post to edit later.
*/
function belier_collab_image_selector(): void
{
    $media_query = new WP_Query(
        array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
        )
    );
    // Declare the container for our form and declare our form header.
    // That way we can read from this form.
    echo "<div class=\"wrap\">
    <form name=\"collab-form\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">";
    // The label for our post list, and the post list itself.
    echo "<label for=\"form_post_image\" class=\"settings\">Post Image</label>	
	<input type=\"search\" list=\"images\" id=\"form_post_image\" name=\"form_post_image\" class=\"settings\" value=\"\" />";
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
    //Submit Button.
    echo "<label for=\"submit\" class=\"submit\">
	<input type=\"submit\" id=\"submit\" name=\"submit\" class=\"button-primary\" value=\"Show Results\"/></label>";
    echo "</form>";
    echo "</div>";
}

function belier_collab_collaborers_selector(): void
{
    // Declare the container for our form and declare our form header.
    // That way we can read from this form.
    echo "<div class=\"wrap\">";
    echo "<h3>WARNING, as of now, this form is <strong>READ-WRITE</strong>. Please let me know if this form should be made Write-Once, Read-Many</h3>";
    echo "<form name=\"author-form\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">";

    /*
    ===========================================================================
	    				    CO-AUTHOR DISPLAYING LOGIC 
    ===========================================================================

    Select the image to select which post to edit later.
    */
    // Just in case if WP is not loaded
    require_once('./wp-load.php');
    // Fulfills the requirement to allow us to use dbDelta() function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_maintenance.php');
    global $wpdb;
    $analytics_table = "{$wpdb->prefix}belier_post_analytics";
    echo "<label for=\"collab_img_file\" class=\"collab_img_file\">Chosen Image</label> 
    <input type=\"text\" id=\"collab_img_file\" value=\"{$_POST['form_post_image']}\" readonly/>
    ";
    // Just making sure. I could not trust pesky users to try and modify the value
    // To give collaborators to images that should not have them
    $image = $_POST['form_post_image'];
    // Preload collaborers if there happens to be any
    $preload_collab_sql = "SELECT collabs
            FROM " . $analytics_table . "
            WHERE CHAR_LENGTH(collabs)>2
            AND post_image = '" . $image . "'
            LIMIT 5";
    // Create empty string array
    $collab_results = array("", "", "");
    if (count($wpdb->get_results($preload_collab_sql)) > 0) {
        // Replace the empty string with an array of strings, one for each collaborer
        $collab_results = explode(" ", $wpdb->get_results($preload_collab_sql)[0]);
    }
    //Three Boxes for up to three collaborators
    echo "<label for=\"collab_one\" class=\"collab_one\">Collaborer 1</label>";
    echo "<input type=\"text\" id=\"collab_one\" name=\"collab_one\" value=\"" . $collab_results[0] . "\"/>";
    echo "<label for=\"collab_two\" class=\"collab_two\">Collaborer 2</label>";
    echo "<input type=\"text\" id=\"collab_two\" name=\"collab_two\" value=\"" . $collab_results[1] . "\"/>";
    echo "<label for=\"collab_three\" class=\"collab_three\">Collaborer 3</label>";
    echo "<input type=\"text\" id=\"collab_three\" name=\"collab_three\" value=\"" . $collab_results[2] . "\"/>";
    echo "<label for=\"ConfirmDatabaseWrite\" class=\"ConfirmDatabaseWrite\">Confirm Writing to Database?</label>";
    echo "<input type=\"checkbox\" name=\"ConfirmDatabaseWrite\" id=\"ConfirmDatabaseWrite\" value=\"Yes\" required />";
    //Submit Button.
    echo "<label for=\"submit\" class=\"submit\">
	<input type=\"submit\" id=\"submit\" name=\"submit\" class=\"button-primary\" value=\"Write to Database\"/></label>";
    echo "</form>";
    echo "</div>";

    /*
    ===========================================================================
	    				    CO-AUTHOR INSERTING LOGIC 
    ===========================================================================

    Inserts our Co-Authors into the DB as a comma separated string
    */
    belier_is_set($_POST['form_post_image']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (belier_is_set($image) === true) && (isset($_POST['ConfirmDatabaseWrite']) || $_POST['ConfirmDatabaseWrite'] == 'Yes')) {
        $collab_string = "";
        if (belier_is_set($_POST['collab_one']) == true) {
            $collab_string = $_POST['collab_one'];
        }
        if (belier_is_set($_POST['collab_two']) == true) {
            $collab_string .= ' ' . $_POST['collab_two'];
        }
        if (belier_is_set($_POST['collab_three']) == true) {
            $collab_string .= ' ' . $_POST['collab_three'];
        }
        $collab_sql = "UPDATE " . $analytics_table .
            "SET collabs ='" . $collab_string . "'" .
            "WHERE post_image ='" . $image . "';";

        $wpdb->query($wpdb->prepare($collab_sql));
    }
}

?>