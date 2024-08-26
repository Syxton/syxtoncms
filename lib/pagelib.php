<?php
/***************************************************************************
 * pagelib.php - Page function library
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 5/28/2021
 * Revision: 3.1.7
 ***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('PAGELIB', true);
define('DEFAULT_PAGEROLE', 4);
$LOADAJAX = [];

// Set PAGE global.
$PAGE = set_pageid();

function callfunction($action = false) {
global $CFG, $MYVARS;
    if (empty($_POST["aslib"])) {
        collect_vars(); // Place all passed variables in MYVARS global.

        if (!empty($MYVARS->GET["i"])) { // Universal javascript and CSS.
            $params = ["directory" => get_directory()];
            echo fill_template("tmp/pagelib.template", "main_js_css", false, $params);
        }
        if (!empty($MYVARS->GET["v"])) { // Validation javascript and CSS.
            echo get_js_tags(["jquery"]);
            echo get_js_tags(["validate"]);
            unset($MYVARS->GET["v"]);
        }
        $action = $MYVARS->GET["action"] ?? ($action ? $action : "[action not provided]");
        if (function_exists($action)) {
            $action();
        } else {
            trigger_error(error_string("no_function", [$action]), E_USER_WARNING);
        }
    }
}

function icon(...$icons) {
    $return = '';

    if (!ismultiarray($icons)) { // Simple icon.
        // rotate is rotate-90, rotate-180, rotate-270 classes.
        $icon = (isset($icons["icon"]) ? $icons["icon"] : (isset($icons[0]) ? $icons[0] : $icons));
        $icons = [
            [
                "icon" => $icon,
                "size" => isset($icons["size"]) ? $icons["size"] : (isset($icons[1]) ? $icons[1] : 1),
                "class" => isset($icons["class"]) ? $icons["class"] : (isset($icons[2]) ? $icons[2] : ""),
                "color" => isset($icons["color"]) ? $icons["color"] : (isset($icons[3]) ? $icons[3] : icon_color($icon)),
                "transform" => isset($icons["transform"]) ? $icons["transform"] : (isset($icons[4]) ? $icons[4] : ""),
            ],
        ];
    } else {
        $icons = $icons[0];
    }

    foreach ($icons as $layer) {
        if (isset($layer["stacksize"])) {
            $stacksize = $layer["stacksize"];
        }
        if (isset($layer["stackclass"])) {
            $stackclass = $layer["stackclass"];
        }
        $content = $layer["content"] ?? "";
        $icon = $layer["icon"] ?? "";
        $styles = $layer["style"] ?? "";
        $transform = $layer["transform"] ?? "";
        $layersize = $layer["size"] ?? "";
        $layersize = empty($layersize) ? "" : " fa-" . $layersize . 'x';

        $layerclass = $layer["class"] ?? "";
        $color = isset($layer["color"]) && !empty($layer["color"]) ? $layer["color"] : icon_color($icon);
        $color = empty($color) ? "" : "color: " . $color . ";";

        if (!empty($icon)) {
            $return .= '<i style="' . $color . $styles . '" data-fa-transform="' . $transform . '" class="' . $layerclass . $layersize . ' fa-solid fa-' . $icon . '"></i>';
        } else {
            $return .= '<span style="' . $styles . '" data-fa-transform="' . $transform . '" class="fa-layers-text ' . $layerclass . '">' . $content . '</span>';
        }
    }

    if (count($icons) > 1) {
        $stacksize = $stacksize ?? "";
        $stacksize = empty($stacksize) ? "" : "fa-" . $stacksize . "x";
        $stackclass = $stackclass ?? "";
        $return = '<span class="fa-layers fa-fw ' . $stacksize . ' ' . $stackclass . '">' . $return . '</span>';
    }

    return $return;
}

function icon_color($icon) {
    // Certain icons have different color than others.
    switch ($icon) {
        case "key":
            $color = "#d2df22";
            break;
        case "sliders":
            $color = "#2e4c5abd";
            break;
        case "trash":
            $color = "#bb0202";
            break;
        case "pencil":
            $color = "#519d58";
            break;
        case "square-rss":
            $color = "orange";
            break;
        case "circle-exclamation":
            $color = "red";
            break;
        case "thumbs-up":
            $color = "green";
            break;
        case "thumbs-down":
            $color = "red";
            break;
        default:
            return "";
    }
    return $color;
}

function collect_vars() {
global $CFG, $MYVARS;
    //Retrieve from Javascript
    $postorget = isset($_POST["action"]) ? $_POST : false;

    $MYVARS ??= new stdClass();
    $MYVARS->GET = !$postorget && isset($_GET["action"]) ? $_GET : $postorget;
    $MYVARS->GET = !$MYVARS->GET ? $_GET : $MYVARS->GET;
}

function get_directory() {
global $CFG;
    return (empty($CFG->directory) ? '' : trim($CFG->directory, '/\\'));
}

function main_body($header_only = false) {
    $params = [
        "page_masthead_1" => page_masthead(true),
        "page_masthead_2" => page_masthead(false, $header_only),
    ];
    return fill_template("tmp/pagelib.template", "main_body_template", false, $params);
}

function set_pageid($pageid = NULL) {
global $PAGE;
    if (empty($PAGE)) {
        $PAGE = new \stdClass;
    }

    if (!isset($pageid)) {
        $pageid = get_pageid();
    }

    $pageid = clean_var_opt($pageid, "int", 0);
    if (isset($pageid) && !get_db_row(fetch_template("dbsql/pages.sql", "get_page"), ["pageid" => $pageid])) {
        return false; // Page cannot be set.
    }

    $PAGE->id = $pageid;
    $_SESSION["pageid"] = $pageid;
    $_COOKIE["pageid"] = $pageid;
    return $PAGE;
}

function get_pageid() {
global $PAGE, $CFG, $MYVARS;

    if (clean_myvar_opt("pageid", "int", false)) {
        return clean_myvar_opt("pageid", "int", false);
    }

    if (!empty($_GET["pageid"]) && is_numeric($_GET["pageid"])) {
        return $_GET["pageid"];
    }

    if (!empty($_COOKIE["pageid"]) && is_numeric($_COOKIE["pageid"])) {
        return $_COOKIE["pageid"];
    }

    if (!empty($_SESSION["pageid"]) && is_numeric($_SESSION["pageid"])) {
        return $_SESSION["pageid"];
    }

    if (!empty($PAGE->id) && is_numeric($PAGE->id)) {
        return $PAGE->id;
    }

    return $CFG->SITEID;
}

function get_feature_area($feature, $featureid) {
    $SQL = "SELECT area
            FROM pages_features
            WHERE feature = ||feature||
            AND featureid = ||featureid||";
    $row = get_db_row($SQL, ["feature" => $feature, "featureid" => $featureid]);

    return $row["area"];
}

function get_default_role($pageid) {
    return get_db_field("default_role", "pages", "pageid = ||pageid||", ["pageid" => $pageid]);
}

function page_masthead($left = true, $header_only = false) {
global $CFG, $USER, $PAGE;
    if ($left) {
        $PAGE = set_pageid();
        $pageid = $PAGE->id;
        $PAGE->themeid ??= get_page_themeid($pageid);

        if (!$currentpage = get_db_row("SELECT * FROM pages WHERE pageid = ||pageid||", ["pageid" => $pageid])) {
            header('Location: ' . $CFG->wwwroot);
            die();
        }
        $styles = get_styles($pageid, $PAGE->themeid);
        $header_color = $styles['pagenamebgcolor'] ?? "";
        $header_text = $styles['pagenamefontcolor'] ?? "";

        $params = [
            "wwwroot" => $CFG->wwwroot,
            "haslogo" => isset($CFG->logofile),
            "logofile" => $CFG->logofile,
            "hasmobilelogo" => !empty($CFG->mobilelogofile),
            "mobilelogofile" => $CFG->mobilelogofile,
            "sitename" => $CFG->sitename,
            "header_only" => ($header_only ? "" : get_nav_items($pageid)),
            "quote" => random_quote(),
            "pagename" => $currentpage["name"],
            "header_text" => $header_text,
            "header_color" => $header_color,
        ];
        return fill_template("tmp/pagelib.template", "page_masthead_template", false, $params);
    }

    return (!$header_only ? (is_logged_in() ? print_logout_button($USER->fname, $USER->lname, $PAGE->id) : get_login_form()) : '');
}

/**
 * Sends an AJAX request to a specified URL.
 *
 * @param array $params The parameters for the AJAX request.
 *                     - url (string): The URL to send the request to.
 *                     - external (bool): Whether the URL is external or not.
 *                     - id (string): The ID of the element to update.
 *                     - reqstring (string): The request string.
 *                     - data (array): The data to send with the request.
 *                     - if (string): The condition to check before sending the request.
 *                     - else (string): The code to execute if the condition is false.
 *                     - display (string): The code to display the response.
 *                     - classes (string): The classes to add to the AJAX element.
 *                     - datatype (string): The data type of the response.
 *                     - contenttype (string): The content type of the request.
 *                     - callback (string): The code to execute after the request is successful.
 * @param bool $forcereturn Whether to force a return statement or not.
 * @return void
 */
