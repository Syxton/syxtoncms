<?php
/***************************************************************************
* htmllib.php - HTML feature library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.4.9
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('HTMLLIB', true);

function display_html($pageid, $area, $featureid) {
global $CFG, $USER, $HTMLSETTINGS;
    $abilities = user_abilities($USER->userid, $pageid, "html", "html", $featureid);
    if (!$settings = fetch_settings("html", $featureid, $pageid)) {
        save_batch_settings(default_settings("html", $pageid, $featureid));
        $settings = fetch_settings("html", $featureid, $pageid);
    }

    if (!empty($abilities->viewhtml->allow)) {
        return get_html_feature($pageid, $featureid, $settings, $abilities, $area);
    }
}

function get_html($htmlid) {
    $SQL = fetch_template("dbsql/html.sql", "get_html", "html");
    return get_db_row($SQL, ['htmlid' => $htmlid]);
}

function get_html_feature($pageid, $featureid, $settings, $abilities, $area = false, $htmlonly = false) {
global $CFG, $USER;
    $SQL = "SELECT * FROM html WHERE htmlid = ||htmlid||";
    $returnme = $makecomment = $comments = $rss = "";

    if ($result = get_db_result($SQL, ['htmlid' => $featureid])) {
        while ($row = fetch_row($result)) {
            $limit = $area == "side" ? $settings->html->$featureid->sidecommentlimit->setting : $settings->html->$featureid->middlecommentlimit->setting;
            if ($settings->html->$featureid->allowcomments->setting) {
                $hidebuttons = $htmlonly ? true : false;

                if (user_is_able($USER->userid, "makecomments", $pageid, "html", $row['htmlid'])) {
                    $params = [
                        "title" => "Make Comment",
                        "path" => action_path("html") . "commentform&htmlid=" . $row['htmlid'],
                        "icon" => icon("comment-medical", 2),
                    ];
                    $makecomment = '
                        <div class="html_makecomment">
                            ' . make_modal_links($params) . '
                        </div>';
                }

                if (user_is_able($USER->userid, "viewcomments", $pageid, "html", $row['htmlid'])) {
                    $listcomments = get_html_comments($row['htmlid'], $pageid, $hidebuttons, $limit);
                    $hide = empty($listcomments) ? "display:none;" : "";

                    $params = [
                        "makecomment" => $makecomment,
                        "featureid" => $featureid,
                        "comments" => $listcomments,
                    ];
                    $comments = fill_template("tmp/html.template", "comment_area", "html", $params);
                }

                $comments = '<div class="html_comments_grid">' . $comments . '</div>';
            }

            $content = '
                <div class="htmlblock">
                    ' . fullscreen_toggle(process_html_filters($row['html'], $featureid, $settings, $area), $featureid, $settings) . '
                </div>';

            // If viewing from rss feed
            if ($htmlonly) {
                $middlecontents = '
                    <div class="html_mini">
                        <div class="html_title">
                            ' . $settings->html->$featureid->feature_title->setting . '
                        </div>
                        <div class="html_text">
                            ' . $content . '
                        </div>
                    </div>';
                $returnme .= fill_template("tmp/index.template", "simplelayout_template", false, ["mainmast" => page_masthead(true, true), "middlecontents" => $middlecontents]);

            } else { // Regular html feature viewing
                if (is_logged_in() && $settings->html->$featureid->enablerss->setting) {
                    $modalsettings = [
                        "title" => "RSS Feed",
                        "path" => action_path("rss", false) . "rss_subscribe_feature&pageid=$pageid&featureid=$featureid&feature=html",
                        "styles" => "display:inline-block;padding-right: 4px;",
                        "iframe" => true,
                        "refresh" => "true",
                        "width" => "640",
                        "icon" => icon([
                            ["icon" => "square", "stacksize" => 2, "color" => "white"],
                            ["icon" => "square-rss"],
                        ]),
                    ];
                    $rss = make_modal_links($modalsettings);

                    if ($feed = find_feed(false, "html", $featureid)) {
                        $rss .= feed_link($feed["rssid"], $feed["userkey"], $settings->html->$featureid->feature_title->setting);
                    }
                }

                ajaxapi([
                    "id" => "html_" . $featureid . "_stopped_editing",
                    "url" => "/features/html/html_ajax.php",
                    "data" => [
                        "action" => "stopped_editing",
                        "htmlid" => $featureid,
                    ],
                    "event" => "none",
                ]);
                $buttons = get_button_layout("html", $row['htmlid'], $pageid);
                $title = $settings->html->$featureid->feature_title->setting;
                $title = '<span class="box_title_text">' . $title . '</span>';
                $html_grid = '<div class="html_grid">' . $content . $comments . '</div>';
                $returnme .= get_css_box($rss . $title, $html_grid, $buttons, null, 'html', $featureid, false, false, false, false, false, false);
            }
        }
    }
    return $returnme;
}

/**
 * Process all the filters for a given html feature.
 *
 * @param string $html
 * @param int $featureid
 * @param array $settings
 * @param string $area
 * @return string
 */
