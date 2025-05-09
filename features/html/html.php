<?php
/***************************************************************************
* html.php - html page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.7.5
***************************************************************************/

if (empty($_POST['aslib'])) {
    if (!isset($CFG)) {
        $sub = '';
        while (!file_exists($sub . 'header.php')) {
            $sub = $sub === '' ? '../' : $sub . '../';
        }
        include $sub . 'header.php';
    }

    if (!defined('HTMLLIB')) {
        include_once $CFG->dirroot . '/features/html/htmllib.php';
    }

    echo fill_template("tmp/page.template", "start_of_page_template");

    callfunction();

    echo fill_template("tmp/page.template", "end_of_page_template");
}

function html_settings() {
    global $CFG, $MYVARS, $USER;
    $featureid = clean_myvar_req('featureid', 'int');
    $pageid    = clean_myvar_req('pageid', 'int');
    $feature   = 'html';

    // Default Settings
    $default_settings = default_settings($feature, $pageid, $featureid);

    // Check if any settings exist for this feature
    if ($settings = fetch_settings($feature, $featureid, $pageid)) {
        echo make_settings_page($settings, $default_settings);
    } else { // No Settings found...setup default settings
        if (save_batch_settings($default_settings)) {
            html_settings();
        }
    }
}

function edithtml() {
    global $CFG, $USER;
    $htmlid   = clean_myvar_req('htmlid', 'int');
    $returnme = $error = '';
    try {
        $SQL = 'SELECT *
                FROM html
                WHERE htmlid = ||htmlid||';
        if (!$row = get_db_row($SQL, ['htmlid' => $htmlid])) {
            throw new Exception(getlang('no_html_found', "/features/html", ['htmlid' => $htmlid]));
        }
        $pageid = $row["pageid"];
        if (!user_is_able($USER->userid, 'edithtml', $pageid)) {
            throw new Exception(getlang("no_permission", false, ['edithtml']));
        }
        $now = get_timestamp();
        if (($now - $row['edit_time']) > 10) {
            $userid = is_logged_in() ? $USER->userid : 0;

            // Action to take on button submit.
            ajaxapi([
                'id'     => "edit_html_form_$htmlid",
                'url'    => '/features/html/html_ajax.php',
                'data'   => [
                    'action' => 'edit_html',
                    'htmlid' => $htmlid,
                    'html'   => 'js||encodeURIComponent(' . get_editor_value_javascript("edit_html_$htmlid") . ')||js',
                ],
                'event'  => 'submit',
                'ondone' => '
                    killInterval("html_' . $htmlid . '");
                    close_modal();',
            ]);

            // Create edit form.
            $params = [
                'formid' => "edit_html_form_$htmlid",
                'editor' => get_editor_box(['initialvalue' => $row['html'], 'name' => "edit_html_$htmlid"]),
            ];
            $returnme .= fill_template("tmp/html.template", "edit_form", "html", $params);

            // While editing, keep updating the edit_time field with the current time repeating every 5 seconds.
            $returnme .= ajaxapi([
                'url'  => '/features/html/html_ajax.php',
                'data' => [
                    'action' => 'still_editing',
                    'htmlid' => $htmlid,
                    'userid' => $userid,
                ],
                "intervalid" => "html_$htmlid",
                "interval" => 5000,
            ], "script");
        } else {
            $returnme .= '
                <div style="padding: 10px">
                    <div style="padding: 5px;text-align:center;">' . icon("person-digging", 10) . '</div>
                    <div style="padding: 25px;text-align:center;">
                        This area is currently being edited by: ' . get_user_name($row['edit_user']) . '
                    </div>
                </div>';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    ajax_return($returnme, $error);
}

function can_edit_comment($comment) {
    global $USER;
    if (user_is_able($USER->userid, 'editanycomment', $comment['pageid'], 'html', $comment['htmlid'])) {
        return true;
    }

    if ($USER->userid === $comment['userid']) {
        if (user_is_able($USER->userid, 'makecomments', $comment['pageid'], 'html', $comment['htmlid'])) {
            return true;
        }
    }

    return false;
}

function commentform() {
    global $CFG, $MYVARS, $USER, $PAGE;
    $commentid = clean_myvar_opt('commentid', 'int', false);
    $replytoid = clean_myvar_opt('replytoid', 'int', false);
    $htmlid = clean_myvar_opt('htmlid', 'int', false);

    $id = false;
    if ($replytoid) {
        $id = dbescape($replytoid);
    } elseif ($commentid) {
        $id = dbescape($commentid);
    }

    $return = "";
    // An edit or reply.
    if ($id) {
        if ($comment = get_db_row(fetch_template('dbsql/html.sql', 'get_comment_info', 'html'), ['commentid' => $id])) {
            $pageid = $comment['pageid'];
            $params = [
                'pageid'    => $pageid,
                'comment'   => '',
                'commentid' => false,
                'replytoid' => false,
                'htmlid'    => $htmlid,
            ];

            if ($replytoid) {
                $params = array_merge($params, [
                    'title'          => 'Reply to Comment',
                    'replytocomment' => htmlentities($comment['comment']),
                    'replytoid'      => $comment['commentid'],
                ]);
                if (user_is_able($USER->userid, 'makereplies', $pageid)) {
                    $return = fill_template('tmp/html.template', 'comment_form_template', 'html', $params);
                } else {
                    trigger_error(getlang("no_permission", false, ['makecomments']), E_USER_WARNING);
                }
            } else {
                $params = array_merge($params, [
                    'commentid' => $comment['commentid'],
                    'title'     => 'Edit Comment',
                    'comment'   => htmlentities($comment['comment']),
                ]);
                if (!can_edit_comment($comment)) {
                    trigger_error(getlang("no_permission", false, ['editing']), E_USER_WARNING);
                    return;
                }
                $return = fill_template('tmp/html.template', 'comment_form_template', 'html', $params);
            }
        } else {
            trigger_error(getlang("no_data", false, ['commentid']), E_USER_WARNING);
            return;
        }
    } else { // New Comment.
        $pageid = $PAGE->id;
        if ($htmlid) {
            if (user_is_able($USER->userid, 'makecomments', $pageid, 'html', $htmlid)) {
                $params = [
                    'pageid'    => $pageid,
                    'comment'   => '',
                    'htmlid'    => $htmlid,
                    'commentid' => false,
                    'replytoid' => false,
                    'title'     => 'Make Comment',
                ];
                $return = fill_template('tmp/html.template', 'comment_form_template', 'html', $params);
            } else {
                trigger_error(getlang("no_permission", false, ['makecomments']), E_USER_WARNING);
            }
        } else {
            trigger_error(getlang("no_data", false, ['htmlid']), E_USER_WARNING);
        }
    }

    ajaxapi([
        "id" => "comment_form_$htmlid",
        "if" => "$('#comment').val().length > 0",
        "url" => "/features/html/html_ajax.php",
        "data" => [
            "action" => "comment",
            "htmlid" => $htmlid,
            "comment" => "js||encodeURIComponent($('#comment').val())||js",
            "commentid" => $commentid,
            "replytoid" => $replytoid,
            "pageid" => $pageid,
        ],
        "event" => "submit",
        "display" => "comment_area_$htmlid",
        "ondone" => "close_modal(); if (data.message.length > 0) { $('#html_comment_button_box_$htmlid').show(); } else { $('#html_comment_button_box_$htmlid').hide(); }",
    ]);

    echo $return;
}

function viewhtml() {
    global $CFG, $MYVARS, $USER, $ROLES;
    $key      = $MYVARS->GET['key'];
    $htmlid   = clean_myvar_req('htmlid', 'int');
    $pageid   = clean_myvar_req('pageid', 'int');
    $pagename = get_db_field('name', 'pages', "pageid = '$pageid'");

    if (!is_logged_in() && isset($key)) {
        key_login($key);
    }

    $settings = fetch_settings('html', $htmlid, $pageid);
    $allowed  = false;

    if (is_logged_in()) {
        if (user_is_able($USER->userid, 'viewhtml', $pageid, 'html', $htmlid)) {
            $abilities = user_abilities($USER->userid, $pageid, false, 'html', $htmlid);
            $allowed   = true;
        } else {
            echo '<center>You do not have proper permissions to view this item.</center>';
        }
    } else {
        if (get_db_field('siteviewable', 'pages', "pageid=$pageid") && role_is_able($ROLES->visitor, 'viewhtml', $pageid)) {
            $abilities = user_abilities($USER->userid, $pageid, 'html', $htmlid);
            $allowed   = true;
        } else {
            echo '<div id="standalone_div"><input type="hidden" id="reroute" value="/features/html/html.php:viewhtml:&amp;pageid=' . $pageid . '&amp;htmlid=' . $htmlid . ':standalone_div" />';
            echo '<div style="width:100%; text-align:center;">You must login to see this content.<br /><center>' . get_login_form(true, false) . '</center></div></div>';
        }
    }

    if ($allowed) {
        echo get_css_set('main'); // Load CSS.

        echo '
            <a class="buttonlike" style="margin: 10px" href="' . $CFG->wwwroot . '/index.php?pageid=' . $pageid . '">Navigate to ' . $pagename . '</a>
            <div class="html_main">
            ' . get_html_feature($pageid, $htmlid, $settings, $abilities, false, true) . '
            </div>';
    }
}
