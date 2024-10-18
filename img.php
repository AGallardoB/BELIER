<?php
//The shim for the shim

error_reporting(E_ALL);
ini_set("display_errors", 1);
// Load the WP constants so we can work on everything properly.
if (!defined('ABSPATH')) {
// Load the WP env if that is not the case.
require_once('../../wp-load.php');
}
require_once(WP_PLUGIN_DIR . '/BELIER' . '/belier_image_display.php');
?>