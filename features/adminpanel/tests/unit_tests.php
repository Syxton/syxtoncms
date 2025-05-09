<?php
/***************************************************************************
 * temp.php: Templating system tests.
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 6/07/2016
 * Revision: 1.0.2
 ***************************************************************************/

header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.

include ('../../header.php');

global $CFG, $USER;

$CFG->debugoverride = true;
$CFG->debug = 3;

if (isset($CFG->downtime) && $CFG->downtime === true && !strstr($CFG->safeip, ',' . get_ip_address() . ',')) {
    include($CFG->dirroot . $CFG->alternatepage);
} else {
    ///////////////////////////////////////////// TESTING SETUP /////////////////////////////////////////////
    echo get_css_set("main");
    echo get_js_set("main");

    echo "<style>
        .unit_test_menu li {
            cursor: pointer;
        }
        .unit_test_menu li .title {
            padding: 5px;
        }
        .unit_test_menu li .title:hover {
            background-color: #D3D3D3;
        }
        .unit_test_menu li div {
            font-weight: initial;
            padding: 2px 10px;
        }
        .unit_test_menu li .title:hover {
            background-color: #D3D3D3;
        }
        .unit_test_menu li.open .title {
            background-color: silver;
        }
        .unit_test_menu {
            list-style-type: none;
            margin: 0;
            padding: 0;
        }
        </style>";

    echo '<div style="padding: 0px 20px;">';

    if (!is_siteadmin($USER->userid)) {
        trigger_error(getlang("generic_permissions", false, ["administrative"]), E_USER_WARNING);
        return;
    }

    echo '
        <ul class="unit_test_menu">';

    ///////////////////////////////////////////// Variable TESTS /////////////////////////////////////////////
    include ('test_vars.php');

    ///////////////////////////////////////////// TEMPLATE TESTS /////////////////////////////////////////////
    include ('test_templates.php');

    ///////////////////////////////////////////// DATABASE TESTS /////////////////////////////////////////////
    include ('test_database.php');

    echo "</ul>";

    echo '
    <script type="text/javascript">
        $(window).on("load", function() {
            $(".unit_test_menu li div").hide();
            $(".unit_test_menu li").on("click", function() {
                $(".unit_test_menu li div").hide("slow");
                if ($(this).hasClass("open")) {
                    $(this).removeClass("open");
                } else {
                    $(".unit_test_menu li").removeClass("open");
                    $(this).addClass("open");
                    $(this).find("div").show("slow");
                }
            });
        });
    </script>';
}
?>