function ajaxapi($params, $forcereturn = false) {
global $CFG, $LOADAJAX;
    // Get the value of the ajaxapi parameter and clean it.
    $ajaxapi = clean_myvar_opt("ajaxapi", "bool", false);

    // Check if the URL is external or not.
    $external = $params["external"] ?? false;

    // Build the URL based on whether it is external or not.
    $url = $external ? clean_var_req($params["url"], "string") : $CFG->wwwroot . clean_var_req($params["url"], "string");

    try {
        // Get the parameters for the AJAX request.
        $id = $params["id"] ?? false; // The ID of the element to update. Also used as the name of function if event is "none".
        $reqstring = $params["reqstring"] ?? ""; // The request string. Used to gather all data in a container. (ie formname)
        $data = $params["data"] ?? []; // The data to send with the request.
        $display = $params["display"] ?? false; // The code to display the response.
        $classes = $params["classes"] ?? "ajaxapi inactive"; // The classes to add to the AJAX element.
        $datatype = $params["datatype"] ?? "json"; // The data type of the response.
        $contenttype = $params["contenttype"] ?? "application/x-www-form-urlencoded; charset=UTF-8"; // The content type of the request.
        $callback = $params["callback"] ?? ""; // The code to execute after the request is successful.
        $async = $params["async"] ?? "true"; // The code to execute after the request is successful.


        $method = $params["method"] ?? "POST";
        $event = $params["event"] ?? "click";
        $paramlist = $params["paramlist"] ?? "";

        // Add the show/hide loading overlay functions.
        $loading = $params["loading"] ?? "";

        // Add return false only if it is inside a function or event.
        $falsereturn = $forcereturn && $forcereturn !== "function" ? "" : ($async !== "true" ? "return return_data;" : "return false;");

        // Should function be added to the myIntervals[] array?
        $intervalid = $params["intervalid"] ?? false;
        $interval = $params["interval"] ?? 60000;

        $ondone = $params["ondone"] ?? ""; // Function run on success.
        $onerror = $params["onerror"] ?? ""; // Function run on server error.
        $always = $params["always"] ?? ""; // Function run after success or error.
        $before = $params["before"] ?? ""; // Function run before ajax call.

        if ($display) {
            if (strpos($display, "js||") === false) { // no embedded js code.
                $display = "'$display'"; // container id is a string and should be in quotes.
            } else {
                // Define the pattern to match the qualifiers.
                $pattern = '/((?<intro>.*)js\|\|(?<var>.*)\|\|js(?<outro>.*))/i';

                // Find all the qualifiers in the content.
                preg_match_all($pattern, $display, $matches);

                // Check if qualifiers were found.
                if (!empty($matches[0])) {
                    $temp = $matches['var'][0]; // ex. id
                    $temp = !empty($matches['intro'][0]) ? "'" . $matches['intro'][0] . "' + $temp" : $temp; // ex. 'unique_' + id
                    $temp = !empty($matches['outro'][0]) ? "$temp + '" . $matches['outro'][0] . "'" : $temp; // ex. 'unique_' + id + '_identifier'
                }
                $display = $temp;
            }

            if (empty($ondone)) {
                $ondone = "jq_display($display, data);"; // ondone becomes simple display.
            } else {
                $ondone = "$.when(jq_display($display, data)).then(function() { $ondone });";  // Prepend display and perform ondone afterwards.
            }
        }

        $onerror = empty($onerror) ? "console.log(data);" : $onerror;

        $showloading = $hideloading = "";
        if ($loading) {
            $showloading = "$('#$loading').show();";
            $hideloading = "$('#$loading').hide();";
        }

        $always = ".always(function(data) { $always return_data = data;$(this).blur(); })";

        // if and else setup.
        $if = $params["if"] ?? false; // The condition to check before sending the request.
        $else = $params["else"] ?? false; // The code to execute if the condition is false.
        $if = $if ? "if ($if) {" : "";
        $else = $else ? "else { $else }" : "";
        $ifclose = $if ? "} $else" : "";

        // datestamp to make each call unique.
        $data["timestamp"] = "js|| Date.now() ||js";
        $data["ajaxapi"] = 1;

        // prepare data json.
        $data = json_encode($data);
        $data = str_replace(['"js||', '||js"', '\'js||', '||js\''], '', $data);

        // if entire form data is needed, merge it with the original request data.
        if (!empty($reqstring)) {
            $reqstring = '
                let orgjson = ' . $data . ';
                var reqdata = mergeJSON(create_request_json("' . $reqstring . '"), JSON.stringify(orgjson));
            ';
            $data = "{}";
            if ($method === 'POST') {
                $data = 'JSON.parse(reqdata)';
            }
        }

        if (empty($reqstring) && $method !== 'POST') {
            $data = '{}';
        }

        $script = "
            var return_data = {};
            ajax: {
                if (typeof event !== 'undefined') {
                    event.preventDefault();
                }
                if (getGlobals().exitEvent) {
                    break ajax;
                }
                $reqstring
                $if
                $showloading
                $before
                $.ajax({
                    url: `$url`,
                    type: `$method`,
                    async: $async,
                    $callback
                    contentType: '$contenttype',
                    data: $data,
                    dataType: '$datatype',
                    cache: false,
                }).fail(function(data) {
                    ajaxerror(data);
                    $onerror
                    $hideloading
                }).done(function(data) {
                    ajaxerror(data);
                    $ondone
                    $hideloading
                    loadajaxjs(data);
                })$always;
                $ifclose
            }";

        if ($intervalid) {
            $script = preg_replace('/\s+/S', " ", $script);
            $script = $script . '
                killInterval(`' . $intervalid . '`);
                makeInterval(`' . $intervalid . '`, `' . str_replace("`", "\`", $script) . '`, ' . $interval . ');';
        }

        // Add return false; at the end if needed.
        $script .= $falsereturn;

        if (!$forcereturn) {
            if (!$id) {
                throw new Exception("ID must be specified");
            }
            // Attach to event.
            if ($event !== "none") {
                $script = "
                \$('[id=$id]').unbind('$event').on('$event', function(e) {
                    e.preventDefault();
                    $script
                });";
            } else {
                $script = "function $id($paramlist) { $script }";
            }

            $script = preg_replace('/\s+/S', " ", $script) . "//# sourceURL=$id.js";
            if ($ajaxapi) {
                $LOADAJAX["$id"] = $script;
            } else {
                // Don't defer so that if the script is labeled inactive, but it is loaded, it can be removed.
                echo js_code_wrap($script, false, false, $id, $classes);
            }
        } else {
            if (!$id) {
                $id = uniqid();
            }
            switch ($forcereturn) {
                case "script":
                    break;
                case "event":
                    $event = $event == "none" ? "click" : $event;
                    $script = "
                        \$('#$id').unbind('$event').on('$event', function(e) {
                            e.preventDefault();
                            $script
                        });";
                    break;
                case "function":
                    $script = "function $id($paramlist) { $script }";
                    break;
            }

            $script = preg_replace('/\s+/S', " ", $script) . " //# sourceURL=$id.js";
            if ($forcereturn !== "code") {
                $script = js_code_wrap($script, false, false, $id, $classes);
            }
        }
        return $script;
    } catch (\Throwable $e) {
        throw new Exception($e->getMessage());
    }
    return false;
}

function requireToVar($file){
    ob_start();
    require($file);
    return ob_get_clean();
}

function ajax_return($response = "", $error = "") {
global $LOADAJAX;
    $ajaxapi = clean_myvar_opt("ajaxapi", "bool", false);

    if ($ajaxapi) {
        header('Content-Type: application/json');
        $response = ["message" => $response, "loadajax" => $LOADAJAX, "ajaxerror" => $error];
        echo json_encode($response);
        exit();
    } else {
        echo $response;
        echo $error;
        foreach ($LOADAJAX as $script) {
            echo js_code_wrap($script);
        }
    }
}

function get_editor_javascript() {
global $CFG;
    return js_script_wrap($CFG->wwwroot . '/scripts/tinymce/jquery.tinymce.min.js');
}

