<?php
/**
 * Core
 * All includes and config
 */

/**
 * Config
 */
$base_url = "http://" . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
define("SYSTEM_PATH", $base_url); // TODO: is this needed?
define('DS', '/'); // TODO: is this needed?


// Load Helper
include_once "yui_helper.php";
?>
