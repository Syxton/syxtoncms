<?php
/***************************************************************************
* htmllib.php - HTML feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 12/22/2015
* Revision: 2.4.9
***************************************************************************/
if (!isset($LIBHEADER)) { if (file_exists('./lib/header.php')) { include('./lib/header.php'); }elseif (file_exists('../lib/header.php')) { include('../lib/header.php'); }elseif (file_exists('../../lib/header.php')) { include('../../lib/header.php'); }}
$HTMLLIB = true;

function display_html($pageid, $area, $featureid) {
global $CFG, $USER, $HTMLSETTINGS;

	$abilities = get_user_abilities($USER->userid,$pageid,"html","html",$featureid);
	if (!$settings = fetch_settings("html",$featureid,$pageid)) {
		make_or_update_settings_array(default_settings("html",$pageid,$featureid));
		$settings = fetch_settings("html",$featureid,$pageid);
	}

    //if (user_has_ability_in_page($USER->userid, "viewhtml", $pageid, "html", $featureid)) {
    if (!empty($abilities->viewhtml->allow)) {
        return get_html($pageid, $featureid, $settings, $abilities, $area);
    }
}

function get_html($pageid,$featureid,$settings,$abilities,$area=false,$htmlonly=false) {
global $CFG,$USER;
	$SQL = "SELECT * FROM html WHERE htmlid='$featureid'";
	$returnme = ""; $makecomment = ""; $comments = $rss = "";
	if ($result = get_db_result($SQL)) {
		while ($row = fetch_row($result)) {
			$limit = $area == "side" ? $settings->html->$featureid->sidecommentlimit->setting : $settings->html->$featureid->middlecommentlimit->setting;
			if ($settings->html->$featureid->allowcomments->setting) {
				$hidebuttons = $htmlonly ? true : false;
				$comments = $abilities->viewcomments->allow && $settings->html->$featureid->allowcomments->setting ? get_html_comments($row['htmlid'],$pageid,$hidebuttons,$limit) : '';
                $makecomment = $abilities->makecomments->allow ? make_modal_links(array("title"=>"Comment","path"=>$CFG->wwwroot."/features/html/html.php?action=makecomment&amp;pageid=$pageid&amp;htmlid=".$row['htmlid'],"styles"=>"float:right;","refresh"=>"true")) : '';
            }
            //if viewing from rss feed
			if ($htmlonly) {
                $returnme .= '<table style="width:100%;border:1px solid silver;padding:10px;"><tr><th>'. $settings->html->$featureid->feature_title->setting.'</th></tr><tr><td><br /><br /><div class="htmlblock">' .filter(stripslashes($row['html']),$featureid,$settings,$area) .'</div><br />'. $comments . '</td></tr></table>';
            } else { //regular html feature viewing
                $stopped_editing = '<input type="hidden" id="html_'.$featureid.'_stopped_editing" value="ajaxapi(\'/features/html/html_ajax.php\',\'stopped_editing\',\'&amp;htmlid='.$featureid.'&amp;userid=0\',function() {if (xmlHttp.readyState == 4) { do_nothing(); }},true);" />';

                if (is_logged_in() && $settings->html->$featureid->enablerss->setting) $rss = make_modal_links(array("title"=>"RSS Feed","path"=>$CFG->wwwroot."/pages/rss.php?action=rss_subscribe_feature&amp;pageid=$pageid&amp;featureid=$featureid&amp;feature=html","styles"=>"position: relative;top: 4px;padding-right:2px;","iframe"=>"true","refresh"=>"true","height"=>"300","width"=>"640","image"=>$CFG->wwwroot."/images/small_rss.png"));

                $buttons = get_button_layout("html",$row['htmlid'],$pageid);
				$returnme .= get_css_box($rss . $settings->html->$featureid->feature_title->setting,$stopped_editing.'<div class="htmlblock">' .filter(stripslashes($row['html']),$featureid,$settings,$area) .'</div><br />'. $makecomment . $comments,$buttons, null, 'html', $featureid, false, false, false, false, false, false);
			}
		}
	}
	return $returnme;
}

