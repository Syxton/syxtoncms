<?php
/***************************************************************************
 * pagelib.php - Page function library
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 5/28/2021
 * Revision: 3.1.7
 ***************************************************************************/

if (!isset($LIBHEADER)) { include('header.php'); }
$PAGELIB = true;

//Get page info
if (empty($PAGE)) {
  $PAGE = new \stdClass;
}
$PAGE->id = isset($_GET['pageid']) ? $_GET['pageid'] : $CFG->SITEID;
if (!is_numeric($PAGE->id)) { // Somebody could be playing with this variable.
  $PAGE->id = $CFG->SITEID;
}

function callfunction() {
global $CFG, $MYVARS;
  if (empty($_POST["aslib"])) {

    collect_vars(); // Place all passed variables in MYVARS global.
  
    if (!empty($MYVARS->GET["i"])) { // Universal javascript and CSS.
      $params = ["directory" => get_directory()];
      echo template_use("tmp/pagelib.template", $params, "main_js_css");
    }
    if (!empty($MYVARS->GET["v"])) { // Validation javascript and CSS.
      echo get_js_tags(["validate"]);
      unset($MYVARS->GET["v"]);
    }
    if (function_exists($MYVARS->GET["action"])) {
      $action = $MYVARS->GET["action"];
      $action(); // Go to the function that was called.
    } else {
      echo get_page_error_message("no_function", [$MYVARS->GET["action"]]);
    }
  }
}

function collect_vars() {
global $CFG, $MYVARS;
    //Retrieve from Javascript
    $postorget = isset($_POST["action"]) ? $_POST : false;

    $MYVARS = empty($MYVARS) ? new stdClass() : $MYVARS;
    $MYVARS->GET = !$postorget && isset($_GET["action"]) ? $_GET : $postorget;
    $MYVARS->GET = empty($MYVARS->GET) ? $_GET : $MYVARS->GET;
}

function get_directory() {
global $CFG;
  return (empty($CFG->directory) ? '' : $CFG->directory . '/');
}

function main_body($header_only = false) {
  $params = [
    "page_masthead_1" => page_masthead(true),
    "page_masthead_2" => page_masthead(false, $header_only),
  ];
  return template_use("tmp/pagelib.template", $params, "main_body_template");
}

