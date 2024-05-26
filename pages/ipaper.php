<?php
/***************************************************************************
* ipaper.php - http://www.ajaxdocumentviewer.com/ link page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.6
***************************************************************************/

include('header.php');

callfunction();

echo fetch_template("tmp/page.template", "end_of_page_template");

function view_ipaper() {
	$url = clean_myvar_req("doc_url", "string");
	echo fill_template("tmp/ipaper.template", "view_ipaper_template", false, ["doc_url" => urlencode(trim(base64_decode($url)))]);
}
?>