function get_editor_value_javascript($editorname = "editor1") {
    return '$(\'#' . $editorname . '\').val()';
}

function get_editor_box($params = []) {
global $CFG;
    $params["initialvalue"] ??= "";
    $params["name"] ??= "editor1";
    $params["vars"]["charlimit"] = $params["charlimit"] ?? 0;
    $params["vars"]["height"] = $params["height"] ?? "calc(100vh - 70px)";
    $params["vars"]["width"] = $params["width"] ?? "100%";
    $params["vars"]["type"] = $params["type"] ?? "HTML";
    $params["vars"]["plugins"] = get_editor_plugins($params["vars"]["type"]);
    $params["vars"]["toolbar"] = get_editor_toolbar($params["vars"]["type"]);
    $params["vars"]["wwwroot"] = $CFG->wwwroot;
    $params["vars"]["directory"] = get_directory();
    return get_editor_javascript() . fill_template("tmp/pagelib.template", "editor_box_template", false, $params);
}

function get_editor_plugins($type) {
    switch ($type) {
        case "Default":
            $set = '"autolink autoresize image lists link responsivefilemanager charmap preview hr anchor pagebreak",
                    "searchreplace wordcount visualblocks visualchars code fullscreen",
                    "insertdatetime media nonbreaking paste table directionality"';
            break;
        case "News":
            $set = '"autolink autoresize image lists link responsivefilemanager charmap preview hr anchor pagebreak",
                    "searchreplace wordcount visualblocks visualchars code fullscreen",
                    "insertdatetime media nonbreaking paste table directionality"';
            break;
        case "HTML":
            $set = '"autolink autoresize image lists link responsivefilemanager charmap preview hr anchor pagebreak",
                    "searchreplace wordcount visualblocks visualchars code fullscreen",
                    "insertdatetime media nonbreaking paste table directionality"';
            break;
        case "Basic":
            $set = '"autolink autoresize lists charmap preview hr anchor pagebreak",
                    "searchreplace wordcount visualblocks visualchars code fullscreen",
                    "insertdatetime nonbreaking paste table directionality"';
            break;
        case "Forum":
            $set = '"autolink autoresize image lists link responsivefilemanager charmap preview hr anchor pagebreak",
                    "searchreplace wordcount visualblocks visualchars code fullscreen",
                    "insertdatetime media nonbreaking paste table directionality"';
            break;
        case "Shoutbox":
            $set = '"autolink autoresize charmap wordcount visualblocks visualchars code"';
            break;
    }
    return $set;
}

function get_editor_toolbar($type) {
    switch ($type) {
        case "Default":
            $set = "insertfile undo redo | formatselect | bold italic strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image";
            break;
        case "News":
            $set = "insertfile undo redo | formatselect | bold italic strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image";
            break;
        case "HTML":
            $set = "insertfile undo redo | formatselect | bold italic strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image";
            break;
        case "Basic":
            $set = "undo redo bold italic | alignleft aligncenter alignright alignjustify link image";
            break;
        case "Forum":
            $set = "insertfile undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image";
            break;
        case "Shoutbox":
            $set = "undo redo bold italic";
            break;
    }
    return $set;
}

function page_default_styles() {
    $styles_array[] = [
        "Page Name Border",
        "pagenamebordercolor",
        "#000000",
    ];
    $styles_array[] = [
        "Page Name Background",
        "pagenamebgcolor",
        "#FFFFFF",
    ];
    $styles_array[] = [
        "Page Name Text",
        "pagenamefontcolor",
        "#000000",
    ];
    $styles_array[] = [
        "Title Background",
        "titlebgcolor",
        "#FFFFFF",
    ];
    $styles_array[] = [
        "Title Text",
        "titlefontcolor",
        "#000000",
    ];
    $styles_array[] = [
        "Border",
        "bordercolor",
        "#000000",
    ];
    $styles_array[] = [
        "Content Background",
        "contentbgcolor",
        "#FFFFFF",
    ];
    return $styles_array;
}

function upgrade_check() {
    global $CFG;
    //MAKE SURE INITIALIZED
    if (!get_db_row("SELECT * FROM pages WHERE pageid = 1")) {
        //INITIALIZE
        execute_db_sql("INSERT INTO pages (pageid,name,short_name,description,default_role,menu_page,opendoorpolicy,siteviewable,keywords) VALUES(1,'Home','home','home','4','0','1','1','home')");
        execute_db_sql("INSERT INTO users (userid,fname,lname,email,password,first_activity,last_activity,ip,temp,alternate,userkey,joined) VALUES(1,'Admin','User','admin@admin.com','" . md5("admin") . "','0','0', '', '', '','" . (md5("admin@admin.com") . md5(time())) . "','" . get_timestamp() . "')");
        execute_db_sql("INSERT INTO roles_assignment (assignmentid,userid,roleid,pageid,confirm) VALUES(1,1,1,1,0)");
    }

    //MAKING SURE LATEST SITE UPDATES ARE INSTALLED
    $featureid = false;
    if ($settings = fetch_settings("site", $featureid)) {
        $site_version = $settings->site->version->setting;
        include_once($CFG->dirroot . "/lib/db.php");
        site_upgrade();
    }

    //install new features if they don't exist'
    run_on_each_feature(NULL, "db", NULL, "_install");

    //run updates
    run_on_each_feature(NULL, "db", NULL, "_upgrade");
}

function run_on_each_feature($filename_ext = "", $filename = "", $function_pre = "", $function_ext = "", $var1 = "notgiven", $var2 = "notgiven", $var3 = "notgiven", $var4 = "notgiven") {
global $CFG;
    //Check for new features and feature updates
    $directory = $CFG->dirroot . "/features";
    if ($handle = opendir($directory)) {
        /* This is the correct way to loop over the directory. */
        while (false !== ($dir = readdir($handle))) {
            if (!strstr($dir, ".") && is_dir($directory . "/" . $dir)) {
                if (!$filename) {
                    include_once($directory . "/" . $dir . '/' . $dir . "$filename_ext.php");
                } else {
                    include_once($directory . "/" . $dir . '/' . "$filename.php");
                }
                $action = $function_pre . $dir . $function_ext;
                if (function_exists("$action")) {
                    if ($var1 == "notgiven") {
                        $action();
                    } elseif ($var2 == "notgiven") {
                        $action($var1);
                    } elseif ($var3 == "notgiven") {
                        $action($var1, $var2);
                    } elseif ($var4 == "notgiven") {
                        $action($var1, $var2, $var3);
                    } else {
                        $action($var1, $var2, $var3, $var4);
                    }
                }
            }
        }
        //close the directory handler
        closedir($handle);
    }
}

function resort_page_features($pageid) {
    // Middle first.
    $i = 1;
    if ($result = get_db_result("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='middle' ORDER BY sort")) {
        while ($row = fetch_row($result)) {
            execute_db_sql("UPDATE pages_features SET sort='$i' WHERE id='" . $row['id'] . "'");
            $i++;
        }
    }
    // Side second.
    $i = 1;
    if ($result = get_db_result("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='side' ORDER BY sort")) {
        while ($row = fetch_row($result)) {
            execute_db_sql("UPDATE pages_features SET sort='$i' WHERE id='" . $row['id'] . "'");
            $i++;
        }
    }
}

