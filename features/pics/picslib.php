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
    if (!user_is_able($USER->userid, "managepics", $pageid)) {
        return trigger_error(error_string("no_permission", ["managepics"]));
    }

    $SQL = fetch_template("dbsql/pics.sql", "get_galleries", "pics", ["siteviewable" => ($pageid == $CFG->SITEID ? true : false)]);
    if ($allgalleries = get_db_result($SQL, ["pageid" => $pageid, "featureid" => $featureid])) {
        $g = 0;
        $gallerylist = (object) [
            $g => (object) [
                "name" => "All",
                "value" => "all",
            ],
        ];
        $g++;
        while ($galleries = fetch_row($allgalleries)) {
            $gallerylist->$g = (object) [
                "name" => $galleries["gallery_title"],
                "value" => $galleries["galleryid"],
            ];
            $g++;
        }

        // Pics manager page turn.
        ajaxapi([
            "id" => "pics_pageturn",
            "paramlist" => "pagenum=0",
            "url" => "/features/pics/pics_ajax.php",
            "data" => [
                "action" => "pics_pageturn",
                "pagenum" => "js||pagenum||js",
                "featureid" => $featureid,
                "galleryid" => "js||$('#gallery').val()||js",
            ],
            "display" => "searchcontainer",
            "loading" => "loading_overlay",
            "event" => "none",
        ]);

        $gallery_select = make_select([
            "properties" => [
                "name" => "gallery",
                "id" => "gallery",
                "onchange" => "pics_pageturn();",
            ],
            "values" => $gallerylist,
            "valuename" => "value",
            "displayname" => "name",
        ]);

        $return = '
            Select which gallery you wish to view.
            ' . $gallery_select . '
            Click on a picture to activate or deactivate it.
            <button title="Delete Gallery" style="float:right;padding:2px;" class="alike" onclick="if ($(\'#gallery\').val() != \'all\') { ajaxapi_old(\'/features/pics/pics_ajax.php\',\'delete_gallery\',\'&pageid=' . $pageid . '&featureid=' . $featureid . '&galleryid=\'+$(\'#gallery\').val(),function() { if (xmlHttp.readyState == 4) { simple_display(\'pics_manager\'); }}, true); } else { alert(\'Cannot delete all galleries at once.\') }">
                ' . icon("trash", 2) . '<span>Delete Gallery</span>
            </button>
            ' . get_searchcontainer(get_pics($featureid));
    } else {
        $return = '<br /><br /><div style="text-align:center">No images have been added.</div>';
    }

    return $return;
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


