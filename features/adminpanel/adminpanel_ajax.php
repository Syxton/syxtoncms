<?php
/***************************************************************************
* adminpanel_ajax.php - Adminpanel backend ajax script
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 6/07/2016
* Revision: 0.0.8
***************************************************************************/
if(!isset($CFG)){ include('../header.php'); } 
update_user_cookie();

if (!empty($_SESSION["lia_original"])) {
    if(!is_siteadmin($_SESSION["lia_original"])){ echo get_page_error_message("generic_permissions"); return;}    
} else {
    if(!is_siteadmin($USER->userid)){ echo get_page_error_message("generic_permissions"); return;}    
}


callfunction();

function admin_email_tester(){
global $CFG;
    echo '
    <strong>Send Test Email</strong><br /><br />
    Email Address: <input type="text" id="email" />
    <input type="button" value="Send Test" onclick="ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'admin_email_test\',\'&amp;email=\'+document.getElementById(\'email\').value,function() { simple_display(\'display\');});" />
    ';
}

function get_phpinfo(){
global $CFG;
    echo "<iframe style='width:100%;height:100%;border:none;' src='$CFG->wwwroot/features/adminpanel/adminpanel_ajax.php?action=phpinfo'></iframe>";    
}

function camper_list(){
global $CFG;
    echo "<iframe style='width:100%;height:100%;border:none;' src='$CFG->wwwroot/features/adminpanel/camper_list.php'></iframe>";        
}

function admin_email_test(){
global $CFG,$MYVARS;
    
    admin_email_tester(); //Send for again
    
    $touser = new stdClass();
    $fromuser = new stdClass();
    
    //Now output the last test.
    $touser->email = $MYVARS->GET["email"];
    $touser->fname = "Test";
    $touser->lname = "Email";
    
    $fromuser->email = $CFG->siteemail;
    $fromuser->fname = $CFG->sitename;
    $fromuser->lname = "";
    
    $subject = "SERVER: EMAIL TEST";
    $message = "This is a test message sent: " . date('l jS \of F Y h:i:s A');
    if(send_email($touser,$fromuser,$cc = false,$subject, $message)){
        echo "<br />Email Success";
    }else{
        echo "<br />Email Failed";
    }
}

function user_admin(){
global $CFG,$MYVARS,$USER;
    include("members.php");    
}

function site_versions(){
global $CFG,$MYVARS,$USER;
    
    echo '<table style="width:100%;font-size:.8em;">';
    
    //Site DB version
    echo '<tr><td colspan="2" style="text-align:center"><ins>Site db version</ins></td><td></tr><tr><td colspan="2" style="text-align:center">' . get_db_field("setting","settings","type='site' AND setting_name='version'") . '</td></tr>';
    
    //Feature versions
    echo '<tr><td colspan="2" style="text-align:center"></td></tr><tr><td colspan="2" style="text-align:center"><ins>Feature db version</ins></td><td></tr>';
    if($result = get_db_result("SELECT * FROM features ORDER BY feature")){
        while($row = fetch_row($result)){
            echo '<tr><td style="width:50%;text-align:right;border:1px solid silver;padding:3px;">'.$row["feature_title"].'</td><td style="padding:3px;border:1px solid silver;text-align:left">'.$row["version"].'</td></tr>';    
        }    
    }
    echo '</table>';
}

