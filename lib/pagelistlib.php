<?php
/***************************************************************************
* pagelistlib.php - Page list function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/16/2016
* Revision: 2.3.4
***************************************************************************/

if (!isset($LIBHEADER)){ include ('header.php'); }
$PAGELISTLIB = true;

//CONFIG VARIABLES

$MYVARS = new stdClass(); 
$MYVARS->search_perpage = 8;

function display_pagelist($pageid){
global $CFG, $USER, $ROLES, $PAGE, $STYLES;
	$preview = isset($STYLES->preview) ? true : false;
    if(!$pageid){ $pageid = $CFG->SITEID; }
	
    if(is_logged_in()){
        $pagename = get_css_box(stripslashes(get_db_field("name", "pages", "pageid=$pageid")), get_db_field("display_name", "roles", "roleid=" . get_user_role($USER->userid, $pageid)), get_button_layout("pagename", 1, $pageid),NULL,'pagename') . '<div style="padding:3px;"></div>';
        $pagelist = !is_siteadmin($USER->userid) ? get_pagelist($USER->userid) : "";
        $buttons = get_button_layout("pagelist", 1, $pageid);
        $pagelist .= '
        <span class="centered_span">
            '.make_modal_links(array("title"=> "Browse for Pages","path"=>$CFG->wwwroot."/pages/page.php?action=browse&amp;section=search&amp;userid=$USER->userid","iframe"=>"true","width"=>"640","height"=>"626")).'
		</span>';
        $pagelist .= get_page_links($pageid, $USER->userid);
        return $pagename . get_css_box('My Page List', $pagelist, $buttons, null, "pagelist", null, false, $preview);
    }else{
        $pagename = get_css_box(get_db_field("name", "pages", "pageid=$pageid"), get_db_field("display_name", "roles", "roleid=" . get_user_role($USER->userid, $pageid)),NULL,NULL,'pagename') . '<div style="padding:3px;"></div>';
        $pagelist = '
        <span class="centered_span">
            '.make_modal_links(array("title"=> "Browse for Pages","path"=>$CFG->wwwroot."/pages/page.php?action=browse","iframe"=>"true","width"=>"640","height"=>"626")).'
		</span>';
        $pagelist .= get_page_links($pageid);    
        return $pagename . get_css_box('My Page List', $pagelist, null, null, "pagelist", null, false, $preview);
    }
}

function get_pagelist($userid){
global $CFG, $ROLES, $USER;
    $roleid = get_user_role($userid, $CFG->SITEID);
    $SQL = "
	SELECT p.* 
	FROM pages p
		INNER JOIN roles_ability ry ON ry.roleid='$roleid' AND ry.ability='viewpages' AND allow='1' 
	WHERE
		(
			p.pageid IN (SELECT ra.pageid FROM roles_assignment ra WHERE ra.userid='$userid' AND ra.pageid=p.pageid AND confirm=0)
			OR
			p.pageid IN (SELECT rau.pageid FROM roles_ability_peruser rau WHERE rau.userid='$userid' AND rau.ability ='viewpages' AND allow='1')
		)
		AND p.pageid NOT IN (SELECT rau.pageid FROM roles_ability_peruser rau WHERE rau.userid='$userid' AND rau.ability ='viewpages' AND allow='0')
		AND p.pageid != " . $CFG->SITEID . "
		AND p.menu_page != '1'
	ORDER BY p.name";
    if($result = get_db_result($SQL)){ return format_pagelist($result);
    }else{  return ""; }
}

function format_pagelist($pageresults){
global $CFG, $USER, $PAGE;
    $returnme = "";
    if($pageresults){
        $returnme = '<select id="select_page" style="width:100%" onchange="go_to_page($(this).val());">';
        while($row = fetch_row($pageresults)){
            $selected = $PAGE->id == $row['pageid'] ? "selected" : ""; //Preselect page if you are there
            $returnme .= '<option value="' . $row['pageid'] . '" ' . $selected . '>' . $row['name'] . '</option>';
        }
        $returnme .= '</select>';
    }
    return $returnme;
}

