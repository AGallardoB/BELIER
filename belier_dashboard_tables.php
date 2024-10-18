<?php
/*
===========================================================================
===========================================================================
===========================================================================
				    THE DASHBOARD DB TABLE SUB-MODULE
                            Release Candidate 1
===========================================================================
===========================================================================
===========================================================================

    Render any table on the dashboard, provided valid headers and SQL syntax
*/
function belier_dashboard_render_table($query_headers, $sql): void
{
    global $wpdb;
    // Turn our SQL syntax into an usable array with it's results.
    $query_results = $wpdb->get_results($sql);
    //echo "<pre>";
    //print_r($query_results);
    //echo "<br>";
    if (count($query_results) > 0) {
        echo " <div class=\"wrap\"> <table class=\"widefat fixed\" cellspacing=\"0\"> <thead>";
        // Write all of the items of the query_headers array and destroy the variables after use.
        foreach ($query_headers as $current_header) {
            echo "<th><strong>" . $current_header . "</strong></th>";
        }
        unset ($current_header);
        unset ($query_headers);
        // Continue rendering the table.
        echo "</thead> <tbody>";
        foreach ($query_results as $q_index => $row) {
            // The funkier alternate row generator... Print each class of TR if the current query index is divisible by 2
            echo ($q_index % 2) ? "<tr class='alternate'>" : "<tr>";
            // Insert the content of each field in it's respective TR
            foreach ($row as $field) {
                echo "<td>" . $field . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody> </table> </div>";
    } // Throw warning, if there's no results
    else {
        echo " <h2><strong> No results found.</strong></h2> ";
    }
}

?>