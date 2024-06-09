jQuery.fn.emptybg = function() {
    var color = $(this[0]).css('background-color');
    if(color == 'transparent' || color == 'rgba(0, 0, 0, 0)' || color == 'rgb(0, 0, 0)' || color == 'inherit'){
        return true;
    } return false; // It's your element
};

jQuery.fn.waitTillExists = function waitTillExists(container, lookfor, callback, maxTimes = false) {
    if ($(container).find(lookfor).length) {
		callback();
		return;
    } else {
        if (maxTimes === false || maxTimes > 0) {
            maxTimes != false && maxTimes--;
            setTimeout(function() {
                waitTillExists(container, lookfor, callback, maxTimes);
            }, 10);
        }
    }
};

jQuery.fn.waitTillGone = function waitTillGone(container, lookfor, callback, maxTimes = false) {
    if (!$(container).find(lookfor).length) {
		callback();
		return;
    } else {
        if (maxTimes === false || maxTimes > 0) {
            maxTimes != false && maxTimes--;
            setTimeout(function() {
                waitTillGone(container, lookfor, callback, maxTimes);
            }, 10);
        }
    }
};