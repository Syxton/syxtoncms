<?php
/***************************************************************************
* page.php - Page relevent page file
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/25/2014
* Revision: 1.5.7
***************************************************************************/

include ('header.php');
echo '
	 <script type="text/javascript"> var dirfromroot = "' . $CFG->directory . '"; </script>
	 <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'ajax/siteajax.js"></script>
     <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'styles/styles_main.css" />
	 <input id="lasthint" type="hidden" />';

callfunction();

echo '</body></html>';

function browse(){
global $CFG, $MYVARS, $USER, $ROLES;
    $section = isset($MYVARS->GET["section"]) ? $MYVARS->GET["section"] : "search";
    echo '
	<script type="text/javascript" src="' . $CFG->wwwroot . '/scripts/ajaxtabs.js"></script>
	<body style="background-color:white;">
	<ul id="findpagetabs" class="shadetabs">';
    if(is_logged_in()){
        switch($section){
            case "search":
                echo '<li><a class="tablinks" href="page.php?action=browse_search" style="text-decoration: none;position: relative;z-index: 1;padding-top: 3px; padding-right: 7px; padding-left: 7px;margin-right: 3px;border-top: 1px solid #778;border-right: 1px solid #778;border-left: 1px solid #778;color: #2d2b2b;" rel="contentscontainer" class="selected" onmouseup="this.blur()">Search Pages</a></li>';
                //	echo '<li><a href="page.php?action=browse_categories" rel="contentscontainer" onmouseup="this.blur()">Categories</a></li>';
                //	echo '<li><a href="page.php?action=browse_keywords" rel="contentscontainer" onmouseup="this.blur()">Keywords</a></li>';
                echo '<li><a href="page.php?action=browse_users" style="text-decoration: none;position: relative;z-index: 1;padding-top: 3px; padding-right: 7px; padding-left: 7px;margin-right: 3px;border-top: 1px solid #778;border-right: 1px solid #778;border-left: 1px solid #778;color: #2d2b2b;" rel="contentscontainer" class="notselected" onmouseup="this.blur()">Search Members</a></li>';
                break;
            case "users":
                echo '<li><a href="page.php?action=browse_search" style="text-decoration: none;position: relative;z-index: 1;padding-top: 3px; padding-right: 7px; padding-left: 7px;margin-right: 3px;border-top: 1px solid #778;border-right: 1px solid #778;border-left: 1px solid #778;color: #2d2b2b;" rel="contentscontainer" class="notselected" onmouseup="this.blur()">Search Pages</a></li>';
                //	echo '<li><a href="page.php?action=browse_categories" rel="contentscontainer" onmouseup="this.blur()">Categories</a></li>';
                //	echo '<li><a href="page.php?action=browse_keywords" rel="contentscontainer" onmouseup="this.blur()">Keywords</a></li>';
                echo '<li><a href="page.php?action=browse_users" style="text-decoration: none;position: relative;z-index: 1;padding-top: 3px; padding-right: 7px; padding-left: 7px;margin-right: 3px;border-top: 1px solid #778;border-right: 1px solid #778;border-left: 1px solid #778;color: #2d2b2b;" rel="contentscontainer" class="selected" onmouseup="this.blur()">Search Members</a></li>';
                break;
        }
        echo '<div id="contentscontainer" style="border:1px solid gray;position:relative;height:470px;padding: 10px 10px 0px 10px;"></div></ul>';
    }else{
        echo '<li><a class="tablinks" href="page.php?action=browse_search" style="text-decoration: none;position: relative;z-index: 1;padding-top: 3px; padding-right: 7px; padding-left: 7px;margin-right: 3px;border-top: 1px solid #778;border-right: 1px solid #778;border-left: 1px solid #778;color: #2d2b2b;" rel="contentscontainer" class="selected" onmouseup="this.blur()">Search Pages</a></li>';
        //	echo '<li><a href="page.php?action=browse_categories" rel="contentscontainer" onmouseup="this.blur()">Categories</a></li>';
        //	echo '<li><a href="page.php?action=browse_keywords" rel="contentscontainer" onmouseup="this.blur()">Keywords</a></li>';
        //echo '<li><a href="page.php?action=browse_users" rel="contentscontainer" class="selected" onmouseup="this.blur()">Users</a></li>';
        echo '<div id="contentscontainer" style="border:1px solid gray;position:relative;height:470px;padding: 10px 10px 0px 10px"></div></ul>';
    }
    echo '<script type="text/javascript">
	var findmethods=new ddajaxtabs("findpagetabs", "contentscontainer")
	findmethods.setpersist(false)
	findmethods.setselectedClassTarget("link") //"link" or "linkparent"
	findmethods.init()
	</script>';
}

