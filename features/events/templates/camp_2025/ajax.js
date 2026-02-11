var folder = "camp_2025";

/**
 * This function redirects the user to the registration form for the given event, copying over the existing registration
 * if the hash and regid parameters are supplied.
 *
 * @param {int} eventid - The ID of the event to load the registration form for.
 * @param {string} [hash] - The hash of the existing registration to copy over. Copied from cart.
 * @param {int} [regid] - The ID of the existing registration to copy over. Copied from db registration.
 */
function show_form_again(eventid, hash = "false", regid = "false") {
    hash = hash !== "false" ? "&hash=" + hash : "";
    regid = regid !== "false" ? "&regid=" + regid : "";

    // Build the URL to connect to
    var url = WWW_ROOT + (dirfromroot == '' ? '' : '/' + dirfromroot) + "/features/events/events.php?action=show_registration&i=!&v=!&eventid=" + eventid + hash + regid;
    window.location = url;
}


/**
 * Copies the registration to the form if the conditions are met.
 * - Checks if there's a registration to copy from.
 * - Confirms the user's intention.
 * - Extracts event ID and registration hash.
 * - Redirects to the registration form with copied data.
 */
function copy_to_form() {
    // Check if there is a registration to copy
    if ($("#copy_event_to_form").val() == 0) {
        return; // Exit if no registration to copy
    }

    // Confirm with the user before proceeding to copy the registration
    if (!confirm('Are you sure you want to copy this registration?')) {
        return; // Exit if user cancels
    }

    // Get the registration details to copy
    var regToCopy = $("#copy_event_to_form").val();

    // Split the registration details to extract event ID and hash
    var details = regToCopy.split("|");
    var eventid = details[0]; // Extract event ID
    var hash = details[1]; // Extract registration hash

    // Redirect to the registration form with the copied details
    show_form_again(eventid, hash);
}

/**
 * Updates the camper's age based on their birth date and the event date.
 * - Calculates the age as the difference between event year and birth year.
 * - Adjusts the age if the event date is after the birthday in the same year.
 * - Ensures age is not negative and limits maximum age.
 * - Sets the calculated age in the camper age input field.
 */
function updateAge() {
    // Get the camper's birth date and the event date
    var bday = datetype("camper_birth_date");
    var event = datetype("event_begin_date");

    var diffInMilliseconds = event.getTime() - bday.getTime();
    const millisecondsInYear = 1000 * 60 * 60 * 24 * 365.25; // Account for leap years

    if (isNaN(diffInMilliseconds) || diffInMilliseconds < 0) {
        bday = lastvaliddate;
        $("#camper_birth_date").val(lastvaliddate.toISOString().substring(0, 10));
        updateAge();
        return false;
    }

    // Update the last valid date to the currently selected date.
    lastvaliddate = bday;

    // Update the age field, rounding up to nearest full year.
    let age = Math.round(diffInMilliseconds / millisecondsInYear);

    $("#camper_age").val(age);
}

/**
 * Resets the registration form after user confirmation.
 * - Prompts the user for confirmation to reset the application.
 * - Redirects to the registration form for the current event if confirmed.
 *
 * @returns {boolean} Always returns false to prevent default form submission.
 */
function resetRegistration() {
    // Prompt the user for confirmation to reset the application
    if (confirm('Are you sure you want to reset the application?')) {
        // Redirect to the registration form with the current event ID
        show_form_again($("#eventid").val());
    }

    // Return false to prevent default form submission
    return false;
}

/**
 * Calculates the total payment amount based on the values of all
 * payment amounts on the page.
 *
 * @returns {void}
 */
function calculate_payment_amount() {
    // Initialize the total payment amount to 0
    var amount = 0;

    // Iterate through all payment amounts on the page
    $(".payment_amounts").each(function () {
        // Add the value of the current payment amount to the total
        amount += parseFloat($(this).val());
    });

    // Set the total payment amount to the calculated value
    $("#payment_amount").val(amount.toFixed(2));
}

$(function () {
    // Create lastvaliddate to prevent invalid dates in the camper_birth_date field
    window.lastvaliddate = datetype("camper_birth_date");

    /**
     * Toggles the visibility of the registration cart menu when clicked.
     * If the cart count is greater than zero, toggle the cart visibility.
     * Otherwise, hide the cart.
     */
    $(".registration_cart_menu").on("click", function () {
        if ($('#count_in_cart').val() > 0) {
            $(".registration_cart").toggle();
            return;
        }
        $(".registration_cart").hide();
    });

    /**
     * Updates the camper's age when the birth date is changed.
     */
    $("#camper_birth_date").on("change", function () {
        updateAge();
    });

    /**
     * Changes the background color of form section inputs with errors when focused.
     */
    $("input:not([type=hidden]).error, textarea.error, select.error", ".formSection").bind("focus", function () {
        $(this).closest(".rowContainer").css("background-color", "whitesmoke");
    });

    /**
     * Reverts the background color of form section inputs with errors when focus is lost.
     */
    $("input:not([type=hidden]).error, textarea.error, select.error", ".formSection").bind("blur", function () {
        $(this).closest(".rowContainer").css("background-color", "white");
    });
});
