<?php
/***************************************************************************
* ipaper.php - http://www.ajaxdocumentviewer.com/ link page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/5/2014
* Revision: 0.1.6
***************************************************************************/

include('header.php');

callfunction();

echo template_use("templates/page.template", array(), "end_of_page_template");

function view_ipaper() {
	global $MYVARS;
    echo template_use("templates/ipaper.template", array("doc_url" => trim(base64_decode($MYVARS->GET["doc_url"]))), "view_ipaper_template");
}
?>
