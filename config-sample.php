<?php  // SyxtonCMS Configuration File

// Unset any existing configuration and user variables
unset($CFG);
unset($USER);

// Initialize configuration as an empty object
$CFG = new \stdClass;

/**
 * Miscellaneous Configuration
 * @var int $SITEID Home site ID
 */
$CFG->SITEID = 1;

/**
 * Downtime Configuration
 * @var bool $downtime Indicates if the site is in downtime
 * @var string $alternatepage Path to the alternate page during downtime
 * @var string $safeip Comma-separated list of safe IP addresses
 */
$CFG->downtime = false;
$CFG->alternatepage = 'down.html';
$CFG->safeip = ',192.168.0.1,192.186.0.2,'; // Must have a comma on both sides of IP address

/**
 * Website Configuration
 * @var string $sitename Website name
 * @var string $siteowner Website owner
 * @var string $siteemail Website email
 * @var string $timezone Time zone
 * @var string $defaultaddress Default address
 * @var string $sitefooter Website footer
 * @var string $logofile Path to the website logo
 * @var string $mobilelogofile Path to the mobile website logo
 */
$CFG->sitename = 'Website Name';
$CFG->siteowner = 'Your Name';
$CFG->siteemail = 'test@email.com';
$CFG->timezone = "America/Indiana/Indianapolis";
$CFG->defaultaddress = "";
$CFG->sitefooter = 'SyxtonCMS';
$CFG->logofile = 'logo.png';
$CFG->mobilelogofile = 'mobilelogo.png';

/**
 * Database Connection Variables
 * @var string $dbtype Type of database (mysql or mysqli)
 * @var string $dbhost Database host
 * @var string $dbname Database name
 * @var string $dbuser Database user
 * @var string $dbpass Database password
 */
$CFG->dbtype = 'mysqli'; // mysql or mysqli
$CFG->dbhost = 'localhost';
$CFG->dbname = 'mydbname';
$CFG->dbuser = 'mydbuser';
$CFG->dbpass = 'mydbpassword';

/**
 * SMTP Server Configuration
 * @var string $smtppath Path to SMTP server
 * @var bool $smtp Enable or disable SMTP
 * @var bool $smtpauth Enable or disable SMTP authentication
 * @var string $smtpuser SMTP username
 * @var string $smtppass SMTP password
 */
$CFG->smtppath = '';
$CFG->smtp = false;
$CFG->smtpauth = true;
$CFG->smtpuser = '';
$CFG->smtppass = '';

/**
 * Directory Variables
 * @var string $directory Directory path for the CMS
 * @var string $wwwroot Web root URL
 * @var string $docroot Document root directory
 * @var string $dirroot Directory root
 */
$CFG->directory = 'mywebsites/syxtoncms'; // Points to http://localhost/mywebsites/syxtoncms
$CFG->wwwroot = '//' . $_SERVER['SERVER_NAME'];
$CFG->wwwroot = $CFG->directory ? $CFG->wwwroot . '/' . $CFG->directory : $CFG->wwwroot;
$CFG->docroot = dirname(__FILE__);
$CFG->dirroot = $CFG->docroot;

/**
 * Userfile Path Configuration
 * @var string $userfilesfolder Folder for user files
 * @var string $userfilespath Path to the user files folder
 * @var string $userfilesurl URL to access user files
 */
$CFG->userfilesfolder = 'userfiles';
$CFG->userfilespath = $CFG->docroot . '\\' . $CFG->userfilesfolder;
$CFG->userfilesurl = $CFG->wwwroot . '/' . $CFG->userfilesfolder;

/**
 * PayPal Configuration
 * @var bool $paypal Live mode (true ON, false Sandbox)
 * @var string $paypal_merchant_account PayPal merchant account email
 * @var string $paypal_auth PayPal authentication token (old integration)
 * @var string $paypal_client_id PayPal client ID (v2 integration)
 * @var string $paypal_client_secret PayPal client secret (v2 integration)
 */
$CFG->paypal = true;
$CFG->paypal_merchant_account = 'test@email.com';
$CFG->paypal_auth = '';
$CFG->paypal_client_id = '';
$CFG->paypal_client_secret = '';

/**
 * Google Maps API site key
 * @var string $googleapikey
 */
$CFG->googleapikey = "";

/**
 * Google Analytics ID
 * @var string $analytics
 */
$CFG->analytics = '';

/**
 * Geolocation API key (https://extreme-ip-lookup.com/)
 * @var string $geolocationkey
 */
$CFG->geolocationkey = "";

/**
 * Miscellaneous Configuration
 * @var int $cookietimeout Cookie timeout in seconds
 * @var int $debug Debug level (0 no errors, 1 log errors, 2 log and print, 3 log and print with extra debug)
 */
$CFG->cookietimeout = 600;
$CFG->debug = 0;
?>