function browse_search(){
global $CFG, $MYVARS, $USER, $ROLES;
    echo '<form onsubmit="document.getElementById(\'loading_overlay_pagesearch\').style.visibility=\'visible\';ajaxapi(\'/ajax/page_ajax.php\',\'pagesearch\',\'&searchwords=\'+escape(document.getElementById(\'searchbox\').value),function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer_pagesearch\'); document.getElementById(\'loading_overlay_pagesearch\').style.visibility=\'hidden\'; }},true); return false;"><p>
		Search for pages by either their name, thier keywords, or their description.  If you have the ability to add it to your personal pagelist, you will see an <img src="' . $CFG->wwwroot . '/images/add.png" title="Add" alt="Add"> link to the right.  If you already have rights in that page you will see the <img src="' . $CFG->wwwroot . '/images/delete.png" title="Remove" alt="Remove"> link.  If you want to request access into a page, click the <img src="' . $CFG->wwwroot . '/images/mail.gif" title=Request" alt="Request"> link.
		</p>
		Search <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" />
		<br /><br /></form>'.make_search_box(false,"pagesearch");
}

function browse_users(){
global $CFG, $MYVARS, $USER, $ROLES;
    echo '<form onsubmit="document.getElementById(\'loading_overlay_usersearch\').style.visibility=\'visible\';ajaxapi(\'/ajax/page_ajax.php\',\'usersearch\',\'&searchwords=\'+escape(document.getElementById(\'searchbox\').value),function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer_usersearch\'); document.getElementById(\'loading_overlay_usersearch\').style.visibility=\'hidden\'; }},true); return false;"><p> 
		Search for users by either their name or thier email address.  If you want to invite a user into your page, click the <img src="' . $CFG->wwwroot . '/images/mail.gif" title=Request" alt="Request"> icon beside their name, then select the page you would like to invite them to.
		</p>
		Search <input type="text" id="searchbox" name="searchbox" />&nbsp;<input type="submit" value="Search" />
		<br /><br /></form>'.make_search_box(false,"usersearch");
}

function browse_categories(){
global $CFG, $MYVARS, $USER, $ROLES;
    $SQL = '
	SELECT * FROM pages p 
		WHERE 
		(
			(
			p.opendoorpolicy = 1
			OR
			p.siteviewable = 1
			)
		AND 
		menu_page != 1
		)
		AND
		p.pageid NOT IN (SELECT ra.pageid FROM roles_assignment ra WHERE userid=' . $USER->userid . ')
	';
    $pages = get_db_result($SQL);
    $returnme = '<div id="create_page_div">Select a page that you would like to add to your personal page list.<table style="width:100%"><tr><td style="vertical-align:top; text-align:right; width:50%;"><select name="select_page" id="select_page" style="width:100%; font-size:.75em;">';
    while ($row = fetch_row($pages)) {
        $SQL2 = "SELECT u.fname, u.lname FROM users u WHERE u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='" . $row['pageid'] . "' AND ra.roleid='" . $ROLES->creator . "')";
        $runby = "";
        $owner = get_db_row($SQL2);
        $runby = strlen($owner['fname']) > 0 ? " - Owner: " . $owner['fname'] . " " . $owner['lname'] : "";
        $returnme .= '<option value="' . $row['pageid'] . '" >' . $row['name'] . $runby . '</option>';
    }
    $returnme .= '</select></td><td style="vertical-align:top; text-align:left;"><input type="button" value="Add" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'subscribe\',\'&amp;pageid=\'+document.getElementById(\'select_page\').value,function(){ option_display(document.getElementById(\'select_page\').value,\'results\'); });"></td></tr></table><div id="results"></div></div>';
    echo $returnme;
}

function browse_keywords(){
    //	$SQL = '
    //	SELECT * FROM pages p
    //		WHERE
    //		(
    //			(
    //			p.opendoorpolicy = 1
    //			OR
    //			p.siteviewable = 1
    //			)
    //		AND
    //		menu_page != 1
    //		)
    //		AND
    //		p.pageid NOT IN (SELECT ra.pageid FROM roles_assignment ra WHERE userid='.$USER->userid.')
    //
    //	';
    //
    //	$pages = get_db_result($SQL);
    //
    //	$returnme = '<div id="create_page_div">Select a page that you would like to add to your personal page list.<table style="width:100%"><tr><td style="vertical-align:top; text-align:right; width:50%;"><select name="select_page" id="select_page" style="width:100%; font-size:.75em;">';
    //
    //	while($row = fetch_row($pages))
    //	{
    //		$SQL2 = "SELECT u.fname, u.lname FROM users u WHERE u.userid IN (SELECT ra.userid FROM roles_assignment ra WHERE ra.pageid='".$row['pageid']."' AND ra.roleid='".$ROLES->creator."')";
    //		$runby = "";
    //		$owner = get_db_row($SQL2);
    //		$runby = strlen($owner['fname']) > 0 ? " - Owner: " . $owner['fname'] . " " . $owner['lname'] : "";
    //		$returnme .= '<option value="' . $row['pageid'] . '" >' . $row['name'] . $runby . '</option>';
    //	}
    //	$returnme .= '</select></td><td style="vertical-align:top; text-align:left;"><input type="button" value="Add" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'subscribe\',\'&amp;pageid=\'+document.getElementById(\'select_page\').value,function(){ option_display(document.getElementById(\'select_page\').value,\'results\'); });"></td></tr></table><div id="results"></div></div>';
    //	echo $returnme;
}