function process_html_filters($html, $featureid, $settings, $area = "middle") {
global $CFG;
    /**
     * Document Viewer Filter
     *
     * @see filter_docviewer()
     */
    if (isset($settings->html->$featureid->documentviewer->setting) && $settings->html->$featureid->documentviewer->setting == 1) {
        $html = filter_docviewer($html);
    }

    /**
     * Embed audio player
     *
     * @see filter_embedaudio()
     */
    if (isset($settings->html->$featureid->embedaudio->setting) && $settings->html->$featureid->embedaudio->setting == 1) {
        $html = filter_embedaudio($html);
    }

    /**
     * Embed Video Player
     *
     * @see filter_embedvideo()
     */
    if (isset($settings->html->$featureid->embedvideo->setting) && $settings->html->$featureid->embedvideo->setting == 1) {
        $html = filter_embedvideo($html);
    }

    /**
     * Embed Youtube video player
     *
     * @see filter_youtube()
     */
    if (isset($settings->html->$featureid->embedyoutube->setting) && $settings->html->$featureid->embedyoutube->setting == 1) {
        $html = filter_youtube($html);
    }

    /**
     * Photo Gallery Filter
     *
     * @see filter_photogallery()
     */
    if (isset($settings->html->$featureid->photogallery->setting) && $settings->html->$featureid->photogallery->setting == 1) {
        $html = filter_photogallery($html);
    }

    return $html;
}

function fullscreen_toggle($html, $featureid, $settings) {
global $CFG;
    if (isset($settings->html->$featureid->allowfullscreen->setting) && $settings->html->$featureid->allowfullscreen->setting == 1) { // Allow fullscreen toggle.
        $html = '
            <div class="html_fullscreen_button">
                <button class="alike fullscreenbutton" title="View Full Screen" onclick="$(\'.html_notfullscreen div\').toggleClass(\'fs_icon_on\'); $(this).closest(\'.htmlblock\').toggleClass(\'html_fullscreen\');">
                </button>
            </div>
            <div class="html_text">
                ' . $html . '
            </div>';
    }
    return $html;
}