function make_modal_links($v) {
global $CFG;
    $v["button"]      = empty($v["button"]) ? "link" : "button";
    $v["title"]       ??= "";
    $v["class"]       ??= "";
    $v["confirmexit"] ??= "";
    $v["onclick"]     ??= "";
    $v["oncomplete"]  ??= "";
    $v["imagestyles"] ??= "";
    $v["id"]          ??= "";
    $v["type"]        = empty($v["type"]) ? "" : 'type="' . $v["type"] . '"';

    $v["image"]       = empty($v["image"]) ? "" : '<img alt="' . $v["title"] . '" title="' . $v["title"] . '" src="' . $v["image"] . '" style="vertical-align: middle;' . $v["imagestyles"] . '" />';
    $v["image"]       = empty($v["icon"]) ? $v["image"] : $v["icon"];

    $gallery_name     = $v["gallery"] ?? "";
    $gallery          = empty($v["gallery"]) ? "" : "*[data-rel=\'$gallery_name\']";
    $v["gallery"]     = empty($v["gallery"]) ? "" : ",rel:'$gallery_name',photo:'true',preloading:'true'";

    $v["width"]       = empty($v["width"]) ? (empty($v["gallery"]) ? "" : "") : (is_numeric($v["width"]) || is_javascript($v["width"]) ? ",width: " . $v["width"] . "" : ",width: '" . $v["width"] . "'");
    $v["height"]      = empty($v["height"]) ? (empty($v["gallery"]) ? "" : "") : (is_numeric($v["height"]) || is_javascript($v["height"]) ? ",height: " . $v["height"] . "" : ",height: '" . $v["height"] . "'");
    $v["path"]        ??= "";

    $path             = $v["path"] && $v["gallery"] ? $v["path"] : "javascript: void(0);";
    $v["text"]        = empty($v["text"]) ? (empty($v["image"]) ? (empty($v["title"]) ? "" : $v["title"]) : $v["image"]) : (empty($v["image"]) ? $v["text"] :  $v["image"] . ' <span style="vertical-align: middle;">' . $v["text"] . "</span>");
    $v["styles"] ??= false;

    $iframe      = empty($v["iframe"]) ? "" : ",fastIframe:false,iframe:true";
    $i           = empty($v["iframe"]) ? "" : "&i=!";

    $v["refresh"] ??= false;
    $v["reuse"] ??= false;
    $v["onExit"] ??= false;

    $onComplete = $v["oncomplete"];
    $modal = $onOpen = $valid = '';
    $unq = uniqid();
    $modal = 'var cb = getColorbox(\'' . $gallery . '\');';
    if (!empty($v["validate"]) && empty($v["iframe"])) { // load validation javascript
        $onOpen .= "loadjs('" . get_js_tags(["validate"], true) . "');";
    } elseif (!empty($v["validate"]) && !empty($v["iframe"])) {
        $valid = "&v=!";
    }

    if (!empty($iframe)) {
        get_js_tags(["scripts/frame_resize.js"]);
        $onComplete .= "resize_modal();";
    }

    if (empty($v["refresh"]) && empty($v["reuse"])) {
        $unq = "cachedClose_$unq";
        $modal .= "getGlobals().$unq = cb.close;
                   cb.close = (function() {
                   let fn = getGlobals().$unq;";
        $modal .= !empty($v["confirmexit"]) ? "if (confirm('Are you sure you wish to close this window?')) {" : "";
        $modal .= !empty($v["onExit"]) ? $v["onExit"] : "";
        $modal .= "fn.apply(fn);
                   cb.close = getGlobals().$unq;
                   delete getGlobals().$unq;";
        $modal .= !empty($v["confirmexit"]) ? "}" : "";
        $modal .= "});";
    }

    if (!empty($v["refresh"])) {
        $modal .= "cb.close = (function() {";
        $modal .= !empty($v["confirmexit"]) ? "if (confirm('Are you sure you wish to close this window?')) {" : "";
        $modal .= !empty($v["onExit"]) ? $v["onExit"] : "";
        $modal .= "setTimeout(function() { getRoot()[0].location.reload(true); }, 1000);";
        $modal .= !empty($v["confirmexit"]) ? "}" : "";
        $modal .= "});";
    }

    if (empty($iframe) && (empty($v["height"]) || empty($v["width"]))) {
        $onComplete .= "resize_modal();";
    }

    if ($v["gallery"]) {
        $modal .= 'cb.apply($(\'' . $gallery . '\', $(top)[0].document), [{maxWidth: \'95%\', maxHeight: \'95%\', fixed: true' . $v["width"] . $v["height"] . $v["gallery"] . ', speed:0}]);';
    } elseif ($v["onclick"]) {
        $modal .= $v["onclick"];
    } else {
        $modal .= 'cb.apply(cb, [{maxWidth: \'98%\', maxHeight: \'98%\', fixed: true, onComplete: function() { ' . $onComplete . ' $(\'#cboxTitle\').attr({\'style\': \'display: none\'}); },href:\'' . $v["path"] . $i . $valid . '&t=' . get_timestamp() . '\'' . $v["width"] . $v["height"] . $v["gallery"] . ',speed:0' . $iframe . '}]);';
    }

    if (!empty($onOpen)) {
        $modal = "setTimeout(function() { $modal }, 500);";
    }

    $modal = preg_replace('/\s+/S', " ", $modal);

    // make attributes that are the same in both a and button types.
    $standardattrs = 'id="' . $v["id"] . '" type="' . $v["type"] . '" title="' . $v["title"] . '" style="' . $v["styles"] . '" onclick="' . $onOpen . ' ' . $modal . ' //# sourceURL='.$unq.'.js"';
    if ($v["button"] == "button") {
        return '<button ' . $standardattrs . ' class="smallbutton ' . $v["class"] . '" />' . $v["text"] . '</button>';
    } else {
        return '<a ' . $standardattrs . ' class="' . $v["class"] . '" data-rel="' . $gallery_name . '"   href="' . $path . '">' . $v["text"] . '</a>';
    }
}

function action_path($filename, $feature = true, $ajaxfolder = false) {
global $CFG;
    if ($ajaxfolder) {
        return $CFG->wwwroot . "/ajax/$filename.php?action=";
    } else {
        if ($feature) {
            $feature = "/features/" . str_replace("_ajax", "", $filename);
        } else {
            $feature = "/pages";
        }
        return $CFG->wwwroot . "$feature/$filename.php?action=";
    }
}

function get_user_links($userid, $pageid) {
global $CFG;
    $returnme = '<input type="hidden" id="loggedin" />';
    $alerts = get_user_alerts($userid);
    if ($alerts) {
        $alerts_text = $alerts == 1 ? "Alert" : "Alerts";
        $returnme .= '
            <span class="profile_links">
            ' . make_modal_links([
                    "title" => "Alerts",
                    "id" => "alerts_link",
                    "text" => '<span id="alerts_span">' . "$alerts $alerts_text" . '</span>',
                    "path" => $CFG->wwwroot . "/pages/user.php?action=user_alerts&userid=$userid",
                    "width" => "600",
                    "height" => "500",
                    "icon" => icon("circle-exclamation", 1, "", "white"),
            ]) . '
            </span>';
    }
    $returnme .= '<input type="hidden" id="alerts" value="' . $alerts . '" />';
    return $returnme;
}

function is_opendoor_page($pageid) {
    if (get_db_count("SELECT * FROM pages WHERE pageid='$pageid' AND opendoorpolicy=1")) {
        return true;
    }
    return false;
}

function is_visitor_allowed_page($pageid) {
    if (get_db_count("SELECT * FROM pages WHERE pageid='$pageid' AND siteviewable=1")) {
        return true;
    }
    return false;
}

function all_features_function($SQL = false, $feature = false, $pre = "", $post = "", $count = false, $var1 = "#false#", $var2 = "#false#", $var3 = "#false#", $var4 = "#false#", $type_in_name = true) {
global $CFG;
    $returnme = $count ? 0 : "";
    $t1 = $t2 = $t3 = $t4 = false;

    if ($SQL !== false) {
        if ($features = get_db_result($SQL)) {
            while ($row = fetch_row($features)) {
                //prepare variables
                if ($var1 !== "#false#") {
                    $t1 = strstr($var1, '#->') ? str_replace("#->", "", $var1) : $t1;
                    if ($t1) {
                        $var1 = $row[$t1];
                    }
                }
                if ($var2 !== "#false#") {
                    $t2 = strstr($var2, '#->') ? str_replace("#->", "", $var2) : $t2;
                    if ($t2) {
                        $var2 = $row[$t2];
                    }
                }
                if ($var3 !== "#false#") {
                    $t3 = strstr($var3, '#->') ? str_replace("#->", "", $var3) : $t3;
                    if ($t3) {
                        $var3 = $row[$t3];
                    }
                }
                if ($var4 !== "#false#") {
                    $t4 = strstr($var4, '#->') ? str_replace("#->", "", $var4) : $t4;
                    if ($t4) {
                        $var4 = $row[$t4];
                    }
                }

                $featuretype = $row['feature'];
                $featurelib  = $featuretype . "lib.php";
                $libname     = strtoupper($featuretype . "lib");
                if (!defined($libname)) {
                    if ($featuretype == "pagelist") {
                        if (file_exists($CFG->dirroot . "/lib/" . $featurelib)) {
                            include_once($CFG->dirroot . "/lib/" . $featurelib);
                        }
                    } else {
                        if (file_exists($CFG->dirroot . "/features/$featuretype/" . $featurelib)) {
                            include_once($CFG->dirroot . "/features/$featuretype/" . $featurelib);
                        }
                    }
                }

                $action = $type_in_name ? $pre . $featuretype . $post : $pre . $post;

                if (function_exists($action)) {
                    if ($var4 !== "#false#") {
                        if ($count) {
                            $returnme += $action($var1, $var2, $var3, $var4);
                        } else {
                            $returnme .= $action($var1, $var2, $var3, $var4);
                        }
                    } elseif ($var3 !== "#false#") {
                        if ($count) {
                            $returnme += $action($var1, $var2, $var3);
                        } else {
                            $returnme .= $action($var1, $var2, $var3);
                        }
                    } elseif ($var2 !== "#false#") {
                        if ($count) {
                            $returnme += $action($var1, $var2);
                        } else {
                            $returnme .= $action($var1, $var2);
                        }
                    } elseif ($var1 !== "#false#") {
                        if ($count) {
                            $returnme += $action($var1);
                        } else {
                            $returnme .= $action($var1);
                        }
                    } else {
                        if ($count) {
                            $returnme += $action();
                        } else {
                            $returnme .= $action();
                        }
                    }
                }
            }
        }
    } elseif ($feature !== false) {
        $featurelib = $feature . "lib.php";
        $libname = strtoupper($feature . "lib");
        if ($feature == "pagelist") {
            if (!defined($libname)) {
                if (file_exists($CFG->dirroot . "/lib/" . $featurelib)) {
                    include_once($CFG->dirroot . "/lib/" . $featurelib);
                }
            }
        } else {
            if (!defined($libname)) {
                if (file_exists($CFG->dirroot . "/features/$feature/" . $featurelib)) {
                    include_once($CFG->dirroot . "/features/$feature/" . $featurelib);
                }
            }
        }

        $action = $type_in_name ? $pre . $feature . $post : $pre . $post;
        if (function_exists($action)) {
            if ($var4 !== "#false#") {
                $returnme = $action($var1, $var2, $var3, $var4);
            } elseif ($var3 !== "#false#") {
                $returnme = $action($var1, $var2, $var3);
            } elseif ($var2 !== "#false#") {
                $returnme = $action($var1, $var2);
            } elseif ($var1 !== "#false#") {
                $returnme .= $action($var1);
            } else {
                $returnme = $action();
            }
        }
    }
    return $returnme;
}