function filter($html, $featureid, $settings, $area = "middle") {
global $CFG;
	if (isset($settings->html->$featureid->documentviewer->setting) && $settings->html->$featureid->documentviewer->setting == 1) { // Document Viewer Filter
		$html = filter_docviewer($html);
	}

	if (isset($settings->html->$featureid->embedaudio->setting) && $settings->html->$featureid->embedaudio->setting == 1) { // Embed audio player
		$html = filter_embedaudio($html);
	}

	if (isset($settings->html->$featureid->embedvideo->setting) && $settings->html->$featureid->embedvideo->setting == 1) { // Embed Video Player
		$html = filter_embedvideo($html);
	}

	if (isset($settings->html->$featureid->embedyoutube->setting) && $settings->html->$featureid->embedyoutube->setting == 1) { // Embed Youtube video player
		$html = filter_youtube($html);
	}

	if (isset($settings->html->$featureid->photogallery->setting) && $settings->html->$featureid->photogallery->setting == 1) { // Photo Gallery Filter
		$html = filter_photogallery($html);
	}
	return $html;
}

function filter_docviewer($html) {
global $CFG;
	if (isset($CFG->doc_view_key)) {
		$regex = '/(<[aA]\s*.[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s""\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(.[^\s]*)(<\/[aA]>)/';
		if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				if (!strstr($match[0],'javascript:')) {
					$filetypes = '/([\.[pP][dD][fF]|\.[dD][oO][cC]|\.[rR][tT][fF]|\.[pP][sS]|\.[pP][pP][tT]|\.[pP][pP][sS]|\.[tT][xX][tT]|\.[sS][xX][cC]|\.[oO][dD][sS]|\.[xX][lL][sS]|\.[oO][dD][tT]|\.[sS][xX][wW]|\.[oO][dD][pP]|\.[sS][xX][iI]])/';
					if (preg_match($filetypes,$match[2])) {
						//make internal links full paths
						$url = strstr($match[2], $CFG->directory.'/userfiles') && !strstr($match[2],$CFG->wwwroot) && !strstr($match[2],"http://") && !strstr($match[2],"www.") ? str_replace($CFG->directory.'/userfiles', $CFG->wwwroot.'/userfiles',$match[2]) : $match[2];

						//make full url if not full
						$protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';
						$url_parts = parse_url($url);
						$url = str_replace("://", "", $url);
						$url = str_replace(":", "", $url);
						$url = str_replace("//", "/", $url);

						if (!empty($url_parts["scheme"])) { // protocol exists.
								$url = str_replace($url_parts["scheme"], $protocol, $url);
						} else {
								$url = $protocol . $url;
						}

						//remove target from urls
						if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/',$url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0],"", $url); }
						$url = preg_replace('/([\'|\"])/','',$url);

						//make ipaper links
						$url = str_replace('\\','',$url);
						$url = str_replace('../','',$url);
						$url = str_replace('..','',$url);

						$html = str_replace($match[0],'<a href="'.$CFG->wwwroot.'/scripts/download.php?file='.$url.'" onclick="blur();"><img src="'.$CFG->wwwroot.'/images/save.png" alt="Save" /></a>&nbsp;'.make_modal_links(array("title"=>$match[4].$match[5],"path"=>$CFG->wwwroot."/pages/ipaper.php?action=view_ipaper&amp;doc_url=".base64_encode($url),"height"=>"80%","width"=>"80%")),$html);
					}
				}
			}
		}
	}
	return $html;
}

