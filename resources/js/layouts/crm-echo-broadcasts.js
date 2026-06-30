/**
 * Load broadcasts.js after Laravel Echo (from app.js) is available.
 * Set window.__CRM_BROADCASTS_JS_URL__ in the layout before this script runs.
 */
(function () {
    var url = window.__CRM_BROADCASTS_JS_URL__;
    if (!url) {
        return;
    }

    function loadBroadcasts() {
        var script = document.createElement('script');
        script.src = url;
        document.body.appendChild(script);
    }

    var attempts = 0;
    var maxAttempts = 50;
    var waitForEcho = setInterval(function () {
        attempts++;

        if (typeof window.Echo !== 'undefined') {
            console.log('✅ window.Echo detected, loading broadcasts.js...');
            clearInterval(waitForEcho);
            loadBroadcasts();
        } else if (attempts >= maxAttempts) {
            if (!window.EchoDisabled) {
                console.warn(
                    '⚠️ window.Echo not available after waiting, broadcasts.js will use polling fallback'
                );
            }
            clearInterval(waitForEcho);
            loadBroadcasts();
        }
    }, 100);
})();
