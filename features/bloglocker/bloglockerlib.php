<?php
/***************************************************************************
* bloglockerlib.php - Blog Locker function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.9.1
***************************************************************************/

if (!LIBHEADER) {
    $sub = './';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == './' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('BLOGLOCKERLIB', true);

//BLOGLOCKERLIB Config
$CFG->bloglocker = new \stdClass;
$CFG->bloglocker->viewable_limit = 20;

function display_bloglocker($pageid, $area, $featureid) {
global $CFG, $USER, $ROLES;

    $content="";

    if (!$settings = fetch_settings("bloglocker", $featureid, $pageid)) {
        save_batch_settings(default_settings("bloglocker", $pageid, $featureid));
        $settings = fetch_settings("bloglocker", $featureid, $pageid);
    }

    $title = $settings->bloglocker->$featureid->feature_title->setting;
    $title = '<span class="box_title_text">' . $title . '</span>';

    $viewable_limit = $settings->bloglocker->$featureid->viewable_limit->setting;

    $SQL = fetch_template("dbsql/bloglocker.sql", "get_bloglocker", "bloglocker", [
        "getblogs" => false,
    ]);

    if (!get_db_count($SQL, ["pageid" => $pageid])) {
        $buttons = get_button_layout("bloglocker", $featureid, $pageid);
        return get_css_box($title,"The blog locker is empty at this time.", $buttons,NULL,"bloglocker", $featureid);
    }

    if (!user_is_able($USER->userid, "viewbloglocker", $pageid)) {
         $buttons = get_button_layout("bloglocker", $featureid, $pageid);
        return get_css_box($title, getlang("no_permission", false, ["viewbloglocker"]), $buttons,NULL,"bloglocker", $featureid);
    }
    if (user_is_able($USER->userid, "viewbloglocker", $pageid)) {
        $lockeritems = get_bloglocker($pageid);
        $i = 0;
        foreach ($lockeritems as $lockeritem) {
            if (++$i > $viewable_limit) { break; }
            $date = '
                <span style="color:gray;padding: 3px;">' .
                    date('m/d/Y', $lockeritem->dateposted) . '
                </span>';
            $p = [
                "title" => $lockeritem->title,
                "path" => action_path("bloglocker") . "view_locker&pageid=$pageid&htmlid=" . $lockeritem->htmlid,
                "width" => "98%", "height" => "95%",
            ];
            $title = make_modal_links($p);

            $action = "";
            if (!$lockeritem->blog && is_logged_in() && user_is_able($USER->userid, "addtolocker", $pageid)) {
                ajaxapi([
                    "id" => "release_bloglocker",
                    "url" => "/ajax/site_ajax.php",
                    "paramlist" => "pageid, featureid",
                    "data" => [
                        "action" => "change_locker_state",
                        "pageid" => "js||pageid||js",
                        "featuretype" => "html",
                        "featureid" => "js||featureid||js",
                        "direction" => "released",
                    ],
                    "event" => "none",
                    "ondone" => "getRoot()[0].go_to_page($pageid);",
                ]);

                $action = '
                    <button style="float: right;" class="alike" id="release_bloglocker" title="Release from the blog locker" onclick="release_bloglocker(' . $pageid . ', ' . $lockeritem->htmlid . ');">
                        ' . icon("box-open") . '
                    </button>';
            }

            $content .= "<div>$date $title $action</div>";
        }
        $buttons = get_button_layout("bloglocker", $featureid, $pageid);
        return get_css_box($title, $content, $buttons, NULL, "bloglocker", $featureid);
    }
}

function get_bloglocker($pageid) {
    $SQL = fetch_template("dbsql/bloglocker.sql", "get_bloglocker", "bloglocker", [
        "getblogs" => true,
    ]);

    if ($blogposts = get_db_result($SQL, ["pageid" => $pageid])) {
        $lockeritems = [];
        while ($post = fetch_row($blogposts)) {
            $featureid = $post["htmlid"];

            if (!$settings = fetch_settings("html", $featureid, $pageid)) {
                save_batch_settings(default_settings("html", $pageid, $featureid));
                $settings = fetch_settings("html", $featureid, $pageid);
            }

            $lockeritems[] = (object)[
                "htmlid" => $featureid,
                "blog" => $settings->html->$featureid->blog->setting,
                "title" => $settings->html->$featureid->feature_title->setting,
                "dateposted" => $post["dateposted"],
            ];
        }
        return (object) $lockeritems;
    }
    return false;
}

function bloglocker_delete($pageid, $featureid) {
    $params = [
        "pageid" => $pageid,
        "featureid" => $featureid,
        "feature" => "bloglocker",
    ];

    try {
        start_db_transaction();
        execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature"), $params);
        execute_db_sql(fetch_template("dbsql/features.sql", "delete_feature_settings"), $params);
        resort_page_features($pageid);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
}

function bloglocker_buttons($pageid, $featuretype, $featureid) {
    global $CFG, $USER;
    $returnme = "";
    return $returnme;
}

function bloglocker_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "Blog Locker",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "setting_name" => "viewable_limit",
            "defaultsetting" => "20",
            "display" => "Viewable Blog Limit",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ]
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>