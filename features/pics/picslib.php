<?php
/***************************************************************************
* picslib.php - Pics function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 4/3/2014
* Revision: 2.3.4
***************************************************************************/

if (!isset($LIBHEADER)) { if (file_exists('./lib/header.php')) { include('./lib/header.php'); }elseif (file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif (file_exists('../../lib/header.php')) { include('../../lib/header.php'); }}
$PICSLIB = true;

function display_pics($pageid,$area,$featureid) {
global $CFG, $USER, $ROLES;

	if (!$settings = fetch_settings("pics",$featureid,$pageid)) {
		make_or_update_settings_array(default_settings("pics",$pageid,$featureid));
		$settings = fetch_settings("pics",$featureid,$pageid);
	}

	$title = $settings->pics->$featureid->feature_title->setting;

	if (is_logged_in()) {
		if (user_has_ability_in_page($USER->userid,"viewpics",$pageid,"pics",$featureid)) {
			if ($pageid==$CFG->SITEID) {
				$SQL = "SELECT * FROM pics_features WHERE pageid='$pageid' LIMIT 1";
				if ($sections = get_db_result($SQL)) {
					while ($row = fetch_row($sections)) {
						$content = get_gallery_links($pageid, $featureid, true);
						$buttons = get_button_layout("pics_features",$row['featureid'],$pageid);
						return get_css_box($title,$content,$buttons,NULL,"pics",$featureid);
					}
				}
			} else {
				$SQL = "SELECT * FROM pics_features WHERE featureid='$featureid'";
				if ($sections = get_db_result($SQL)) {
					while ($row = fetch_row($sections)) {
						$content = get_gallery_links($pageid, $featureid);
						$buttons = get_button_layout("pics_features",$featureid,$pageid);
						return get_css_box($title,$content,$buttons,NULL,"pics",$featureid);
					}
				}
			}
		}
	} else {
		if (role_has_ability_in_page($ROLES->visitor,"viewpics",$pageid)) {
			$title = get_db_field("setting", "settings", "type='pics' AND pageid=$pageid AND featureid=$featureid");
			$content = get_gallery_links($pageid, $featureid, true);
			return get_css_box($title,$content,NULL,NULL,"pics",$featureid);
		}
	}
}

function get_pics_manager($pageid,$featureid) {
global $CFG,$MYVARS,$USER;
    $returnme = '';
    if (!user_has_ability_in_page($USER->userid,"managepics",$pageid)) { return get_page_error_message("no_permission",array("managepics")); }

	if ($pageid == $CFG->SITEID) {
		$SQL = "SELECT DISTINCT galleryid, galleryid, gallery_title FROM pics p WHERE (p.pageid='$pageid' AND p.featureid=$featureid) OR (p.siteviewable=1) ORDER BY p.galleryid";
	} else {
		$SQL = "SELECT DISTINCT galleryid, galleryid, gallery_title FROM pics p WHERE p.pageid='$pageid' AND p.featureid=$featureid ORDER BY p.galleryid";
	}

	if ($allgalleries = get_db_result($SQL)) {
			$g = 0;
	  	$gallerylist = new \stdClass;
	    $gallerylist->$g = new \stdClass;
			$gallerylist->$g->name = "All";
			$gallerylist->$g->value = "all";
			$g++;
			while ($galleries = fetch_row($allgalleries)) {
        $gallerylist->$g = new \stdClass;
				$gallerylist->$g->name = $galleries["gallery_title"];
				$gallerylist->$g->value = $galleries["galleryid"];
				$g++;
			}

		$returnme .= '<div id="loading_overlay" style="text-align: center; position: absolute; width: 98%; z-index:4;height: 98%; background-color: white; opacity: 0.6; visibility: hidden;"><br /><br /><br /><img src="' . $CFG->wwwroot . '/images/loading_large.gif" /></div>';
		$gallery_select = 'Select which gallery you wish to view. '.make_select_from_array("gallery", $gallerylist, "value", "name", false, "" , 'onchange="$(\'#loading_overlay\').show(); ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid=\'+$(\'#gallery\').val()+\'&amp;editable=true\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }},true);"',false);
        $returnme .= $gallery_select . ' Click on a picture to activate or deactivate it.';
        $returnme .= '<a title="Delete Gallery" style="float:right;padding:2px;" href="javascript: void(0);" onclick="if ($(\'#gallery\').val() != \'all\') { ajaxapi(\'/features/pics/pics_ajax.php\',\'delete_gallery\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid=\'+$(\'#gallery\').val(),function() { if (xmlHttp.readyState == 4) { simple_display(\'pics_manager\'); }},true); } else { alert(\'Cannot delete all galleries at once.\') }"><img src="'.$CFG->wwwroot.'/images/trash.gif" /></a>';
		$returnme .= '<span id="searchcontainer">'.get_pics($pageid,$featureid,"all",0,"true").'</span>';
	} else { $returnme .= '<br /><br /><div style="text-align:center">No images have been added.</div>'; }

    return $returnme;
}

function get_gallery_links($pageid,$featureid,$allsections = false) {
global $CFG;
	$path = $CFG->wwwroot.'/features/pics/files/';
	$section = $allsections ? "" : "AND p.featureid=$featureid";
	if ($pageid == $CFG->SITEID) {
		$SQL = "SELECT * FROM pics p LEFT JOIN pics_galleries pg ON pg.galleryid = p.galleryid WHERE (p.pageid='$pageid' $section AND p.sitehidden=0) OR (p.siteviewable=1 and p.sitehidden=0) ORDER BY p.galleryid DESC,p.dateadded ASC";
  } else {
		$SQL = "SELECT * FROM pics p LEFT JOIN pics_galleries pg ON pg.galleryid = p.galleryid WHERE p.pageid='$pageid' $section AND p.pagehidden=0 ORDER BY p.galleryid DESC,p.dateadded ASC";
	}

	$returnme = $group = ""; $display = true;
	if ($result = get_db_result($SQL)) {
		$gallery = "";
		while ($row = fetch_row($result)) {
			$display = $gallery == "" || $gallery != $row['galleryid'] ? true : false;
			$display = $display ? '' : 'display:none;';
      $returnme .= empty($display) ? make_modal_links(array("id"=>"pic_".$row["picsid"],"title"=> stripslashes($row['caption']),"text"=>$row['name'],"gallery"=>"pics_gallery_".$row['galleryid'],"path"=>$path.$row['pageid']."/".$row['featureid']."/".$row['imagename'],"styles"=>$display)) : '<a href="'.$path.$row['pageid']."/".$row['featureid']."/".$row['imagename'].'" title="'.stripslashes($row['caption']).'" data-rel="pics_gallery_'.$row['galleryid'].'" style="'.$display.'"></a>';
			$returnme .= $display == "" ? '<br />' : '';
			$gallery = $row["galleryid"];
		}
	} else { $returnme = '<div style="text-align:center;padding:7px">No images have been added.</div>';}
	return $returnme;
}

function get_pics($pageid,$featureid,$galleryid='all',$pagenum=0,$editable='false', $perpage = 8, $order='dateadded DESC') {
global $CFG,$USER;

	$pagenum = !$pagenum ? 0 : $pagenum;
	$order = !$order ? 'dateadded DESC' : $order;
	$perpage = !$perpage ? 8 : $perpage;

	$deletepic = ""; $activated = ""; $whichgallery="";

	$sitehidden = $editable ? "" : "AND sitehidden=0";
	$pagehidden = $editable ? "" : "AND pagehidden=0";

	if ($galleryid == "all") { //Show only 1 gallery or all galleries
		$full_order = "galleryid," . $order;
	} else {
		$whichgallery = " AND galleryid=$galleryid";
		$full_order = $order;
	}

    if ($pageid == $CFG->SITEID) {
		$SQL = "SELECT * FROM pics WHERE (pageid='$pageid' AND featureid=$featureid $whichgallery $sitehidden) OR (siteviewable=1 $sitehidden $whichgallery) ORDER BY $full_order";
	} else {
		$SQL = "SELECT * FROM pics WHERE pageid='$pageid' AND featureid=$featureid $whichgallery $pagehidden ORDER BY $full_order";
	}

	$total = get_db_count($SQL); //get the total for all pages returned.

	$firstonpage = $perpage * $pagenum;
    $limit = " LIMIT $firstonpage," . $perpage;
	$SQL .= $limit; //Limit to one page of return.

	if ($pages = get_db_result($SQL)) {
		$count = $total > (($pagenum+1) * $perpage) ? $perpage : $total - (($pagenum) * $perpage); //get the amount returned...is it a full page of results?
	    $amountshown = $firstonpage + $perpage < $total ? $firstonpage + $perpage : $total;
	    $prev = $pagenum > 0 ? '<a href="javascript: document.getElementById(\'loading_overlay\').style.visibility=\'visible\'; ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid='.$galleryid.'&amp;editable='.$editable.'&amp;perpage='.$perpage.'&amp;order='.urlencode($order).'&amp;pagenum=' . ($pagenum - 1) . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true);" onmouseup="this.blur();"><img src="' . $CFG->wwwroot . '/images/prev.gif" title="Previous Page" alt="Previous Page" /></a>' : "";
	    $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
	    $next = $firstonpage + $perpage < $total ? '<a href="javascript: document.getElementById(\'loading_overlay\').style.visibility=\'visible\'; ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid='.$galleryid.'&amp;editable='.$editable.'&amp;perpage='.$perpage.'&amp;order='.urlencode($order).'&amp;pagenum=' . ($pagenum + 1) . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true);" onmouseup="this.blur();"><img src="' . $CFG->wwwroot . '/images/next.gif" title="Next Page" alt="Next Page" /></a>' : "";
 		$header = '<table style="width:100%;"><tr style="height:45px;"><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><p>';

		$returnme = '<div style="width:760px;overflow:auto;margin-right:auto;margin-left:auto;">';
		while ($row = fetch_row($pages)) {
			if (file_exists($CFG->dirroot.'/features/pics/files/'.$row["pageid"]."/".$row["featureid"]."/".$row['imagename'])) {
				$filepath = $CFG->dirroot.'/features/pics/files/'.$row["pageid"]."/".$row["featureid"]."/".$row['imagename'];
				$webpath = $CFG->wwwroot.'/features/pics/files/'.$row["pageid"]."/".$row["featureid"]."/".$row['imagename'];
				$mypicture = getimagesize($filepath);
			} else {
				$filepath = $CFG->dirroot."/images/not_found.jpg";
				$webpath = $CFG->wwwroot."/images/not_found.jpg";
				$mypicture = getimagesize($filepath);
			}

			if ($editable != 'false') {
				$deletepic = user_has_ability_in_page($USER->userid,"deletepics",$pageid,"pics",$featureid) ? ' <a href="javascript: if (confirm(\'Do you want to delete this image?\')) { ajaxapi(\'/features/pics/pics_ajax.php\',\'delete_pic\',\'&amp;picsid='.$row['picsid'].'\',function() {do_nothing();}); document.getElementById(\'loading_overlay\').style.visibility=\'visible\'; ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid='.$galleryid.'&amp;editable='.$editable.'&amp;perpage='.$perpage.'&amp;order='.urlencode($order).'&amp;pagenum=' . ($pagenum) . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true);}"><img style="position:absolute; z-index:2; border:none;" src="'.$CFG->wwwroot.'/images/trash.gif" title="Delete" alt="Delete Feature" /></a>' : '';
				if (($pageid == $CFG->SITEID && $row["sitehidden"] == 1) || ($pageid != $CFG->SITEID && $row["pagehidden"] == 1)) {
					$activated = '';
				} else { //image is activated
					$activated = 'background-color:#FFFF66;';
				}
			}

			$disabled =  $pageid == $CFG->SITEID && $row["pageid"] == $pageid ? "DISABLED" : "";
			$captionsize = $editable != 'false' ? "height:40px;width:155px;" : "height:80px;width:170px;";
			$checked = $row["siteviewable"] == 1 ? " checked=checked" : "";
			$alreadysite1 = $pageid == $CFG->SITEID && $row["pageid"] != $pageid ? 'do_nothing();' : 'simple_display(\'picsid_'.$row["picsid"].'\'); setTimeout(function() { document.getElementById(\'picsid_'.$row["picsid"].'\').style.visibility=\'hidden\'; },3000); ';
			$alreadysite2 = $pageid == $CFG->SITEID && $row["pageid"] != $pageid ? 'ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid='.$galleryid.'&amp;editable='.$editable.'&amp;perpage='.$perpage.'&amp;order='.urlencode($order).'&amp;pagenum=' . ($pagenum) . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true);' : '';
			$caption = $editable != 'false' ? '<textarea id="caption_'.$row["picsid"].'" style="margin-left:2px;font-size:1em;'.$captionsize.'" type="text">'.stripslashes($row["caption"]).'</textarea><a onclick="document.getElementById(\'picsid_'.$row["picsid"].'\').style.visibility=\'visible\'; ajaxapi(\'/features/pics/pics_ajax.php\',\'save_caption\',\'&amp;picsid='.$row["picsid"].'&amp;caption=\'+escape(document.getElementById(\'caption_'.$row["picsid"].'\').value),function() { simple_display(\'picsid_'.$row["picsid"].'\')}); setTimeout(function() { document.getElementById(\'picsid_'.$row["picsid"].'\').style.visibility=\'hidden\'; },3000);"><img style="position:absolute;top:2px;" src="'.$CFG->wwwroot.'/images/save.png" /></a><span style="font-size:.85em;"><input type="checkbox" id="siteviewable_'.$row["picsid"].'" '.$disabled.' onchange="if (confirm(\'Do you want to change the site viewability of this image?\')) { document.getElementById(\'picsid_'.$row["picsid"].'\').style.visibility=\'visible\'; ajaxapi(\'/features/pics/pics_ajax.php\',\'save_viewability\',\'&amp;picsid='.$row["picsid"].'&amp;siteviewable=\'+document.getElementById(\'siteviewable_'.$row["picsid"].'\').checked,function() { '.$alreadysite1.' }); '.$alreadysite2.' } else { if (document.getElementById(\'siteviewable_'.$row["picsid"].'\').checked == true) { document.getElementById(\'siteviewable_'.$row["picsid"].'\').checked = false; } else { document.getElementById(\'siteviewable_'.$row["picsid"].'\').checked = true; } } blur();"'.$checked.' /><span style="position:relative;top:-3px;">Site Viewable</span></span>' : '<div style="font-size:.85em;'.$captionsize.'">'.stripslashes($row["caption"]).'</div>';

			if ($row["pageid"] != $pageid) { //this image is from another page and must be copied rather than moved.
				$movepics = $editable != 'false' ? '<div style="display:block;position:relative;text-align:center;width:171px;">'.make_select('movepics',get_db_result("SELECT * FROM pics_galleries WHERE pageid=".$pageid),"galleryid","name",false,'onchange=""',true,NULL,"font-size:.85em;width:170px;","Copy to Gallery...not working",$row["galleryid"]).'</div>' : '';
			} else {
				$movepics = $editable != 'false' ? '<div style="display:block;position:relative;text-align:center;width:171px;">'.make_select('movepics_'.$row["picsid"],get_db_result("SELECT * FROM pics_galleries WHERE pageid=".$pageid),"galleryid","name",false,'onchange="if (document.getElementById(\'movepics_'.$row["picsid"].'\').value != \'\' && confirm(\'Do you want to move this image to another gallery?\')) {ajaxapi(\'/features/pics/pics_ajax.php\',\'move_pic\',\'&amp;picsid='.$row["picsid"].'&amp;galleryid=\'+document.getElementById(\'movepics_'.$row["picsid"].'\').value,function() {do_nothing();}); document.getElementById(\'loading_overlay\').style.visibility=\'visible\'; ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'&amp;galleryid='.$galleryid.'&amp;editable='.$editable.'&amp;perpage='.$perpage.'&amp;order='.urlencode($order).'&amp;pagenum=' . ($pagenum) . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); document.getElementById(\'loading_overlay\').style.visibility=\'hidden\'; }},true);} else { change_selection(\'movepics_'.$row["picsid"].'\',\'\'); blur();}"',true,NULL,"font-size:.85em;width:170px;","Move to Gallery...",$row["galleryid"]).'</div>' : '';
			}

			$returnme .= '<div style="padding: 3px; border:1px solid #96E4D7; margin:3px; float: left; width:171px;">
								<div id="picsid_'.$row["picsid"].'" style="text-align:center; padding-top:70px;font-size:1.5em;width:171px; z-index:3;height: 145px; background-color: white; opacity: 0.6; position:absolute;visibility: hidden;"></div>
									<span id="activated_picsid_'.$row["picsid"].'">
										<div style="overflow:hidden;text-align:center;width:171px;font-size:.85em;'.$activated.'">
										'.$row["imagename"].'
										</div>
									</span>
									<div style="display:block;position:relative;text-align:center;width:171px;">
										<table>
											<tr>
												<td>
													<div style="width:165px; height:130px; overflow:hidden; text-align:center;">
														'.$deletepic.'<a onclick="blur();" href="javascript: ajaxapi(\'/features/pics/pics_ajax.php\',\'toggle_activate\',\'&amp;pageid='.$pageid.'&amp;picsid='.$row["picsid"].'\',function() {simple_display(\'activated_picsid_'.$row["picsid"].'\');});"><img src="'.$webpath.'"'.imgResize($mypicture[0],$mypicture[1], 165) . ' /></a>
													</div>
												</td>
											</tr>
										</table>
									</div>
									<div style="display:block;position:relative;text-align:left;width:171px;">
										'.$caption.'
									</div>
									'.$movepics.'
							</div>';
		}
	return $header . $returnme . '</div>';
	} else {
		return '<br /><br /><div style="text-align:center"><strong>No images have been added.</strong></div>';
	}
}

