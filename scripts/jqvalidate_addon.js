jQuery.validator.addMethod("ajax1", function(value, element, param) {
    param = param.split("::");
    //Matches the previous value.
    if (param[4] && param[4] == value) {
        return true;
    }

    var modparam = param[2];
    //finds other field info marked with #name
    if (modparam.indexOf("#") != -1) {
        var varname = modparam.match('[#]+[a-z]*');
        modparam = modparam.replace(varname.toString(), $(varname.toString()).val());
    }

    if (param == value) {
        return true;
    }
    var d = new Date();
    var resp = $.ajax({
        async: false,
        type: 'post',
        url: './' + param[0],
        data: 'action=' + param[1] + modparam + encodeURIComponent(value) + "&currTime=" + d.toUTCString()
    }).responseText;

    if (param[3] && param[3] == "true") {
        if (resp == "true") {
            return true;
        }
    } else {
        if (resp == "false") {
            return true;
        }
    }
}, jQuery.validator.format("This value is not allowed."));

jQuery.validator.addMethod("ajax2", function(value, element, param) {
    param = param.split("::");
    //Matches the previous value.
    if (param[4] && param[4] == value) {
        return true;
    }

    var modparam = param[2];
    //finds other field info marked with #name
    if (modparam.indexOf("#") != -1) {
        var varname = modparam.match('[#]+[a-z]*');
        modparam = modparam.replace(varname.toString(), $(varname.toString()).val());
    }

    if (param == value) {
        return true;
    }
    var d = new Date();
    var resp = $.ajax({
        async: false,
        type: 'post',
        url: './' + param[0],
        data: 'action=' + param[1] + modparam + encodeURIComponent(value) + "&currTime=" + d.toUTCString()
    }).responseText;

    if (param[3] && param[3] == "true") {
        if (resp == "true") {
            return true;
        }
    } else {
        if (resp == "false") {
            return true;
        }
    }
}, jQuery.validator.format("This value is not allowed."));

jQuery.validator.addMethod("ajax3", function(value, element, param) {
    param = param.split("::");
    //Matches the previous value.
    if (param[4] && param[4] == value) {
        return true;
    }

    var modparam = param[2];
    //finds other field info marked with #name
    if (modparam.indexOf("#") != -1) {
        var varname = modparam.match('[#]+[a-z]*');
        modparam = modparam.replace(varname.toString(), $(varname.toString()).val());
    }

    if (param == value) {
        return true;
    }
    var d = new Date();
    var resp = $.ajax({
        async: false,
        type: 'post',
        url: './' + param[0],
        data: 'action=' + param[1] + modparam + encodeURIComponent(value) + "&currTime=" + d.toUTCString()
    }).responseText;

    if (param[3] && param[3] == "true") {
        if (resp == "true") {
            return true;
        }
    } else {
        if (resp == "false") {
            return true;
        }
    }
}, jQuery.validator.format("This value is not allowed."));

//Captcha
jQuery.validator.addMethod("captcha", function(value, element, param) {
    param = param.split("::");
    var number = $(param[2]).val().split("|");
    var d = new Date();
    var resp = $.ajax({
        async: false,
        type: 'post',
        url: './' + param[0],
        data: 'action=' + param[1] + '&orig=' + number[0] + '&matchwith=' + encodeURIComponent(value) + "&currTime=" + d.toUTCString()
    }).responseText;

    if (resp == "true") {
        return true;
    }
}, jQuery.validator.format("This does not match the image."));

//Phone
jQuery.validator.addMethod("phone", function(value, element) {
    return this.optional(element) || /^\(?(\d{3})\)?[- ]?(\d{3})[- ]?(\d{4})$/.test(value);
}, jQuery.validator.format("Enter a valid phone number."));

//Future Date
jQuery.validator.addMethod("futuredate", function(value, element, param) {
    var myDate = new Date()
    if (param && (!/Invalid|NaN/.test(new Date($(param).prop("value"))))) {
        myDate = new Date($(param).prop("value"))
    }
    return this.optional(element) || myDate < (new Date(value));
}, jQuery.validator.format("Enter a date in the future."));

//Past Date
jQuery.validator.addMethod("pastdate", function(value, element, param) {
    var myDate = new Date()
    if (param && (!/Invalid|NaN/.test(new Date($(param).prop("value"))))) {
        myDate = new Date($(param).prop("value"))
    }
    return this.optional(element) || myDate > (new Date(value));
}, jQuery.validator.format("Enter a date in the past."));

//Letters only
jQuery.validator.addMethod("letters", function(value, element) {
    return this.optional(element) || /([a-zA-Z])/.test(value);
}, jQuery.validator.format("This can contain only letters."));

//No Numbers
jQuery.validator.addMethod("nonumbers", function(value, element) {
    return this.optional(element) || /^[^0-9]+$/.test(value);
}, jQuery.validator.format("This cannot contain numbers."));

//Custom
jQuery.validator.addMethod("custom", function(value, element, param) {
    var pattern = new RegExp(param)
    return this.optional(element) || pattern.test(value)
}, jQuery.validator.format("This is not a valid entry"));

// Relative date vs another field: param = "#fieldKey::op" (also accepts "|")
// op: before | before_or_equal | after | after_or_equal | same
jQuery.validator.addMethod("daterel", function(value, element, param) {
    if (this.optional(element)) {
        return true;
    }
    param = String(param == null ? "" : param);
    var parts = param.indexOf("::") >= 0 ? param.split("::") : param.split("|");
    var sel = (parts[0] ? parts[0] : "").trim();
    var op = (parts[1] ? parts[1] : "after").trim();
    if (!sel) {
        return true;
    }
    if (sel.charAt(0) !== "#" && sel.charAt(0) !== ".") {
        sel = "#" + sel;
    }
    var otherVal = jQuery(sel).val();
    if (otherVal === undefined || otherVal === null || String(otherVal).trim() === "") {
        return true;
    }
    function parseYmd(v) {
        v = String(v).trim();
        var m = v.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
        }
        return new Date(v);
    }
    var a = parseYmd(value);
    var b = parseYmd(otherVal);
    if (/Invalid|NaN/.test(String(a))) { return false; }
    if (/Invalid|NaN/.test(String(b))) {
        return false;
    }
    var at = new Date(a.getFullYear(), a.getMonth(), a.getDate()).getTime();
    var bt = new Date(b.getFullYear(), b.getMonth(), b.getDate()).getTime();
    var cmp = at - bt;
    if (op === "before") { return cmp < 0; }
    if (op === "before_or_equal") { return cmp <= 0; }
    if (op === "after") { return cmp > 0; }
    if (op === "after_or_equal") { return cmp >= 0; }
    if (op === "same") { return cmp === 0; }
    return true;
}, function(params, element) {
    params = String(params == null ? "" : params);
    var partsMsg = params.indexOf("::") >= 0 ? params.split("::") : params.split("|");
    var op = (partsMsg[1] ? partsMsg[1] : "after").trim();
    var msgs = {
        before: "This date must be before the related date.",
        before_or_equal: "This date must be on or before the related date.",
        after: "This date must be after the related date.",
        after_or_equal: "This date must be on or after the related date.",
        same: "This date must match the related date."
    };
    return msgs[op] ? msgs[op] : "This date is not valid relative to the related field.";
});

