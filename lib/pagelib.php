<?php
/***************************************************************************
* pagelib.php - Page function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 3.1.5
***************************************************************************/

if(!isset($LIBHEADER)){ include ('header.php'); }
$PAGELIB = true;

if(empty($PAGE)){ $PAGE = new stdClass(); }

function callfunction(){
global $CFG,$MYVARS;
    if(empty($_POST["aslib"])){
        //Retrieve from Javascript
        $postorget = isset($_POST["action"]) ? $_POST : false;
        if(empty($MYVARS)){ $MYVARS = new stdClass(); }
        $MYVARS->GET = !$postorget && isset($_GET["action"]) ? $_GET : $postorget;
        if(!empty($MYVARS->GET["i"])){ //universal javascript and css
            echo '
            <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'ajax/siteajax.js"></script>
            <script type="text/javascript"> var dirfromroot = "'.$CFG->directory.'"; </script>
            <link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/min/?f='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'styles/styles_main.css" />
            <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts&amp;f=jquery.min.js,jquery.extend.js,jquery.colorbox.js,jquery.colorbox.extend.js"></script>';
        }
        if(!empty($MYVARS->GET["v"])){ //validation javascript and css
            echo '
                <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts&f=jqvalidate.js,jqvalidate_addon.js" ></script>';
            unset($MYVARS->GET["v"]);
        }
        if(function_exists($MYVARS->GET["action"])){
        	$action = $MYVARS->GET["action"];
        	$action(); //Go to the function that was called.
        }else{ echo get_page_error_message("no_function",array($MYVARS->GET["action"])); }

    }
}

function postorget(){
global $MYVARS;
	//Retrieve from Javascript
	$postorget = isset($_GET["action"]) ? $_GET : $_POST;
	$postorget = isset($postorget["action"]) ? $postorget : "";
	$MYVARS->GET = $postorget;
	if($postorget != ""){
		return $postorget["action"];
	}
	return false;
}

function main_body($header_only = false){
global $CFG,$USER,$PAGE;
    return  '<div id="wholepage">
                <div class="logo_nav">'.page_masthead(true).'</div>
                <div class="colmask rightmenu">
    				<div class="colleft">
                        <div class="col2 pagesort2 connectedSortable">
                            '.page_masthead(false, $header_only).'
    					</div>
    					<div class="col1 pagesort1 connectedSortable">
        					<span id="column_width" style="width:100%;"></span>
    					</div>
    				</div>
     			</div>
            </div>
            <div style="clear:both;"></div>';
}

function page_masthead($left = true, $header_only = false){
global $CFG,$USER,$PAGE;
    if ($left) {
        $returnme = '   <header>
                            <div>
                                <a href="'.$CFG->wwwroot.'">
                                    <img id="logo" src="'.$CFG->wwwroot.'/images/'.$CFG->logofile.'" alt="'.$CFG->sitename.' Logo" style="max-width: 85vw;" />
                                </a>
                            </div>
                            <nav>
                            <img id="menu-icon" src="'.$CFG->wwwroot.'/styles/menu-icon.png" />
                                '.($header_only ? "" : get_nav_items($PAGE->id)).'
                            </nav>
                        </header>
                        ' . random_quote();
    } else {
        $returnme = (!$header_only ? (is_logged_in() ? print_logout_button($USER->fname, $USER->lname, $PAGE->id) : get_login_form()) : '');
    }

    return $returnme;
}

function get_editor_javascript(){
global $CFG;
    //return '<script type="text/javascript" src="'.$CFG->wwwroot.'/scripts/ckeditor/ckeditor.js"></script>';
    return '<script type="text/javascript" src="'.$CFG->wwwroot.'/scripts/tinymce/jquery.tinymce.min.js"></script>';
}

function get_editor_value_javascript($editorname="editor1"){
    return '$(\'#'.$editorname.'\').val()';
}

function get_editor_box($initialValue="",$name="editor1",$height="550",$width="100%",$type="HTML"){
global $CFG,$USER;
    return '<textarea id="editor1" name="editor1" class="wysiwyg_editor">'.$initialValue.'</textarea>
    <script type="text/javascript">
        $(window).load(function() {
            $(".wysiwyg_editor").tinymce({
                script_url : "'.$CFG->wwwroot.'/scripts/tinymce/tinymce.min.js",
                toolbar: "'.get_editor_toolbar($type).'",
                height: "'.$height.'",
                width: "'.$width.'",
                removed_menuitems: "newdocument",
                theme : "modern",
                convert_urls: false,
                plugins: [
                    '.get_editor_plugins($type).'
                ],
                external_filemanager_path: "'.(empty($CFG->directory) ? '' : '/'.$CFG->directory).'/scripts/tinymce/plugins/filemanager/",
                filemanager_title: "File Manager" ,
                external_plugins: { "filemanager" : "'.(empty($CFG->directory) ? '' : '/'.$CFG->directory).'/scripts/tinymce/plugins/filemanager/plugin.min.js"}
            });
        });
    </script>';
}

