<?php
/***************************************************************************
* page_ajax.php - Page backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2013
* Revision: 1.4.2
***************************************************************************/

include ('header.php');
update_user_cookie();

$CFG->sitesearch = new stdClass();
$CFG->sitesearch->perpage = 8;

callfunction();

function edit_page() {
global $CFG, $MYVARS;
  $name = dbescape($MYVARS->GET["name"]);
  $description = dbescape($MYVARS->GET["description"]);
  $keywords = dbescape($MYVARS->GET["keywords"]);
  $defaultrole = dbescape($MYVARS->GET["defaultrole"]);
  $opendoor = dbescape($MYVARS->GET["opendoor"]);
  $siteviewable = dbescape($MYVARS->GET["siteviewable"]);
  $menu_page = dbescape($MYVARS->GET["menu_page"]);
  $hidefromvisitors = dbescape($MYVARS->GET["hidefromvisitors"]);
  $pageid = dbescape($MYVARS->GET["pageid"]);

  if ($menu_page == "1") { // Menu Page
    $SQL = "SELECT *
              FROM menus
             WHERE pageid = '$pageid'";
    if (get_db_row($SQL)) { //Page was already a menu...just run an update
      $SQL = "UPDATE menus
                 SET hidefromvisitors = '$hidefromvisitors',
                     link = '$pageid',
                     text = '$name'
               WHERE pageid = '$pageid'";
      execute_db_sql($SQL);
    } else { // New Menu Item
      $sort = get_db_field("sort", "menus", "id > 0 ORDER BY sort DESC");
      $sort++;
      $SQL = "INSERT INTO menus (pageid, text, link, sort, hidefromvisitors)
                   VALUES ('$pageid','$name','$pageid','$sort','$hidefromvisitors')";
      execute_db_sql($SQL);
    }
  } else {
    $SQL = "FROM menus WHERE pageid = '$pageid'";
    if (get_db_row("SELECT * $SQL")) { // Page is already a menu...just delete that row.
      execute_db_sql("DELETE $SQL");
    }
  }

  $shortname = substr(strtolower(preg_replace("/\W|_/", '', $name)), 0, 20);
  $SQL = "UPDATE pages
             SET description = '$description',
                 name = '$name',
                 short_name = '$shortname',
                 keywords = '$keywords',
                 siteviewable = '$siteviewable',
                 menu_page = '$menu_page',
                 default_role = '$defaultrole',
                 opendoorpolicy = '$opendoor'
           WHERE pageid = $pageid";
  if (execute_db_sql($SQL)) { echo "Page edited successfully"; }
}

function create_page() {
global $CFG, $MYVARS;
  update_user_cookie();
  echo create_new_page((object) $MYVARS->GET); // Converts associative array to object.
}