function get_user_alerts($userid, $returncount = true) {
    $returnme = all_features_function("SELECT * FROM features", false, "get_", "_alerts", $returncount, $userid, $returncount);
    if (!$returncount) {
        $returnme = $returnme == "" ? fill_template("tmp/pagelib.template", "get_user_alerts_template") : $returnme;
    }

    return $returnme;
}

function print_logout_button($fname, $lname, $pageid = false) {
global $CFG, $USER;
    if (empty($pageid)) {
        $pageid = $CFG->SITEID;
    }

    // Logged in as someone else.
    $logoutas = "";
    if (!empty($_SESSION["lia_original"])) {
        $lia_name = get_user_name($_SESSION["lia_original"]);
        ajaxapi([
            "id" => "lia_switchback",
            "url" => "/features/adminpanel/adminpanel_ajax.php",
            "data" => [
                "action" => "logoutas",
            ],
            "ondone" => "getRoot()[0].go_to_page('" . $CFG->SITEID . "');",
        ]);

        $logoutas = fill_template("tmp/pagelib.template", "print_logout_button_switchback_template", false, ["lia_name" => $lia_name]);
    }

    $edit = user_is_able($USER->userid, "editprofile", $pageid) ? true : false;
    $usericon = empty($logoutas) ? "user" : "user-secret";
    $params   = [
        "siteid" => $CFG->SITEID,
        "title" => "Edit Profile",
        "text" => "$fname $lname",
        "path" => $CFG->wwwroot . "/pages/user.php?action=change_profile",
        "validate" => "true",
        "width" => "500",
        "icon" => icon([["icon" => $usericon, "style" => "font-size: 1.5em"]]),
    ];
    $profile = $edit ? make_modal_links($params) : "$fname $lname";

    $rolename = get_db_field("display_name", "roles", "roleid = " . user_role($USER->userid, $pageid));

    $params = [
        "role" => $rolename,
        "logoutas" => $logoutas,
        "profile" => $profile,
        "userlinks" => get_user_links($USER->userid, $pageid),
    ];

    ajaxapi([
        'id'     => "logout",
        'url'    => '/ajax/site_ajax.php',
        'data'   => [
            'action' => 'get_login_box',
            'logout' => 1,
        ],
        'ondone' => 'killInterval("logincheck"); go_to_page(' . $CFG->SITEID . ');',
    ]);
    return fill_template("tmp/pagelib.template", "print_logout_button_template", false, $params);
}

function get_nav_items($pageid = false) {
global $CFG, $USER, $PAGE;
    $pageid = !$pageid ? (empty($PAGE->id) ? $CFG->SITEID : $PAGE->id) : $pageid;

    //SQL Creation
    if (is_logged_in()) {
        $SQL = fetch_template("dbsql/pages.sql", "get_menu_for_users");
    } else {
        $SQL = fetch_template("dbsql/pages.sql", "get_menu_for_visitors");
    }

    $selected = $pageid == $CFG->SITEID ? true : false;
    $items = '';
    //Query the database
    if ($result = get_db_result($SQL)) {
        while ($row = fetch_row($result)) {
            $menu_children = get_menu_children($row["id"], $pageid);
            $parent = empty($menu_children) ? false : true;
            $selected = $pageid == $row['pageid'] ? true : false;
            $link = empty($row["link"]) ? "#" : $CFG->wwwroot . "/index.php?pageid=" . $row['link'];
            $text = $row['text'] . ' ' . $parent;
            $params = [
                "is_selected" => $selected,
                "menu_children" => $menu_children,
                "text" => $row['text'],
                "is_parent" => $parent,
                "link" => $link,
            ];
            $items .= fill_template("tmp/page.template", "get_nav_item", false, $params);
        }
    }

    if (is_logged_in() && is_siteadmin($USER->userid)) { // Members list visible only if logged in admin
        $members_modal = make_modal_links([
            "title" => "Members List",
            "path" => $CFG->wwwroot . "/pages/page.php?action=browse&section=users&userid=" . $USER->userid,
            "iframe" => true,
            "width" => "640",
            "height" => "623",
            "confirmexit" => "true",
        ]);
        $items .= fill_template("tmp/page.template", "get_ul_item", false, ["item" => $members_modal]);
    }

    if (!empty($items)) {
        return fill_template("tmp/page.template", "make_ul", false, ["id" => "pagenav", "class" => "navtabs", "items" => $items]);
    }
    return "";
}

function get_menu_children($menuid, $pageid) {
global $CFG;
    if ($result = get_db_result(fetch_template("dbsql/pages.sql", "get_menu_children"), ["menuid" => $menuid])) {
        $items = "";
        while ($row = fetch_row($result)) {
            $menu_children = get_menu_children($row["id"], $pageid);
            $parent = empty($menu_children) ? false : true;
            $selected = $pageid == $row['pageid'] ? true : false;
            $link = empty($row["link"]) ? "#" : $CFG->wwwroot . "/index.php?pageid=" . $row['link'];
            $text = stripslashes($row['text']) . ' ' . $parent;
            $params = [
                "is_selected" => $selected,
                "menu_children" => $menu_children,
                "text" => stripslashes($row['text']),
                "is_parent" => $parent,
                "link" => $link,
            ];
            $items .= fill_template("tmp/page.template", "get_nav_item", false, $params);
        }
        return fill_template("tmp/page.template", "make_ul", false, ["id" => "pagenavchild", "class" => "dropdown", "items" => $items]);
    }
    return "";
}

