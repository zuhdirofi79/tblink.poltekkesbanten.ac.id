import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getEchoConfig, isRealtimeEnabled } from './lib/realtime';

if (isRealtimeEnabled()) {
    window.Pusher = Pusher;
    window.Echo = new Echo(getEchoConfig());
} else {
    window.Pusher = undefined;
    window.Echo = undefined;
    console.info('[Realtime] Disabled: Echo was not instantiated (no valid app key).');
}
