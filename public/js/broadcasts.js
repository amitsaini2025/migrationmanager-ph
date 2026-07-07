(function () {
    const bannerSelector = '[data-broadcast-banner]';
    const titleSelector = '[data-broadcast-title]';
    const messageSelector = '[data-broadcast-message]';
    const metaTextSelector = '[data-broadcast-meta-text]';
    const dismissSelector = '[data-action="dismiss"]';
    const openSelector = '[data-action="open-broadcast"]';
    const contentSelector = '.broadcast-banner__content';

    const currentUserId = (() => {
        const meta = document.querySelector('meta[name="current-user-id"]');
        if (!meta) {
            return null;
        }

        const value = parseInt(meta.getAttribute('content') || '', 10);
        return Number.isFinite(value) ? value : null;
    })();

    const bannerEl = document.querySelector(bannerSelector);

    if (!bannerEl || !currentUserId) {
        return;
    }

    const titleEl = bannerEl.querySelector(titleSelector);
    const messageEl = bannerEl.querySelector(messageSelector);
    const metaTextEl = bannerEl.querySelector(metaTextSelector);
    const contentEl = bannerEl.querySelector(contentSelector);
    const dismissBtn = bannerEl.querySelectorAll(dismissSelector);

    const endpoints = {
        unread: '/notifications/broadcasts/unread',
        manage: '/notifications/broadcasts/manage',
    };

    const state = {
        queue: [],
        active: null,
        pollingTimer: null,
        isPolling: false,
        dismissed: new Set(),
        navigating: false,
        broadcastEchoSubscribed: false,
        legacyEchoListenersAttached: false,
    };

    function formatTimestamp(timestamp) {
        if (!timestamp) {
            return '';
        }
        if (typeof window.formatDisplayDateTime === 'function') {
            return window.formatDisplayDateTime(timestamp);
        }
        const date = new Date(timestamp);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            day: '2-digit',
            month: 'short',
        }).format(date);
    }

    function setBannerVisible(isVisible) {
        bannerEl.classList.toggle('is-visible', isVisible);
    }

    function formatPreviewMessage(rawMessage) {
        const source = String(rawMessage || '');
        if (!source) {
            return '';
        }

        const temp = document.createElement('div');
        temp.innerHTML = source;
        const text = (temp.textContent || temp.innerText || '').trim();
        const maxLength = 180;

        if (text.length <= maxLength) {
            return text;
        }

        return `${text.slice(0, maxLength)}...`;
    }

    function renderActive() {
        const broadcast = state.active;

        if (!broadcast) {
            titleEl && (titleEl.textContent = '');
            messageEl && (messageEl.textContent = '');
            metaTextEl && (metaTextEl.textContent = '');
            setBannerVisible(false);
            return;
        }

        if (titleEl) {
            titleEl.textContent = broadcast.title || '';
            titleEl.classList.toggle('has-title', Boolean(broadcast.title));
        }

        if (messageEl) {
            messageEl.textContent = formatPreviewMessage(broadcast.message);
        }

        if (metaTextEl) {
            const parts = [];

            if (broadcast.sender_name) {
                parts.push(broadcast.sender_name);
            }

            const formatted = formatTimestamp(broadcast.sent_at);
            if (formatted) {
                parts.push(formatted);
            }

            metaTextEl.textContent = parts.join(' • ');
        }

        bannerEl.classList.toggle('is-clickable', true);
        bannerEl.setAttribute('role', 'button');
        bannerEl.setAttribute('tabindex', '0');
        setBannerVisible(true);
    }

    function openActiveBroadcast() {
        const broadcast = state.active;
        if (!broadcast || state.navigating) {
            return;
        }

        state.navigating = true;
        const query = new URLSearchParams();

        if (broadcast.batch_uuid) {
            query.set('batch', String(broadcast.batch_uuid));
        }

        if (broadcast.notification_id) {
            query.set('notification', String(broadcast.notification_id));
        }

        const queryString = query.toString();
        const destination = queryString
            ? `${endpoints.manage}?${queryString}`
            : endpoints.manage;

        window.location.assign(destination);

        // Fallback: if navigation is prevented by the browser/environment,
        // allow subsequent clicks to try again.
        setTimeout(() => {
            state.navigating = false;
        }, 1500);
    }

    function showNextBroadcast() {
        if (state.queue.length === 0) {
            state.active = null;
            renderActive();
            return;
        }

        state.active = state.queue.shift();
        renderActive();
    }

    function enqueueBroadcasts(items) {
        let added = false;
        const existingIds = new Set(state.queue.map((item) => item.notification_id));
        if (state.active) {
            existingIds.add(state.active.notification_id);
        }

        items.forEach((item) => {
            if (!item || !item.notification_id || state.dismissed.has(item.notification_id)) {
                return;
            }

            if (!existingIds.has(item.notification_id)) {
                state.queue.push(item);
                existingIds.add(item.notification_id);
                added = true;
            }
        });

        if (added) {
            state.queue.sort((a, b) => new Date(a.sent_at) - new Date(b.sent_at));
        }

        if (!state.active) {
            showNextBroadcast();
        }
    }

    function fetchUnreadBroadcasts() {
        if (state.isPolling) {
            return;
        }

        state.isPolling = true;

        fetch(endpoints.unread, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'include',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to fetch broadcasts');
                }
                return response.json();
            })
            .then((payload) => {
                if (Array.isArray(payload?.data)) {
                    enqueueBroadcasts(payload.data);
                }
            })
            .catch((error) => {
                console.error('Broadcast polling error:', error);
            })
            .finally(() => {
                state.isPolling = false;
            });
    }

    function startPolling() {
        if (state.pollingTimer) {
            clearInterval(state.pollingTimer);
        }

        // Use longer polling interval as fallback (only if WebSocket fails)
        state.pollingTimer = setInterval(fetchUnreadBroadcasts, 60000);
    }
    
    function startRealtimeListener() {
        if (!window.Echo) {
            // Only show warning if Echo was expected but failed (not if intentionally disabled)
            if (!window.EchoDisabled) {
                console.warn('⚠️ Laravel Echo not available for broadcast notifications, using polling fallback');
            }
            startPolling();
            return;
        }
        
        if (!currentUserId) {
            console.warn('⚠️ Current user ID not found, cannot subscribe to broadcast notifications');
            startPolling();
            return;
        }
        
        const connection = window.Echo.connector.pusher.connection;

        function subscribeToBroadcastChannel() {
            if (state.broadcastEchoSubscribed) {
                return;
            }

            state.broadcastEchoSubscribed = true;
            console.log('✅ Subscribing to broadcast notifications for user:', currentUserId);

            window.Echo.private(`user.${currentUserId}`)
                .listen('.BroadcastNotificationCreated', (e) => {
                    console.log('📢 Received broadcast notification:', e);

                    // Fetch the full notification details to get notification_id
                    fetch(endpoints.unread, {
                        method: 'GET',
                        headers: {
                            Accept: 'application/json',
                        },
                        credentials: 'include',
                    })
                    .then((response) => response.json())
                    .then((payload) => {
                        if (Array.isArray(payload?.data)) {
                            // Find the notification with matching batch_uuid
                            const fullNotification = payload.data.find(n => n.batch_uuid === e.batch_uuid);
                            if (fullNotification) {
                                enqueueBroadcasts([fullNotification]);
                            }
                        }
                    })
                    .catch((error) => {
                        console.error('Error fetching notification details:', error);
                    });
                });
        }

        function onBroadcastEchoConnected() {
            console.log('✅ Reverb connected for broadcast notifications');
            subscribeToBroadcastChannel();
            setupEchoListeners();
            if (state.pollingTimer) {
                clearInterval(state.pollingTimer);
                state.pollingTimer = null;
            }
        }

        // Monitor connection status; defer private auth until WebSocket is open.
        if (connection.state === 'connected') {
            onBroadcastEchoConnected();
        } else {
            connection.bind('connected', onBroadcastEchoConnected);
        }

        connection.bind('disconnected', () => {
            console.warn('⚠️ Reverb disconnected, falling back to polling for broadcasts');
            startPolling();
        });
    }

    function dismissActiveBroadcast() {
        if (!state.active) {
            return;
        }

        state.dismissed.add(state.active.notification_id);
        showNextBroadcast();
    }

    function setupEchoListeners() {
        const echo = window.Echo;
        if (!echo || !currentUserId || state.legacyEchoListenersAttached) {
            return;
        }

        state.legacyEchoListenersAttached = true;

        try {
            echo.channel('broadcasts').listen('.BroadcastNotificationCreated', (event) => {
                if (event?.scope === 'all') {
                    fetchUnreadBroadcasts();
                }
            });

            echo.private(`user.${currentUserId}`).listen('.BroadcastNotificationCreated', () => {
                fetchUnreadBroadcasts();
            });
        } catch (error) {
            console.warn('Unable to register broadcast channel listeners:', error);
        }
    }

    function handleVisibilityChange() {
        if (document.visibilityState === 'visible') {
            fetchUnreadBroadcasts();
        }
    }

    function init() {
        // Initial fetch to show existing broadcasts
        fetchUnreadBroadcasts();
        
        // Use real-time listener with polling fallback
        startRealtimeListener();

        document.addEventListener('visibilitychange', handleVisibilityChange);

        if (dismissBtn && dismissBtn.length > 0) {
            dismissBtn.forEach(btn => {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    dismissActiveBroadcast();
                });
            });
        }

        const openBtn = bannerEl.querySelectorAll(openSelector);
        if (openBtn && openBtn.length > 0) {
            openBtn.forEach(btn => {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openActiveBroadcast();
                });
            });
        }

        bannerEl.addEventListener('click', (event) => {
            if (event.target.closest(dismissSelector)) {
                return;
            }
            if (event.target.closest(openSelector)) {
                return;
            }
            openActiveBroadcast();
        });

        [titleEl, messageEl, metaTextEl, contentEl].forEach((el) => {
            if (!el) {
                return;
            }

            el.addEventListener('click', (event) => {
                if (event.target.closest(dismissSelector)) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                openActiveBroadcast();
            });
        });

        bannerEl.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openActiveBroadcast();
            }
        });
    }

    init();
})();


