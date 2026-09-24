<?php
/***************************************************************************
* ipaper.php - http://www.ajaxdocumentviewer.com/ link page
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.1.6
***************************************************************************/

if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}

echo fill_template("tmp/page.template", "start_of_page_template");

callfunction();

echo fill_template("tmp/page.template", "end_of_page_template");

function view_ipaper() {
    global $CFG;
    $raw = clean_myvar_req("doc_url", "string");
    $url = trim(base64_decode($raw));
    if ($url === false || $url === '') {
        echo 'Invalid document URL.';
        return;
    }

    // Normalize & basic safety (same spirit as the filter)
    $url = trim(base64_decode($raw));
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $url = str_replace(['\\', '../'], '', $url);  // avoid stripping lone ".." inside tokens

    if (stripos($url, 'filegate.php') !== false) {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $q);
        $rel = (string) ($q['p'] ?? '');
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    } else {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    $isPdf    = ($ext === 'pdf');
    $isOffice = in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','rtf'], true);
    $viewNative = in_array($ext, ['csv'], true);

    if ($viewNative) {
        $function = "native_$ext";
        if (function_exists($function)) {
            $nativeViewer = $function($url);
        } else {
            $nativeViewer = "No native viewer available for .$ext files.";
        }
    }

    // Google/Microsoft can only fetch publicly reachable URLs.
    // Private filegate links (and anything that requires a session) will fail.
    $publiclyFetchable = !preg_match('#filegate\.php#i', $url)
        || (strpos($url, 'lvl=link') !== false); // pure link-level shares are the only ones Google might reach

    if ($isPdf) {
        // Native browser PDF viewer – best UX, works with gated URLs
        $iframeSrc = $url;
    } elseif ($isOffice && $publiclyFetchable) {
        // Microsoft’s viewer is generally more reliable than the old gview
        $iframeSrc = 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($url);
        // fallback if you still want Google for some types:
        // $iframeSrc = 'https://docs.google.com/gview?url=' . rawurlencode($url) . '&embedded=true';
    } else {
        // Private or unsupported – don’t waste a click on a blank Google frame
        echo fill_template("tmp/ipaper.template", "view_ipaper_fallback_template", false, [
            "doc_url" => htmlspecialchars($url),
            "download_url" => $CFG->wwwroot . '/scripts/download.php?file=' . rawurlencode($url),
            "viewNative" => $viewNative,
            "nativeViewer" => $nativeViewer,
        ]);
        return;
    }

    echo fill_template("tmp/ipaper.template", "view_ipaper_template", false, [
        "iframe_src" => $iframeSrc,   // change template to use this
    ]);
}

function native_csv(string $url): string
{
    $path = fm_gated_url_to_path($url);
    if ($path === null || !is_file($path)) {
        // Optional: use the same messaging the gate itself uses
        $status = fm_gated_url_predict_status($url) ?? 404;
        $name   = fm_gate_filename_from_url($url) ?: null;
        return fm_gate_placeholder_html($status, $name);
        // or simply: return 'Unable to open the CSV file.';
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        return 'Not a CSV file.';
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return 'Unable to open the CSV file on disk.';
    }

    $html = '<table class="searchresults" style="margin: 10px 0;">';
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td style="padding: 2px;border:1px solid silver">' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }
    fclose($handle);
    $html .= '</table>';
    return $html;
}

function native_whatever(string $url): string
{
    $path = fm_gated_url_to_path($url);
    if ($path === null || !is_file($path)) {
        $status = fm_gated_url_predict_status($url) ?? 404;
        return fm_gate_placeholder_html($status, fm_gate_filename_from_url($url) ?: null);
    }

    // … extension / MIME check …
    // … open $path and render …
}
?>