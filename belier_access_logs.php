<?php

/*
===========================================================================
===========================================================================
===========================================================================
					      ACCESS LOGS SUB-MODULE
						        Version  1
===========================================================================
===========================================================================
===========================================================================

Very simple dashboard page. Select a range of dates from a form, and display
all the image accesses logged for that interval. It does what it has to do
and it does it fairly well, if i say so myself.
===========================================================================
					 			TO-DO
===========================================================================

- Fix formatting woes.
- Improve the UX
- Implement an error notices function.
*/

/*
===========================================================================
					    	MAIN FUNCTION		
===========================================================================

	The function that glues every other function together. Outside of it's glue logic
	features, it performs form validation.
*/

function belier_access_logs(): void
{
    // Holdover from previous instance of this functionality. Checks for non-admin users.
    // Safety braces are always welcome.
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    // Create the structure to contain the form fields for the following query
    // and create an empty query, out of habit, variables need to be initialized somewhere.
    $analytics_query = "";
    $form_fields = array(
        "start_date" => '',
        "end_date" => '',
        "username" => ''
    );
    // Create our default start and end dates. Starting one month ago at 00:00, and ending
    // at 23:59:59 of today.
    $default_start_date = new DateTime('midnight 1 month ago');
    $default_start_date = $default_start_date->format('Y-m-d H:i:s');
    $default_end_date = new DateTime('tomorrow - 1 second');
    $default_end_date = $default_end_date->format('Y-m-d H:i:s');

    // Call the form rendering function.
    access_render_form();
    // Actions to perform after the form has been submitted.
    // This also helps preventing an SQL query being generated upon clicking
    // the dashboard link.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // kill old labels. Unlikely to even work since all traces of the old error-notices function
        // have been removed.
        echo " <script> document.getElementById(no_data_notice).innerHTML = \"\"; </script> ";
        // Fill the array with the form data.
        $form_fields['start_date'] = $_POST['form_start_date'];
        $form_fields['end_date'] = $_POST['form_end_date'];
        $form_fields['username'] = sanitize_user($_POST['form_username']);
        // Setup for file validation. Create our fail condition inmediately.
        $dates_not_filled = true;
        // If the dates have been successfully filled.
        if (($_POST['form_start_date'] != "") && ($_POST['form_end_date'] != "")) {
            // Set our fail condition to false. 
            $dates_not_filled = false;
            // Add the form fields and append the start and end hours
            $form_fields['start_date'] = $form_fields['start_date'] . " 00:00:00";
            $form_fields['end_date'] = $form_fields['end_date'] . " 23:59:59";
        } else {
            // Otherwise, add the default dates.
            $form_fields['start_date'] = $default_start_date;
            $form_fields['end_date'] = $default_end_date;
        }
        // Call our SQL query generator with our date array.
        $results = generate_sql_query($form_fields);
        // Prepare our table headers to send to our generic table renderer
        $headers = array(
            'Post Viewed', 'Date Viewed', 'Username', 'IP Address'
        );
        // Evaluate the table renderer once. Just in case we could run into a race condition
        // Where it could potentially be evaluated twice.
        require_once('belier_dashboard_tables.php');
        // Generate a table with our headers and results.
        belier_dashboard_render_table($headers, $results);
        unset($results);
        unset($headers);
    }
}

/*
===========================================================================
					        SQL QUERY GENERATOR		
===========================================================================

	Despite it not actually performing any queries, it'll appropiately
    validate the received fields, and from those, generate the specified
    query as needed, which will be ultimately rendered alongside the table.
*/

