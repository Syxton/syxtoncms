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
    // Query string may be encoded; restore a real filesystem path.
    $file = urldecode((string)$_GET['file']);
    // Strip slashes added when path was embedded in HTML (create_file uses addslashes).
    $file = stripslashes($file);

    // check if gated file.
    if (strstr($file, "filegate.php")) {
        $file = fm_gated_url_to_path($file);
        if (!file_exists($file)) {
            die("File not found.");
        }
        $path_parts = pathinfo($file);
    } else {
        // Normalize separators only — do NOT rawurlencode the path for readfile().
        // rawurlencode turned staffapps(2021).csv into staffapps%282021%29.csv and broke exports.
        $file = str_replace('\\', '/', $file);
        // Collapse accidental duplicate slashes in the path (keep leading protocol-style C:/)
        $file = preg_replace('#(?<!:)/+#', '/', $file);
        $path_parts = pathinfo($file);
    }

    if (empty($path_parts['filename']) && empty($path_parts['extension'])) {
        die("Invalid file path.");
    }

    $ext = strtolower($path_parts['extension'] ?? '');
    if ($ext === '' || !array_key_exists($ext, $allowed_ext)) {
        die("Not allowed file type.");
    }

    // Download name is basename only (safe for Content-Disposition)
    $filename = $path_parts['filename'] . "." . $ext;

    if (!is_file($file) || !is_readable($file)) {
        die("File not found.");
    }

    // get mime type
    if ($allowed_ext[$ext] == '') {
        $mtype = '';
        if (function_exists('mime_content_type')) {
            $mtype = mime_content_type($file);
        } elseif (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mtype = finfo_file($finfo, $file);
            finfo_close($finfo);
        }
        if ($mtype == '') {
            $mtype = "application/octet-stream";
        }
    } else {
        $mtype = $allowed_ext[$ext];
    }

    // Prefer realpath for read; keep $filename for the download name
    $realpath = realpath($file);
    if ($realpath === false) {
        die("File not found.");
    }

    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Type: $mtype");
    header("Content-Disposition: attachment; filename=\"" . str_replace('"', '', $filename) . "\"");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . filesize($realpath));
    readfile($realpath);
}
exit;
