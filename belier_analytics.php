<?php
/* 
===========================================================================
===========================================================================
===========================================================================
					    SITE ANALYTICS SUB-MODULE
						   (Functional Beta 3)
===========================================================================
===========================================================================
===========================================================================


*/

/*
Prohibit non-posters and non admins from accesing this page.
Sort of redundant. Already checked on belier.php
*/

/*
===========================================================================
					BELIER ANALYTICS MAIN FUNCTION		
===========================================================================

The main function here. As with it's other data-showing siblings, this main
function handles all of the ancillary logic to tie the form and the tables
together

*/
function belier_analytics()
{
    require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_maintenance.php');
    if ((!current_user_can('manage_options')) || (!current_user_can('publish_posts'))) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    analytics_render_form();
    //Actions to take place upon pressing Submit.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        /*
        $analytics_table, array (
                    'date_viewed' => $date_viewed,
                    'ip_address'  => $ip_address,
                    'username' 	  => $username,
                    'post_image'  => $post_image,
                    'post_id' 	  => $post_id,
                    'author' 	  => $author,
                )
        */
        $analytics_fields = array(
            "date_start" => '',
            "date_end" => '',
            "query_type" => '',
        );
        require_once('belier_dashboard_tables.php');
        // Fill the adequate start and end dates.
        // This probably could be a case statement. But i'm unsure if that works
        // by grabbing from POST.

        // Set up Cutoff Year

        if (isset($_POST['CheckWithCutOff'])) {
            $cutoff_year = (intval(date('Y'))) - 3;
        } else {
            $cutoff_year = 0;
        }

        // Quarter 1 begis on Feb 1st, Ends on midnight of May 1st.
        if ($_POST['form_quarter'] == 'quarter_1') {
            $analytics_fields['date_start'] = ($_POST['form_year'] . "-02-01 00:00");
            $analytics_fields['date_end'] = ($_POST['form_year'] . "-04-30 23:59");
        }
        // Quarter 2 begis on May 1st, Ends on midnight of Aug 1st.
        if ($_POST['form_quarter'] == 'quarter_2') {
            $analytics_fields['date_start'] = ($_POST['form_year'] . "-05-01 00:00");
            $analytics_fields['date_end'] = ($_POST['form_year'] . "-07-31 23:59");
        }
        // Quarter 3 begis on Aug 1st, Ends on midnight of Nov 1st.
        if ($_POST['form_quarter'] == 'quarter_3') {
            $analytics_fields['date_start'] = ($_POST['form_year'] . "-08-01 00:00");
            $analytics_fields['date_end'] = ($_POST['form_year'] . "-10-31 23:59");
        }
        // Quarter 4 begis on Nov 1st, Ends on midnight of January 31th.
        // Why'd they make it so the last quarter crosses years is a decision lost to time.
        if ($_POST['form_quarter'] == 'quarter_4') {
            $analytics_fields['date_start'] = ($_POST['form_year'] . "-11-01 00:00");
            $analytics_fields['date_end'] = (($_POST['form_year'] + 1) . "-01-31 23:59");
        }
        $analytics_fields['query_type'] = $_POST['form_query_type'];
        // Render the result tables depending on our selected type of query
        // Render only the deafult option (the one seen by artists), Author Totals.
        $post_headers = array(
            'Post Image', 'Author', 'Views'
        );
        $author_headers = array(
            'Author', 'Total Unique Views', 'Percentage of Total'
        );

        if ($analytics_fields['query_type'] == 'query_author_totals') {
            $authors_results = analytics_authors_results($analytics_fields, false);
            //analytics_render_authors_table($authors_results, $analytics_fields);
            echo "<h2>Author totals for period spanning " . $analytics_fields['date_start'] . " to " . $analytics_fields['date_end'] . "</h2>";
            belier_dashboard_render_table($author_headers, $authors_results);
            //unset($authors_results);
            //unset($author_headers);
        }
        // Render only the Post Totals, used for other analytics.
        if ($analytics_fields['query_type'] == 'query_post_totals') {
            $quarter_results = analytics_quarter_results($analytics_fields);
            //analytics_render_posts_table($quarter_results, $analytics_fields);
            echo "<h2>Post totals for period spanning " . $analytics_fields['date_start'] . " to " . $analytics_fields['date_end'] . "</h2>";
            belier_dashboard_render_table($post_headers, $quarter_results);
            //unset($quarter_results);
            //unset($post_headers);
        }
        // Render both, mimicking the previous program behaviour at a fraction of the cost.
        if ($analytics_fields['query_type'] == 'query_everything') {
            /*
                OK so you are going to ask yourself what the fuck is the boolean for.
                So at some point you decided that because dividing the collabs stuff into
                individual authors with their percentages was going to be a pain in the ass.

                You'd instead went on and simulate the structure of the results of a query that
                would give you what you want without needing to do extra maths during data
                acquisition. That's fine and all, but belier_dashboard_render_table does not
                support your real-queries-that-are-just-like-the-real-thing-but-you-divided-
                collabs-and-authors-into-discrete-authors-with-divided-percentages.

                So you got to go to belier_dashboard_tables and add some logic for it to
                differenciate between a strinng and an array. And because you did never take
                this into account because you were learning and PHP is weakly typed now you
                need to deal with the consequences of your actions.

                See you in 6 months when i'm all but extinct and have no memories of this
                commit.
            */
            $authors_results = analytics_authors_results($analytics_fields, false);
            $quarter_results = analytics_quarter_results($analytics_fields);
            echo "<h2>Full Data for period spanning " . $analytics_fields['date_start'] . " to " . $analytics_fields['date_end'] . "</h2>";
            belier_dashboard_render_table($author_headers, $authors_results);
            //unset($authors_results);
            //unset($author_headers);
            belier_dashboard_render_table($post_headers, $quarter_results);
            //unset($quarter_results);
            //unset($post_headers);
            //analytics_render_authors_table($authors_results, $analytics_fields);
            //analytics_render_posts_table($quarter_results, $analytics_fields);
        }
    }
    unset($authors_results);
    unset($author_headers);
    unset($quarter_results);
    unset($post_headers);
}

