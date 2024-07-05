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
    $SQL = "SELECT * FROM html WHERE htmlid='$featureid'";
    $returnme = $makecomment = $comments = $rss = "";

    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            $limit = $area == "side" ? $settings->html->$featureid->sidecommentlimit->setting : $settings->html->$featureid->middlecommentlimit->setting;
            if ($settings->html->$featureid->allowcomments->setting) {
                $hidebuttons = $htmlonly ? true : false;
                if (user_is_able($USER->userid, "viewcomments", $pageid, "html", $row['htmlid'])) {
                    $comments = get_html_comments($row['htmlid'], $pageid, $hidebuttons, $limit);
                }

                if (user_is_able($USER->userid, "makecomments", $pageid, "html", $row['htmlid'])) {
                    $params = [
                        "title" => "Comment",
                        "path" => action_path("html") . "commentform&htmlid=" . $row['htmlid'],
                        "refresh" => "true",
                    ];
                    $makecomment = '<div class="html_makecomment">' . make_modal_links($params) . '</div>';
                }
            }

            $nomargin = 'style="margin: 12px;"';
            if (isset($settings->html->$featureid->allowfullscreen->setting) && $settings->html->$featureid->allowfullscreen->setting == 1) { // if fullscreen option is on, remove margin.
                $nomargin = 'style="margin:-8px;"';
            }

            $html = '<div class="htmlblock" ' . $nomargin . '>
                        ' . fullscreen_toggle(filter($row['html'], $featureid, $settings, $area), $featureid, $settings) . '
                    </div>';

                // If viewing from rss feed
            if ($htmlonly) {
                $middlecontents = '<div class="html_mini">
                                                <div class="html_title">
                                                ' . $settings->html->$featureid->feature_title->setting . '
                                                </div>
                                                <div class="html_text">
                                                ' . $html . '
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
                    'id'   => 'html_' . $featureid . '_stopped_editing',
                    'url'  => '/features/html/html_ajax.php',
                    'data' => [
                        'action' => 'stopped_editing',
                        'htmlid' => $featureid,
                    ],
                    'event' => 'none',
                ]);
                $buttons = get_button_layout("html", $row['htmlid'], $pageid);
                $title = $settings->html->$featureid->feature_title->setting;
                $title = '<span class="box_title_text">' . $title . '</span>';
                $returnme .= get_css_box($rss . $title, $html . $comments . $makecomment, $buttons, null, 'html', $featureid, false, false, false, false, false, false);
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