function pics_delete($pageid,$featureid) {
global $CFG;
	if (isset($featureid)) { //Pics section delete
		recursive_delete($CFG->dirroot.'/features/pics/files/'.$pageid."/".$featureid);
		execute_db_sql("DELETE FROM pages_features WHERE feature='pics' AND pageid='$pageid' AND featureid='$featureid'");
		execute_db_sql("DELETE FROM pics_features WHERE pageid='$pageid' and featureid='$featureid'");
		execute_db_sql("DELETE FROM pics_galleries WHERE pageid='$pageid' and featureid='$featureid'");
		execute_db_sql("DELETE FROM pics WHERE pageid='$pageid' and featureid='$featureid'");
		execute_db_sql("DELETE FROM settings WHERE pageid='$pageid' AND type='pics' AND featureid='$featureid'");

		resort_page_features($pageid);
	}
}

//Just changes the view size of an image
function imgResize($width, $height, $target) {
	//takes the larger size of the width and height and applies the formula. Your function is designed to work with any image in any size.
	if ($width > $height) {
        $percentage = ($target / $width);
	} else {
        $percentage = ($target / $height);
	}
	//gets the new value and applies the percentage, then rounds the value
	$width = round($width * $percentage);
	$height = round($height * $percentage);
	//returns the new sizes in html image tag format...this is so you can plug this function inside an image tag so that it will set the image to the correct size, without putting a whole script into the tag.
	return "width=\"$width\" height=\"$height\"";
}