/*
===========================================================================
						ANALYTICS_RENDER_FORM		
===========================================================================

	You have been on this place before. This will render the form we'll use
	to get our queries done.
*/
function analytics_render_form()
{
    // The CSS Styles, just in case
    echo " <style>
	form{ 
		 display: inline-block;
	} 
	</style>
	";
    // The actual page header.
    $current_year = date('Y');
    $current_month = date('m');
    // A convoluted way of auto-selecting the current quarter in the form.
    // Saves two clicks on the user, so it's worth it.
    $sel_q1 = "";
    $sel_q2 = "";
    $sel_q3 = "";
    $sel_q4 = "";
    // Select default quarter as Q4 if before february
    if (($current_month >= 11) || ($current_month < 2)) {
        $sel_q4 = "selected";
    }
    // Select default quarter as Q1 if before may
    if (($current_month >= 2) && ($current_month < 5)) {
        $sel_q1 = "selected";
    }
    // Select default quarter as Q2 if before august
    if (($current_month >= 5) && ($current_month < 8)) {
        $sel_q2 = "selected";
    }
    // Select default quarter as Q3 if before november
    if (($current_month >= 8) && ($current_month < 11)) {
        $sel_q3 = "selected";
    }

    // Create the structure of the form as well as the title.
    // Based on which of the 4 quarters got it's variable set up as "selected", the
    // current quarter will be selected on the list. It should make the default choice
    // of the current quarter easier.
    echo "	
	<div class=\"wrap\"> 
		<h2>Access Log Viewer</h2>
	</div> 
	<div class=\"wrap\"> 
		<br>
		<form name=\"viewership-form\" class=\"analytics\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">
		<select id=\"form_quarter\" name=\"form_quarter\">
			<option value=\"quarter_1\" " . $sel_q1 . ">Quarter 1: (February - May)</option>
			<option value=\"quarter_2\" " . $sel_q2 . ">Quarter 2: (May - August)</option>
			<option value=\"quarter_3\" " . $sel_q3 . ">Quarter 3: (August - November)</option>
			<option value=\"quarter_4\" " . $sel_q4 . ">Quarter 3: (November - February)</option>
		</select>

		<select id=\"form_year\" name=\"form_year\">
		";
    for ($target_year = $current_year; $target_year >= ($current_year - 5); $target_year -= 1) {
        // in-place selection of previous year, for those months after November, but before February rolls around
        echo "<option value=\"" . $target_year . "\" ";
        if (($current_month < 2) && ($target_year == ($current_year - 1))) {
            echo "selected";
        }
        echo ">" . $target_year . "</option>";
    }
    echo "
		</select>
		<select id=\"form_query_type\" name=\"form_query_type\" class=\"settings\">
		<option value=\"query_author_totals\" selected>Author Totals</option>
		<option value=\"query_post_totals\">Post Totals</option>
		<option value=\"query_everything\">Everything</option>
		</select>
        <input type=\"checkbox\" id=\"CheckWithCutOff\" name=\"CheckWithCutOff\" value=\"1\">
        <label for=\"CheckWithCutOff\"> Apply Cut-Off (-3 years)</label><br>
		<label for=\"submit\" class=\"submit\"> <input type=\"submit\" id=\"submit\" name=\"submit\" class=\"button-primary\" value=\"Show Results\"/> </label>
		</form> 
	</div>
	<br> 
	<hr>
	";
}