function fullscreen_toggle($html, $featureid, $settings) {
global $CFG;
    if (isset($settings->html->$featureid->allowfullscreen->setting) && $settings->html->$featureid->allowfullscreen->setting == 1) { // Allow fullscreen toggle.
        $html = '<div class="html_notfullscreen">
                    <a title="View Full Screen" href="javascript: void(0);" onclick="$(\'.html_notfullscreen div\').toggleClass(\'fs_icon_on\'); $(this).closest(\'.htmlblock\').toggleClass(\'html_fullscreen\');">
                        <div class="fs_icon"></div>
                    </a>
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
                    $icon = "save.png";
                    $filetypes = '/([\.[pP][dD][fF]|\.[dD][oO][cC]|\.[rR][tT][fF]|\.[pP][sS]|\.[pP][pP][tT]|\.[pP][pP][sS]|\.[tT][xX][tT]|\.[sS][xX][cC]|\.[oO][dD][sS]|\.[xX][lL][sS]|\.[oO][dD][tT]|\.[sS][xX][wW]|\.[oO][dD][pP]|\.[sS][xX][iI]])/';
                    if (preg_match($filetypes, $match[2])) {
                        if (strstr($match[2], $CFG->directory . '/userfiles') || strstr($match[2], $CFG->wwwroot)) { // internal link.
                            $url = $CFG->wwwroot . strstr($match[2], '/userfiles/');
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
                        if (strstr($url, $CFG->directory . '/userfiles') &&
                            strstr($url, $CFG->wwwroot) &&
                            !file_exists($CFG->docroot . strstr($url, '/userfiles/'))) { // internal link check.
                            $icon = "deny.png";
                            $link = 'javascript: void(0);';
                            $title = "File Not Found: $url";
                            $url = "";
                        } else {
                            $title = $url;
                            $link = $CFG->wwwroot . '/scripts/download.php?file=' . $url;
                        }
                        $html = str_replace($match[0], '<a title="' . $title . '" href="' . $link . '" onclick="blur();"><img src="' . $CFG->wwwroot . '/images/' . $icon . '" alt="Save" /></a>&nbsp;' . make_modal_links(["text" => $text, "title" => $title, "path" => $CFG->wwwroot . "/pages/ipaper.php?action=view_ipaper&doc_url=" . base64_encode($url),"height" => "80%", "width" => "80%"]), $html);
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
                    $url = strstr($match[2], $CFG->directory . '/userfiles') && !strstr($match[2], $CFG->wwwroot) ? str_replace($CFG->directory . '/userfiles', $CFG->wwwroot . '/userfiles', $match[2]) : $match[2];
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
                    $url = strstr($match[2], $CFG->directory . '/userfiles') && !strstr($match[2], $CFG->wwwroot) ? str_replace($CFG->directory . '/userfiles', $CFG->wwwroot . '/userfiles', $match[2]) : $match[2];
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
                    $url = strstr($match[2], $CFG->directory . '/userfiles') && !strstr($match[2], $CFG->wwwroot) ? str_replace($CFG->directory . '/userfiles', $CFG->wwwroot . '/userfiles', $match[2]) : $match[2];
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
            $localdirectory = $CFG->dirroot . "/" . substr($url, strpos($url, "userfiles"));
            if (substr($localdirectory, -1) == '/') {
                $localdirectory = substr($localdirectory, 0, -1);
            }

            if (is_readable($localdirectory) && strpos($url, 'userfiles') !== false) {
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
                                        $name = empty($display) ? '<img style="width:17px;height:17px;vertical-align: middle;" src="' . $CFG->wwwroot . '/images/gallery.png" /> ' . $match[4] : $match[4]; // Use text inside original hyperlink.
                                        $modalsettings = ["id" => "autogallery_$i", "title" => $caption, "text" => $name, "gallery" => $galleryid, "path" => $fileurl, "styles" => $display];
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
                    $name = '<img style="width:15px;height:15px;vertical-align: middle;" src="' . $CFG->wwwroot . '/images/image.png" /> ' . $match[4]; // Use text inside original hyperlink.
                    $modalsettings = ["id" => "autogallery_$i", "title" => $caption, "text" => $name, "gallery" => $galleryid, "path" => $fileurl];
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

function get_html_comments($htmlid, $pageid, $hidebuttons = false, $perpage = false, $pagenum = false, $hide = true) {
global $CFG, $USER;
    $returnme = $commenttext = $prev = $info = $next = $header = $arrows = $limit = "";

    $pagenum = $pagenum ?: 0;
    $perpage = $perpage ?: 0;

    $original = $pagenum ? false : true;
    $comments = gather_comments($htmlid, $pagenum, $perpage);
    if ($perpage) {
        $total = get_db_count("SELECT * FROM html_comments WHERE htmlid = '$htmlid'");
        $searchvars = get_search_page_variables($total, $perpage, $pagenum);
        $prev = $searchvars["prev"] ? '<a href="javascript: $(\'#loading_overlay_html_' . $htmlid . '\').show(); ajaxapi_old(\'/features/html/html_ajax.php\',\'commentspage\',\'&pagenum=' . ($pagenum - 1) . '&perpage=' . $perpage . '&pageid=' . $pageid . '&htmlid=' . $htmlid . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer_html_' . $htmlid . '\'); $(\'#loading_overlay_html_' . $htmlid . '\').hide(); }}, true); " onmouseup="this.blur()">Previous</a>' : "";
          $info = $searchvars["info"];
          $next = $searchvars["next"] ? '<a href="javascript: $(\'#loading_overlay_html_' . $htmlid . '\').show(); ajaxapi_old(\'/features/html/html_ajax.php\',\'commentspage\',\'&pagenum=' . ($pagenum + 1) . '&perpage=' . $perpage . '&pageid=' . $pageid . '&htmlid=' . $htmlid . '\',function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer_html_' . $htmlid . '\'); $(\'#loading_overlay_html_' . $htmlid . '\').hide(); }}, true);" onmouseup="this.blur()">Next</a>' : "";
          $arrows = '<table style="width:100%;"><tr><td style="width:25%;text-align:left;">' . $prev . '</td><td style="width:50%;text-align:center;color:green;">' . $info . '</td><td style="width:25%;text-align:right;">' . $next . '</td></tr></table><br /><br />';
        $limit = "LIMIT " .$searchvars["firstonpage"] . "," . $perpage;
    } else {
        $limit = "LIMIT $perpage";
    }

    if ($comments["collection"]) {
        $header = '<button class="smallbutton" id="html_' . $htmlid . '_hide_button" onclick="$(\'#html_' . $htmlid . '_comments_button, #html_' . $htmlid . '_comments\').toggle();">Hide Comments</button><br />';

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
            $returnme = make_search_box($arrows . $commenttext, "html_$htmlid");
        } else {
            $returnme = $arrows . $commenttext;
        }

        if ($hide) {
            $returnme = '<div class="centered">
                            <button id="html_' . $htmlid . '_comments_button" class="smallbutton" onclick="$(\'#html_' . $htmlid . '_comments_button, #html_' . $htmlid . '_comments\').toggle();">
                                  Show Comments
                            </button>
                            <div id="html_' . $htmlid . '_comments" style="display:none;">
                                ' . $header . $returnme . '
                            </div>
                        </div>';
        }
    }
    return $returnme;
}

function get_comment_buttons($params) {
global $CFG, $USER, $PAGE;
    $pageid = $params["pageid"] ?? $PAGE->id;

    $caneditowncomment = ($USER->userid == $params["comment"]["userid"] && user_is_able($USER->userid, "makecomments", $pageid));
    $deletecomment = $editcomment = $makereply = false;
    // DELETE BUTTON.
    if ($caneditowncomment || user_is_able($USER->userid, "deletecomments", $pageid)) {
        $deletecomment = make_modal_links([
            "title" => "Delete Comment",
            "path" => action_path("html") . "deletecomment&commentid=" . $params["comment"]['commentid'],
            "refresh" => "true",
            "image" => $CFG->wwwroot . "/images/delete.png",
        ]);
    }

    // EDIT BUTTON.
    if ($caneditowncomment || user_is_able($USER->userid, "editanycomment", $pageid)) {
        $editcomment = make_modal_links([
            "title" => "Edit Comment",
            "path" => action_path("html") . "commentform&commentid=" . $params["comment"]['commentid'],
            "refresh" => "true",
            "icon" => icon("pencil"),
        ]);
    }

    // REPLY BUTTON.
    if (user_is_able($USER->userid, "makereplies", $pageid)) {
        $makereply = make_modal_links([
            "title" => "Reply",
            "path" => action_path("html") . "commentform&replytoid=" . $params["comment"]['commentid'],
            "refresh" => "true",
            "image" => $CFG->wwwroot . "/images/undo.png",
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