function filter_embedaudio($html) {
global $CFG;
	$regex = '/(<[aA]\s*.[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s""\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(.[^\s]*)(<\/[aA]>)/';
	if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
		$i = 0;
		foreach ($matches as $match) {
			if (!strstr($match[0],'javascript:')) {
				$found = false;
				$filetypes = '/([\.[aA][aA][cC]|\.[mM][4][aA])/';
				if (preg_match($filetypes,$match[2])) {
					//make internal links full paths
					$url = strstr($match[2],$CFG->directory.'/userfiles') && !strstr($match[2],$CFG->wwwroot) ? str_replace($CFG->directory.'/userfiles', $CFG->wwwroot.'/userfiles',$match[2]) : $match[2];
					//remove target from urls
					if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/',$url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0],"", $url);}
					$url = preg_replace('/([\'|\"])/','',$url);

					$url = str_replace('\\','',$url);
					$info = explode(".",$match[4].$match[5]);
					$html = str_replace($match[0], "
						<script type='text/javascript' src='".$CFG->wwwroot."/scripts/filters/video/swfobject.js'></script>
						<span id='mediaspace_s$i'></span>
						<script type='text/javascript'>
							var s$i = new SWFObject('".$CFG->wwwroot."/scripts/filters/video/player.swf','ply','290','30','9','#ffffff');
							s$i.addParam('allowfullscreen','true');
							s$i.addParam('allowscriptaccess','always');
							s$i.addParam('wmode','opaque');
							s$i.addParam('flashvars','file=".stripslashes(urlencode($url))."&amp;skin=".$CFG->wwwroot."/scripts/filters/video/skins/stylish_slim.swf');
							s$i.write('mediaspace_s$i');
						</script>
					",$html);
				}

				$found = false;
				$filetypes = '/([\.[mM][pP][3])/';
				if (preg_match($filetypes,$match[2])) {
					if (!$found) {
						$player = "<script language='javascript' src='".$CFG->wwwroot."/scripts/filters/audio/audio-player.js'></script>
									 <script type='text/javascript'>
											 AudioPlayer.setup('".$CFG->wwwroot."/scripts/filters/audio/player.swf', {
													 width: 290
											 });
									 </script>";
					} else {$player = ""; }

					$found = true;
					//make internal links full paths
					$url = strstr($match[2],$CFG->directory.'/userfiles') && !strstr($match[2],$CFG->wwwroot) ? str_replace($CFG->directory.'/userfiles', $CFG->wwwroot.'/userfiles',$match[2]) : $match[2];
					//remove target from urls
					if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/',$url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0],"", $url);}
					$url = preg_replace('/([\'|\"])/','',$url);
					$url = str_replace('\\','',$url);
					$info = explode(".",$match[4].$match[5]);
					$info = explode("-",$info[0]);
					$html = str_replace($match[0], $player ."
					<span id='audioplayer_$featureid"."_$i"."'></span>
					<script type='text/javascript'>
					 AudioPlayer.embed('audioplayer_$featureid"."_$i"."', {
							 soundFile: '".stripslashes($url)."',
							 titles: '$info[1]',
							 artists: '$info[0]',
							 autostart: 'no'
					 });
					 </script>
					",$html);
				}
			}
		$i++;
		}
	}
	return $html;
}
function filter_embedvideo($html) {
global $CFG;
	$regex = '/(<[aA]\s*.[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s""\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(.[^\s]*)(<\/[aA]>)/';
	if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
		$i = 0;
		foreach ($matches as $match) {
			if (!strstr($match[0],'javascript:')) {
				$filetypes = '/([\.[fF][lL][vV]|\.[mM][pP][4])/';
				if (preg_match($filetypes,$match[2])) {
					//make internal links full paths
					$url = strstr($match[2],$CFG->directory.'/userfiles') && !strstr($match[2],$CFG->wwwroot) ? str_replace($CFG->directory.'/userfiles', $CFG->wwwroot.'/userfiles',$match[2]) : $match[2];
					//remove target from urls
					if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/',$url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0],"", $url);}
					$url = preg_replace('/([\'|\"])/','',$url);

					$url = str_replace('\\','',$url);
					$rand = rand(0,time());
					$html = str_replace($match[0], "
													<script type='text/javascript' src='".$CFG->wwwroot."/scripts/filters/video/flowplayer/flowplayer-3.2.4.min.js'></script>
													<div id='vid_$rand' class='flowplayer_div' style='width:100%;'><a href='$url' style='display:block;' class='flowplayers' id='player_$rand'></a></div>
													<script>flowplayer('a.flowplayers', '".$CFG->wwwroot."/scripts/filters/video/flowplayer/flowplayer-3.2.4.swf',{
													clip: {
															autoPlay: false,
															autoBuffering: true,
															onBegin: function() { this.getControls().css({height:'5%'});},
															onMetaData: setInterval(function() {
																			$('a.flowplayers').flowplayer().each(function() {
																					var myclip = this.getClip(0);
																					if (myclip.metaData != undefined) {
																							var width = $('#'+this.id()).parent('.flowplayer_div').attr('clientWidth') >= myclip.metaData.width ? myclip.metaData.width : $('#'+this.id()).parent('.flowplayer_div').attr('clientWidth');
																							var height = (width/myclip.metaData.width) * myclip.metaData.height;
																							var wrap = jQuery(this.getParent());
																							wrap.css({width: width+'px', height: height+'px'});
																					}
																			});
																	},1000)
															}
													});
													</script>
					",$html);
				}
			}
		$i++;
		}
	}
	return $html;
}

function filter_youtube($html) {
global $CFG;
	$regex = '/(<[aA]\s*.[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s""\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(.[^\s]*)(<\/[aA]>)/';
	if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) { //link to youtube
		foreach ($matches as $match) {
			$url = $match[2];
			$id = youtube_id_from_url($url);
			if (!strstr($url,'#noembed')) {
				if (!strstr($match[0],'javascript:') && strlen($id) > 0) {
					if (preg_match('/((http:\/\/)?(?:youtu\.be\/|(?:[a-z]{2,3}\.)?youtube\.com\/v\/)([\w-]{11}).*|http:\/\/(?:youtu\.be\/|(?:[a-z]{2,3}\.)?youtube\.com\/watch(?:\?|#\!)v=)([\w-]{11}).*)/i',$match[0]) || preg_match('/(\s*\.[yY][oO][uU][tT][uU][bB][eE]\.[cC][oO][mM][\/]\s*)/',$url)) {
							$html = str_replace($match[0], '<div style="'.($area == "middle" ? 'max-width:500px;margin:auto;' : '').'"><div style="width: 100%; padding-top: 60%; margin-bottom: 5px; position: relative;"><iframe style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;" src="//www.youtube.com/embed/'.$id.'"></iframe></div></div>',$html);
					}
				}
			}
		}
	}
	return $html;
}

