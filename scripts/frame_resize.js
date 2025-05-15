var iframeids = [];

//Should script hide iframe from browsers that don't support this script (non IE5+/NS6+ browsers. Recommended):
var iframehide = "yes";
var cushion = 50;

/**
 * Add the iframe id to the list of iframes that need to be resized.
 * @param {string} id - The id of the iframe element.
 */
function resizeCaller(id) {
    iframeids.push(id);
    iframeids = [...new Set(iframeids)]; // remove duplicates and keep the order.

    // Resize all iframes that have been registered.
    resizeAll();
}

/**
 * Resize all iframes that have been registered.
 */
function resizeAll() {
    for (var i = 0; i < iframeids.length; i++) {
        // Resize the iframe based on its content.
        resizeIframe(iframeids[i]);

        if (iframehide == "no") {
            // Make sure the iframe is visible.
            var tempobj = document.getElementById(iframeids[i]);
            tempobj.style.display = "block";
        }
    }
}

/**
 * Resize an iframe to fit its content.
 *
 * @param {string} frameid
 *   The id of the iframe element to resize.
 */
function resizeIframe(frameid) {
    var currentfr = document.getElementById(frameid);
    if (currentfr) {
        currentfr.style.display = "block";

        // Browser-specific way to get the document object of the iframe.
        var doc = currentfr.contentDocument || currentfr.Document;

        if (doc && doc.body) {
            // Create an Observer instance.
            const resizeObserver = new ResizeObserver(entries => {
                // The element we are observing is the first div inside the iframe body.
                // We add a little extra height to avoid scrollbars.
                currentfr.height = parseInt($(entries[0].target).height()) + cushion;
            });

            // Start observing the first div inside the iframe body.
            resizeObserver.observe($(doc.body).find("div:first")[0]);
        }
    }
}

/**
 * Adjust an iframe to fit its content after it has loaded.
 *
 * This function is called after an iframe has finished loading. It will resize
 * the iframe to fit its content and also trigger the resize_modal function if
 * it exists.
 *
 * @param {Event} loadevt
 *   The load event that triggered the call to this function.
 */
function readjustIframe(loadevt) {
    var crossevt = (window.event) ? event : loadevt;
    var iframeroot = (crossevt.currentTarget) ? crossevt.currentTarget : crossevt.srcElement;
    if (iframeroot) {
        // Resize the iframe to fit its content.
        resizeIframe(iframeroot.id);
    }

    // If the resize_modal function exists, call it.
    if (typeof resize_modal === "function") {
        resize_modal();
    }
}
