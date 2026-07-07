<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reverb messaging lab — {{ config('app.name', 'Laravel') }}</title>
    <style>
        :root { --bg: #0f1419; --panel: #1a2332; --border: #2d3a4f; --text: #e7ecf3; --muted: #8b9bb4; --accent: #3d8bfd; --ok: #3ecf8e; --err: #f87171; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; background: var(--bg); color: var(--text); line-height: 1.5; min-height: 100vh; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 1.5rem; }
        h1 { font-size: 1.35rem; font-weight: 600; margin: 0 0 0.5rem; }
        .sub { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem; }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem; margin-bottom: 1rem; }
        label { display: block; font-size: 0.8rem; color: var(--muted); margin-bottom: 0.35rem; }
        input[type="text"], input[type="number"], textarea {
            width: 100%; padding: 0.55rem 0.65rem; border-radius: 6px; border: 1px solid var(--border);
            background: #0d1117; color: var(--text); font-size: 0.95rem;
        }
        textarea { min-height: 72px; resize: vertical; }
        .row { display: grid; gap: 1rem; }
        @media (min-width: 640px) { .row-2 { grid-template-columns: 1fr 1fr; } }
        button {
            cursor: pointer; border: none; border-radius: 6px; padding: 0.55rem 1rem; font-size: 0.9rem; font-weight: 500;
            background: var(--accent); color: #fff;
        }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        button.secondary { background: var(--border); color: var(--text); }
        .status { font-size: 0.85rem; padding: 0.5rem 0.75rem; border-radius: 6px; background: #0d1117; border: 1px solid var(--border); }
        .status.ok { border-color: var(--ok); color: var(--ok); }
        .status.err { border-color: var(--err); color: var(--err); }
        .log { font-family: ui-monospace, monospace; font-size: 0.78rem; max-height: 280px; overflow: auto; white-space: pre-wrap; word-break: break-word; background: #0d1117; border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; }
        .flex { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .pill { font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: var(--border); color: var(--muted); }
        a { color: var(--accent); }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Reverb messaging lab</h1>
    <p class="sub">
        Staff-only. If <code>REVERB_ACCESS_LOGIN</code> / <code>REVERB_ACCESS_PASSWORD</code> are set in <code>.env</code>, you are signed in automatically for this page; otherwise use <a href="{{ route('crm.login') }}">CRM login</a> first. Uses the same Reverb WebSocket setup as Client Portal messages
        (<code>private-user.{staffId}</code> + event <code>message.sent</code>). Ensure <code>php artisan reverb:start</code> is running and
        <code>BROADCAST_DRIVER=reverb</code> in <code>.env</code>.
    </p>

    @if (!$currentUserId)
        <div class="panel status err">You are not logged in as staff. <a href="{{ route('crm.login') }}">Sign in</a>.</div>
    @else
        <div class="panel">
            <div class="flex" style="margin-bottom:1rem;">
                <span class="pill">Staff ID: {{ $currentUserId }}</span>
                @if ($staffName)<span class="pill">{{ $staffName }}</span>@endif
                <span class="pill">Broadcast driver: {{ $broadcastDriver ?? 'null' }}</span>
                @if (empty($reverbAppKey))<span class="pill" style="color:var(--err);">Reverb app key missing</span>@endif
            </div>

            <p class="sub" style="margin-top:0;">Option A: enter the numeric <strong>client matter ID</strong> (from DB or network tab). Option B: <strong>Portal client ID</strong> (client’s <code>admins.id</code>) + <strong>matter ref</strong> (e.g. <code>BA_1</code>).</p>

            <div class="row row-2">
                <div>
                    <label for="client_matter_id">Client matter ID</label>
                    <input type="number" id="client_matter_id" name="client_matter_id" min="1" placeholder="e.g. 42">
                </div>
                <div>
                    <label for="portal_client_id">Portal client ID (<code>admins.id</code>)</label>
                    <input type="number" id="portal_client_id" name="portal_client_id" min="1" placeholder="Client user id">
                </div>
            </div>
            <div style="margin-top:1rem;">
                <label for="matter_ref">Matter ref (e.g. BA_1)</label>
                <input type="text" id="matter_ref" name="matter_ref" placeholder="BA_1" autocomplete="off">
            </div>
            <div class="flex" style="margin-top:1rem;">
                <button type="button" id="btn_resolve">Connect to matter</button>
                <button type="button" id="btn_disconnect" class="secondary" disabled>Disconnect socket</button>
            </div>
        </div>

        <div class="panel">
            <div id="conn_status" class="status">Socket: idle. Resolve a matter first.</div>
        </div>

        <div class="panel">
            <label for="outgoing">Send test message (HTTP → Laravel → broadcast via Reverb)</label>
            <textarea id="outgoing" placeholder="Type a message…" disabled></textarea>
            <div class="flex" style="margin-top:0.65rem;">
                <button type="button" id="btn_send" disabled>Send</button>
            </div>
        </div>

        <div class="panel">
            <strong style="font-size:0.9rem;">Live log</strong>
            <p class="sub" style="margin:0.35rem 0 0.75rem;">Incoming <code>message.sent</code> for the connected matter appears here.</p>
            <div id="log" class="log"></div>
        </div>
    @endif
</div>

@if ($currentUserId)
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const RESOLVE_URL = @json(url('/reverb-messaging-test/resolve-matter'));
    const MESSAGES_URL = @json(url('/clients/matter-messages'));
    const SEND_URL = @json(url('/clients/send-message'));

    const currentUserId = {{ (int) $currentUserId }};
    const pusherAppKey = @json($reverbAppKey);
    const reverbHost = @json($reverbHost);
    const reverbPort = {{ (int) $reverbPort }};
    const reverbScheme = @json($reverbScheme);
    const reverbUseTLS = reverbScheme === 'https' && reverbHost !== 'localhost' && reverbHost !== '127.0.0.1';

    let activeMatterId = null;
    let pusher = null;
    let subscribedChannel = null;

    const logEl = document.getElementById('log');
    const connStatus = document.getElementById('conn_status');
    const btnResolve = document.getElementById('btn_resolve');
    const btnDisconnect = document.getElementById('btn_disconnect');
    const btnSend = document.getElementById('btn_send');
    const outgoing = document.getElementById('outgoing');

    function log(line, isError) {
        const t = new Date().toISOString();
        logEl.textContent += '[' + t + '] ' + line + '\n';
        logEl.scrollTop = logEl.scrollHeight;
        if (isError) console.error(line);
        else console.log(line);
    }

    function setConn(text, ok, err) {
        connStatus.textContent = text;
        connStatus.className = 'status' + (ok ? ' ok' : '') + (err ? ' err' : '');
    }

    function teardownSocket() {
        if (subscribedChannel) {
            try { subscribedChannel.unbind_all(); } catch (e) {}
            subscribedChannel = null;
        }
        if (pusher) {
            try { pusher.disconnect(); } catch (e) {}
            pusher = null;
        }
    }

    btnDisconnect.addEventListener('click', function () {
        teardownSocket();
        activeMatterId = null;
        btnDisconnect.disabled = true;
        btnSend.disabled = true;
        outgoing.disabled = true;
        setConn('Socket disconnected.', false, false);
        log('Disconnected.');
    });

    btnResolve.addEventListener('click', async function () {
        const mid = document.getElementById('client_matter_id').value.trim();
        const pid = document.getElementById('portal_client_id').value.trim();
        const ref = document.getElementById('matter_ref').value.trim();

        const body = {};
        if (mid) body.client_matter_id = parseInt(mid, 10);
        if (pid) body.portal_client_id = parseInt(pid, 10);
        if (ref) body.matter_ref = ref;

        setConn('Resolving matter…', false, false);
        btnResolve.disabled = true;

        try {
            const res = await fetch(RESOLVE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok || !data.success) {
                throw new Error(data.message || ('HTTP ' + res.status));
            }
            activeMatterId = data.client_matter_id;
            logEl.textContent = '';
            log('Resolved matter: id=' + activeMatterId + ' label=' + (data.matter_label || '') + ' portal_client_id=' + (data.portal_client_id || ''));

            teardownSocket();
            await loadHistory();
            connectReverb();
            outgoing.disabled = false;
            btnSend.disabled = false;
            btnDisconnect.disabled = false;
            setConn('Matter #' + activeMatterId + ' — subscribing to private-user.' + currentUserId + ' (Reverb)', true, false);
        } catch (e) {
            setConn('Resolve failed: ' + e.message, false, true);
            log('Resolve failed: ' + e.message, true);
        } finally {
            btnResolve.disabled = false;
        }
    });

    async function loadHistory() {
        if (!activeMatterId) return;
        log('Loading message history…');
        try {
            const url = MESSAGES_URL + '?client_matter_id=' + encodeURIComponent(activeMatterId);
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (!data.success || !data.data || !data.data.messages) {
                log('History: ' + (data.message || 'empty or error'), !data.success);
                return;
            }
            const list = data.data.messages;
            log('History loaded: ' + list.length + ' message(s).');
            list.forEach(function (m) {
                appendMessageLine(m, m.is_sent);
            });
        } catch (e) {
            log('History error: ' + e.message, true);
        }
    }

    function appendMessageLine(msg, isSent) {
        const who = isSent ? 'me' : ('from ' + (msg.sender_name || msg.sender || '?'));
        const text = (msg.message || '').replace(/\s+/g, ' ').trim() || '(no text)';
        log((isSent ? '→ ' : '← ') + who + ': ' + text);
    }

    function connectReverb() {
        if (!pusherAppKey) {
            setConn('Missing Reverb app key in config.', false, true);
            log('Set REVERB_APP_KEY and VITE_REVERB_* / config.', true);
            return;
        }

        function subscribe() {
            const channelName = 'private-user.' + currentUserId;
            subscribedChannel = pusher.subscribe(channelName);
            subscribedChannel.bind('pusher:subscription_succeeded', function () {
                log('Subscribed: ' + channelName);
                setConn('Reverb connected — ' + channelName, true, false);
            });
            subscribedChannel.bind('pusher:subscription_error', function (st) {
                log('Subscription error: ' + JSON.stringify(st), true);
                setConn('Channel subscription failed (check /broadcasting/auth).', false, true);
            });
            subscribedChannel.bind('message.sent', function (data) {
                if (!data || !data.message) return;
                if (parseInt(data.message.client_matter_id, 10) !== activeMatterId) return;
                const mine = parseInt(data.message.sender_id, 10) === currentUserId;
                appendMessageLine(data.message, mine);
            });
        }

        if (typeof Pusher !== 'undefined') {
            initPusherClient(subscribe);
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pusher/7.2.0/pusher.min.js';
        script.onload = function () { initPusherClient(subscribe); };
        script.onerror = function () {
            log('Failed to load Pusher JS', true);
            setConn('Could not load Pusher library.', false, true);
        };
        document.head.appendChild(script);
    }

    function initPusherClient(onReady) {
        try {
            const cfg = {
                cluster: 'ap2',
                forceTLS: reverbUseTLS,
                encrypted: reverbUseTLS,
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    }
                },
                wsHost: reverbHost,
                wsPort: reverbPort,
                wssPort: reverbPort,
                disableStats: true,
                enabledTransports: reverbUseTLS ? ['wss'] : ['ws']
            };
            pusher = new Pusher(pusherAppKey, cfg);
            pusher.connection.bind('connected', function () {
                log('WebSocket connected (Laravel Reverb).');
                onReady();
            });
            pusher.connection.bind('error', function (err) {
                log('Connection error: ' + JSON.stringify(err), true);
            });
            pusher.connection.bind('disconnected', function () {
                log('WebSocket disconnected.');
            });
        } catch (e) {
            log('Pusher init error: ' + e.message, true);
            setConn(e.message, false, true);
        }
    }

    btnSend.addEventListener('click', async function () {
        if (!activeMatterId) return;
        const text = outgoing.value.trim();
        if (!text) {
            log('Enter message text.', true);
            return;
        }
        btnSend.disabled = true;
        try {
            const res = await fetch(SEND_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ client_matter_id: activeMatterId, message: text })
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok || !data.success) {
                throw new Error(data.message || ('HTTP ' + res.status));
            }
            outgoing.value = '';
            log('Send OK (message_id=' + (data.data && data.data.message_id) + '). Real-time echo may follow via Reverb.');
        } catch (e) {
            log('Send failed: ' + e.message, true);
        } finally {
            btnSend.disabled = false;
        }
    });
})();
</script>
@endif
</body>
</html>
