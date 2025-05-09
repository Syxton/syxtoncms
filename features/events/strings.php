<?php
/***************************************************************************
* Events feature strings
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 5/08/2025
* Revision: 0.0.1
***************************************************************************/

return (object) [
    // General events
    "invalid_event_conversion" => "Could not conert request to event.",

    // Event requests
    "input_request_startdate" => "What is the starting date of your event. <strong>(xx/xx/xxxx)</strong>",
    "input_request_enddate" => "What is the ending date of your event. <strong>(xx/xx/xxxx)</strong> &nbsp; <strong>Leave it blank if it is only 1 day long</strong>.",
    "input_request_description" => "Please give us as much information about your event as possible.",
    "input_request_participants" => "Approximately how many people will be participating?",

    "valid_request_date_future" => "Must be a date in the future",
    "valid_request_date_later" => "Must be a date later than the first date chosen.",
    "valid_request_date_used" => "Sorry, This date is already reserved.",
    "old_request" => "Event request is either invalid or voting has finished.",
    "failed_confirm" => "Event confirmation status could not be changed.",

    // Event creation
    "input_event_name" => "Please enter the name of your event.",
    "input_contact" => "The full name of the person to contact for questions about this event.",
    "input_contact_email" => "The email address of the contact person.",
    "input_event_cost" => "Does this event costs money to attend?",
    "input_event_limits" => "Create registration limits for your event.",
    "input_event_max_users" => "Set a maximum amount of people allowed to register for your event. (0 = no max)",
    "input_event_custom_limit_fields" => "Select the field you would like to limit.",
    "input_event_custom_limit_num" => "This is the number of people allowed to register that match this limiter.",
    "input_event_custom_limiter" => "This is the field value that will be limited.",
    "input_event_custom_limit_explanation" => "Soft limits allow the registrant to finish registering, adding them to the QUEUE section instead of haulting their registration.",
    "input_event_min_cost" => "Minimum cost to be paid at registration.",
    "input_event_full_cost" => "Amount for event to be paid in full minus any optional charges.",
    "input_event_sale_fee" => "Amount for event to be paid in full during sale period. (0 = no sale price)",
    "input_event_sale_end" => "The date the regular price will go into effect.",
    "input_event_paypal" => "Paypal account email, if using Paypal.  Leave blank if you will not be using paypal.",
    "input_event_payableto" => "Make Checks/Money Orders payable to.",
    "input_event_checksaddress" => "Send Checks/Money Orders to this address.",
    "input_byline" => "A short subtitle of the event.",
    "input_event_description" => "A full description of the event.",
    "input_event_location" => "Please select the location of your event or add a new location.",
    "input_event_category" => "Please select the calendar type of your event.",
    "input_event_allowinpage" => "If the user is signed in when they register, they will be allowed access into the original page the event was created in. ",
    "input_event_start" => "Select a date when the event begins.",
    "input_event_end" => "Select a date when the event ends.",
    "input_event_start_reg" => "Select a date when people can begin to register for your event.",
    "input_event_stop_reg" => "Select a date when people can no longer register for your event.",
    "input_event_multiday" => "This event spans multiple days.",
    "input_event_allday" => "This event lasts all day.",
    "input_event_siteviewable" => "Upon approval, this event will show up in the front page events and calendar.",
    "input_event_registration" => "Use an event registration page.",
    "input_location_name" => "Short name to describe the location.",
    "input_location_add1" => "Addr. line 1: (ex. 55 Oak Street)",
    "input_location_add2" => "Addr. line 2: (ex. Marshall, IL)",
    "input_location_zip" => "Zip Code: (ex. 62441)",
    "input_location_share" => "Let other users use this location in their events.",
    "input_event_template" => "Select a registration form template to use.",
    "input_event_workers" => "Require event workers to apply.",

    // Event staff
    "input_staff_dob" => "Please enter a date of birth.  <strong>(xx/xx/xxxx)</strong>",
    "input_staff_agerange" => "Please select an age range.",
    "input_staff_congregation" => "Please enter the name of the congregation you attend.",
    "input_explain" => "Please explain in detail.",
    "input_staff_parentalconsent" => "Please enter the full name of your parent/guardian.",
    "input_staff_parentalconsentsig" => "Checking this box denotes a parent/guardian signature.",
    "input_staff_workerconsent" => "Please enter your full name.",
    "input_staff_workerconsentsig" => "Checking this box denotes your signature.",
    "input_staff_workerconsentdate" => "Please enter today's date.",
    "input_staff_refname" => "Please enter the references name.",
    "input_staff_refrelationship" => "Please enter the relationship to the applicant.",
];

?>