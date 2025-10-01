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
    if (typeof getRoot("#cboxLoadedContent")[0] !== 'undefined') {
        setTimeout(function () {
            // Get widths and heights
            let topheight = getRoot().height();
            let topwidth = getRoot().width();

            let width = 0;
            let contentheight = 0;

            // Set heights
            let heightspace = 70; // 42 is the combined top and bottom border of color box + 28 bottom margin of color box.

            let debug = {
                width: width,
                topheight: topheight,
                topwidth: topwidth,
            };

            // Get content height.
            if (typeof getRoot("iframe[class=cboxIframe]") === 'undefined' || getRoot("iframe[class=cboxIframe]").length === 0) {
                $("#cboxLoadedContent").width("auto");
                $("#cboxContent").width("auto");

                let html = $("#cboxLoadedContent")[0];
                width = Math.max(html.clientWidth, html.scrollWidth, html.offsetWidth);
                width = parseInt(width) > getRoot().width() ? getRoot().width() : parseInt(width) + 60;
                contentheight = Math.max(html.clientHeight, html.scrollHeight, html.offsetHeight);
                debug.area = "root";

            } else { // iframe inside modal.
                width = parseInt($("#colorbox").width()) > getRoot().width() ? getRoot().width() : parseInt($("#colorbox").width());
                let body = getRoot("iframe[class=cboxIframe]")[0].contentWindow.document.body;
                let html = getRoot("iframe[class=cboxIframe]")[0].contentWindow.document.documentElement;
                contentheight = Math.max( body.scrollHeight, body.offsetHeight, html.clientHeight, html.scrollHeight, html.offsetHeight);
                debug.area = "iframe";
            }

            debug.contentheight = contentheight;

            // Set new heights.
            if (typeof contentheight !== 'undefined') {
                let newheight = 0;
                if (topheight < contentheight + heightspace) { // The content is larger than the largest modal.
                    newheight = topheight;
                } else { // Content is smaller than largestpossible modal.
                    newheight = contentheight + heightspace;
                }
                debug.newheight = newheight;
                debug.width = width;

                getColorbox().resize({
                    width: width,
                    height: newheight
                });
            }
            //console.log(debug);
        }, 20);
    }
});