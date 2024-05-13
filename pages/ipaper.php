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

echo use_template("tmp/page.template", [], "end_of_page_template");

function view_ipaper() {
	global $MYVARS;
    $url = urlencode(trim(base64_decode($MYVARS->GET["doc_url"])));
    //echo $url;
    echo use_template("tmp/ipaper.template", ["doc_url" => $url], "view_ipaper_template");
}
?>