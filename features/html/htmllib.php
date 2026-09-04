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

                $makecomment = make_comment_button($row['htmlid'], $pageid);

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
                $returnme .= fill_template("tmp/index.template", "simplelayout_template", false, ["mainmast" => page_masthead(false, false), "middlecontents" => $middlecontents]);

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
        return $html;
    }

    $docExts = 'pdf|doc|docx|rtf|ppt|pptx|pps|txt|xls|xlsx|ods|odt|odp|sxc|sxw|sxi';
    $regex = '/(<[aA]\s.*[^>]*)(?:[hH][rR][eE][fF]\s*=)(?:[\s"\']*)(?!#|[Mm]ailto|[lL]ocation.|[jJ]avascript|.*css|.*this\.)(.*?)(\s*[\"|\']>)(.*?)(.[^\s]*)(<\/[aA]>)/';

    if (!preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
        return $html;
    }

    foreach ($matches as $match) {
        if (strstr($match[0], 'javascript:')) {
            continue;
        }

        $href = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $href = preg_replace('/([\'"])/', '', $href);

        // --- extension detection ---
        $ext = '';
        if (stripos($href, 'filegate.php') !== false) {
            $parts = parse_url($href);
            parse_str($parts['query'] ?? '', $q);
            $rel = (string) ($q['p'] ?? '');
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        } else {
            // path or last path segment before ?/#
            $path = parse_url($href, PHP_URL_PATH) ?: $href;
            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }

        if ($ext === '' || !preg_match('/^(' . $docExts . ')$/i', $ext)) {
            continue;
        }

        // Gated link that would 403/404 at filegate.php - swap the
        // icon/modal link for a small placeholder instead of pointing at a
        // doc_url that will fail when opened.
        if (stripos($href, 'filegate.php') !== false && ($status = fm_gated_url_predict_status($href)) !== null) {
            $pos = strpos($html, $match[0]);
            if ($pos !== false) {
                $html = substr_replace($html, fm_gate_placeholder_html($status, fm_gate_filename_from_url($href)), $pos, strlen($match[0]));
            }
            continue;
        }

        // --- build a usable absolute URL ---
        if (stripos($href, 'filegate.php') !== false) {
            // Keep gated URLs intact (token/mtime/level must survive).
            $url = $href;
            // Absolute if relative
            if (!preg_match('#^https?://#i', $url)) {
                $url = rtrim($CFG->wwwroot, '/') . '/' . ltrim($url, '/');
            }
        } elseif (strstr($href, $CFG->userfilesurl) || strstr($href, $CFG->wwwroot)) {
            $url = $CFG->wwwroot . strstr($href, '/' . $CFG->userfilesfolder . '/');
        } else {
            $url = $href;
        }

        // Normalize scheme / www only for non-gated links
        if (stripos($href, 'filegate.php') !== false) {
            $url = $href;

            // Protocol-relative: //syxtoncms.test/filegate.php?...
            if (strpos($url, '//') === 0) {
                $scheme = parse_url($CFG->wwwroot, PHP_URL_SCHEME) ?: 'https';
                $url = $scheme . ':' . $url;   // → https://syxtoncms.test/filegate.php?...
            }

            if (!preg_match('#^https?://#i', $url)) {
                $host   = parse_url($CFG->wwwroot, PHP_URL_HOST);
                $scheme = parse_url($CFG->wwwroot, PHP_URL_SCHEME) ?: 'https';

                if (isset($url[0]) && $url[0] === '/') {
                    // Root-relative: /filegate.php?...
                    $url = $scheme . '://' . $host . $url;
                } elseif (stripos($url, $host) === 0) {
                    // Bare host: syxtoncms.test/filegate.php?...
                    $url = $scheme . '://' . $url;
                } else {
                    // Relative: filegate.php?...
                    $url = rtrim($CFG->wwwroot, '/') . '/' . ltrim($url, '/');
                }
            }
        }

        // strip any leftover target="..." that landed in the href capture
        if (preg_match('/\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"\']?[^\s]*/', $url, $target)) {
            $url = str_replace($target[0], '', $url);
        }

        $text  = $match[4] . $match[5];
        $title = $url;
        $icon  = icon('floppy-disk');
        $dl    = $CFG->wwwroot . '/scripts/download.php?file=' . rawurlencode($url);

        // Skip the old filesystem "file not found" check for gated URLs;
        // download.php / filegate already enforce existence + permissions.
        if (stripos($url, 'filegate.php') === false
            && strstr($url, $CFG->userfilespath)
            && strstr($url, $CFG->wwwroot)
            && !file_exists($CFG->docroot . strstr($url, '/' . $CFG->userfilesfolder . '/'))
        ) {
            $icon  = icon('ban');
            $dl    = 'javascript:void(0);';
            $title = "File Not Found: $url";
            $url   = '';
        }

        $modal = make_modal_links([
            'text'   => $text,
            'title'  => $title,
            'path'   => $CFG->wwwroot . '/pages/ipaper.php?action=view_ipaper&doc_url=' . base64_encode($url),
        ]);

        $html = str_replace(
            $match[0],
            '<a title="' . htmlspecialchars($title) . '" href="' . $dl . '" onclick="blur();">' . $icon . '</a>&nbsp;' . $modal,
            $html
        );
    }

    return $html;
}

