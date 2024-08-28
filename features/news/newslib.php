<?php
/***************************************************************************
* newslib.php - News function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.8.3
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('NEWSLIB', true);

//NEWSLIB Config
$CFG->news = new \stdClass;
$CFG->news->maxlength = 500;
$CFG->news->modalheight = 600;
$CFG->news->modalwidth = 640;

function display_news($pageid, $area, $featureid=false) {
global $CFG, $USER, $ROLES;
if (!$pageid) { $pageid = $CFG->SITEID; }
$returnme = ''; $section_content = ""; $toggle = "";

    $main_section = get_db_row("SELECT * FROM news_features WHERE featureid=" . $featureid);
    if (!$settings = fetch_settings("news", $featureid, $pageid)) {
        save_batch_settings(default_settings("news", $pageid, $featureid));
        $settings = fetch_settings("news", $featureid, $pageid);
    }

    $limit = $settings->news->$featureid->limit_viewable->setting;
    $title = $settings->news->$featureid->feature_title->setting;

    if (!is_logged_in()) { //If the user is not signed in
        if (role_is_able($ROLES->visitor, 'viewnews', $pageid)) { //Has ability to see the news items
                if ($area == "middle") {
                    if ($pagenews = get_section_news($featureid, "LIMIT " . $limit)) { //Gets the news from the given section
                        $i=0; $newdate=false;
                        foreach ($pagenews as $news) {
                            if (isset($news->content)) {
                                $daygraphic = !$newdate || date('j', $newdate) != date('j', $news->submitted) ? get_date_graphic($news->submitted, true) : get_date_graphic($news->submitted, false);
                                $newdate = $pagenews->$i->submitted;
                                $section_content .= make_news_table($pageid, $news, $area, $daygraphic);
                                $section = $news->featureid;
                            }
                        }
                    }
                }

                //Get the Archived News Area
                $section_content .= get_section_archives($pageid, $featureid, NULL, $area);
                if (empty($section_content)) { $section_content = "No news added yet"; }
                $buttons = get_button_layout("news_features", $featureid, $pageid);
                $title = '<span class="box_title_text">' . $title . '</span>';
                $returnme .= get_css_box($title, $section_content, $buttons, NULL, "news", $featureid);
            }

    } else { //User is signed in

        if (user_is_able($USER->userid, 'viewnews', $pageid)) {
            if (is_logged_in()) {
            $rss = make_modal_links([
                "title" => "News RSS Feed",
                "path" => action_path("rss", false) . "rss_subscribe_feature&feature=news&pageid=$pageid&featureid=$featureid",
                "iframe" => true,
                "refresh" => "true",
                "width" => "640",
                "icon" => icon([
                    ["icon" => "square", "stacksize" => 2, "color" => "white"],
                    ["icon" => "square-rss"],
                ]),
            ]);
      }

            if ($area == "middle") {
                if ($pageid == $CFG->SITEID) { //This is the site page
                    $returnme .= '';
                    if ($pages = get_users_news_pages($USER->userid, "LIMIT $limit")) {
                        if ($pagenews = get_pages_news($pages, "LIMIT $limit")) {
                            $newdate=false;
                            foreach ($pagenews as $news) {
                                if (isset($news->content)) {
                                    $daygraphic = !$newdate || date('j', $newdate) != date('j', $news->submitted) ? get_date_graphic($news->submitted, true) : get_date_graphic($news->submitted, false);
                                      $newdate = $news->submitted;
                                      $section_content .= make_news_table($pageid, $news, $area, $daygraphic);
                                      $section = $news->featureid;
                                }
                            }
                        }
                    }
                } else { //This is for any page other than site
                    if ($pagenews = get_section_news($featureid, "LIMIT " . $limit)) {
                        $newdate=false;
                        foreach ($pagenews as $news) {
                            $daygraphic = !$newdate || date('j', $newdate) != date('j', $news->submitted) ? get_date_graphic($news->submitted, true) : get_date_graphic($news->submitted, false);
                            $newdate = $news->submitted;
                            $section_content .= make_news_table($pageid, $news, $area, $daygraphic);
                            $section = $news->featureid;
                        }
                    }
                }
            }
            $buttons = get_button_layout("news_features", $featureid, $pageid);
            //Get the Archived News Area
            $section_content .= get_section_archives($pageid, $featureid, $USER->userid, $area);
            if (empty($section_content)) { $section_content = "No news added yet"; }
            $title = '<span class="box_title_text">' . $title . '</span>';
            $returnme .= get_css_box($rss . $title, $section_content, $buttons, NULL, "news", $featureid);
        }
    }
    return $toggle . $returnme;
}

function make_news_table($pageid, $pagenews, $area, $daygraphic, $standalone = false) {
global $CFG;
    $buttons = $standalone ? '' : get_button_layout("news", $pagenews->newsid, $pagenews->pageid);
    $user = get_db_row("SELECT * FROM users where userid = " . $pagenews->userid);
    $link = make_modal_links([
        "text" => "Read Article",
        "button" => true,
        "title"=> stripslashes(htmlentities($pagenews->title)),
        "icon" => icon("book-open-reader", 2),
        "path" => action_path("news") . "viewnews&newsonly=1&pageid=$pageid&newsid=$pagenews->newsid",
        "width" => "98%",
        "height" => "95%",
    ]);
    $readmore = "";
    if (!$standalone && !empty(stripslashes($pagenews->content))) {
        $readmore = '<span class="readmore">' . $link . '</span>';
    }

    $captionlength = 50;
    $graphicdate = '';
    if ($area == "middle") {
        $captionlength = 350;
        $graphicdate = $daygraphic;
    }

    $params = [
        "graphic" => $graphicdate,
        "title" => $pagenews->title,
        "summary" => truncate($pagenews->content, $captionlength),
        "readmore" => $readmore,
        "buttons" => $buttons,
        "ago" => ago($pagenews->submitted),
        "author" => $user['fname'] . ' ' . $user['lname'],
    ];
    return fill_template("tmp/news.template", "news_table", "news", $params);
}

function get_users_news_pages($userid, $limit="", $site=true) {
global $CFG;
    $includesite = $site ? "" : " WHERE ns.pageid !=" . $CFG->SITEID;
    if (is_siteadmin($userid)) {
        $SQL = "
        SELECT DISTINCT ns.pageid,ns.lastupdate FROM news_features ns
        INNER JOIN pages_features pf on pf.pageid=ns.pageid AND pf.feature='news' AND pf.featureid=ns.featureid
        $includesite
         ORDER BY ns.pageid,ns.lastupdate DESC $limit";
    } else {
          $SQL = "
          SELECT DISTINCT ns.pageid,ns.lastupdate FROM news_features ns
          INNER JOIN roles_assignment ra ON ra.userid=$userid AND ra.pageid = ns.pageid AND confirm=0
          INNER JOIN roles_ability ry ON ry.roleid=ra.roleid AND ry.ability='viewnews' AND allow='1'
          INNER JOIN pages_features pf on pf.pageid=ns.pageid AND pf.feature='news' AND pf.featureid=ns.featureid
          $includesite
           ORDER BY ns.pageid,ns.lastupdate DESC $limit";
    }
    return get_db_result($SQL);
}

function get_section_archives($pageid, $featureid, $userid = false, $area = "middle") {
global $CFG;
    $zero = 0;

    if ($pagenews = get_all_news($userid, $pageid, $featureid)) {
        // Gather all the data.
        $years = years_with_news($userid, $pagenews);
        $months = months_with_news($userid, $years->$zero->year, $pagenews);
        $lastrow = get_array_count($months) - 1;

        // Update month drop down list on year change.
        ajaxapi([
            "id" => "news_" . $featureid . "_archive_year",
            "url" => "/features/news/news_ajax.php",
            "data" => [
                "action" => "update_archive_months",
                "featureid" => $featureid,
                "pageid" => $pageid,
                "year" => "js||$('#news_" . $featureid . "_archive_year').val()||js",
            ],
            "event" => "change",
            "display" => "month_span_" . $featureid . "_archive",
            "ondone" => "$('#news_" . $featureid . "_archive_month').trigger('change');",
        ]);

        // Update article drop down list on month change.
        ajaxapi([
            "id" => "news_" . $featureid . "_archive_month",
            "url" => "/features/news/news_ajax.php",
            "data" => [
                "action" => "update_archive_articles",
                "featureid" => $featureid,
                "pageid" => $pageid,
                "year" => "js||$('#news_" . $featureid . "_archive_year').val()||js",
                "month" => "js||$('#news_" . $featureid . "_archive_month').val()||js",
            ],
            "event" => "change",
            "display" => "article_span_" . $featureid . "_archive",
        ]);

        // Create year drop down.
        $yearselector = make_select([
            "properties" => [
                "name" => "news_" . $featureid . "_archive_year",
                "id" => "news_" . $featureid . "_archive_year",
                "style" => "font-size:.8em;",
            ],
            "values" => $years,
            "valuename" => "year",
            "selected" => date('Y', get_timestamp()),
        ]);

        // Create month drop down.
        $monthselector = make_select([
            "properties" => [
                "name" => "news_" . $featureid . "_archive_month",
                "id" => "news_" . $featureid . "_archive_month",
                "style" => "font-size:.8em;",
            ],
            "values" => $months,
            "valuename" => "month",
            "displayname" => "monthname",
            "selected" => $months->$lastrow->month,
        ]);

        // Create article drop down.
        $articleselector = make_select([
            "properties" => [
                "name" => "news_" . $featureid . "_archive_news",
                "id" => "news_" . $featureid . "_archive_news",
                "style" => "font-size:.8em;",
            ],
            "values" => get_month_news($userid, $years->$zero->year, $months->$lastrow->month, $pagenews),
            "valuename" => "newsid",
            "displayname" => "title",
        ]);

        // Create article button.
        $showarticle = make_modal_links([
            "title" => "Get News",
            "text" => "Get News",
            "id" => "fetch_" . $featureid . "_button",
            "button" => true,
            "path" => action_path("news") . "viewnews&newsonly=1&pageid=$pageid&newsid=' + $('#news_" . $featureid . "_archive_news').val() + '&featureid=$featureid",
            "icon" => icon("newspaper"),
        ]);

        $params = [
            "featureid" => $featureid,
            "yearselector" => $yearselector,
            "monthselector" => $monthselector,
            "articleselector" => $articleselector,
            "showarticle" => $showarticle,
        ];
        return fill_template("tmp/news.template", "news_archive_template", "news", $params);
    }
    return "";
}

function get_array_count($array, $i = 0) {
    foreach ($array as $item) {
        $i++;
    }
    return $i;
}

function get_all_news($userid, $pageid, $featureid) {
global $CFG, $USER;
    $zero = 0;
    if ($userid) {
        if ($CFG->SITEID == $pageid) {
            $pages = get_users_news_pages($userid, NULL, true);
            $returnme = get_pages_news($pages);
            if (isset($returnme->$zero)) { return $returnme;
            } else { return false; }
        } else {
            $returnme = get_section_news($featureid);
            if (isset($returnme->$zero)) { return $returnme;
            } else { return false; }
        }
    } else {
        $returnme = get_section_news($featureid);
        if (isset($returnme->$zero)) { return $returnme;
        } else { return false; }
    }
}

function get_month_news($userid, $year, $month, $pagenews=false, $pageid =false, $featureid=false) {
    if (!$pagenews) { $pagenews = get_all_news($userid, $pageid, $featureid); }
    $y=$currentmonth=$last=$i=0; $first = false;
    if (isset($pagenews->$i)) {
        while (isset($pagenews->$i)) { //Find first and last month of given year that a news item was exists in pagenews set
            if (date("Y", $pagenews->$i->submitted) == $year && date("n", $pagenews->$i->submitted) == $month) {
                if ($first === false) { $first = $i; }
                $last = $i;
            }$i++;
        }
        if ($first !== false) {
            $firststamp = $pagenews->$first->submitted; $laststamp = $pagenews->$last->submitted;
            while ($first <= $last) {
                if (empty($returnme)) { $returnme = new \stdClass; }
                $returnme->$y = new \stdClass;
                $returnme->$y->title = $pagenews->$first->title;
                $returnme->$y->newsid = $pagenews->$first->newsid;
                $first++; $y++;
            }
            return $returnme;
        } else { return false; }
    } else { return false; }
}

function months_with_news($userid, $year, $pagenews=false, $pageid =false, $featureid=false) {
    if (!$pagenews) { $pagenews = get_all_news($userid, $pageid, $featureid);	}
    $last=$i=0; $first = false;
    if (isset($pagenews->$i)) {
        while (isset($pagenews->$i)) { //Find first and last month of given year that a news item was exists in pagenews set
            if (date("Y", $pagenews->$i->submitted) == $year) {
                if ($first === false) { $first = $i; }
                $last = $i;
            }$i++;
        }
        if ($first !== false) {
            //SWAP THEM SO THAT MONTHS WILL BE DISPLAYED FROM JANUARY TO DECEMBER INSTEAD OF BACKWARDS
            $firststamp = $pagenews->$first->submitted;
            $laststamp = $pagenews->$last->submitted;
            $temp = $first;$first = $last;$last = $temp;
            $firstmonth = date("n", $firststamp);
            $lastmonth = date("n", $laststamp);
            $y = 0; $currentmonth = 0;
            while ($firstmonth >= $lastmonth) {
                $beginmonth = mktime(0, 0, 0, $lastmonth, 1, $year);
                $daysinmonth = cal_days_in_month(CAL_GREGORIAN, $firstmonth, $year) + 1;
                $endmonth = mktime(0, 0, 0, $firstmonth, $daysinmonth, $year);
                $i=$first;
                while (isset($pagenews->$i)) {
                    if ($pagenews->$i->submitted >= $beginmonth && $pagenews->$i->submitted <= $endmonth) {
                        if (date("n", $pagenews->$i->submitted) > $currentmonth) {
                            $currentmonth = date("n", $pagenews->$i->submitted);
                            if (empty($returnme)) { $returnme = new \stdClass; }
                            $returnme->$y = new \stdClass;
                            $returnme->$y->month = $currentmonth;
                            $returnme->$y->monthname = date("F", $pagenews->$i->submitted);
                            break;
                        }
                    }
                    $i--;
                }
                $lastmonth++; $y++;
            }
            return $returnme;
        } else { return false; }
    }
    return false;
}

function years_with_news($userid, $pagenews=false, $pageid =false, $featureid=false) {
    if (!$pagenews) { $pagenews = get_all_news($userid, $pageid, $featureid);	}
    $zero=$last=0;
    if (isset($pagenews->$zero)) {
        foreach ($pagenews as $news) { $last++; } $last--; //counts news items -- count() doesn't work on objects)
        $first = $pagenews->$zero->submitted;
        $last = $pagenews->$last->submitted;
        $firstyear = date("Y", $last);
        $currentyear = date("Y", $first);
        $y = 0;
        while ($currentyear >= $firstyear) {
            $beginyear = mktime(0, 0, 0, 1, 1, $currentyear);
            $endyear = mktime(0, 0, 0, 12, 32, $currentyear);

            foreach ($pagenews as $news) {
                if ($news->submitted >= $beginyear && $news->submitted <= $endyear) {
                    if (empty($returnme)) { $returnme = new \stdClass; }
                    $returnme->$y = new \stdClass;
                    $returnme->$y->year = $currentyear;
                    $y++;
                    break;
                }
            }

            $currentyear--;
        }
        return $returnme;
    } else { return false; }
}

function get_section_news($featureid, $limit = "") {
global $CFG;
    $SQL = "SELECT * FROM news WHERE featureid='$featureid'	ORDER BY submitted DESC $limit";
    $i=0;
    if ($news_results = get_db_result($SQL)) {
        $news = new \stdClass;
        while ($row = fetch_row($news_results)) {
            $news->$i = new \stdClass;
            $news->$i->newsid = $row['newsid'];
            $news->$i->pageid = $row['pageid'];
            $news->$i->featureid = $row['featureid'];
            $news->$i->title = stripslashes($row['title']);
            $news->$i->content = stripslashes($row['content']);
            $news->$i->submitted = $row['submitted'];
            $news->$i->edited = $row['edited'];
            $news->$i->caption = stripslashes($row['caption']);
            $news->$i->userid = $row['userid'];
            $i++;
        }
    }

    if ($i == 0) return false;
    return $news;
}

function get_page_news($pageid, $limit = "") {
global $CFG;
    $sections = "";
    $SQL = "SELECT *
            FROM news_features
            WHERE pageid = '$pageid'
            ORDER BY lastupdate";

    if ($section_results = get_db_result($SQL)) {
        while ($section = fetch_row($section_results)) {
            $sections .= $sections == "" ? "featureid=" . $section['featureid'] : " OR featureid=" . $section['featureid'];
        }
    }
    if ($sections != "") {
        if (!$limit) {
            if (!$settings = fetch_settings("news", $featureid, $pageid)) {
                save_batch_settings(default_settings("news", $pageid, $featureid));
                $settings = fetch_settings("news", $featureid, $pageid);
            }

            $limit = "LIMIT " . $settings->news->$featureid->limit_viewable->setting;
        }

        $SQL = "SELECT *
                FROM news
                WHERE ($sections)
                ORDER BY submitted DESC
                $limit";
        $i=0;
        if ($news_results = get_db_result($SQL)) {
            while ($row = fetch_row($news_results)) {
                $news->$i->newsid = $row['newsid'];
                $news->$i->pageid = $row['pageid'];
                $news->$i->featureid = $row['featureid'];
                $news->$i->title = $row['title'];
                $news->$i->content = $row['content'];
                $news->$i->submitted = $row['submitted'];
                $news->$i->edited = $row['edited'];
                $news->$i->caption = $row['caption'];
                $news->$i->userid = $row['userid'];
                $i++;
            }
        }
        if ($i == 0) { return false; }
        return $news;
    }
    return false;
}

function get_pages_news($pages, $limit = "") {
global $CFG;
    $mypages = "";
    if ($pages) {
        while ($page = fetch_row($pages, "num")) {
            $mypages .= $mypages == "" ? '(pageid=' . $page[0] : ' OR pageid=' . $page[0];
        } $mypages .= ')';

        $SQL = "SELECT *
                            FROM news
                         WHERE $mypages
                    ORDER BY submitted DESC
                        $limit";

        $i=0;
        if ($news_results = get_db_result($SQL)) {
      $news = new \stdClass;
            while ($row = fetch_row($news_results)) {
        $news->$i = new \stdClass;
                $news->$i->newsid = $row['newsid'];
                $news->$i->pageid = $row['pageid'];
                $news->$i->featureid = $row['featureid'];
                $news->$i->title = $row['title'];
                $news->$i->content = $row['content'];
                $news->$i->submitted = $row['submitted'];
                $news->$i->edited = $row['edited'];
                $news->$i->caption = $row['caption'];
                $news->$i->userid = $row['userid'];
                $i++;
            }
        }
        if ($i == 0) { return false; }
        return $news;
    }
    return false;
}

function closetags($html) {
    $selfclosing = ',img,input,br,hr,';
    //put all opened tags into an array
    preg_match_all("#<([a-z]+)(?: .*)?(?<![/|/ ])>#iU", $html, $result);
    $openedtags=$result[1];
    //put all closed tags into an array
    preg_match_all("#</([a-z]+)>#iU", $html, $result);
    $closedtags=$result[1];
    $len_opened = count($openedtags);

    //all tags are closed
    if (count($closedtags) == $len_opened) { return $html; }

    $openedtags = array_reverse($openedtags);
    //close tags
    for ($i=0;$i < $len_opened;$i++) {
        $temp = $openedtags[$i];
        switch ($openedtags[$i]) {
            case strstr($selfclosing, ", $temp,"):
                break;
            default:
                if (!in_array($openedtags[$i], $closedtags)) {
                    $html .= '</' . $openedtags[$i] . '>';
                } else {
                    unset($closedtags[array_search($openedtags[$i], $closedtags)]);
                }
        }
    }
    return $html;
}

function news_delete($pageid, $featureid = false, $newsid = false) {
    if (empty($newsid)) { // News feature delete
        try {
            start_db_transaction();
            $sql = [];
            $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature"];
            $sql[] = ["file" => "dbsql/features.sql", "subsection" => "delete_feature_settings"];
            $sql[] = ["file" => "dbsql/news.sql", "feature" => "news", "subsection" => "delete_news_features"];
            $sql[] = ["file" => "dbsql/news.sql", "feature" => "news", "subsection" => "delete_all_news_items"];

            // Delete feature
            execute_db_sqls(fetch_template_set($sql), $params);

            resort_page_features($pageid);
            commit_db_transaction();
        } catch (\Throwable $e) {
            rollback_db_transaction($e->getMessage());
            return false;
        }
    } else { // News item delete
        try {
            start_db_transaction();
            execute_db_sql(fetch_template("dbsql/news.sql", "delete_news_item", "news"), ["newsid" => $newsid]);
            commit_db_transaction();
        } catch (\Throwable $e) {
            rollback_db_transaction($e->getMessage());
            return false;
        }
    }
}

function news_rss($feed, $userid, $userkey) {
global $CFG;
    $feeds = "";
    if ($feed["pageid"] == $CFG->SITEID && $userid) { //This is the site page for people who are members
        if ($pages = get_users_news_pages($userid, "LIMIT 50")) {
            if ($pagenews = get_pages_news($pages, "LIMIT 50")) {
                foreach ($pagenews as $news) {
                    if (isset($news->content)) {
                       $feeds .= fill_feed($news->title, strip_tags($news->caption), $CFG->wwwroot . '/features/news/news.php?action=viewnews&key=' . $userkey . '&pageid=' . $feed["pageid"] . '&newsid=' . $news->newsid, $news->submitted);
                     }
                }
            }
        }
    } else { // This is for any page other than site
        if ($pagenews = get_section_news($feed["featureid"], "LIMIT 50")) {
            foreach ($pagenews as $news) {
                if (isset($news->content)) {
                    $feeds .= fill_feed($news->title, strip_tags($news->caption), $CFG->wwwroot . '/features/news/news.php?action=viewnews&key=' . $userkey . '&pageid=' . $feed["pageid"] . '&newsid=' . $news->newsid, $news->submitted);
                }
            }
        }
    }
    return $feeds;
}

function insert_blank_news($pageid) {
    $type = "news";
    try {
        start_db_transaction();
        fetch_template("dbsql/news.sql", "insert_news_feature", "news");
        if ($featureid = execute_db_sql(fetch_template("dbsql/news.sql", "insert_news_feature", $type), ["pageid" => $pageid, "lastupdate" => get_timestamp()])) {
            $area = get_db_field("default_area", "features", "feature = ||feature||", ["feature" => $type]);
            $sort = get_db_count(fetch_template("dbsql/features.sql", "get_features_by_page_area"), ["pageid" => $pageid, "area" => $area]) + 1;
            $params = [
                "pageid" => $pageid,
                "feature" => $type,
                "featureid" => $featureid,
                "sort" => $sort,
                "area" => $area,
            ];
            execute_db_sql(fetch_template("dbsql/features.sql", "insert_page_feature"), $params);
            commit_db_transaction();
            return $featureid;
        }
    } catch (\Throwable $e) {
        rollback_db_transaction($e->getMessage());
    }
    return false;
}

function news_buttons($pageid, $featuretype, $featureid) {
global $CFG, $USER;
$return = "";
    if (strstr($featuretype, "_features")) { // Overall news feature.
        $return .= user_is_able($USER->userid, "addnews", $pageid) ? make_modal_links(["title"=> "Add News Item", "path" => action_path("news") . "addeditnews&pageid=$pageid&featureid=$featureid", "iframe" => true, "refresh" => "true", "width" => "850", "height" => "600", "icon" => icon("plus"), "class" => "slide_menu_button"]) : '';
    } else { // Individual news item.
        if (user_is_able($USER->userid, "editnews", $pageid)) {
            $return .= make_modal_links([
                "title"=> "Edit News Item",
                "path" => action_path("news") . "addeditnews&pageid=$pageid&newsid=$featureid",
                "iframe" => true,
                "refresh" => "true",
                "width" => "850",
                "height" => "600",
                "icon" => icon("pencil"), "class" => "slide_menu_button",
            ]);
        }

        if (user_is_able($USER->userid, "deletenews", $pageid)) {
            ajaxapi([
                "id" => "delete_news_" . $featureid . "_article",
                "if" => "confirm('Are you sure you want to delete this?')",
                "url" => "/ajax/site_ajax.php",
                "data" => [
                    "action" => "delete_feature",
                    "subid" => $featureid,
                    "pageid" => $pageid,
                    "featuretype" => $featuretype,
                    "month" => "js||$('#news_" . $featureid . "_archive_month').val()||js",
                ],
                "ondone" => "go_to_page(' . $pageid . ');",
            ]);
            $return .= '
                <button id="delete_news_' . $featureid . '_article" class="slide_menu_button alike" title="Delete News Item">
                    ' . icon("trash") . '
                </button>';
        }
    }
    return $return;
}

function news_default_settings($type, $pageid, $featureid) {
    $settings = [
        [
            "setting_name" => "feature_title",
            "defaultsetting" => "News Section",
            "display" => "Feature Title",
            "inputtype" => "text",
        ],
        [
            "setting_name" => "limit_viewable",
            "defaultsetting" => "5",
            "display" => "Viewable Limit",
            "inputtype" => "text",
            "numeric" => true,
            "validation" => "<=0",
            "warning" => "Must be greater than 0.",
        ],
    ];

    $settings = attach_setting_identifiers($settings, $type, $pageid, $featureid);
    return $settings;
}
?>