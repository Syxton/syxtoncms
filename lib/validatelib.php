<?php
/***************************************************************************
* validatelib.php - Validatation script library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.4
***************************************************************************/
if (!isset($CFG) || !defined('LIBHEADER')) {
	$sub = '';
	while (!file_exists($sub . 'lib/header.php')) {
		$sub = $sub == '' ? '../' : $sub . '../';
	}
	include($sub . 'lib/header.php');
}
define("VALIDATELIB", true);

function create_validation_script($formname, $function, $ajax=false) {
global $CFG;
	$setup = '';
	$script = '
	$.validator.setDefaults({done: \'valid\'});
	$(\'#' . $formname . '\').validate({
		meta: \'validate\',
		submitHandler: function() {
		' . $function . '
		},
		ignore: \'.calendarDateInput\'
	});';

    //Text fields and new HTML5 types
    $setup .= '$(\'.formContainer input[type=text],[type=email],[type=search],[type=url]\').focus(function() {
  				      $(this).parent().find(\'label.error\').css(\'visibility\', \'visible\');
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'visible\')
  				  }).blur(function() {
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'hidden\');
  				  });';

    //Password fields
    $setup .= '$(\'.formContainer input[type=password]\').focus(function() {
  				      $(this).parent().find(\'label.error\').css(\'visibility\', \'visible\');
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'visible\')
  				  }).blur(function() {
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'hidden\');
  				  });';

    //Textarea fields
    $setup .= '$(\'.formContainer textarea\').focus(function() {
  				      $(this).parent().find(\'label.error\').css(\'visibility\', \'visible\');
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'visible\')
  				  }).blur(function() {
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'hidden\');
  				  });';

    //CHECK fields
    $setup .= '$(\'.formContainer input[type=checkbox]\').focus(function() {
  				      $(this).parent().find(\'label.error\').css(\'visibility\', \'visible\');
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'visible\')
  				  }).blur(function() {
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'hidden\');
  				  });';

    //SELECT fields
    $setup .= '$(\'.formContainer select\').focus(function() {
  				      $(this).parent().find(\'label.error\').css(\'visibility\', \'visible\');
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'visible\')
  				  }).blur(function() {
  				      $(this).parent().find(\'.info\').css(\'visibility\', \'hidden\');
  				  });';

    if ($ajax) {
		 return $script . $setup;
	} else {
		 return js_code_wrap($script . $setup, "defer", true);
	}
}
?>