function get_page_links($pageid, $userid = false){
global $CFG, $ROLES, $USER;
    $returnme = "";
    if($userid){
        if(is_siteadmin($userid)){
            $SQL = "
    		SELECT pl.* 
    		FROM pages_links pl
    		WHERE
    			pl.hostpageid=$pageid
    			AND pl.linkpageid != " . $CFG->SITEID . "
    			AND pl.linkpageid != " . $pageid . "
    		ORDER BY pl.sort";
        }else{
            $SQL = "
    		SELECT pl.* 
    		FROM pages_links pl
    		WHERE
    			(
    			pl.hostpageid=$pageid
    			AND pl.linkpageid != " . $CFG->SITEID . "
    			AND pl.linkpageid != " . $pageid . "
    			)
    			AND
    			(
    				(
    				pl.linkpageid IN (SELECT ras.pageid FROM roles_assignment ras WHERE ras.userid='$userid' AND ras.confirm='0' AND ras.roleid IN (SELECT ra.roleid FROM roles_ability ra WHERE ra.ability='viewpages' AND ra.allow='1'))
    				AND
    				pl.linkpageid NOT IN (SELECT rap.pageid FROM roles_ability_perpage rap WHERE rap.pageid=pl.linkpageid AND rap.allow=0 AND rap.roleid IN (SELECT ras.roleid FROM roles_assignment ras WHERE ras.userid='$userid' AND ras.pageid=pl.linkpageid AND ras.confirm=0))
    				AND
    				pl.linkpageid NOT IN (SELECT rau.pageid FROM roles_ability_peruser rau WHERE rau.userid='$userid' AND rau.ability ='viewpages' AND rau.allow='0')
    				)
    				OR
    				(
    				pl.linkpageid IN (SELECT p.pageid FROM pages p WHERE (p.siteviewable=1 OR p.opendoorpolicy=1))
    				AND
    				pl.linkpageid NOT IN (SELECT rap.pageid FROM roles_ability_perpage rap WHERE rap.pageid=pl.linkpageid AND rap.allow=0 AND rap.roleid IN (SELECT ras.roleid FROM roles_assignment ras WHERE ras.userid='$userid' AND ras.pageid=rap.pageid AND ras.confirm=0))
    				AND
    				pl.linkpageid NOT IN (SELECT rau.pageid FROM roles_ability_peruser rau WHERE rau.userid='$userid' AND rau.ability ='viewpages' AND rau.allow='0')
    				)
    				OR
    				pl.linkpageid IN (SELECT rau.pageid FROM roles_ability_peruser rau WHERE rau.userid='$userid' AND rau.ability ='viewpages' AND rau.allow='1')
    			)
    		ORDER BY pl.sort";
        }
    }else{
        $SQL = "
    	SELECT pl.* 
    	FROM pages_links pl
    	WHERE
    		pl.linkpageid IN (SELECT p.pageid FROM pages p WHERE p.pageid=pl.linkpageid AND siteviewable=1)
    		AND pl.hostpageid=$pageid 
    		AND pl.linkpageid != " . $CFG->SITEID . "
    		AND pl.linkpageid != " . $pageid . "
    	ORDER BY pl.sort";
    }
    if($result = get_db_result($SQL)){
        $filler = "";
        $returnme .= '<div id="page_links_div"><br /><div style="border: 1px solid gray; background-color:#EFEFEF;"><span style="line-height:2em; width:100%; display:block; background-color:#D1D7DC;"><b>&nbsp;Page Links</b></span><br />';
        while($page = fetch_row($result)){
            $filler .= '<div style="line-height:1.5em; padding:5px;" id="link_span_' . $page['linkid'] . '"><span style="width:100%;"><a style="vertical-align:middle;" href="' . $CFG->wwwroot . '/index.php?pageid=' . $page["linkpageid"] . '">' . $page["linkdisplay"] . '</a></span>';
	   		if(user_has_ability_in_page($userid, "editpage", $pageid)){ 
                $filler .= ' <a style="vertical-align:middle;" onclick="blur();" href="javascript:if(confirm(\'Are you sure you want to unlink this page?\')){ajaxapi(\'/ajax/page_ajax.php\',\'unlink_page\',\'&amp;pageid=' . $pageid . '&amp;linkid=' . $page['linkid'] . '\',function() { ajaxapi(\'/ajax/page_ajax.php\',\'refresh_page_links\',\'&amp;pageid=' . $pageid . '\',function() { simple_display(\'page_links_div\');});});}"><img src="' . $CFG->wwwroot . '/images/unlink.png" title="Unlink Page" alt="Unlink Page" /></a> &nbsp;';
            }
			$filler .= '</div>';
		}
        $returnme = $filler == "" ? "" : $returnme . $filler . '</div></div>';
    }
    return $returnme;
}

function pagelist_buttons($pageid, $featuretype, $featureid){
global $CFG, $USER, $PAGE;
    $returnme = "";
    if(user_has_ability_in_page($USER->userid, "createpage", $CFG->SITEID)){ 
        $returnme .= make_modal_links(array("title"=> "Create","path"=>$CFG->wwwroot."/pages/page.php?action=create_edit_page","refresh"=>"true","validate"=>"true","width"=>"640","height"=>"475","image"=>$CFG->wwwroot . "/images/add.png","class"=>"slide_menu_button"));
    }
    if(user_has_ability_in_page($USER->userid, "editpage", $pageid)){ 
        $returnme .= make_modal_links(array("title"=> "Create/Edit Page Links","path"=>$CFG->wwwroot."/pages/page.php?action=create_edit_links&amp;pageid=$pageid","refresh"=>"true","width"=>"600","height"=>"500","image"=>$CFG->wwwroot . "/images/link.gif","class"=>"slide_menu_button"));
    }
    return $returnme;
}

function pagename_buttons($pageid){
global $CFG, $USER, $PAGE;
    $returnme = "";
    if(user_has_ability_in_page($USER->userid, "editpage", $pageid)){ 
        $returnme .= make_modal_links(array("title"=> "Edit Page Settings","path"=>$CFG->wwwroot."/pages/page.php?action=create_edit_page&amp;pageid=$pageid","refresh"=>"true","validate"=>"true","width"=>"640","height"=>"475","image"=>$CFG->wwwroot . "/images/settings.png","class"=>"slide_menu_button"));
    }
	if(user_has_ability_in_page($USER->userid, "editpage", $pageid)){ 
        $returnme .= make_modal_links(array("title"=> "Edit Page Theme","path"=>$CFG->wwwroot."/pages/themes.php?action=change_theme&amp;pageid=$pageid&amp;feature=page","iframe"=>"true","refresh"=>"true","width"=>"640","height"=>"600","image"=>$CFG->wwwroot . "/images/themes.gif","class"=>"slide_menu_button"));
    }
	return $returnme;
}
?>