/*
===========================================================================
						THE POST TOTALS SQL FORMATTER		
===========================================================================

	Based on what the form has received, it'll generate a query to check
	which posts have been seen the most.
*/

function analytics_quarter_results($analytics_fields)
{
    // This is our analytics table fields.
    /*	date_viewed  DATETIME NOT NULL,
        ip_address   VARCHAR(256) NOT NULL,
        username     VARCHAR(128) NOT NULL,
        post_image   VARCHAR(256) NOT NULL,
        -- NOT ANYMORE -- post_id      BIGINT UNSIGNED NOT NULL,
        -- NOT ANYMORE -- post_year    SMALLINT UNSIGNED NOT NULL,
        author       VARCHAR(128) NOT NULL,
        collabs      VARCHAR(224),
        PRIMARY KEY  (date_viewed, ip_address, username) */

    global $wpdb;
    $analytics_table = $wpdb->prefix . 'belier_post_analytics';

    $query = "SELECT post_image, author, count(post_image) AS views
	FROM {$analytics_table}
	WHERE date_viewed > '{$analytics_fields['date_start']}' 
	AND date_viewed < '{$analytics_fields['date_end']}' 
	GROUP BY post_image; ";
    return $query;

}

/*
===========================================================================
						THE AUTHOR TOTALS SQL FORMATTER		
===========================================================================

	Based on what the form has received, it'll generate a query to check
	which authors have been seen the most.

	Take into account this does not take into account collabs at the SQL level.
	It might probably never will. It's easier for me to divide a collab on the
	table directly using PHP. And i still gotta implement the collab marker.
*/

