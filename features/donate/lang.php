<?php
/***************************************************************************
* lang.php - Donation Lang library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.2
***************************************************************************/
 
unset($HELP);
unset($ERRORS);

$HELP = new \stdClass;
$ERRORS = new \stdClass;

//ERRORS
$ERRORS->donate_req_title = "A campaign title is required.";
$ERRORS->donate_req_goal = "A number must be entered.  0 = No fixed goal.";
$ERRORS->donate_req_description = "You must tell why you are asking for donations.";
$ERRORS->donate_req_token = "You must have the PDT authentication token for your paypal account.";
$ERRORS->donate_req_amount = "A donation amount must be entered.";
$ERRORS->donate_req_min = 'The donation amount must be greater than or equal to $ {0}.';

//DONATION CREATION FORM
$HELP->donate_paypal_email = "Please enter the email address associated with your Paypal account.";
$HELP->donate_description = "Why are you asking for donations?";
$HELP->donate_goal = "Enter a dollar amount.  0 = no final goal";
$HELP->donate_title = "What is the name of this Donation Campaign";
$HELP->donate_shared = "Do you want to allow other pages to join this donation campaign?";
$HELP->donate_name = "(Optional) - Some form of identification.";
$HELP->donate_amount = 'How much would you like to donate?';
$HELP->donate_token = "Please enter the PDT authentication token for your paypal account.";
$HELP->donate_paypaltx = "Paypal transaction ID.  'Offline' or leave blank.";
$HELP->donate_campaign = "Who is this money donated to?";

?>