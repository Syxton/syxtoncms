<?php
/***************************************************************************
* forumlib.php - Forum function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 3/23/2012
* Revision: 0.8.8
***************************************************************************/

if(!isset($LIBHEADER)){ if(file_exists('./lib/header.php')){ include('./lib/header.php'); }elseif(file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif(file_exists('../../lib/header.php')){ include('../../lib/header.php'); }}
$FORUMLIB = true;

function display_forum($pageid,$area,$forumid){
global $CFG, $USER, $ROLES;
	date_default_timezone_set(date_default_timezone_get());
	$content='<div id="forum_div_'.$forumid.'">';
	
    //get settings or create default settings if they don't exist
	if(!$settings = fetch_settings("forum",$forumid,$pageid)){
		make_or_update_settings_array(default_settings("forum",$pageid,$forumid));
		$settings = fetch_settings("forum",$forumid,$pageid);
	}
    
	$title = $settings->forum->$forumid->feature_title->setting;
	$refresh_time = 1 * 60000; //the 1 could be a setting for minutes
	if($area == "middle"){ //This is a FORUM
		if(user_has_ability_in_page($USER->userid,"viewforums",$pageid)){
			$content .= get_forum_categories($forumid);
		}else{
			$content .= '<span class="centered_span">'.get_error_message("generic_permissions").'</span>';
		}
		$content .= '</div><input type="hidden" name="forum_refresh_'.$forumid.'" id="forum_refresh_'.$forumid.'" value="ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_categories_ajax\',\'&amp;forumid='.$forumid.'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);" />';
        
        //Refresh Script
        $content.='<script type="text/javascript">var forum'.$forumid.'_interval=0; forum'.$forumid.'_interval = setInterval(function(){ eval(stripslashes(unescape(window.parent.document.getElementById("forum_refresh_'.$forumid.'").value))); },'.$refresh_time.');</script>';
    }else{ //This is a SHOUTBOX
		if(user_has_ability_in_page($USER->userid,"viewshoutbox",$pageid)){
			$content .= get_shoutbox($forumid);
		}else{
			$content .= '<span class="centered_span">'.get_error_message("generic_permissions").'</span>';
		}
		$content .= '</div><input type="hidden" name="forum_refresh_'.$forumid.'" id="forum_refresh_'.$forumid.'" value="ajaxapi(\'/features/forum/forum_ajax.php\',\'get_shoutbox_ajax\',\'&amp;forumid='.$forumid.'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);" />';
	}
	  
	$buttons = is_logged_in() ? get_button_layout("forum",$forumid,$pageid) : "";
	return get_css_box($title,$content,$buttons, NULL, "forum", $forumid);
}

function get_forum_categories($forumid){
global $USER,$CFG;
	$returnme = '<br /><table class="forum_category"><tr><td class="forum_headers">Category Name</td><td class="forum_headers" style="width:70px;">Discussions</td><td  class="forum_headers" style="width:70px;">Posts</td></tr>';
	$content = "";
	if($categories = get_db_result("SELECT * FROM forum_categories WHERE forumid=$forumid AND shoutbox=0 ORDER BY sort")){
		while($category = fetch_row($categories)){
			$notviewed = true;
			//Find if new posts are available
			if(is_logged_in()){
				$SQL = 'SELECT * FROM forum_posts f WHERE f.catid='.$category["catid"].' AND
				(discussionid IN (
								SELECT a.discussionid 
									FROM forum_discussions a 
									INNER JOIN forum_views b ON a.discussionid = b.discussionid 
									WHERE b.userid='.$USER->userid.'
									AND a.lastpost > b.lastviewed
							 )
				OR discussionid NOT IN (SELECT discussionid FROM forum_views WHERE catid='.$category["catid"].' AND userid='.$USER->userid.')
				)';
				$notviewed = $newposts = get_db_result($SQL) ? true : false;
			}
			$discussion_count = get_db_count("SELECT * FROM forum_discussions WHERE catid=".$category["catid"]." AND shoutbox=0");
			$posts_count = get_db_count("SELECT * FROM forum_posts WHERE catid=".$category["catid"]);
			$viewclass = $notviewed ? 'forum_col1' : 'forum_col1_viewed';
			$content .= '	<tr><td class="'.$viewclass.'"><span style="position:relative;float:left;"><a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum=0&amp;pageid='.$category['pageid'].'&amp;forumid='.$forumid.'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum=0&amp;pageid='.$category['pageid'].'&amp;forumid='.$forumid.'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\');}},true);" >'.$category["title"].'</a></span>';
			$edit = user_has_ability_in_page($USER->userid,"editforumcategory",$category['pageid']);
			$content .= '<span style="position:relative;float:right;">';
			
            if($edit){
				if($category["sort"] > 1) $content .= '<a title="Move Up" onclick="this.blur();" href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'move_category\',\'&amp;catid='.$category['catid'].'&amp;forumid='.$forumid.'&amp;pageid='.$category['pageid'].'&amp;direction=up\',function(){if (xmlHttp.readyState == 4) {  simple_display(\'forum_div_'.$forumid.'\');}},true);"><img alt="Move Up" src="'.$CFG->wwwroot.'/images/up.gif" /></a>';
				if($category["sort"] != get_db_field("sort","forum_categories","forumid=$forumid AND shoutbox=0 ORDER BY sort DESC")) $content .= '<a title="Move Down" onclick="this.blur();" href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'move_category\',\'&amp;catid='.$category['catid'].'&amp;forumid='.$forumid.'&amp;pageid='.$category['pageid'].'&amp;direction=down\',function() { simple_display(\'forum_div_'.$forumid.'\');});"><img alt="Move Down" src="'.$CFG->wwwroot.'/images/down.gif" /></a>';
			}
			
            if(user_has_ability_in_page($USER->userid,"deleteforumcategory",$category['pageid'])){ $content .= '<a title="Delete Category" onclick="this.blur();" href="javascript: if(confirm(\'Are you sure you wish to delete this category? \nThis will delete all discussions and posts inside this category.\')){ ajaxapi(\'/features/forum/forum_ajax.php\',\'delete_category\',\'&amp;forumid='.$forumid.'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) {  simple_display(\'forum_div_'.$forumid.'\');}},true);}"><img alt="Delete Category" src="'.$CFG->wwwroot.'/images/delete.png" /></a>';}		
			
            if($edit){
                $content .= make_modal_links(array("title"=>"Edit Category","path"=>$CFG->wwwroot."/features/forum/forum.php?action=createforumcategory&amp;catid=".$category['catid'].'&amp;pageid='.$category['pageid'].'&amp;forumid='.$forumid,"runafter"=>'forum_refresh_'.$forumid,"height"=>"200","width"=>"640","validate"=>"true","image"=>$CFG->wwwroot."/images/edit.png"));
            }
            
			$content .= '	</span></td>
						<td class="forum_col2">'.$discussion_count.'</td>
						<td class="forum_col3">'.($posts_count-$discussion_count).'</td>
						</tr>';
		}
	}
	$returnme .= $content == "" ? '<tr><td colspan="3" class="forum_col1">No Categories Created.</td></tr>' : $content;
	$returnme .= "</table>";
	return $returnme;
}

function update_user_views($catid,$discussionid,$userid){
global $CFG;
	$time = get_timestamp();
	if(!get_db_row("SELECT * FROM forum_views WHERE userid='$userid' AND discussionid='$discussionid'")){
	   execute_db_sql("INSERT INTO forum_views (userid,catid,discussionid,lastviewed) VALUES('$userid','$catid','$discussionid','$time')");
	}else{
	   execute_db_sql("UPDATE forum_views SET lastviewed='$time' WHERE userid='$userid' AND discussionid='$discussionid'");
	}
}

function get_shoutbox($forumid){
global $USER,$CFG;
	date_default_timezone_set(date_default_timezone_get());
    $pageid = get_db_field("page","form","forumid='$forumid'");
	$settings = fetch_settings("forum",$forumid,$pageid);
	$shoutboxlimit = isset($settings->forum->$forumid->shoutboxlimit->setting) ? " LIMIT ". $settings->forum->$forumid->shoutboxlimit->setting : "";
	//////////////////////////////////////////////////////////////////
	$userid = is_logged_in() ? "&amp;userid=" . $USER->userid : "";
	$returnme = '
	<table class="shoutbox">
        <tr>
            <td>
                <img class="shoutbox_tableft" src="'.$CFG->wwwroot.'/images/shouttab_left.gif" alt="shout tab left image" /><img class="shoutbox_tabcenter" src="'.$CFG->wwwroot.'/images/shouttab_background.gif" alt="shout tab background image"/><img class="shoutbox_tabright" src="'.$CFG->wwwroot.'/images/shouttab_right.gif" alt="shout tab right image" />
            </td>
        </tr>
	<tr>
        <td>
            <span class="shoutbox_tabtext">
                '.make_modal_links(array("title"=>"Shout","path"=>$CFG->wwwroot."/features/forum/forum.php?action=shoutbox_editor$userid&amp;forumid=$forumid","iframe"=>"true","refresh"=>"true","runafter"=>"forum_refresh_$forumid")).'
            </span>
        </td>
    </tr>';
    
	$shoutboxid = get_db_field("discussionid","forum_discussions","forumid=$forumid AND shoutbox=1");
	if($posts = get_db_result("SELECT * FROM forum_posts WHERE discussionid=$shoutboxid ORDER BY posted DESC $shoutboxlimit")){
		while($post = fetch_row($posts)){
			$alias = $post["ownerid"] != 0 ? get_user_name($post["ownerid"]): $post["alias"];
			$posted = date("m.d.y g:ia",$post["posted"]);
            $message = strip_tags($post["message"],"<img><a>");
			$returnme .= '  <tr>
                                <td class="shoutbox_post">
                                    <span style="color: black">'.$alias.' at '.$posted.'</span><br />'.$message.'<br /><br />
                                </td>
                            </tr>';
		}
	}
	$returnme .= "</table>";
	return $returnme;
}

function get_post_pages($forumid, $discussion, $pagenum, $beforeskipping=10, $buttons = true){
global $CFG;
	$settings = fetch_settings("forum",$forumid,$discussion["pageid"]);
	$perpage = isset($settings->forum->$forumid->postsperpage->setting) ? " LIMIT ". $settings->forum->$forumid->postsperpage->setting : "";

	$perpage = $settings->forum->$forumid->postsperpage->setting;
	$pagenum = $pagenum === false ? false : $pagenum;
	$previous = ""; $next = "";
	if($post_count = get_db_count("SELECT * FROM forum_posts WHERE discussionid=".$discussion["discussionid"])){
		$page_counter = 1;
		$lastpage = (ceil($post_count / $perpage)-1);
		$countdown = $post_count;	
		if($buttons && !($pagenum === false) && $pagenum > 0) $previous = '<a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.($pagenum-1).'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.($pagenum-1).'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){if (xmlHttp.readyState == 4){ simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\');}},true); " > Back </a>';
		if($buttons && !($pagenum === false) && $pagenum < $lastpage) $next = '<a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.($pagenum+1).'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.($pagenum+1).'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){if (xmlHttp.readyState == 4){ simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\');}},true);" > Next </a>';
		$returnme = '<span style="font-size:.8em;">Page: '.$previous;
		while($countdown > 0){
			if($page_counter > $beforeskipping){
				$returnme .= !($pagenum === false) && $pagenum == $lastpage ? ' Last ' : ' <a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.$lastpage.'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){ if (xmlHttp.readyState == 4){ simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.$lastpage.'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\');}},true); "> Last </a>';
				$countdown = 0;
			}else{
				$returnme .= !($pagenum === false) && $pagenum == ($page_counter-1) ? ' ' . $page_counter . ' ' : ' <a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.($page_counter-1).'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_posts\',\'&amp;discussionid='.$discussion['discussionid'].'&amp;pagenum='.($page_counter-1).'&amp;catid='.$discussion['catid'].'&amp;forumid='.$discussion['forumid'].'&amp;pageid='.$discussion["pageid"].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\');}},true); ">' . $page_counter . ' </a>';
				$countdown -= $perpage;
			}
			$page_counter++;
		}
		if($page_counter == 2){ return "";}
	}else{ return ""; }
	
    return ''.$returnme.$next.'</span>';
}

function get_discussion_pages($forumid, $category, $pagenum, $beforeskipping=20, $buttons = true){
global $CFG;

	$settings = fetch_settings("forum",$forumid,$category['pageid']);
	$perpage = $settings->forum->$forumid->discussionsperpage->setting;
	
	$pagenum = $pagenum === false ? false : $pagenum;
	$previous = ""; $next = "";
	if($discussion_count = get_db_count("SELECT * FROM forum_discussions WHERE bulletin=0 AND catid=".$category["catid"]." AND shoutbox=0")){
		$page_counter = 1;
		$lastpage = (ceil($discussion_count / $perpage)-1);
		$countdown = $discussion_count;	
		if($buttons && !($pagenum === false) && $pagenum > 0){ $previous = '<a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.($pagenum-1).'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.($pagenum-1).'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\'); }},true);" > Back </a>';}
		if($buttons && !($pagenum === false) && $pagenum < $lastpage){ $next = '<a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.($pagenum+1).'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.($pagenum+1).'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\'); }},true);" > Next </a>';}
		$returnme = '<span style="font-size:.8em;">Page: '.$previous;
		while($countdown > 0){
			if($page_counter > $beforeskipping){
				$returnme .= !($pagenum === false) && $pagenum == $lastpage ? ' Last ' : ' <a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.$lastpage.'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.$lastpage.'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\'); }},true);"> Last </a>';
				$countdown = 0;
			}else{
				$returnme .= !($pagenum === false) && $pagenum == ($page_counter-1) ? ' ' . $page_counter . ' ' : ' <a href="javascript: ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.($page_counter-1).'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); $(\'#forum_refresh_'.$forumid.'\').val(\''.addslashes('ajaxapi(\'/features/forum/forum_ajax.php\',\'get_forum_discussions\',\'&amp;dpagenum='.($page_counter-1).'&amp;pageid='.$category['pageid'].'&amp;forumid='.$category['forumid'].'&amp;catid='.$category['catid'].'\',function(){if (xmlHttp.readyState == 4) { simple_display(\'forum_div_'.$forumid.'\'); }},true);').'\'); }},true);">' . $page_counter . ' </a>';
				$countdown -= $perpage;
			}
			$page_counter++;
		}
		if($page_counter == 2){ return "";}
	}else{ return ""; }
    
	return ''.$returnme.$next.'</span>';
}

function first_post($discussionid){
	$postid = get_db_field("postid","forum_posts","discussionid=$discussionid ORDER BY postid");
	return $postid;
}

function resort_categories($forumid){
	if($result = get_db_result("SELECT * FROM forum_categories WHERE forumid='$forumid' AND shoutbox=0 ORDER BY sort")){
		$i = 1;
		while($row = fetch_row($result)){
			execute_db_sql("UPDATE forum_categories SET sort='$i' WHERE catid='".$row['catid']."'");
			$i++;
		}
	}
}

function insert_blank_forum($pageid){
	$title = "Forum"; $type="forum";
	if($featureid = execute_db_sql("INSERT INTO forum (pageid) VALUES('$pageid')")){
		$area = get_db_field("default_area", "features", "feature='forum'");
		$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid,feature,sort,area,featureid) VALUES('$pageid','forum','$sort','$area','$featureid')");
		$catid = execute_db_sql("INSERT INTO forum_categories (forumid,pageid,title,shoutbox) VALUES('$featureid','$pageid','Shoutbox',1)");
		$discussionid = execute_db_sql("INSERT INTO forum_discussions (catid,forumid,pageid,title,shoutbox) VALUES('$catid','$featureid','$pageid','Shoutbox',1)");		
		execute_db_sql("INSERT INTO settings (type,pageid,featureid,setting_name,setting,extra,defaultsetting) VALUES('$type',".$pageid.",".$featureid.",'feature_title','$title',NULL,'$title'),('$type',".$pageid.",".$featureid.",'shoutboxlimit','10',NULL,'10'),('$type',".$pageid.",".$featureid.",'postsperpage','10',NULL,'10'),('$type',".$pageid.",".$featureid.",'discussionsperpage','10',NULL,'10')");
	
		return $featureid;
	}
	return false;
}

function forum_delete($pageid,$featureid,$sectionid){
	execute_db_sql("DELETE FROM pages_features WHERE feature='forum' AND pageid='$pageid' AND featureid='$featureid'");
	execute_db_sql("DELETE FROM forum WHERE forumid='$featureid'");
	execute_db_sql("DELETE FROM forum_categories WHERE forumid='$featureid'");
	execute_db_sql("DELETE FROM forum_discussions WHERE forumid='$featureid'");
	execute_db_sql("DELETE FROM forum_posts WHERE forumid='$featureid'");
	execute_db_sql("DELETE FROM settings WHERE pageid='$pageid' AND type='forum' AND featureid='$featureid'");
	
	resort_page_features($pageid);
}

function forum_buttons($pageid,$featuretype,$featureid){
global $CFG,$USER;
	$returnme = "";
	if(user_has_ability_in_page($USER->userid,"createforumcategory",$pageid)){ 
        $returnme .= make_modal_links(array("title"=>"Create Forum Category","path"=>$CFG->wwwroot."/features/forum/forum.php?action=createforumcategory&amp;pageid=$pageid&amp;forumid=$featureid","width"=>"350","height"=>"200","validate"=>"true","runafter"=>"forum_refresh_$featureid","image"=>$CFG->wwwroot.'/images/add.png',"class"=>"slide_menu_button")); 
    }
	return $returnme;
}

function forum_default_settings($feature,$pageid,$featureid){
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Forum",false,"Forum","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","discussionsperpage","10",false,"10","Discussions Per Page","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","postsperpage","10",false,"10","Posts Per Page","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","shoutboxlimit","10",false,"10","Shoutbox Posts Shown","text",true,"<=0","Must be greater than 0.");
	return $settings_array;
}
?>