function pagesearch() {
global $CFG, $MYVARS, $USER;
  $searchwords = trim($MYVARS->GET["searchwords"]);
  // no search words given
  if ($searchwords == "") {
    $searchwords = '%';
  }

  // logged in
  $loggedin = is_logged_in() ? true : false;
  $userid = $loggedin ? $USER->userid : "";

  // is a site admin
  $admin = $loggedin && is_siteadmin($userid) ? true : false;

  //restrict possible page listings
  $siteviewableonly = $loggedin ? "" : " AND p.siteviewable=1";
  $opendoorpolicy = $admin ? "" : " AND (p.opendoorpolicy=1 OR p.siteviewable=1)";

  //Create the page limiter
  $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
  $firstonpage = $CFG->sitesearch->perpage * $pagenum;
  $limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
  $words = explode(" ", $searchwords);

  $i = 0; $searchstring = "";
  while (isset($words[$i])) {
      $searchpart = "(p.name LIKE '%" . $words[$i] . "%' OR p.keywords LIKE '%" . $words[$i] . "%' OR p.description LIKE '%" . $words[$i] . "%')";
      $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
      $i++;
  }

  if ($loggedin) {
      $roleid = get_user_role($userid, $CFG->SITEID);
      $check_rights = "";
      if (empty($admin)) { // Is my site role allowed to view pages.  REPLACE WITH user_has_ability_in_page?????
          $check_rights = ", IF(p.pageid IN (SELECT p.pageid
                                               FROM pages p
                                         INNER JOIN roles_ability ry
                                                 ON ry.roleid = '$roleid'
                                                AND ry.ability = 'viewpages'
                                                AND allow = '1'
                                              WHERE (p.pageid IN (SELECT ra.pageid
                                                                    FROM roles_assignment ra
                                                                   WHERE ra.userid = '$userid'
                                                                     AND ra.pageid = p.pageid
                                                                     AND ra.confirm = 0)
                                                 OR p.pageid IN (SELECT rau.pageid
                                                                   FROM roles_ability_peruser rau
                                                                  WHERE rau.userid = '$userid'
                                                                    AND rau.ability = 'viewpages'
                                                                    AND allow = '1'))
                                                AND p.pageid NOT IN (SELECT rau.pageid
                                                                       FROM roles_ability_peruser rau
                                                                      WHERE rau.userid = '$userid'
                                                                        AND rau.ability = 'viewpages'
                                                                        AND allow = '0')
                                                AND p.pageid != '$CFG->SITEID'
                                                AND p.menu_page != '1'), 1, 0) as added";
      }

      $SQL = "SELECT p.*
              $check_rights
                FROM pages p
               WHERE p.pageid != '$CFG->SITEID'
	               AND ($searchstring)
	               AND p.menu_page = 0
	          ORDER BY p.name";
  } else {
      $SQL = "SELECT p.*
                FROM pages p
               WHERE p.pageid != '$CFG->SITEID'
    	           AND ($searchstring)
    $siteviewableonly
       $no_menu_items
    	       ORDER BY p.name";
  }

  $total = get_db_count($SQL); //get the total for all pages returned.
  $SQL .= $limit; //Limit to one page of return.
  $pages = get_db_result($SQL);

  $count = $total > (($pagenum+1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = array("resultsfound" => ($count > 0), "searchresults" => "", "searchwords" => $searchwords, "searchtype" => "pagesearch", "isprev" => ($pagenum > 0), "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total), "wwwroot" => $CFG->wwwroot, "prev_pagenum" => ($pagenum - 1), "next_pagenum" => ($pagenum + 1),
                  "pagenum" => $pagenum, "viewing" => ($firstonpage + 1), "amountshown" => $amountshown, "total" => $total);

  if ($count > 0) {
    while ($page = fetch_row($pages)) {
      $linked = true;
      if ($loggedin && !$admin) {
        if ($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_has_ability_in_page($userid, "assign_roles", $page["pageid"])) {
          $params["col3"] = template_use("templates/page_ajax.template", array("must_request" => false, "can_add_remove" => user_has_ability_in_page($userid, "add_page", $CFG->SITEID),
                                                                               "isadd" => ($page["added"] == 0), "wwwroot" => $CFG->wwwroot, "pagenum" => $pagenum,
                                                                               "searchwords" => $searchwords, "pageid" => $page["pageid"]), "search_pages_buttons_template");
        } else {
          $linked = false;
          $alreadyrequested = get_db_row("SELECT * FROM roles_assignment WHERE userid='$userid' AND pageid='" . $page["pageid"] . "' AND confirm=1") ? true : false;
          $params["col3"] = template_use("templates/page_ajax.template", array("must_request" => true, "alreadyrequested" => $alreadyrequested, "wwwroot" => $CFG->wwwroot, "pagenum" => $pagenum,
                                                                               "searchwords" => $searchwords, "pageid" => $page["pageid"]), "search_pages_buttons_template");
        }
      }

      $params["linked"] = $linked;
      $params["col1"] = template_use("templates/page_ajax.template", array("linked" => $linked, "pageid" =>  $page["pageid"],"name" => substr($page["name"], 0, 30)), "search_pages_link_template");
      $params["col2"] = substr($page["description"], 0, 50);
      $params["searchresults"] = $params["searchresults"] . template_use("templates/page_ajax.template", $params, "search_row_template");
    }
  }

  echo template_use("templates/page_ajax.template", $params, "search_template");
}

function usersearch(){
global $CFG, $MYVARS, $USER;
  $userid = $USER->userid;
  $searchwords = trim($MYVARS->GET["searchwords"]);
  //no search words given
  if ($searchwords == "") {
      $searchwords = '%';
  }

  echo '<input type="hidden" id="searchwords" value="' . $searchwords . '" />';

  //is a site admin
  $admin = is_siteadmin($userid) ? true : false;

  //Create the page limiter
  $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
  $firstonpage = $CFG->sitesearch->perpage * $pagenum;
  $limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
  $words = explode(" ", $searchwords);

  $i = 0; $searchstring = "";
  while(isset($words[$i])){
      $searchpart = "(u.fname LIKE '%" . $words[$i] . "%' OR u.lname LIKE '%" . $words[$i] . "%' OR u.email LIKE '%" . $words[$i] . "%')";
      $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
      $i++;
  }

  $SQL = "SELECT u.*
            FROM users u
           WHERE ($searchstring)
           ORDER BY u.lname";

  $total = get_db_count($SQL); //get the total for all pages returned.
  $SQL .= $limit; //Limit to one page of return.
  $users = get_db_result($SQL);
  $count = $total > (($pagenum+1) * $CFG->sitesearch->perpage) ? $CFG->sitesearch->perpage : $total - (($pagenum) * $CFG->sitesearch->perpage); //get the amount returned...is it a full page of results?
  $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;

  $params = array("resultsfound" => ($count > 0), "searchresults" => "", "searchwords" => $searchwords, "searchtype" => "usersearch", "isprev" => ($pagenum > 0), "isnext" => ($firstonpage + $CFG->sitesearch->perpage < $total), "wwwroot" => $CFG->wwwroot, "prev_pagenum" => ($pagenum - 1), "next_pagenum" => ($pagenum + 1),
                  "pagenum" => $pagenum, "viewing" => ($firstonpage + 1), "amountshown" => $amountshown, "total" => $total);

  if ($count > 0) {
    while ($user = fetch_row($users)) {
      $params["isuser"] = ($userid != $user["userid"] && !is_siteadmin($user["userid"]));
      $params["userid"] = $userid;
      $params["user"] = $user;
      $params["col1"] = $user["fname"] . " " . $user["lname"];
      $params["col2"] = $user["email"];
      $params["col3"] = template_use("templates/page_ajax.template", $params, "search_users_buttons_template");
      $params["searchresults"] = $params["searchresults"] . template_use("templates/page_ajax.template", $params, "search_row_template");
    }
  }

  echo template_use("templates/page_ajax.template", $params, "search_template");
}

function get_new_link_form(){
global $MYVARS, $CFG, $USER;
    $pageid = $MYVARS->GET['pageid'];
    echo '<br />
	<table style="width:100%">
		<tr>
			<td class="field_title" style="white-space:nowrap;">
				Link Page Search:
			</td>
			<td class="field_input">
				<form onsubmit="ajaxapi(\'/ajax/page_ajax.php\',\'linkpagesearch\',\'&pageid=' . $pageid . '&searchwords=\'+escape(document.getElementById(\'searchbox\').value),function() { simple_display(\'page_search_span\');}); return false;"><input type="text" size="37" name="searchbox" id="searchbox" />&nbsp;<input type="submit" value="Search" />
			</td>
		</tr>
		<tr>
			<td colspan="2">
			<span id="page_search_span"></span>
			</td>
		</tr>
	</table>';
}

function get_link_manager(){
global $MYVARS, $CFG, $USER;
    $pageid = $MYVARS->GET['pageid'];
    $returnme = "";
    $i = 0;
    if($links = get_db_result("SELECT * FROM pages_links WHERE hostpageid=$pageid ORDER BY sort")){
        $returnme = "<br />
		Reorder the links how you would like them to be displayed in this page.  Change the link names and save them by selecting the \"Save\" button
    	that appears beside it.  Changing a links position also saves a name change to that link.
    	<br /><br />
    	<div style='overflow:hidden;font:13.3px sans-serif;width:31em;border-left:1px solid #808080;border-top:1px solid #808080;border-bottom:1px solid #fff; border-right:1px solid #fff;margin:auto;'>
    	<div style='background:#0A246A;overflow:auto;border-left:3px solid #0A246A;border-top:1px solid #404040;border-bottom:1px solid #d4d0c8;border-right:3px solid #0A246A;'><hr />";
        $count = get_db_count("SELECT * FROM pages_links WHERE hostpageid=$pageid");
        while($link = fetch_row($links)){
            $returnme .= '<label for="standard' . $i . '" style="padding-right:3px;white-space:nowrap;display:block;background:#0a246a; color:#fff;">
		 			<span style="width:10px;background-color:gray;">&nbsp;' . ($i + 1) . '.&nbsp;</span>
					 	<input type="text" id="linkdisplay' . $i . '" size="38" value="' . stripslashes($link['linkdisplay']) . '" onkeyup="if(document.getElementById(\'linkdisplay' . $i . '_hidden\').value != document.getElementById(\'linkdisplay' . $i . '\').value){document.getElementById(\'linkdisplay' . $i . '_save\').style.display = \'inline\';}else{document.getElementById(\'linkdisplay' . $i . '_save\').style.display = \'none\';}" />
						<input type="hidden" id="linkdisplay' . $i . '_hidden" value="' . stripslashes($link['linkdisplay']) . '" />&nbsp;
					<span id="linkdisplay' . $i . '_save" style="display:none;">
						<input type="button" value="Save Name" style="font-size:.75em;display:inline;" onclick="ajaxapi(\'/ajax/page_ajax.php\',\'rename_link\',\'&linkid=' . $link['linkid'] . '&linkdisplay=\'+escape(document.getElementById(\'linkdisplay' . $i . '\').value),function() { document.getElementById(\'linkdisplay' . $i . '\').value = document.getElementById(\'linkdisplay' . $i . '_hidden\').value; }); document.getElementById(\'linkdisplay' . $i . '_save\').style.display = \'none\';"/></span>&nbsp;';
            $returnme .= $i > 0 ? '<img src="' . $CFG->wwwroot . '/images/up.png" title="Move Up" alt="Move Up" onclick="ajaxapi(\'/ajax/page_ajax.php\',\'move_link\',\'&pageid=' . $pageid . '&direction=up&linkid=' . $link['linkid'] . '&linkdisplay=\'+escape(document.getElementById(\'linkdisplay' . $i . '\').value),function() { ajaxapi(\'/ajax/page_ajax.php\',\'get_link_manager\',\'&pageid=' . $pageid . '&linkid=' . $link["linkid"] . '\',function() { simple_display(\'links_mode_span\');});});"/>&nbsp;' : "";
            $returnme .= $i < ($count - 1) ? '<img src="' . $CFG->wwwroot . '/images/down.png" title="Move Down" alt="Move Down" onclick="ajaxapi(\'/ajax/page_ajax.php\',\'move_link\',\'&pageid=' . $pageid . '&direction=down&linkid=' . $link['linkid'] . '&linkdisplay=\'+escape(document.getElementById(\'linkdisplay' . $i . '\').value),function() { ajaxapi(\'/ajax/page_ajax.php\',\'get_link_manager\',\'&pageid=' . $pageid . '&linkid=' . $link["linkid"] . '\',function() { simple_display(\'links_mode_span\');});});"/></label>' : "";
            $returnme .= '<hr />';
            $i++;
        }
        $returnme .= '</div></div>';
    }
    $returnme = $returnme == "" ? "There are no links to manage on this page." : $returnme;
    echo $returnme;
}

function linkpagesearch(){
global $CFG, $MYVARS, $USER;
    $searchwords = trim($MYVARS->GET["searchwords"]);
    $pageid = $MYVARS->GET["pageid"];
    //no search words given
    if($searchwords == ""){
        $searchwords = '%';
    }
    //logged in
    $loggedin = is_logged_in() ? true : false;
    $userid = $loggedin ? $USER->userid : "";
    //is a site admin
    $admin = $loggedin && is_siteadmin($userid) ? true : false;
    //restrict possible page listings
    $siteviewableonly = $loggedin ? "" : " AND p.siteviewable=1";
    $opendoorpolicy = $admin ? "" : " AND (p.opendoorpolicy=1 OR p.siteviewable=1)";
    //Create the page limiter
    $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
    $firstonpage = $CFG->sitesearch->perpage * $pagenum;
    $limit = " LIMIT $firstonpage," . $CFG->sitesearch->perpage;
    $words = explode(" ", $searchwords);
    $i = 0; $searchstring = "";
    while(isset($words[$i])){
        $searchpart = "(p.name LIKE '%" . $words[$i] . "%' OR p.keywords LIKE '%" . $words[$i] . "%' OR p.description LIKE '%" . $words[$i] . "%')";
        $searchstring = $searchstring == '' ? $searchpart : $searchstring . " OR $searchpart";
        $i++;
    }
    if($loggedin){
        $roleid = get_user_role($userid, $CFG->SITEID);
        $SQL = '
		SELECT p.*, (SELECT pl.linkid FROM pages_links pl WHERE pl.linkpageid=p.pageid AND pl.hostpageid=' . $pageid . ') as alreadylinked
		FROM pages p WHERE p.pageid != ' . $CFG->SITEID . '
		AND (' . $searchstring . ')
		AND p.pageid != ' . $pageid . '
		ORDER BY p.name
		';
    }
    $total = get_db_count($SQL); //get the total for all pages returned.
    $SQL .= $limit; //Limit to one page of return.
    $pages = get_db_result($SQL);
    $count = $pages ? get_db_count($SQL) : 0; //get the amount returned...is it a full page of results?
    $amountshown = $firstonpage + $CFG->sitesearch->perpage < $total ? $firstonpage + $CFG->sitesearch->perpage : $total;
    $prev = $pagenum > 0 ? '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'linkpagesearch\',\'&pageid=' . $pageid . '&pagenum=' . ($pagenum - 1) . '&searchwords=\'+escape(\'' . $searchwords . '\'),function() {simple_display(\'page_search_span\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page"></a>' : "";
    $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
    $next = $firstonpage + $CFG->sitesearch->perpage < $total ? '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'linkpagesearch\',\'&pageid=' . $pageid . '&pagenum=' . ($pagenum + 1) . '&searchwords=\'+escape(\'' . $searchwords . '\'),function() {simple_display(\'page_search_span\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/next.gif" title="Next Page" alt="Next Page"></a>' : "";
    $header = '';
    if($count > 0){
        $body = '<table style="background-color:#F3F6FB;width:100%;border-collapse:collapse;">';
        while($page = fetch_row($pages)){
            $add_remove = "";
            $confirmopen = "";
            $confirmclose = "";
            $header = $header == "" ? '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><p>' : $header;
            if($page["siteviewable"] == 0){
                $confirmopen = "if(confirm('This linked page is not viewable to everyone and will only show up for people who have viewing rights.')){";
                $confirmclose = "}";
            }
            if($loggedin){
                $add_remove = $page["alreadylinked"] == 0 ? '<span id="addremove_' . $page["pageid"] . '"><a href="javascript: ' . $confirmopen . 'ajaxapi(\'/ajax/page_ajax.php\',\'make_page_link\',\'&pageid=' . $pageid . '&linkpageid=' . $page["pageid"] . '\',function() {simple_display(\'addremove_' . $page["pageid"] . '\');});  ajaxapi(\'/ajax/page_ajax.php\',\'linkpagesearch\',\'&pageid=' . $pageid . '&pagenum=' . $pagenum . '&searchwords=\'+escape(\'' . $searchwords . '\'),function() {simple_display(\'page_search_span\');});' . $confirmclose . '" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/add.png" title="Add Page Link" alt="Add Page Link"></a></span>' : '<span id="addremove_' . $page["pageid"] . '"><a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'unlink_page\',\'&pageid=' . $pageid . '&linkid=' . $page["alreadylinked"] . '\',function() {simple_display(\'addremove_' . $page["pageid"] . '\');});  ajaxapi(\'/ajax/page_ajax.php\',\'linkpagesearch\',\'&pageid=' . $pageid . '&pagenum=' . $pagenum . '&searchwords=\'+escape(\'' . $searchwords . '\'),function() {simple_display(\'page_search_span\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/delete.png" title="Remove Page Link" alt="Remove Page Link"></a></span>';
            }
            $body .= '<tr style="height:30px;border:3px solid white;font-size:.9em;"><td style="width:30%;padding:5px;font-size:.85em;white-space:nowrap;">' . substr(stripslashes($page["name"]), 0, 30) . '</td><td style="width:60%;padding:5px;font-size:.75em;">' . substr(stripslashes(strip_tags($page["description"])), 0, 100) . '</td><td style="text-align:right;padding:5px;">' . $add_remove . '</td></tr>';
        }
        $body .= "</table>";
    }else{
        $header .= '<span class="error_text" class="centered_span">No matches found.</span>';
    }
    echo '<input type="hidden" id="searchwords" value="' . $searchwords . '" />' . $header . $body;
}

function make_page_link(){
global $MYVARS, $CFG, $USER;
    $pageid = $MYVARS->GET['pageid'];
    $linkid = $MYVARS->GET['linkpageid'];
    $sort = get_db_count("SELECT * FROM pages_links WHERE hostpageid=$pageid") + 1;
    $display = get_db_field("name", "pages", "pageid=$linkid");
    execute_db_sql("INSERT INTO pages_links (hostpageid,linkpageid,sort,linkdisplay) VALUES($pageid,$linkid,$sort,'$display')");
    echo "";
}


function unlink_page(){
global $MYVARS, $CFG, $USER;
    $linkid = $MYVARS->GET['linkid'];
    $pageid = $MYVARS->GET['pageid'];
    $SQL = "DELETE FROM pages_links WHERE linkid=$linkid";
    execute_db_sql($SQL);
    resort_links($pageid);
    echo "";
}

function move_link(){
global $MYVARS, $CFG, $USER;
    $linkid = $MYVARS->GET['linkid'];
    $linkdisplay = addslashes($MYVARS->GET['linkdisplay']);
    $pageid = $MYVARS->GET['pageid'];
    $direction = $MYVARS->GET['direction'];
    $change = $direction == "up" ? -1 : 1;
    $SQL = "SELECT * FROM pages_links WHERE linkid=$linkid";
    $link1 = get_db_row($SQL);
    $SQL = "SELECT * FROM pages_links WHERE hostpageid=$pageid AND sort=" . ($link1["sort"] + $change);
    $link2 = get_db_row($SQL);
    execute_db_sql("UPDATE pages_links SET sort=" . $link2["sort"] . ",linkdisplay='" . $linkdisplay . "' WHERE linkid=$linkid");
    execute_db_sql("UPDATE pages_links SET sort=" . $link1["sort"] . " WHERE linkid=" . $link2["linkid"]);
    resort_links($pageid);
    echo "";
}

function rename_link(){
global $MYVARS, $CFG, $USER;
    $linkid = $MYVARS->GET['linkid'];
    $linkdisplay = addslashes($MYVARS->GET['linkdisplay']);
    execute_db_sql("UPDATE pages_links SET linkdisplay='$linkdisplay' WHERE linkid=$linkid");
    echo "";
}

function resort_links($pageid){
global $MYVARS, $CFG, $USER;
    $i = 1;
    if($links = get_db_result("SELECT * FROM pages_links WHERE hostpageid=$pageid ORDER BY sort")){
        while($link = fetch_row($links)){
            execute_db_sql("UPDATE pages_links SET sort=$i WHERE linkid=" . $link["linkid"]);
            $i++;
        }
    }
}

function get_inviteable_pages(){
global $CFG, $MYVARS;
    $inviter = $MYVARS->GET["inviter"];
    $invitee = $MYVARS->GET["invitee"];
    $pages = user_has_ability_in_pages($inviter, "invite", false, false); //list pages you have invite permissions in
    $notthese = user_has_ability_in_pages($invitee, "viewpages", false, false); //remove pages that the user already has access to

    echo make_select("page_invite_list", $pages, "pageid", "name", null, 'onchange="if($(\'#page_invite_list\').val() != \'\' && confirm(\'Do you wish to send an invitation to this user?\')){  ajaxapi(\'/ajax/page_ajax.php\',\'invite_user\',\'&pageid=\'+$(\'#page_invite_list\').val()+\'&userid=' . $invitee . '\',function() { simple_display(\'pagelist_' . $invitee . '\'); });  }else{  ajaxapi(\'/ajax/site_ajax.php\',\'donothing\',\'\',function() { simple_display(\'pagelist_' . $invitee . '\'); }) }"', true, 1 , "width:150px;","",$notthese);
}

function invite_user(){
global $CFG, $MYVARS;
    $userid = $MYVARS->GET["userid"];
    $pageid = $MYVARS->GET["pageid"];
    $defaultrole = get_db_field("default_role", "pages", "pageid=$pageid");
    if(get_db_row("SELECT * FROM roles_assignment WHERE userid='$userid' AND roleid='$defaultrole' && pageid='$pageid' AND confirm='2'") || execute_db_sql("INSERT INTO roles_assignment (userid,roleid,pageid,confirm) VALUES($userid,$defaultrole,$pageid,2)")){ echo "Invite Sent";
    }else{  echo "Invite Error"; }
}

function refresh_page_links(){
global $CFG, $USER, $MYVARS;
    if(!isset($PAGELISTLIB)){ include_once ($CFG->dirroot . '/lib/pagelistlib.php'); }
    $userid = $USER->userid;
    $pageid = $MYVARS->GET["pageid"];
    echo get_page_links($pageid, $userid);
}

function change_subscription(){
global $CFG, $MYVARS, $USER;
    $userid = $USER->userid;
    $pageid = $MYVARS->GET["pageid"];
    if(subscribe_to_page($pageid, $userid, true)){ // subscription added
        echo '<a href="javascript: if(confirm(\'Are you sure you want to remove yourself from this page? \n You might not be able to get into this page again.\')){ajaxapi(\'/ajax/page_ajax.php\',\'change_subscription\',\'&pageid=' . $pageid . '\',function() {simple_display(\'addremove_' . $pageid . '\');}); ajaxapi(\'/ajax/page_ajax.php\',\'pagesearch\',\'&pagenum=' . $pagenum . '&searchwords=\'+escape(document.getElementById(\'searchwords\').value),function() {simple_display(\'searchcontainer_pagesearch\');});}" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/delete.png" title="Remove Page" alt="Remove Page"></a>';
    }else{ //subscription removed
        $page = get_db_row("SELECT * FROM pages WHERE pageid=$pageid");
        if($page["siteviewable"] == 1 || $page["opendoorpolicy"] == 1 || $page["added"] == 1 || user_has_ability_in_page($userid, "assign_roles", $pageid)){
            echo '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'change_subscription\',\'&pageid=' . $pageid . '\',function() {simple_display(\'addremove_' . $pageid . '\');}); ajaxapi(\'/ajax/page_ajax.php\',\'pagesearch\',\'&pagenum=' . $pagenum . '&searchwords=\'+escape(document.getElementById(\'searchwords\').value),function() {simple_display(\'searchcontainer_pagesearch\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/add.png" title="Add Page" alt="Add Page"></a>';
        }else{  echo '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'add_request\',\'&pageid=' . $pageid . '\',function() {simple_display(\'addremove_' . $pageid . '\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/mail.gif" title="Send Request" alt="Send Request"></a>'; }
    }
}

function add_request(){
global $CFG, $MYVARS, $USER;
    $userid = $USER->userid;
    $pageid = $MYVARS->GET["pageid"];
    $roleid = get_db_field("default_role", "pages", "pageid=$pageid");
    if(execute_db_sql("INSERT INTO roles_assignment (userid,roleid,pageid,confirm) VALUES($userid,$roleid,$pageid,1)")) {
        echo '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'remove_request\',\'&pageid=' . $pageid . '\',function() {simple_display(\'addremove_' . $pageid . '\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/undo.png" title="Remove Request" alt="Remove Request"></a>';
    }else{
        echo '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'add_request\',\'&pageid=' . $pageid . '\',function() {simple_display(\'addremove_' . $pageid . '\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/mail.gif" title="Send Request" alt="Send Request"></a>';
    }
}

function remove_request(){
global $CFG, $MYVARS, $USER;
    $userid = $USER->userid;
    $pageid = $MYVARS->GET["pageid"];
    if(execute_db_sql("DELETE FROM roles_assignment WHERE userid=$userid AND pageid=$pageid AND confirm=1")){
        echo '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'add_request\',\'&pageid=' . $pageid . '\',function() {simple_display(\'addremove_' . $pageid . '\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/mail.gif" title="Send Request" alt="Send Request"></a>';
    }else{
        echo '<a href="javascript: ajaxapi(\'/ajax/page_ajax.php\',\'remove_request\',\'&pageid=' . $pageid . '\',function() {simple_display(\'addremove_' . $pageid . '\');});" onmouseup="this.blur()"><img src="' . $CFG->wwwroot . '/images/undo.png" title="Remove Request" alt="Remove Request"></a>';
    }
}
?>