function generate_sql_query($form_fields): string
{
    // Importing the global database handler and our work table.
    global $wpdb;
    $analytics_table = $wpdb->prefix . "belier_post_analytics";
    /*
    Here's a refresher of the table we're going to query.
        date_viewed DATETIME NOT NULL,
		ip_address VARCHAR(256) NOT NULL,
		username VARCHAR(128) NOT NULL,
		post_image VARCHAR(256) NOT NULL,
		post_id BIGINT UNSIGNED NOT NULL,
		authors VARCHAR(256) NOT NULL,
		PRIMARY KEY (date_viewed, ip_address, username)
    */
    /*
        We're only interested on the date viewed, the IP, the username
        and the associated post image so we can grab its attatchment.
        We'll use the dates from the form. be either the default dates
        or user-input dates. This function doesn't know the difference.
    */
    $sql = "SELECT post_image, date_viewed, username, ip_address
        FROM {$analytics_table}
        WHERE date_viewed
        BETWEEN '{$form_fields['start_date']}'
        AND '{$form_fields['end_date']}' ";
    // Rudimentary validation for the form's username field
    if ((isset($form_fields['username']) && (strlen(trim($form_fields['username'])) > 0)) && !($form_fields['username'] == "''")) {
        // if a name has been inserted and exists on the database. Query using that name.
        if (username_exists($form_fields['username'])) {
            $sql .= "AND username = {$form_fields['username']} ";
        } // if a name has been inserted, but does not exist. Generate an error.
        else {
            echo " <div id='no_data_notice' class='notice notice-error is-dismissible inline'> <p> <strong> No username found on users database. </strong> </p> </div> ";
        }
    }

    $sql .= "ORDER BY date_viewed DESC;";

    // Debug printout of the query
    echo " <div id='no_data_notice' class='notice notice-info is-dismissible inline'> <p> <strong> SQL Query: $sql </strong> </p> </div> ";
    // Return the SQL query
    return $sql;
}

/*
===========================================================================
					        FORM RENDERER		
===========================================================================

	A simple form. Three fields. Start Date, End Date, User. Does what it
    says on the tin.
*/

function access_render_form(): void
{
    // Get placeholder dates with a slightly different format
    // For the users. We show whole days in the form, but for our database
    // We check from 00:00 from the selected start day, up to 23:59:59 of
    // the selected end day.
    $default_start_date = new DateTime('midnight 1 month ago');
    $default_start_date = $default_start_date->format('Y-m-d');
    $default_end_date = new DateTime('tomorrow - 1 second');
    $default_end_date = $default_end_date->format('Y-m-d');
    // Try to style the whole form in one line.
    echo " <style> form{ display: inline; } form input { text-align: center; margin-left: 0.25%; margin-right: 1%; } input.settings { text-align: left; } </style> ";
    // The actual page header.
    echo "<div class=\"wrap\"> <h2>Access Log Viewer</h2> </div> <div class=\"wrap\"> <br>";
    // The form declaration.
    echo "<form name=\"log-dates\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">";
    // The date picker for our start date, with convoluted built-in validation.
    // "date" inputs exist and are friendlier to use, but they're a nightmare to format
    // in a database-friendly way.
    echo "<label for=\"form_start_date\">Start Date</label>
        <input type=\"text\" id=\"form_start_date\" name=\"form_start_date\" size=\"8\"
        pattern=\"(?:19|20|21)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))\"
        value=\"";
    if (isset($_POST['form_start_date'])) {
        echo $_POST['form_start_date'];
    }
    echo "\" placeholder=\"{$default_start_date}\" />";
    // The date picker for our end date. Same situation as the start date field.
    echo "<label for=\"form_end_date\">End Date</label>
        <input type=\"text\" id=\"form_end_date\" name=\"form_end_date\" size=\"8\"
        pattern=\"(?:19|20|21)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))\"
        value=\"";
    if (isset($_POST['form_end_date'])) {
        echo $_POST['form_end_date'];
    }
    echo "\" placeholder=\"{$default_end_date}\" />";
    // 2023-Sept-07
    // The username text field. This one uses the fancy hybrid text box / dropdown thing. This is huge.
    echo "<label for=\"form_username\" class=\"settings\">User Name</label>";
    echo "<input list=\"users\" type=\"text\" id=\"form_username\" value=\"\" name=\"form_username\" size=\"20\" class=\"settings\" />";
    echo "<datalist id=\"users\">";
    $users = get_users();
    // Iterate through all the objects as entries on the dropdown's list
    foreach ($users as $current_user) {
        echo "<option value='" . esc_html($current_user->user_login) . "'>" . esc_html($current_user->user_login) . "</option>";
    }
    echo "</datalist>";
    // Close the select.
    echo " </select>";
    // The submit button
    echo "<label for=\"submit\" class=\"submit\">
        <input type=\"submit\" id=\"submit\" name=\"submit\" class=\"button-primary\" value=\"Show Results\"/>
        </label> </form> <br> ";
    echo "<hr> </div>";
}

?>