function get_css_box($title, $content, $buttons = '', $padding = null, $feature = '', $featureid = '', $themeid = false, $preview = false, $pageid = false, $bottom_left = false, $bottom_center = false, $bottom_right = false, $class = "") {
global $CFG, $PAGE, $STYLES;
    $returnme = '';

    if ($pageid === false) {
        $pageid = $PAGE->id;
    }

    if ($themeid === false && !$preview) {
        $themeid = get_page_themeid($pageid);
    }

    if ($feature == 'pagename') {
        if ($preview) {
            $styles = $STYLES->pagename;
        } else {
            $styles = get_styles($pageid, "$themeid", "pagename", NULL);
        }

        $pagenamebordercolor = $styles['pagenamebordercolor'] ?? "";
        $pagenamebgcolor     = $styles['pagenamebgcolor'] ?? "";
        $pagenamefontcolor   = $styles['pagenamefontcolor'] ?? "";

        $params = [
            "pagenamebordercolor" => $pagenamebordercolor,
            "pagenamebgcolor" => $pagenamebgcolor,
            "pagenamefontcolor" => $pagenamefontcolor,
            "title" => $title,
            "buttons" => $buttons,
        ];
        $returnme = fill_template("tmp/pagelib.template", "get_css_box_template1", false, $params);
    } else {
        if ($preview) {
            $styles = $STYLES->$feature;
        } else {
            $styles = get_styles($pageid, "$themeid", $feature, $featureid);
        }

        $contentbgcolor = $styles['contentbgcolor'] ?? "";
        $bordercolor    = $styles['bordercolor'] ?? "";
        $titlebgcolor   = $styles['titlebgcolor'] ?? "";
        $titlefontcolor = $styles['titlefontcolor'] ?? "";

        $bottom = "";
        if ($bottom_left || $bottom_center || $bottom_right) {
            $params = [
                "bottom_left" => $bottom_left,
                "bottom_right" => $bottom_right,
                "contentbgcolor" => $contentbgcolor,
            ];
            $returnme .= fill_template("tmp/pagelib.template", "get_css_box_bottom_template", false, $params);
        }

        if ($featureid) {
            $data = get_feature_data($feature, $featureid);
        }
	    $area = isset($data) && $data ? "area_" . $data['area'] : "";

        $opendiv = empty($feature) || $feature == 'pagelist' || $feature == 'addfeature' ? '' : 'class="box ' . $area . '" id="' . $feature . '_' . $featureid . '"';
        $padding = isset($padding) ? ' padding:' . $padding . ";" : "";
        $params = [
            "opendiv" => $opendiv,
            "bordercolor" => $bordercolor,
            "titlebgcolor" => $titlebgcolor,
            "buttons" => $buttons,
            "titlefontcolor" => $titlefontcolor,
            "title" => stripslashes($title),
            "class" => $class,
            "padding" => $padding,
            "contentbgcolor" => $contentbgcolor,
            "content" => $content,
            "bottom" => $bottom,
        ];
        $returnme .= fill_template("tmp/pagelib.template", "get_css_box_template2", false, $params);
    }
    return $returnme;
}

function search_template($templateparams) {
    ajaxapi([
        "id" => "search_navigate",
        "paramlist" => "pagenum",
        "url" => "/ajax/page_ajax.php",
        "data" => [
            "action" => $templateparams["searchtype"],
            "searchwords" => "js||encodeURIComponent('" . $templateparams["searchwords"] . "')||js",
            "pagenum" => "js||pagenum||js",
        ],
        "display" => "searchcontainer_" . $templateparams["searchtype"],
        "loading" => "loading_overlay_" . $templateparams["searchtype"],
        "event" => "none",
    ]);

    return fill_template("tmp/page_ajax.template", "search_template", false, $templateparams);
}

/**
 * Create a select from an array of objects or database results
 *
 * @param array $params Parameters for creating the select
 * @return string HTML of the select
 */
function make_select($params) {
    // Create properties string
    $properties = "";
    foreach ($params["properties"] as $prop => $v) {
        $properties .= $prop . '="' . $v . '" ';
    }

    // Initialize options string
    $optionsstring = "";

    // Get value and display names
    $valuename = $params["valuename"];
    $displayname = $params["displayname"] ?? $valuename;

    if (!empty($params["values"])) {
        // Add first option, if given
        if (isset($params["firstoption"])) {
            $optionsstring .= '<option value="">' . $params["firstoption"] . '</option>';
        }

        if (is_array($params["values"])) {
            // Array.
            foreach ($params["values"] as $value) {
                $options = [
                    "value" => $value[$valuename],
                    "valuename" => $valuename,
                    "display" => $value[$displayname],
                    "selected" => $params["selected"] ?? null,
                    "exclude" => $params["exclude"] ?? null,
                ];
                $optionsstring .= make_options($options);
            }
        } elseif (get_class($params["values"]) == "stdClass") {
            // Standard object.
            foreach ($params["values"] as $value) {
                $options = [
                    "value" => $value->$valuename,
                    "valuename" => $valuename,
                    "display" => $value->$displayname,
                    "selected" => $params["selected"] ?? null,
                    "exclude" => $params["exclude"] ?? null,
                ];
                $optionsstring .= make_options($options);
            }
        } else {
            // Database object.
            while ($row = fetch_row($params["values"])) {
                $options = [
                "value" => $row[$valuename],
                "valuename" => $valuename,
                "display" => $row[$displayname],
                "selected" => $params["selected"] ?? null,
                "exclude" => $params["exclude"] ?? null,
                ];

                $optionsstring .= make_options($options);
            }
        }
    } else {
        if (isset($params["fallback"])) {
            $returnme = $params["fallback"];
        } else {
            $optionsstring .= '<option value="">None</option>';
        }
    }

    if (!empty($optionsstring)) {
        $returnme = "<select $properties>$optionsstring</select>";
    }

    return $returnme;
}

function make_options($params) {
    // Initialize return string
    $returnme = "";

    // Determine if value should be excluded
    $exclude = false;
    if (isset($params["exclude"])) { // exclude value
        switch (gettype($params["exclude"])) {
            case "string":
            case "integer":
                $exclude = $params["exclude"] == $params["value"] ? true : false;
                break;
            case "array":
                foreach ($params["exclude"] as $e) {
                if ($e == $params["value"]) {
                    $exclude = true;
                }
                }
                break;
            case "object":
                while ($e = fetch_row($params["exclude"])) {
                    if ($e[$params["valuename"]] == $params["value"]) {
                        $exclude = true;
                    }
                }

                db_goto_row($params["exclude"]);
                break;
        }
    }

    // Add option if not excluded
    if (!$exclude) {
        // Determine if option is selected
        $selected = "";
        if (isset($params["selected"]) && $params["value"] == $params["selected"]) {
            $selected = 'selected="selected"';
        }

        $returnme = '<option value="' . $params["value"] . '" ' . $selected . '>' . $params["display"] . '</option>';
    }

  return $returnme;
}

function sort_object($object, $value, $sorttype = SORT_REGULAR) {
    $i = 0;
    while (isset($object->$i)) {
        $array[$i] = $object->$i->$value;
        $i++;
    }

    sort($array, $sorttype);

    $i = 0;
    while (isset($object->$i)) {
        $z = 0;
        while (isset($object->$z) && $object->$z->$value != $array[$i]) {
            $z++;
        }

        $newobject->$i = $object->$z;
        $i++;
    }

    return $newobject;
}

function create_page_shortname($var) {
    return substr(strtolower(preg_replace("/\W|_/", '', $var)), 0, 20);
}

function modify_menu_page($params) {
    if (!isset($params["text"]) && isset($params["name"])) {
        $params["text"] = $params["name"];
    }
    if (get_db_row(fetch_template("dbsql/pages.sql", "get_page_menu"), $params)) {
        return execute_db_sql(fetch_template("dbsql/pages.sql", "update_page_menu"), $params);
    } else { // Make new menu item.
        $params["sort"] = get_db_field("sort", "menus", "id > 0 ORDER BY sort DESC") + 1;
        return execute_db_sql(fetch_template("dbsql/pages.sql", "add_page_menu"), $params);
    }
}

function delete_page($pageid) {
    $pageid = clean_var_req($pageid, "int");
    $templates = [];
    $templates[] = [
        "file" => "dbsql/pages.sql",
        "subsection" => [
            "delete_page",
            "delete_page_menus",
            "delete_page_settings",
        ],
    ];
    $templates[] = [
        "file" => "dbsql/roles.sql",
        "subsection" => [
            "remove_page_role_assignments",
            "remove_page_roles_ability_perpage",
            "remove_page_roles_ability_peruser",
            "remove_page_roles_ability_perfeature",
            "remove_page_roles_ability_pergroup",
            "remove_page_roles_ability_perfeature_peruser",
            "remove_page_roles_ability_perfeature_pergroup",
        ],
    ];
    $templates[] = ["file" => "dbsql/features.sql", "subsection" => "delete_page_features"];
    $templates[] = ["file" => "dbsql/styles.sql", "subsection" => "delete_page_styles"];

    try {
        start_db_transaction();
        execute_db_sqls(fetch_template_set($templates), ["pageid" => $pageid]);
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        return false;
    }
    return true;
}

/**
 * Subscribe a user to a page.
 *
 * @param int $pageid The id of the page to subscribe to.
 * @param int $userid The id of the user to subscribe. Defaults to the current user.
 * @param bool $removeifexists If true, will remove the subscription if it already exists.
 *
 * @return boolean True if the subscription was successful, false otherwise.
 */