function create_edit_page(){
global $CFG, $MYVARS, $ROLES, $USER;
    	
	if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }

    $admin = is_siteadmin($USER->userid) ? true : false;
    if(isset($MYVARS->GET["pageid"])){
        if(!user_has_ability_in_page($USER->userid, "editpage", $MYVARS->GET["pageid"])){
            echo get_error_message("generic_permissions");
            return;
        }
        $page = get_db_row("SELECT * FROM pages WHERE pageid=" . $MYVARS->GET["pageid"]);
        $name = stripslashes($page["name"]);
        $description = stripslashes($page["description"]);
        $keywords = stripslashes($page["keywords"]);
        $role_selected = $page["default_role"];
        $global_yes = $page["siteviewable"] != "0" ? "selected" : "";
        $global_no = $global_yes == "" ? "selected" : "";
        $open_yes = $page["opendoorpolicy"] != "0" ? "selected" : "";
        $open_no = $open_yes == "" ? "selected" : "";
        $menu_yes = $page["menu_page"] != "0" ? "selected" : "";
        $menu_no = $menu_yes == "" ? "selected" : "";
        $menu_page = $page["menu_page"];
        $hide_no = $hide_yes = "";
        if($page["menu_page"] != "0"){
            $hidefromvisitors = get_db_field("hidefromvisitors", "menus", "pageid=" . $MYVARS->GET["pageid"]);
            $hide_yes = $hidefromvisitors != "0" ? "selected" : "";
            $hide_no = $hide_yes == "" ? "selected" : "";
        }
    }else{
        if(!user_has_ability_in_page($USER->userid, "createpage", $CFG->SITEID)){
            echo get_error_message("generic_permissions");
            return;
        }
        
        $menu_no = $menu_yes = $hide_no = $hide_yes = $global_yes = $global_no = $open_yes = $open_no = $name = $description = $keywords = "";
        $role_selected = 4;
        $menu_page = 0;
        $hidefromvisitors = 0;
    }
    
    if (isset($MYVARS->GET["pageid"])){
    	echo create_validation_script("create_page_form" , 'ajaxapi(\'/ajax/page_ajax.php\',\'edit_page\',\'&name=\' + escape(document.getElementById(\'name\').value) + \'&description=\' + escape(document.getElementById(\'summary\').value) + \'&keywords=\' + escape(document.getElementById(\'keywords\').value) + \'&defaultrole=\' + document.getElementById(\'role_select\').value + \'&opendoor=\' + document.getElementById(\'opendoor\').value + \'&siteviewable=\' + document.getElementById(\'siteviewable\').value + \'&menu_page=\' + document.getElementById(\'menu_page\').value + \'&hidefromvisitors=\' + document.getElementById(\'hidefromvisitors\').value + \'&pageid=' . $MYVARS->GET["pageid"] . '\',function() { close_modal(); });');
    }else{
    	echo create_validation_script("create_page_form" , 'ajaxapi(\'/ajax/page_ajax.php\',\'create_page\',\'&name=\' + escape(document.getElementById(\'name\').value) + \'&description=\' + escape(document.getElementById(\'summary\').value) + \'&keywords=\' + document.getElementById(\'keywords\').value + \'&defaultrole=\' + document.getElementById(\'role_select\').value + \'&opendoor=\' + document.getElementById(\'opendoor\').value + \'&siteviewable=\' + document.getElementById(\'siteviewable\').value + \'&menu_page=\' + document.getElementById(\'menu_page\').value + \'&hidefromvisitors=\' + document.getElementById(\'hidefromvisitors\').value,function() { create_page_display();});');	
    }
  
    echo '
    <div class="formDiv" id="create_page_div">
    	<form id="create_page_form">
    		<fieldset class="formContainer">
    			<div class="rowContainer">
    				<label for="name">Page Name</label><input type="text" id="name" name="name" data-rule-required="true" value="'.$name.'" /><div class="tooltipContainer info">'.get_help("input_page_name").'</div><br />
    			</div>
    			<div class="rowContainer">
    				<label for="keywords">Page Keywords</label><textarea id="keywords" name="keywords" cols="28" rows="2" data-rule-required="true" >' . $keywords . '</textarea><div class="tooltipContainer info">'.get_help("input_page_tags").'</div><br />
    			</div>			
    			<div class="rowContainer">
    				<label for="description">Page Description</label><div style="display:inline-block">
                    <textarea id="summary" name="summary" cols="28" rows="4" data-rule-required="true">' . stripslashes($description) . '</textarea>
                    </div><div class="tooltipContainer info">'.get_help("input_page_summary").'</div><br />
    			</div>
    			<div class="rowContainer">
    				<label for="role_select">Default Role</label>';
    				$SQL = 'SELECT * FROM roles WHERE roleid > "' . $ROLES->creator . '" AND roleid < "'.$ROLES->none.'" ORDER BY roleid DESC';
    			    $roles = get_db_result($SQL);
    			    echo make_select("role_select", $roles, "roleid", "display_name", $role_selected);
	echo '	<div class="tooltipContainer info">'.get_help("input_page_default_role").'</div><br />
			</div>
			<div class="rowContainer">
				<label for="opendoor">Open Door Policy</label>
				<select name="opendoor" id="opendoor">
					<option value="0" ' . $open_no . '>No</option>
					<option value="1" ' . $open_yes . '>Yes</option>
				</select>
				<div class="tooltipContainer info">'.get_help("input_page_opendoor").'</div><br />
			</div>	
			<div class="rowContainer">
				<label for="siteviewable">Site viewable</label>
				<select name="siteviewable" id="siteviewable">
					<option value="0" ' . $global_no . '>No</option>
					<option value="1" ' . $global_yes . '>Yes</option>
				</select>
				<div class="tooltipContainer info">'.get_help("input_page_siteviewable").'</div><br />
			</div>';
    if($admin){
    	echo '
    			<div class="rowContainer">
    				<label for="menu_page">Show in Main Menu</label>
    				<select name="menu_page" id="menu_page">
    				<option value="0" ' . $menu_no . '>No</option>
    				<option value="1" ' . $menu_yes . '>Yes</option>
    				</select>
    				<div class="tooltipContainer info">'.get_help("input_page_menulink").'</div><br />
    			</div>
    			<div class="rowContainer">
    				<label for="hidefromvisitors">Hide Menu from Visitors</label>
    				<select name="hidefromvisitors" id="hidefromvisitors">
    				<option value="0" ' . $hide_no . '>No</option>
    				<option value="1" ' . $hide_yes . '>Yes</option>
    				</select>
    				<div class="tooltipContainer info">'.get_help("input_page_menulink").'</div><br />
    			</div>';
    }else{
        echo '<input type="hidden" id="menu_page" name="menu_page" value="' . $menu_page . '" /><input type="hidden" id="hidefromvisitors" name="hidefromvisitors" value="' . $hidefromvisitors . '" />';
    }
 			
    if(isset($MYVARS->GET["pageid"])){
    	echo '<input class="submit" name="submit" type="submit" value="Submit Changes" />';
    }else{
    	echo '<input class="submit" name="submit" type="submit"value="Create Page" />';
    }		
 
    echo '		</fieldset>
    	</form>
    </div>';
}

function create_edit_links(){
global $CFG, $MYVARS, $ROLES, $USER;
    $pageid = $MYVARS->GET["pageid"];
    //Stop right there you!
    if(!user_has_ability_in_page($USER->userid, "editpage", $pageid)){
        echo get_error_message("generic_permissions");
        return;
    }
    
    echo '
		<div id="links_editor" style="width:99%;height:93%;border-color:buttonface; border-style:dotted; padding: 10px 0px 10px 0px;">
		<table>
		<tr><td><b>Links Editor Mode</b>&nbsp;&nbsp;<input type="button" value="Add/Remove Links" onclick="ajaxapi(\'/ajax/page_ajax.php\',\'get_new_link_form\',\'&pageid=' . $pageid . '\',function() { simple_display(\'links_mode_span\');});">
						&nbsp;
						<input type="button" value="Sort Links" onclick="ajaxapi(\'/ajax/page_ajax.php\',\'get_link_manager\',\'&pageid=' . $pageid . '&linkid=' . $pageid . '\',function() { simple_display(\'links_mode_span\');  });">
		</td></tr></table><br />
		<span id="links_mode_span"></span>
		</div>';
}
?>