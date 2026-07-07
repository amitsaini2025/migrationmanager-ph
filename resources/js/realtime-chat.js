/**
 * Real-time chat via Laravel Reverb (Pusher protocol).
 *
 * Uses VITE_REVERB_* from .env; auth is signed at /api/broadcasting/auth (Sanctum Bearer).
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally (required by Laravel Echo)
window.Pusher = Pusher;

class RealtimeChat {
    constructor(config = {}) {
        this.config = {
            reverbKey:
                config.reverbKey ||
                config.pusherKey ||
                import.meta.env.VITE_REVERB_APP_KEY,
            apiBaseUrl: config.apiBaseUrl || '/api',
            authToken: config.authToken || null,
            userId: config.userId || null,
            enableLogging: config.enableLogging || false,
            ...config
        };

        this.echo = null;
        this.listeners = {
            onMessageReceived: [],
            onMessageSent: [],
            onMessageUpdated: [],
            onUnreadCountUpdated: [],
            onConnectionEstablished: [],
            onConnectionError: []
        };

        this.initialize();
    }

    initialize() {
        try {
            if (!this.config.reverbKey) {
                throw new Error(
                    'Reverb app key is required. Set VITE_REVERB_APP_KEY in .env (see REVERB_APP_KEY).'
                );
            }

            const useTLS = import.meta.env.VITE_REVERB_SCHEME === 'https';
            const port = parseInt(import.meta.env.VITE_REVERB_PORT, 10);
            const wsPort = !isNaN(port) ? port : useTLS ? 443 : 8080;

            this.echo = new Echo({
                broadcaster: 'reverb',
                key: this.config.reverbKey,
                wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
                wsPort,
                wssPort: wsPort,
                forceTLS: useTLS,
                enabledTransports: useTLS ? ['wss'] : ['ws'],
                authEndpoint: `${this.config.apiBaseUrl}/broadcasting/auth`,
                auth: {
                    headers: {
                        Authorization: `Bearer ${this.config.authToken}`,
                        Accept: 'application/json',
                    },
                },
            });

            if (this.config.enableLogging) {
                Pusher.logToConsole = true;
            }

            console.log('✅ Laravel Echo initialized with Reverb', useTLS ? '(wss)' : '(ws)');
            this.triggerListeners('onConnectionEstablished');
        } catch (error) {
            console.error('❌ Failed to initialize Echo:', error);
            this.triggerListeners('onConnectionError', error);
        }
    }

    /**
     * Subscribe to user's private channel
     * Receives messages, read receipts, and notifications
     */
    subscribeToUserChannel(userId = null) {
        const targetUserId = userId || this.config.userId;
        
        if (!targetUserId) {
            console.error('❌ User ID is required to subscribe');
            return;
        }

        const channel = this.echo.private(`user.${targetUserId}`);

        // Listen for new messages sent to this user
        channel.listen('.message.sent', (event) => {
            console.log('📨 New message received:', event);
            this.triggerListeners('onMessageReceived', event);
        });

        // Listen for message read receipts
        channel.listen('.message.received', (event) => {
            console.log('✓✓ Message read by recipient:', event);
            this.triggerListeners('onMessageUpdated', event);
        });

        // Listen for message updates (edits, deletions, etc.)
        channel.listen('.message.updated', (event) => {
            console.log('✏️ Message updated:', event);
            this.triggerListeners('onMessageUpdated', event);
        });

        // Listen for unread count updates
        channel.listen('.unread.count.updated', (event) => {
            console.log('🔢 Unread count updated:', event);
            this.triggerListeners('onUnreadCountUpdated', event);
        });

        console.log(`✅ Subscribed to user channel: user.${targetUserId}`);
        return channel;
    }

    /**
     * Subscribe to matter-specific channel
     * Receives all messages for a specific matter
     */
    subscribeToMatterChannel(matterId) {
        if (!matterId) {
            console.error('❌ Matter ID is required to subscribe');
            return;
        }

        const channel = this.echo.private(`matter.${matterId}`);

        // Listen for new messages in this matter
        channel.listen('.message.sent', (event) => {
            console.log('📨 Matter message received:', event);
            this.triggerListeners('onMessageReceived', event);
        });

        console.log(`✅ Subscribed to matter channel: matter.${matterId}`);
        return channel;
    }

    /**
     * Send a message via HTTP API
     * The backend will broadcast it to all connected clients
     */
    async sendMessage(messageData) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/messages/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.config.authToken}`,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(messageData)
            });

            const result = await response.json();

            if (result.success) {
                console.log('✅ Message sent successfully:', result.data);
                this.triggerListeners('onMessageSent', result.data);
                return result.data;
            } else {
                console.error('❌ Failed to send message:', result.message);
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('❌ Error sending message:', error);
            throw error;
        }
    }

    /**
     * Mark a message as read
     */
    async markAsRead(messageId) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/messages/${messageId}/read`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${this.config.authToken}`,
                    'Accept': 'application/json',
                }
            });

            const result = await response.json();

            if (result.success) {
                console.log('✅ Message marked as read');
                return result;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('❌ Error marking message as read:', error);
            throw error;
        }
    }

    /**
     * Get messages for a specific matter
     */
    async getMessages(clientMatterId, page = 1, limit = 20) {
        try {
            const response = await fetch(
                `${this.config.apiBaseUrl}/messages?client_matter_id=${clientMatterId}&page=${page}&limit=${limit}`,
                {
                    headers: {
                        'Authorization': `Bearer ${this.config.authToken}`,
                        'Accept': 'application/json',
                    }
                }
            );

            const result = await response.json();

            if (result.success) {
                return result.data;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('❌ Error fetching messages:', error);
            throw error;
        }
    }

    /**
     * Get unread message count
     */
    async getUnreadCount() {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/messages/unread-count`, {
                headers: {
                    'Authorization': `Bearer ${this.config.authToken}`,
                    'Accept': 'application/json',
                }
            });

            const result = await response.json();

            if (result.success) {
                return result.data.unread_count;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('❌ Error fetching unread count:', error);
            throw error;
        }
    }

    /**
     * Event listener management
     */
    on(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event].push(callback);
        }
    }

    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
    }

    triggerListeners(event, data = null) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    }

    /**
     * Disconnect from all channels
     */
    disconnect() {
        if (this.echo) {
            this.echo.disconnect();
            console.log('🔌 Disconnected from Reverb');
        }
    }

    /**
     * Leave a specific channel
     */
    leaveChannel(channelName) {
        if (this.echo) {
            this.echo.leave(channelName);
            console.log(`👋 Left channel: ${channelName}`);
        }
    }
}

// Export for use in other modules
export default RealtimeChat;

// Usage example:
/*
import RealtimeChat from './realtime-chat';

// Initialize the chat client
const chat = new RealtimeChat({
    reverbKey: import.meta.env.VITE_REVERB_APP_KEY,
    authToken: 'your-bearer-token',
    userId: 123
});

// Subscribe to user's private channel
chat.subscribeToUserChannel();

// Subscribe to a specific matter
chat.subscribeToMatterChannel(456);

// Listen for new messages
chat.on('onMessageReceived', (event) => {
    console.log('New message:', event.message);
    // Update UI with new message
});

// Listen for unread count updates
chat.on('onUnreadCountUpdated', (event) => {
    console.log('Unread count:', event.unread_count);
    // Update badge/notification count
});

// Send a message
await chat.sendMessage({
    message: 'Hello from web!',
    client_matter_id: 456
});

// Get message history
const messages = await chat.getMessages(456, 1, 20);

// Mark message as read
await chat.markAsRead(789);

// When done, disconnect
chat.disconnect();
*/