function filter_photogallery($html) {
global $CFG;
	if (isset($CFG->doc_view_key)) {
		$regex = '/(<[aA]\s*.[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s""\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(<\/[aA]>)/';
		if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$url = $match[2];
				$exts = array('jpeg', 'jpg', 'gif', 'png');
				if (strpos($match[0], 'title="gallery"') !== false && strpos($url, 'userfiles') !== false) {
					//make internal links full paths
					$localdirectory = $CFG->dirroot . "/" . substr($url, strpos($url, "userfiles"));

					if (substr($localdirectory, -1) == '/') {
						$localdirectory = substr($localdirectory, 0, -1);
					}
					$gallery = ""; $galleryid = uniqid("autogallery");
					if (is_readable($localdirectory) && (file_exists($localdirectory) || is_dir($localdirectory))) {
						$directoryList = opendir($localdirectory);
						$i = 0;
						$captions = get_file_captions($localdirectory);

						while($file = readdir($directoryList)) {
							if ($file != '.' && $file != '..') {
								$path = $localdirectory . '/' . $file;
								if (is_readable($path)) {
									if (is_file($path) && in_array(end(explode('.', end(explode('/', $path)))),   $exts)) {
										$fileurl = $url . '/' . $file; // Use web url instead of local link.
										$caption = isset($captions[$file]) ? $captions[$file] : $file; // Either a caption or the filename
										$name = $match[4]; // Use text inside original hyperlink.
										$display = empty($gallery) ? "" : "display:none;";
										$modalsettings = array("id" => "autogallery_$i", "title" => $caption, "text" => $name, "gallery" => $galleryid, "path" => $fileurl, "styles" => $display);
							      $gallery .= empty($display) ? make_modal_links($modalsettings) : '<a href="'.$fileurl.'" title="'.$caption.'" data-rel="'.$galleryid.'" style="'.$display.'"></a>';
									}
								}
							}
							$i++;
						}
						closedir($directoryList);
						if (!empty($gallery)) {
							$html = str_replace($match[0], $gallery, $html);
						}
					}
				}
			}
		}
	}
	return $html;
}