function analytics_authors_results(&$analytics_fields, $quarter_analytics_as_array)
{
    // This is our analytics table fields.
    /*	date_viewed  DATETIME NOT NULL,
        ip_address   VARCHAR(256) NOT NULL,
        username     VARCHAR(128) NOT NULL,
        post_image   VARCHAR(256) NOT NULL,
        -- NOT ANYMORE -- post_id      BIGINT UNSIGNED NOT NULL,
        -- NOT ANYMORE -- post_year    SMALLINT UNSIGNED NOT NULL,
        author       VARCHAR(128) NOT NULL,
        collabs      VARCHAR(224),
        PRIMARY KEY  (date_viewed, ip_address, username) */

    // Make sure only we're looking at data from the last few years, to avoid some
    // Evergreen classics affecting results.
    //$cutoff_year = (intval(date('Y'))) - 3;
    if (isset($_POST['CheckWithCutOff'])) {
        $cutoff_year = (intval(date('Y'))) - 3;
    } else {
        $cutoff_year = 0;
    }
    global $wpdb;
    $analytics_table = $wpdb->prefix . "belier_post_analytics";
    /*
        The query below seems to be working perfectly. Another, slower, way of getting the results is:

        SELECT author,
        COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wp_post_analytics)
        AS 'percentage of views'
        FROM wp_post_analytics
        GROUP BY author;

        It should be noted. It needs to be translated from SQL back to PHP->WP->WPDB handler
    */

    // old behaviour was: WHERE date_viewed > '{$cutoff_year}'

    if (belier_is_set($quarter_analytics_as_array) === false) {
        // Let's contemplate selecting the quarter analytics wether or not they obligue to the cutoff year
        $query = "SELECT author AS 'author', count(*) AS 'views', count(*) * 100.0 / sum(count(*)) over() AS 'fraction'
		FROM {$analytics_table}";
        if ($cutoff_year==0){
            $query.= "WHERE date_viewed > '{$analytics_fields['date_start']}' 
	        AND date_viewed < '{$analytics_fields['date_end']}' ";
        }else{
            $query.= "WHERE date_viewed > '{$analytics_fields['date_start']}' 
	        AND date_viewed < '{$analytics_fields['date_end']}' 
            AND CAST(SUBSTRING(post_image, 1, 4) AS UNSIGNED)  > '{$cutoff_year}'";
        }
		$query.= "GROUP BY author
		ORDER BY fraction DESC; ";
        return $query;
    } else {
        // Let's try making this massive-ass hack
        // In essence we do the query, carry it out and transform it
        // Into an array that we can plug somewhere else.
        $query = "SELECT collabs AS 'collabs', author AS 'author', count(*) AS 'views', count(*) * 100.0 / sum(count(*)) over() AS 'fraction'
		FROM {$analytics_table}";
        if ($cutoff_year==0){
            $query.= "WHERE date_viewed > '{$analytics_fields['date_start']}' 
	        AND date_viewed < '{$analytics_fields['date_end']}' ";
        }else{
            $query.= "WHERE date_viewed > '{$analytics_fields['date_start']}' 
	        AND date_viewed < '{$analytics_fields['date_end']}' 
            AND CAST(SUBSTRING(post_image, 1, 4) AS UNSIGNED)  > '{$cutoff_year}'";
        }
		$query.= "GROUP BY author
		ORDER BY fraction DESC; ";
        //get the results array.
        $query_results = $wpdb->get_results($wpdb->prepare($query), 'ARRAY_A');
        $pre_prepared_array = array();
        /*
        From WordPress' Documentation: This will result a 3 dimensional array. It contains the array
        of rows from the SQL result, starting from zero. the value for those keys is another array, containing the results
        of the row itself, one key by column.

        As a PHP Array it would be something like:
            $query_results=array(
                'row0' => array(
                    'collabs' => 'fieldA',
                    'author' => 'fieldB',
                    'views' => 'fieldC',
                    'fraction' => 'fieldD',
                ),
                'row1' => array(
                    'collabs' => 'fieldA',
                    'author' => 'fieldB',
                    'views' => 'fieldC',
                    'fraction' => 'fieldD',
                ),
                [...]
            );
        */
        // If we got more than one result
        if (count($query_results) > 0) {
            // Start looping through the queried results
            foreach ($query_results as $q_index => $current_row) {
                // for each one of the rows on the index, create an empty array in that position
                //$pre_prepared_array[$q_index]=array();
                foreach ($current_row as $key => $value) {
                    // Check if collabs is empty, if it is, do nothing as there are no collabs.
                    if (belier_is_set($key['collabs']) !== false) {
                        // our prepeared array at the current index position will just get the author itself.
                        // as well as everything else
                        // array_push($pre_prepared_array, (array_slice($current_row, 1)));
                        $pre_prepared_array[] = (array_slice($current_row, 1));
                    } else {
                        // get all the authors on a string
                        $all_authors = $key['author'] . " " . $key['collabs'];
                        // turn the string into an array, i swear this makes sense later
                        $array_authors = explode(' ', $all_authors);
                        // count the items in the new array. it's slower than substr_count, but will ensure
                        // i don't havee off-by-one errors
                        $number_of_authors = count($array_authors);
                        // NOW we do some of that weird juggling to get everything in place.
                        // Starting by running through each of the authors, which is why we needed the array
                        foreach ($array_authors as $current_author) {
                            // Another array, this time holding our modified values before pushing it to the proper array
                            $temp = array(
                                'author' => $current_author . " (As part of a collab)",
                                'views' => ($key['views'] / $number_of_authors),
                                'fraction' => ($key['fraction'] / $number_of_authors),
                            );
                            // Now we push the array into the array. This ought to work with luck
                            //array_push($pre_prepared_array, $temp);
                            $pre_prepared_array[] = $temp;
                        }
                    }
                }
            }
        }
        // free RAM by destroying the unused variables
        unset ($number_of_authors);
        unset ($temp);
        unset ($array_authors);
        unset ($all_authors);
        unset ($query_results);
        // return the array formated the way belier_dashboard_render_table likes it
        return ($pre_prepared_array);
    }
}

?>