function view_logfile(){
global $CFG,$MYVARS,$USER;
    
    $viewtype = isset($MYVARS->GET["viewtype"]) ? $MYVARS->GET["viewtype"] : "all";
    $year = isset($MYVARS->GET["year"]) ? $MYVARS->GET["year"] : date("Y");
    $month = isset($MYVARS->GET["month"]) ? $MYVARS->GET["month"] : date("m");
    $userid = isset($MYVARS->GET["userid"]) ? $MYVARS->GET["userid"] : $USER->userid;
    $pagenum = isset($MYVARS->GET["pagenum"]) ? $MYVARS->GET["pagenum"] : 0;
    $daylabels = $labelpos = "";
    
    
    $daysinmonth = date('t',mktime(0,0,0,$month,1,$year));
    for($n=1;$n <= $daysinmonth;$n++){
        $daytext = date("D",mktime(0,0,0,$month,$n,$year));
        $daylabels .= "|".date("m/d",mktime(0,0,0,$month,$n,$year));
        $labelpos .= $labelpos=="" ? $n : ",".$n;
    }
    $daylabels .= "|";
    $labelpos .= $labelpos=="" ? $n : ",".$n;
        
    if($month == 1){
        $nextyear=$year;
        $nextmonth=($month+1);   
        $prevyear=($year-1);
        $prevmonth=12; 
    }elseif($month == 12){
        $nextyear=($year+1);
        $nextmonth=1;
        $prevyear=$year;
        $prevmonth=($month-1);
    }else{
        $nextyear=$year;
        $nextmonth=($month+1);  
        $prevyear=$year;
        $prevmonth=($month-1);  
    }
    
    //Next and Previous Month links
    $next = date('Y') < $nextyear || (date('Y') == $nextyear && date('m') < $nextmonth) ? '' : '<a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'view_logfile\',\'&amp;year='.$nextyear.'&amp;month='.$nextmonth.'&amp;userid='.$userid.'\',function() { if (xmlHttp.readyState == 4) { simple_display(\'display\'); }},true);" onmouseup="this.blur()">View '.date("F Y",mktime(0,0,0,$nextmonth,1,$nextyear)).' >></a>';
    $prev = '<a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'view_logfile\',\'&amp;year='.$prevyear.'&amp;month='.$prevmonth.'&amp;userid='.$userid.'\',function() { if (xmlHttp.readyState == 4) { simple_display(\'display\'); }},true);" onmouseup="this.blur()"><< View '.date("F Y",mktime(0,0,0,$prevmonth,1,$prevyear)).'</a>';
    
    echo '<table style="font-size:.75em;width:98%;margin-right:auto;margin-left:auto;"><tr><td style="text-align:left">'.$prev.'</td><td style="text-align:right">'.$next.'</td></tr></table>';
    echo '<img style="width:98%" src="//chart.apis.google.com/chart?chxl=0:'.$daylabels.'&chxp=0:'.$labelpos.'&chm=B,8EE5EE,0,0,0&chco=00868B&chxt=x,y&chs=1000x300&cht=lc&chd='.get_user_data($userid,$year,$month).'&chls=0.75,-1,-1&chxs=0,676767,8.5,-1,lt,676767|1,676767,8.5,-0.333,l,676767&chtt='.get_user_name($userid)."'s ".date("F Y",mktime(0,0,0,$month,1,$year)).'+Log" /><br /><br /><br /><div id="actions_div">'.get_user_usage($userid,$pagenum,$year,$month) . "</div>";
}

function get_user_data($userid,$year,$month){
global $CFG,$MYVARS,$USER;
    $data = ""; $i = $max = 0;
    $SQL = "SELECT *,COUNT(*) as hits,YEAR(FROM_UNIXTIME(timeline)) as myyear,MONTH(FROM_UNIXTIME(timeline)) as mymonth,DAYOFMONTH(FROM_UNIXTIME(timeline)) as myday FROM `logfile` 
    WHERE userid=$userid AND YEAR(FROM_UNIXTIME(timeline))=$year AND MONTH(FROM_UNIXTIME(timeline))=$month GROUP BY myyear,mymonth,myday ORDER BY myday";

    $date = mktime(0,0,0,$month,1,$year); //The get's the first of the given month
    $first=1; //last day of given month
    if($result = get_db_result($SQL)){
        while($row = fetch_row($result)){         
            while($first < $row["myday"]){
                $data[$i] = 'x';
                $first++;$i++;
            }
            $data[$i] = $row["hits"];
            $max = $data[$i] > $max ? $data[$i] : $max;  
            $i++; $first++;
        }
        $first--;
        while($first <= date('t',$date)){
            $data[$i] = 'x';
            $i++;$first++;
        }
        //$data= array_reverse($data); //Display data from first day to last even though we are usually looking for the latest data
        $max = ceil($max/100) * 100;
        $lines = $max/10;
    }else{
        while($first <= date('t',$date)){
            $data[$i] = 'x';
            $i++;$first++;
        }
        $max = 100;
    }

    return extendedEncode($data, $max) . "&chxr=0,1,".(date('t',$date)+1).",1|1,0,$max";
}

function get_user_usage_page(){
global $CFG,$MYVARS,$USER;
    echo get_user_usage($MYVARS->GET["userid"],$MYVARS->GET["pagenum"],$MYVARS->GET["year"],$MYVARS->GET["month"]); 
}

