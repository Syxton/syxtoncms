<?php
/***************************************************************************
* newslib.php - News function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 8/19/2013
* Revision: 2.8.3
***************************************************************************/

if (!isset($LIBHEADER)) if (file_exists('./lib/header.php')) { include('./lib/header.php'); }elseif (file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif (file_exists('../../lib/header.php')) { include('../../lib/header.php'); }
$NEWSLIB = true;

//NEWSLIB Config
$CFG->news = new \stdClass;
$CFG->news->maxlength = 500;
$CFG->news->modalheight = 600;
$CFG->news->modalwidth = 640;

function display_news($pageid,$area,$featureid=false) {
global $CFG,$USER,$ROLES;
if (!$pageid) { $pageid = $CFG->SITEID; }
$returnme = ''; $section_content = ""; $toggle = "";

	$main_section = get_db_row("SELECT * FROM news_features WHERE featureid=".$featureid);
	if (!$settings = fetch_settings("news",$featureid,$pageid)) {
		make_or_update_settings_array(default_settings("news",$pageid,$featureid));
		$settings = fetch_settings("news",$featureid,$pageid);
	}

	$limit = $settings->news->$featureid->limit_viewable->setting;
	$title = $settings->news->$featureid->feature_title->setting;

	if (!is_logged_in()) { //If the user is not signed in
		if (role_has_ability_in_page($ROLES->visitor, 'viewnews', $pageid)) { //Has ability to see the news items
				if ($area == "middle") {
					if ($pagenews = get_section_news($featureid, "LIMIT ".$limit)) { //Gets the news from the given section
						$i=0; $newdate=false;
                        foreach ($pagenews as $news) {
                            if (isset($news->content)) {
                                $daygraphic = !$newdate || date('j',$newdate) != date('j',$news->submitted) ? get_date_graphic($news->submitted,true) : get_date_graphic($news->submitted,false);
                                $newdate = $pagenews->$i->submitted;
                                $section_content .= make_news_table($pageid,$news,$area,$daygraphic);
                                $section = $news->featureid;
                            }
                        }
					}
				}

				//Get the Archived News Area
				$section_content .= get_section_archives($pageid, $featureid, NULL, $area);
				if (empty($section_content)) { $section_content = "No news added yet"; }
				$buttons = get_button_layout("news_features",$featureid,$pageid);
				$returnme .= get_css_box($title,$section_content,$buttons,NULL,"news",$featureid);
			}

	} else { //User is signed in

		if (user_has_ability_in_page($USER->userid, 'viewnews', $pageid)) {
			if (is_logged_in()) {
      	$rss = make_modal_links(array("title"=>"News RSS Feed","path"=>$CFG->wwwroot."/pages/rss.php?action=rss_subscribe_feature&amp;feature=news&amp;pageid=$pageid&amp;featureid=$featureid","width"=>"640","styles"=>"position: relative;top: 4px;padding-right:2px;","height"=>"400","image"=>$CFG->wwwroot."/images/small_rss.png"));
      }

			if ($area == "middle") {
				if ($pageid == $CFG->SITEID) { //This is the site page
					$returnme .= '';
					if ($pages = get_users_news_pages($USER->userid,"LIMIT $limit")) {
						if ($pagenews = get_pages_news($pages,"LIMIT $limit")) {
							$newdate=false;
                            foreach ($pagenews as $news) {
                                if (isset($news->content)) {
                                    $daygraphic = !$newdate || date('j',$newdate) != date('j',$news->submitted) ? get_date_graphic($news->submitted,true) : get_date_graphic($news->submitted,false);
    								$newdate = $news->submitted;
    								$section_content .= make_news_table($pageid,$news,$area,$daygraphic);
    								$section = $news->featureid;
                                }
                            }
						}
					}
				} else { //This is for any page other than site
					if ($pagenews = get_section_news($featureid, "LIMIT ".$limit)) {
						$newdate=false;
                        foreach ($pagenews as $news) {
                            $daygraphic = !$newdate || date('j',$newdate) != date('j',$news->submitted) ? get_date_graphic($news->submitted,true) : get_date_graphic($news->submitted,false);
							$newdate = $news->submitted;
							$section_content .= make_news_table($pageid,$news,$area,$daygraphic);
							$section = $news->featureid;
                        }
					}
				}
			}
			$buttons = get_button_layout("news_features",$featureid,$pageid);
			//Get the Archived News Area
			$section_content .= get_section_archives($pageid, $featureid, $USER->userid, $area);
			if (empty($section_content)) { $section_content = "No news added yet"; }
			$returnme .= get_css_box($rss . $title,$section_content,$buttons, NULL, "news", $featureid);
		}
	}
    return $toggle . $returnme;
}

function make_news_table($pageid,$pagenews,$area,$daygraphic,$standalone = false) {
global $CFG;
	$buttons = $standalone ? '' : get_button_layout("news",$pagenews->newsid, $pagenews->pageid);
	$user = get_db_row("SELECT * FROM users where userid = " . $pagenews->userid);
	if ($area == "middle") {
    	$dots = strlen($pagenews->caption) > 350 ? "..." : "";
    	$returnme = '
    	<table class="newstable">
            <tr>
                '.$daygraphic.'
                  	<table style="width:100%;border-spacing: 0px;">
    	     		<tr>
    		     		<td colspan="2">
    		     		<div style="font-size:1em; color:red;"><strong>'.stripslashes($pagenews->title).'</strong></div>
    					<span style="font-size:.9em">
    		     		'.substr(stripslashes(strip_tags($pagenews->caption)),0,350).$dots.'
    		     		</span> ';
                        $returnme .= !$standalone  && stripslashes($pagenews->content) != "" ? '<span style="font-size:.9em; color:gray;">'.make_modal_links(array("title"=> stripslashes(htmlentities($pagenews->title)),"text"=>"[More...]","path"=>$CFG->wwwroot."/features/news/news.php?action=viewnews&amp;newsonly=1&amp;pageid=$pageid&amp;newsid=$pagenews->newsid","width"=>"800","height"=>"95%")).'</span>' : '';
    					$returnme .= '<div class="hprcp_n" style="margin-top:4px;"><div class="hprcp_e"><div class="hprcp_w"></div></div></div>
    					<div class="hprcp_head">
    						<div style="width:100%;vertical-align:middle;color:gray;position:relative;_right:2px;top:-8px;">
    						<span style="font-size:.85em;line-height:28px;">
    						Submitted: '.ago($pagenews->submitted).' by '.stripslashes($user['fname']).' '.stripslashes($user['lname']).'</span><div style="line-height:0px;position:relative;top:0px;right:0px;font-size:.01em; padding-top:2px;float:right">'.$buttons.'</div>
    						</div>
    					</div>
    	     			</td>
    	     		</tr>
    	     	  </table>
    		  </td>
            </tr>
    	</table>';
    } else {
        $dots = strlen($pagenews->caption) > 50 ? "..." : "";
    	$returnme = '
    	<table class="newstable">
            <tr>
         	<td>
    		    <table style="width:100%;border-spacing: 0px;">
    	     		<tr colspan="2">
    		     		<td>
    		     		<div style="font-size:1.35em; color:red;">'.stripslashes($pagenews->title).'</div>
    					<span style="font-size:1em">
    		     		'.stripslashes(substr(strip_tags($pagenews->caption),0,50)).$dots.'
    		     		</span>&nbsp;
    				 		<span style="font-size:.95em; color:gray;">
                                '.make_modal_links(array("title"=> stripslashes(htmlentities($pagenews->title)),"text"=>"[More...]","path"=>$CFG->wwwroot."/features/news/news.php?action=viewnews&amp;pageid=$pageid&amp;newsid=$pagenews->newsid","width"=>"800","height"=>"95%")).'
    				 		</span>
    					<div class="hprcp_n" style="margin-top:4px;">
    						<div class="hprcp_e">
    							<div class="hprcp_w">
    							</div>
    						</div>
    					</div>
    					<div class="hprcp_head">
    						<div style="width:100%;vertical-align:middle;color:gray;position:relative;_right:2px;top:-8px;">
    							<span style="font-size:.85em; float:left;line-height:28px;">
    							'.ago($pagenews->submitted).'
    							</span>
    							<div style="line-height:0px;position:relative;top:2px;right:2px;font-size:.01em; padding-top:2px;">'.$buttons.'</div>
    						</div>
    					</div>
    	     			</td>
    	     		</tr>
    	     	  </table>
    		  </td>
            </tr>
    	</table>';
	}
	return $returnme;
}

function get_users_news_pages($userid, $limit="", $site=true) {
global $CFG;
	$includesite = $site ? "" : " WHERE ns.pageid !=" . $CFG->SITEID;
	if (is_siteadmin($userid)) {
		$SQL = "
		SELECT DISTINCT ns.pageid,ns.lastupdate FROM news_features ns
		INNER JOIN pages_features pf on pf.pageid=ns.pageid AND pf.feature='news' AND pf.featureid=ns.featureid
		$includesite
		 ORDER BY ns.pageid,ns.lastupdate DESC $limit";
	} else {
    	$SQL = "
    	SELECT DISTINCT ns.pageidns.lastupdate FROM news_features ns
    	INNER JOIN roles_assignment ra ON ra.userid=$userid AND ra.pageid = ns.pageid AND confirm=0
    	INNER JOIN roles_ability ry ON ry.roleid=ra.roleid AND ry.ability='viewnews' AND allow='1'
    	INNER JOIN pages_features pf on pf.pageid=ns.pageid AND pf.feature='news' AND pf.featureid=ns.featureid
    	$includesite
    	 ORDER BY ns.pageid,ns.lastupdate DESC $limit";
	}
    return get_db_result($SQL);
}

function get_section_archives($pageid, $featureid, $userid = false, $area = "middle") {
global $CFG;
	$lastyear = date('Y',get_timestamp());
	if ($area == "middle") {
		$zero = 0;
		if ($pagenews = get_all_news($userid, $pageid, $featureid)) {
    		$returnme = '<br/><table style="background-color:#FCD163;border:1px solid gray;width:100%;text-align:right;font-size:.85em;">
    				<tr><td style="width:100px;text-align:center;"><strong><span>Archive</span></strong></td><td class="field_title" style="width: 60px;">Year: </td><td class="field_input" style="width: 60px;"><span id="year_span_'.$featureid.'_archive">';
    		$years = years_with_news($userid, $pagenews);
    		$returnme .= make_select_from_array("news_".$featureid."_archive_year", $years, "year", "year", $lastyear, '', 'onchange="ajaxapi(\'/features/news/news_ajax.php\',\'update_archive_months\',\'&amp;year=\'+this.value+\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'\',function() { simple_display(\'month_span_'.$featureid.'_archive\');});ajaxapi(\'/features/news/news_ajax.php\',\'update_archive_articles\',\'&amp;year=\'+$(\'#news_'.$featureid.'_archive_year\').val()+\'&amp;month=\'+$(\'#news_'.$featureid.'_archive_month\').val()+\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'\',function() { simple_display(\'article_span_'.$featureid.'_archive\');});"',false,NULL,'font-size:.8em;');
    		$returnme .= '</span></td><td class="field_title" style="width: 60px;">Month: </td><td class="field_input" style="width: 100px;"><span id="month_span_'.$featureid.'_archive">';
    		$months = months_with_news($userid, $years->$zero->year, $pagenews);
    		$lastrow = get_array_count($months)-1;
    		$returnme .= make_select_from_array("news_".$featureid."_archive_month", $months, "month", "monthname", $months->$lastrow->month ,'', 'onchange="ajaxapi(\'/features/news/news_ajax.php\',\'update_archive_articles\',\'&amp;year=\'+$(\'#news_'.$featureid.'_archive_year\').val()+\'&amp;month=\'+this.value+\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'\',function() { simple_display(\'article_span_'.$featureid.'_archive\');});"',false,NULL,'font-size:.8em;');
    		$returnme .= '</span></td><td class="field_title" style="width: 60px;">Article: </td><td class="field_input"><span id="article_span_'.$featureid.'_archive">';
    		$newsarticles = get_month_news($userid, $years->$zero->year, $months->$lastrow->month, $pagenews);
    		$returnme .= make_select_from_array("news_".$featureid."_archive_news", $newsarticles, "newsid", "title", NULL ,'','',false,NULL,'font-size:.8em;');
    		$returnme .= '</span></td><td>'.make_modal_links(array("title"=> "Get News","id"=>"fetch_".$featureid."_button","path"=>$CFG->wwwroot."/features/news/news.php?action=viewnews&amp;newsonly=1&amp;pageid=$pageid&amp;newsid='+$('#news_15_archive_news').val()+'&amp;featureid=$featureid","width"=>"800","image"=>$CFG->wwwroot."/images/magnifying_glass.png")).'</td></tr></table>';
            return $returnme;
		} else { return ""; }
	} else {
		$zero = 0;
		if ($pagenews = get_all_news($userid, $pageid, $featureid)) {
    		$returnme = '<table style="background-color:#FCD163;border:1px solid gray;width:100%;text-align:left;font-size:.85em;">
    				<tr><td colspan="3" style="text-align:center;"><strong>Archive</strong></td></tr><tr><td class="field_title" style="width:5%;padding:0px 2px;">Year: </td><td><span id="year_span_'.$featureid.'_archive">';
    		$years = years_with_news($userid, $pagenews);
    		$returnme .= make_select_from_array("news_".$featureid."_archive_year", $years, "year", "year", $lastyear, '', 'onchange="ajaxapi(\'/features/news/news_ajax.php\',\'update_archive_months\',\'&amp;year=\'+this.value+\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'\',function() { simple_display(\'month_span_'.$featureid.'_archive\');}); ajaxapi(\'/features/news/news_ajax.php\',\'update_archive_articles\',\'&amp;year=\'+$(\'#news_'.$featureid.'_archive_year\').val()+\'&amp;month=\'+$(\'#news_'.$featureid.'_archive_month\').val()+\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'\',function() { simple_display(\'article_span_'.$featureid.'_archive\');});"',false,NULL,'font-size:.8em;');
    		$returnme .= '</span></td><td style="width:5%;"></td></tr><tr><td class="field_title" style="width:5%;padding:0px 2px;">Month: </td><td><span id="month_span_'.$featureid.'_archive">';
    		$months = months_with_news($userid, $years->$zero->year, $pagenews);
    		$lastrow = get_array_count($months)-1;
    		$returnme .= make_select_from_array("news_".$featureid."_archive_month", $months, "month", "monthname", $months->$lastrow->month ,'', 'onchange="ajaxapi(\'/features/news/news_ajax.php\',\'update_archive_articles\',\'&amp;year=\'+$(\'#news_'.$featureid.'_archive_year\').val()+\'&amp;month=\'+this.value+\'&amp;pageid='.$pageid.'&amp;featureid='.$featureid.'\',function() { simple_display(\'article_span_'.$featureid.'_archive\'); });"',false,NULL,'font-size:.8em;');
    		$returnme .= '</span></td><td></td></tr><tr><td class="field_title" style="width:5%;padding:0px 2px;">Article: </td><td><span id="article_span_'.$featureid.'_archive">';
    		$newsarticles = get_month_news($userid, $years->$zero->year, $months->$lastrow->month, $pagenews);
    		$returnme .= make_select_from_array("news_".$featureid."_archive_news", $newsarticles, "newsid", "title", NULL ,'', 'onchange=""',false,NULL,'font-size:.8em;');
    		$returnme .= '</span></td><td>'.make_modal_links(array("title"=> "Get News","id"=>"fetch_".$featureid."_button","path"=>$CFG->wwwroot."/features/news/news.php?action=viewnews&amp;newsonly=1&amp;pageid=$pageid&amp;newsid='+$('#news_15_archive_news').val()+'&amp;featureid=$featureid","width"=>"800","image"=>$CFG->wwwroot."/images/magnifying_glass.png")).'</td></tr></table>';
    		return $returnme;
		} else { return ""; }
	}
}

function get_array_count($array, $i = 0) {
	foreach ($array as $item) {
		$i++;
	}
	return $i;
}

function get_all_news($userid, $pageid, $featureid) {
global $CFG, $USER;
	$zero = 0;
	if ($userid) {
		if ($CFG->SITEID == $pageid) {
			$pages = get_users_news_pages($userid, NULL, true);
			$returnme = get_pages_news($pages);
			if (isset($returnme->$zero)) { return $returnme;
			} else { return false; }
		} else {
			$returnme = get_section_news($featureid);
			if (isset($returnme->$zero)) { return $returnme;
			} else { return false; }
		}
	} else {
		$returnme = get_section_news($featureid);
		if (isset($returnme->$zero)) { return $returnme;
		} else { return false; }
	}
}

function get_month_news($userid, $year, $month, $pagenews=false, $pageid=false, $featureid=false) {
	if (!$pagenews) { $pagenews = get_all_news($userid,$pageid,$featureid); }
	$y=$currentmonth=$last=$i=0; $first = false;
	if (isset($pagenews->$i)) {
		while (isset($pagenews->$i)) { //Find first and last month of given year that a news item was exists in pagenews set
			if (date("Y",$pagenews->$i->submitted) == $year && date("n",$pagenews->$i->submitted) == $month) {
				if ($first === false) { $first = $i; }
				$last = $i;
			}$i++;
		}
		if ($first !== false) {
			$firststamp = $pagenews->$first->submitted; $laststamp = $pagenews->$last->submitted;
			while ($first <= $last) {
                if (empty($returnme)) { $returnme = new \stdClass; }
                $returnme->$y = new \stdClass;
				$returnme->$y->title = $pagenews->$first->title;
				$returnme->$y->newsid = $pagenews->$first->newsid;
				$first++; $y++;
			}
			return $returnme;
		} else { return false; }
	} else { return false; }
}

function months_with_news($userid, $year, $pagenews=false, $pageid=false, $featureid=false) {
	if (!$pagenews) { $pagenews = get_all_news($userid,$pageid,$featureid);	}
	$last=$i=0; $first = false;
	if (isset($pagenews->$i)) {
		while (isset($pagenews->$i)) { //Find first and last month of given year that a news item was exists in pagenews set
			if (date("Y",$pagenews->$i->submitted) == $year) {
				if ($first === false) { $first = $i; }
				$last = $i;
			}$i++;
		}
		if ($first !== false) {
			//SWAP THEM SO THAT MONTHS WILL BE DISPLAYED FROM JANUARY TO DECEMBER INSTEAD OF BACKWARDS
			$firststamp = $pagenews->$first->submitted;
			$laststamp = $pagenews->$last->submitted;
			$temp = $first;$first = $last;$last = $temp;
			$firstmonth = date("n",$firststamp);
			$lastmonth = date("n",$laststamp);
			$y = 0; $currentmonth = 0;
			while ($firstmonth >= $lastmonth) {
				$beginmonth = mktime(0,0,0,$lastmonth,1,$year);
				$daysinmonth = cal_days_in_month(CAL_GREGORIAN,$firstmonth,$year) + 1;
				$endmonth = mktime(0,0,0,$firstmonth,$daysinmonth,$year);
				$i=$first;
				while (isset($pagenews->$i)) {
					if ($pagenews->$i->submitted >= $beginmonth && $pagenews->$i->submitted <= $endmonth) {
						if (date("n",$pagenews->$i->submitted) > $currentmonth) {
							$currentmonth = date("n",$pagenews->$i->submitted);
							if (empty($returnme)) { $returnme = new \stdClass; }
                            $returnme->$y = new \stdClass;
                            $returnme->$y->month = $currentmonth;
							$returnme->$y->monthname = date("F", $pagenews->$i->submitted);
							break;
						}
					}
					$i--;
				}
				$lastmonth++; $y++;
			}
			return $returnme;
		} else { return false; }
	}
	return false;
}

function years_with_news($userid, $pagenews=false, $pageid=false, $featureid=false) {
	if (!$pagenews) { $pagenews = get_all_news($userid,$pageid,$featureid);	}
	$zero=$last=0;
    if (isset($pagenews->$zero)) {
		foreach ($pagenews as $news) { $last++; } $last--; //counts news items -- count() doesn't work on objects)
		$first = $pagenews->$zero->submitted;
		$last = $pagenews->$last->submitted;
		$firstyear = date("Y",$last);
		$currentyear = date("Y",$first);
        $y = 0;
		while ($currentyear >= $firstyear) {
			$beginyear = mktime(0,0,0,1,1,$currentyear);
			$endyear = mktime(0,0,0,12,32,$currentyear);

            foreach ($pagenews as $news) {
                if ($news->submitted >= $beginyear && $news->submitted <= $endyear) {
                    if (empty($returnme)) { $returnme = new \stdClass; }
                    $returnme->$y = new \stdClass;
                    $returnme->$y->year = $currentyear;
                    $y++;
                    break;
                }
            }

            $currentyear--;
		}
		return $returnme;
	} else { return false; }
}

function get_section_news($featureid, $limit = "") {
global $CFG;
	$SQL = "SELECT * FROM news WHERE featureid='$featureid'	ORDER BY submitted DESC $limit";
    $i=0;
	if ($news_results = get_db_result($SQL)) {
        $news = new \stdClass;
		while ($row = fetch_row($news_results)) {
			$news->$i = new \stdClass;
            $news->$i->newsid = $row['newsid'];
			$news->$i->pageid = $row['pageid'];
			$news->$i->featureid = $row['featureid'];
			$news->$i->title = stripslashes($row['title']);
			$news->$i->content = stripslashes($row['content']);
			$news->$i->submitted = $row['submitted'];
			$news->$i->edited = $row['edited'];
			$news->$i->caption = stripslashes($row['caption']);
			$news->$i->userid = $row['userid'];
			$i++;
		}
	}

	if ($i == 0) return false;
	return $news;
}

function get_page_news($pageid, $limit = "") {
global $CFG;
	$sections = "";
	$SQL = "
	SELECT * FROM news_features
	WHERE pageid=$pageid
	ORDER BY lastupdate";
	if ($section_results = get_db_result($SQL)) {
		while ($section = fetch_row($section_results)) {
			$sections .= $sections == "" ? "featureid=".$section['featureid'] : " OR featureid=".$section['featureid'];
		}
	}
	if ($sections != "") {
		if (!$limit) { $limit = "LIMIT " . $section['limit_viewable']; }
		$SQL = "
		SELECT * FROM news
		WHERE ($sections)
		ORDER BY submitted DESC $limit
		";
		$i=0;
		if ($news_results = get_db_result($SQL)) {
			while ($row = fetch_row($news_results)) {
				$news->$i->newsid = $row['newsid'];
				$news->$i->pageid = $row['pageid'];
				$news->$i->featureid = $row['featureid'];
				$news->$i->title = $row['title'];
				$news->$i->content = $row['content'];
				$news->$i->submitted = $row['submitted'];
				$news->$i->edited = $row['edited'];
				$news->$i->caption = $row['caption'];
				$news->$i->userid = $row['userid'];
				$i++;
			}
		}
		if ($i == 0) { return false; }
		return $news;
	}
    return false;
}

function get_pages_news($pages, $limit = "") {
global $CFG;
	$mypages = "";
	if ($pages) {
		while ($page = fetch_row($pages,"num")) {
			$mypages .= $mypages == "" ? '(pageid=' . $page[0] : ' OR pageid='. $page[0];
		} $mypages .= ')';

	  $SQL = "SELECT *
							FROM news
						 WHERE $mypages
					ORDER BY submitted DESC
						$limit";

		$i=0;
		if ($news_results = get_db_result($SQL)) {
      $news = new \stdClass;
			while ($row = fetch_row($news_results)) {
        $news->$i = new \stdClass;
				$news->$i->newsid = $row['newsid'];
				$news->$i->pageid = $row['pageid'];
				$news->$i->featureid = $row['featureid'];
				$news->$i->title = $row['title'];
				$news->$i->content = $row['content'];
				$news->$i->submitted = $row['submitted'];
				$news->$i->edited = $row['edited'];
				$news->$i->caption = $row['caption'];
				$news->$i->userid = $row['userid'];
				$i++;
			}
		}
		if ($i == 0) { return false; }
		return $news;
	}
    return false;
}

//function format_news_clip($newscontent) {
//global $CFG;
//	if (strlen($newscontent->content) > $CFG->news->maxlength) {
//		$newscontent->content = substr($newscontent->content,0,$CFG->news->maxlength);
//		$newscontent->content = closetags($newscontent->content);
//		$newscontent->content .= '
//        <br />
//        <div class="full_news_link" style="text-align:center">
//            '.make_modal_links(array("title"=>$newscontent->title,"text"=> "View full entry","path"=>$CFG->wwwroot."/features/viewnews&amp;newsid=$newscontent->newsid","width"=>"700")).'
//        </div>
//        <div id="newsid'.$newscontent->newsid.'" style="display:none;">'.$newscontent->content.'
//        </div>';
//	}
//    return $newscontent->content;
//}

function closetags($html) {
	$selfclosing = ',img,input,br,hr,';
	//put all opened tags into an array
	preg_match_all("#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU", $html, $result);
	$openedtags=$result[1];
	//put all closed tags into an array
	preg_match_all("#</([a-z]+)>#iU",$html,$result);
	$closedtags=$result[1];
	$len_opened = count($openedtags);

    //all tags are closed
	if (count($closedtags) == $len_opened) { return $html; }

    $openedtags = array_reverse($openedtags);
	//close tags
	for ($i=0;$i < $len_opened;$i++) {
		$temp = $openedtags[$i];
		switch ($openedtags[$i]) {
			case strstr($selfclosing,",$temp,"):
				break;
			default:
				if (!in_array($openedtags[$i],$closedtags)) {
					$html .= '</'.$openedtags[$i].'>';
				} else {
					unset($closedtags[array_search($openedtags[$i],$closedtags)]);
				}
		}
	}
	return $html;
}

function news_delete($pageid,$featureid,$newsid) {
	if (empty($newsid)) { //News feature delete
		if (execute_db_sql("DELETE FROM pages_features WHERE feature='news' AND pageid='$pageid' AND featureid='$featureid'") && execute_db_sql("DELETE FROM news_features WHERE pageid='$pageid' and featureid='$featureid'") && execute_db_sql("DELETE FROM news WHERE pageid='$pageid' and featureid='$featureid'") && execute_db_sql("DELETE FROM settings WHERE type='news' AND pageid='$pageid' AND featureid='$featureid'")) {
			resort_page_features($pageid);
		}
	} else { //News item delete
		execute_db_sql("DELETE FROM news WHERE newsid='$newsid'");
	}
}

function news_rss($feed, $userid, $userkey) {
global $CFG;
	$feeds = "";
	if ($feed["pageid"] == $CFG->SITEID && $userid) { //This is the site page for people who are members
		if ($pages = get_users_news_pages($userid,"LIMIT 50")) {
			if ($pagenews = get_pages_news($pages,"LIMIT 50")) {
                foreach ($pagenews as $news) {
                    if (isset($news->content)) {
                       $feeds .= fill_feed($news->title,strip_tags($news->caption),$CFG->wwwroot.'/features/news/news.php?action=viewnews&key='.$userkey.'&pageid='.$feed["pageid"].'&newsid='.$news->newsid,$news->submitted);
                     }
                }
			}
		}
	} else { //This is for any page other than site
		if ($pagenews = get_section_news($feed["featureid"], "LIMIT 50")) {
			foreach ($pagenews as $news) {
                if (isset($news->content)) {
                    $feeds .= fill_feed($news->title,strip_tags($news->caption),$CFG->wwwroot.'/features/news/news.php?action=viewnews&key='.$userkey.'&pageid='.$feed["pageid"].'&newsid='.$news->newsid,$news->submitted);
                }
			}
		}
	}
	return $feeds;
}

function insert_blank_news($pageid) {
global $CFG;
	if ($featureid = execute_db_sql("INSERT INTO news_features (pageid,lastupdate) VALUES('$pageid','".get_timestamp()."')")) {
		$feature = "news";
		$area = get_db_field("default_area", "features", "feature='news'");
		$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid,feature,sort,area,featureid) VALUES('$pageid','news','$sort','$area','$featureid')");
		return $featureid;
	}
	return false;
}

