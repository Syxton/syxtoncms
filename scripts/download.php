<?php
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
$allowed_ext = [
  // archives
  'zip' => 'application/zip',
  'rar' => 'application/zip',
  'ace' => 'application/zip',
  '7z' => 'application/zip',

  // documents
  'pdf' => 'application/pdf',
  'txt' => 'application/msword',
  'doc' => 'application/msword',
  'docx' => 'application/msword',
  'xls' => 'application/vnd.ms-excel',
  'xlsx' => 'application/vnd.ms-excel',
  'ppt' => 'application/vnd.ms-powerpoint',
  'pptx' => 'application/vnd.ms-powerpoint',
  'csv' => 'application/vnd.ms-excel',
  'xml' => 'application/xml',

  // executables
  'exe' => 'application/octet-stream',

  // images
  'gif' => 'image/gif',
  'png' => 'image/png',
  'jpg' => 'image/jpeg',
  'jpeg' => 'image/jpeg',

  // audio
  'mp3' => 'audio/mpeg',
  'wav' => 'audio/x-wav',

  // video
  'mpeg' => 'video/mpeg',
  'mpg' => 'video/mpeg',
  'mpe' => 'video/mpeg',
  'mov' => 'video/quicktime',
  'avi' => 'video/x-msvideo',
];

if (!empty($_GET['file'])) {
    $file = $_GET['file'];
    $file = urldecode($file);

    // check if gated file.
    if (strstr($file, "filegate.php")) {
        // check if file exists.
        $file = fm_gated_url_to_path($file);
        if (!file_exists($file)) {
            die("File not found.");
        }
        $path_parts = pathinfo($file);
    } else {
        $file = str_replace("\\", "/", $file);
        $path_parts = pathinfo($file);

        $file = str_replace(
            ['%3A', '%2F'],
            [':', '/'],
            rawurlencode($file)
        );
    }

    if (empty($path_parts['filename']) && empty($path_parts['extension'])) {
        die("Invalid file path.");
    }

    if (!array_key_exists($path_parts['extension'], $allowed_ext)) {
        die("Not allowed file type.");
    }

    $filename = $path_parts['filename'] . "." . $path_parts['extension'];

    // get mime type
    if ($allowed_ext[$path_parts['extension']] == '') {
        $mtype = '';
        // mime type is not set, get from server settings
        if (function_exists('mime_content_type')) {
            $mtype = mime_content_type($file);
        }elseif (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME); // return mime type
            $mtype = finfo_file($finfo, $file);
            finfo_close($finfo);
        }
        if ($mtype == '') {
            $mtype = "application/octet-stream";
        }
    } else {
      // get mime type defined by admin
      $mtype = $allowed_ext[$path_parts['extension']];
    }

    ob_start();
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Type: $mtype");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Transfer-Encoding: binary");
    ob_clean();
    flush();
    readfile($file);
}
exit;
?>