<?php
/**************************************************************************
* db.php - feature db upgrades
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.3
***************************************************************************/

function pics_upgrade() {
	global $CFG;
    $version = get_db_field("version", "features", "feature='pics'");
	$thisversion = 20100819;
	if ($version < $thisversion) {
		execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='pics'");
	}

	// 2026-09-25: move gallery files from userfiles/pics/files/... into the
	// gated area 'pics' tree at {fmroot}/pics/{pageid}/files/{featureid}/...
	$thisversion = 20260925;
	if ($version < $thisversion) {
		if (!defined('PICSLIB')) {
			include_once($CFG->dirroot . '/features/pics/picslib.php');
		}
		if (!defined('FMCONFIG')) {
			include_once($CFG->dirroot . '/filegatelib.php');
		}

		$summary = pics_migrate_to_filegate();
		$ok = empty($summary['errors']);

		// Log a compact summary so site admins can see what happened.
		error_log(sprintf(
			'pics_migrate_to_filegate: moved=%d skipped=%d missing=%d errors=%d',
			$summary['moved'],
			$summary['skipped'],
			$summary['missing'],
			count($summary['errors'])
		));
		foreach ($summary['errors'] as $err) {
			error_log('pics_migrate_to_filegate error: ' . $err);
		}

		// Advance the version even if some individual files failed (e.g. already
		// missing on disk) so the upgrade doesn't retry forever. Re-running
		// pics_migrate_to_filegate() manually remains safe if needed.
		if ($ok || ($summary['moved'] + $summary['skipped'] + $summary['missing']) > 0) {
			execute_db_sql("UPDATE features SET version='$thisversion' WHERE feature='pics'");
		}
	}

	return upgrade_occured('Pics feature', $version, $thisversion);
}


/**
 * One-time upgrade: move existing pics files from the legacy web-accessible
 * userfiles/pics/files/{pageid}/{featureid}/ tree into the gated
 * {fmroot}/pics/{pageid}/files/{featureid}/ location.
 *
 * Safe to re-run: skips files already at the destination, and only deletes
 * the source after a successful copy. Invoked from pics_upgrade() in db.php
 * when the feature version is bumped past the migration threshold.
 */
function pics_migrate_to_filegate(): array {
    global $CFG;

    $summary = [
        'moved'   => 0,
        'skipped' => 0,
        'missing' => 0,
        'errors'  => [],
        'details' => [],
    ];

    $legacyRoot = rtrim($CFG->userfilespath, '/\\') . DIRECTORY_SEPARATOR . 'pics' . DIRECTORY_SEPARATOR . 'files';
    if (!is_dir($legacyRoot)) {
        $summary['errors'][] = "Legacy root does not exist: $legacyRoot";
        return $summary;
    }

    $result = get_db_result("SELECT picsid, pageid, featureid, imagename FROM pics");
    if (!$result) {
        return $summary;
    }

    while ($row = fetch_row($result)) {
        $pageid    = (string) $row['pageid'];
        $featureid = (string) $row['featureid'];
        $imagename = $row['imagename'];
        $legacy    = $legacyRoot . DIRECTORY_SEPARATOR . $pageid . DIRECTORY_SEPARATOR . $featureid . DIRECTORY_SEPARATOR . $imagename;

        if (!is_file($legacy)) {
            $summary['missing']++;
            $summary['details'][] = "Missing legacy file for picsid={$row['picsid']}: $legacy";
            continue;
        }

        $destDir = pics_disk_dir($pageid, $featureid);
        if ($destDir === null) {
            $summary['errors'][] = "Could not create destination dir for pageid=$pageid featureid=$featureid";
            continue;
        }
        $dest = $destDir . DIRECTORY_SEPARATOR . $imagename;

        if (is_file($dest)) {
            // Already migrated (or identical name exists). Optionally still remove legacy.
            $summary['skipped']++;
            // Uncomment the next two lines if you want to clean up legacy copies on re-run:
            // @unlink($legacy);
            continue;
        }

        if (!@copy($legacy, $dest)) {
            $summary['errors'][] = "Copy failed: $legacy -> $dest";
            continue;
        }
        // Preserve mtime so any pre-generated tokens (if any) stay valid a bit longer,
        // and so the migration itself doesn't immediately invalidate links that
        // might have been issued against the new path with the same mtime.
        $mtime = @filemtime($legacy);
        if ($mtime) {
            @touch($dest, $mtime);
        }
        if (!@unlink($legacy)) {
            $summary['errors'][] = "Copied but could not remove legacy: $legacy";
        }
        $summary['moved']++;
        $summary['details'][] = "Moved picsid={$row['picsid']}: $imagename";
    }

    // Prune empty legacy directories (best-effort).
    pics_migrate_prune_empty_dirs($legacyRoot);

    return $summary;
}

/** Recursively remove empty directories under $dir (does not remove $dir itself). */
function pics_migrate_prune_empty_dirs(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $entries = @scandir($dir);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            pics_migrate_prune_empty_dirs($path);
            @rmdir($path); // only succeeds if now empty
        }
    }
}
?>