function change_page_subscription($pageid, $userid = false, $removeifexists = false) {
global $USER;
    $userid = $userid ? $userid : $USER->userid;
    $defaultrole = get_default_role($pageid);

    $SQL = fetch_template("dbsql/roles.sql", "get_role_assignment");
    // Subscribe if role doesn't exist.
    if (!$roleExists = get_db_count($SQL, ["userid" => $userid, "pageid" => $pageid, "confirm" => 0])) {
        $SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
        $role_assignment = execute_db_sql($SQL, ["userid" => $userid, "roleid" => $defaultrole, "pageid" => $pageid, "confirm" => 0]);
    } else {
        // Unsubscribe if role exists and removeifexists is true.
        if ($removeifexists && $roleExists) {
            $role_assignment = execute_db_sql(fetch_template("dbsql/roles.sql", "remove_user_role_assignment"), ["userid" => $userid, "pageid" => $pageid]);
        }
    }

    if ($pageid && $role_assignment) {
        return true;
    }
    return false;
}

function get_feature_data($feature, $featureid) {
    $SQL = "SELECT area
            FROM pages_features
            WHERE feature = ||feature||
            AND featureid = ||featureid||";
    return get_db_row($SQL, ["feature" => $feature, "featureid" => $featureid]);
}

function get_page_contents($pageid = false, $area = "middle") {
global $CFG, $PAGE;
    $returnme = '';

    if (!$pageid) {
        $PAGE = set_pageid();
        $pageid = $PAGE->pageid;
    }

    if ($area == "side") { // ADD pagelist to top of right side
        if (!defined('PAGELISTLIB')) { require_once($CFG->dirroot . '/lib/pagelistlib.php'); }
        $returnme .= display_pagelist($pageid, $area);
    }

    $SQL = "SELECT * from pages_features WHERE pageid='$pageid' AND area='$area' ORDER BY sort";

    $returnme .= all_features_function($SQL, false, "display_", "", false, $pageid, $area, "#->featureid");

    if ($area == "side") { //ADD Add feature block to bottom of right side
        if (!defined('ADDFEATURELIB')) {
            include_once($CFG->dirroot . '/lib/addfeaturelib.php');
        }
        $returnme .= display_addfeature($pageid, $area);
    }

    return $returnme;
}

function get_login_form($loginonly = false, $newuser = true) {
global $CFG;
    if (!defined('VALIDATELIB')) {
        include_once($CFG->dirroot . '/lib/validatelib.php');
    }

    $newuserlink = $newuser ? make_modal_links([
                                "title" => "New User",
                                "path" => $CFG->wwwroot . "/pages/user.php?action=new_user",
                                "width" => "500",
                            ]) : '';

    $forgotpasswordlink = make_modal_links([
                            "title" => "Forgot password?",
                            "path" => $CFG->wwwroot . "/pages/user.php?action=forgot_password_form",
                            "width" => "500",
                        ]);

    ajaxapi([
        'id' => "jq_login",
        'url' => '/ajax/site_ajax.php',
        'data' => [
            'action' => "login",
            'username' => "js||encodeURIComponent($('#username').val())||js",
            'password' => "js||$('#password').val()||js",
        ],
        "event" => "none",
        "ondone" => "verify_login(data);",
    ]);

    $params = [
        "validation_script" => create_validation_script("login_form", "jq_login();"),
        "valid_req_username" => error_string('valid_req_username'),
        "input_username" => get_help("input_username"),
        "valid_req_password" => error_string('valid_req_password'),
        "input_password2" => get_help("input_password2"),
        "newuserlink" => $newuserlink,
        "forgotpasswordlink" => $forgotpasswordlink,
    ];
    $content = fill_template("tmp/pagelib.template", "get_login_form_template", false, $params);

    $returnme = $loginonly ? $content : get_css_box("Login", $content);
    return $returnme;
}

function add_page_feature($pageid, $featuretype) {
global $PAGE;
    if (set_pageid($pageid)) {
        // Add feature
        $default_area = get_db_field("default_area", "features", "feature='$featuretype'");
        $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $default_area]) + 1;
        if (get_db_row("SELECT * FROM features WHERE feature='$featuretype' AND multiples_allowed = '1'")) {
            $featureid = all_features_function(false, $featuretype, "insert_blank_", "", false, $pageid);
        } else {
            $featureid = execute_db_sql("INSERT INTO pages_features (pageid, feature, sort, area) VALUES('$pageid','$featuretype','$sort','$default_area')");
            execute_db_sql("UPDATE pages_features SET featureid='$featureid' WHERE id='$featureid'");
        }

        log_entry($featuretype, $featureid, "Added Feature");
    }
}

function get_edit_buttons($pageid, $featuretype, $featureid = false) {
global $CFG, $USER;
    $returnme = "";
    $is_feature_menu = true; //Assume it is a main feature block button menu
    //User must be logged in
    if (is_logged_in()) {
        //Is this a feature with sections?
        //If is it a feature with sections...get the correct feature id.  If it is a normal feature, set is_section to true
        if (!strstr($featuretype, "_features")) {
            $subset = get_db_row("SHOW TABLE STATUS LIKE '$featuretype" . "_features'");
            $is_feature_menu = $featuretype == "pagename" || empty($subset) ? true : false;
        } else {
            $featuretype = str_replace("_features", "", $featuretype);
        }

        //If this is a section
        if ($is_feature_menu) {
            //Move block buttons
            if (user_is_able($USER->userid, "movefeatures", $pageid, $featuretype, $featureid)) {
                $returnme .= ' <a class="slide_menu_button pagesorthandle" href="javascript: void(0);" title="Move feature">' . icon("up-down-left-right") . '</a> ';
            }

            //Role and Abilities Manger button
            if ($featureid && (user_is_able($USER->userid, "edit_feature_abilities", $pageid, $featuretype, $featureid)
                                ||
                                 user_is_able($USER->userid, "edit_feature_user_abilities", $pageid, $featuretype, $featureid)
                                ||
                                 user_is_able($USER->userid, "edit_feature_group_abilities", $pageid, $featuretype, $featureid))) {
                $params = [
                    "title" => "Roles & Abilities Manager",
                    "path" => $CFG->wwwroot . "/pages/roles.php?action=manager&feature=$featuretype&pageid=$pageid&featureid=$featureid",
                    "iframe" => true,
                    "width" => "700",
                    "height" => "580",
                    "icon" => icon("key"),
                    "class" => "slide_menu_button",
                ];
                $returnme .= make_modal_links($params);
            }

            //Feature Settings
            if (file_exists($CFG->dirroot . '/features/' . $featuretype . '/' . $featuretype . '.php')) {
                $_POST["aslib"] = true;
                $settings = include_once($CFG->dirroot . '/features/' . $featuretype . '/' . $featuretype . '.php');

                //Settings link for all features
                if (function_exists($featuretype . '_settings') && user_is_able($USER->userid, "editfeaturesettings", $pageid, $featuretype, $featureid)) {
                    $params = [
                        "title" => "Edit Settings",
                        "path" => $CFG->wwwroot . "/features/$featuretype/$featuretype.php?action=" . $featuretype . "_settings&pageid=$pageid&featureid=$featureid",
                        "width" => "640",
                        "refresh" => "true",
                        "icon" => icon("sliders"),
                        "class" => "slide_menu_button",
                    ];
                    $returnme .= make_modal_links($params);
                }
                $_POST["aslib"] = false;
            }

            //Remove feature button
            if (user_is_able($USER->userid, "removefeatures", $pageid, $featuretype, $featureid)) {
                ajaxapi([
                    "if" => "confirm('Are you sure you want to delete this?')",
                    "id" => "delete_" . $featuretype . "_" . $featureid,
                    "url" => "/ajax/site_ajax.php",
                    "data" => ["action" => "delete_feature", "pageid" => $pageid, "featuretype" => $featuretype, "featureid" =>  $featureid],
                    "ondone" => "go_to_page(' . $pageid . ');",
                ]);
                $returnme .= '<button id="delete_' . $featuretype . "_" . $featureid . '" class="alike slide_menu_button" title="Delete Feature">' . icon("trash") . '</button>';
            }
        }
    }
    return $returnme;
}

function get_button_layout($featuretype, $featureid = "", $pageid = 0) {
global $CFG, $PAGE;
    $returnme = "";
    if ($featuretype == 'pagename' || $featuretype == 'pagelist') {
        if (!defined('PAGELISTLIB')) { include_once ($CFG->dirroot . '/lib/pagelistlib.php'); }
        $action = $featuretype . "_buttons";
        $feature_buttons = function_exists($action) ? $action($pageid, $featuretype, $featureid) : "";
        $buttons = $feature_buttons;
    } else {
        $feature = str_replace("_features", "", $featuretype);
        $feature_buttons = all_features_function(false, $feature, "", "_buttons", false, $pageid, $featuretype, $featureid);
        $buttons = $feature_buttons . get_edit_buttons($pageid, $featuretype, $featureid);
    }

    $themeid = get_page_themeid($PAGE->id);
    if (!$themeid && $pageid) {
        $themeid = get_page_themeid($pageid);
    }

    $styles = get_styles($pageid, $themeid, $featuretype, $featureid);
    $contentbgcolor = $styles['contentbgcolor'] ?? "";
    $bordercolor    = $styles['bordercolor'] ?? "";
    $titlebgcolor   = $styles['titlebgcolor'] ?? "";
    $titlefontcolor = $styles['titlefontcolor'] ?? "";

    if (strlen($buttons) > 0) {
        $params = [
            "bordercolor" => $bordercolor,
            "titlefontcolor" => $titlefontcolor,
            "titlebgcolor" => $titlebgcolor,
            "featuretype" => $featuretype,
            "featureid" => $featureid,
            "buttons" => $buttons,
            "icon" => icon("grip-vertical", 1, "", $titlebgcolor),
        ];
        $returnme = fill_template("tmp/pagelib.template", "get_button_layout_template", false, $params);
    }

    return $returnme;
}

