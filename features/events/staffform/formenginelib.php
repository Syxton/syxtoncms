<?php
/***************************************************************************
 * SHIM: form engine lives in core lib/formengine/
 * Events staffform keeps this path for backward-compatible includes.
 ***************************************************************************/
if (!defined('FORMENGINELIB')) {
    global $CFG;
    $core = '';
    if (!empty($CFG->dirroot)) {
        $core = $CFG->dirroot . '/lib/formengine/formenginelib.php';
    }
    if ($core === '' || !file_exists($core)) {
        // features/events/staffform → ../../../lib/formengine
        $core = dirname(__DIR__, 3) . '/lib/formengine/formenginelib.php';
    }
    if (file_exists($core)) {
        include_once($core);
    } else {
        define('FORMENGINELIB', true);
    }
}
