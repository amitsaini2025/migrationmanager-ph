import './bootstrap';

import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make global
window.Alpine = Alpine;
window.SignaturePad = SignaturePad;
window.Pusher = Pusher;

Alpine.start();

/*
|--------------------------------------------------------------------------
| Office visit notification sound (browser autoplay policy)
|--------------------------------------------------------------------------
| Browsers block audio until the user has interacted with the page. We unlock
| on first click/keydown/touchstart, then play() from notifications works.
|--------------------------------------------------------------------------
*/
window.__notificationAudioUnlocked = false;

function unlockNotificationAudioFromGesture() {
    if (window.__notificationAudioUnlocked) return;
    const audio = document.getElementById('player');
    if (!audio) return;
    const playPromise = audio.play();
    if (playPromise !== undefined) {
        playPromise
            .then(function () {
                audio.pause();
                audio.currentTime = 0;
                window.__notificationAudioUnlocked = true;
            })
            .catch(function () {});
    }
}

if (typeof document !== 'undefined') {
    ['click', 'keydown', 'touchstart'].forEach(function (ev) {
        document.addEventListener(ev, unlockNotificationAudioFromGesture, {
            capture: true,
            passive: true,
            once: true,
        });
    });
}

window.playOfficeVisitNotificationSound = function () {
    const audio = document.getElementById('player');
    if (!audio || !window.__notificationAudioUnlocked) return;
    const playPromise = audio.play();
    if (playPromise !== undefined) {
        playPromise.catch(function () {});
    }
};

/*
|--------------------------------------------------------------------------
| Office visit popup (Echo can fire before layout defines showTeamsNotification)
|--------------------------------------------------------------------------
*/
window.__officeVisitNotificationQueue = window.__officeVisitNotificationQueue || [];

function normalizeOfficeVisitEchoPayload(e) {
    if (!e) return null;
    if (e.notification) return e.notification;
    if (e.id !== undefined || e.checkin_id !== undefined) return e;
    return null;
}

window.deliverOfficeVisitNotificationPayload = function (payload) {
    if (!payload) return;
    if (typeof window.showTeamsNotification === 'function') {
        window.showTeamsNotification(payload);
        return;
    }
    window.__officeVisitNotificationQueue.push(payload);
};

window.drainOfficeVisitNotificationQueue = function () {
    if (typeof window.showTeamsNotification !== 'function') return;
    const q = window.__officeVisitNotificationQueue;
    if (!q || !q.length) return;
    const batch = q.splice(0, q.length);
    batch.forEach(function (payload) {
        try {
            window.showTeamsNotification(payload);
        } catch (err) {
            console.warn('Office visit queued notification error:', err);
        }
    });
};

/*
|--------------------------------------------------------------------------
| Notification Bell Update (always available - used by Echo and client_portal)
|--------------------------------------------------------------------------
*/
window.updateNotificationBell = function (count, options = {}) {
    const el = document.getElementById('countbell_notification');
    if (!el) return;
    const prevCount = parseInt(String(el.textContent || '0'), 10) || 0;
    const newCount = typeof count === 'number' ? count : parseInt(String(count), 10) || 0;
    el.textContent = newCount > 0 ? String(newCount) : '';
    el.style.display = newCount > 0 ? 'inline' : 'none';

    const parent = el.closest('.notification-toggle') || el.parentElement;
    if (parent) {
        parent.classList.add('notification-bell-flash');
        setTimeout(function () { parent.classList.remove('notification-bell-flash'); }, 600);
    }
    if (options.showToast !== false && newCount > prevCount) {
        const izi = typeof window !== 'undefined' && window.iziToast;
        if (izi && izi.show) {
            const toastMessage = options.message || (newCount === 1 ? 'You have a new notification' : 'You have ' + (newCount - prevCount) + ' new notification(s)');
            const toastConfig = {
                title: 'Notification',
                message: toastMessage,
                position: 'topRight',
                color: 'blue',
                timeout: 5000,
                closeOnClick: true
            };
            if (options.url) {
                toastConfig.onClick = function () {
                    window.location.href = options.url;
                };
            }
            izi.show(toastConfig);
        }
    }
};

/*
|--------------------------------------------------------------------------
| Laravel Echo + Reverb
|--------------------------------------------------------------------------
| - Local: ws://localhost:8080 (REVERB_SCHEME=http, REVERB_PORT=8080)
| - Production: wss://host:443 (REVERB_SCHEME=https, Nginx proxies to Reverb)
*/

function attachCrmEchoUserChannelListeners() {
    if (window.__crmEchoUserListenersAttached || !window.Echo) {
        return;
    }

    const userId = document.querySelector('meta[name="current-user-id"]')?.content;
    if (!userId) {
        return;
    }

    window.__crmEchoUserListenersAttached = true;

    const userChannel = window.Echo.private('user.' + userId);
    userChannel.listen('.notification.count.updated', function (e) {
        try {
            const count = e.unread_count !== undefined ? parseInt(e.unread_count, 10) : 0;
            const opts = { showToast: true };
            if (e.message) opts.message = e.message;
            if (e.url) opts.url = e.url;
            window.updateNotificationBell(count, opts);
        } catch (err) {
            console.warn('Notification count update error:', err);
        }
    });

    // Office visit popups: deliverOfficeVisitNotificationPayload queues until showTeamsNotification exists.
    if (!window.__officeVisitEchoAttached) {
        userChannel.listen('.OfficeVisitNotificationCreated', function (e) {
            try {
                const payload = normalizeOfficeVisitEchoPayload(e);
                if (payload) {
                    window.deliverOfficeVisitNotificationPayload(payload);
                }
            } catch (err) {
                console.warn('Office visit notification handler error:', err);
            }
        });
        window.__officeVisitEchoAttached = true;
        console.log('✅ Office visit notification listener attached (Echo)');
    }
}

