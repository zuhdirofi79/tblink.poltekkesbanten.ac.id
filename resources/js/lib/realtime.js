/**
 * Compile-time realtime configuration.
 *
 * VITE_* values are baked in during `npm run build`. An empty or placeholder
 * Reverb/Pusher key must never instantiate pusher-js, otherwise the whole
 * Inertia boot crashes with:
 *   "You must pass your app key when you instantiate Pusher."
 */

const INVALID_KEYS = new Set([
    '',
    'null',
    'undefined',
    'ganti_nanti',
    'app-key',
    'your-app-key',
    'reverb-key',
]);

function envString(value) {
    if (value === undefined || value === null) {
        return '';
    }

    return String(value).trim();
}

export function getRealtimeKey() {
    return envString(import.meta.env.VITE_REVERB_APP_KEY);
}

export function isRealtimeEnabled() {
    const key = getRealtimeKey();

    if (!key) {
        return false;
    }

    return !INVALID_KEYS.has(key.toLowerCase());
}

/**
 * Echo config for @laravel/echo-react `configureEcho()`.
 * Valid key  => Reverb/Pusher connector
 * No/invalid => Laravel "null" connector (no websocket, no Pusher constructor)
 */
export function getEchoConfig() {
    if (!isRealtimeEnabled()) {
        return {
            broadcaster: 'null',
        };
    }

    return {
        broadcaster: 'reverb',
        key: getRealtimeKey(),
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    };
}