function news_buttons($pageid,$featuretype,$featureid) {
global $CFG,$USER;
	$returnme = "";
	if (strstr($featuretype,"_features")) {
        $returnme .= user_has_ability_in_page($USER->userid,"addnews",$pageid) ? make_modal_links(array("title"=> "Add News Item","path"=>$CFG->wwwroot."/features/news/news.php?action=addeditnews&amp;pageid=$pageid&amp;featureid=$featureid","iframe"=>"true","refresh"=>"true","width"=>"850","height"=>"600","image"=>$CFG->wwwroot."/images/add.png","class"=>"slide_menu_button")) : '';
	} else {
        $returnme .= user_has_ability_in_page($USER->userid,"editnews",$pageid) ? make_modal_links(array("title"=> "Edit News Item","path"=>$CFG->wwwroot."/features/news/news.php?action=addeditnews&amp;pageid=$pageid&amp;newsid=$featureid","iframe"=>"true","refresh"=>"true","width"=>"850","height"=>"600","image"=>$CFG->wwwroot."/images/edit.png","class"=>"slide_menu_button")) : '';
        $returnme .= user_has_ability_in_page($USER->userid,"deletenews",$pageid) ? ' <a class="slide_menu_button" title="Delete News Item" onclick="if (confirm(\'Are you sure you want to delete this?\')) { ajaxapi(\'/ajax/site_ajax.php\',\'delete_feature\',\'&amp;pageid='.$pageid.'&amp;featuretype='.$featuretype.'&amp;sectionid='.$featureid.'&amp;featureid='.$featureid.'\',function() { update_login_contents('.$pageid.');});}"><img src="'.$CFG->wwwroot.'/images/delete.png" alt="Delete News Item" /></a> ' : '';
    }
	return $returnme;
}

function news_default_settings($feature,$pageid,$featureid) {
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","News Section",false,"News Section","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","limit_viewable","5",false,"5","Viewable Limit","text",true,"<=0","Must be greater than 0.");
	return $settings_array;
}
?>
