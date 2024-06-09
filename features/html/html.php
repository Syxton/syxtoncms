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
            throw new Exception(error_string('no_html_found', ['htmlid' => $htmlid]));
        }
        $pageid = $row["pageid"];
        if (!user_is_able($USER->userid, 'edithtml', $pageid)) {
            throw new Exception(error_string('no_permission', ['edithtml']));
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
            $checkscript = ajaxapi([
                'url'  => '/features/html/html_ajax.php',
                'data' => [
                    'action' => 'still_editing',
                    'htmlid' => $htmlid,
                    'userid' => $userid,
                ],
                "intervalid" => "html_$htmlid",
                "interval" => 5000,
            ], 'code');

            $returnme .= js_code_wrap($checkscript);
        } else {
            $returnme .= '
                <div style="width:100%;text-align:center;">
                    <img src="' . $CFG->wwwroot . '/images/underconstruction.png" />
                </div>
                <div style="width:100%;text-align:center;">
                    This area is currently being edited by: ' . get_user_name($row['edit_user']) . '
                </div>
                ';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    ajax_return($returnme, $error);
}

function deletecomment() {
    global $CFG, $MYVARS, $USER;
    $pageid    = clean_myvar_opt('pageid', 'int', get_pageid());
    $commentid = dbescape($MYVARS->GET['commentid']);
    $comment   = get_db_row("SELECT * FROM html_comments WHERE commentid='$commentid'");

    if (!(user_is_able($USER->userid, 'deletecomments', $pageid) || ($USER->userid === $userid && user_is_able($USER->userid, 'makecomments', $pageid)))) {
        trigger_error(error_string('generic_permissions'), E_USER_WARNING);

        return;
    }

    echo '
    <table style="width:80%;margin-left: auto; margin-right: auto;">
        <tr>
            <td style="text-align:center;">
                Are you sure you want to delete this comment?
            </td>
        </tr>
        <tr>
            <td style="text-align:center;">
                <input type="button" value="Yes" onclick="ajaxapi(\'/features/html/html_ajax.php\',\'deletecomment\',\'&amp;commentid=' . $commentid . '&amp;pageid=' . $pageid . '\',function() { if (xmlHttp.readyState == 4) { close_modal(); } });" />
            </td>
        </tr>
    </table>
    ';
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
    $commentid = $MYVARS->GET['commentid'] ?? false;
    $replytoid = $MYVARS->GET['replytoid'] ?? false;

    $id = false;
    if ($replytoid) {
        $id = dbescape($replytoid);
    } elseif ($commentid) {
        $id = dbescape($commentid);
    }

    // An edit or reply.
    if ($id) {
        if ($comment = get_db_row(fetch_template('dbsql/html.sql', 'get_comment_info', 'html'), ['commentid' => $id])) {
            $params = [
                'pageid'    => $comment['pageid'],
                'comment'   => '',
                'commentid' => false,
                'replytoid' => false,
                'htmlid'    => false,
            ];

            if ($replytoid) {
                $params = array_merge($params, [
                    'title'          => 'Reply to Comment',
                    'replytocomment' => htmlentities($comment['comment']),
                    'replytoid'      => $comment['commentid'],
                ]);
                if (user_is_able($USER->userid, 'makereplies', $comment['pageid'])) {
                    echo fill_template('tmp/html.template', 'comment_form_template', 'html', $params);
                } else {
                    trigger_error(error_string('no_permission', ['makecomments']), E_USER_WARNING);
                }
            } else {
                $params = array_merge($params, [
                    'commentid' => $comment['commentid'],
                    'title'     => 'Edit Comment',
                    'comment'   => htmlentities($comment['comment']),
                ]);
                if (!can_edit_comment($comment)) {
                    trigger_error(error_string('no_permission', ['editing']), E_USER_WARNING);

                    return;
                }
                echo fill_template('tmp/html.template', 'comment_form_template', 'html', $params);
            }
        } else {
            trigger_error(error_string('no_data', ['commentid']), E_USER_WARNING);

            return;
        }
    } else { // New Comment.
        $htmlid = $MYVARS->GET['htmlid'] ?? false;
        if ($htmlid) {
            if (user_is_able($USER->userid, 'makecomments', $PAGE->id, 'html', $htmlid)) {
                $params = [
                    'pageid'    => $PAGE->id,
                    'comment'   => '',
                    'htmlid'    => $htmlid,
                    'commentid' => false,
                    'replytoid' => false,
                    'title'     => 'Make Comment',
                ];
                echo fill_template('tmp/html.template', 'comment_form_template', 'html', $params);
            } else {
                trigger_error(error_string('no_permission', ['makecomments']), E_USER_WARNING);
            }
        } else {
            trigger_error(error_string('no_data', ['htmlid']), E_USER_WARNING);
        }
    }
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
