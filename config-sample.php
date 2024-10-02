<?php  // SyxtonCMS Configuration File

unset($CFG);
unset($USER);

$CFG = new \stdClass;

//Downtime
$CFG->downtime = false;
$CFG->alternatepage = 'down.html';
$CFG->safeip = ',192.168.0.1,192.186.0.2,'; //Must have a comma on both sides of ip address

//Website info
$CFG->sitename 	= 'Website Name';
$CFG->siteowner	= 'Your Name';
$CFG->siteemail	= 'test@email.com';
$CFG->sitefooter = '1234 My Address';
$CFG->logofile 	= 'logo.png';
$CFG->mobilelogofile 	= 'mobilelogo.png';

//Database connection variables
$CFG->dbtype    = 'mysqli'; //mysql or mysqli
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'mydbname';
$CFG->dbuser    = 'mydbuser';
$CFG->dbpass    = 'mydbpassword';

//SMTP server
$CFG->smtppath	= '';
$CFG->smtp	= false;
$CFG->smtpauth = true;
$CFG->smtpuser	= '';
$CFG->smtppass	= '';

//Directory variables
$CFG->directory = 'mywebsites/syxtoncms';
$CFG->wwwroot   = '//' . $_SERVER['SERVER_NAME'];
$CFG->wwwroot   = $CFG->directory ? $CFG->wwwroot . '/' . $CFG->directory : $CFG->wwwroot;
$CFG->docroot   = dirname(__FILE__);
$CFG->dirroot   = $CFG->docroot;

//Userfile path
$CFG->userfilesfolder = 'userfiles';
$CFG->userfilespath = $CFG->docroot . '\\' . $CFG->userfilesfolder;
$CFG->userfilesurl = $CFG->wwwroot . '/' . $CFG->userfilesfolder;

//Home site id
$CFG->SITEID = 1;

//Paypal Live
$CFG->paypal = true;
$CFG->paypal_merchant_account = 'test@email.com';
$CFG->paypal_auth = '';

//Cookie variables in seconds
$CFG->cookietimeout = 600;
$CFG->timezone = "America/Indiana/Indianapolis";
$CFG->defaultaddress = "My City, IL";

//Google Maps API site key
$CFG->googleapikey = "";

//Geolocation API key (https://extreme-ip-lookup.com/)
$CFG->geolocationkey = "";

//Google Analytics id
$CFG->analytics = '';

// Debug level. 0 no errors, 1 log errors, 2 log and print, 3 log and print with extra debug.
$CFG->debug = 0;
?>