function get_pics($featureid, $galleryid = 0, $pagenum = 0, $order = false) {
global $CFG, $USER;
    $pageid = get_pageid();
    $order = $order ? $order : " dateadded DESC";
    $next = $prev = $deletepic = $activated = $siteviewable = $movepics = "";

    if (!$settings = fetch_settings("pics", $featureid, $pageid)) {
        save_batch_settings(default_settings("pics", $pageid, $featureid));
        $settings = fetch_settings("pics", $featureid, $pageid);
    }

    $perpage = $settings->pics->$featureid->picsperpage->setting;

    // Show only 1 gallery or all galleries
    if (!$galleryid) {
        $whichgallery = "";
        $full_order = " galleryid," . $order;
    } else {
        $whichgallery = " AND galleryid=$galleryid";
        $full_order = $order;
    }

    $canedit = user_is_able($USER->userid, "editpics", $pageid, "pics", $featureid);
    $candelete = user_is_able($USER->userid, "deletepics", $pageid, "pics", $featureid);

    $sitehidden = $canedit ? "" : " AND sitehidden = 0";
    $pagehidden = $canedit ? "" : " AND pagehidden = 0";

    if ($pageid == $CFG->SITEID) {
        $SQL = 'SELECT * FROM pics WHERE (featureid = ||featureid|| OR siteviewable = 1)' . $whichgallery . $sitehidden . ' ORDER BY' . $full_order;
    } else {
        $SQL = 'SELECT *
                FROM pics
                WHERE featureid = ||featureid||
                ' . $whichgallery . '
                ' . $pagehidden . '
                ORDER BY ' . $full_order;
    }

    $total = get_db_count($SQL, ["featureid" => $featureid]); // get the total for all pages returned.

    $firstonpage = $perpage * $pagenum;
    $limit = " LIMIT $firstonpage," . $perpage;
    $SQL .= $limit; //Limit to one page of return.
    if ($pages = get_db_result($SQL, ["featureid" => $featureid])) {
        if ($candelete) {
            // Image delete ajax code.
            ajaxapi([
                "id" => "delete_pic",
                "if" => "confirm('Do you want to delete this image?')",
                "paramlist" => "picsid",
                "url" => "/features/html/html_ajax.php",
                "data" => [
                    "action" => "delete_pic",
                    "picsid" => "js||picsid||js",
                ],
                "ondone" => "pics_pageturn($pagenum);",
                "event" => "none",
            ]);
        }

        if ($canedit) {
            // Image move ajax code.
            ajaxapi([
                "id" => "move_pic",
                "if" => "confirm('Do you want to move this image?')",
                "paramlist" => "picsid",
                "url" => "/features/pics/pics_ajax.php",
                "data" => [
                    "action" => "move_pic",
                    "picsid" => "js||picsid||js",
                    "galleryid" => "js||$('#movepics_' + picsid).val()||js",
                ],
                "ondone" => "pics_pageturn($pagenum);",
                "event" => "none",
            ]);

            // Image add/edit caption ajax code.
            ajaxapi([
                "id" => "save_caption",
                "paramlist" => "picsid",
                "url" => "/features/pics/pics_ajax.php",
                "data" => [
                    "action" => "save_caption",
                    "picsid" => "js||picsid||js",
                    "caption" => "js||encodeURIComponent($('#caption_' + picsid).val())||js",
                    "galleryid" => "js||$('#movepics_' + picsid).val()||js",
                ],
                "ondone" => "pics_pageturn($pagenum);",
                "event" => "none",
            ]);
        }

        $returnme = '';
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

            if ($canedit) {
                if ($candelete) {
                    $deletepic = '
                        <button class="alike" style="float:right;" title="Delete" onclick="delete_pic(' . $row['picsid'] . ');">
                            <span>' . icon("trash") . '</span>
                        </button>';
                }

                if (($pageid === $CFG->SITEID && $row["sitehidden"] === 1) || ($pageid !== $CFG->SITEID && $row["pagehidden"] === 1)) {
                    $activated = '';
                } else { //image is activated
                    $activated = 'pics_active';
                }
            }

            $disabled = $pageid == $CFG->SITEID && $row["pageid"] == $pageid ? "DISABLED" : "";
            $checked = $row["siteviewable"] == 1 ? " checked=checked" : "";

            if ($pageid === $CFG->SITEID && $row["pageid"] !== $pageid) {
                $reloadpage = true;
                $alreadysite1 = 'do_nothing();';
                $alreadysite2 = 'ajaxapi_old(\'/features/pics/pics_ajax.php\',
                                         \'pics_pageturn\',
                                         \'&pageid=' . $pageid . '&featureid=' . $featureid . '&galleryid=' . $galleryid . '&order=' . urlencode($order) . '&pagenum=' . ($pagenum) . '\',
                                         function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true);';
            } else { // Not on site and image is not from this page.
                $alreadysite1 = 'simple_display(\'picsid_' . $row["picsid"] . '\');
                                 setTimeout(function() { $(\'#picsid_' . $row["picsid"] . '\').hide(); }, 3000);';
                $alreadysite2 = '';
            }

            if ($canedit) {
                $caption = '
                    <div class="pics_manager_caption">
                        <textarea id="caption_' . $row["picsid"] . '" class="pics_manager_piccaption" onkeyup="pics_track_change(' . $row["picsid"] . ');">' . $row["caption"] . '</textarea>
                        <button class="alike" onclick="save_caption(' . $row["picsid"] . ');">
                            ' . icon("floppy-disk") . '
                        </button>
                    </div>';

                if ($pageid !== $CFG->SITEID) {
                    $siteviewable = '
                        <div class="pics_manager_siteviewable">
                            <input type="checkbox" style="vertical-align:middle" id="siteviewable_' . $row["picsid"] . '"
                                ' . $disabled . '
                                 onchange="if (confirm(\'Do you want to change the site viewability of this image?\')) {
                                                $(\'#picsid_' . $row["picsid"] . '\').show();
                                                ajaxapi_old(\'/features/pics/pics_ajax.php\',
                                                \'save_viewability\',
                                                \'&picsid=' . $row["picsid"] . '&siteviewable=\' + $(\'#siteviewable_' . $row["picsid"] . '\').prop(\'checked\'),
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
                        </div>';
                }
            } else {
                $caption = '
                    <div class="pics_manager_caption">
                        <div style="font-size:.85em;' . $captionsize . '">
                            ' . stripslashes($row["caption"]) . '
                        </div>
                    </div>';
            }

            if ($row["pageid"] !== $pageid) { //this image is from another page and must be copied rather than moved.
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
                                        ajaxapi_old(\'/features/pics/pics_ajax.php\',
                                                \'move_pic\',
                                                \'&picsid=' . $row["picsid"] . '&galleryid=\'+$(\'#movepics_' . $row["picsid"] . '\').val(),
                                                function() { do_nothing(); });
                                        ajaxapi_old(\'/features/pics/pics_ajax.php\',
                                                \'pics_pageturn\',
                                                \'&pageid=' . $pageid . '&featureid=' . $featureid . '&galleryid=' . $galleryid . '&order=' . urlencode($order) . '&pagenum=' . ($pagenum) . '\',
                                                function() { if (xmlHttp.readyState == 4) { simple_display(\'searchcontainer\'); $(\'#loading_overlay\').hide(); }}, true); } else { change_selection(\'movepics_' . $row["picsid"] . '\', \'\'); blur(); }',
                        "style" => "font-size:1em;width:100%;",
                    ],
                    "values" => get_db_result("SELECT * FROM pics_galleries WHERE pageid = '$pageid'"),
                    "valuename" => "galleryid",
                    "firstoption" => "Move to Gallery...",
                    "displayname" => "name",
                    "exclude" => $row["galleryid"],
                ];
            }

            if ($canedit) {
                $movepics = '
                    <div class="pics_manager_movepics">
                        ' . make_select($galleryselect) . '
                    </div>';
            }

            $returnme .= '
                <div id="picsid_' . $row["picsid"] . '" style="border:1px solid #96E4D7; margin:3px;">
                    <div id="activated_picsid_' . $row["picsid"] . '" class="pics_activation_status ' . $activated . '">
                        ' . $row["imagename"] . $deletepic . '
                    </div>
                    <div style="width:165px;overflow:hidden;text-align:center;">
                        <a href="javascript: void(0);" onclick="ajaxapi_old(\'/features/pics/pics_ajax.php\',\'toggle_activate\',\'&pageid=' . $pageid . '&picsid=' . $row["picsid"] . '\',function() { $(\'#activated_picsid_' . $row["picsid"] . '\').toggleClass(\'pics_active\');}); blur();">
                            <img src="' . $webpath . '"' . imgResize($mypicture[0], $mypicture[1], 165) . ' />
                        </a>
                    </div>
                    ' . $caption . '
                    ' . $siteviewable . '
                    ' . $movepics . '
                </div>';
        }

        $count = $total > (($pagenum + 1) * $perpage) ? $perpage : $total - (($pagenum) * $perpage); //get the amount returned...is it a full page of results?
        $amountshown = $firstonpage + $perpage < $total ? $firstonpage + $perpage : $total;

        if ($pagenum > 0) {
            $prev = '
                <button title="Previous Page" class="alike" onclick="pics_pageturn(' . ($pagenum - 1) . ');">
                    ' . icon("circle-chevron-left", 2) . '
                </button>';
        }

        if ($firstonpage + $perpage < $total) {
            $next = '
                <button title="Next Page" class="alike" onclick="pics_pageturn(' . ($pagenum + 1) . ');">
                    ' . icon("circle-chevron-right", 2) . '
                </button>';
        }

        return '<br /><br />
            <table style="width:100%;">
                <tr style="height:45px;">
                    <td style="width:25%;text-align:left;">
                        ' . $prev . '
                    </td>
                    <td style="width:50%;text-align:center;color:green;">
                        Viewing ' . ($firstonpage + 1) . ' through ' . $amountshown . ' out of ' . $total . '
                    </td>
                    <td style="width:25%;text-align:right;">
                        ' . $next . '
                    </td>
                </tr>
            </table>
            <div style="display: flex;flex-wrap: wrap;justify-content: space-evenly;">
                ' . $returnme . '
            </div>';
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
    $system = explode(".", $name);
    if (preg_match("/jpg|jpeg/", strtolower($system[1]))) {
        $src_img = imagecreatefromjpeg($name);
    }
    if (preg_match("/gif/", strtolower($system[1]))) {
        $src_img = imagecreatefromgif($name);
    }
    if (preg_match("/png/", strtolower($system[1]))) {
        $src_img = imagecreatefrompng($name);
    }
    if (isset($src_img)) {
        $old_x = imageSX($src_img);
        $old_y = imageSY($src_img);
        if ($old_x < $new_w && $old_y < $new_h) {
            return false;
        }
        if ($old_x > $old_y) {
            $thumb_w = $new_w;
            $thumb_h = $old_y * ($new_h / $old_x);
        }
        if ($old_x < $old_y) {
            $thumb_w = $old_x * ($new_w / $old_y);
            $thumb_h = $new_h;
        }
        if ($old_x == $old_y) {
            $thumb_w = $new_w;
            $thumb_h = $new_h;
        }
        $dst_img = ImageCreateTrueColor((int) $thumb_w, (int) $thumb_h);
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, (int) $thumb_w, (int) $thumb_h, $old_x, $old_y);
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
                            "path" => action_path("pics") . "manage_pics&pageid=$pageid&featureid=$featureid",
                            "refresh" => "true",
                            "icon" => icon("images"),
                            "class" => "slide_menu_button",
                        ]);
        }

        if (!empty($pics_abilities->addpics->allow)) {
            $returnme .= make_modal_links([
                            "title" => "Add Images",
                            "path" => action_path("pics") . "add_pics&pageid=$pageid&featureid=$featureid",
                            "iframe" => true,
                            "refresh" => "true",
                            "width" => "640",
                            "height" => "500",
                            "icon" => icon("plus"),
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
