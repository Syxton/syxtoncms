var iframeids = [];

//Should script hide iframe from browsers that don't support this script (non IE5+/NS6+ browsers. Recommended):
var iframehide = "yes";
var cushion = 10;

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
				let newheight = parseInt(currentfr.contentDocument.body.scrollHeight) + cushion;
				//console.log(frameid + " contentDocument.body.scrollHeight: " + newheight);
				currentfr.height = newheight;
			}
		} else if (currentfr.Document) {
			if (currentfr.Document.body !== null) {
				let newheight = parseInt(currentfr.Document.body.scrollHeight) + cushion;
				//console.log(frameid + " Document.body.scrollHeight: " + newheight);
				currentfr.height = newheight;
			}
		}

		// Something in the iframe hasn't loaded. wait and try again.
		if (currentfr.height <= cushion) {
			//console.log(frameid + " Still waiting...");
			setTimeout(function () { resizeIframe(frameid); }, 250);
			return;
		}

		if (currentfr.addEventListener) {
            currentfr.addEventListener("load", readjustIframe, false);
        } else if (currentfr.attachEvent) {
            currentfr.detachEvent("onload", readjustIframe); // Bug fix line
            currentfr.attachEvent("onload", readjustIframe);
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