function get_editor_plugins($type){
    switch ($type){
    case "Default":
        $set = '"autolink image lists link responsivefilemanager charmap preview hr anchor pagebreak",
                "searchreplace wordcount visualblocks visualchars code fullscreen",
                "insertdatetime media nonbreaking paste table contextmenu directionality"';
        break;
    case "News":
        $set = '"autolink image lists link responsivefilemanager charmap preview hr anchor pagebreak",
                "searchreplace wordcount visualblocks visualchars code fullscreen",
                "insertdatetime media nonbreaking paste table contextmenu directionality"';
        break;
    case "HTML":
        $set = '"autolink image lists link responsivefilemanager charmap preview hr anchor pagebreak",
                "searchreplace wordcount visualblocks visualchars code fullscreen",
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

function get_editor_toolbar($type){
    switch ($type){
    case "Default":
        $set = "insertfile undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image";
        break;
    case "News":
        $set = "insertfile undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image";
        break;
    case "HTML":
        $set = "insertfile undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image";
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

function page_default_styles(){
	$styles_array[] = array("Page Name Border","pagenamebordercolor","#000000");
	$styles_array[] = array("Page Name Background","pagenamebgcolor","#FFFFFF");
	$styles_array[] = array("Page Name Text","pagenamefontcolor","#000000");
	$styles_array[] = array("Title Background","titlebgcolor","#FFFFFF");
	$styles_array[] = array("Title Text","titlefontcolor","#000000");
	$styles_array[] = array("Border","bordercolor","#000000");
	$styles_array[] = array("Content Background","contentbgcolor","#FFFFFF");
	return $styles_array;
}

function upgrade_check(){
global $CFG;
    //MAKE SURE INITIALIZED
    if(!get_db_row("SELECT * FROM pages WHERE pageid=1")){
        //INITIALIZE
        execute_db_sql("INSERT INTO pages (pageid,name,short_name,description,default_role,menu_page,opendoorpolicy,siteviewable,keywords) VALUES(1,'Home','home','home','4','0','1','1','home')");
        execute_db_sql("INSERT INTO users (userid,fname,lname,email,password,first_activity,last_activity,ip,temp,alternate,userkey,joined) VALUES(1,'Admin','User','admin@admin.com','".md5("admin")."','0','0','','','','".(md5("admin@admin.com").md5(time()))."','".get_timestamp()."')");
        execute_db_sql("INSERT INTO roles_assignment (assignmentid,userid,roleid,pageid,confirm) VALUES(1,1,1,1,0)");
    }

	//MAKING SURE LATEST SITE UPDATES ARE INSTALLED
	$featureid = false;
    if($settings = fetch_settings("site", $featureid)){
		$site_version = $settings->site->version->setting;
		include_once($CFG->dirroot . "/lib/db.php");
		site_upgrade();
	}

    //install new features if they don't exist'
    run_on_each_feature(NULL,"db",NULL,"_install");

    //run updates
    run_on_each_feature(NULL,"db",NULL,"_upgrade");
}

function run_on_each_feature($filename_ext="",$filename="",$function_pre="",$function_ext="",$var1="notgiven",$var2="notgiven",$var3="notgiven",$var4="notgiven"){
global $CFG;
	//Check for new features and feature updates
	$directory = $CFG->dirroot . "/features";
	if($handle = opendir($directory)){
	    /* This is the correct way to loop over the directory. */
	    while(false !== ($dir = readdir($handle))){
	        if(!strstr($dir,".") && is_dir($directory . "/" . $dir)){
			    if(!$filename){
			        include_once($directory . "/" . $dir . '/' . $dir . "$filename_ext.php");
			    }else{
			        include_once($directory . "/" . $dir . '/' . "$filename.php");
			    }
				$action = $function_pre . $dir . $function_ext;
				if(function_exists("$action")){
                    if($var1=="notgiven"){
                        $action();
    				}elseif($var2=="notgiven"){
    				    $action($var1);
    				}elseif($var3=="notgiven"){
    				    $action($var1,$var2);
    				}elseif($var4=="notgiven"){
    				    $action($var1,$var2,$var3);
    				}else{ $action($var1,$var2,$var3,$var4); }
                }
	        }
	    }
	    //close the directory handler
		closedir($handle);
	}
}

function move_page_feature($pageid, $featuretype, $featureid, $direction){
global $CFG, $USER, $PAGE;
	$PAGE->id = $pageid;
	$current_position = get_db_field("sort", "pages_features", "pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
	$area = get_db_field("area", "pages_features", "pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
	if($direction == 'up'){
		$up_position = $current_position - 1;
		execute_db_sql("UPDATE pages_features SET sort='$current_position' WHERE pageid='$pageid' AND area='$area' AND sort='$up_position'");
		execute_db_sql("UPDATE pages_features SET sort='$up_position' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
	}elseif ($direction == 'down'){
		$down_position = $current_position + 1;
		execute_db_sql("UPDATE pages_features SET sort='$current_position' WHERE pageid='$pageid' AND area='$area' AND sort='$down_position'");
		execute_db_sql("UPDATE pages_features SET sort='$down_position' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
	}elseif ($direction == 'middle'){
		execute_db_sql("UPDATE pages_features SET area='middle',sort='9999' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
		resort_page_features($pageid);
	}elseif ($direction == 'side'){
		execute_db_sql("UPDATE pages_features SET area='side',sort='9999' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
		resort_page_features($pageid);
	}elseif ($direction == 'locker'){
		execute_db_sql("UPDATE pages_features SET area='locker' WHERE pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
		resort_page_features($pageid);
	}
	//Log
	log_entry($featuretype, $featureid, "Move Feature");
}

function resort_page_features($pageid){
	//Middle first
	$i = 1;
	if($result = get_db_result("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='middle' ORDER BY sort")){
		while($row = fetch_row($result)){
			execute_db_sql("UPDATE pages_features SET sort='$i' WHERE id='" . $row['id'] . "'");
			$i++;
		}
	}
	//Side second
	$i = 1;
	if($result = get_db_result("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='side' ORDER BY sort")){
		while($row = fetch_row($result)){
			execute_db_sql("UPDATE pages_features SET sort='$i' WHERE id='" . $row['id'] . "'");
			$i++;
		}
	}
}

function make_modal_links($v){
global $CFG;
    $v["button"] = empty($v["button"]) ? "link" : "button";
    $v["title"] = empty($v["title"]) ? "" : $v["title"];
    $v["confirmexit"] = empty($v["confirmexit"]) ? "" : $v["confirmexit"];
    $v["id"] = empty($v["id"]) ? "" : 'id="'.$v["id"].'"';
    $v["type"] = empty($v["type"]) ? "" : 'type="'.$v["type"].'"';
    $gallery_name = empty($v["gallery"]) ? "" : $v["gallery"];
    $gallery = empty($v["gallery"]) ? "" : "('*[data-rel=\'$gallery_name\']')";
    $v["gallery"] = empty($v["gallery"]) ? "" : ",rel:'$gallery_name',photo:'true',preloading:'true'";
    $v["imagestyles"] = empty($v["imagestyles"]) ? "" : $v["imagestyles"];
    $v["image"] = empty($v["image"]) ? "" : '<img alt="'.$v["title"].'" title="'.$v["title"].'" src="'.$v["image"].'" style="'.$v["imagestyles"].'" />';
    $v["width"] = empty($v["width"]) ? (empty($v["gallery"]) ? "" : "") : ",width:'".$v["width"]."'";
    $v["height"] = empty($v["height"]) ? (empty($v["gallery"]) ? "" : "") : ",height:'".$v["height"]."'";
    $v["path"] = empty($v["path"]) ? "" : $v["path"];
    $v["class"] = empty($v["class"]) ? "" : $v["class"];
    $path = $v["path"] && $v["gallery"] ? $v["path"] : "javascript: void(0);";
    $v["text"] = empty($v["text"]) ? (empty($v["image"]) ? (empty($v["title"]) ? "" : $v["title"]) : $v["image"]) : (empty($v["image"]) ? $v["text"] : $v["image"]." ".$v["text"]);

    $iframe = empty($v["iframe"]) ? "" : ",fastIframe:true,iframe:true";
    $i = empty($v["iframe"]) ? "" : "&amp;i=!";
    $rand = '&amp;t='.get_timestamp();
    $v["styles"] = empty($v["styles"]) ? "" : $v["styles"];

    $v["refresh"] = empty($v["refresh"]) ? "" : $v["refresh"];
    $v["runafter"] = empty($v["runafter"]) ? "" : $v["runafter"];

    $modal = $onOpen = $onComplete = $valid = '';

    if(!empty($v["validate"]) && empty($v["iframe"])){ //load validation javascript
        $onOpen .= 'loadjs(\''.$CFG->wwwroot.'/min/?b=\' + (dirfromroot == \'\' ? \'\' : dirfromroot + \'/\') + \'scripts&f=jqvalidate.js,jqvalidate_addon.js\');';
    }elseif(!empty($v["validate"]) && !empty($v["iframe"])){
        $valid="&amp;v=!";
    }

    if(!empty($v["refresh"])){
        $modal .= '
        $.colorbox.close = function(){
            window.location.reload( true );
        };';
    }

    if(!empty($v["runafter"])){
        $modal .= '
        var originalClose = $.colorbox.close;
        $.colorbox.close = function(){';

        $modal .= empty($v["confirmexit"]) ? "" : 'if(confirm(\'Are you sure you wish to close this window?\')){';
        $modal .= 'eval(stripslashes(unescape(self.parent.$(\'#'.$v["runafter"].'\').val())));
                   setTimeout(function(){ originalClose(); $.colorbox.close = originalClose; },100);';
        $modal .= empty($v["confirmexit"]) ? "" : '}';
        $modal .= '};';
    }elseif(!empty($v["confirmexit"])){
        $modal .= '
        var originalClose = $.colorbox.close;
        $.colorbox.close = function(){
            if(confirm(\'Are you sure you wish to close this window?\')){
                originalClose(); $.colorbox.close = originalClose;
            }
        };';
    }

    if((empty($v["height"]) || empty($v["width"]))){
        if(empty($v["iframe"])){
            $onComplete = 'setTimeout(function(){ $.colorbox.resize(); },1500);';
        }else{
            $onComplete .= '
            setTimeout(function(){
                parent.$.colorbox.resize({
                    width:$(\'iframe[class=cboxIframe]\').contents().width()+50,
                    height:$(\'iframe[class=cboxIframe]\').contents().height()+75
                });
            },1500);';
        }
    }

    if($v["gallery"]){
        $modal .= '$'.$gallery.'.colorbox({maxWidth: \'95%\',maxHeight: \'95%\',fixed: true'.$v["width"].$v["height"].$v["gallery"].',speed:0});';
    }else{
        $modal .= '$.colorbox({maxWidth: \'98%\',maxHeight: \'98%\',fixed: true,onComplete:function(){ '.$onComplete.' $(\'#cboxTitle\').attr({\'style\': \'display: none\'}); },href:\''.$v["path"].$i.$valid.$rand.'\''.$v["width"].$v["height"].$v["gallery"].',speed:0'.$iframe.'});';
    }

    if(!empty($onOpen)){
        $modal = "setTimeout(function(){ $modal },500);";
    }

    if($v["button"] == "button") {
        return '<button '.trim($v["id"]).' class="smallbutton '.$v["class"].'" '.trim($v["type"]).' title="'.trim(strip_tags($v["title"])).'" style="'.$v["styles"].'" onclick="'.$onOpen.' '.$modal.'" />'.$v["text"].'</button>';
    } else {
        return '<a '.trim($v["id"]).' class="'.$v["class"].'" '.trim($v["type"]).' data-rel="'.$gallery_name.'" title="'.trim(strip_tags($v["title"])).'" style="'.$v["styles"].'" onclick="'.$onOpen.' '.$modal.'" href="'.$path.'">'.$v["text"].'</a>';
    }
}

function get_user_links($userid, $pageid){
global $CFG;
	$returnme = '<input type="hidden" id="loggedin" />';
    $alerts = false;
    $alerts = get_user_alerts($userid);
	if($alerts){
        $alerts_text = $alerts == 1 ? "Alert" : "Alerts";
        $returnme .=  '<span class="profile_links">'.make_modal_links(array("title"=> "Alerts","id"=>"alerts_link","text"=> "<span id=\"alerts_span\">$alerts $alerts_text</span>","path"=>$CFG->wwwroot."/pages/user.php?action=user_alerts&amp;userid=$userid","width"=>"600","height"=>"500","image"=>$CFG->wwwroot."/images/error.gif")) . '</span>';
	}
    $returnme .= '<input type="hidden" id="alerts" value="' . $alerts . '" />';
	return $returnme;
}

function is_opendoor_page($pageid){
	if(get_db_count("SELECT * FROM pages WHERE pageid='$pageid' AND opendoorpolicy=1")){ return true; }
	return false;
}

function is_visitor_allowed_page($pageid){
	if(get_db_count("SELECT * FROM pages WHERE pageid='$pageid' AND siteviewable=1")){ return true; }
	return false;
}

function all_features_function($SQL=false,$feature=false,$pre="",$post="",$count=false,$var1="#false#",$var2="#false#",$var3="#false#",$var4="#false#",$type_in_name = true){
global $CFG;
	$returnme = $count ? 0 : "";
	$t1 = $t2 = $t3 = $t4 = false;

	if($SQL !== false){
		if($features = get_db_result($SQL)){
			while($row = fetch_row($features)){
				//prepare variables
				if($var1 !== "#false#"){ $t1 = strstr($var1,'#->') ? str_replace("#->","",$var1) : $t1; if($t1){ $var1 = $row[$t1]; } }
				if($var2 !== "#false#"){ $t2 = strstr($var2,'#->') ? str_replace("#->","",$var2) : $t2; if($t2){ $var2 = $row[$t2]; } }
				if($var3 !== "#false#"){ $t3 = strstr($var3,'#->') ? str_replace("#->","",$var3) : $t3; if($t3){ $var3 = $row[$t3]; } }
				if($var4 !== "#false#"){ $t4 = strstr($var4,'#->') ? str_replace("#->","",$var4) : $t4; if($t4){ $var4 = $row[$t4]; } }

				$featuretype = $row['feature'];
				$featurelib = $featuretype . "lib.php";
				$libname = strtoupper($featuretype . "lib");
				if(!isset($$libname)){
				    if($featuretype == "pagelist"){
				        if(!isset($$libname)){ include_once($CFG->dirroot . "/lib/" . $featurelib); }
				    }else{
				        if(!isset($$libname)){ include_once($CFG->dirroot . "/features/$featuretype/" . $featurelib); }
				    }
                }
				$action = $type_in_name ? $pre . $featuretype . $post : $pre . $post;

				if(function_exists($action)){
					if($var4 !== "#false#"){ if($count){ $returnme += $action($var1, $var2, $var3, $var4); }else{ $returnme .= $action($var1, $var2, $var3, $var4); }
					}elseif($var3 !== "#false#"){ if($count){ $returnme += $action($var1, $var2, $var3); }else{ $returnme .= $action($var1, $var2, $var3); }
				 	}elseif($var2 !== "#false#"){ if($count){ $returnme += $action($var1, $var2); }else{ $returnme .= $action($var1, $var2); }
				 	}elseif($var1 !== "#false#"){ if($count){ $returnme += $action($var1); }else{ $returnme .= $action($var1); }
				 	}else{ if($count){ $returnme += $action(); }else{ $returnme .= $action(); } }
				}
			}
		}
	}elseif($feature !== false){
		$featurelib = $feature . "lib.php";
		$libname = strtoupper($feature . "lib");
        if($feature == "pagelist"){
	        if(!isset($$libname)){ include_once($CFG->dirroot . "/lib/" . $featurelib); }
	    }else{
            if(!isset($$libname)){ include_once ($CFG->dirroot . "/features/$feature/" . $featurelib); }
	    }

		$action = $type_in_name ? $pre . $feature . $post : $pre . $post;
		if(function_exists($action)){
			if($var4 !== "#false#"){ $returnme = $action($var1, $var2, $var3, $var4);
			}elseif($var3 !== "#false#"){ $returnme = $action($var1, $var2, $var3);
		 	}elseif($var2 !== "#false#"){ $returnme = $action($var1, $var2);
		 	}elseif($var1 !== "#false#"){ $returnme .= $action($var1);
		 	}else{ $returnme = $action(); }
		}
	}
	return $returnme;
}

function get_user_alerts($userid, $returncount = true, $internal = true){
	$returnme = all_features_function("SELECT * FROM features",false,"get_","_alerts",$returncount,$userid,$returncount);
	if(!$returncount){ $returnme = $returnme == "" ? '<span class="centered_span">There are no more alerts for you at this time.</span>' : $returnme; }

	if($internal){
		return $returnme;
	}else{
		echo $returnme;
	}
}

function print_logout_button($fname, $lname, $pageid = false){
global $CFG, $USER;
	if(empty($pageid)){ $pageid = $CFG->SITEID; }
    $edit = user_has_ability_in_page($USER->userid, "editprofile", $pageid) ? true : false;
    $param = array("title"       => "Edit Profile",
                   "text"        => "$fname $lname",
                   "path"        => $CFG->wwwroot."/pages/user.php?action=change_profile",
                   "validate"    => "true",
                   "width"       => "500",
                   "image"       => $CFG->wwwroot."/images/user.png",
                   "styles"      => "");
    $profile = $edit ? make_modal_links($param) : "$fname $lname";

    // Logged in as someone else.
    $logoutas = "";
    if (!empty($_SESSION["lia_original"])) {
        $lia_name = get_user_name($_SESSION["lia_original"]);
        $logoutas = '<a title="Switch back to: '.$lia_name.'" href="javascript: void(0)" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'logoutas\',\'\',function() { go_to_page('.$CFG->SITEID.');});">Switch back to: '.$lia_name.'</a><br />';
    }

    $logout = '<a title="Log Out" href="javascript: void(0)" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'get_login_box\',\'&amp;logout=1\',function() { clearInterval(myInterval); go_to_page('.$CFG->SITEID.');});">(Log Out)</a>';
    return  '<div id="login_box" class="login_box logout" style="text-align:right;">
                '.$logoutas.'
                <span style="display:inline-block;line-height: 18px;">
                    '.$profile.' '.$logout.'
                </span>' .
            '</div><br />' .
            get_user_links($USER->userid, $pageid);
}

function get_nav_items($pageid = false){
global $CFG, $USER, $PAGE;
    $pageid = !$pageid ? (empty($PAGE->id) ? $CFG->SITEID : $PAGE->id) : $pageid;

	//SQL Creation
	if (is_logged_in()) { $SQL = "SELECT * FROM menus WHERE parent IS NULL order by sort";
	} else { $SQL = "SELECT * FROM menus WHERE hidefromvisitors=0 AND parent IS NULL order by sort"; }

	$selected = $pageid == $CFG->SITEID ? ' class="selected"' : '';
	$returnme = '<ul id="pagenav" class="navtabs"><li><a href="' . $CFG->wwwroot . "/index.php?pageid=" . $CFG->SITEID . '" onmouseup="this.blur()" onfocus="this.blur()" ' . $selected . '><span>Home</span></a></li>';
	//Query the database
	if($result = get_db_result($SQL)){
		while($row = fetch_row($result)){
			$selected = $pageid == $row['pageid'] ? 'class="selected"' : '';
			$returnme .= '<li><a href="' . $CFG->wwwroot . "/index.php?pageid=" . $row['link'] . '" onmouseup="this.blur()" onfocus="this.blur()" ' . $selected . '><span>' . stripslashes($row['text']) . '</span></a>';
            $returnme .= get_menu_children($row["id"], $pageid);
            $returnme .= '</li>';
        }
	}

    if(is_logged_in()){ //Members list visible only if logged in
        $returnme .= '<li>'.make_modal_links(array("title"=> "Members List","path"=>$CFG->wwwroot."/pages/page.php?action=browse&amp;section=users&amp;userid=$USER->userid","iframe"=>"true","width"=>"640","height"=>"623","confirmexit"=>"true")).'</li>';
    }

	$returnme .= '</ul>';
	return $returnme;
}

function get_menu_children($menuid, $pageid) {
global $CFG;
    $returnme = '';
    $SQL = "SELECT * FROM menus WHERE parent='$menuid' order by sort";
    if($result = get_db_result($SQL)){
        $returnme .= '<ul class="dropdown">';
		while($row = fetch_row($result)){
			$selected = $pageid == $row['pageid'] ? 'class="selected"' : '';
			$returnme .= '<li><a href="' . $CFG->wwwroot . "/index.php?pageid=" . $row['link'] . '" onmouseup="this.blur()" onfocus="this.blur()" ' . $selected . '><span>' . stripslashes($row['text']) . '</span></a>';
            $returnme .= get_menu_children($row["id"], $pageid);
            $returnme .= '</li>';
        }
        $returnme .= '</ul>';
	}
    return $returnme;
}

function get_css_box($title, $content, $buttons = '', $padding = null, $feature = '', $featureid = '', $themeid = false, $preview = false, $pageid=false, $bottom_left = false, $bottom_center = false, $bottom_right = false, $class = ""){
global $CFG, $PAGE, $STYLES;
    $returnme = '';
	if($pageid === false){
		if($themeid === false){ $themeid = isset($PAGE->themeid) ? $PAGE->themeid : false;}
        if(isset($PAGE->id) && isset($CFG->SITEID) && $PAGE->id == $CFG->SITEID){
            $pageid = 0;
        }else{
            $pageid = isset($PAGE->id) ? $PAGE->id : 0;
        }
	}else{
		if($themeid === false){ $themeid = getpagetheme($pageid);}
	}

	if($feature == 'pagename'){
		if($preview){ $styles = $STYLES->pagename;
        }else{ $styles=get_styles($pageid,"$themeid","pagename",NULL); }

		$pagenamebordercolor = isset($styles['pagenamebordercolor']) ? $styles['pagenamebordercolor'] : "";
		$pagenamebgcolor = isset($styles['pagenamebgcolor']) ? $styles['pagenamebgcolor'] : "";
		$pagenamefontcolor = isset($styles['pagenamefontcolor']) ? $styles['pagenamefontcolor'] : "";

		$returnme = '
		<div class="pagename_box" style="border: 2px solid ' . $pagenamebordercolor . ';background-color:' . $pagenamebgcolor . ';">
            <div class="box_title" style="color:'.$pagenamefontcolor.';">
                <strong>' . stripslashes($title) . '</strong>
                <br />
        		<span style="color:'.$pagenamefontcolor.';font-size:.75em;">'.$content.'</span>
            </div>
                ' . $buttons . '
		</div>';
	}else{
		if($preview){ $styles = $STYLES->$feature;
		}else{ $styles = get_styles($pageid,"$themeid", $feature, $featureid); }

		$contentbgcolor = isset($styles['contentbgcolor']) ? $styles['contentbgcolor'] : "";
		$bordercolor = isset($styles['bordercolor']) ? $styles['bordercolor'] : "";
		$titlebgcolor = isset($styles['titlebgcolor']) ? $styles['titlebgcolor'] : "";
		$titlefontcolor = isset($styles['titlefontcolor']) ? $styles['titlefontcolor'] : "";

		$padding = isset($padding) ? ' padding:' . $padding . ';' : "";
		$bottom = $bottom_left || $bottom_center || $bottom_right ? '<div style="display:table;width:100%;' . 'background-color:'.$contentbgcolor.'"><div style="float:left;padding-left:2px;">' . $bottom_left . '</div><div style="text-align:center;position:relative;float:left;left:18%;width:60%">' . $bottom_center . '</div><div style="float:right;padding-right:2px;">' . $bottom_right . '</div></div>' : "";

        $returnme .= empty($feature) || $feature == 'pagelist' || $feature == 'addfeature' ? '<div>' : '<div class="box" id="'.$feature.'_'.$featureid.'">';
        $returnme .= '
    		<div class="box_header"  style="border: 2px solid ' . $bordercolor . ';background-color:' . $titlebgcolor . ';">
                <div class="box_title"  style="line-height:23px;color:'.$titlefontcolor.';">
                    ' . stripslashes($title) . '
                </div>
                    ' . $buttons . '
    		</div>
            <div class="box_content '.$class.'" style="border: 2px solid ' . $bordercolor . ';border-top: none;' . $padding . 'background-color:'.$contentbgcolor.';">
    		  ' . $content . '
              ' . $bottom . '
    		</div>
            <div style="padding:3px;"></div>';
        $returnme .= empty($feature) || $feature == 'pagelist' || $feature == 'addfeature' ? '</div>' : '</div>';
	}
	return $returnme;
}

function make_select($name, $values, $valuename, $displayname, $selected = false, $onchange = "", $leadingblank = false, $size=1, $style="", $leadingblanktitle="",$excludevalue=false){
    $returnme = '<select size="'.$size.'" id="' . $name . '" name="' . $name . '" ' . $onchange . ' style="'.$style.'" >';
	if($leadingblank){ $returnme .= '<option value="">'.$leadingblanktitle.'</option>'; }
	if($values){
		while($row = fetch_row($values)){
			$exclude = false;
            if($excludevalue){ //exclude value
                switch (gettype($excludevalue)) {
                    case "string":
                        $exclude = $excludevalue == $row[$valuename] ? true : false;
                        break;
                    case "array":
                        foreach($excludevalue as $e){
                            if($e == $row[$valuename]){
                                $exclude = true;
                            }
                        }
                        break;
                    case "object":
                        while($e = fetch_row($excludevalue)){
                            if($e[$valuename] == $row[$valuename]){

                                $exclude = true;
                            }
                        }

                        db_goto_row($excludevalue);
                        break;
                }
			}

			if(!$excludevalue || !$exclude){
				$returnme .= $row[$valuename] == $selected ? '<option value="' . $row[$valuename] . '" selected="selected">' . $row[$displayname] . '</option>' : '<option value="' . $row[$valuename] . '">' . $row[$displayname] . '</option>';
			}
		}
	}
	$returnme .= '</select>';
	return $returnme;
}

function make_select_from_array($name, $values, $valuename, $displayname, $selected = false, $width = "", $onchange = "", $leadingblank = false, $size=1, $style="",$leadingblanktitle="",$excludevalue=false){
	$returnme = '<select size="'.$size.'" id="' . $name . '" name="' . $name . '" ' . $onchange . ' ' . $width . ' style="'.$style.'">';
	if($leadingblank){ $returnme .= '<option value="">'.$leadingblanktitle.'</option>';}
	foreach($values as $value){
		$exclude = false;
        if($excludevalue){ //exclude value
            switch (gettype($excludevalue)) {
                case "string":
                    $exclude = $excludevalue == $value->$valuename ? true : false;
                    break;
                case "array":
                    foreach($excludevalue as $e){
                        if($e == $value->$valuename){
                            $exclude = true;
                        }
                    }
                    break;
                case "object":
                    while($e = fetch_row($excludevalue)){
                        if($e[$valuename] == $value->$valuename){
                            $exclude = true;
                        }
                    }

                    db_goto_row($excludevalue);
                    break;
            }
		}
		if(!$excludevalue || !$exclude){
			$returnme .= $value->$valuename == $selected ? '<option value="' . $value->$valuename . '" selected="selected">' . $value->$displayname . '</option>' : '<option value="' . $value->$valuename . '">' . $value->$displayname . '</option>';
		}
	}

	$returnme .= '</select>';
	return $returnme;
}

function sort_object($object, $value, $sorttype = SORT_REGULAR){
	$i = 0;
	while(isset($object->$i)){
		$array[$i] = $object->$i->$value;
		$i++;
	}

	sort($array, $sorttype);

	$i=0;
	while(isset($object->$i)){
		$z=0;
		while(isset($object->$z) && $object->$z->$value != $array[$i]){
			$z++;
		}

		$newobject->$i = $object->$z;
		$i++;
	}

	return $newobject;
}

function create_new_page($newpage){
global $CFG, $USER, $ROLES, $PAGE;
	$shortname = strtolower(str_replace(" ", "", $newpage->name));

    $pageid = execute_db_sql("INSERT INTO pages (name,short_name,description,keywords,default_role,opendoorpolicy,siteviewable,menu_page) VALUES('" . $newpage->name . "','$shortname','" . $newpage->description . "','" . $newpage->keywords . "','" . $newpage->defaultrole . "','" . $newpage->opendoor . "','" . $newpage->siteviewable . "','" . $newpage->menu_page . "')");
	$role_assignment = execute_db_sql("INSERT INTO roles_assignment (userid,roleid,pageid) VALUES('" . $USER->userid . "','" . $ROLES->creator . "','$pageid')");

    if($newpage->menu_page == 1){
		$sort = get_db_field("sort", "menus", "id > 0 ORDER BY sort DESC");
		$sort++;
		execute_db_sql("INSERT INTO menus (pageid,text,link,sort,hidefromvisitors) VALUES('$pageid','" . $newpage->name . "','$pageid','$sort','" . $newpage->hidefromvisitors . "')");
	}

    if($pageid && $role_assignment){
        if(empty($PAGE)){ $PAGE = new stdClass(); }
		$PAGE->id = $pageid;
		//Log
		log_entry("page", $pageid, "Page Created");
		//create_rss($pageid);
		return "true**$pageid";
	}else{
		if($userid){
			execute_db_sql("DELETE FROM pages WHERE pageid='$pageid'");
			execute_db_sql("DELETE FROM roles_assignment WHERE pageid='$pageid'");
		}
		return "false**" . get_error_message("page_not_created");
	}
}

function subscribe_to_page($pageid, $userid = false, $addorremove = false){
global $CFG, $USER, $ROLES;
	$user = $userid ? $userid : $USER->userid;
	$defaultrole = get_db_field("default_role", "pages", "pageid='$pageid'");
	if(!$addorremove) {
		$role_assignment = execute_db_sql("INSERT INTO roles_assignment (userid,roleid,pageid) VALUES('" . $user . "','" . $defaultrole . "','$pageid')");
	}else{
		if(get_db_count("SELECT * FROM roles_assignment WHERE pageid=$pageid AND userid=$user AND confirm=0")){ //role already exists
			$role_assignment = execute_db_sql("DELETE FROM roles_assignment WHERE pageid=$pageid AND userid=$user");
			return false;
		}else{
			$role_assignment = execute_db_sql("INSERT INTO roles_assignment (userid,roleid,pageid) VALUES('" . $user . "','" . $defaultrole . "','$pageid')");
			return true;
		}
	}

	if($pageid && $role_assignment) {
		return "true**$pageid";
	}else{
		return "false**" . get_error_message("could_not_subscribe");
	}
}

function get_page_contents($pageid = false, $area){
global $CFG, $USER, $PAGE;
	$returnme = '';

	if(!$pageid){
		$pageid = $CFG->SITEID;
		$PAGE->id = $pageid;
	}else{ $PAGE->id = $pageid; }

	if($area == "side"){ //ADD pagelist to top of right side
		if(!isset($PAGELISTLIB)){ include_once ($CFG->dirroot . '/lib/pagelistlib.php'); }
		$returnme .= display_pagelist($pageid, $area);
	}

	$SQL = "SELECT * from pages_features WHERE pageid='$pageid' AND area='$area' ORDER BY sort";

	$returnme .= all_features_function($SQL,false,"display_","",false,$pageid,$area,"#->featureid");

	if($area == "side"){ //ADD Add feature block to bottom of right side
		if (!isset($ADDFEATURELIB)) include_once ($CFG->dirroot . '/lib/addfeaturelib.php');
		$returnme .= display_addfeature($pageid, $area);
	}

	return $returnme;
}

function get_login_form($loginonly = false, $newuser = true){
global $USER,$CFG;
	if(!isset($VALIDATELIB)){ include_once($CFG->dirroot . '/lib/validatelib.php'); }
	$title = "Login";
	$content = '
	 <script type="text/javascript" src="'.$CFG->wwwroot.'/min/?b='.(empty($CFG->directory) ? '' : $CFG->directory . '/').'scripts&amp;f=jqvalidate.js,jqvalidate_addon.js"></script>
     '.create_validation_script("login_form" , "login(document.getElementById('username').value,document.getElementById('password').value);").'
	 <form id="login_form">
		 <fieldset>
				<div class="rowContainer">
					<label class="rowTitle" for="username">Username</label>
					<input tabindex=1 style="margin-right:0px;width:80%" type="email" id="username" name="username" data-rule-required="true" data-msg-required="'.get_error_message('valid_req_username').'" /><div class="tooltipContainer info">'.get_help("input_username").'</div>
                    <div class="spacer" style="clear: both;"></div>
				</div>
				<div class="rowContainer">
			  		<label class="rowTitle" for="password">Password</label>
				    <input tabindex=2 style="margin-right:0px;width:80%" type="password" id="password" name="password" data-rule-required="true" data-msg-required="'.get_error_message('valid_req_password').'" /><div class="tooltipContainer info">'.get_help("input_password2").'</div>
		            <div class="spacer" style="clear: both;"></div>
                </div>
		</fieldset>
        <input name="submit" type="submit" value="Sign In" style="margin-left:5px;" />
	<span style="float:right;font-size:.9em">';
        $content .= $newuser ? make_modal_links(array("title"=> "New User","path"=>$CFG->wwwroot."/pages/user.php?action=new_user","width"=>"500")).'<br />' : '';
        $content .= make_modal_links(array("title"=> "Forgot password?","path"=>$CFG->wwwroot."/pages/user.php?action=forgot_password","width"=>"500")).'
	</span>
	</form>
	<div id="login_box_error" class="error_text"></div>';

	$returnme = $loginonly ? $content : get_css_box($title, $content);
	return $returnme;
}

function add_page_feature($pageid, $featuretype){
global $CFG, $PAGE;
    if(empty($PAGE)){ $PAGE = new stdClass(); }
	$PAGE->id = $pageid;
	$default_area = get_db_field("default_area", "features", "feature='$featuretype'");
	$sort = get_db_count("SELECT * FROM pages_features WHERE pageid='$pageid' AND area='$default_area'") + 1;
	if(get_db_row("SELECT * FROM features WHERE feature='$featuretype' AND multiples_allowed='1'")){
		$featureid = all_features_function(false,$featuretype,"insert_blank_","",false,$pageid);
	}else{
	    echo "INSERT INTO pages_features (pageid, feature, sort, area, featureid) VALUES('$pageid','$featuretype','$sort','$default_area','')";
		$featureid = execute_db_sql("INSERT INTO pages_features (pageid, feature, sort, area) VALUES('$pageid','$featuretype','$sort','$default_area')");
		execute_db_sql("UPDATE pages_features SET featureid='$featureid' WHERE id='$featureid'");
	}

	//Log
	log_entry($featuretype, $featureid, "Added Feature");
}

function get_edit_buttons($pageid, $featuretype, $featureid = false){
	global $CFG, $USER;
	$returnme = "";
    $is_feature_menu = true; //Assume it is a main feature block button menu
	//User must be logged in
	if(is_logged_in()){
		//Is this a feature with sections?
		//If is it a feature with sections...get the correct feature id.  If it is a normal feature, set is_section to true
        if(!strstr($featuretype,"_features")){
            $subset = get_db_row("SHOW TABLE STATUS LIKE '$featuretype" . "_features'");
            $is_feature_menu = $featuretype == "pagename" || empty($subset) ? true : false;
        }else{
            $featuretype = str_replace("_features", "", $featuretype);
        }

		//If this is a section
		if($is_feature_menu){

            //Move block buttons
			if(user_has_ability_in_page($USER->userid, "movefeatures", $pageid,$featuretype,$featureid)){
                $returnme .= ' <a class="slide_menu_button pagesorthandle" href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;featureid=' . $featureid . '&amp;direction=drag\',function() { update_login_contents(' . $pageid . ');});"><img title="Move feature" src="' . $CFG->wwwroot . '/images/move.png" alt="Move feature" /></a> ';
//
//                if($featureid){
//    				//Find out which directions the feature can be moved.
//    				$area = get_db_field("area", "pages_features", "pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid'");
//    				$sort = get_db_field("sort", "pages_features", "pageid='$pageid' AND feature='$featuretype' AND featureid='$featureid' AND area='$area'");
//    				$above = get_db_field("feature", "pages_features", "pageid='$pageid' AND area='$area' AND sort < '$sort'");
//    				$below = get_db_field("feature", "pages_features", "pageid='$pageid' AND area='$area' AND sort > '$sort'");
//                }else{
//    				//Find out which directions the feature can be moved.
//    				$area = get_db_field("area", "pages_features", "pageid='$pageid' AND feature='$featuretype'");
//    				$sort = get_db_field("sort", "pages_features", "pageid='$pageid' AND feature='$featuretype' AND area='$area'");
//    				$above = get_db_field("feature", "pages_features", "pageid='$pageid' AND area='$area' AND sort < '$sort'");
//    				$below = get_db_field("feature", "pages_features", "pageid='$pageid' AND area='$area' AND sort > '$sort'");
//    			}
//				if($above){ $returnme .= ' <a class="slide_menu_button" href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;featureid=' . $featureid . '&amp;direction=up\',function() { update_login_contents(' . $pageid . ');});"><img title="Move Up" src="' . $CFG->wwwroot . '/images/up.png" alt="Move feature up" /></a> ';}
//				if($below){ $returnme .= ' <a class="slide_menu_button" href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;featureid=' . $featureid . '&amp;direction=down\',function() { update_login_contents(' . $pageid . ');});"><img title="Move Down" src="' . $CFG->wwwroot . '/images/down.png" alt="Move feature down" /></a> ';}
//				if($area == "middle"){
//				    $returnme .= ' <a class="slide_menu_button" href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;featureid=' . $featureid . '&amp;direction=side\',function() { update_login_contents(' . $pageid . ');});"><img title="Send to side" src="' . $CFG->wwwroot . '/images/right.png" alt="Move feature to the side area" /></a> ';
//				}elseif($area == "side"){
//				    $returnme .= ' <a class="slide_menu_button" href="javascript: void(0);" onclick="ajaxapi(\'/ajax/site_ajax.php\',\'move_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;featureid=' . $featureid . '&amp;direction=middle\',function() { update_login_contents(' . $pageid . ');});"><img title="Send to middle" src="' . $CFG->wwwroot . '/images/left.png" alt="Move feature to the middle area" /></a> ';
//                }
            }

            //Role and Abilities Manger button
            if($featureid && (user_has_ability_in_page($USER->userid,"edit_feature_abilities",$pageid,$featuretype,$featureid) || user_has_ability_in_page($USER->userid,"edit_feature_user_abilities",$pageid,$featuretype,$featureid) || user_has_ability_in_page($USER->userid,"edit_feature_group_abilities",$pageid,$featuretype,$featureid))){
                $returnme .= make_modal_links(array("title"=> "Roles & Abilities Manager","path"=>$CFG->wwwroot."/pages/roles.php?action=manager&amp;feature=$featuretype&amp;pageid=$pageid&amp;featureid=$featureid","iframe"=>"true","width"=>"700","height"=>"580","image"=>$CFG->wwwroot."/images/key.png","class"=>"slide_menu_button"));
            }

            //Feature Settings
            if(file_exists($CFG->dirroot . '/features/'.$featuretype.'/'.$featuretype.'.php')){
                $_POST["aslib"] = true;
                $settings = include_once($CFG->dirroot . '/features/'.$featuretype.'/'.$featuretype.'.php');

                //Settings link for all features
                if(function_exists($featuretype.'_settings') && user_has_ability_in_page($USER->userid,"editfeaturesettings",$pageid,$featuretype,$featureid)){
                    $returnme .= make_modal_links(array("title"=>"Edit Settings","path"=>$CFG->wwwroot."/features/$featuretype/$featuretype.php?action=".$featuretype."_settings&amp;pageid=$pageid&amp;featureid=$featureid","width"=>"640","refresh"=>"true","image"=>$CFG->wwwroot."/images/settings.png","class"=>"slide_menu_button"));
                }
                $_POST["aslib"] = false;
            }

            //Remove feature button
            if(user_has_ability_in_page($USER->userid, "removefeatures", $pageid,$featuretype,$featureid)){ $returnme .= ' <a title="Delete" class="slide_menu_button" href="javascript: if(confirm(\'Are you sure you want to delete this?\')){ ajaxapi(\'/ajax/site_ajax.php\',\'delete_feature\',\'&amp;pageid=' . $pageid . '&amp;featuretype=' . $featuretype . '&amp;sectionid=&amp;featureid=' . $featureid . '\',function() { update_login_contents(' . $pageid . ');});}"><img src="' . $CFG->wwwroot . '/images/delete.png" alt="Delete Feature" /></a> ';}
		}
	}
    return $returnme;
}

function get_button_layout($featuretype, $featureid = "", $pageid){
global $CFG, $PAGE;
	if($featuretype == 'pagename' || $featuretype == 'pagelist'){
		include_once ($CFG->dirroot . '/lib/pagelistlib.php');
		$action = $featuretype . "_buttons";
		$feature_buttons = function_exists($action) ? $action($pageid, $featuretype, $featureid) : "";
		$buttons = $feature_buttons;
	}else{
		$feature = str_replace("_features", "", $featuretype);
		$feature_buttons = all_features_function(false,$feature,"","_buttons",false,$pageid,$featuretype,$featureid);
		$buttons = $feature_buttons . get_edit_buttons($pageid, $featuretype, $featureid);
	}

	$themeid = getpagetheme($PAGE->id);
    if(!$themeid && $pageid){
        $themeid = getpagetheme($pageid);
    }
    $styles = get_styles($pageid,$themeid,$featuretype,$featureid);

    $contentbgcolor = isset($styles['contentbgcolor']) ? $styles['contentbgcolor'] : "";
	$bordercolor = isset($styles['bordercolor']) ? $styles['bordercolor'] : "";
	$titlebgcolor = isset($styles['titlebgcolor']) ? $styles['titlebgcolor'] : "";
	$titlefontcolor = isset($styles['titlefontcolor']) ? $styles['titlefontcolor'] : "";

	if(strlen($buttons) > 0){
	   return '
        <div id="slide_menu" class="slide_menu_invisible slide_menu" style="border-top:1px solid '.$bordercolor.';border-bottom:1px solid '.$bordercolor.';">
        <div id="' . $featuretype . '_' . $featureid . '_buttons" style="padding:0;">
		  ' . $buttons . '
		</div>
        </div>
        <div onclick="$(this).prev(\'#slide_menu\').animate({width: \'toggle\'},function(){$(this).toggleClass(\'slide_menu_visible\');});" class="slide_menu slide_menu_tab" style="background-color:'.$titlefontcolor.';color:'.$titlebgcolor.';border-left:1px solid '.$bordercolor.';border-top:1px solid '.$bordercolor.';border-bottom:1px solid '.$bordercolor.';"><strong>+</strong></div>
        <div style="clear:both"></div>';
	}

    return "";
}

//DOES NOTHING!!!
function page_buttons($pageid, $featuretype, $featureid){
global $CFG, $USER;
	$returnme = "";
	return $returnme;
}

function get_search_page_variables($total, $perpage, $pagenum){
	$array['firstonpage'] = $perpage * $pagenum;
	$array['count'] = $total > (($pagenum+1) * $perpage) ? $perpage : $total - (($pagenum) * $perpage);
	$array['amountshown'] = $array['firstonpage'] + $perpage < $total ? $array['firstonpage'] + $perpage : $total;
	$array['prev'] = $pagenum > 0 ? true : false;
	$array['info'] = 'Viewing ' . ($array['firstonpage'] + 1) . " through " . $array['amountshown'] . " out of $total";
	$array['next'] = $array['firstonpage'] + $perpage < $total ? true : false;
	return $array;
}

function make_search_box($contents="",$name_addition=""){
global $CFG;
	$returnme = '
		<div style="position:relative;width:95%;margin-right:auto;margin-left:auto;">
		<div id="loading_overlay_'.$name_addition.'" style="text-align:center;position:absolute;width:100%;height:99%;vertical-align:middle;background-color:white;opacity:.6;visibility:hidden;"><img src="' . $CFG->wwwroot . '/images/loading_large.gif" alt="loading image" /></div>
		<div id="searchcontainer_'.$name_addition.'" style="padding:5px; display:block; width:99%;">'.$contents.'</div></div>';
	return $returnme;
}

function format_popup($content="",$title="",$height="",$padding="25px"){
global $CFG;
    return '<div style="padding:'.$padding.';border:1px solid silver;border-radius: 5px;height:'.$height.';">
                <h3>'.$title.'</h3>'.$content.'
            </div>';
}

function keepalive() {
global $CFG;
    return '<iframe style="display:none;" src="'.$CFG->wwwroot.'/index.php?keepalive=true"></iframe>';
}
?>