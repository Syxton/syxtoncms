<?php
/***************************************************************************
* rsslib.php - RSS function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 1.2.4
***************************************************************************/

if (!LIBHEADER) { include('header.php'); }
define('RSSLIB', true);

//Limit amount of RSS info (should be a setting)
$CFG->rsslimit = 2;

function get_rss() {
global $CFG, $MYVARS;
	// Authenticate
    $rssfeed = "";
	$userkey = dbescape($MYVARS->GET["key"]);
	if ($userid = get_db_field("userid", "users", "userkey='$userkey'")) {
		if (isset($MYVARS->GET["rssid"])) {
			$rssid = dbescape($MYVARS->GET["rssid"]);
			if ($rss = get_db_row("SELECT * FROM rss WHERE rssid = '$rssid' AND userid = '$userid'")) {
				$rssname = $rss["rssname"];
				$feeds = create_feed($rssid, $userid, $userkey);
			}
		} else {
			$pageid = dbescape($MYVARS->GET["pageid"]);
			if (user_is_able($userid, "viewpage", $pageid)) {	
				// User has already created rssid...just needs the link for it again.
				if ($feed = get_db_row("SELECT * FROM rss_feeds WHERE pageid = '$pageid' AND type='page' AND rssid IN (SELECT rssid FROM rss WHERE userid = '$userid')")) {
					$rssname = get_db_field("rssname", "rss", "rssid=" . $feed["rssid"]);
					$feeds = create_feed($feed["rssid"], $userid, $userkey);
				} else { // Need to create new rssid and feed
					$page = get_db_row("SELECT * FROM pages WHERE pageid = '$pageid'");
					$rssname = $page["name"];
					if ($rssid = execute_db_sql("INSERT INTO rss (userid, rssname) VALUES($userid, '" . dbescape($page["name"]) . "')")) {
						$SQL = "INSERT INTO rss_feeds (rssid, type, pageid) VALUES($rssid, 'page', $pageid)";
						if (execute_db_sql($SQL)) {
							$feeds = create_feed($rssid, $userid, $userkey);
						}
					}
				}
			}
		}

        $rssfeed = '<title>' . htmlspecialchars($rssname) . '</title>
                    <description>RSS Syndication</description>
                    <pubDate>' . date(DATE_RFC822,get_timestamp()) . '</pubDate>
                    <link>' . htmlspecialchars($CFG->wwwroot) . '</link>
                    ' . $feeds;
	}
return '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL . '<rss version="2.0"><channel>' . $rssfeed . '</channel></rss>';
}

function sort_feeds($feed) {
global $CFG;
	$items = explode('<item>', $feed);
	unset($items[0]);
	
	$sorteditems = [];
	//break down feed
	foreach ($items as $item) {
		$item = str_replace('</',',</', $item);
		$item = strip_tags($item); 
		$item = explode(',', $item);
		$sorteditems[] = [
			"title" => $item[0],
			"description" => $item[1] . ", " . $item[2],
			"link" => $item[3],
		];
	}
	
	//sort feed
	usort($sorteditems, 'compare_fields');

	$feed = ""; //reset feed

    $sorteditems = array_slice($sorteditems, 0, $CFG->rsslimit); //cut off array at rsslimit amount

    foreach ($sorteditems as $item) {
		$feed .= '<item>
					<title>
					' . $item["title"] . 
					'</title>
					<description>
					' . $item["description"] . 
					'</description>
					<link>
					' . $item["link"] . 
					'</link>
				</item>';	
	}
	return $feed;
}

function compare_fields($a, $b) {
	$adate = explode(", ", $a['description']);
	$bdate = explode(", ", $b['description']);
	$returnme = strtotime($adate[1]) > strtotime($bdate[1]) ? -1 : 1;
	return $returnme;
} 

function find_feed($pageid = false, $feature = false, $featureid = false) {
global $USER;
    $userid = $USER->userid;
    $pageid = $pageid ?: false;
    $feature = $feature ?: false;
    $featureid = $featureid ?: false;

    $userkey = get_db_field("userkey", "users", "userid = '$userid'");

    if ($pageid) {
        $rssid = get_db_field("rssid", "rss_feeds", "pageid = '$pageid' AND type = 'page' AND rssid IN (SELECT rssid FROM rss WHERE userid = '$userid')");
    } elseif ($feature) {
        $rssid = get_db_field("rssid", "rss_feeds", "type = '$feature' AND featureid = '$featureid' AND rssid IN (SELECT rssid FROM rss WHERE userid = '$userid')");
    } else {
        $rssid = false;
    }
    return ["rssid" => $rssid, "userkey" => $userkey];
}

function feed_link($rssid, $userkey, $title = "RSS Feed") {
global $CFG;
    $title = htmlspecialchars($title);
    if ($rssid && $userkey) {
        return '<link rel="alternate" type="application/atom+xml" title="' . $title . '" href="' . $CFG->wwwroot . '/scripts/rss/rss.php?rssid=' . $rssid . '&key=' . $userkey . '"/>';
    }
}

function feature_feeds($feed, $userid, $userkey) {
global $CFG;
	if ($feed["type"] == "page") {
        $feeds = create_page_feed($feed["pageid"], $userid, $userkey); $feeds = sort_feeds($feeds); 
    } else {
        $feeds = all_features_function(false, $feed["type"], "", "_rss", false, $feed, $userid, $userkey);
    }
	return $feeds;
}

function create_feed($rssid, $userid, $userkey) {
global $CFG;
	$feeds = "";
    if ($rssid) {
        if ($result = get_db_result("SELECT * FROM rss_feeds WHERE rssid = '$rssid'")) {
            while ($feed = fetch_row($result)) {
                $feeds .= feature_feeds($feed, $userid, $userkey);
            }	
        }
    }
	return $feeds;
}

function create_page_feed($pageid, $userid, $userkey) {
global $CFG;
	$feeds = "";
	//Go through all rss'able features in the page
	if ($result = get_db_result("SELECT * FROM pages_features WHERE pageid = '$pageid' AND feature IN (SELECT feature FROM features WHERE rss = 1)")) {
		while ($feature = fetch_row($result)) {
			$feed["pageid"] = $pageid;
			$feed["featureid"] = $feature["featureid"];
			$feed["type"] = $feature["feature"];
			$feeds .= feature_feeds($feed, $userid, $userkey);
		}
	}
	return $feeds;
}

function fill_feed($title, $description, $link, $date) {
	return "<item><title>" . htmlspecialchars($title) . "</title>
            <description>" . date(DATE_RFC822, $date) . "</description>
            <link>" . htmlspecialchars($link) . "</link></item>";
}
?>