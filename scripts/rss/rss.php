<?php
if (!isset($CFG)) include('../../config.php');
include_once($CFG->dirroot . '/lib/header.php');
include_once($CFG->dirroot . '/lib/rsslib.php');

collect_vars();
header('Content-Type: application/xml; charset=utf-8');
echo preg_replace('/\s+/S', " ", get_rss());
?>