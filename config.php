<?php  /// Moodle Configuration File 

unset($CFG);
unset($USER);

$CFG = new stdClass();

//Downtime
$CFG->downtime = false;
$CFG->alternatepage = 'down.html';
$CFG->safeip = ''; //Must have a comma on both sides of ip address

//Website info
$CFG->sitename 	= '';
$CFG->siteowner	= '';
$CFG->siteemail	= '';
$CFG->sitefooter = '';
$CFG->logofile 	= 'logo.png';

//Database connection variables
$CFG->dbtype    = ''; //mysql or mysqli
$CFG->dbhost    = 'localhost';
$CFG->dbname    = '';
$CFG->dbuser    = '';
$CFG->dbpass    = '';

//SMTP server
$CFG->smtppath	= '';
$CFG->smtp	= false;
$CFG->smtpauth = true;
$CFG->smtpuser	= '';
$CFG->smtppass	= '';

//Directory variables
$CFG->directory = '';
$CFG->wwwroot   = 'http://'.$_SERVER['SERVER_NAME'];
$CFG->wwwroot   = $CFG->directory ? $CFG->wwwroot.'/'.$CFG->directory : $CFG->wwwroot;
$CFG->docroot   = dirname(__FILE__);
$CFG->dirroot   = $CFG->docroot;

//Userfile path
$CFG->userfilespath = substr($CFG->docroot,0,strrpos($CFG->docroot,'/'));

//Home site id
$CFG->SITEID = 1;

//Paypal Live
$CFG->paypal = true;
$CFG->paypal_merchant_account = '';
$CFG->paypal_auth = '';

//Cookie variables in seconds
$CFG->cookietimeout = 600;
$CFG->timezone = "";
$CFG->defaultaddress = "";

//Google Maps API site key
$CFG->googleapikey = "";

//Scribd API key
$CFG->doc_view_key = '';

//Google Analytics id
$CFG->analytics = '';
?>