<?php
/***************************************************************************
* pics_ajax.php - Pics feature ajax backend
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.7.4
***************************************************************************/
if (!isset($CFG)) {
	$sub = '';
	while (!file_exists($sub . 'header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'header.php');
}

if (!defined('PICSLIB')) include_once ($CFG->dirroot . '/features/pics/picslib.php');

update_user_cookie();

callfunction();

function pics_pageturn() {
global $CFG, $MYVARS;
	$galleryid = !isset($MYVARS->GET["galleryid"]) ? NULL : $MYVARS->GET["galleryid"];
	$pagenum = !isset($MYVARS->GET["pagenum"]) ? NULL : $MYVARS->GET["pagenum"];
	$editable = !isset($MYVARS->GET["editable"]) ? 'false' : $MYVARS->GET["editable"];
	$perpage = !isset($MYVARS->GET["perpage"]) ? NULL : $MYVARS->GET["perpage"];
	$order = !isset($MYVARS->GET["order"]) ? NULL : urldecode($MYVARS->GET["order"]);
	
	echo get_pics($MYVARS->GET["pageid"], $MYVARS->GET["featureid"], $galleryid, $pagenum, $editable, $perpage);
}

function new_gallery() {
global $CFG, $MYVARS;
	$newgallery = clean_myvar_req("param", "bool");
	$pageid = clean_myvar_req("pageid", "int");
	if ($newgallery) {
		echo '<input name="gallery_name" id="gallery_name" type="text" size="32" onkeypress="return handleEnter(this, event);" />';
	} else {
        $p = [
            "properties" => [
                "name" => "galleryid",
                "id" => "galleryid",
            ],
            "values" => get_db_result(use_template("dbsql/pics.sql", ["pageid" => $pageid], "get_page_galleries", "pics")),
            "valuename" => "galleryid",
            "displayname" => "name",
            "firstoption" => "None selected",
        ];
		echo make_select($p);
	}
}

function move_pic() {
global $CFG, $MYVARS;
	$picsid = $MYVARS->GET['picsid']; $galleryid = $MYVARS->GET['galleryid'];
	execute_db_sql("UPDATE pics SET galleryid='$galleryid' WHERE picsid='$picsid'");
	echo 'Done';
}

function save_caption() {
global $CFG, $MYVARS;
	$picsid = $MYVARS->GET['picsid']; $caption = addslashes(urldecode($MYVARS->GET['caption']));
	execute_db_sql("UPDATE pics SET caption='$caption' WHERE picsid='$picsid'");
	echo 'Saved';
}

function save_viewability() {
global $CFG, $MYVARS;
	$picsid = $MYVARS->GET['picsid']; $siteviewable = $MYVARS->GET['siteviewable'] == "true" ? 1 : 0;
	execute_db_sql("UPDATE pics SET siteviewable='$siteviewable' WHERE picsid='$picsid'");
	echo 'Saved';
}

function delete_pic() {
global $CFG, $MYVARS;
    $picsid = $MYVARS->GET['picsid'];
    $row = get_db_row("SELECT * FROM pics WHERE picsid='$picsid'");
    delete_file($CFG->dirroot . '/features/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row["imagename"]);
    execute_db_sql("DELETE FROM pics WHERE picsid='$picsid'");
    echo '<font style="font-color:gray">Picture Deleted</font>';
}

function delete_gallery() {
global $CFG, $MYVARS;
    $galleryid = $MYVARS->GET['galleryid'];
    $pageid = clean_myvar_req("pageid", "int");
    $featureid = clean_myvar_req("featureid", "int");

    $delete = $copy = false;
    if (!empty($galleryid) && !empty($pageid) && !empty($featureid)) {
        if ($result = get_db_result("SELECT * FROM pics WHERE galleryid='$galleryid'")) {
            while ($row = fetch_row($result)) {
                if ($pageid != $CFG->SITEID && !empty($row["siteviewable"])) { //siteviewable images from a page other than SITE.  Move them to site
                    $copy = true;
                    $site_featureid = get_db_field("featureid", "pages_features", "feature='pics' AND pageid='" . $CFG->SITEID."'");
                    $old = $CFG->dirroot . '/features/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row["imagename"];
                    $new = $CFG->dirroot . '/features/pics/files/' . $CFG->SITEID. "/" . $site_featureid. "/" . $row["imagename"];
                    copy_file($old, $new);
                    delete_file($old);
                } elseif ($pageid == $CFG->SITEID && $pageid != $row["pageid"]) {  //SITE is dealing with images from another page
                    execute_db_sql("UPDATE pics SET siteviewable=0 WHERE galleryid='$galleryid'");
                } else { //nobody is using it, so delete it
                    $delete = true;
                    delete_file($CFG->dirroot . '/features/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row["imagename"]);
                    execute_db_sql("DELETE FROM pics WHERE picsid='" . $row["picsid"] . "'");    
                }
            }
        }
        
        if ($copy) {
            $SQL = "UPDATE pics SET pageid='$CFG->SITEID',featureid='$site_featureid' WHERE galleryid='$galleryid'";
            execute_db_sql($SQL);
            $SQL = "UPDATE pics_galleries SET pageid='$CFG->SITEID',featureid='$site_featureid' WHERE galleryid='$galleryid'";
            execute_db_sql($SQL);
        }
        
        if ($delete) {
            execute_db_sql("DELETE FROM pics_galleries WHERE galleryid='$galleryid'");
        }         
    }
    
    echo get_pics_manager($pageid, $featureid);
}

function pics_upload() {
global $CFG, $MYVARS;
    $newgallery = clean_myvar_opt("new_gallery", "bool", false);
    $pageid = clean_myvar_req("pageid", "int");
    $featureid = clean_myvar_req("featureid", "int");

    try {
        start_db_transaction();
        //must have a featureid and pageid
        if (!empty($featureid) && !empty($pageid)) {
            //upload directory.
            $upload_dir = 'files/' . "$pageid/$featureid/";
            
            //Make sure that upload directory exists
            recursive_mkdir($upload_dir);
            
            //the file size in bytes.
            $max_upload_bytes = return_bytes(ini_get('upload_max_filesize')); //Gets max upload filesize from server.
            $max_post_bytes = return_bytes(ini_get('upload_max_filesize')); //Gets max post from server.
            $size_bytes = $max_upload_bytes < $max_post_bytes ? $max_upload_bytes : $max_post_bytes; //use the smaller of the two
            
            //Extensions you want files uploaded limited to.
            $limitedext = [".gif", ".jpg", ".jpeg", ".png", ".bmp"];
            
            //check if the directory exists or not.
            if (!is_dir("$upload_dir")) {
                die ("Error: The directory <strong>($upload_dir)</strong> doesn't exist");
            }
            //check if the directory is writable.
            if (!is_writeable("$upload_dir")) {
                die("Error: The directory <strong>($upload_dir)</strong> is NOT writable, Please CHMOD (777)");
            }
            
            //if the form has been submitted, then do the upload process
            $filenames = explode("**", clean_myvar_req("filenames", "string"));
            $file_count = count($filenames);
            
            //do a loop for uploading files based on ($file_count) number of files.
            $i = $success = 0;
            $galleryid = clean_myvar_opt("galleryid", "int", false);
            $files = $_FILES["files"];

            while (isset($filenames[$i]) && isset($files["name"][$i])) {
                $file_name = $files["name"][$i];
                //to remove spaces from file name we have to replace it with "_".
                $file_name = str_replace(' ', '_', $file_name);
                $file_tmp = $files['tmp_name'][$i];
                $file_size = $files['size'][$i];
                #-----------------------------------------------------------#
                # this code will check if the files were selected or not.    #
                #-----------------------------------------------------------#
                if (!is_uploaded_file($file_tmp)) {
                    //print error message and file number.
                    echo "Skipping file ($file_name) Not selected. <br />";
                } else {
                    #-----------------------------------------------------------#
                    # this code will check file extension                       #
                    #-----------------------------------------------------------#
                    $ext = strrchr($file_name, '.');
                    if (!in_array(strtolower($ext), $limitedext)) {
                        echo "Skipping file ($file_name) Incompatible file extension. <br />";
                    } else {
                        #-----------------------------------------------------------#
                        # this code will check file size is correct                 #
                        #-----------------------------------------------------------#
                        if ($file_size > $size_bytes) {
                            echo "Skipping file ($file_name) File must be less than <strong>" . $size_bytes / 1024 . "</strong> KB. <br />";
                        } else {
                            #-----------------------------------------------------------#
                            # this code check if file is Already EXISTS.                #
                            #-----------------------------------------------------------#
                            if (file_exists($upload_dir.$file_name)) {
                                echo "Skipping file ($file_name) File already exists. <br />";
                            } else {
                                #-----------------------------------------------------------#
                                # this function will upload the files.  :) ;) cool          #
                                #-----------------------------------------------------------#
                                if (move_uploaded_file($file_tmp, $upload_dir.$file_name)) {
                                    $dateadded = get_timestamp();
                                    if ($newgallery && !$galleryid) {
                                        $gallery_name = clean_myvar_req("gallery_name", "string");
                                        $SQL = fetch_template("dbsql/pics.sql", "insert_gallery", "pics");
                                        $galleryid = execute_db_sql($SQL, ["pageid" => $pageid, "featureid" => $featureid, "gallery_name" => $gallery_name]);
                                    } elseif (!$newgallery && $galleryid) {
                                        $gallery_name = get_db_field("name", "pics_galleries", "galleryid = ||galleryid||", ["galleryid" => $galleryid]);
                                    }
                                    $params = [
                                        "pageid" => $pageid,
                                        "featureid" => $featureid,
                                        "galleryid" => $galleryid,
                                        "gallery_title" => $gallery_name,
                                        "imagename" => $filename,
                                        "siteviewable" => 0,
                                        "caption" => '',
                                        "alttext" => '',
                                        "dateadded" => $dateadded,
                                    ];
                                    execute_db_sql(fetch_template("dbsql/pics.sql", "insert_pic", "pics"), $params);
                                    resizeImage($upload_dir.$file_name, $upload_dir.$file_name,"600", "600");
                                    $success++;
                                } // end of (move_uploaded_file).
                            } // end of (file_exists).
                        } // end of (file_size).
                    } // end of (limitedext).
                } // end of (!is_uploaded_file).
                $i++;
            } // end of (while loop).

            if (empty($success)) {
                throw new \Exception("Error: No file was uploaded.");
            } else {
                die("<strong>$success file[s] uploaded.</strong>");
            }       
        }
        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
}

function toggle_activate() {
global $CFG;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$picsid = clean_myvar_req("picsid", "int");
	$row = get_db_row("SELECT * FROM pics WHERE picsid = ||picsid||", ["picsid" => $picsid]);

	$sitehidden = $row["sitehidden"];
	$pagehidden = $row["pagehidden"];

	$sitehidden = $sitehidden == 0 ? 1 : 0;
	$pagehidden = $pagehidden == 0 ? 1 : 0;

	if ($pageid === $CFG->SITEID) { // SITE IMAGE
		$activated = $sitehidden == 0 ? 'background-color:#FFFF66;' : '';
		execute_db_sql("UPDATE pics SET sitehidden = ||sitehidden|| WHERE picsid = ||picsid||", ["sitehidden" => $sitehidden, "picsid" => $picsid]);
	} else {
		$activated = $pagehidden == 0 ? 'background-color:#FFFF66;' : '';
		execute_db_sql("UPDATE pics SET pagehidden = ||pagehidden|| WHERE picsid = ||picsid||", ["pagehidden" => $pagehidden, "picsid" => $picsid]);
	}
    
    echo '<div style="text-align:center;width:171px;font-size:.85em;' . $activated . '">' . $row["imagename"] . '</div>';
}
?>