function filter_docviewer($html) {
global $CFG;
    if (isset($CFG->doc_view_key)) {
        $regex = '/(<[aA]\s*.[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s""\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(.[^\s]*)(<\/[aA]>)/';
        if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!strstr($match[0], 'javascript:')) { // not a javascript link.
                    $filetypes = '/([\.[pP][dD][fF]|\.[dD][oO][cC]|\.[rR][tT][fF]|\.[pP][sS]|\.[pP][pP][tT]|\.[pP][pP][sS]|\.[tT][xX][tT]|\.[sS][xX][cC]|\.[oO][dD][sS]|\.[xX][lL][sS]|\.[oO][dD][tT]|\.[sS][xX][wW]|\.[oO][dD][pP]|\.[sS][xX][iI]])/';
                    if (preg_match($filetypes, $match[2])) {
                        if (strstr($match[2], $CFG->userfilesurl) || strstr($match[2], $CFG->wwwroot)) { // internal link.
                            $url = $CFG->wwwroot . strstr($match[2], '/' . $CFG->userfilesfolder . '/');
                        } else { // external link.
                            $url = $match[2];
                        }

                        if (!empty($url)) {
                            // make full url if not full
                            $protocol = get_protocol() . "//";
                            $url_parts = parse_url($url);
                            $url = str_replace("://", "", $url);
                            $url = str_replace(":", "", $url);
                            $url = str_replace("//", "/", $url);
                            $url = trim($url, "/");

                            if (!empty($url_parts["scheme"])) { // protocol exists.
                                $url = str_replace($url_parts["scheme"], $protocol, $url);
                            } else {
                                $url = $protocol . $url;
                            }

                            // Make sure www is in the url.
                            $url = strstr($url, "://www.") !== false ? $url : str_replace("://", "://www.", $url);

                            //remove target from urls
                            if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/', $url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0], "", $url); }
                            $url = preg_replace('/([\'|\"])/', '', $url);

                            //make ipaper links
                            $url = str_replace('\\', '', $url);
                            $url = str_replace('../', '', $url);
                            $url = str_replace('..', '', $url);
                        }

                        $link = "";
                        $text = $match[4].$match[5];
                        if (strstr($url, $CFG->userfilespath) &&
                            strstr($url, $CFG->wwwroot) &&
                            !file_exists($CFG->docroot . strstr($url, '/' . $CFG->userfilesfolder . '/'))) { // internal link check.
                            $icon = icon("ban");
                            $link = 'javascript: void(0);';
                            $title = "File Not Found: $url";
                            $url = "";
                        } else {
                            $icon = icon("floppy-disk");
                            $title = $url;
                            $link = $CFG->wwwroot . '/scripts/download.php?file=' . $url;
                        }
                        $html = str_replace($match[0], '<a title="' . $title . '" href="' . $link . '" onclick="blur();">' . $icon . '</a>&nbsp;' . make_modal_links(["text" => $text, "title" => $title, "path" => $CFG->wwwroot . "/pages/ipaper.php?action=view_ipaper&doc_url=" . base64_encode($url),"height" => "80%", "width" => "80%"]), $html);
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
            if (!strstr($match[0], 'javascript:')) {
                $found = false;
                $filetypes = '/([\.[aA][aA][cC]|\.[mM][4][aA])/';
                if (preg_match($filetypes, $match[2])) {
                    //make internal links full paths
                    $url = strstr($match[2], $CFG->userfilespath) && !strstr($match[2], $CFG->wwwroot) ? str_replace($CFG->userfilespath, $CFG->userfilesurl, $match[2]) : $match[2];
                    //remove target from urls
                    if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/', $url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0], "", $url);}
                    $url = preg_replace('/([\'|\"])/', '', $url);

                    $url = str_replace('\\', '', $url);
                    $info = explode(".", $match[4].$match[5]);
                    $script = "var s$i = new SWFObject('" . $CFG->wwwroot . "/scripts/filters/video/player.swf','ply','290','30','9','#ffffff');
                                 s$i.addParam('allowfullscreen','true');
                                 s$i.addParam('allowscriptaccess','always');
                                 s$i.addParam('wmode','opaque');
                                 s$i.addParam('flashvars','file=" . stripslashes(urlencode($url)) . "&skin=" . $CFG->wwwroot . "/scripts/filters/video/skins/stylish_slim.swf');
                                 s$i.write('mediaspace_s$i');";
                    $html = str_replace($match[0], js_script_wrap($CFG->wwwroot . "/scripts/filters/video/swfobject.js") . "<span id='mediaspace_s$i'></span>" . js_code_wrap($script), $html);
                }

                $found = false;
                $filetypes = '/([\.[mM][pP][3])/';
                if (preg_match($filetypes, $match[2])) {
                    $player = "";
                    if (!$found) {
                        $player = js_script_wrap($CFG->wwwroot . "/scripts/filters/audio/audio-player.js") . js_code_wrap("AudioPlayer.setup('" . $CFG->wwwroot . "/scripts/filters/audio/player.swf', { width: 290 });");
                    }

                    $found = true;
                    //make internal links full paths
                    $url = strstr($match[2], $CFG->userfilespath) && !strstr($match[2], $CFG->wwwroot) ? str_replace($CFG->userfilespath, $CFG->userfilesurl, $match[2]) : $match[2];
                    //remove target from urls
                    if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/', $url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0], "", $url);}
                    $url = preg_replace('/([\'|\"])/', '', $url);
                    $url = str_replace('\\', '', $url);
                    $info = explode(".", $match[4].$match[5]);
                    $info = explode("-", $info[0]);
                    $script = "AudioPlayer.embed('audioplayer_$featureid"."_$i"."', {
                                    soundFile: '" . stripslashes($url) . "',
                                    titles: '$info[1]',
                                    artists: '$info[0]',
                                    autostart: 'no'
                                });";
                    $html = str_replace($match[0], $player . "<span id='audioplayer_$featureid"."_$i"."'></span>" . js_code_wrap($script), $html);
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
            if (!strstr($match[0], 'javascript:')) {
                $filetypes = '/([\.[fF][lL][vV]|\.[mM][pP][4])/';
                if (preg_match($filetypes, $match[2])) {
                    //make internal links full paths
                    $url = strstr($match[2], $CFG->userfilespath) && !strstr($match[2], $CFG->wwwroot) ? str_replace($CFG->userfilespath, $CFG->userfilesurl, $match[2]) : $match[2];
                    //remove target from urls
                    if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/', $url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0], "", $url);}
                    $url = preg_replace('/([\'|\"])/', '', $url);

                    $url = str_replace('\\', '', $url);
                    $rand = rand(0, time());
                    $script = " flowplayer('a.flowplayers', '" . $CFG->wwwroot . "/scripts/filters/video/flowplayer/flowplayer-3.2.4.swf',{
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
                                });";
                    $html = str_replace($match[0], js_script_wrap($CFG->wwwroot . '/scripts/filters/video/flowplayer/flowplayer-3.2.4.min.js') .
                             "<div id='vid_$rand' class='flowplayer_div' style='width:100%;'>
                                <a href='$url' style='display:block;' class='flowplayers' id='player_$rand'></a>
                            </div>" . js_code_wrap($script), $html);
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
            if (!strstr($url, '#noembed')) {
                if (!strstr($match[0], 'javascript:') && strlen($id) > 0) {
                    if (preg_match('/((http:\/\/)?(?:youtu\.be\/|(?:[a-z]{2,3}\.)?youtube\.com\/v\/)([\w-]{11}).*|http:\/\/(?:youtu\.be\/|(?:[a-z]{2,3}\.)?youtube\.com\/watch(?:\?|#\!)v=)([\w-]{11}).*)/i', $match[0]) || preg_match('/(\s*\.[yY][oO][uU][tT][uU][bB][eE]\.[cC][oO][mM][\/]\s*)/', $url)) {
                            $html = str_replace($match[0], '<div style="' . ($area == "middle" ? 'max-width:500px;margin:auto;' : '') . '"><div style="width: 100%; padding-top: 60%; margin-bottom: 5px; position: relative;"><iframe style="position: absolute; width: 100%; height: 100%; top: 0; left: 0;" src="//www.youtube.com/embed/' . $id . '"></iframe></div></div>', $html);
                    }
                }
            }
        }
    }
    return $html;
}

function filter_photogallery($html) {
global $CFG;
    $exts = ['jpeg', 'jpg', 'gif', 'png'];
    $regex = '/(<[aA]\s*.[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s""\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(<\/[aA]>)/';
    if (preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
        $i = 0;
        foreach ($matches as $match) {
            $url = $match[2];
            //make internal links full paths
            $localdirectory = $CFG->dirroot . "/" . substr($url, strpos($url, $CFG->userfilesfolder));
            if (substr($localdirectory, -1) == '/') {
                $localdirectory = substr($localdirectory, 0, -1);
            }

            if (is_readable($localdirectory) && strpos($url, $CFG->userfilesfolder) !== false) {
                $gallery = ""; $galleryid = uniqid("autogallery");
                if (is_dir($localdirectory)) { // directory
                    $captions = get_file_captions($localdirectory); // get the captions if they exist.
                    if (strpos($match[0], 'title="gallery"') !== false) {
                        $directoryList = opendir($localdirectory);
                        while ($file = readdir($directoryList)) {
                            if ($file != '.' && $file != '..') {
                                $path = $localdirectory . '/' . $file;
                                if (is_readable($path)) {
                                    if (is_file($path) && in_array(end(explode('.', end(explode('/', $file)))), $exts)) {
                                        $fileurl = $url . '/' . $file; // Use web url instead of local link.
                                        $caption = $captions[$file] ?? $file; // Either a caption or the filename
                                        $display = empty($gallery) ? "" : "display:none;";
                                        $modalsettings = ["icon" => icon("images"), "id" => "autogallery_$i", "title" => $caption, "text" => $match[4], "gallery" => $galleryid, "path" => $fileurl, "styles" => $display];
                                        $gallery .= empty($display) ? make_modal_links($modalsettings) : '<a href="' . $fileurl . '" title="' . $caption . '" data-rel="' . $galleryid . '" style="' . $display . '"></a>';
                                    }
                                }
                            }
                            //$i++;
                        }
                        closedir($directoryList);
                    }
                } else if (is_file($localdirectory)) { // file
                    $file = basename($localdirectory); // get just the filename and extention.
                    $captions = get_file_captions(str_replace($file, "", $localdirectory)); // get the caption from the image directory if possible
                    $fileurl = $url; // Use web url instead of local link.
                    $caption = $captions[$file] ?? $file; // Either a caption or the filename
                    $name = $match[4]; // Use text inside original hyperlink.
                    $modalsettings = ["icon" => icon("image"), "id" => "autogallery_$i", "title" => $caption, "text" => $name, "gallery" => $galleryid, "path" => $fileurl];
                    $gallery = make_modal_links($modalsettings);
                }

                if (!empty($gallery)) {
                    $html = str_replace($match[0], $gallery, $html);
                }
            }
            $i++;
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

function insert_blank_html($pageid) {
global $CFG;
    $type = "html";
    try {
        start_db_transaction();
        if ($featureid = execute_db_sql(fetch_template("dbsql/html.sql", "insert_html", "html"), ["pageid" => $pageid, "html" => "", "dateposted" => get_timestamp()])) {
            $area = get_db_field("default_area", "features", "feature = ||feature||", ["feature" => $type]);
            $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $area]) + 1;
            $params = [
                "pageid" => $pageid,
                "feature" => $type,
                "sort" => $sort,
                "area" => $area,
                "featureid" => $featureid,
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

function gather_comments($htmlid, $pagenum, $perpage, $collection = [], $totalcount = 0, $parentid = 0) {
    $SQL = "SELECT *
            FROM html_comments
            WHERE htmlid = '$htmlid'
            AND parentid = '$parentid'
            ORDER BY created, commentid";

    if ($comments = get_db_result($SQL)) {
        while ($comment = fetch_row($comments)) {
            // Too far.
            if ($totalcount > (($pagenum + 1) * $perpage)) {
                return ["collection" => $collection, "totalcount" => $totalcount];
            }

            // Enough collected.
            if (count($collection) >= $perpage) {
                return ["collection" => $collection, "totalcount" => $totalcount];
            }

            // Correct comments to be shown.
            if ($totalcount >= ($pagenum * $perpage)) {
                $collection[] = $comment;
            }

            $totalcount++;
            $replies = gather_comments($htmlid, $pagenum, $perpage, $collection, $totalcount, $comment["commentid"]);
            $collection = $replies["collection"];
            $totalcount = $replies["totalcount"];
        }
    }

    return ["collection" => $collection, "totalcount" => $totalcount];
}

function get_info_from_commentid($commentid) {
    $comment = get_db_row("SELECT * FROM html_comments WHERE commentid = ||commentid||", ["commentid" => $commentid]);
    $htmlid = $comment["htmlid"];
    $pageid = get_db_field("pageid", "html", "htmlid = ||htmlid||", ["htmlid" => $htmlid]);
    $area = get_feature_area("html", $htmlid);

    if (!$settings = fetch_settings("html", $htmlid, $pageid)) {
        save_batch_settings(default_settings("html", $pageid, $htmlid));
        $settings = fetch_settings("html", $htmlid, $pageid);
    }

    $perpage = $area == "side" ? $settings->html->$htmlid->sidecommentlimit->setting : $settings->html->$htmlid->middlecommentlimit->setting;

    return [
        "area" => $area,
        "pageid" => $pageid,
        "htmlid" => $htmlid,
        "perpage" => $perpage,
        "comment" => $comment,
    ];
}

function get_html_comments($htmlid, $pageid, $hidebuttons = false, $perpage = false, $pagenum = false, $hide = true) {
global $CFG, $USER;
    $returnme = $commenttext = $prev = $info = $next = $header = $pagenav = $limit = "";

    $pagenum = $pagenum ?: 0;
    $perpage = $perpage ?: 0;

    $original = $pagenum ? false : true;
    $comments = gather_comments($htmlid, $pagenum, $perpage);
    if ($perpage) {
        $total = get_db_count("SELECT * FROM html_comments WHERE htmlid = '$htmlid'");
        $searchvars = get_search_page_variables($total, $perpage, $pagenum);

        if ($searchvars["prev"]) {
            ajaxapi([
                "id" => "prev_commentpage_html_$htmlid",
                "url" => "/features/html/html_ajax.php",
                "data" => [
                    "action" => "commentspage",
                    "pagenum" => $pagenum - 1,
                    "perpage" => $perpage,
                    "pageid" => $pageid,
                    "htmlid" => $htmlid,
                ],
                "display" => "searchcontainer_html_$htmlid",
                "loading" => "loading_overlay_html_$htmlid",
            ]);
            $prev = '
                <button id="prev_commentpage_html_' . $htmlid . '" class="alike">
                    ' . icon("circle-chevron-left", 2) . '
                </button>';
        }
        $info = $searchvars["info"];

        if ($searchvars["next"]) {
            ajaxapi([
                "id" => "next_commentpage_html_$htmlid",
                "url" => "/features/html/html_ajax.php",
                "data" => [
                    "action" => "commentspage",
                    "pagenum" => $pagenum + 1,
                    "perpage" => $perpage,
                    "pageid" => $pageid,
                    "htmlid" => $htmlid,
                ],
                "display" => "searchcontainer_html_$htmlid",
                "loading" => "loading_overlay_html_$htmlid",
            ]);
            $next = '
                <button id="next_commentpage_html_' . $htmlid . '" class="alike">
                    ' . icon("circle-chevron-right", 2) . '
                </button>';
        }

        $pagenav = '
            <table style="width:100%;">
                <tr>
                    <td style="width:25%;text-align:left;">
                        ' . $prev . '
                    </td>
                    <td style="width:50%;text-align:center;color:green;">
                        ' . $info . '
                    </td>
                    <td style="width:25%;text-align:right;">
                        ' . $next . '
                    </td>
                </tr>
            </table>
            <br /><br />';
        $limit = "LIMIT " .$searchvars["firstonpage"] . "," . $perpage;
    } else {
        $limit = "LIMIT $perpage";
    }

    if ($comments["collection"]) {
        foreach ($comments["collection"] as $row) {
            $username = !$row['userid'] ? "Visitor" : get_user_name($row['userid']);

            $commentbuttons = ["delete" => "", "edit" => "", "reply" => ""];
            if (!$hidebuttons) {
                $commentbuttons = get_comment_buttons(["pageid" => $pageid, "comment" => $row]);
            }

            $params = [
                "username" => $username,
                "says" => $row['parentid'] ? "replied" : "says",
                "time" => $row['modified'] ? ($row['modified'] > $row['created'] ? "edited " : "") . ago($row['modified']) : "",
                "comment" => nl2br($row['comment']),
                "childclass" => $row['parentid'] ? "childcomment" : "",
                "buttons" => $commentbuttons,
            ];
            $commenttext .= fill_template("tmp/html.template", "comment_template", "html", $params);
        }

        // Wrap comments in div.
        $commenttext = '<div class="html_comments">' . $commenttext . '</div>';

        // Don't make the overlay div over and over'
        if ($original) {
            $returnme = make_search_box($pagenav . $commenttext, "html_$htmlid");
        } else {
            $returnme = $pagenav . $commenttext;
        }

        if ($hide) {
            $returnme = '
                <div id="html_' . $htmlid . '_comments">
                    ' . $returnme . '
                </div>';
        }
    }
    return $returnme;
}

function get_comment_buttons($params) {
global $CFG, $USER, $PAGE;
    $pageid = $params["pageid"] ?? $PAGE->id;
    $htmlid = $params["comment"]['htmlid'];
    $caneditowncomment = ($USER->userid == $params["comment"]["userid"] && user_is_able($USER->userid, "makecomments", $pageid));
    $deletecomment = $editcomment = $makereply = false;
    // DELETE BUTTON.
    if ($caneditowncomment || user_is_able($USER->userid, "deletecomments", $pageid)) {

        ajaxapi([
            "id" => "delete_comment_" . $params["comment"]['commentid'],
            "if" => "confirm('Are you sure you want to delete this comment?')",
            "url" => "/features/html/html_ajax.php",
            "data" => [
                "action" => "deletecomment",
                "commentid" => $params["comment"]['commentid'],
                "pageid" => $pageid,
            ],
            "display" => "comment_area_$htmlid",
            "ondone" => "if (data.message.length > 0) { $('#html_comment_button_box_$htmlid').show(); } else { $('#html_comment_button_box_$htmlid').hide(); }",
        ]);

        $deletecomment = '<button title="Delete Comment" id="delete_comment_' . $params["comment"]['commentid'] . '" class="alike">' . icon("trash") . '</button>';
    }

    // EDIT BUTTON.
    if ($caneditowncomment || user_is_able($USER->userid, "editanycomment", $pageid)) {
        $editcomment = make_modal_links([
            "title" => "Edit Comment",
            "path" => action_path("html") . "commentform&commentid=" . $params["comment"]['commentid'] . "&htmlid=$htmlid",
            "icon" => icon("pencil"),
        ]);
    }

    // REPLY BUTTON.
    if (user_is_able($USER->userid, "makereplies", $pageid)) {
        $makereply = make_modal_links([
            "title" => "Reply to Comment",
            "path" => action_path("html") . "commentform&replytoid=" . $params["comment"]['commentid'] . "&htmlid=$htmlid",
            "icon" => icon("reply"),
        ]);
    }

    return ["delete" => $deletecomment, "edit" => $editcomment, "reply" => $makereply];
}

function get_html_replies($commentid, $hidebuttons, $pageid) {
global $CFG, $USER;
    $replies = "";
    $SQL = "SELECT *
            FROM html_comments
            WHERE parentid = '$commentid'
            ORDER BY created, commentid";
    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            $username = !$row['userid'] ? "Visitor" : get_user_name($row['userid']);
            $commentbuttons = ["delete" => "", "edit" => "", "reply" => ""];
            if (!$hidebuttons) {
                $commentbuttons = get_comment_buttons(["pageid" => $pageid, "comment" => $row]);
            }
            $params = [
                "username" => $username,
                "comment" => nl2br($row['comment']),
                "buttons" => $commentbuttons,
            ];
            $replies .= fill_template("tmp/html.template", "comment_template", "html", $params);
        }
        return $replies;
    }
    return false;
}

function html_delete($pageid, $featureid) {
    $params = [
        "pageid" => $pageid,
        "featureid" => $featureid,
        "feature" => "html",
    ];

    try {
        start_db_transaction();
        $sql = [];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature"];
        $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature_settings"];
        $sql[] = ["file" => "dbsql/html.sql", "feature" => "html", "subsection" => "delete_html"];

        // Delete feature
        execute_db_sqls(fetch_template_set($sql), $params);

        resort_page_features($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
}

function html_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
    $settings = fetch_settings("html", $featureid, $pageid);
    $blog = $settings->html->$featureid->blog->setting;

    $html_abilities = user_abilities($USER->userid, $pageid, "html", "html", $featureid);
    $feature_abilities = user_abilities($USER->userid, $pageid, "features", "html", $featureid);

    $html = get_html($featureid);

    $returnme = "";
    if ($blog && !empty($feature_abilities->addfeature->allow)) {
        ajaxapi([
            "id" => "add_edition_$featureid",
            "url" => "/features/html/html_ajax.php",
            "if" => "confirm('Do you want to make a new blog edition?  This will move the current blog to the Blog Locker.')",
            "data" => [
                "action" => "new_edition",
                "pageid" => $pageid,
                "htmlid" => $featureid,
            ],
            "ondone" => "getRoot()[0].go_to_page($pageid);",
        ]);
        $returnme .= '
            <button class="slide_menu_button alike" title="Add Blog Edition" id="add_edition_' . $featureid . '">
                ' . icon("plus") . '
            </button>';
    }

    if (!empty($html_abilities->edithtml->allow)) {
        $returnme .= make_modal_links([
            "title" => "Edit HTML",
            "path" => action_path("html") . "edithtml&htmlid=$featureid",
            "onExit" => "killInterval('html_$featureid'); html_" . $featureid . "_stopped_editing();",
            "iframe" => true,
            "refresh" => "true",
            "width" => "$('#html_$featureid').width()",
            "icon" => icon("pencil"),
            "class" => "slide_menu_button",
        ]);
    }

    if (!$blog && user_is_able($USER->userid, "addtolocker", $pageid)) {
        ajaxapi([
            "id" => "movetolocker",
            "url" => "/ajax/site_ajax.php",
            "paramlist" => "pageid, featureid",
            "data" => [
                "action" => "change_locker_state",
                "pageid" => "js||pageid||js",
                "featuretype" => "html",
                "featureid" => "js||featureid||js",
                "direction" => "locker",
            ],
            "event" => "none",
            "ondone" => "getRoot()[0].go_to_page($pageid);",
        ]);
        $returnme .= '
            <button class="slide_menu_button alike" title="Move to Blog Locker" onclick="movetolocker(' . $pageid . ', ' . $featureid . ');">
                ' . icon("box-archive") . '
            </button>';
    }
    return $returnme;
}

function html_template($html) {
    return '<div class="html_template">' . $html . '</div>';
}

function html_rss($feed, $userid, $userkey) {
global $CFG;
    $feeds = "";

    $featureid = $feed["featureid"];
    $settings = fetch_settings("html", $featureid, $feed["pageid"]);
    if ($settings->html->$featureid->enablerss->setting) {
        $html = get_db_row("SELECT * FROM html WHERE htmlid = '$featureid'");
        if ($settings->html->$featureid->blog->setting) {
            if ($html['firstedition']) { //this is not a first edition
                $htmlresults = get_db_result("SELECT * FROM html WHERE htmlid='" . $html["firstedition"] . "' OR firstedition='" . $html["firstedition"] . "' ORDER BY htmlid DESC LIMIT 50");
            } else {
                $htmlresults = get_db_result("SELECT * FROM html WHERE htmlid='" . $html["htmlid"] . "' OR firstedition='" . $html["htmlid"] . "' ORDER BY htmlid DESC LIMIT 50");
            }

            while ($html = fetch_row($htmlresults)) {
                $htmlid = $html["htmlid"];
                $settings = fetch_settings("html", $htmlid, $feed["pageid"]);
                $feeds .= fill_feed($settings->html->$htmlid->feature_title->setting . " " . date('d/m/Y', $html["dateposted"]), substr($html["html"], 0, 100), $CFG->wwwroot . '/features/html/html.php?action=viewhtml&key=' . $userkey . '&pageid=' . $feed["pageid"] . '&htmlid=' . $htmlid, $html["dateposted"]);
            }
        } else {
            $feeds .= fill_feed($settings->html->$featureid->feature_title->setting, substr($html["html"], 0, 100), $CFG->wwwroot . '/features/html/html.php?action=viewhtml&key=' . $userkey . '&pageid=' . $feed["pageid"] . '&htmlid=' . $featureid, $html["dateposted"]);
        }
    }
    return $feeds;
}

function html_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "HTML",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "setting_name" => "blog",
            "defaultsetting" => "0",
            "display" => "Blog Mode (editions)",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "enablerss",
            "defaultsetting" => "0",
            "display" => "Enable RSS",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "allowcomments",
            "defaultsetting" => "0",
            "display" => "Allow Comments",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "middlecommentlimit",
            "defaultsetting" => "10",
            "display" => "Limit Comments Shown in Middle",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "sidecommentlimit",
            "defaultsetting" => "3",
            "display" => "Limit Comments Shown on Side",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
        [
            "setting_name" => "documentviewer",
            "defaultsetting" => "0",
            "display" => "Document Viewer Filter",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "embedaudio",
            "defaultsetting" => "0",
            "display" => "Embed Audio Links",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "embedvideo",
            "defaultsetting" => "0",
            "display" => "Embed Video Links",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "embedyoutube",
            "defaultsetting" => "0",
            "display" => "Embed Youtube Links",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "photogallery",
            "defaultsetting" => "0",
            "display" => "Auto Photogallery",
            "inputtype" => "yes/no",
        ],
        [
            "setting_name" => "allowfullscreen",
            "defaultsetting" => "0",
            "display" => "Allow Fullscreen",
            "inputtype" => "yes/no",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>