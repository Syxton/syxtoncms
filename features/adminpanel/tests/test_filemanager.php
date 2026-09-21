<?php
/***************************************************************************
 * test_filemanager.php: File manager (tinymce/plugins/filemanager) unit
 * and integration tests.
 * -------------------------------------------------------------------------
 * Two layers:
 *
 *   1. PURE HELPER TESTS - call fmconfig.php's sanitize/token/permission
 *      functions directly, in-process. Fast, deterministic, no disk/HTTP.
 *
 *   2. LIVE API TESTS - real HTTP requests to api.php (via curl, reusing
 *      this session's cookie) exercising the actual CSRF check, permission
 *      gate, and disk operations exactly as the browser dialog does. This
 *      is the only way to test api.php itself: its action handlers are
 *      inline in one big switch, not separate callable functions, and the
 *      file calls exit() on bad input - including api.php would blow up
 *      unit_tests.php's own page if we tried to include() it directly.
 *
 * Everything in layer 2 runs under area=priv (My files) for the CURRENT
 * admin user, inside a disposable "unit_test_<random>" folder that is
 * created fresh and permanently deleted at the end (via a shutdown
 * function, so a failed/aborted run still cleans up). Nothing outside
 * that folder is touched.
 ***************************************************************************/

$passCounter = 0;
$totalCounter = 0;
$tests = "";

if (!defined('FMCONFIG')) {
    require_once($CFG->dirroot . '/scripts/tinymce/plugins/filemanager/fmconfig.php');
}

global $USER;

// ===========================================================================
// LAYER 1: pure helper function tests (fmconfig.php) - no disk, no HTTP.
// ===========================================================================

// -- fm_sanitize_relpath() ---------------------------------------------------
$tests .= testCheck("sanitize_relpath: plain nested path passes through",
    fm_sanitize_relpath("folder one/sub-folder_2") === "folder one/sub-folder_2" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_relpath: empty string is a valid (root) path",
    fm_sanitize_relpath("") === "" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_relpath: backslashes normalize to forward slashes",
    fm_sanitize_relpath("a\\b\\c") === "a/b/c" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_relpath: rejects '..' traversal",
    fm_sanitize_relpath("../../etc/passwd") === null ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_relpath: rejects embedded null byte",
    fm_sanitize_relpath("folder/evil\0.txt") === null ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_relpath: rejects disallowed characters",
    fm_sanitize_relpath("folder/<script>") === null ? "PASS" : "FAIL", $passCounter, $totalCounter);

// -- fm_sanitize_name() ------------------------------------------------------
$tests .= testCheck("sanitize_name: ordinary filename passes through",
    fm_sanitize_name("My Report (final).docx") === "My Report (final).docx" ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_name: rejects exactly '..'",
    fm_sanitize_name("..") === null ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_name: rejects a path separator inside a single-segment name",
    fm_sanitize_name("a/b.txt") === null ? "PASS" : "FAIL", $passCounter, $totalCounter);

$tests .= testCheck("sanitize_name: trims surrounding whitespace",
    fm_sanitize_name("  spaced.txt  ") === "spaced.txt" ? "PASS" : "FAIL", $passCounter, $totalCounter);