function get_pageid() {
global $PAGE, $CFG, $MYVARS;

  if (!empty($MYVARS->GET["pageid"]) && is_numeric($MYVARS->GET["pageid"])) {
    return $MYVARS->GET["pageid"];
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

function page_masthead($left = true, $header_only = false) {
global $CFG, $USER, $PAGE;
  if ($left) {
    $styles = get_styles($PAGE->id, $PAGE->themeid);
  	$header_color = isset($styles['pagenamebgcolor']) ? $styles['pagenamebgcolor'] : "";
  	$header_text = isset($styles['pagenamefontcolor']) ? $styles['pagenamefontcolor'] : "";

    $params = [
      "wwwroot" => $CFG->wwwroot,
      "haslogo" => isset($CFG->logofile),
      "logofile" => $CFG->logofile,
      "hasmobilelogo" => !empty($CFG->mobilelogofile),
      "mobilelogofile" => $CFG->mobilelogofile,
      "sitename" => $CFG->sitename,
      "header_only" => ($header_only ? "" : get_nav_items($PAGE->id)),
      "quote" => random_quote(),
      "pagename" => $PAGE->name,
      "header_text" => $header_text,
      "header_color" => $header_color,
    ];
    return template_use("tmp/pagelib.template", $params, "page_masthead_template");
  }

  return (!$header_only ? (is_logged_in() ? print_logout_button($USER->fname, $USER->lname, $PAGE->id) : get_login_form()) : '');
}

function get_editor_javascript() {
global $CFG;
  return js_script_wrap($CFG->wwwroot . '/scripts/tinymce/jquery.tinymce.min.js');
}

function get_editor_value_javascript($editorname = "editor1") {
  return '$(\'#' . $editorname . '\').val()';
}

function get_editor_box($initialValue = "", $name = "editor1", $height = "550", $width = "95%", $type = "HTML") {
global $CFG;
  $variables = new \stdClass();
  $variables->wwwroot = $CFG->wwwroot;
  $variables->toolbar = get_editor_toolbar($type);
  $variables->height = $height;
  $variables->width = $width;
  $variables->plugins = get_editor_plugins($type);
  $variables->directory = get_directory();

  return template_use("tmp/pagelib.template", ["name" => $name, "variables" => $variables, "initialvalue" => $initialValue], "editor_box_template");
}

function get_editor_plugins($type) {
  switch ($type) {
    case "Default":
      $set = '"autolink image lists link responsivefilemanager charmap preview hr anchor pagebreak",
          "searchreplace wordcount visualblocks visualchars code fullscreen textcolor",
          "insertdatetime media nonbreaking paste table contextmenu directionality"';
      break;
    case "News":
      $set = '"autolink image lists link responsivefilemanager charmap preview hr anchor pagebreak",
          "searchreplace wordcount visualblocks visualchars code fullscreen textcolor",
          "insertdatetime media nonbreaking paste table contextmenu directionality"';
      break;
    case "HTML":
      $set = '"autolink image lists link responsivefilemanager charmap preview hr anchor pagebreak",
          "searchreplace wordcount visualblocks visualchars code fullscreen textcolor",
          "insertdatetime media nonbreaking paste table contextmenu directionality"';
      break;
    case "Basic":
      $set = '"autolink lists charmap preview hr anchor pagebreak",
          "searchreplace wordcount visualblocks visualchars code fullscreen",
          "insertdatetime nonbreaking paste table contextmenu directionality"';
      break;
    case "Forum":
      $set = '"autolink image lists link responsivefilemanager charmap preview hr anchor pagebreak",
          "searchreplace wordcount visualblocks visualchars code fullscreen",
          "insertdatetime media nonbreaking paste table contextmenu directionality"';
      break;
    case "Shoutbox":
      $set = '"autolink"';
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
  if (!get_db_row("SELECT * FROM pages WHERE pageid=1")) {
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

function move_page_feature($pageid, $featuretype, $featureid, $direction) {
global $PAGE;
  $PAGE->id         = $pageid;
  $current_position = get_db_field("sort", "pages_features", "pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
  $area             = get_db_field("area", "pages_features", "pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
  if ($direction == 'up') {
    $up_position = $current_position - 1;
    execute_db_sql("UPDATE pages_features SET sort='$current_position' WHERE pageid='$pageid' AND area='$area' AND sort='$up_position'");
    execute_db_sql("UPDATE pages_features SET sort='$up_position' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
  } elseif ($direction == 'down') {
    $down_position = $current_position + 1;
    execute_db_sql("UPDATE pages_features SET sort='$current_position' WHERE pageid='$pageid' AND area='$area' AND sort='$down_position'");
    execute_db_sql("UPDATE pages_features SET sort='$down_position' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
  } elseif ($direction == 'middle') {
    execute_db_sql("UPDATE pages_features SET area='middle',sort='9999' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
    resort_page_features($pageid);
  } elseif ($direction == 'side') {
    execute_db_sql("UPDATE pages_features SET area='side',sort='9999' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
    resort_page_features($pageid);
  } elseif ($direction == 'locker') {
    execute_db_sql("UPDATE pages_features SET area='locker' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
    resort_page_features($pageid);
  }

  log_entry($featuretype, $featureid, "Move Feature");
}

function resort_page_features($pageid) {
  //Middle first
  $i = 1;
  if ($result = get_db_result("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='middle' ORDER BY sort")) {
    while ($row = fetch_row($result)) {
      execute_db_sql("UPDATE pages_features SET sort='$i' WHERE id='" . $row['id'] . "'");
      $i++;
    }
  }
  //Side second
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
  $v["title"]       = empty($v["title"]) ? "" : $v["title"];
  $v["confirmexit"] = empty($v["confirmexit"]) ? "" : $v["confirmexit"];
  $v["id"]          = empty($v["id"]) ? "" : 'id="' . $v["id"] . '"';
  $v["type"]        = empty($v["type"]) ? "" : 'type="' . $v["type"] . '"';
  $gallery_name     = empty($v["gallery"]) ? "" : $v["gallery"];
  $gallery          = empty($v["gallery"]) ? "" : "('*[data-rel=\'$gallery_name\']')";
  $v["gallery"]     = empty($v["gallery"]) ? "" : ",rel:'$gallery_name',photo:'true',preloading:'true'";
  $v["imagestyles"] = empty($v["imagestyles"]) ? ($v["button"] == "link" ? "" : "vertical-align: middle;") : $v["imagestyles"];
  $v["image"]       = empty($v["image"]) ? "" : '<img alt="' . $v["title"] . '" title="' . $v["title"] . '" src="' . $v["image"] . '" style="' . $v["imagestyles"] . '" />';
  $v["width"]       = empty($v["width"]) ? (empty($v["gallery"]) ? "" : "") : ",width:'" . $v["width"] . "'";
  $v["height"]      = empty($v["height"]) ? (empty($v["gallery"]) ? "" : "") : ",height:'" . $v["height"] . "'";
  $v["path"]        = empty($v["path"]) ? "" : $v["path"];
  $v["class"]       = empty($v["class"]) ? "" : $v["class"];
  $path             = $v["path"] && $v["gallery"] ? $v["path"] : "javascript: void(0);";
  $v["text"]        = empty($v["text"]) ? (empty($v["image"]) ? (empty($v["title"]) ? "" : $v["title"]) : $v["image"]) : (empty($v["image"]) ? $v["text"] :  $v["image"] . ' <span style="vertical-align: middle;">' . $v["text"] . "</span>");

  $iframe      = empty($v["iframe"]) ? "" : ",fastIframe:true,iframe:true";
  $i           = empty($v["iframe"]) ? "" : "&amp;i=!";
  $rand        = '&amp;t=' . get_timestamp();
  $v["styles"] = empty($v["styles"]) ? "" : $v["styles"];

  $v["refresh"]  = empty($v["refresh"]) ? "" : $v["refresh"];
  $v["runafter"] = empty($v["runafter"]) ? "" : $v["runafter"];

  $modal = $onOpen = $onComplete = $valid = '';

  if (!empty($v["validate"]) && empty($v["iframe"])) { //load validation javascript
    $onOpen .= "loadjs('" . get_js_tags(["validate"], true) . "');";
  } elseif (!empty($v["validate"]) && !empty($v["iframe"])) {
    $valid = "&amp;v=!";
  }

  if (!empty($v["refresh"])) {
    $modal .= '
    $.colorbox.close = function() {
        window.location.reload( true );
    };';
  }

  if (!empty($v["runafter"])) {
    $modal .= '
    var originalClose = $.colorbox.close;
    $.colorbox.close = function() {';

    $modal .= empty($v["confirmexit"]) ? "" : 'if (confirm(\'Are you sure you wish to close this window?\')) {';
    $modal .= 'eval(stripslashes(unescape(self.parent.$(\'#' . $v["runafter"] . '\').val())));
               setTimeout(function() { originalClose(); $.colorbox.close = originalClose; },100);';
    $modal .= empty($v["confirmexit"]) ? "" : '}';
    $modal .= '};';
  } elseif (!empty($v["confirmexit"])) {
    $modal .= '
    var originalClose = $.colorbox.close;
    $.colorbox.close = function() {
        if (confirm(\'Are you sure you wish to close this window?\')) {
            originalClose(); $.colorbox.close = originalClose;
        }
    };';
  }

  if ((empty($v["height"]) || empty($v["width"]))) {
    if (empty($v["iframe"])) {
      $onComplete = 'setTimeout(function() { $.colorbox.resize(); },1500);';
    } else {
      $onComplete .= '
      setTimeout(function() {
          parent.$.colorbox.resize({
              width:$(\'iframe[class=cboxIframe]\').contents().width()+50,
              height:$(\'iframe[class=cboxIframe]\').contents().height()+75
          });
      },1500);';
    }
  }

  if ($v["gallery"]) {
    $modal .= '$' . $gallery . '.colorbox({maxWidth: \'95%\',maxHeight: \'95%\',fixed: true' . $v["width"] . $v["height"] . $v["gallery"] . ',speed:0});';
  } else {
    $modal .= '$.colorbox({maxWidth: \'98%\',maxHeight: \'98%\',fixed: true,onComplete:function() { ' . $onComplete . ' $(\'#cboxTitle\').attr({\'style\': \'display: none\'}); },href:\'' . $v["path"] . $i . $valid . $rand . '\'' . $v["width"] . $v["height"] . $v["gallery"] . ',speed:0' . $iframe . '});';
  }

  if (!empty($onOpen)) {
    $modal = "setTimeout(function() { $modal },500);";
  }

  if ($v["button"] == "button") {
    return '<button ' . trim($v["id"]) . ' class="smallbutton ' . $v["class"] . '" ' . trim($v["type"]) . ' title="' . trim(strip_tags($v["title"])) . '" style="' . $v["styles"] . '" onclick="' . $onOpen . ' ' . $modal . '" />' . $v["text"] . '</button>';
  } else {
    return '<a ' . trim($v["id"]) . ' class="' . $v["class"] . '" ' . trim($v["type"]) . ' data-rel="' . $gallery_name . '" title="' . trim(strip_tags($v["title"])) . '" style="' . $v["styles"] . '" onclick="' . $onOpen . ' ' . $modal . '" href="' . $path . '">' . $v["text"] . '</a>';
  }
}

function get_user_links($userid, $pageid) {
global $CFG;
  $returnme = '<input type="hidden" id="loggedin" />';
  $alerts   = false;
  $alerts   = get_user_alerts($userid);
  if ($alerts) {
    $alerts_text = $alerts == 1 ? "Alert" : "Alerts";
    $returnme .= '<span class="profile_links">' . make_modal_links([
                                                    "title" => "Alerts",
                                                    "id" => "alerts_link",
                                                    "text" => '<span id="alerts_span">' . "$alerts $alerts_text" . '</span>',
                                                    "path" => $CFG->wwwroot . "/pages/user.php?action=user_alerts&amp;userid=$userid",
                                                    "width" => "600",
                                                    "height" => "500",
                                                    "image" => $CFG->wwwroot . "/images/error.gif",
                                                  ]) . '</span>';
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
  $t1       = $t2 = $t3 = $t4 = false;

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
        if (!isset($$libname)) {
            if ($featuretype == "pagelist") {
                if (!isset($$libname)) {
                    include_once($CFG->dirroot . "/lib/" . $featurelib);
                }
            } else {
                if (!isset($$libname)) {
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
    $libname    = strtoupper($feature . "lib");
    if ($feature == "pagelist") {
        if (!isset($$libname)) {
            include_once($CFG->dirroot . "/lib/" . $featurelib);
        }
    } else {
        if (!isset($$libname)) {
            include_once($CFG->dirroot . "/features/$feature/" . $featurelib);
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

function get_user_alerts($userid, $returncount = true, $internal = true) {
  $returnme = all_features_function("SELECT * FROM features", false, "get_", "_alerts", $returncount, $userid, $returncount);
  if (!$returncount) {
    $returnme = $returnme == "" ? template_use("tmp/pagelib.template", [], "get_user_alerts_template") : $returnme;
  }

  if ($internal) {
    return $returnme;
  } else {
    echo $returnme;
  }
}

function print_logout_button($fname, $lname, $pageid = false) {
global $CFG, $USER;
  if (empty($pageid)) {
    $pageid = $CFG->SITEID;
  }
  $edit = user_has_ability_in_page($USER->userid, "editprofile", $pageid) ? true : false;
  $params   = [
    "siteid" => $CFG->SITEID,
    "title" => "Edit Profile",
    "text" => "$fname $lname",
    "path" => $CFG->wwwroot . "/pages/user.php?action=change_profile",
    "validate" => "true",
    "width" => "500",
    "image" => $CFG->wwwroot . "/images/user.png",
    "styles" => "",
  ];
  $profile = $edit ? make_modal_links($params) : "$fname $lname";

  // Logged in as someone else.
  $logoutas = "";
  if (!empty($_SESSION["lia_original"])) {
    $lia_name = get_user_name($_SESSION["lia_original"]);
    $logoutas = template_use("tmp/pagelib.template", ["lia_name" => $lia_name], "print_logout_button_switchback_template");
  }

  $params = [
    "siteid" => $CFG->SITEID,
    "logoutas" => $logoutas,
    "profile" => $profile,
    "userlinks" => get_user_links($USER->userid, $pageid),
  ];
  return template_use("tmp/pagelib.template", $params, "print_logout_button_template");
}

function get_nav_items($pageid = false) {
global $CFG, $USER, $PAGE;
  $pageid = !$pageid ? (empty($PAGE->id) ? $CFG->SITEID : $PAGE->id) : $pageid;

  //SQL Creation
  if (is_logged_in()) {
    $SQL = template_use("dbsql/pages.sql", [], "get_menu_for_users");
  } else {
    $SQL = template_use("dbsql/pages.sql", [], "get_menu_for_visitors");
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
      $text = stripslashes($row['text']) . ' ' . $parent;
      $params = [
        "is_selected" => $selected,
        "menu_children" => $menu_children,
        "text" => stripslashes($row['text']),
        "is_parent" => $parent,
        "link" => $link,
      ];
      $items .= template_use("tmp/page.template", $params, "get_nav_item");
    }
  }

  if (is_logged_in() && is_siteadmin($USER->userid)) { // Members list visible only if logged in admin
    $members_modal = make_modal_links([
                        "title" => "Members List",
                        "path" => $CFG->wwwroot . "/pages/page.php?action=browse&amp;section=users&amp;userid=" . $USER->userid,
                        "iframe" => "true",
                        "width" => "640",
                        "height" => "623",
                        "confirmexit" => "true",
                      ]);
    $items .= template_use("tmp/page.template", ["members_modal" => $members_modal], "get_members_item");
  }

  if (!empty($items)) {
    return template_use("tmp/page.template", ["id" => "pagenav", "class" => "navtabs", "items" => $items], "make_ul");
  }
  return "";
}

function get_menu_children($menuid, $pageid) {
global $CFG;
  $SQL = template_use("dbsql/pages.sql", ["menuid" => $menuid], "get_menu_children");
  if ($result = get_db_result($SQL)) {
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
      $items .= template_use("tmp/page.template", $params, "get_nav_item");
    }
    return template_use("tmp/page.template", ["id" => "pagenavchild", "class" => "dropdown", "items" => $items], "make_ul");
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

    $pagenamebordercolor = isset($styles['pagenamebordercolor']) ? $styles['pagenamebordercolor'] : "";
    $pagenamebgcolor     = isset($styles['pagenamebgcolor']) ? $styles['pagenamebgcolor'] : "";
    $pagenamefontcolor   = isset($styles['pagenamefontcolor']) ? $styles['pagenamefontcolor'] : "";

    $params = [
      "pagenamebordercolor" => $pagenamebordercolor,
      "pagenamebgcolor" => $pagenamebgcolor,
      "pagenamefontcolor" => $pagenamefontcolor,
      "title" => stripslashes($title),
      "content" => $content,
      "buttons" => $buttons,
    ];
    $returnme = template_use("tmp/pagelib.template", $params, "get_css_box_template1");
  } else {
    if ($preview) {
      $styles = $STYLES->$feature;
    } else {
      $styles = get_styles($pageid, "$themeid", $feature, $featureid);
    }

    $contentbgcolor = isset($styles['contentbgcolor']) ? $styles['contentbgcolor'] : "";
    $bordercolor    = isset($styles['bordercolor']) ? $styles['bordercolor'] : "";
    $titlebgcolor   = isset($styles['titlebgcolor']) ? $styles['titlebgcolor'] : "";
    $titlefontcolor = isset($styles['titlefontcolor']) ? $styles['titlefontcolor'] : "";

    $bottom = "";
    if ($bottom_left || $bottom_center || $bottom_right) {
      $params = [
        "bottom_left" => $bottom_left,
        "bottom_right" => $bottom_right,
        "contentbgcolor" => $contentbgcolor,
      ];
      $returnme .= template_use("tmp/pagelib.template", $params, "get_css_box_bottom_template");
    }

    $opendiv = empty($feature) || $feature == 'pagelist' || $feature == 'addfeature' ? '' : 'class="box" id="' . $feature . '_' . $featureid . '"';
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
    $returnme .= template_use("tmp/pagelib.template", $params, "get_css_box_template2");
  }
  return $returnme;
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

  // Initialize return string
  $returnme = '<select ' . $properties . '>';

  // Get value and display names
  $valuename = $params["valuename"];
  $displayname = $params["displayname"] ?? $valuename;

  if (!empty($params["values"])) {
    // Add first option, if given
    if (isset($params["firstoption"])) {
      $returnme .= '<option value="">' . $params["firstoption"] . '</option>';
    }
  
    if (get_class($params["values"]) !== "stdClass") {
      // Database object.
      while ($row = fetch_row($params["values"])) {
        $options = [
          "value" => $row[$valuename],
          "valuename" => $valuename,
          "display" => $row[$displayname],
          "selected" => $params["selected"] ?? null,
          "exclude" => $params["exclude"] ?? null,
        ];
        $returnme .= make_options($options);
      }
    } else {
      // Standard object.
      foreach ($params["values"] as $value) {
        $options = [
          "value" => $value->$valuename,
          "valuename" => $valuename,
          "display" => $value->$displayname,
          "selected" => $params["selected"] ?? null,
          "exclude" => $params["exclude"] ?? null,
        ];
        $returnme .= make_options($options);
      }
    }
  } else {
    $returnme .= '<option value="">None</option>';
  }

  $returnme .= '</select>';
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

function create_new_page($page) {
global $CFG, $USER, $ROLES, $PAGE;
  $SQL = template_use("dbsql/pages.sql", ["page" => $page, "short_name" => strtolower(str_replace(" ", "", $page->name))], "create_page");
  $pageid = execute_db_sql($SQL);

  if ($pageid) {
    $SQL = template_use("dbsql/roles.sql", ["userid" => $USER->userid, "roleid" => $ROLES->creator, "pageid" => $pageid], "insert_role_assignment");
    $role_assignment = execute_db_sql($SQL);

    if ($newpage->menu_page == 1) {
      $sort = get_db_field("sort", "menus", "id > 0 ORDER BY sort DESC");
      $sort++;
      $SQL = template_use("dbsql/pages.sql", ["pageid" => $pageid, "text" => $page->name, "link" => $pageid, "sort" => $sort, "hidefromvisitors" => $page->hidefromvisitors], "add_page_menu");
      execute_db_sql($SQL);
    }

    if ($pageid && $role_assignment) {
      if (empty($PAGE)) {
        $PAGE = new \stdClass;
      }
      $PAGE->id = $pageid;
      //Log
      log_entry("page", $pageid, "Page Created");
      return json_encode(["true", $pageid, "Course Created"]);
    } else {
      if ($pageid) {
        delete_page($pageid);
      }
    }
  }
  return json_encode(["false", $CFG->SITEID, get_error_message("page_not_created")]);
}

function delete_page($pageid) {
  $SQL = template_use("dbsql/pages.sql", ["pageid" => $pageid], "delete_page");
  execute_db_sqls($SQL);
}

function subscribe_to_page($pageid, $userid = false, $addorremove = false) {
global $USER;
  $userid        = $userid ? $userid : $USER->userid;
  $defaultrole = get_db_field("default_role", "pages", "pageid='$pageid'");
  if (!$addorremove) {
    $SQL = template_use("dbsql/roles.sql", ["userid" => $userid, "roleid" => $defaultrole, "pageid" => $pageid], "insert_role_assignment");
    $role_assignment = execute_db_sql($SQL);
  } else {
    $SQL = template_use("dbsql/roles.sql", ["userid" => $userid, "pageid" => $pageid], "check_for_role_assignment");
    if (get_db_count($SQL)) { //role already exists
      $SQL = template_use("dbsql/roles.sql", ["userid" => $userid, "pageid" => $pageid], "remove_role_assignment");
      $role_assignment = execute_db_sql($SQL);
    } else {
      $SQL = template_use("dbsql/roles.sql", ["userid" => $user, "roleid" => $defaultrole, "pageid" => $pageid], "insert_role_assignment");
      $role_assignment = execute_db_sql($SQL);
    }
  }

  if ($pageid && $role_assignment) {
    return true;
  }
  return false;
}

function get_page_contents($pageid = false, $area = "middle") {
global $CFG, $PAGE;
  $returnme = '';

  if (!$pageid) {
    $pageid   = $CFG->SITEID;
    $PAGE->id = $pageid;
  } else {
    $PAGE->id = $pageid;
  }

  if ($area == "side") { //ADD pagelist to top of right side
    if (!isset($PAGELISTLIB)) {
      include_once($CFG->dirroot . '/lib/pagelistlib.php');
    }
    $returnme .= display_pagelist($pageid, $area);
  }

  $SQL = "SELECT * from pages_features WHERE pageid='$pageid' AND area='$area' ORDER BY sort";

  $returnme .= all_features_function($SQL, false, "display_", "", false, $pageid, $area, "#->featureid");

  if ($area == "side") { //ADD Add feature block to bottom of right side
    if (!isset($ADDFEATURELIB)) {
      include_once($CFG->dirroot . '/lib/addfeaturelib.php');
    }
    $returnme .= display_addfeature($pageid, $area);
  }

  return $returnme;
}

function get_login_form($loginonly = false, $newuser = true) {
global $CFG;
  if (!isset($VALIDATELIB)) {
    include_once($CFG->dirroot . '/lib/validatelib.php');
  }

  $newuserlink = $newuser ? make_modal_links([
                              "title" => "New User",
                              "path" => $CFG->wwwroot . "/pages/user.php?action=new_user",
                              "width" => "500",]
                            ) : '';

  $forgotpasswordlink = make_modal_links([
                          "title" => "Forgot password?",
                          "path" => $CFG->wwwroot . "/pages/user.php?action=forgot_password",
                          "width" => "500",
                        ]);

  $params = [
    "wwwroot" => $CFG->wwwroot,
    "directory" => get_directory(),
    "validation_script" => create_validation_script("login_form", "login($('#username').val(), $('#password').val());"),
    "valid_req_username" => get_error_message('valid_req_username'),
    "input_username" => get_help("input_username"),
    "valid_req_password" => get_error_message('valid_req_password'),
    "input_password2" => get_help("input_password2"),
    "newuserlink" => $newuserlink,
    "forgotpasswordlink" => $forgotpasswordlink,
  ];
  $content = template_use("tmp/pagelib.template", $params, "get_login_form_template");

  $returnme = $loginonly ? $content : get_css_box("Login", $content);
  return $returnme;
}

function add_page_feature($pageid, $featuretype) {
global $PAGE;
  if (empty($PAGE)) {
    $PAGE = new \stdClass;
  }
  $PAGE->id     = $pageid;
  $default_area = get_db_field("default_area", "features", "feature='$featuretype'");
  $sort         = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$default_area'") + 1;
  if (get_db_row("SELECT * FROM features WHERE feature='$featuretype' AND multiples_allowed='1'")) {
    $featureid = all_features_function(false, $featuretype, "insert_blank_", "", false, $pageid);
  } else {
    echo "INSERT INTO pages_features (pageid, feature, sort, area, featureid) VALUES('$pageid','$featuretype','$sort','$default_area', '')";
    $featureid = execute_db_sql("INSERT INTO pages_features (pageid, feature, sort, area) VALUES('$pageid','$featuretype','$sort','$default_area')");
    execute_db_sql("UPDATE pages_features SET featureid='$featureid' WHERE id='$featureid'");
  }

  //Log
  log_entry($featuretype, $featureid, "Added Feature");
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
      if (user_has_ability_in_page($USER->userid, "movefeatures", $pageid, $featuretype, $featureid)) {
        $returnme .= ' <a class="slide_menu_button pagesorthandle" href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;featureid=' . $featureid . '&amp;direction=drag\',function() { update_login_contents(' . $pageid . ');});"><img title="Move feature" src="' . $CFG->wwwroot . '/images/move.png" alt="Move feature" /></a> ';
      }

      //Role and Abilities Manger button
      if ($featureid && (user_has_ability_in_page($USER->userid, "edit_feature_abilities", $pageid, $featuretype, $featureid) || user_has_ability_in_page($USER->userid, "edit_feature_user_abilities", $pageid, $featuretype, $featureid) || user_has_ability_in_page($USER->userid, "edit_feature_group_abilities", $pageid, $featuretype, $featureid))) {
        $params = [
          "title" => "Roles & Abilities Manager",
          "path" => $CFG->wwwroot . "/pages/roles.php?action=manager&amp;feature=$featuretype&amp;pageid=$pageid&amp;featureid=$featureid",
          "iframe" => "true",
          "width" => "700",
          "height" => "580",
          "image" => $CFG->wwwroot . "/images/key.png",
          "class" => "slide_menu_button",
        ];
        $returnme .= make_modal_links($params);
      }

      //Feature Settings
      if (file_exists($CFG->dirroot . '/features/' . $featuretype . '/' . $featuretype . '.php')) {
        $_POST["aslib"] = true;
        $settings       = include_once($CFG->dirroot . '/features/' . $featuretype . '/' . $featuretype . '.php');

        //Settings link for all features
        if (function_exists($featuretype . '_settings') && user_has_ability_in_page($USER->userid, "editfeaturesettings", $pageid, $featuretype, $featureid)) {
          $params = [
            "title" => "Edit Settings",
            "path" => $CFG->wwwroot . "/features/$featuretype/$featuretype.php?action=" . $featuretype . "_settings&amp;pageid=$pageid&amp;featureid=$featureid",
            "width" => "640",
            "refresh" => "true",
            "image" => $CFG->wwwroot . "/images/settings.png",
            "class" => "slide_menu_button",
          ];
          $returnme .= make_modal_links($params);
        }
        $_POST["aslib"] = false;
      }

      //Remove feature button
      if (user_has_ability_in_page($USER->userid, "removefeatures", $pageid, $featuretype, $featureid)) {
        $returnme .= ' <a title="Delete" class="slide_menu_button" href="javascript: if (confirm(\'Are you sure you want to delete this?\')) { ajaxapi(\'/ajax/site_ajax.php\',\'delete_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;featureid=' . $featureid . '\',function() { update_login_contents(' . $pageid . ');});}"><img src="' . $CFG->wwwroot . '/images/delete.png" alt="Delete Feature" /></a> ';
      }
    }
  }
  return $returnme;
}

function get_button_layout($featuretype, $featureid = "", $pageid = 0) {
global $CFG, $PAGE;
  $returnme = "";
  if ($featuretype == 'pagename' || $featuretype == 'pagelist') {
    include_once($CFG->dirroot . '/lib/pagelistlib.php');
    $action          = $featuretype . "_buttons";
    $feature_buttons = function_exists($action) ? $action($pageid, $featuretype, $featureid) : "";
    $buttons         = $feature_buttons;
  } else {
    $feature         = str_replace("_features", "", $featuretype);
    $feature_buttons = all_features_function(false, $feature, "", "_buttons", false, $pageid, $featuretype, $featureid);
    $buttons         = $feature_buttons . get_edit_buttons($pageid, $featuretype, $featureid);
  }

  $themeid = get_page_themeid($PAGE->id);
  if (!$themeid && $pageid) {
    $themeid = get_page_themeid($pageid);
  }

  $styles = get_styles($pageid, $themeid, $featuretype, $featureid);
  $contentbgcolor = isset($styles['contentbgcolor']) ? $styles['contentbgcolor'] : "";
  $bordercolor    = isset($styles['bordercolor']) ? $styles['bordercolor'] : "";
  $titlebgcolor   = isset($styles['titlebgcolor']) ? $styles['titlebgcolor'] : "";
  $titlefontcolor = isset($styles['titlefontcolor']) ? $styles['titlefontcolor'] : "";

  if (strlen($buttons) > 0) {
    $params = [
      "bordercolor" => $bordercolor,
      "titlefontcolor" => $titlefontcolor,
      "titlebgcolor" => $titlebgcolor,
      "featuretype" => $featuretype,
      "featureid" => $featureid,
      "buttons" => $buttons,
    ];
    $returnme = template_use("tmp/pagelib.template", $params, "get_button_layout_template");
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

function make_search_box($contents = "", $name_addition = "") {
global $CFG;
  $params = [
    "name_addition" => $name_addition,
    "wwwroot" => $CFG->wwwroot,
    "contents" => $contents,
  ];
  return template_use("tmp/pagelib.template", $params, "make_search_box_template");
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
function format_popup(string $content = "", string $title = "", string $height = "", string $padding = "15px") {
  $params = [
    "padding" => $padding,
    "height" => $height,
    "title" => $title,
    "content" => $content,
  ];
  return template_use("tmp/pagelib.template", $params, "format_popup_template");
}

/**
 * Include a hidden iframe to keep the session alive
 *
 * @return string The HTML for the hidden iframe
 */
function keepalive() {
    global $CFG;

    // Parameters for the template
    $params = ["wwwroot" => $CFG->wwwroot];

    return template_use("tmp/pagelib.template", $params, "keepalive_template");
}

function donothing() {
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

?>