//Changes the actual pixels of an image
function resizeImage($name,$filename,$new_w,$new_h) {
	$system=explode(".",$name);
	if (preg_match("/jpg|jpeg/",strtolower($system[1]))) {$src_img=imagecreatefromjpeg($name);}
	if (preg_match("/gif/",strtolower($system[1]))) {$src_img=imagecreatefromgif ($name);}
	if (preg_match("/png/",strtolower($system[1]))) {$src_img=imagecreatefrompng($name);}
	if (isset($src_img)) {
    	$old_x=imageSX($src_img);
    	$old_y=imageSY($src_img);
    	if ($old_x < $new_w && $old_y < $new_h) { return false; }
    	if ($old_x > $old_y) {
    		$thumb_w=$new_w;
    		$thumb_h=$old_y*($new_h/$old_x);
    	}
    	if ($old_x < $old_y) {
    		$thumb_w=$old_x*($new_w/$old_y);
    		$thumb_h=$new_h;
    	}
    	if ($old_x == $old_y) {
    		$thumb_w=$new_w;
    		$thumb_h=$new_h;
    	}
    	$dst_img=ImageCreateTrueColor($thumb_w,$thumb_h);
    	imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y);
    	if (preg_match("/png/",$system[1])) {
    		imagepng($dst_img,$filename);
    	} else {
    		imagejpeg($dst_img,$filename);
    	}
    	imagedestroy($dst_img);
    	imagedestroy($src_img);
	}
}