function get_user_usage($userid,$pagenum,$year,$month){
global $CFG,$MYVARS,$USER;
    $returnme = ""; $perpage=20;
    $firstonpage = $perpage * $pagenum;
    $LIMIT = " LIMIT $firstonpage," . $perpage;
    $data = ""; $i=0;
    
    $SQL = "SELECT * FROM `logfile` WHERE userid=$userid AND YEAR(FROM_UNIXTIME(timeline))=$year AND MONTH(FROM_UNIXTIME(timeline))=$month";
    $total = get_db_count($SQL);
    
    $SQL = "SELECT *,YEAR(FROM_UNIXTIME(timeline)) as myyear,MONTH(FROM_UNIXTIME(timeline)) as mymonth,DAYOFMONTH(FROM_UNIXTIME(timeline)) as myday FROM `logfile` 
    WHERE userid=$userid AND YEAR(FROM_UNIXTIME(timeline))=$year AND MONTH(FROM_UNIXTIME(timeline))=$month ORDER BY timeline DESC $LIMIT";

    $next = $pagenum > 0 ? '<a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'get_user_usage_page\',\'&amp;pagenum=' . ($pagenum - 1) . '&amp;year='.$year.'&amp;month='.$month.'&amp;userid='.$userid.'\',function() { if (xmlHttp.readyState == 4) { simple_display(\'actions_div\'); }},true);" onmouseup="this.blur()">Later Actions >></a>' : "";
    $prev = $firstonpage + $perpage < $total ? '<a href="javascript: ajaxapi(\'/features/adminpanel/adminpanel_ajax.php\',\'get_user_usage_page\',\'&amp;pagenum=' . ($pagenum + 1) . '&amp;year='.$year.'&amp;month='.$month.'&amp;userid='.$userid.'\',function() { if (xmlHttp.readyState == 4) { simple_display(\'actions_div\'); }},true);" onmouseup="this.blur()"><< Previous Actions</a>' : "";


    $returnme .= '<table style="font-size:.75em;width:98%;margin-right:auto;margin-left:auto;"><tr><td style="text-align:left">'.$prev.'</td><td style="text-align:right">'.$next.'</td></tr></table><div style="width:98%;margin-right:auto;margin-left:auto;"><table style="border-collapse:collapse;width:100%;border:1px solid black;background-color:silver;padding:3px;font-size:.9em;"><tr><td style="width:25%;text-align:center;"><strong>Date</strong></td><td style="width:35%;"><strong>Page</strong></td><td style="width:20%;"><strong>Feature</strong></td><td style="width:15%;"><strong>Action</strong></td></tr></table></div>';
    if($result = get_db_result($SQL)){
        while($row = fetch_row($result)){         
            $data[$i] = $row;
            $i++;
        }
        
        $data= array_reverse($data); $i=0;
        while(isset($data[$i])){
            $bg = !($i%2) ? "DDDDDD" : "CCCCCC";
            $info = get_db_field("setting","settings","type='".$data[$i]["feature"]."' AND setting_name='feature_title' AND pageid='".$data[$i]["pageid"]."' AND featureid='".$data[$i]["info"]."'");
            $info = $info != "" ? $info : $data[$i]["feature"];
            $returnme .= '<div style="background-color:#'.$bg.';width:98%;margin-right:auto;margin-left:auto;"><table style="border-collapse:collapse;width:100%;font-size:.9em;padding-left:3px"><tr><td style="width:25%;text-align:center;">'.date("m/d/Y g:i a",$data[$i]["timeline"]).'</td><td style="width:35%;">'.stripslashes(get_db_field("name","pages","pageid='".$data[$i]["pageid"]."'")).'</td><td style="width:20%;">'.$info.'</td><td style="width:15%;">'.stripslashes($data[$i]["description"]).'</td></tr></table></div>';
            $i++;        
        }
    }else{ 
        $returnme .= '<div style="width:98%;margin-right:auto;margin-left:auto;"><table style="border-collapse:collapse;width:100%;font-size:.9em;"><tr><td colspan="4" style="text-align:center;">No Usage</td></tr></table></div>';       
    }   
    return $returnme;
}

function extendedEncode($arrVals, $maxVal){
    // Same as simple encoding, but for extended encoding.
    $EXTENDED_MAP='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-.';
    $EXTENDED_MAP_LENGTH = strlen($EXTENDED_MAP);
    
    $chartData = 'e:'; $i=0;  
    while($i < count($arrVals)){
        // In case the array vals were translated to strings.
        $numericVal = $arrVals[$i];
        // Scale the value to maxVal.
        $scaledVal = floor($EXTENDED_MAP_LENGTH * $EXTENDED_MAP_LENGTH * $numericVal / $maxVal);
        
        if($scaledVal > ($EXTENDED_MAP_LENGTH * $EXTENDED_MAP_LENGTH) - 1) {
            $chartData .= "..";
        }elseif($scaledVal < 0){
            $chartData .= '__';
        }else{
            // Calculate first and second digits and add them to the output.
            $quotient = floor($scaledVal / $EXTENDED_MAP_LENGTH);
            $remainder = $scaledVal - $EXTENDED_MAP_LENGTH * $quotient;
            $chartData .= substr($EXTENDED_MAP,$quotient,1) . substr($EXTENDED_MAP,$remainder,1);
        }
        $i++;
    }
    return $chartData;
}

function loginas() {
global $MYVARS;
    $userid = $MYVARS->GET["userid"];
    if (!empty($userid)) {
        if (empty($_SESSION["lia_original"])) {
            $_SESSION["lia_original"] = $_SESSION["userid"];    
        }
        $_SESSION["userid"] = $userid;      
    }

    echo $_SESSION["pageid"];
}

function logoutas() {
global $MYVARS;
    $_SESSION["userid"] = $_SESSION["lia_original"];
    unset($_SESSION["lia_original"]);
    echo $_SESSION["pageid"];
}
?>