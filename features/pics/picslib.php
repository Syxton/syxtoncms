<?php
/***************************************************************************
* picslib.php - Pics function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.3.4
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define('PICSLIB', true);

function display_pics($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES;

	if (!$settings = fetch_settings("pics", $featureid, $pageid)) {
		save_batch_settings(default_settings("pics", $pageid, $featureid));
		$settings = fetch_settings("pics", $featureid, $pageid);
	}

	$title = $settings->pics->$featureid->feature_title->setting;

	if (is_logged_in()) {
		$title = '<span class="box_title_text">' . $title . '</span>';
		if (user_is_able($USER->userid, "viewpics", $pageid,"pics", $featureid)) {
			if ($pageid ==$CFG->SITEID) {
				$SQL = "SELECT * FROM pics_features WHERE pageid='$pageid' LIMIT 1";
				if ($sections = get_db_result($SQL)) {
					while ($row = fetch_row($sections)) {
						$content = get_gallery_links($pageid, $featureid, true);
						$buttons = get_button_layout("pics_features", $row['featureid'], $pageid);
						return get_css_box($title, $content, $buttons,NULL,"pics", $featureid);
					}
				}
			} else {
				$SQL = "SELECT * FROM pics_features WHERE featureid='$featureid'";
				if ($sections = get_db_result($SQL)) {
					while ($row = fetch_row($sections)) {
						$content = get_gallery_links($pageid, $featureid);
						$buttons = get_button_layout("pics_features", $featureid, $pageid);
						return get_css_box($title, $content, $buttons,NULL,"pics", $featureid);
					}
				}
			}
		}
	} else {
		if (role_is_able($ROLES->visitor,"viewpics", $pageid)) {
			$title = get_db_field("setting", "settings", "type='pics' AND pageid=$pageid AND featureid=$featureid");
			$content = get_gallery_links($pageid, $featureid, true);
			$title = '<span class="box_title_text">' . $title . '</span>';
			return get_css_box($title, $content,NULL,NULL,"pics", $featureid);
		}
	}
}

function get_pics_manager($pageid, $featureid) {
global $CFG, $MYVARS, $USER;
    $returnme = '';
    if (!user_is_able($USER->userid, "managepics", $pageid)) {
        return trigger_error(error_string("no_permission", ["managepics"]));
	}

	$SQL = fetch_template("dbsql/pics.sql", "get_galleries", "pics", ["siteviewable" => ($pageid == $CFG->SITEID ? true : false)]);
	$params = [
		"pageid" => $pageid,
		"featureid" => $featureid,
	];
	if ($allgalleries = get_db_result($SQL, $params)) {
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

		$params = [
			"properties" => [
				"name" => "gallery",
				"id" => "gallery",
				"onchange" => '$(\'#loading_overlay\').show();
								ajaxapi(\'/features/pics/pics_ajax.php\',
										\'pics_pageturn\',
										\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=\' + $(\'#gallery\').val() + \'&amp;editable=true\',
										function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true);',
			],
			"values" => $gallerylist,
			"valuename" => "value",
			"displayname" => "name",
		];
		$gallery_select = 'Select which gallery you wish to view. ' . make_select($params);
        $returnme .= $gallery_select . ' Click on a picture to activate or deactivate it.';
        $returnme .= '<a title="Delete Gallery" style="float:right;padding:2px;" href="javascript: void(0);" onclick="if ($(\'#gallery\').val() != \'all\') { ajaxapi(\'/features/pics/pics_ajax.php\',\'delete_gallery\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=\'+$(\'#gallery\').val(),function() { if (xmlHttp.readyState == 4) { simple_display(\'pics_manager\'); }}, true); } else { alert(\'Cannot delete all galleries at once.\') }"><img src="' . $CFG->wwwroot . '/images/trash.gif" /></a>';
		$returnme .= get_searchcontainer(get_pics($pageid, $featureid, "all", 0, "true"));
	} else {
		$returnme .= '<br /><br /><div style="text-align:center">No images have been added.</div>';
	}

    return $returnme;
}

function get_gallery_links($pageid, $featureid, $allsections = false) {
global $CFG;
	$path = $CFG->wwwroot . '/features/pics/files/';
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
			if (empty($display)) {
				$returnme .= make_modal_links([
								"id" => "pic_" . $row["picsid"],
								"title" => stripslashes($row['caption']),
								"text" => $row['name'],
								"gallery" => "pics_gallery_" . $row['galleryid'],
								"path" => $path . $row['pageid'] . "/" . $row['featureid'] . "/" . $row['imagename'],
								"styles" => $display,
							]);
			} else {
				$returnme .= '<a href="' . $path . $row['pageid'] . "/" . $row['featureid'] . "/" . $row['imagename'] . '" title="' . stripslashes($row['caption']) . '" data-rel="pics_gallery_'  .$row['galleryid'] . '" style="' . $display . '"></a>';
			}
			$returnme .= $display == "" ? '<br />' : '';
			$gallery = $row["galleryid"];
		}
	} else { $returnme = '<div style="text-align:center;padding:7px">No images have been added.</div>';}
	return $returnme;
}

function get_pics($pageid, $featureid, $galleryid='all', $pagenum=0, $editable='false', $perpage = 8, $order='dateadded DESC') {
global $CFG, $USER;

	$pagenum = !$pagenum ? 0 : $pagenum;
	$perpage = !$perpage ? 8 : $perpage;

	if ($galleryid == "all") { //Show only 1 gallery or all galleries
		$whichgallery = "";
		$full_order = "galleryid," . $order;
	} else {
		$whichgallery = " AND galleryid=$galleryid";
		$full_order = $order;
	}

	$sitehidden = $editable ? "" : "AND sitehidden=0";
	$pagehidden = $editable ? "" : "AND pagehidden=0";

    if ($pageid == $CFG->SITEID) {
		$SQL = "SELECT * FROM pics WHERE (pageid='$pageid' AND featureid=$featureid $whichgallery $sitehidden) OR (siteviewable=1 $sitehidden $whichgallery) ORDER BY $full_order";
	} else {
		$SQL = "SELECT * FROM pics WHERE pageid='$pageid' AND featureid=$featureid $whichgallery $pagehidden ORDER BY $full_order";
	}

	$total = get_db_count($SQL); // get the total for all pages returned.

	$firstonpage = $perpage * $pagenum;
    $limit = " LIMIT $firstonpage," . $perpage;
	$SQL .= $limit; //Limit to one page of return.
	if ($pages = get_db_result($SQL)) {
		$deletepic = ""; $activated = "";
		$count = $total > (($pagenum + 1) * $perpage) ? $perpage : $total - (($pagenum) * $perpage); //get the amount returned...is it a full page of results?
		  $amountshown = $firstonpage + $perpage < $total ? $firstonpage + $perpage : $total;
		  $prev = $pagenum > 0 ? '<a href="javascript: $(\'#loading_overlay\').show(); ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=' . $galleryid . '&amp;editable=' . $editable . '&amp;perpage=' . $perpage . '&amp;order=' . urlencode($order) . '&amp;pagenum=' . ($pagenum - 1) . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true);" onmouseup="this.blur();"><img src="' . $CFG->wwwroot . '/images/prev.png" title="Previous Page" alt="Previous Page" /></a>' : "";
		  $info = 'Viewing ' . ($firstonpage + 1) . " through " . $amountshown . " out of $total";
		  $next = $firstonpage + $perpage < $total ? '<a href="javascript: $(\'#loading_overlay\').show(); ajaxapi(\'/features/pics/pics_ajax.php\',\'pics_pageturn\',\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=' . $galleryid . '&amp;editable=' . $editable . '&amp;perpage=' . $perpage . '&amp;order=' . urlencode($order) . '&amp;pagenum=' . ($pagenum + 1) . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true);" onmouseup="this.blur();"><img src="' . $CFG->wwwroot . '/images/next.png" title="Next Page" alt="Next Page" /></a>' : "";
 		$header = '<table style="width:100%;"><tr style="height:45px;"><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><p>';

		$returnme = '<div style="width:760px;overflow:auto;margin-right:auto;margin-left:auto;">';
		while ($row = fetch_row($pages)) {
			if (file_exists($CFG->dirroot . '/features/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row['imagename'])) {
				$filepath = $CFG->dirroot . '/features/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row['imagename'];
				$webpath = $CFG->wwwroot . '/features/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row['imagename'];
				$mypicture = getimagesize($filepath);
			} else {
				$filepath = $CFG->dirroot . "/images/not_found.jpg";
				$webpath = $CFG->wwwroot . "/images/not_found.jpg";
				$mypicture = getimagesize($filepath);
			}

			if ($editable != 'false') {
				if (user_is_able($USER->userid, "deletepics", $pageid, "pics", $featureid)) {
					$deletepic = ' <a href="#" onclick="if (confirm(\'Do you want to delete this image?\')) {
															$(\'#loading_overlay\').show();
															ajaxapi(\'/features/pics/pics_ajax.php\',
																	\'delete_pic\',
																	\'&amp;picsid=' . $row['picsid'] . '\',
																	function() { do_nothing(); });
															ajaxapi(\'/features/pics/pics_ajax.php\',
																	\'pics_pageturn\',
																	\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=' . $galleryid . '&amp;editable=' . $editable . '&amp;perpage=' . $perpage . '&amp;order=' . urlencode($order) . '&amp;pagenum=' . ($pagenum) . '\',
																	function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true);
														}">
										<img style="position:absolute; z-index:2; border:none;" src="' . $CFG->wwwroot . '/images/trash.gif" title="Delete" alt="Delete Feature" />
									</a>';
				}
				
				if (($pageid == $CFG->SITEID && $row["sitehidden"] == 1) || ($pageid != $CFG->SITEID && $row["pagehidden"] == 1)) {
					$activated = '';
				} else { //image is activated
					$activated = 'background-color:#FFFF66;';
				}
				$captionsize = "margin: 0;padding: 5px 0px;font-size: 1em;height: 40px;width: calc(100% - 20px);vertical-align: middle;";
			} else {
				$captionsize = "height: 80px;width: 170px;";
			}

			$disabled =  $pageid == $CFG->SITEID && $row["pageid"] == $pageid ? "DISABLED" : "";
			$checked = $row["siteviewable"] == 1 ? " checked=checked" : "";

			if ($pageid == $CFG->SITEID && $row["pageid"] != $pageid) {
				$alreadysite1 = 'do_nothing();';
				$alreadysite2 = 'ajaxapi(\'/features/pics/pics_ajax.php\',
										 \'pics_pageturn\',
										 \'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=' . $galleryid . '&amp;editable=' . $editable . '&amp;perpage=' . $perpage . '&amp;order=' . urlencode($order) . '&amp;pagenum=' . ($pagenum) . '\',
										 function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true);';
			} else {
				$alreadysite1 = 'simple_display(\'picsid_' . $row["picsid"] . '\');
								 setTimeout(function() { $(\'#picsid_' . $row["picsid"] . '\').hide(); }, 3000);';
				$alreadysite2 = '';
			}
			
			if ($editable != 'false') {
				$caption = '<textarea id="caption_' . $row["picsid"] . '" style="margin-left:2px;font-size:1em;' . $captionsize . '" type="text">' . stripslashes($row["caption"]) . '</textarea>
							<a onclick="$(\'#picsid_' . $row["picsid"] . '\').show();
										ajaxapi(\'/features/pics/pics_ajax.php\',
												\'save_caption\',
												\'&amp;picsid=' . $row["picsid"] . '&amp;caption=\' + encodeURIComponent($(\'#caption_' . $row["picsid"] . '\').val()),
												function() { simple_display(\'picsid_' . $row["picsid"] . '\'); });
												setTimeout(function() { $(\'#picsid_' . $row["picsid"] . '\').hide(); }, 3000);">
								<img src="' . $CFG->wwwroot . '/images/save.png" />
							</a>
							<span style="font-size:.85em;display: inline-block;padding: 5px;">
								<input type="checkbox" style="vertical-align:middle" id="siteviewable_' . $row["picsid"] . '" 
								' . $disabled . '
								 onchange="if (confirm(\'Do you want to change the site viewability of this image?\')) {
												$(\'#picsid_' . $row["picsid"] . '\').show();
												ajaxapi(\'/features/pics/pics_ajax.php\',
												\'save_viewability\',
												\'&amp;picsid=' . $row["picsid"] . '&amp;siteviewable=\' + $(\'#siteviewable_' . $row["picsid"] . '\').prop(\'checked\'),
												function() { ' . $alreadysite1 . ' });
												' . $alreadysite2 . '
											 } else {
												if ($(\'#siteviewable_' . $row["picsid"] . '\').prop(\'checked\')) {
													$(\'#siteviewable_' . $row["picsid"] . '\').prop(\'checked\', false);
												} else {
													$(\'#siteviewable_' . $row["picsid"] . '\').prop(\'checked\', true);
												}
											}
											blur();"' . $checked . ' />
								<span>
									Site Viewable
								</span>
							</span>';
			} else {
				$caption = '<div style="font-size:.85em;' . $captionsize . '">
							' . stripslashes($row["caption"]) . 
							 '</div>';
			}

			if ($row["pageid"] != $pageid) { //this image is from another page and must be copied rather than moved.
				$galleryselect = [
					"properties" => [
						"name" => "movepics",
						"id" => "movepics",
						"style" => "font-size:.85em;width:170px;",
					],
					"values" => get_db_result("SELECT * FROM pics_galleries WHERE pageid = '$pageid'"),
					"valuename" => "galleryid",
					"firstoption" => "Copy to Gallery...not working",
					"displayname" => "name",
					"exclude" => $row["galleryid"],
				];
			} else {
				$galleryselect = [
					"properties" => [
						"name" => 'movepics_' . $row["picsid"],
						"id" => 'movepics_' . $row["picsid"],
						"onchange" => 'if ($(\'#movepics_' . $row["picsid"] . '\').val() != \'\' && confirm(\'Do you want to move this image to another gallery?\')) {
										$(\'#loading_overlay\').show();
										ajaxapi(\'/features/pics/pics_ajax.php\',
												\'move_pic\',
												\'&amp;picsid=' . $row["picsid"] . '&amp;galleryid=\'+$(\'#movepics_' . $row["picsid"] . '\').val(),
												function() { do_nothing(); }); 
										ajaxapi(\'/features/pics/pics_ajax.php\',
												\'pics_pageturn\',
												\'&amp;pageid=' . $pageid . '&amp;featureid=' . $featureid . '&amp;galleryid=' . $galleryid . '&amp;editable=' . $editable . '&amp;perpage=' . $perpage . '&amp;order=' . urlencode($order) . '&amp;pagenum=' . ($pagenum) . '\',
												function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true); } else { change_selection(\'movepics_' . $row["picsid"] . '\', \'\'); blur(); }',
						"style" => "font-size:.85em;width:170px;",
					],
					"values" => get_db_result("SELECT * FROM pics_galleries WHERE pageid = '$pageid'"),
					"valuename" => "galleryid",
					"firstoption" => "Move to Gallery...",
					"displayname" => "name",
					"exclude" => $row["galleryid"],
				];
			}

			$movepics = $editable != 'false' ? '<div style="display:block;position:relative;text-align:center;width:171px;">' . make_select($galleryselect) . '</div>' : '';
			$returnme .= '<div style="padding: 3px; border:1px solid #96E4D7; margin:3px; float: left; width:171px;">
								<div id="picsid_' . $row["picsid"] . '" style="text-align:center; padding-top:70px;font-size:1.5em;width:171px; z-index:3;height: 145px; background-color: white; opacity: 0.6; position:absolute;display:none;"></div>
									<span id="activated_picsid_' . $row["picsid"] . '">
										<div style="overflow:hidden;text-align:center;width:171px;font-size:.85em;' . $activated . '">
										' . $row["imagename"] . '
										</div>
									</span>
									<div style="display:block;position:relative;text-align:center;width:171px;">
										<table>
											<tr>
												<td>
													<div style="width:165px; height:130px; overflow:hidden; text-align:center;">
														' . $deletepic . '<a href="javascript: void(0);" onclick="ajaxapi(\'/features/pics/pics_ajax.php\',\'toggle_activate\',\'&amp;pageid=' . $pageid . '&amp;picsid=' . $row["picsid"] . '\',function() {simple_display(\'activated_picsid_' . $row["picsid"] . '\');}); blur();"><img src="' . $webpath . '"' . imgResize($mypicture[0], $mypicture[1], 165) . ' /></a>
													</div>
												</td>
											</tr>
										</table>
									</div>
									<div style="display:block;position:relative;text-align:left;width:171px;">
										' . $caption . '
									</div>
									' . $movepics . '
							</div>';
		}
	return $header . $returnme . '</div>';
	} else {
		return '<br /><br /><div style="text-align:center"><strong>No images have been added.</strong></div>';
	}
}

function pics_delete($pageid, $featureid) {
global $CFG;
	if (isset($featureid)) { //Pics section delete
		recursive_delete($CFG->dirroot . '/features/pics/files/' . $pageid . "/" . $featureid);

		$params = [
			"pageid" => $pageid,
			"featureid" => $featureid,
			"feature" => "pics",
		];

		try {
			start_db_transaction();
			$sql = [];
			$sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature"];
			$sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature_settings"];
			$sql[] = ["file" => "dbsql/pics.sql", "feature" => "pics", "subsection" => "delete_galleries"];
			$sql[] = ["file" => "dbsql/pics.sql", "feature" => "pics", "subsection" => "delete_pics_features"];
			$sql[] = ["file" => "dbsql/pics.sql", "feature" => "pics", "subsection" => "delete_pics"];
	
			// Delete feature
			execute_db_sqls(fetch_template_set($sql), $params);

			resort_page_features($pageid);
			commit_db_transaction();
		} catch (\Throwable $e) {
			rollback_db_transaction($e->getMessage());
			return false;
		}
	}
}

// Just changes the view size of an image
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

// Changes the actual pixels of an image
function resizeImage($name, $filename, $new_w, $new_h) {
	$system=explode(".", $name);
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
  		$dst_img=ImageCreateTrueColor($thumb_w, $thumb_h);
  		imagecopyresampled($dst_img, $src_img,0,0,0,0, $thumb_w, $thumb_h, $old_x, $old_y);
  		if (preg_match("/png/", $system[1])) {
  			imagepng($dst_img, $filename);
  		} else {
  			imagejpeg($dst_img, $filename);
  		}
  		imagedestroy($dst_img);
  		imagedestroy($src_img);
	}
}

function insert_blank_pics($pageid) {
global $CFG;
    $type = "pics";
    try {
        start_db_transaction();
        if ($featureid = execute_db_sql(fetch_template("dbsql/pics.sql", "insert_pics_feature", "pics"), ["pageid" => $pageid])) {
            $area = get_db_field("default_area", "features", "feature = ||feature||", ["feature" => $type]);
            $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $area]) + 1;
            $params = [
                "pageid" => $pageid,
                "feature" => $type,
                "featureid" => $featureid,
                "sort" => $sort,
                "area" => $area,
            ];
            execute_db_sql(fetch_template("dbsql/features.sql", "insert_page_feature"), $params);
            commit_db_transaction();
            return $featureid;
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
	return false;
}

function pics_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
	$returnme = "";
	if (strstr($featuretype,"_features")) {
		$pics_abilities = user_abilities($USER->userid, $pageid,"pics", "pics", $featureid);
		$feature_abilities = user_abilities($USER->userid, $pageid,"features", "pics", $featureid);

        if (!empty($pics_abilities->managepics->allow) && get_db_row("SELECT * FROM pics WHERE pageid='$pageid' and featureid='$featureid'")) {
            $returnme .= make_modal_links([
							"title" => "Manage Galleries",
							"path" => action_path("pics") . "manage_pics&amp;pageid=$pageid&amp;featureid=$featureid",
							"refresh" => "true",
							"image" => $CFG->wwwroot . "/images/swap.png",
							"class" => "slide_menu_button",
						]);
        }

        if (!empty($pics_abilities->addpics->allow)) {
            $returnme .= make_modal_links([
							"title" => "Add Images",
							"path" => action_path("pics") . "add_pics&amp;pageid=$pageid&amp;featureid=$featureid",
							"iframe" => true,
							"refresh" => "true",
							"width" => "640",
							"height" => "500",
							"image" => $CFG->wwwroot . "/images/add.png",
							"class" => "slide_menu_button",
						]);
        }
    }
	return $returnme;
}

function pics_default_settings($type, $pageid, $featureid) {
	$settings = [
		[
			"setting_name" => "feature_title",
			"defaultsetting" => "Image Gallery",
			"display" => "Feature Title",
			"inputtype" => "text",
		],
		[
			"setting_name" => "picsperpage",
			"defaultsetting" => "12",
			"display" => "Pictures Per Page",
			"inputtype" => "text",
			"numeric" => true,
			"validation" => "<=0",
			"warning" => "Must be greater than 0.",
		],
	];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
	return $settings;
}
?>