function insert_blank_pics($pageid) {
global $CFG;
	if ($featureid = execute_db_sql("INSERT INTO pics_features (pageid) VALUES('$pageid')")) {
		$type = "pics";
		$area = get_db_field("default_area", "features", "feature='pics'");
		$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid,feature,sort,area,featureid) VALUES('$pageid','pics','$sort','$area','$featureid')");
		return $featureid;
	}
	return false;
}

function pics_buttons($pageid,$featuretype,$featureid) {
global $CFG,$USER;
	$returnme = "";
	if (strstr($featuretype,"_features")) {
		$pics_abilities = get_user_abilities($USER->userid,$pageid,"pics","pics",$featureid);
		$feature_abilities = get_user_abilities($USER->userid,$pageid,"features","pics",$featureid);

        if (!empty($pics_abilities->managepics->allow) && get_db_row("SELECT * FROM pics WHERE pageid='$pageid' and featureid='$featureid'")) {
            $returnme .= make_modal_links(array("title"=> "Manage Galleries","path"=>$CFG->wwwroot."/features/pics/pics.php?action=manage_pics&amp;pageid=$pageid&amp;featureid=$featureid","refresh"=>"true","image"=>$CFG->wwwroot."/images/swap.png","class"=>"slide_menu_button"));
        }

        if (!empty($pics_abilities->addpics->allow)) {
            $returnme .= make_modal_links(array("title"=> "Add Images","path"=>$CFG->wwwroot."/features/pics/pics.php?action=add_pics&amp;pageid=$pageid&amp;featureid=$featureid","iframe"=>"true","refresh"=>"true","width"=>"640","height"=>"500","image"=>$CFG->wwwroot."/images/add.png","class"=>"slide_menu_button"));
        }
    }
	return $returnme;
}

function pics_default_settings($feature,$pageid,$featureid) {
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","Image Gallery",false,"Image Gallery","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","picsperpage","12",false,"12","Pictures Per Page","text",true,"<=0","Must be greater than 0.");
	return $settings_array;
}
?>
