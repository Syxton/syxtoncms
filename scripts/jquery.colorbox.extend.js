(function($) {
    $.fn.hasScrollBar = function() {
        if (typeof this.get(0) == 'undefined') {
            return "undefined";
        }
        return this.get(0).scrollHeight > this.height();
    }
})(jQuery);

/**
 * Adds a debounced resize event to a jQuery element.
 *
 * @param {function} fn - The function to be executed on resize.
 * @param {number} [threshold=250] - The time delay in milliseconds.
 * @param {boolean} [execAsap=false] - Whether to execute the function immediately.
 * @return {jQuery} - The jQuery element.
 */
(function($, advmodalresizer) {
    /**
     * Debounces a function to prevent it from being called more than once within a
     * specified time delay.
     *
     * @param {function} func - The function to be debounced.
     * @param {number} [threshold=250] - The time delay in milliseconds.
     * @param {boolean} [execAsap=false] - Whether to execute the function immediately.
     * @return {function} - The debounced function.
     */
    var debounce = function(func, threshold, execAsap) {
        var timeout;
        /**
         * The debounced function.
         */
        return function debounced() {
            var obj = this,
                args = arguments;

            /**
             * Executes the debounced function after the specified time delay.
             */
            function delayed() {
                if (!execAsap) {
                    func.apply(obj, args);
                }
                timeout = null;
            };

            if (timeout) {
                clearTimeout(timeout);
            } else if (execAsap) {
                func.apply(obj, args);
            }
            timeout = setTimeout(delayed, threshold || 100);
        };
    }
    // advModalResizer
    /**
     * Adds a debounced resize event to a jQuery element.
     *
     * @param {function} fn - The function to be executed on resize.
     * @return {jQuery} - The jQuery element.
     */
    jQuery.fn[advmodalresizer] = function(fn) {
        return fn ? this.bind('resize', debounce(fn)) : this.trigger(advmodalresizer);
    };

})(jQuery, 'advModalResizer');


// usage:
$(window).advModalResizer(function() {
    // code that makes it easy...
    if (typeof $($(top)[0].$.find("#cboxLoadedContent")).get(0) != 'undefined') {
        setTimeout(function () {
            // Get widths and heights
            var width = $(top)[0].$("#colorbox").width() > $(top).width() ? $(top).width() : $(top)[0].$("#colorbox").width();
            var topheight = $(top).height();
            var topwidth = $(top).width();

            // Set heights
            var heightspace = 70; // 42 is the combined top and bottom border of color box + 28 bottom margin of color box.

            let debug = {
                width: width,
                topheight: topheight,
                topwidth: topwidth
            };

            if (typeof $($(top)[0].$.find("iframe[class=cboxIframe]")) == 'undefined' || $($(top)[0].$.find("iframe[class=cboxIframe]")).length == 0) {
                var contentheight = $("#cboxLoadedContent").innerHeight();
                debug.area = "root";
                debug.contentheight = contentheight;
            } else { // iframe inside modal.
                var contentheight = parseInt($($(top)[0].$.find("iframe[class=cboxIframe]")).attr("height"));
                debug.area = "iframe";
                debug.contentheight = contentheight;
            }

            // Get widths and heights
            if (typeof contentheight != 'undefined') {
                if (topheight < contentheight + heightspace) { // The content is larger than the largest modal.
                    var newheight = topheight;
                } else { // Content is smaller than largestpossible modal.
                    var newheight = contentheight + heightspace;
                }
                debug.newheight = newheight;

                $(top)[0].$.colorbox.resize({
                    width: width,
                    height: newheight
                });
            }
            console.log(debug);
        }, 20);
    }
});