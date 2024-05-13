<?php
/***************************************************************************
* news.php - News thickbox page lib
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.4.0
***************************************************************************/
if (empty($_POST["aslib"])) {
    if (!isset($CFG)) {
		$sub = '';
		while (!file_exists($sub . 'header.php')) {
			$sub = $sub == '' ? '../' : $sub . '../';
		}
		include($sub . 'header.php');
	}

    if (!defined('NEWSLIB')) { include_once($CFG->dirroot . '/features/news/newslib.php');}

    callfunction();

    echo get_editor_javascript();

    echo '</body></html>';
}

function news_settings() {
global $CFG, $MYVARS, $USER;
	$featureid = dbescape($MYVARS->GET['featureid']); $pageid = dbescape($MYVARS->GET['pageid']);
	$feature = "news";

	//Default Settings
	$default_settings = default_settings($feature, $pageid, $featureid);

	//Check if any settings exist for this feature
	if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
	} else { //No Settings found...setup default settings
		if (save_batch_settings($default_settings)) { news_settings(); }
	}
}

function addeditnews() {
global $CFG, $MYVARS, $USER;
	$pageid = $MYVARS->GET["pageid"];
    $featureid= empty($MYVARS->GET["featureid"]) ? false : $MYVARS->GET["featureid"];
	$newsid= empty($MYVARS->GET["newsid"]) ? false : $MYVARS->GET["newsid"];

    $title = $caption = $content = "";
    if ($newsid) {
        if (!user_is_able($USER->userid, "editnews", $pageid,"news", $featureid)) { trigger_error(error_string("no_permission", ["editnews"]), E_USER_WARNING); return; }
        $row = get_db_row("SELECT * FROM news WHERE newsid='$newsid'");
        $title = stripslashes(htmlentities($row["title"]));
        $caption = stripslashes(htmlentities($row["caption"]));
        $content = stripslashes($row["content"]);
        $button = '<input type="button" value="Save" onclick="ajaxapi(\'/features/news/news_ajax.php\',\'edit_news\',\'&amp;title=\'+escape($(\'#news_title\').val())+\'&amp;summary=\' + escape($(\'#news_summary\').val()) + \'&amp;pageid=' . $pageid . '&amp;html=\'+escape(' . get_editor_value_javascript() . ')+\'&amp;newsid=' . $newsid . '\',function() { close_modal(); });" />';
    } else {
        if (!user_is_able($USER->userid, "addnews", $pageid,"news", $featureid)) { trigger_error(error_string("no_permission", ["addnews"]), E_USER_WARNING); return; }
        $button = '<input type="button" value="Save" onclick="ajaxapi(\'/features/news/news_ajax.php\',\'add_news\',\'&amp;title=\'+escape($(\'#news_title\').val())+\'&amp;summary=\' + escape($(\'#news_summary\').val()) + \'&amp;pageid=' . $pageid . '&amp;html=\'+escape(' . get_editor_value_javascript() . ')+\'&amp;featureid=' . $featureid . '\',function() { close_modal(); });" />';
    }

	echo '
		<div id="edit_news_div">
				<table style="width:100%">
					<tr>
						<td style="text-align:right;font-size: 12px;">
							News Title:
						</td>
						<td style="text-align:left; width:86%;">
							<input type="text" id="news_title" size="60" maxlength="60" value="' . $title . '"/>
						</td>
					</tr>
					<tr>
						<td style="text-align:right; vertical-align:top;font-size: 12px;">
							Summary:
						</td>
						<td style="text-align:left; width:86%;">
							<textarea id="news_summary" cols="60" rows="2" >' . $caption . '</textarea>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<hl />
						</td>
					</tr>
					<tr>
						<td colspan="2">';
                            echo get_editor_box(["initialvalue" => $content, "type" => "News", "height" => "230"]);
							echo '<br />' . $button . '
						</td>
					</tr>
				</table>
		</div>';
}

function viewnews() {
global $CFG, $MYVARS, $USER, $ROLES;
    $newsid = $MYVARS->GET['newsid'];
    $pageid = $MYVARS->GET['pageid'];
    $newsonly = isset($MYVARS->GET['newsonly']) ? true : false;
	if (is_logged_in()) {
		  if (!user_is_able($USER->userid, "viewnews", $pageid, "news", $newsid)) {
            trigger_error(error_string("no_permission", ["viewnews"]), E_USER_WARNING);
			return;
		} else {
			echo news_wrapper($newsid, $pageid, $newsonly);
		}
	} else {
		if (get_db_field("siteviewable", "pages", "pageid=$pageid") && role_is_able($ROLES->visitor, 'viewnews', $pageid)) {
            echo news_wrapper($newsid, $pageid, $newsonly);
		} else {
  			echo '	<div id="standalone_div">
						<input type="hidden" id="reroute" value="/features/news/news.php:viewnews:&amp;pageid=' . $pageid . '&amp;newsid=' . $newsid . ':standalone_div" />
						<div style="width:100%; text-align:center;">
							You must login to see this content.
							<br />
							<center>
								' . get_login_form(true, false) . '
							</center>
						</div>
					  </div>';
		}
	}
}

function news_wrapper($newsid, $pageid, $newsonly) {
global $CFG;
	$news = get_db_row("SELECT * FROM news WHERE newsid=$newsid");
	$daygraphic = get_date_graphic($news['submitted'], true, true);
    $pagenews = new \stdClass;
	$pagenews->newsid = $news['newsid'];
	$pagenews->title = stripslashes($news['title']);
	$pagenews->caption = stripslashes($news['caption']);
	$pagenews->submitted = $news['submitted'];
	$pagenews->userid = $news['userid'];
	$display_news = $news['content'] == "" ? stripslashes($news['caption']) : stripslashes($news['content']);
	if ($newsonly) {
		return '<div>
					<h1 style="font-size:3em;text-align: center;">' . $pagenews->title . '</h1>
					<div style="font-size:1.8em;text-align: center;color:grey;">' . $pagenews->caption . '</div>
					<div style="font-size:1em;text-align:right;padding:10px;">By: ' . get_user_name($pagenews->userid) . '</div>
					<br />
					' . $display_news . '
				</div>';
	} else {
        $pagename = get_db_field("name", "pages", "pageid = '$pageid'");
        $middlecontents = '<a class="buttonlike" style="margin: 10px" href="' . $CFG->wwwroot . '/index.php?pageid=' . $pageid . '">Navigate to ' . $pagename . '</a>
                            <div class="html_mini">
                            ' . make_news_table($pageid, $pagenews, "middle", $daygraphic, true) . '<br />
                            ' . $display_news . '
                            </div>';
        return get_css_set("main") . use_template("tmp/index.template", ["mainmast" => page_masthead(true, true), "middlecontents" => $middlecontents], "simplelayout_template");
	}
}
?>
