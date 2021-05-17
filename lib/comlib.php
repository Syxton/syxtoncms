<?php
/***************************************************************************
* comlib.php - Communication Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 2/25/2014
* Revision: 0.0.8
***************************************************************************/

if(!isset($LIBHEADER)) include('header.php');
$COMLIB = true;

function send_email($touser,$fromuser,$cc = false,$subject, $message, $bcc = false){
 global $MYVARS,$CFG;
    $success = false;
	if (!$CFG->smtp) {
		// To send HTML mail, the Content-type header must be set
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

		// Additional headers
		$headers .= 'To: ' . ucwords(strtolower($touser->fname) . ' ' . strtolower($touser->lname)) . ' <' . $touser->email . '>' . "\r\n";
		$headers .= 'From: '.ucwords(strtolower($fromuser->fname).' '. strtolower($fromuser->lname)).' <' . $fromuser->email . '>' . "\r\n";
		if ($cc) { $headers .= 'Cc: ' . $cc . "\r\n"; }
		if ($bcc) {$headers .= 'Bcc: ' . $bcc . "\r\n"; }
		$headers .= 'Reply-To: ' . $fromuser->email . "\r\n";
		$headers .= 'Return-Path:'.$fromuser->email."\n";

		if (@mail($touser->email, $subject, $message, $headers, "-f" . $fromuser->email)) {
      $success = true;
    }
	} else {
    if (@smtp($touser, $fromuser, $cc, $subject, $message, $bcc)) {
      $success = true;
    }
  }

	return $success;
}

function send_multi_email($tolist,$fromuser,$cc = false,$subject, $message, $bcc = false){
    foreach($tolist as $touser){
        send_email($touser, $fromuser, $cc, $subject, $message, $bcc);
    }
}

function smtp($touser, $fromuser, $cc = false, $subject, $message, $bcc = false) {
	global $CFG;
	require_once($CFG->dirroot . "/scripts/PEAR/Mail.php");
	$to = ucwords(strtolower($touser->fname) . ' ' . strtolower($touser->lname)) . ' <' . $touser->email . '>';
	$from = ucwords(strtolower($fromuser->fname).' '. strtolower($fromuser->lname)). ' <' . $fromuser->email . '>';
	$subject = $subject;
	$body = '<html><head></head><body>'.$message.'</body></html>';
	$cc = $cc ? '<'.$cc.'>' : '';
	$bcc = $bcc ? '<'.$bcc.'>' : '';

	$headers = array (
		'MIME-Version' => '1.0',
		'Content-type' => 'text/html; charset=iso-8859-1',
		'Reply-To' => $from,
		'Return-Path'=> $fromuser->email,
		'From' => $from,
	  	'To' => $to,
	  	'Cc' => $cc,
	  	'Bcc' => $bcc,
	  	'Subject' => $subject);

  $smtpinfo["host"] = $CFG->smtp;
  $smtpinfo["port"] = $CFG->smtpport;
  $smtpinfo["auth"] = $CFG->smtpauth;
  $smtpinfo["username"] = $CFG->smtpuser;
  $smtpinfo["password"] = $CFG->smtppass;

  $mail_object =& Mail::factory("smtp", $smtpinfo);

  $mail_object->send($to, $headers, $body);

  if (PEAR::isError($mail_object)) {
    return false;
  } else {
    return true;
  }
}
?>