function youtube_id_from_url($url) {
    $pattern =
        '%^# Match any youtube URL
        (?:https?://)?  # Optional scheme. Either http or https
        (?:www\.)?      # Optional www subdomain
        (?:             # Group host alternatives
          youtu\.be/    # Either youtu.be,
        | youtube\.com  # or youtube.com
          (?:           # Group path alternatives
            /embed/     # Either /embed/
          | /v/         # or /v/
          | /watch\?v=  # or /watch\?v=
          )             # End path alternatives.
        )               # End host alternatives.
        ([\w-]{10,12})  # Allow 10-12 for 11 char youtube id.
        $%x'
        ;
    $result = preg_match($pattern, $url, $matches);
    if (false !== $result && !empty($matches[1])) {
        return $matches[1];
    }
    return false;
}

function insert_blank_html($pageid,$settings = false) {
global $CFG;
	if ($featureid = execute_db_sql("INSERT INTO html (pageid,html,dateposted) VALUES('$pageid','','".get_timestamp()."')")) {
		$area = get_db_field("default_area", "features", "feature='html'");
		$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$area'") + 1;
		execute_db_sql("INSERT INTO pages_features (pageid,feature,sort,area,featureid) VALUES('$pageid','html','$sort','$area','$featureid')");
		return $featureid;
	}
	return false;
}

function get_html_comments($htmlid,$pageid,$hidebuttons=false,$perpage=0,$pagenum=false,$hide=true) {
global $CFG,$USER;
	$comments = $prev = $info = $next = $header = $arrows = $limit = "";
	$original = $pagenum === false ? true : false;
	$pagenum = $pagenum === false ? 0 : $pagenum;

	if ($perpage) {
		$total = get_db_count("SELECT * FROM html_comments WHERE htmlid=$htmlid");
		$searchvars = get_search_page_variables($total, $perpage, $pagenum);
		$prev = $searchvars["prev"] ? '<a href="javascript: document.getElementById(\'loading_overlay\').style.visibility=\'visible\'; ajaxapi(\'/features/html/html_ajax.php\',\'commentspage\',\'&pagenum=' . ($pagenum - 1) . '&perpage='.$perpage.'&pageid='.$pageid.'&htmlid='.$htmlid.'\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer_'."html_$htmlid".'\'); document.getElementById(\'loading_overlay'."html_$featureid".'\').style.visibility=\'hidden\'; }},true); " onmouseup="this.blur()">Previous</a>' : "";
	    $info = $searchvars["info"];
    	$next = $searchvars["next"] ? '<a href="javascript: document.getElementById(\'loading_overlay\').style.visibility=\'visible\'; ajaxapi(\'/features/html/html_ajax.php\',\'commentspage\',\'&pagenum=' . ($pagenum + 1) . '&perpage='.$perpage.'&pageid='.$pageid.'&htmlid='.$htmlid.'\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer'."html_$htmlid".'\'); document.getElementById(\'loading_overlay'."html_$featureid".'\').style.visibility=\'hidden\'; }},true);" onmouseup="this.blur()">Next</a>' : "";
    	$arrows = '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;font-size:.75em;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><br /><br />';
		$limit = "LIMIT " .$searchvars["firstonpage"] . "," . $perpage;
	} else { $limit = "LIMIT $perpage";}

	$SQL = "SELECT * FROM html_comments WHERE htmlid=$htmlid ORDER BY commentid DESC $limit";

	if ($result = get_db_result($SQL)) {
		$header = '<div class="button" id="hmtl_'.$htmlid.'_hide_button" style="display:block;margin-right:auto;margin-left:auto;font-size:.8em;width:90px;" onclick="hide_show_buttons(\'hmtl_'.$htmlid.'_comments_button\',true); hide_show_buttons(\'hmtl_'.$htmlid.'_comments\',true)">Hide Comments</div><br />';

		while ($row = fetch_row($result)) {
			$makecomment = $makereply = $editcomment = $deletecomment = $username = "";
			$username = !$row['userid'] ? "Visitor" : get_user_name($row['userid']);
			$reply = get_html_replies($row['commentid'],$hidebuttons,$pageid);
			if (!$hidebuttons) { $deletecomment = user_has_ability_in_page($USER->userid,"deletecomments",$pageid) || ($USER->userid == $row["userid"] && user_has_ability_in_page($USER->userid,"makecomments",$pageid)) ? make_modal_links(array("title"=>"Delete Comment","path"=>$CFG->wwwroot."/features/html/html.php?action=deletecomment&amp;userid=".$row["userid"].'&amp;pageid='.$pageid.'&amp;commentid='.$row['commentid'],"refresh"=>"true","image"=>$CFG->wwwroot."/images/delete.png")) : "";}
			if (!$hidebuttons && !strlen($reply) > 0 && user_has_ability_in_page($USER->userid,"makereplies",$pageid)) { $makereply = make_modal_links(array("title"=>"Reply","path"=>$CFG->wwwroot."/features/html/html.php?action=makereply&amp;pageid=$pageid&amp;commentid=".$row['commentid'],"refresh"=>"true","styles"=>"float:right;padding: 2px;"));}
			if (!$hidebuttons) { $editcomment = user_has_ability_in_page($USER->userid,"editanycomment",$pageid) || ($USER->userid == $row["userid"] && user_has_ability_in_page($USER->userid,"makecomments",$pageid)) ? '<br />'.make_modal_links(array("title"=>"Edit","path"=>$CFG->wwwroot."/features/html/html.php?action=editcomment&amp;pageid=$pageid&amp;commentid=".$row['commentid'].'&amp;userid='.$row["userid"],"refresh"=>"true","styles"=>"float:left;padding: 2px;")) : ''; }
			$comments .=
			'
			<table class="blogbox">
				<tr class="blogreplyheader">
					<td style="text-align:left;vertical-align:middle;">
						'.$username.' says
					</td>
					<td style="text-align:right;">
						'.$deletecomment.'
					</td>
				</tr>
				<tr class="blogreply">
					<td colspan="2">
					'.$row['comment'].'<br />
					'.$editcomment.'&nbsp;
					'.$makereply.'
					</td>
				</tr>
				'.$reply.'
			</table>
			';
		}
		//Don't make the overlay div over and over'
		if ($original) { $comments = make_search_box($arrows.$comments,"html_$htmlid");
        } else { $comments = $arrows.$comments; }

		if ($hide) { $comments = '<div id="hmtl_'.$htmlid.'_comments_button" class="button" style="display:block;margin-right:auto;margin-left:auto;font-size:.8em;width:90px;" onclick="hide_show_buttons(\'hmtl_'.$htmlid.'_comments_button\',true); hide_show_buttons(\'hmtl_'.$htmlid.'_comments\',true)">Show Comments</div><div id="hmtl_'.$htmlid.'_comments" style="display:none;">' . $header . $comments . '</div>'; }
	}
	return $comments;
}

function get_html_replies($commentid,$hidebuttons,$pageid) {
global $CFG,$USER;
	$replies = $editreply = $deletereply = "";
	$SQL = "SELECT * FROM html_replies WHERE commentid='$commentid' LIMIT 1";
	if ($result = get_db_result($SQL)) {
		while ($row = fetch_row($result)) {
			$username = get_user_name($row['userid']);
			if (!$hidebuttons) { $editreply = user_has_ability_in_page($USER->userid,"makereplies",$pageid) ? '<br />'.make_modal_links(array("title"=>"Edit","path"=>$CFG->wwwroot."/features/html/html.php?action=editreply&amp;pageid=$pageid&amp;commentid=$commentid&amp;replyid=".$row["replyid"],"refresh"=>"true","styles"=>"float:left;padding: 2px;")).'<br />' : '';}
			if (!$hidebuttons) { $deletereply = user_has_ability_in_page($USER->userid,"deletereply",$pageid) ? make_modal_links(array("title"=>"Delete Reply","path"=>$CFG->wwwroot."/features/html/html.php?action=deletereply&amp;pageid=$pageid&amp;replyid=".$row["replyid"],"refresh"=>"true","image"=>$CFG->wwwroot."/images/delete.png")) : "";}
			$replies =
			'
				<tr class="blogcommentheader">
					<td style="text-align:left;vertical-align:middle;">
						'.$username.' replies
					</td>
					<td style="text-align:right;">
						'.$deletereply.'
					</td>
				</tr>
				<tr class="blogcomment">
					<td colspan="2">
					'.$row['reply'].'<br />
					'.$editreply.'
					</td>
				</tr>
			';
		}
	}
	return $replies;
}

function html_delete($pageid,$featureid,$sectionid) {
	execute_db_sql("DELETE FROM pages_features WHERE feature='html' AND pageid='$pageid' AND featureid='$featureid'");
	execute_db_sql("DELETE FROM html WHERE pageid='$pageid' and htmlid='$featureid'");
	resort_page_features($pageid);
}

function html_buttons($pageid,$featuretype,$featureid) {
global $CFG,$USER;
	$settings = fetch_settings("html",$featureid,$pageid);
	$blog = $settings->html->$featureid->blog->setting;

	$html_abilities = get_user_abilities($USER->userid,$pageid,"html","html",$featureid);
	$feature_abilities = get_user_abilities($USER->userid,$pageid,"features","html",$featureid);

	$returnme = "";
	if ($blog && !empty($feature_abilities->addfeature->allow)) { $returnme .= ' <a class="slide_menu_button" title="Add Blog Edition" onclick="if (confirm(\'Do you want to make a new blog edition?  This will move the current blog to the Blog Locker.\')) { ajaxapi(\'/features/html/html_ajax.php\',\'new_edition\',\'&amp;pageid='.$pageid.'&amp;htmlid='.$featureid.'\',function() { refresh_page(); });}"><img src="'.$CFG->wwwroot.'/images/add.png" alt="Delete Feature" /></a> '; }

    if (!empty($html_abilities->edithtml->allow)) { $returnme .= make_modal_links(array("title"=>"Edit HTML","path"=>$CFG->wwwroot."/features/html/html.php?action=edithtml&amp;pageid=$pageid&amp;featureid=$featureid","runafter"=>"html_".$featureid."_stopped_editing","iframe"=>"true","refresh"=>"true","width"=>"950","image"=>$CFG->wwwroot."/images/edit.png","class"=>"slide_menu_button")); }

    if (!$blog && user_has_ability_in_page($USER->userid,"addtolocker",$pageid)) { $returnme .= '  <a class="slide_menu_button" title="Move to Blog Locker" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid='.$pageid.'&amp;featuretype='.$featuretype.'&amp;sectionid=&amp;featureid='.$featureid.'&amp;direction=locker\',function() { refresh_page(); });"><img src="'.$CFG->wwwroot.'/images/vault.png" alt="Move to Blog Locker" /></a> '; }
	return $returnme;
}

function html_template($html) {
	return '<div class="html_template">'.$html.'</div>';
}

function html_rss($feed, $userid, $userkey) {
global $CFG;
	$feeds = "";

	$settings = fetch_settings("html",$feed["featureid"],$feed["pageid"]);
	if ($settings->html->$feed["featureid"]->enablerss->setting) {
		if ($settings->html->$feed["featureid"]->blog->setting) {
			$html = get_db_row("SELECT * FROM html WHERE htmlid='".$feed["featureid"]."'");
			if ($html['firstedition']) { //this is not a first edition
				$htmlresults = get_db_result("SELECT * FROM html WHERE htmlid='".$html["firstedition"]."' OR firstedition='".$html["firstedition"]."' ORDER BY htmlid DESC LIMIT 50");
			} else {
				$htmlresults = get_db_result("SELECT * FROM html WHERE htmlid='".$html["htmlid"]."' OR firstedition='".$html["htmlid"]."' ORDER BY htmlid DESC LIMIT 50");
			}

			while ($html = fetch_row($htmlresults)) {
				$settings = fetch_settings("html",$html["htmlid"],$feed["pageid"]);
				$feeds .= fill_feed($settings->html->$html["htmlid"]->feature_title->setting . " " . date('d/m/Y',$html["dateposted"]),substr($html["html"],0,100),$CFG->wwwroot.'/features/html/html.php?action=viewhtml&key='.$userkey.'&pageid='.$feed["pageid"].'&htmlid='.$html["htmlid"],$html["dateposted"]);
			}
		} else {
			$html = get_db_row("SELECT * FROM html WHERE htmlid='".$feed["featureid"]."'");
			$feeds .= fill_feed($settings->html->$feed["featureid"]->feature_title->setting,substr($html["html"],0,100),$CFG->wwwroot.'/features/html/html.php?action=viewhtml&key='.$userkey.'&pageid='.$feed["pageid"].'&htmlid='.$feed["featureid"],$html["dateposted"]);
		}
	}
	return $feeds;
}

function html_default_settings($feature,$pageid,$featureid) {
	$settings_array[] = array(false,"$feature","$pageid","$featureid","feature_title","HTML",false,"HTML","Feature Title","text");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","siteviewable","0",false,"0","Site Viewable","no/yes");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","pageviewable","0",false,"0","Page Viewable","no/yes");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","blog","0",false,"0","Blog Mode (editions)","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","enablerss","0",false,"0","Enable RSS","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","allowcomments","0",false,"0","Allow Comments","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","middlecommentlimit","10",false,"10","Limit Replies Shown in Middle","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","sidecommentlimit","3",false,"3","Limit Replies Shown on Side","text",true,"<=0","Must be greater than 0.");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","documentviewer","0",false,"0","Document Viewer Filter","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","embedaudio","0",false,"0","Embed Audio Links","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","embedvideo","0",false,"0","Embed Video Links","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","embedyoutube","0",false,"0","Embed Youtube.com Links","yes/no");
	$settings_array[] = array(false,"$feature","$pageid","$featureid","photogallery","0",false,"0","Auto Photogallery","yes/no");
	return $settings_array;
}
?>
