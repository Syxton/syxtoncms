<?php
if(!isset($CFG)) include('../../config.php');
include_once($CFG->dirroot . '/lib/header.php');
include_once($CFG->dirroot . '/lib/rsslib.php');

//Retrieve from Javascript
if(empty($MYVARS)){ $MYVARS = new \stdClass; }
$MYVARS->GET = $_GET;

echo header("Content-Type: application/xml; charset=ISO-8859-1");
//echo '<code>';
echo get_rss();
//echo '</code>';

?>