function filter_embedaudio($html) {
    global $CFG;

    // Formats browsers can play natively in <audio>
    $audioExts = ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'wav', 'webm'];

    $regex = '/<a\b[^>]*\bhref\s*=\s*(["\'])(?!#|mailto:|javascript:|location\.|.*?\.css|this\.)([^"\']+)\1[^>]*>(.*?)<\/a>/is';
    if (!preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
        return $html;
    }

    foreach ($matches as $index => $match) {
        $href = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $href = preg_replace('/[\'"]/', '', $href);

        $isFilegate = stripos($href, 'filegate.php') !== false;

        // Extension: filegate → p= param; otherwise path
        $ext = '';
        if ($isFilegate) {
            $parts = parse_url($href);
            parse_str($parts['query'] ?? '', $q);
            $rel = (string) ($q['p'] ?? '');
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        } else {
            $path = parse_url($href, PHP_URL_PATH) ?: $href;
            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }

        if ($ext === '' || !in_array($ext, $audioExts, true)) {
            continue;
        }

        // Gated link that would 403/404 at filegate.php - swap the player
        // for a small placeholder instead of embedding a src that will fail.
        if ($isFilegate && ($status = fm_gated_url_predict_status($href)) !== null) {
            $pos = strpos($html, $match[0]);
            if ($pos !== false) {
                $html = substr_replace($html, fm_gate_placeholder_html($status, fm_gate_filename_from_url($href)), $pos, strlen($match[0]));
            }
            continue;
        }

        // Build a usable URL
        if ($isFilegate) {
            $url = $href;
            if (!preg_match('#^https?://#i', $url)) {
                if (strpos($url, '//') === 0) {
                    $scheme = parse_url($CFG->wwwroot, PHP_URL_SCHEME) ?: 'https';
                    $url = $scheme . ':' . $url;
                } elseif (isset($url[0]) && $url[0] === '/') {
                    $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
                    $scheme = parse_url($CFG->wwwroot, PHP_URL_SCHEME) ?: 'https';
                    $url = $scheme . '://' . $host . $url;
                } else {
                    $url = rtrim($CFG->wwwroot, '/') . '/' . ltrim($url, '/');
                }
            }
        } elseif (strstr($href, $CFG->userfilespath) && !strstr($href, $CFG->wwwroot)) {
            $url = str_replace($CFG->userfilespath, $CFG->userfilesurl, $href);
        } else {
            $url = $href;
        }

        // Strip accidental target="..." left in the capture
        if (preg_match('/\s*target\s*=\s*[\"\']?[^\s]*/i', $url, $target)) {
            $url = str_replace($target[0], '', $url);
        }
        $url = str_replace('\\', '', $url);

        $linkText = trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = $linkText !== '' ? $linkText : basename(parse_url($url, PHP_URL_PATH) ?: $url);

        // Optional: derive artist/title from "Artist - Title.mp3" style text
        $artist = '';
        $track  = $title;
        if (strpos($title, ' - ') !== false) {
            [$artist, $track] = array_map('trim', explode(' - ', $title, 2));
        }

        $mime = match ($ext) {
            'mp3'        => 'audio/mpeg',
            'm4a', 'aac' => 'audio/mp4',
            'ogg', 'oga' => 'audio/ogg',
            'wav'        => 'audio/wav',
            'webm'       => 'audio/webm',
            default      => 'audio/' . $ext,
        };

        $playerId = 'audioplayer_' . $index;

        $label = $artist !== ''
            ? htmlspecialchars($artist . ' — ' . $track, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars($track, ENT_QUOTES, 'UTF-8');

        $embed = sprintf(
            '<figure class="embedded-audio" id="%s">'
            . '<audio controls preload="metadata" style="max-width:100%%;width:320px;">'
            . '<source src="%s" type="%s">'
            . 'Your browser does not support HTML5 audio.'
            . '<a href="%s">Download</a>'
            . '</audio>'
            . '</figure>',
            htmlspecialchars($playerId, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($mime, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        );

        $pos = strpos($html, $match[0]);
        if ($pos !== false) {
            $html = substr_replace($html, $embed, $pos, strlen($match[0]));
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
                $filetypes = '/\.flv|\.mp4/i';
                if (preg_match($filetypes, $match[2])) {
                    //make internal links full paths
                    $url = strstr($match[2], $CFG->userfilespath) && !strstr($match[2], $CFG->wwwroot) ? str_replace($CFG->userfilespath, $CFG->userfilesurl, $match[2]) : $match[2];
                    //remove target from urls
                    if (preg_match('/(\s*[tT][aA][rR][gG][eE][tT]\s*=\s*[\"|\']*[^\s]*)/', $url, $target, PREG_OFFSET_CAPTURE)) { $url = str_replace($target[0], "", $url);}
                    $url = preg_replace('/([\'|\"])/', '', $url);

                    $url = str_replace('\\', '', $url);

                    // Gated link that would 403/404 at filegate.php - swap
                    // the player for a small placeholder instead of
                    // embedding a src that will fail.
                    if (stripos($url, 'filegate.php') !== false && ($status = fm_gated_url_predict_status($url)) !== null) {
                        $html = str_replace($match[0], fm_gate_placeholder_html($status, fm_gate_filename_from_url($url)), $html);
                    } else {
                        $html5player = '
                        <video width="100%" controls>
                            <source src="' . $url . '" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>';
                        $html = str_replace($match[0], $html5player, $html);
                    }
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

    $extensions = ['jpg', 'jpeg', 'gif', 'png'];
    $regex = '/<a\b[^>]*\bhref\s*=\s*(["\'])(?!#|mailto:|javascript:|location\.|.*?\.css|this\.)([^"\']+)\1[^>]*>(.*?)<\/a>/is';

    if (!preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
        return $html;
    }

    foreach ($matches as $index => $match) {
        $url = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $isFilegate = (stripos($url, 'filegate.php') !== false);
        $isUserfiles = (strpos($url, $CFG->userfilesfolder) !== false);

        if (!$isFilegate && !$isUserfiles) {
            continue;
        }

        // Resolve to a local path.
        if ($isFilegate) {
            $parts = parse_url($url);
            parse_str($parts['query'] ?? '', $q);
            $isFolderLink    = isset($q['m']) && (string) $q['m'] === '0';
            $rel             = (string) ($q['p'] ?? '');
            $ext             = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            $isGalleryFolder = stripos($match[0], 'title="gallery"') !== false;

            // Only treat this as a broken embed when it actually looks like
            // one this filter would have turned into a gallery - a folder
            // link marked title="gallery", or a direct link to an image
            // file - so other filegate links (docs, audio, plain downloads)
            // are left for whichever filter actually owns them.
            $looksLikeGallery = ($isFolderLink && $isGalleryFolder)
                || (!$isFolderLink && $ext !== '' && in_array($ext, $extensions, true));

            if ($looksLikeGallery) {
                $status = fm_gated_url_predict_status($url);
                if ($status !== null) {
                    $pos = strpos($html, $match[0]);
                    if ($pos !== false) {
                        $html = substr_replace($html, fm_gate_placeholder_html($status, fm_gate_filename_from_url($url)), $pos, strlen($match[0]));
                    }
                    continue;
                }
            }

            $localpath = fm_gated_url_to_path($url);
            if ($localpath === null) {
                continue;
            }
        } else {
            // Legacy userfiles URL → filesystem path
            $pos = strpos($url, $CFG->userfilesfolder);
            $rel = trim(urldecode(substr($url, $pos + strlen($CFG->userfilesfolder))), '/\\');
            $localpath = rtrim($CFG->userfilespath, '/\\') . DIRECTORY_SEPARATOR
                       . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        }

        if (!is_readable($localpath)) {
            continue;
        }

        $galleryid = 'autogallery_' . $index;
        $gallery   = '';

        if (is_dir($localpath)) {
            // Folder galleries require title="gallery" on the <a>.
            if (stripos($match[0], 'title="gallery"') === false) {
                continue;
            }

            // Filegate folder links use m=0 (evergreen index).
            if ($isFilegate) {
                $parts = parse_url($url);
                parse_str($parts['query'] ?? '', $q);
                if (!isset($q['m']) || (string) $q['m'] !== '0') {
                    continue;
                }
            }

            $captions = get_file_captions($localpath);

            if ($isUserfiles) {
                $files = getdirectoryfiles($localpath, $extensions);
                // name => name (legacy returns filenames only)
                $files = array_combine($files, $files) ?: [];
                foreach ($files as $name => $_) {
                    $files[$name] = rtrim($url, '/') . '/' . rawurlencode($name);
                }
            } else {
                // Expect [ filename => ['filename'=>..., 'fileurl'=>...], ... ]
                // or the list form you already use — normalize to name => url
                $raw = fm_get_gated_files_from_path($url, $extensions);
                $files = [];
                foreach ($raw as $key => $item) {
                    if (is_array($item)) {
                        $files[$item['filename']] = $item['fileurl'];
                    } else {
                        // if it returns filename => url already
                        $files[is_string($key) ? $key : $item] = is_array($item) ? $item['fileurl'] : $item;
                    }
                }
            }

            if (empty($files)) {
                // No images in this gallery folder — remove the link entirely
                // rather than leaving a dead gallery that opens as "(empty)".
                $pos = strpos($html, $match[0]);
                if ($pos !== false) {
                    // Keep the link text, drop the <a>
                    $html = substr_replace($html, $match[3], $pos, strlen($match[0]));
                }
                continue;
            }

            // Natural case-insensitive sort by filename
            uksort($files, fn($a, $b) => strnatcasecmp($a, $b));

            $firstName = array_key_first($files);
            $firstUrl  = $files[$firstName];
            unset($files[$firstName]);

            $gallery .= make_modal_links([
                'icon'    => icon('images'),
                'id'      => $galleryid,
                'title'   => $captions[$firstName] ?? $firstName,
                'text'    => $match[3],
                'gallery' => $galleryid,
                'path'    => $firstUrl,
            ]);

            foreach ($files as $filename => $fileurl) {
                $caption = $captions[$filename] ?? $filename;
                $gallery .= sprintf(
                    '<a href="%s" title="%s" data-rel="%s" style="display:none;"></a>',
                    htmlspecialchars($fileurl, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($galleryid, ENT_QUOTES, 'UTF-8')
                );
            }
        } elseif (is_file($localpath)) {
            $filename  = basename($localpath);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, $extensions, true)) {
                continue;
            }

            $captions = get_file_captions(dirname($localpath));

            $gallery = make_modal_links([
                'icon'    => icon('image'),
                'id'      => $galleryid,
                'title'   => $captions[$filename] ?? $filename,
                'text'    => $match[3],
                'gallery' => $galleryid,
                'path'    => $url,
            ]);
        }

        if ($gallery !== '') {
            // Replace only this occurrence (exact match).
            $pos = strpos($html, $match[0]);
            if ($pos !== false) {
                $html = substr_replace($html, $gallery, $pos, strlen($match[0]));
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
            WHERE htmlid = ||htmlid||
            AND parentid = ||parentid||
            ORDER BY created, commentid";

    if ($comments = get_db_result($SQL, ["htmlid" => $htmlid, "parentid" => $parentid])) {
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

    $original = $pagenum ? false : true;
    $total = get_db_count("SELECT * FROM html_comments WHERE htmlid = ||htmlid||", ["htmlid" => $htmlid]);
    $perpage = $perpage ?: 0;
    $pagenum = $pagenum !== false ? $pagenum : floor($total / $perpage);

    $comments = gather_comments($htmlid, $pagenum, $perpage);
    if ($perpage) {
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

        $pagenav = fill_template("tmp/main.template", "pagination_bar", "adminpanel", [
            "prev" => $prev,
            "next" => $next,
            "info" => $info,
        ]);

        $limit = "LIMIT " . $searchvars["firstonpage"] . "," . $perpage;
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

function make_comment_button($htmlid, $pageid) {
    global $USER;

    $makecomment = "";
    if (user_is_able($USER->userid, "makecomments", $pageid, "html", $htmlid)) {
        $params = [
            "text" => "Make Comment",
            "path" => action_path("html") . "commentform&htmlid=" . $htmlid,
            "icon" => icon("comment-medical", 2, "", "white"),
            "button" => true,
            "styles" => "padding: 10px;",
            "width" => "500",
            "class" => "html_make_comment_button",
        ];
        $makecomment = '
            <div class="html_makecomment">
                ' . make_modal_links($params) . '
            </div>';
    }
    return $makecomment;
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
            "width" => "$('#html_$featureid').width() + 150",
            "height" => "95%",
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