// -- admin preview + share tokens -------------------------------------------
$tok = fm_build_admin_token(FM_AREA_PRIVATE, "42", "a/b.png", 1000);
$tests .= testCheck("admin token: verifies against its own inputs",
    fm_verify_admin_token(FM_AREA_PRIVATE, "42", "a/b.png", 1000, $tok) ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("admin token: rejects a tampered mtime",
    !fm_verify_admin_token(FM_AREA_PRIVATE, "42", "a/b.png", 1001, $tok) ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("admin token: rejects a tampered path",
    !fm_verify_admin_token(FM_AREA_PRIVATE, "42", "a/other.png", 1000, $tok) ? "PASS" : "FAIL", $passCounter, $totalCounter);

$shareTok = fm_build_share_token(FM_LEVEL_PAGE, FM_AREA_PUBLIC, "7", "gallery/pic.jpg", 5000, "7");
$tests .= testCheck("share token: verifies against its own inputs",
    fm_verify_share_token(FM_LEVEL_PAGE, FM_AREA_PUBLIC, "7", "gallery/pic.jpg", 5000, "7", $shareTok) ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("share token: rejects a swapped 'extra' (pageid) binding",
    !fm_verify_share_token(FM_LEVEL_PAGE, FM_AREA_PUBLIC, "7", "gallery/pic.jpg", 5000, "9", $shareTok) ? "PASS" : "FAIL", $passCounter, $totalCounter);

// -- fm_normalize_gated_url() -------------------------------------------------
$tests .= testCheck("normalize_gated_url: decodes '&amp;' back to '&'",
    fm_normalize_gated_url("filegate.php?a=1&amp;b=2") === "filegate.php?a=1&b=2" ? "PASS" : "FAIL", $passCounter, $totalCounter);

// -- permission hooks, run as the logged-in siteadmin -----------------------
$tests .= testCheck("can_access_private: siteadmin can reach another user's private area",
    fm_can_access_private("999999") ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("can_access_private: siteadmin can reach their own private area",
    fm_can_access_private((string) $USER->userid) ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("is_able: siteadmin passes any filemanager_* ability check",
    fm_is_able("filemanager_delete", "123") ? "PASS" : "FAIL", $passCounter, $totalCounter);
// NOTE: is_siteadmin() short-circuits to true BEFORE the $pageid === '' check
// in fm_is_able(), so a siteadmin session passes even with no pageid - the
// "denies with no pageid" branch only applies to non-admin users, which this
// always-admin unit-test session can't exercise. This just documents the
// siteadmin bypass explicitly instead of asserting the wrong thing.
$tests .= testCheck("is_able: siteadmin bypass applies even with no pageid to scope to",
    fm_is_able("filemanager_delete", "") ? "PASS" : "FAIL", $passCounter, $totalCounter);

// -- fm_check_share_permission() ---------------------------------------------
$tests .= testCheck("check_share_permission: 'link' level never requires login/ownership",
    fm_check_share_permission(FM_LEVEL_LINK, FM_AREA_PUBLIC, "123", "") ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("check_share_permission: 'private' level is refused for a Page-files area",
    !fm_check_share_permission(FM_LEVEL_PRIVATE, FM_AREA_PUBLIC, (string) $USER->userid, "") ? "PASS" : "FAIL", $passCounter, $totalCounter);
$tests .= testCheck("check_share_permission: unknown level is refused",
    !fm_check_share_permission("bogus", FM_AREA_PUBLIC, "1", "") ? "PASS" : "FAIL", $passCounter, $totalCounter);

echo '
    <li>
        <h2 class="title">File Manager: Helper Function Tests ' . $passCounter . '/' . $totalCounter . '</h2>
        <div>
            ' . $tests . '
        </div>
    </li>';

// ===========================================================================
// LAYER 2: live HTTP round-trip against api.php, under My files (area=priv)
// for the current admin. Every mutating call goes through the exact same
// CSRF + permission + disk-write path the browser dialog uses.
// ===========================================================================

$passCounter2 = 0;
$totalCounter2 = 0;
$tests2 = "";

if (!function_exists('curl_init')) {
    $tests2 .= testCheck("live API tests", "FAIL: the PHP curl extension is not available - cannot run", $passCounter2, $totalCounter2);
    echo '
        <li>
            <h2 class="title">File Manager: Live API Tests ' . $passCounter2 . '/' . $totalCounter2 . '</h2>
            <div>' . $tests2 . '</div>
        </li>';
} else {

    $fmArea = FM_AREA_PRIVATE;
    $fmId   = (string) $USER->userid;
    $testRoot = 'unit_test_' . bin2hex(random_bytes(6));

    if (empty($_SESSION['fm_csrf'])) {
        $_SESSION['fm_csrf'] = bin2hex(random_bytes(32));
    }
    $csrf = $_SESSION['fm_csrf'];

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:';
    $apiUrl = $scheme . $CFG->wwwroot . '/scripts/tinymce/plugins/filemanager/api.php';
    $cookieHeader = session_name() . '=' . session_id();
    $host = parse_url($apiUrl, PHP_URL_HOST);
    $port = parse_url($apiUrl, PHP_URL_PORT) ?: ($scheme === 'https:' ? 443 : 80);
    // apiUrl is always a SELF-call (built from $CFG->wwwroot), so it's safe -
    // and often necessary - to bypass normal DNS/hosts resolution for it and
    // just connect back to this server's own address directly. Local dev
    // hostnames (e.g. a custom .test domain wired up by Valet/Herd/dnsmasq
    // on the developer's machine) frequently aren't resolvable from the
    // server process itself, which otherwise shows up as a flat "HTTP 0"
    // with every single live-API test failing.
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    // Same reasoning for TLS: this is a loopback call into ourselves over
    // whatever hostname the app is configured with, which very often means
    // a local/self-signed cert on dev boxes. Skip peer verification for it -
    // the actual trust boundary here is the session cookie, not the cert.
    $isLocalHost = true;

    // Release the session file lock before issuing HTTP requests back into
    // this same app - PHP's default file-based session handler otherwise
    // serializes every request for one session, and api.php's own
    // session_start() would block until THIS script finished (deadlock).
    session_write_close();

    /**
     * Fire one request at api.php as this same logged-in session and
     * return [httpCode, decodedJsonOrNull, rawBody]. Pass $cookieHeader as
     * '' to send no session cookie at all (used by the unauthenticated
     * negative-path test).
     */
    function fm_test_call(string $apiUrl, string $cookieHeader, bool $relaxTls, array $fields, ?array $file = null) {
        global $CFG;
        $host = parse_url($apiUrl, PHP_URL_HOST);
        $scheme = parse_url($apiUrl, PHP_URL_SCHEME);
        $port = parse_url($apiUrl, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);
        // SERVER_ADDR is right for a plain single-box setup; behind a
        // container/proxy where that's not directly reachable, set
        // $CFG->fm_test_self_ip in config.php to override it.
        $serverAddr = $CFG->fm_test_self_ip ?? ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $cookieHeader !== '' ? ["Cookie: $cookieHeader"] : [],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Force this self-call back to our own server address instead of
            // depending on DNS/hosts resolution for our own public hostname.
            CURLOPT_RESOLVE => ["$host:$port:$serverAddr"],
        ]);
        if ($relaxTls) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        if ($file !== null) {
            $fields['file[]'] = new CURLFile($file['path'], $file['type'], $file['name']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); // multipart, since a CURLFile is present
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $errno !== 0) {
            // Surface the real reason instead of a bare null - "HTTP 0" with
            // no explanation is nearly undiagnosable from the test output.
            return [0, ['curl_errno' => $errno, 'curl_error' => $err], $err];
        }
        $decoded = json_decode($body, true);
        return [$code, $decoded, $body];
    }

    /**
     * Convenience wrapper around fm_test_call() for uploading a small text
     * payload without each caller hand-rolling a temp file. Returns the
     * same [httpCode, decodedJson, rawBody] shape.
     */
    function fm_test_upload_text(string $apiUrl, string $cookieHeader, bool $relaxTls, array $base, string $path, string $filename, string $content, string $onConflict = 'rename') {
        $tmp = tempnam(sys_get_temp_dir(), 'fmtest_');
        file_put_contents($tmp, $content);
        $result = fm_test_call($apiUrl, $cookieHeader, $relaxTls,
            $base + ['action' => 'upload', 'path' => $path, 'onConflict' => $onConflict],
            ['path' => $tmp, 'type' => 'text/plain', 'name' => $filename]
        );
        @unlink($tmp);
        return $result;
    }

    /**
     * Permanently remove only those trash entries that originated under
     * $testRoot (the disposable scratch folder for this test run). Leaves
     * any pre-existing / unrelated items in the recycle bin alone.
     *
     * Matching rules (from trash_list meta):
     *   - path === $testRoot, or path starts with "$testRoot/"
     *   - path === '' AND name === $testRoot  (the scratch root itself)
     */
    function fm_test_purge_scratch_trash(string $apiUrl, string $cookieHeader, bool $relaxTls, array $base, string $testRoot): void {
        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $relaxTls, $base + ['action' => 'trash_list']);
        if ($code !== 200 || empty($data['items']) || !is_array($data['items'])) {
            return;
        }
        $prefix = $testRoot . '/';
        foreach ($data['items'] as $item) {
            $path = (string) ($item['path'] ?? '');
            $name = (string) ($item['name'] ?? '');
            $tid  = (string) ($item['trashId'] ?? '');
            if (!preg_match('/^[0-9a-f]{16}$/', $tid)) {
                continue;
            }
            $underScratch = ($path === $testRoot)
                || (strpos($path, $prefix) === 0)
                || ($path === '' && $name === $testRoot);
            if ($underScratch) {
                fm_test_call($apiUrl, $cookieHeader, $relaxTls, $base + [
                    'action' => 'trash_delete', 'trashId' => $tid,
                ]);
            }
        }
    }

    $base = ['area' => $fmArea, 'id' => $fmId, 'csrf' => $csrf];

    // --- cleanup, registered up front so a mid-test failure still tidies up ---
    register_shutdown_function(function () use ($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot) {
        // Best effort: soft-delete the whole scratch folder (if it still
        // exists), then permanently delete every trash entry that originated
        // under it - including items left by onConflict=replace tests.
        // Pre-existing recycle-bin items outside $testRoot are left alone.
        fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
            'action' => 'delete', 'name' => $testRoot, 'target' => 'folder', 'path' => '',
        ]);
        fm_test_purge_scratch_trash($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot);
    });

    // 1) create the scratch root folder
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'mkdir', 'name' => $testRoot, 'path' => '',
    ]);
    $tests2 .= testCheck("mkdir: creates the scratch root folder", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 2) it shows up in a listing
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'list', 'path' => '']);
    $names = array_column($data['folders'] ?? [], 'name');
    $tests2 .= testCheck("list: scratch root folder appears at the top level", ($code === 200 && in_array($testRoot, $names, true)) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 3) duplicate mkdir is rejected
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'mkdir', 'name' => $testRoot, 'path' => '',
    ]);
    $tests2 .= testCheck("mkdir: creating the same folder twice returns 409", $code === 409 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 4) nested subfolder
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'mkdir', 'name' => 'sub', 'path' => $testRoot,
    ]);
    $tests2 .= testCheck("mkdir: creates a nested subfolder", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 5) upload a small text file into the root
    $tmpFile = tempnam(sys_get_temp_dir(), 'fmtest_');
    $contents = "unit test file - " . bin2hex(random_bytes(8));
    file_put_contents($tmpFile, $contents);
    rename($tmpFile, $tmpFile . '.txt');
    $tmpFile .= '.txt';

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost,
        $base + ['action' => 'upload', 'path' => $testRoot],
        ['path' => $tmpFile, 'type' => 'text/plain', 'name' => 'test.txt']
    );
    $uploadOk = $code === 200 && !empty($data['results'][0]['ok']) && $data['results'][0]['name'] === 'test.txt';
    $tests2 .= testCheck("upload: text file is accepted and saved as 'test.txt'", $uploadOk ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 6) check_conflicts sees the just-uploaded name
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'check_conflicts', 'path' => $testRoot,
        'items' => json_encode([['name' => 'test.txt', 'target' => 'file']]),
    ]);
    $tests2 .= testCheck("check_conflicts: reports the existing file as a conflict", ($code === 200 && in_array('test.txt', $data['conflicts'] ?? [], true)) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 7) uploading the same name again (default onConflict=rename) numbers it instead of clobbering
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost,
        $base + ['action' => 'upload', 'path' => $testRoot],
        ['path' => $tmpFile, 'type' => 'text/plain', 'name' => 'test.txt']
    );
    $renamedOnConflict = $code === 200 && !empty($data['results'][0]['ok']) && $data['results'][0]['name'] !== 'test.txt';
    $tests2 .= testCheck("upload: re-uploading the same name auto-renames rather than overwriting", $renamedOnConflict ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    $secondUploadName = $data['results'][0]['name'] ?? null;

    // 8) list shows both files with correct size
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'list', 'path' => $testRoot]);
    $fileNames = array_column($data['files'] ?? [], 'name');
    $tests2 .= testCheck("list: both uploaded files are visible", ($code === 200 && in_array('test.txt', $fileNames, true) && in_array($secondUploadName, $fileNames, true)) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    $sizeOk = false;
    foreach ($data['files'] ?? [] as $f) {
        if ($f['name'] === 'test.txt') {
            $sizeOk = ((int) $f['size']) === strlen($contents);
        }
    }
    $tests2 .= testCheck("list: reported file size matches the uploaded content", $sizeOk ? "PASS" : "FAIL", $passCounter2, $totalCounter2);

    // 9) rename
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'rename', 'path' => $testRoot, 'old' => 'test.txt', 'new' => 'renamed.txt', 'target' => 'file',
    ]);
    $tests2 .= testCheck("rename: file is renamed", ($code === 200 && ($data['name'] ?? '') === 'renamed.txt') ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 10) rename cannot spoof the extension - request a new base name AND a
    // different extension; the server should keep the real .txt extension
    // rather than trust the caller's .pdf.
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'rename', 'path' => $testRoot, 'old' => 'renamed.txt', 'new' => 'sneaky.pdf', 'target' => 'file',
    ]);
    $tests2 .= testCheck("rename: requesting a .pdf extension on a .txt file keeps the real .txt extension", ($code === 200 && ($data['name'] ?? '') === 'sneaky.txt') ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    // server responded with whatever it actually wrote - use that name from here on
    $currentFileName = $data['name'] ?? 'sneaky.txt';

    // 11) duplicate
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'duplicate', 'path' => $testRoot, 'name' => $currentFileName, 'target' => 'file',
    ]);
    $tests2 .= testCheck("duplicate: creates a '(copy)' sibling", ($code === 200 && !empty($data['ok']) && strpos($data['name'] ?? '', '(copy)') !== false) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    $duplicateName = $data['name'] ?? null;

    // 12) copy the 'sub' folder into itself (same-folder copy -> '(copy)' folder name)
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'copy', 'path' => $testRoot, 'name' => 'sub', 'target' => 'folder',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot,
    ]);
    $tests2 .= testCheck("copy: same-folder folder copy produces a '(copy)' name", ($code === 200 && !empty($data['ok']) && strpos($data['name'] ?? '', '(copy)') !== false) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    $subCopyName = $data['name'] ?? null;

    // 13) move the copied folder into 'sub' (nests it one level deeper)
    if ($subCopyName !== null) {
        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
            'action' => 'move', 'path' => $testRoot, 'name' => $subCopyName, 'target' => 'folder',
            'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/sub',
        ]);
        $tests2 .= testCheck("move: relocates a folder into a different subfolder", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'list', 'path' => $testRoot . '/sub']);
        $subNames = array_column($data['folders'] ?? [], 'name');
        $tests2 .= testCheck("move: moved folder is now listed under 'sub'", ($code === 200 && !empty($subNames)) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    }

    // 14) move rejects a same-location no-op
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'move', 'path' => $testRoot, 'name' => $currentFileName, 'target' => 'file',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot,
    ]);
    $tests2 .= testCheck("move: refuses to move a file onto itself", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 15) geturl - link level
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'geturl', 'path' => $testRoot, 'name' => $currentFileName, 'target' => 'file', 'level' => 'link',
    ]);
    $tests2 .= testCheck("geturl: 'link' level returns a signed URL", ($code === 200 && !empty($data['ok']) && strpos($data['url'] ?? '', 'filegate.php') !== false) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 16) geturl - 'private' level is valid for My files
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'geturl', 'path' => $testRoot, 'name' => $currentFileName, 'target' => 'file', 'level' => 'private',
    ]);
    $tests2 .= testCheck("geturl: 'private' level is accepted for a My files item", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 17) geturl - 'page' level with no pageid is rejected
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'geturl', 'path' => $testRoot, 'name' => $currentFileName, 'target' => 'file', 'level' => 'page',
    ]);
    $tests2 .= testCheck("geturl: 'page' level without a pageid is rejected", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 18) delete (soft) -> trash_list -> restore
    if ($duplicateName !== null) {
        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
            'action' => 'delete', 'path' => $testRoot, 'name' => $duplicateName, 'target' => 'file',
        ]);
        $tests2 .= testCheck("delete: soft-deletes a file and returns a trashId", ($code === 200 && !empty($data['trashId']) && preg_match('/^[0-9a-f]{16}$/', $data['trashId'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
        $trashId = $data['trashId'] ?? null;

        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'trash_list']);
        $trashNames = array_column($data['items'] ?? [], 'trashId');
        $tests2 .= testCheck("trash_list: the deleted item appears in trash", ($code === 200 && $trashId !== null && in_array($trashId, $trashNames, true)) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

        if ($trashId !== null) {
            [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
                'action' => 'restore', 'trashId' => $trashId,
            ]);
            $tests2 .= testCheck("restore: brings the deleted file back", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
            $restoredName = $data['name'] ?? $duplicateName;

            // permanent delete: soft-delete again, then trash_delete
            [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
                'action' => 'delete', 'path' => $testRoot, 'name' => $restoredName, 'target' => 'file',
            ]);
            $trashId2 = $data['trashId'] ?? null;
            if ($trashId2 !== null) {
                [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
                    'action' => 'trash_delete', 'trashId' => $trashId2,
                ]);
                $tests2 .= testCheck("trash_delete: permanently removes the trashed item", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

                [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
                    'action' => 'restore', 'trashId' => $trashId2,
                ]);
                $tests2 .= testCheck("restore: a permanently-deleted trashId can no longer be restored", $code === 404 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
            }
        }
    }

    // --- security / negative-path checks ---------------------------------

    // 19) wrong CSRF token on a state-changing action
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, [
        'area' => $fmArea, 'id' => $fmId, 'csrf' => 'not-the-real-token',
        'action' => 'mkdir', 'name' => 'should_not_be_created', 'path' => $testRoot,
    ]);
    $tests2 .= testCheck("security: a bad CSRF token is rejected on mkdir", $code === 403 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 20) path traversal on an otherwise-valid read-only action
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'list', 'path' => '../../../../etc',
    ]);
    $tests2 .= testCheck("security: path traversal in 'path' is rejected", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 21) invalid area value
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, [
        'area' => 'not-a-real-area', 'id' => $fmId, 'csrf' => $csrf, 'action' => 'list', 'path' => '',
    ]);
    $tests2 .= testCheck("security: an unrecognized area value is rejected", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 22) Old files is read-only - a mutating action there is refused regardless of ownership
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, [
        'area' => FM_AREA_OLD, 'id' => $fmId, 'csrf' => $csrf,
        'action' => 'mkdir', 'name' => 'nope', 'path' => '',
    ]);
    $tests2 .= testCheck("security: Old files refuses a mutating action even for its owner", $code === 403 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 23) unauthenticated request (no cookie) is refused
    [$code, $data] = fm_test_call($apiUrl, '', $isLocalHost, [
        'area' => $fmArea, 'id' => $fmId, 'action' => 'list', 'path' => '',
    ]);
    $tests2 .= testCheck("security: a request with no session cookie is refused", $code === 403 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- setup for the extra test groups below --------------------------
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'mkdir', 'name' => 'extra', 'path' => $testRoot]);
    $tests2 .= testCheck("setup: create 'extra' scratch subfolder", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'mkdir', 'name' => 'extra2', 'path' => $testRoot]);
    $tests2 .= testCheck("setup: create 'extra2' scratch subfolder", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- upload rejection paths -------------------------------------------

    // 24) disallowed extension
    [$code, $data] = fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra', 'malware.exe', $contents);
    $rejectedExt = $code === 200 && empty($data['results'][0]['ok']) && ($data['results'][0]['error'] ?? '') === 'File type not allowed';
    $tests2 .= testCheck("upload: a disallowed extension (.exe) is rejected", $rejectedExt ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 25) extension allowed but content doesn't match it (spoofed image)
    [$code, $data] = fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra', 'fake.jpg', $contents);
    $rejectedContent = $code === 200 && empty($data['results'][0]['ok']) && ($data['results'][0]['error'] ?? '') === 'Content does not match file type';
    $tests2 .= testCheck("upload: plain text renamed to .jpg is rejected by the content check", $rejectedContent ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- onConflict=replace (upload / move / copy) --------------------------

    // 26) upload onConflict=replace keeps the exact name instead of numbering
    $contentA = "replace-test-original-" . bin2hex(random_bytes(4));
    $contentB = "replace-test-REPLACEMENT-CONTENT-" . bin2hex(random_bytes(4));
    fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra', 'replace.txt', $contentA);
    [$code, $data] = fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra', 'replace.txt', $contentB, 'replace');
    $replaceOk = $code === 200 && !empty($data['results'][0]['ok']) && $data['results'][0]['name'] === 'replace.txt';
    $tests2 .= testCheck("upload: onConflict=replace keeps the original name (no numbering)", $replaceOk ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'list', 'path' => $testRoot . '/extra']);
    $replacedSizeOk = false;
    foreach ($data['files'] ?? [] as $f) {
        if ($f['name'] === 'replace.txt') {
            $replacedSizeOk = ((int) $f['size']) === strlen($contentB);
        }
    }
    $tests2 .= testCheck("upload: onConflict=replace actually overwrites the content", $replacedSizeOk ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'trash_list']);
    $replacedWentToTrash = false;
    foreach ($data['items'] ?? [] as $it) {
        if (($it['name'] ?? '') === 'replace.txt') {
            $replacedWentToTrash = true;
        }
    }
    $tests2 .= testCheck("upload: onConflict=replace soft-deletes the item it overwrote (undoable)", $replacedWentToTrash ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // 27) move onConflict=replace keeps the destination's exact name
    fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra', 'moveTarget.txt', "mover-content");
    fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra2', 'moveTarget.txt', "original-destination-content");
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'move', 'path' => $testRoot . '/extra', 'name' => 'moveTarget.txt', 'target' => 'file',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/extra2', 'onConflict' => 'replace',
    ]);
    $tests2 .= testCheck("move: onConflict=replace keeps the destination's exact name", ($code === 200 && ($data['name'] ?? '') === 'moveTarget.txt') ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'list', 'path' => $testRoot . '/extra2']);
    $moveReplacedOk = false;
    foreach ($data['files'] ?? [] as $f) {
        if ($f['name'] === 'moveTarget.txt') {
            $moveReplacedOk = ((int) $f['size']) === strlen("mover-content");
        }
    }
    $tests2 .= testCheck("move: onConflict=replace overwrites the destination's content", $moveReplacedOk ? "PASS" : "FAIL", $passCounter2, $totalCounter2);

    // 28) copy onConflict=replace - same idea, but the source must remain
    fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra', 'copyTarget.txt', "copy-source-content");
    fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $base, $testRoot . '/extra2', 'copyTarget.txt', "copy-destination-content");
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'copy', 'path' => $testRoot . '/extra', 'name' => 'copyTarget.txt', 'target' => 'file',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/extra2', 'onConflict' => 'replace',
    ]);
    $tests2 .= testCheck("copy: onConflict=replace keeps the destination's exact name", ($code === 200 && ($data['name'] ?? '') === 'copyTarget.txt') ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'list', 'path' => $testRoot . '/extra']);
    $sourceStillThere = in_array('copyTarget.txt', array_column($data['files'] ?? [], 'name'), true);
    $tests2 .= testCheck("copy: source file is left in place (unlike move)", $sourceStillThere ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- CSRF spot-checks on other mutating actions --------------------------

    // 29-30) mkdir was already covered above - check delete and move too,
    // since each action independently re-derives the CSRF check.
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, [
        'area' => $fmArea, 'id' => $fmId, 'csrf' => 'not-the-real-token',
        'action' => 'delete', 'name' => 'replace.txt', 'target' => 'file', 'path' => $testRoot . '/extra',
    ]);
    $tests2 .= testCheck("security: a bad CSRF token is rejected on delete", $code === 403 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, [
        'area' => $fmArea, 'id' => $fmId, 'csrf' => 'not-the-real-token',
        'action' => 'move', 'name' => 'replace.txt', 'target' => 'file', 'path' => $testRoot . '/extra',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/extra2',
    ]);
    $tests2 .= testCheck("security: a bad CSRF token is rejected on move", $code === 403 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- 404s for a missing source / missing destination ---------------------

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'rename', 'path' => $testRoot, 'old' => 'does-not-exist.txt', 'new' => 'x.txt', 'target' => 'file',
    ]);
    $tests2 .= testCheck("rename: 404s on a missing source", $code === 404 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'delete', 'path' => $testRoot, 'name' => 'does-not-exist.txt', 'target' => 'file',
    ]);
    $tests2 .= testCheck("delete: 404s on a missing source", $code === 404 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'move', 'path' => $testRoot . '/extra', 'name' => 'does-not-exist.txt', 'target' => 'file',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/extra2',
    ]);
    $tests2 .= testCheck("move: 404s on a missing source", $code === 404 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'copy', 'path' => $testRoot . '/extra', 'name' => 'does-not-exist.txt', 'target' => 'file',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/extra2',
    ]);
    $tests2 .= testCheck("copy: 404s on a missing source", $code === 404 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'move', 'path' => $testRoot . '/extra', 'name' => 'irrelevant.txt', 'target' => 'file',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/does-not-exist-folder',
    ]);
    $tests2 .= testCheck("move: 404s on a missing destination folder", $code === 404 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'copy', 'path' => $testRoot . '/extra', 'name' => 'irrelevant.txt', 'target' => 'file',
        'toArea' => $fmArea, 'toId' => $fmId, 'toPath' => $testRoot . '/does-not-exist-folder',
    ]);
    $tests2 .= testCheck("copy: 404s on a missing destination folder", $code === 404 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- target ('file'|'folder') mismatch on delete --------------------------

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'delete', 'path' => $testRoot, 'name' => 'sub', 'target' => 'file',
    ]);
    $tests2 .= testCheck("delete: target='file' against an actual folder is rejected (400)", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'delete', 'path' => $testRoot . '/extra', 'name' => 'replace.txt', 'target' => 'folder',
    ]);
    $tests2 .= testCheck("delete: target='folder' against an actual file is rejected (400)", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- empty-items edge cases -------------------------------------------

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'check_conflicts', 'path' => $testRoot, 'items' => '[]',
    ]);
    $tests2 .= testCheck("check_conflicts: an empty selection returns an empty (not error) result", ($code === 200 && ($data['conflicts'] ?? null) === []) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'download_zip', 'path' => $testRoot, 'items' => '[]',
    ]);
    $tests2 .= testCheck("download_zip: an empty selection is rejected (400)", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- unknown action ------------------------------------------------------

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'not_a_real_action', 'path' => '']);
    $tests2 .= testCheck("an unrecognized action is rejected (400)", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- Old files: empty-state listing is graceful, not an error --------------

    $fakeOldId = 'unittest_old_' . bin2hex(random_bytes(4)); // guaranteed to have no legacy folder
    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, [
        'area' => FM_AREA_OLD, 'id' => $fakeOldId, 'csrf' => $csrf, 'action' => 'list', 'path' => '',
    ]);
    $oldEmptyOk = $code === 200 && ($data['folders'] ?? null) === [] && ($data['files'] ?? null) === [] && ($data['trashCount'] ?? null) === 0;
    $tests2 .= testCheck("Old files: listing a user with no legacy folder returns an empty result, not an error", $oldEmptyOk ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- geturl: folder-level links -----------------------------------------

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'geturl', 'path' => $testRoot, 'name' => 'sub', 'target' => 'folder', 'level' => 'link', 'mode' => 'index',
    ]);
    $tests2 .= testCheck("geturl: folder link in 'index' mode", ($code === 200 && !empty($data['ok']) && ($data['mode'] ?? '') === 'index') ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'geturl', 'path' => $testRoot, 'name' => 'sub', 'target' => 'folder', 'level' => 'link', 'mode' => 'gallery',
    ]);
    $tests2 .= testCheck("geturl: folder link in 'gallery' mode", ($code === 200 && !empty($data['ok']) && ($data['mode'] ?? '') === 'gallery') ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

    // --- download_zip: verify it actually streams back real zip bytes ---------

    [$code, , $raw] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'download_zip', 'path' => $testRoot . '/extra',
        'items' => json_encode([['name' => 'replace.txt', 'target' => 'file']]),
    ]);
    $tests2 .= testCheck("download_zip: a single file selection streams back a real zip (PK signature)", ($code === 200 && substr((string) $raw, 0, 2) === 'PK') ? "PASS" : "FAIL: HTTP $code", $passCounter2, $totalCounter2);

    [$code, , $raw] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + [
        'action' => 'download_zip', 'path' => $testRoot,
        'items' => json_encode([['name' => 'sub', 'target' => 'folder']]),
    ]);
    $tests2 .= testCheck("download_zip: a folder selection (recursive) streams back a real zip (PK signature)", ($code === 200 && substr((string) $raw, 0, 2) === 'PK') ? "PASS" : "FAIL: HTTP $code", $passCounter2, $totalCounter2);

    // --- Page files (area=pub): only runs if this admin has an actual page
    // to test against, discovered via search_pages rather than assumed. ---

    [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $base + ['action' => 'search_pages', 'ability' => 'filemanager_view']);
    $tests2 .= testCheck("search_pages: returns a page list for the destination picker", ($code === 200 && is_array($data['pages'] ?? null)) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    $realPageId = (!empty($data['pages'][0]['id'])) ? (string) $data['pages'][0]['id'] : null;

    if ($realPageId === null) {
        $tests2 .= '<p><em>No page this admin can act on was found via search_pages - skipping the Page files (area=pub) tests below. This is expected on a brand-new install with no pages yet.</em></p>';
    } else {
        $pubFolder = 'unit_test_pub_' . bin2hex(random_bytes(6));
        $pubBase = ['area' => FM_AREA_PUBLIC, 'id' => $realPageId, 'csrf' => $csrf, 'pageid' => $realPageId];

        register_shutdown_function(function () use ($apiUrl, $cookieHeader, $isLocalHost, $pubBase, $pubFolder) {
            // Soft-delete the public scratch folder (if still present), then
            // permanently delete only trash entries that originated under it.
            fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $pubBase + [
                'action' => 'delete', 'name' => $pubFolder, 'target' => 'folder', 'path' => '',
            ]);
            fm_test_purge_scratch_trash($apiUrl, $cookieHeader, $isLocalHost, $pubBase, $pubFolder);
        });

        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $pubBase + ['action' => 'mkdir', 'name' => $pubFolder, 'path' => '']);
        $tests2 .= testCheck("Page files (pub): mkdir under page $realPageId", ($code === 200 && !empty($data['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

        [$code, $data] = fm_test_upload_text($apiUrl, $cookieHeader, $isLocalHost, $pubBase, $pubFolder, 'pubtest.txt', "page files test content");
        $tests2 .= testCheck("Page files (pub): upload into the page's folder", ($code === 200 && !empty($data['results'][0]['ok'])) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $pubBase + ['action' => 'list', 'path' => $pubFolder]);
        $tests2 .= testCheck("Page files (pub): uploaded file is listed", ($code === 200 && in_array('pubtest.txt', array_column($data['files'] ?? [], 'name'), true)) ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);

        // 'private' (My eyes only) links only make sense for My files - confirm
        // they're refused here even though this admin owns everything.
        [$code, $data] = fm_test_call($apiUrl, $cookieHeader, $isLocalHost, $pubBase + [
            'action' => 'geturl', 'path' => $pubFolder, 'name' => 'pubtest.txt', 'target' => 'file', 'level' => 'private',
        ]);
        $tests2 .= testCheck("geturl: 'private' level is refused outside My files (Page files)", $code === 400 ? "PASS" : "FAIL: HTTP $code " . json_encode($data), $passCounter2, $totalCounter2);
    }

    @unlink($tmpFile);

    echo '
        <li>
            <h2 class="title">File Manager: Live API Tests ' . $passCounter2 . '/' . $totalCounter2 . '</h2>
            <div>
                <p><em>Ran against <code>' . htmlspecialchars($apiUrl, ENT_QUOTES) . '</code>, scratch folder <code>' . htmlspecialchars($testRoot, ENT_QUOTES) . '</code> (auto-deleted when this page finishes).</em></p>
                ' . $tests2 . '
            </div>
        </li>';
}