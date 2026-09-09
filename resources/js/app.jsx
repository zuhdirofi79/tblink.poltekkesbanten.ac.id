import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import './firebase'
import { configureEcho } from '@laravel/echo-react';
import { getEchoConfig, isRealtimeEnabled } from './lib/realtime';
// import './echo'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Always configure Echo so useEcho() in layouts does not throw.
// Without a valid VITE_REVERB_APP_KEY this uses the null connector,
// so pusher-js is never constructed and the dashboard can still mount.
configureEcho(getEchoConfig());

if (!isRealtimeEnabled()) {
    console.info('[Realtime] Disabled: no valid Reverb/Pusher configuration.');
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.jsx`, import.meta.glob('./pages/**/*.jsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

