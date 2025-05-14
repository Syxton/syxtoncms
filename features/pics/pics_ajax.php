<?php
/***************************************************************************
* pics_ajax.php - Pics feature ajax backend
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.7.4
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}

if (!defined('PICSLIB')) { include_once ($CFG->dirroot . '/features/pics/picslib.php'); }
update_user_cookie();

callfunction();

function pics_pageturn() {
global $CFG, $MYVARS;
    $featureid = clean_myvar_req("featureid", "int", false);
    $galleryid = clean_myvar_opt("galleryid", "int", 0);
    $pagenum = clean_myvar_opt("pagenum", "int", 0);
    $order = clean_myvar_opt("order", "string", false);

	ajax_return(get_pics($featureid, $galleryid, $pagenum, $order));
}

function new_gallery() {
	$newgallery = clean_myvar_req("param", "bool");
	$pageid = clean_myvar_req("pageid", "int");

    $return = $error = "";
    try {
        if ($newgallery) {
            $return = '<input name="gallery_name" id="gallery_name" type="text" size="32" onkeypress="return handleEnter(this, event);" />';
        } else {
            $p = [
                "properties" => [
                    "name" => "galleryid",
                    "id" => "galleryid",
                ],
                "values" => get_db_result(fetch_template("dbsql/pics.sql", "get_page_galleries", "pics"), ["pageid" => $pageid]),
                "valuename" => "galleryid",
                "displayname" => "name",
                "firstoption" => "None selected",
            ];
            $return = make_select($p);
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

	ajax_return($return, $error);
}

function altergallery() {
global $CFG;
    $picsid = clean_myvar_req("picsid", "int");
    $galleryid = clean_myvar_req("galleryid", "int");
    $pageid = get_pageid();

    $return = $error = "";
    try {
        $pic = get_db_row("SELECT * FROM pics WHERE picsid = ||picsid||", ["picsid" => $picsid]);
        if ($pageid !== $pic["pageid"]) { // Pic is from a different course and needs to be copied to this course.
            $featureid = get_db_field("featureid", "pics_galleries", "galleryid = ||galleryid||", ["galleryid" => $galleryid]);
            $old = $CFG->userfilespath . '/pics/files/' . $pic["pageid"] . "/" . $pic["featureid"]. "/" . $pic["imagename"];
            $new = $CFG->userfilespath . '/pics/files/' . $pageid . "/" . $featureid . "/" . $pic["imagename"];
            if (!copy_file($old, $new)) {
                throw new Exception("Could not copy file.");
            }
            if (!copy_db_row($pic, "pics", [["picsid" => NULL, "siteviewable" => 0, "featureid" => $featureid, "galleryid" => $galleryid, "pageid" => $pageid]])) {
                throw new Exception("Could not change pics gallery.");
            }
        } else {
            $params = [
                "pageid" => $pageid,
                "picsid" => $picsid,
                "galleryid" => $galleryid,
            ];
            if (!execute_db_sql("UPDATE pics SET galleryid = ||galleryid||, pageid = ||pageid|| WHERE picsid = ||picsid||", $params)) {
                throw new Exception("Could not change pics gallery");
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

	ajax_return($return, $error);
}

function save_caption() {
    $picsid = clean_myvar_req("picsid", "int");
    $caption = clean_myvar_opt("caption", "html", "");

    $return = $error = "";
    try {
        if (!execute_db_sql("UPDATE pics SET caption = ||caption|| WHERE picsid = ||picsid||", ["picsid" => $picsid, "caption" => $caption])) {
            throw new Exception("Could not add caption");
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

	ajax_return($return, $error);
}

function save_viewability() {
    $picsid = clean_myvar_req("picsid", "int");
    $siteviewable = clean_myvar_opt("siteviewable", "bool", 0);
    $siteviewable = $siteviewable ? 1 : 0; // make into integer because checklists come across as bool.

    $return = $error = "";
    try {
        if (!execute_db_sql("UPDATE pics SET siteviewable = ||siteviewable|| WHERE picsid =||picsid||", ["picsid" => $picsid, "siteviewable" => $siteviewable])) {
            throw new Exception("Could not save viewability.");
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

	ajax_return($return, $error);
}

function delete_pic() {
global $CFG;
    $picsid = clean_myvar_req("picsid", "int");

    $return = $error = "";
    try {
        $row = get_db_row("SELECT * FROM pics WHERE picsid = ||picsid||", ["picsid" => $picsid]);
        if (!delete_file($CFG->userfilespath . '/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row["imagename"])) {
            throw new Exception("Could not delete files.");
        }

        if (!execute_db_sql("DELETE FROM pics WHERE picsid = ||picsid||", ["picsid" => $picsid])) {
            throw new Exception("Could not save viewability.");
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }

	ajax_return($return, $error);
}

function delete_gallery() {
global $CFG, $MYVARS;
    $pageid = clean_myvar_req("pageid", "int");
    $featureid = clean_myvar_req("featureid", "int");
    $galleryid = clean_myvar_opt("galleryid", "int", 0);

    try {
        $delete = $copy = false;
        if (!empty($galleryid) && !empty($pageid) && !empty($featureid)) {
            if ($result = get_db_result("SELECT * FROM pics WHERE galleryid='$galleryid'")) {
                while ($row = fetch_row($result)) {
                    if ($pageid !== $CFG->SITEID && !empty($row["siteviewable"])) { //siteviewable images from a page other than SITE.  Move them to site
                        $copy = true;
                        $site_featureid = get_db_field("featureid", "pages_features", "feature='pics' AND pageid=||pageid||", ["pageid" => $CFG->SITEID]);
                        $old = $CFG->userfilespath . '/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row["imagename"];
                        $new = $CFG->userfilespath . '/pics/files/' . $CFG->SITEID. "/" . $site_featureid. "/" . $row["imagename"];
                        if (!copy_file($old, $new)) {
                            throw new Exception("Could not copy file.");
                        }

                        if (!delete_file($old)) {
                            throw new Exception("Could not delete file.");
                        }
                    } elseif ($pageid == $CFG->SITEID && $pageid != $row["pageid"]) {  //SITE is dealing with images from another page
                        execute_db_sql("UPDATE pics SET siteviewable = 0 WHERE galleryid = ||galleryid||", ["galleryid" => $galleryid]);
                    } else { //nobody is using it, so delete it
                        $delete = true;
                        if (!delete_file($CFG->userfilespath . '/pics/files/' . $row["pageid"]. "/" . $row["featureid"]. "/" . $row["imagename"])) {
                            throw new Exception("Could not delete file.");
                        }
                        execute_db_sql("DELETE FROM pics WHERE picsid='" . $row["picsid"] . "'");
                    }
                }
            }

            if ($copy) {
                $SQL = "UPDATE pics SET pageid = ||pageid||, featureid = ||featureid|| WHERE galleryid = ||galleryid||";
                execute_db_sql($SQL, ["pageid" => $CFG->SITEID, "featureid" => $site_featureid, "galleryid" => $galleryid]);
                $SQL = "UPDATE pics_galleries SET pageid = ||pageid||, featureid = ||featureid|| WHERE galleryid = ||galleryid||";
                execute_db_sql($SQL, ["galleryid" => $galleryid]);
            }

            if ($delete) {
                execute_db_sql("DELETE FROM pics_galleries WHERE galleryid = ||galleryid||", ["galleryid" => $galleryid]);
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    $return = get_pics_manager($pageid, $featureid);
    ajax_return($return, $error);
}

function pics_upload() {
global $CFG;
    $newgallery = clean_myvar_opt("new_gallery", "bool", false);
    $pageid = clean_myvar_req("pageid", "int");
    $featureid = clean_myvar_req("featureid", "int");

    // upload directory.
    $upload_dir = $CFG->userfilespath . "/pics/files/$pageid/$featureid/";

    try {
        start_db_transaction();
        //must have a featureid and pageid
        if (!empty($featureid) && !empty($pageid)) {
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
                throw new \Exception("Error: The directory <strong>($upload_dir)</strong> doesn't exist");
            }
            //check if the directory is writable.
            if (!is_writeable("$upload_dir")) {
                throw new \Exception("Error: The directory <strong>($upload_dir)</strong> is NOT writable, Please CHMOD (777)");
            }

            //do a loop for uploading files based on ($file_count) number of files.
            $i = $success = 0;
            $galleryid = clean_myvar_opt("galleryid", "int", false);
            $files = $_FILES["files"];
            $file_count = count($files["name"]);

            while (isset($files["name"][$i])) {
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
                            $n = 0;
                            $original_filename = $file_name;
                            while(file_exists($upload_dir . $file_name)) {
                                $n++;
                                $file_name = str_replace($ext, "", $original_filename) . " ($n)" . $ext;
                            }
                            $files["name"][$i] = $file_name;

                            #-----------------------------------------------------------#
                            # this function will upload the files.  :) ;) cool          #
                            #-----------------------------------------------------------#
                            if (move_uploaded_file($file_tmp, $upload_dir . $file_name)) {
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
                                    "imagename" => $file_name,
                                    "siteviewable" => 0,
                                    "caption" => '',
                                    "alttext" => '',
                                    "dateadded" => $dateadded,
                                ];
                                $SQL = fetch_template("dbsql/pics.sql", "insert_pic", "pics");
                                if(!execute_db_sql($SQL, $params)) {
                                    throw new \Exception("Error: Failed to insert file record ($file_name) into database. ($file_name).");
                                }
                                resizeImage($upload_dir . $file_name, $upload_dir . $file_name, "1000", "1000");
                                $success++;
                            } // end of (move_uploaded_file).
                        } // end of (file_size).
                    } // end of (limitedext).
                } // end of (!is_uploaded_file).
                $i++;
            } // end of (while loop).

            if (empty($success)) {
                throw new \Exception("Error: No file was uploaded.");
            } else {
                commit_db_transaction();
                die("<strong>$success file[s] uploaded.</strong>");
            }
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $i = 0;
        while (isset($filenames[$i]) && isset($files["name"][$i])) {
            if (file_exists($upload_dir . $files["name"][$i])) {
                unlink($upload_dir . $files["name"][$i]);
            }
            $i++;
        }
    }
}

function toggle_activate() {
global $CFG;
	$pageid = clean_myvar_opt("pageid", "int", get_pageid());
	$picsid = clean_myvar_req("picsid", "int");

    $return = $error = "";
    try {
        start_db_transaction();
        $pics = get_db_row("SELECT * FROM pics WHERE picsid = ||picsid||", ["picsid" => $picsid]);

        $sitehidden = $pics["sitehidden"];
        $pagehidden = $pics["pagehidden"];

        $sitehidden = $sitehidden == 0 ? 1 : 0;
        $pagehidden = $pagehidden == 0 ? 1 : 0;

        if ($pageid === $CFG->SITEID) { // SITE IMAGE
            execute_db_sql("UPDATE pics SET sitehidden = ||sitehidden|| WHERE picsid = ||picsid||", ["sitehidden" => $sitehidden, "picsid" => $picsid]);
        } else {
            execute_db_sql("UPDATE pics SET pagehidden = ||pagehidden|| WHERE picsid = ||picsid||", ["pagehidden" => $pagehidden, "picsid" => $picsid]);
        }

        commit_db_transaction();
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
        $error = $e->getMessage();
    }

    ajax_return($return, $error);
}
?>