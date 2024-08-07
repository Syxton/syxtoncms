<?php
/***************************************************************************
* lang.php - Event Lang library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/14/2024
* Revision: 0.0.4
***************************************************************************/

unset($HELP);
unset($ERRORS);

$HELP = new \stdClass;
$ERRORS = new \stdClass;

//EVENT REQUEST AREA
//REQUEST HELP
$HELP->input_request_name = "Please enter a contact name.";
$HELP->input_request_email = "Please enter a valid email address so that we can contact you.";
$HELP->input_request_phone = "Please enter a contact phone number. <strong>(xxxxxxxxxx)</strong>";
$HELP->input_request_startdate = "What is the starting date of your event. <strong>(xx/xx/xxxx)</strong>";
$HELP->input_request_enddate = "What is the ending date of your event. <strong>(xx/xx/xxxx)</strong> &nbsp; <strong>Leave it blank if it is only 1 day long</strong>.";
$HELP->input_request_description = "Please give us as much information about your event as possible.";
$HELP->input_request_participants = "Approximately how many people will be participating?";
$HELP->input_request_event_name = "What is your event called?";

//REQUEST ERRORS
$ERRORS->valid_request_name = "A contact name is required.";
$ERRORS->valid_request_email = "A contact email address is required.";
$ERRORS->valid_request_email_invalid = "This email address is invalid.";
$ERRORS->valid_request_phone = "A contact phone number is required.";
$ERRORS->valid_request_phone_invalid = "This phone number is invalid.";
$ERRORS->valid_request_event_name = "This event requires a name.";
$ERRORS->valid_request_startdate = "We need to know the first day of your event.";
$ERRORS->valid_request_enddate = "We need to know how long your event is going to last.";
$ERRORS->valid_request_date_future = "Must be a date in the future";
$ERRORS->valid_request_date_later = "Must be a date later than the first date chosen.";
$ERRORS->valid_request_date_used = "Sorry, This date is already reserved.";
$ERRORS->valid_request_description = "A description is required.  Please be thorough.";
$ERRORS->invalid_old_request = "Event request is either invalid or voting has finished.";
$ERRORS->failed_confirm = "Event confirmation status could not be changed.";

//EVENT CREATION FORM
$HELP->input_event_name = "Please enter the name of your event.";
$HELP->input_contact = "The person to contact for questions about this event.";
$HELP->input_event_email = "The email address of the contact person.";
$HELP->input_event_cost = "Does this event costs money to attend?";
$HELP->input_event_limits = "Create registration limits for your event.";
$HELP->input_event_max_users = "Set a maximum amount of people allowed to register for your event. (0 = no max)";
$HELP->input_event_custom_limit_fields = "Select the field you would like to limit.";
$HELP->input_event_custom_limit_num = "This is the limit that will be set.";
$HELP->input_event_custom_limit_sorh = "Soft limits allow the registrant to finish registering, adding them to the QUEUE section instead of haulting their registration.";
$HELP->input_event_min_cost = "Minimum cost to be paid at registration.";
$HELP->input_event_full_cost = "Amount for event to be paid in full minus any optional charges.";
$HELP->input_event_sale_fee = "Amount for event to be paid in full during sale period. (0 = no sale price)";
$HELP->input_event_sale_end = "The date the regular price will go into effect.";
$HELP->input_event_paypal = "Paypal account email, if using Paypal.  Leave blank if you will not be using paypal.";
$HELP->input_event_payableto = "Make Checks/Money Orders payable to.";
$HELP->input_event_checksaddress = "Send Checks/Money Orders to this address.";
$HELP->input_byline = "A short subtitle of the event.";
$HELP->input_description = "A full description of the event.";
$HELP->input_event_location = "Please select the location of your event or add a new location.";
$HELP->input_event_category = "Please select the type of your event.";
$HELP->input_event_allowinpage = "If the user is signed in when they register, they will be allowed access into the original page the event was created in. ";
$HELP->input_event_start_reg = "Select a date when people can begin to register for your event.";
$HELP->input_event_stop_reg = "Select a date when people can no longer register for your event.";
$HELP->input_event_multiday = "This event spans multiple days.";
$HELP->input_event_allday = "This event lasts all day.";
$HELP->input_event_siteviewable = "Upon approval, this event will show up in the front page events and calendar.";
$HELP->input_event_registration = "Use an event registration page.";
$HELP->input_location_name = "Short name to describe the location.";
$HELP->input_location_add1 = "Addr. line 1: (ex. 55 Oak Street)";
$HELP->input_location_add2 = "Addr. line 2: (ex. Marshall, IL)";
$HELP->input_location_zip = "Zip Code: (ex. 62441)";
$HELP->input_location_share = "Let other users use this location in their events.";
$HELP->input_event_template = "Select a registration form template to use.";
$HELP->input_event_workers = "Require event workers to apply.";

//STAFF APP AREA
//STAFF APP HELP
$HELP->input_staff_name = "Please enter your full name.";
$HELP->input_staff_dob = "Please enter a date of birth.  <strong>(xx/xx/xxxx)</strong>";
$HELP->input_staff_phone = "Please enter a phone number. <strong>(xxxxxxxxxx)</strong>";
$HELP->input_staff_address = "Please enter an Address, City, State, Zip";
$HELP->input_staff_agerange = "Please select an age range.";
$HELP->input_staff_cocmember = "Please select Yes or No";
$HELP->input_staff_congregation = "Please enter the name of the congregation you attend.";
$HELP->input_staff_priorwork = "Please select Yes or No";
$HELP->input_staff_q1_1 = "Please select Yes or No";
$HELP->input_staff_q1_2 = "Please select Yes or No";
$HELP->input_staff_q1_3 = "Please select Yes or No";
$HELP->input_staff_q2_1 = "Please select Yes or No";
$HELP->input_staff_q2_2 = "Please select Yes or No";
$HELP->input_staff_q2_3 = "Please explain in detail.";
$HELP->input_staff_parentalconsent = "Please enter the full name of your parent/guardian.";
$HELP->input_staff_parentalconsentsig = "Checking this box denotes a parent/guardian signature.";
$HELP->input_staff_workerconsent = "Please enter your full name.";
$HELP->input_staff_workerconsentsig = "Checking this box denotes your signature.";
$HELP->input_staff_workerconsentdate = "Please enter today's date.";
$HELP->input_staff_refname = "Please enter the references name.";
$HELP->input_staff_refrelationship = "Please enter the relationship to the applicant.";
$HELP->input_staff_refphone = "Please enter a valid phone number of this reference.";

//STAFF APP ERRORS
$ERRORS->valid_staff_name = "A full name is required.";
$ERRORS->valid_staff_dob = "A valid date must be entered.";
$ERRORS->valid_staff_dateformat = "The date must be in the format xx/xx/xxxx.";
$ERRORS->valid_staff_phone = "A contact phone number is required.";
$ERRORS->valid_staff_phone_invalid = "This phone number is invalid.";
?>