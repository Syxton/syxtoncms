var iframeids = [];

//Should script hide iframe from browsers that don't support this script (non IE5+/NS6+ browsers. Recommended):
var iframehide = "yes";
var cushion = 50;

function resizeCaller(id) {
    iframeids.push(id);
    iframeids = [...new Set(iframeids)]; // remove duplicates.

    resizeAll();
}

function resizeAll() {
    for (i = 0; i < iframeids.length; i++) {
        resizeIframe(iframeids[i]);

        if (iframehide == "no") {
            var tempobj = document.getElementById(iframeids[i]);
            tempobj.style.display = "block";
        }
    }
}

function resizeIframe(frameid) {
    var currentfr = document.getElementById(frameid);
    if (currentfr) {
        currentfr.style.display = "block";
        if (currentfr.contentDocument) {
            if (currentfr.contentDocument.body !== null) {
                // create an Observer instance
                const resizeObserver = new ResizeObserver(entries => {
                    currentfr.height = parseInt($(entries[0].target).height()) + cushion;
                });
                // start observing a DOM node
                resizeObserver.observe($(currentfr.contentDocument.body).find("div:first")[0]);
            }
        } else if (currentfr.Document) {
            if (currentfr.Document.body !== null) {
                // create an Observer instance
                const resizeObserver = new ResizeObserver(entries => {
                    currentfr.height = parseInt($(entries[0].target).height()) + cushion;
                });
                // start observing a DOM node
                resizeObserver.observe($(currentfr.Document.body).find("div:first")[0]);
            }
        }
    }
}

function readjustIframe(loadevt) {
    var crossevt = (window.event) ? event : loadevt;
    var iframeroot = (crossevt.currentTarget) ? crossevt.currentTarget : crossevt.srcElement;
    if (iframeroot) {
        resizeIframe(iframeroot.id);
    }

    if (typeof resize_modal === "function") {
        resize_modal();
    }
}

function loadintoIframe(iframeid, url) {
    if (document.getElementById) {
        document.getElementById(iframeid).src = url;
    }
}