function whenEchoConnected(callback) {
    const connection = window.Echo?.connector?.pusher?.connection;
    if (!connection) {
        return;
    }

    if (connection.state === 'connected') {
        callback();
        return;
    }

    connection.bind('connected', callback);
}

function isCrmEchoConnected() {
    if (window.EchoDisabled || !window.Echo) {
        return false;
    }

    if (typeof window.Echo.connectionStatus === 'function') {
        return window.Echo.connectionStatus() === 'connected';
    }

    return window.Echo?.connector?.pusher?.connection?.state === 'connected';
}

if (import.meta.env.VITE_REVERB_APP_KEY) {
    try {
        if (!window.Echo) {
            const useTLS = import.meta.env.VITE_REVERB_SCHEME === 'https';
            const port = parseInt(import.meta.env.VITE_REVERB_PORT, 10);
            const wsPort = !isNaN(port) ? port : (useTLS ? 443 : 8080);
            const configuredHost = import.meta.env.VITE_REVERB_HOST || 'localhost';
            const wsHost =
                configuredHost === 'localhost' || configuredHost === '127.0.0.1'
                    ? (window.location.hostname || configuredHost)
                    : configuredHost;

            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,

                wsHost,
                wsPort,
                wssPort: wsPort,

                forceTLS: useTLS,
                enabledTransports: useTLS ? ['wss'] : ['ws'],
                disableStats: true,

                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content'),
                    },
                },
            });

            console.log('✅ Laravel Echo initialized with Reverb', useTLS ? '(wss)' : '(ws)');
        }

        // Wait for the WebSocket handshake before private-channel auth (avoids console noise).
        whenEchoConnected(attachCrmEchoUserChannelListeners);
    } catch (error) {
        console.warn('⚠️ Failed to initialize Laravel Echo:', error);
        window.EchoDisabled = true;
    }
} else {
    window.EchoDisabled = true;
}

// Poll office-visit notifications as a safety net (Echo can still deliver instantly).
// showTeamsNotification dedupes by id so this does not double-render when both fire.
(function pollOfficeVisitNotificationsAll() {
    const userId = document.querySelector('meta[name="current-user-id"]')?.content;
    if (!userId) return;

    function poll() {
        if (document.visibilityState === 'hidden') return;
        if (typeof window.showTeamsNotification !== 'function') return;
        fetch('/fetch-office-visit-notifications', {
            method: 'GET',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((r) => r.json())
            .then((data) => {
                const list = data && data.notifications ? data.notifications : [];
                list.forEach(function (n) {
                    window.showTeamsNotification(n);
                });
            })
            .catch(() => {});
    }

    let attempts = 0;
    const waitForHandler = setInterval(function () {
        attempts++;
        if (typeof window.showTeamsNotification === 'function') {
            clearInterval(waitForHandler);
            if (typeof window.drainOfficeVisitNotificationQueue === 'function') {
                window.drainOfficeVisitNotificationQueue();
            }
            setTimeout(poll, 3000);
            setInterval(poll, 10000);
        }
        if (attempts >= 300) clearInterval(waitForHandler);
    }, 200);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            poll();
        }
    });
})();

// Polling fallback for notification badge (HTML already has the count; Echo updates live).
(function pollNotificationCount() {
    const badgeEl = document.getElementById('countbell_notification');
    const userId = document.querySelector('meta[name="current-user-id"]')?.content;
    if (!badgeEl || !userId) return;

    function fetchCount() {
        if (document.visibilityState === 'hidden') return;
        if (isCrmEchoConnected()) return;
        fetch('/fetch-notification', {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'include'
        })
            .then((r) => r.json())
            .then((data) => {
                const count = parseInt(data.unseen_notification || 0, 10) || 0;
                if (typeof window.updateNotificationBell === 'function') {
                    window.updateNotificationBell(count, { showToast: false });
                } else if (badgeEl) {
                    badgeEl.textContent = count > 0 ? String(count) : '';
                    badgeEl.style.display = count > 0 ? 'inline' : 'none';
                }
            })
            .catch(() => {});
    }

    setInterval(fetchCount, 30000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') fetchCount();
    });

    const echoConnector = window.Echo?.connector;
    if (echoConnector && typeof echoConnector.onConnectionChange === 'function') {
        echoConnector.onConnectionChange(function (status) {
            if (status === 'disconnected' || status === 'failed') {
                fetchCount();
            }
        });
    } else {
        const connection = echoConnector?.pusher?.connection;
        if (connection) {
            connection.bind('disconnected', fetchCount);
            connection.bind('unavailable', fetchCount);
            connection.bind('failed', fetchCount);
        }
    }
})();

/*
|--------------------------------------------------------------------------
| FullCalendar v6
|--------------------------------------------------------------------------
*/

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';

window.FullCalendar = { Calendar };
window.FullCalendarPlugins = {
    dayGridPlugin,
    timeGridPlugin,
    interactionPlugin,
    listPlugin,
};
