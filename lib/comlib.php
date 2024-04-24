<?php
/***************************************************************************
* comlib.php - Communication Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 2/25/2014
* Revision: 0.0.8
***************************************************************************/

if (!isset($LIBHEADER)) include('header.php');
$COMLIB = true;

function send_email($touser, $fromuser, $subject, $message, $cc = false, $bcc = false) {
 global $MYVARS, $CFG;
  $touser = is_array($touser) ? (object)$touser : $touser;
  $fromuser = is_array($fromuser) ? (object)$fromuser : $fromuser;
  $success = false;
	if (!$CFG->smtp) {
		$headers = [];
		// To send HTML mail, the Content-type header must be set
		$headers['MIME-Version'] = '1.0';
		$headers['Content-type'] = 'text/html; charset=iso-8859-1';

		// Additional headers
		$headers['To'] = ucwords(strtolower($touser->fname) . ' ' . strtolower($touser->lname)) . ' <' . $touser->email . '>';
		$headers['From'] = ucwords(strtolower($fromuser->fname).' '. strtolower($fromuser->lname)).' <' . $fromuser->email . '>';
		$headers['Reply-To'] = $fromuser->email;
		$headers['Return-Path'] = $fromuser->email;
		$headers['X-Mailer'] = 'PHP/' . phpversion();
		if ($cc) { $headers['Cc'] = $cc; }
		if ($bcc) { $headers['Bcc'] = $bcc; }

		if (@mail($touser->email, $subject, $message, $headers, "-f" . $fromuser->email)) {
      		$success = true;
    	}
	} else {
		if (@smtp($touser, $fromuser, $subject, $message, $cc, $bcc)) {
			$success = true;
		}
	}

	return $success;
}

function send_multi_email($tolist, $fromuser, $subject, $message, $cc = false, $bcc = false) {
    foreach ($tolist as $touser) {
        send_email($touser, $fromuser, $subject, $message, $cc, $bcc);
    }
}

function smtp($touser, $fromuser, $subject, $message, $cc = false, $bcc = false) {
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