/**
 * Returns a set of variables for a search page
 * @param int $total Total number of search results
 * @param int $perpage Number of results to display on each page
 * @param int $pagenum Current page number
 * @return array An array of variables for the search page
 */
function get_search_page_variables(int $total, int $perpage, int $pagenum) {
    $firstonpage = $perpage * $pagenum; // First result on this page

    $vars = [ // Variables for the "Viewing x through y out of z" message
        "first" => $firstonpage + 1,
        "last" => $firstonpage + $perpage < $total ? $firstonpage + $perpage : $total,
        "total" => $total,
    ];

    return [
        "firstonpage" => $firstonpage, // First result on this page
        "count"       => $total > (($pagenum + 1) * $perpage) ? $perpage : $total - $firstonpage, // Number of results on this page
        "amountshown" => $vars["last"], // Last result on this page
        "prev"        => $pagenum > 0 ? true : false, // Whether there is a previous page
        "info"        => fill_string('Viewing {first} through {last} out of {total}', $vars), // Viewing x through y out of z message
        "next"        => $firstonpage + $perpage < $total ? true : false, // Whether there is a next page
    ];
}

function get_nav_params($pageparams) {
    return $navparams = [
        "isprev" => ($pageparams["pagenum"] > 0),
        "isnext" => ($pageparams["firstonpage"] + $pageparams["perpage"] < $pageparams["total"]),
        "firstonpage" => $pageparams["firstonpage"] + 1,
        "amountshown" => $pageparams["firstonpage"] + $pageparams["perpage"] < $pageparams["total"] ? $pageparams["firstonpage"] + $pageparams["perpage"] : $pageparams["total"],
        "total" => $pageparams["total"],
    ];
}

function get_page_count($pageparams) {
    if ($pageparams["total"] > (($pageparams["pagenum"] + 1) * $pageparams["perpage"])) {
        return $pageparams["perpage"];
    }

    return  $pageparams["total"] - (($pageparams["pagenum"]) * $pageparams["perpage"]);
}

function get_searchcontainer($initial = "") {
    global $CFG;
    return '<div id="loading_overlay" class="loading_overlay dontprint" style="display: none;">
                ' . icon("spinner fa-spin-pulse") . '
            </div>
            <span id="searchcontainer">' . $initial . '</span>';
}

function make_search_box($contents = "", $name_addition = "") {
global $CFG;
    $params = [
        "name_addition" => $name_addition,
        "wwwroot" => $CFG->wwwroot,
        "contents" => $contents,
    ];
    return fill_template("tmp/pagelib.template", "make_search_box_template", false, $params);
}

/**
 * Formats the content in a popup window
 *
 * @param string $content The content to put in the popup
 * @param string $title The title for the popup
 * @param string $height The height of the popup (defaults to "calc(100% - 60px)")
 * @param string $padding The padding of the popup (defaults to "15px")
 * @return string The HTML for the popup
 */
function format_popup(string $content = "", string $title = "", string $height = "auto", string $padding = "15px", string $before = "") {
    $params = [
        "before" => $before,
        "padding" => $padding,
        "height" => $height,
        "title" => $title,
        "content" => $content,
    ];
    return fill_template("tmp/pagelib.template", "format_popup_template", false, $params);
}

/**
 * Move a feature in a specific area of a page
 *
 * @param array $params Parameters to pass to the function
 * @param string $params["column"] Array of feature IDs with their current column and row information
 * @param string $params["pageid"] The ID of the page to move the feature to
 * @param string $params["area"] The area to move the feature to
 *
 * @throws Exception If there is an issue with the feature data or the database query
 */
function move_features($params) {
    $i = 1;
    foreach ($params["column"] as $featureinfo) {
        $feature = explode("_", $featureinfo);
        $featuretype = $feature[0] ?? false;
        $featureid = $feature[1] ?? false;

        if (!$featuretype || !$featureid) {
            throw new Exception("Invalid feature data passed to move_features");
        }

        $SQL = fetch_template("dbsql/features.sql", "get_feature");
        $current = get_db_row($SQL, ["pageid" => $params["pageid"], "feature" => $featuretype, "featureid" => $featureid]);

        if (!$current) {
            throw new Exception("Cannot get_feature during move");
        }

        $SQL = fetch_template("dbsql/features.sql", "update_feature_sort");
        execute_db_sql($SQL, [
            "id" => $current["id"],
            "sort" => $i,
            "area" => $params["area"],
        ]);
        $i++;
    }
}

/**
 * Include a hidden iframe to keep the session alive
 *
 * @return string The HTML for the hidden iframe
 */
function keepalive() {
global $CFG;
    return fill_template("tmp/pagelib.template", "keepalive_template", false, ["wwwroot" => $CFG->wwwroot]);
}

function emptyreturn() {
    echo "";
}

/**
 * Replaces placeholders in a string with the given values.
 *
 * @param string $string The string with placeholders.
 * @param array $vars The key-value pairs used for replacement.
 * @return string The modified string.
 */
function fill_string(string $string, array $vars) {
    /* Iterate over the given key-value pairs and replace their
        placeholders in the given string. */
    foreach ($vars as $key => $value) {
        /* Replace the placeholder "{key}" with the value. */
        $string = str_replace("{" . $key . "}", $value, $string);
    }

    /* Return the modified string. */
    return $string;
}

function old_truncate($string, $length = 100, $append = "&hellip;") {
    $string = trim($string);

    if (strlen($string) > $length) {
        $string = wordwrap($string, $length);
        $string = explode("\n", $string, 2);
        $string = $string[0] . $append;
    }

    return $string;
}

function truncate($string, $length) {
    // Replace specific HTML tags with newline characters
    $string = preg_replace('/<ul>|<li>|<div>|<\/ul>|<\/li>|<\/div>/', "\n", $string);

    // Strip all HTML tags except for <b>, <i>, <strong>, and <em>
    $allowed_tags = '<b><i><strong><em>';
    $string = strip_tags($string, $allowed_tags);

    // Normalize multiple newlines to a single newline
    $string = preg_replace('/\n+/', "\n", $string);

    // Convert all newlines to br tags
    $string = nl2br($string);

    // Trim whitespace from beginning and end
    $string = trim($string);

    // Check if the length of the string is less than or equal to the specified length
    if (strlen($string) <= $length) {
        return $string;
    }

    // Truncate the string to the specified length
    $truncated = substr($string, 0, $length);

    // Ensure words are not cut in half
    if (substr($string, $length, 1) != ' ' && strpos($truncated, ' ') !== false) {
        $truncated = substr($truncated, 0, strrpos($truncated, ' '));
    }

    // Append ellipsis
    return $truncated . '...';
}

function encodeURIComponent($str) {
    $revert = ['%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'];
    return strtr(rawurlencode($str), $revert);
}

function preg_grep_keys($pattern, $input, $flags = 0) {
    return array_filter($input, function($key) use ($pattern, $flags) {
           return preg_match($pattern, $key, $flags);
    }, ARRAY_FILTER_USE_KEY);
}

function is_javascript($str) {
    // Trim whitespace
    $str = trim($str);

    // Check for common JavaScript indicators
    if (preg_match('/^(function|var|let|const|if|for|while|return|document\.|window\.)/', $str) ||
        preg_match('/(\{.*\}|\(.*\))\s*=>/', $str) || // Arrow functions
        preg_match('/\b(true|false|null|undefined)\b/', $str) || // Common JavaScript literals
        preg_match('/(console\.log\(|alert\(|setTimeout\(|setInterval\(|\.width\(|\.height\()/', $str) || // Common JavaScript functions
        preg_match('/[\w$]+\.(length|push|pop|shift|unshift|slice|indexOf)/', $str)) { // Common array/string methods
        return true;
    }

    return false;
}
?>