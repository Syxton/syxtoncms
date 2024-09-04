<?php
/***************************************************************************
* userlib.php - User function library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 2.8.0
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
    $sub = '';
    while (!file_exists($sub . 'lib/header.php')) {
        $sub = $sub == '' ? '../' : $sub . '../';
    }
    include($sub . 'lib/header.php');
}
define('USERLIB', true);

function random_quote() {
    $returnme = '<div id="carousel"  data-flickity=\'{ "autoPlay": 10000, "pageDots": false, "imagesLoaded": true, "percentPosition": false, "wrapAround": true }\'>';

    if (!$img = randomimages("images/carousel/")) { return ''; } // No carousel if no images are found.

    $count = count($img) >= 5 ? 5 : count($img); // Images found, find out how many.
    $loading = "";
    // Get enough quotes to add to the images.
    if ($result = get_db_result("SELECT quote, author FROM quotes ORDER BY RAND() LIMIT 0, $count")) {
        while ($row = fetch_row($result)) {
            // Set quotes and authors.
            $quote = empty($row['quote']) ? '' : '<div>' . $row['quote'] . '</div>';
            $author = empty($row['author']) ? '' : '<br /><div style="float:right;">-- ' . $row['author'] . '</div>';

            // Get random image index from $img array.
            $randindex = array_rand($img);
            $returnme .= "<div class='carousel-cell'>" .
                            "<div><img $loading class='carouselslides' src='" . $img[$randindex]."' alt='carousel image with a quote' /></div>" .
                            "<div class='carouselquotes'>" . $quote . $author . "</div>" .
                         "</div>";
            // Unset the random image so it can't be used twice.
            unset($img[$randindex]);
            $loading = "loading='lazy'";
        }
    }
    return $returnme . '</div>';
}

function randomimages($dir) {
    $images = glob($dir . '*.{jpg,jpeg,png,gif,webp,avif}', GLOB_BRACE);
    if (empty(count($images))) { return false; }
    return $images;
}

function get_ip_address() {
global $USER;

    $serverip = getHostByName(getHostName());
    $ip = empty($USER->ip) ? "" : $USER->ip;
    // already saved? Save some time.
    if (isset($USER->ip) &&filter_var($USER->ip, FILTER_VALIDATE_IP) && strlen($ip) >= 7 && $ip !== $serverip) {
        return $USER->ip;
    }

    // Best second choice.
    if (!filter_var($ip, FILTER_VALIDATE_IP) || strlen($ip) < 7 || $ip == $serverip) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // Ask 3rd party (ip4.me).
    if (!filter_var($ip, FILTER_VALIDATE_IP) || strlen($ip) < 7 || $ip == $serverip) {
        $file = file_get_contents('http://ip4.me/');

        // Trim IP based on HTML formatting
        $pos = strpos($file, '+3' ) + 3;
        $ip = substr($file, $pos, strlen( $file ) );

        // Trim IP based on HTML formatting
        $pos = strpos($ip, '</' );
        $ip = substr($ip, 0, $pos );
    }

    // Ask another 3rd party (Google).
    if (!filter_var($ip, FILTER_VALIDATE_IP) || strlen($ip) < 7 || $ip == $serverip) {
        // use Google to get IP.
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $res = socket_connect($sock, '8.8.8.8', 53);
        // You might want error checking code here based on the value of $res
        socket_getsockname($sock, $addr);
        socket_shutdown($sock);
        socket_close($sock);
        $ip = $addr;
    }
    $USER->ip = $ip;
    return $ip;
}

function load_user_cookie() {
global $CFG, $USER;
    if (!empty($_SESSION['userid'])) { //cookie exists
        $time = get_timestamp();
        $recentlyactive = "";

        $loggedinas = !empty($_SESSION["lia_original"]);
        if (!$loggedinas) {
            $recentlyactive = " AND last_activity > " . $CFG->cookietimeout;
        }
        $SQL = fetch_template("dbsql/users.sql", "get_active_user", false, ["recentlyactive" => $recentlyactive]);
        if ($row = get_db_row($SQL, ["userid" => $_SESSION['userid']])) { // Get user info from db, load into $USER global
            $temp = (object)$row;
            $_SESSION['userid'] = $temp->userid;
        } else {
            $temp = (object)["userid" => 0];
            $_SESSION['userid'] = "";
        }
    } else {
        $temp = (object)["userid" => 0];
        $_SESSION['userid'] = "";
    }
    $USER = $temp;
}

function update_user_cookie() {
global $CFG, $USER;
    $time = get_timestamp();
    if (!is_logged_in()) { // check to see if $USER global is set
        // if $USER global isn't set, see if there is an existing cookie and have it loaded into $USER
        load_user_cookie();
    }

    if (is_logged_in()) { // check if $USER global is set now.
        $_SESSION['userid'] = $USER->userid;
        // Update last active timestamp
        execute_db_sql("UPDATE users SET last_activity = ||time|| WHERE userid = ||userid||", ["time" => $time, "userid" => $USER->userid]);
    } else { // Still not logged in. Set $USER global to 0
        $USER = (object) ["userid" => 0];
    }
}

function create_new_user($user) {
global $CFG, $USER;
    $created = false;

    try {
      start_db_transaction();
        $temp = create_random_password();
        $key = md5($user['email']) . md5(time());
        $time = get_timestamp();

        // Create new user
        $params = [
            'fname' => $user['fname'],
            'lname' => $user['lname'],
            'email' => $user['email'],
            'temp' => $temp,
            'password' => $temp,
            'userkey' => $key,
            'time' => $time,
        ];
        $userid = execute_db_sql(fetch_template("dbsql/users.sql", "create_user"), $params);
        $defaultrole = get_default_role($CFG->SITEID);
        $SQL = fetch_template("dbsql/roles.sql", "insert_role_assignment");
        $role_assignment = execute_db_sql($SQL, ["userid" => $userid, "roleid" => $defaultrole, "pageid" => $CFG->SITEID, "confirm" => 0]);
        commit_db_transaction();

        if ($userid && $role_assignment) {
            $created = true;
            $USER = (object) ['userid' => $userid, 'fname' => $user['fname'], 'lname' => $user['lname'], 'email' => $user['email']];
            $FROMUSER = (object) ['fname' => $CFG->sitename, 'lname' => '', 'email' => $CFG->siteemail];

            // Send confirmation email
            if (!defined('COMLIB')) { include_once($CFG->dirroot . '/lib/comlib.php'); }
            $subject = $CFG->sitename . ' New User Confirmation';
            $message = write_confirmation_email($user, $temp);

            if (@send_email($USER, $FROMUSER, $subject, $message)) {
                @send_email($FROMUSER, $FROMUSER, $subject, $message);
                return new_user_confirmation($user);
            }
        }
    } catch (\Throwable $e) {
        if ($created) {
            return error_string("user_not_emailed");
        } else {
            rollback_db_transaction($e->getMessage());
        }
    }
    return error_string("user_not_added");
}

function create_random_password() {
    // Make random password and activation code
    $pass1 = [
        "little", "big", "loud", "quiet", "short", "tall", "tiny", "huge", "old", "young", "nice", "mean", "scary",
        "sneaky", "snooty", "pretty", "happy", "sneezy", "itchy", "giant", "feeble", "silly", "cute", "adorable",
        "boring", "funny", "mysterious", "witty", "angry", "beautiful", "amazing", "minimal", "crazy", "wonderful",];
    $rnd1 = array_rand($pass1);
    srand ((float) microtime( )*1000000);
    $pass2 = rand(1, 9);
    $pass3 = [
        "cat", "dog", "chicken", "mouse", "deer", "snake", "fawn", "rat", "lion", "tiger", "chipmunk", "owl", "bear",
        "rooster", "whale", "fish", "puma", "panther", "horse", "clown", "piggy", "kitten", "puppy", "kangaroo", "seal",
        "tortoise", "squirrel", "sloth", "singer", "doggy", "butterfly", "grasshopper", "shark", "dolphin", "antelope",];
    $rnd3 = array_rand($pass3);
    return $pass1[$rnd1] . $pass2 . $pass3[$rnd3];
}

function new_user_confirmation($user) {
global $CFG;
    $user = gettype($user) == 'object' ? $user : (object) $user;
    return '
          <p><font size="3" face="Tahoma"><strong>' . ucfirst($user->fname) . ' ' . ucfirst($user->lname) . '\'s</strong> account was created </font><font size="3" face="Tahoma" color="#999999">successfully!</font></p>
          <p><font face="Tahoma">An email has been sent to your email </font><font face="Tahoma" color="#3366ff">(<strong><em>' . $user->email . '</em></strong>) </font><font face="Tahoma">account to verify your ability to check this account.&nbsp; </font></p>
          <p><font face="Tahoma">Instructions of how to log into your </font><font face="Tahoma"><strong>' . $CFG->sitename . ' </strong></font><font face="Tahoma">account will be given inside this email address.&nbsp; This will includes a randomly generated password that is required to enter the site for the first time.&nbsp; </font></p>
          <p><font face="Tahoma">After your first login, the password that you specified will automatically be used for every login thereafter. </font></p>
          <p><br />
          <font face="Tahoma">Thank you for using <strong>' . $CFG->sitename . '</strong><br />
          </font></p>
          ';
}

function write_confirmation_email($user, $temp) {
global $CFG;
    $params = [
        "fname" => $user["fname"],
        "lname" => $user["lname"],
        "email" => $user["email"],
        "temp" => $temp,
        "config" => $CFG,
    ];
    return fill_template("tmp/user.template", "confirmation_email_template", false, $params);
}

function get_user_name($userid) {
    if ($user = get_db_row("SELECT * FROM users WHERE userid='$userid' LIMIT 1")) { return $user['fname'] . " " . $user['lname']; }
    return "Anonymous";
}

function is_logged_in($userid = false) {
global $CFG, $USER;
    $key = clean_myvar_opt("key", "string", false);

    $userid = $userid ? $userid : ($USER->userid ?? false);
    if (!$userid) {
        if (!$key) {
            return false;
        }
        $userid = get_db_field("userid", "users", "userkey=||key||", ["key" => $key]);
    }

    if (!empty($userid)) {
        $USER->userid = $userid;
        recursive_mkdir($CFG->userfilespath . '/' . $userid);
        return true;
    }
    return false;
}

function nameize($str, $a_char = ["'", "-", " ", '"', '.']) {
    $str = trim($str);
    $str = preg_replace('!\s+!', ' ', $str);
    $name_parts = explode(" ", $str);

    if (count($name_parts) > 1) {
        $output = "";
        foreach ($name_parts as $np) {
            $output .= nameize($np) . " ";
        }
        return trim($output);
    }

    // the tricky part is finding names like DeMarco: 2 capitals
    for ($i=0;$i<strlen($str);$i++) {
        if ($i > 0 && ctype_lower($str[($i - 1)]) && ctype_upper($str[$i]) && isset($str[($i + 1)]) && ctype_lower($str[($i + 1)])) {
            $temp = $str;
            $str = substr($temp, 0, ($i)) . "+ " . substr($temp, ($i), (strlen($str)-($i)));
            $i++; $i++;
        }
    }

    // $str contains the complete raw name string
    // $a_char is an array containing the characters we use as separators for capitalization. If you don't pass anything, there are three in there as default.
    $string = strtolower($str);
    foreach ($a_char as $temp) {
        $pos = strpos($string, $temp);
        if ($pos !== -1) {
            //we are in the loop because we found one of the special characters in the array, so lets split it up into chunks and capitalize each one.
            $mend = '';
            $a_split = explode($temp, $string);
            foreach ($a_split as $temp2) {
                //capitalize each portion of the string which was separated at a special character
                $mend .= ucfirst($temp2).$temp;
            }
            $string = substr($mend, 0, -1);
        }
    }

    $str = "";
        for ($i=0;$i<strlen($string);$i++) {
        if (array_search($string[$i], $a_char)) {
            if ($string[$i] !== $string[(strlen($string)-$i - 1)]) {
                $str .= $string[$i];
            }
        } else {
            $str .= $string[$i];
        }
    }
    $str = str_replace("+ ", "", $str);
    $str = str_replace("+", "", $str);
    $str = str_replace('""', '"', $str);
    return trim(ucfirst($str));
}
?>