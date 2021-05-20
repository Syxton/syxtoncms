<?php
/***************************************************************************
* bloglockerlib.php - Blog Locker function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2013
* Revision: 0.9.1
***************************************************************************/

if(!isset($LIBHEADER)){ if(file_exists('./lib/header.php')){ include('./lib/header.php'); }elseif(file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif(file_exists('../../lib/header.php')){ include('../../lib/header.php'); }}
$BLOGLOCKERLIB = true;

//BLOGLOCKERLIB Config
$CFG->bloglocker = new \stdClass; 
$CFG->bloglocker->viewable_limit = 20;

function display_bloglocker($pageid,$area,$featureid){
global $CFG, $USER, $ROLES;

	$content="";

	if(!$settings = fetch_settings("bloglocker",$featureid,$pageid)){
		make_or_update_settings_array(default_settings("bloglocker",$pageid,$featureid));
		$settings = fetch_settings("bloglocker",$featureid,$pageid);
	}

	$title = $settings->bloglocker->$featureid->feature_title->setting;
	$viewable_limit = $settings->bloglocker->$featureid->viewable_limit->setting;
	
	if(get_db_count("SELECT * FROM pages_features pf WHERE pf.pageid='$pageid' AND pf.feature='html' AND pf.area='locker'")){
		if(is_logged_in()){
			if(user_has_ability_in_page($USER->userid,"viewbloglocker",$pageid)){
				if($area == "middle"){
					$lockeritems = get_bloglocker($pageid);
					$i=0;
                    foreach($lockeritems as $lockeritem){
                        if(++$i > $viewable_limit){ break; }
                        $content .= '<span style="color:gray;font-size:.75em;">'.date('m/d/Y',$lockeritem->dateposted).' </span>';
                        $content .= make_modal_links(array("title"=>$lockeritem->title,"path"=>$CFG->wwwroot."/features/bloglocker/bloglocker.php?action=view_locker&amp;pageid=$pageid&amp;htmlid=$lockeritem->htmlid"));
						if(!$lockeritem->blog && user_has_ability_in_page($USER->userid,"addtolocker",$pageid)){ $content .= '<a title="Send to middle" href="javascript: ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid='.$pageid.'&amp;featuretype=html&amp;featureid='.$lockeritem->htmlid.'&amp;direction=middle\',function() { update_login_contents('.$pageid.');});"><img src="'.$CFG->wwwroot.'/images/undo.png" alt="Move feature to the middle area" /></a>';	}
						$content .= '<br />';
                    }
				}else{
					$lockeritems = get_bloglocker($pageid);
					$i=0;
                    foreach($lockeritems as $lockeritem){
                        if(++$i > $viewable_limit){ break; }
                        $content .= '<span style="color:gray;font-size:.75em;">'.date('m/d/Y',$lockeritem->dateposted).' </span>';
						$content .= make_modal_links(array("title"=>$lockeritem->title,"path"=>$CFG->wwwroot."/features/bloglocker/bloglocker.php?action=view_locker&amp;pageid=$pageid&amp;htmlid=$lockeritem->htmlid"));
                        if(!$lockeritem->blog && user_has_ability_in_page($USER->userid,"addtolocker",$pageid)){ $content .= '<a title="Move feature to middle area" href="javascript: ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid='.$pageid.'&amp;featuretype=html&amp;featureid='.$lockeritem->htmlid.'&amp;direction=middle\',function() { update_login_contents('.$pageid.');});"><img src="'.$CFG->wwwroot.'/images/undo.png" alt="Move feature to the middle area" /></a>';}	
						$content .= '<br />';
                    }
				}
				$buttons = get_button_layout("bloglocker",$featureid,$pageid); 
				return get_css_box($title,$content,$buttons,NULL,"bloglocker",$featureid);
			}
		}else{
			if(role_has_ability_in_page($ROLES->visitor,"viewbloglocker",$pageid)){
				if($area == "middle"){
					$lockeritems = get_bloglocker($pageid);
                    $i=0;
                    foreach($lockeritems as $lockeritem){
                        if(++$i > $viewable_limit){ break; }
                        $content .= '<span style="color:gray;font-size:.75em;">'.date('m/d/Y',$lockeritem->dateposted).' </span>';
						$content .= make_modal_links(array("title"=>$lockeritem->title,"path"=>$CFG->wwwroot."/features/bloglocker/bloglocker.php?action=view_locker&amp;pageid=$pageid&amp;htmlid=$lockeritem->htmlid")).'<br />';
                    }
				}else{
					$lockeritems = get_bloglocker($pageid);
					$i=0;
                    foreach($lockeritems as $lockeritem){
                        if(++$i > $viewable_limit){ break; }
						$content .= '<span style="color:gray;font-size:.75em;">'.date('m/d/Y',$lockeritem->dateposted).' </span>';
						$content .= make_modal_links(array("title"=>$lockeritem->title,"path"=>$CFG->wwwroot."/features/bloglocker/bloglocker.php?action=view_locker&amp;pageid=$pageid&amp;htmlid=$lockeritem->htmlid")).'<br />';                   
                    }
				}
				$buttons = get_button_layout("bloglocker",$featureid,$pageid); 
				return get_css_box($title,$content,$buttons,NULL,"bloglocker",$featureid);
			}
		}
	}else{
		$buttons = get_button_layout("bloglocker",$featureid,$pageid); 
		return get_css_box($title,"The blog locker is empty at this time.",$buttons,NULL,"bloglocker",$featureid);
	}
}

function get_bloglocker($pageid){
global $CFG;
	$SQL = "SELECT * FROM pages_features pf INNER JOIN html h ON h.htmlid=pf.featureid WHERE pf.pageid='$pageid' AND pf.feature='html' AND pf.area='locker' ORDER BY h.dateposted DESC";

	$i=0;
	if($result = get_db_result($SQL)){
        $lockeritems = new \stdClass; 
		while($row = fetch_row($result)){
			$featureid = $row["htmlid"];
			
			if(!$settings = fetch_settings("html",$featureid,$pageid)){
				make_or_update_settings_array(default_settings("html",$pageid,$featureid));
				$settings = fetch_settings("html",$featureid,$pageid);
			}
            
            $lockeritems->$i = new \stdClass;
			$lockeritems->$i->htmlid = $featureid;
			$lockeritems->$i->blog = $settings->html->$featureid->blog->setting;
			$lockeritems->$i->title = $settings->html->$featureid->feature_title->setting;
			$lockeritems->$i->dateposted = $row["dateposted"];
			$i++;	
		}
		return $lockeritems;
	}
	return false;
}

function bloglocker_delete($pageid,$featureid,$sectionid){
	execute_db_sql("DELETE FROM pages_features WHERE feature='bloglocker' AND pageid='$pageid' AND featureid='$featureid'");
	resort_page_features($pageid);
}

function bloglocker_buttons($pageid,$featuretype,$featureid){
	global $CFG,$USER;
	$returnme = "";
	return $returnme;
}

function bloglocker_default_settings($feature,$pageid,$featureid){
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Blog Locker",false,"Blog Locker","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","viewable_limit","20",false,"20","Viewable Blog Limit","text",true,"<= 0","Must be greater than 0.");
	return $settings_array;
}
?>