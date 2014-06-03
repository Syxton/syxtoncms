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

echo '</body></html>';

function view_ipaper(){
	global $MYVARS, $CFG, $USER;
    
    $doc_url = trim(base64_decode($MYVARS->GET["doc_url"]));
    echo '<iframe src="http://docs.google.com/viewer?url='.urlencode($doc_url).'&amp;embedded=true" scrolling="no" style="margin-right:auto;margin-left:auto;border:none;width:99%;height:99%;overflow:hidden;">
		Your browser does not support inline frames or is currently configured
		not to display inline frames.
		</iframe> ';
}
?>