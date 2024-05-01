<?php
/**
 * This file is part of the Syxton CMS.
 *
 *  New Google style members search page - features/adminpanel/members.php
 * -------------------------------------------------------------------------
 * Author: Matthew Davidson
 * Date: 8/19/2010
 * Revision: 0.0.2
 ***************************************************************************/

global $CFG;
?>
<div class="members">
    <div class="members_searchform">
        <h2>
            Search the Member Directory
        </h2>
        <a class="search_links" href="#" onclick="$('#instructions').toggle();">
            Show/Hide Instructions
        </a>
        <div id="instructions">
            <div>
                <b>By Name:</b>
                <code>
                    /n Joe
                </code>
                Default search. "/n" only needed if following another tag.
            </div>
            <div>
                <b>Field Search:</b>
                <code>
                    /f department like parent
                </code>
                /f :fieldname: (=, !=, &gt;, &lt;, &gt;=, &lt;=, or like) :value:
            </div>
            <div>
                <b>Sort:</b>
                <code>
                    /s -joined
                </code>
                /s (+ for ascending - for decending) :fieldname:
            </div>
        </div>
        <br />
        <a class="search_links" href="#" onclick="$('#allfields').toggle();">
            Show/Hide Available fields
        </a>
        <span id="allfields">
            <?php
            // Get list of all fields that can be searched on.
            if ($result = get_db_result("SHOW COLUMNS FROM users")) {
                echo "<select>";
                while ($field = fetch_row($result)) {
                    echo '<option>' . $field["Field"] . '</option>';
                }
                echo "</select>";
            }
            ?>
        </span>
        <div id="mem_searchbardiv">
            Common Searches:
            <a href="#" 
                onclick="$('#waiting_span').show(); 
                        ajaxapi('/features/adminpanel/members_script.php',
                                'members_search',
                                '&search=/s -joined',
                                function() {
                                if (xmlHttp.readyState == 4) { 
                                    simple_display('mem_resultsdiv');
                                    $('#waiting_span').hide();
                                }
                            }, true);">
                New Members
            </a>
            <a href="#" 
                onclick="$('#waiting_span').show();
                        ajaxapi('/features/adminpanel/members_script.php',
                                'members_search',
                                '&search=/s -last_activity',
                                function() {
                                    if (xmlHttp.readyState == 4) {
                                        simple_display('mem_resultsdiv');
                                        $('#waiting_span').hide();
                                    }
                                }, true);">
                Last Accessed
            </a>
            <br />
            <input type="text"
                   id="searchbox"
                   size="50"
                   onkeypress="if (event.keyCode == 13 || event.which == 13) {
                       $('.members_searchbutton').click();
                   }" />
            &nbsp;
            <input class="members_searchbutton"
                   type="button"
                   value="Search"
                   onclick="$('#waiting_span').show();
                            ajaxapi('/features/adminpanel/members_script.php',
                                    'members_search',
                                    '&search=' + escape($('#searchbox').val()),
                                    function() {
                                        if (xmlHttp.readyState == 4) {
                                            simple_display('mem_resultsdiv');
                                            $('#waiting_span').hide();
                                        }
                                    }, true);" />
        </div>
    </div>
    <div style="display:none;" id="waiting_span">
        <img src="<?php echo $CFG->wwwroot; ?>/images/indicator.gif" />
    </div>
    <div id="mem_resultsdiv"></div>
    <div id="mem_debug"></div>
</div>
<style>
.members {
    text-align: center;
    border: none;
}

.members_searchbutton {
    margin-right: 0;
}

.members_searchform {
    display: inline-block;
    margin-right: auto;
    margin-left: auto;
}

#mem_resultsdiv {
    text-align: left;
    padding: 10px 20px;
}

#mem_searchbardiv {
    text-align: left;
    font-size: .75em;
    margin-top: 10px;
}

#instructions,
#allfields {
    font-size: .75em;
    display: none;
    text-align: left
}

#instructions code {
    color: blue;
    padding: 0 5px;
}

.search_links {
    font-size: .75em;
}
</style>
