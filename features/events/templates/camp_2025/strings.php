<?php

return (object) [
    "help_email" => "Please enter a valid email address to send your registration information.",
    "help_middlei" => "Please enter a middle initial.",
    "help_age" => "The age will be calculated based on the birthdate entered.",
    "help_bday" => "Please enter the camper's date of birth.",
    "help_grade" => "Please enter the last grade the camper has finished. 0-12",
    "help_gender" => "Please select whether the camper is Male or Female.",
    "help_congregation" => "What, if any, church congregation does the camper attend?",
    "help_parent" => "Please enter the name of a parent or guardian of the camper.",
    "help_address" => "Please enter the mailing address of the camper.",
    "help_campership" => "Please enter the church/organization sponsering your campership.",
    "help_city" => "Please enter the home city of the camper.",
    "help_state" => "Please select the home state of the camper.",
    "help_membername" => "Please enter the name associated with your insurance.",
    "help_relationship" => "Please enter the relationship between the member and the camper.",
    "help_carrier" => "Please enter the name of your insurance.",
    "help_memberid" => "Please enter your member ID number.",
    "help_membercode" => "Please enter your benefit code.",
    "help_memberaccount" => "Please enter the account # of your insurance.",
    "help_healthto" => "Please enter the expiration date of your insurance.",
    "help_history" => "Please enter any of the camper's important medical history.",
    "help_allergies" => "Please enter any of the camper's allergies.",
    "help_existing" => "Please enter any of the camper's existing medical issues.",
    "help_meds" => "Please enter any medications the camper is currently taking.",
    "help_tetanus" => "Please enter the date of the camper's last tetanus shot.",
    "help_pictures" => "Do you want to purchase a camp picture?",
    "help_paywithapp" => "Please enter the amount you wish to pay up front.",
    "help_shirt" => "Do you want a camp shirt?",
    "help_shirt_size" => "What size camp shirt do you want?",
    "help_phone" => "Please enter a phone number beginning with the area code.",
    "error_age_min" => "The birthdate you have selected does not meet the minimum age of {0}",
    "error_age_max" => "The birthdate you have selected exeeds the maximum age of {0}",

    "title_pending" => "Registration Complete Pending Payment",
    "title_complete" => "Registration Complete",

    "subtitle_pending" => "Some payments may be required to complete your registrations.<br />Please see the instructions below.",
    "subtitle_complete" => "Your registrations are complete.",

    "message_paynow" => '
        At this time you have chosen to pay <strong>${paynow}</strong> toward the cost of your registration(s).
        <br /><br />
        <div class="centered">
            Click a Payment method below to pay for your registration fees.
            <br />
            {form}
        </div>',
    "message_paylater" => '
        At this time you have chosen not to make a payment.
        <br />
        Please be advised that we have sent payment instruction emails to the address you provided.
        We kindly ask that you review this information carefully in order to proceed with the payment process.',
    "message_nopay" => '
        Thank you for registering! We\'re excited to have you join us. Your registration is now complete, and there are no outstanding payments required.
        You\'re all set! <br />
        <div class="centered">
            We look forward to seeing you at the event.
        </div>',
];
?>