import { Button } from '@/components/ui/button';
import { router, usePage } from '@inertiajs/react';
import {
    clearFBToken,
    fingerprintFromHash,
    getFBToken,
    getPushRegistration,
    hashToken,
    peekFBToken,
} from '@/firebase';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

function StatusRow({ label, value, ok }) {
    return (
        <div className="flex justify-between gap-3 text-sm border-b py-1">
            <span className="text-muted-foreground">{label}</span>
            <span className={ok ? 'text-primary font-medium' : 'text-destructive font-medium'}>{value}</span>
        </div>
    );
}

function detectPlatform() {
    const ua = navigator.userAgent || '';
    if (/Android/i.test(ua)) {
        return 'android';
    }
    if (/iPhone|iPad|iPod/i.test(ua)) {
        return 'ios';
    }
    return 'desktop';
}

function detectDeviceLabel() {
    const platform = detectPlatform();
    if (platform === 'android') {
        return 'Android Chrome';
    }
    if (platform === 'ios') {
        return 'iOS';
    }
    return 'Desktop Chrome';
}

export default function PushNotificationPanel() {
    const page = usePage();
    const vapidPublicKey = page.props.push?.vapidPublicKey;
    const firebaseProjectId = page.props.push?.firebaseProjectId || import.meta.env.VITE_FIREBASE_PROJECT_ID || '';
    const messagingSenderId = page.props.push?.messagingSenderId || import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || '';
    const serverRegistered = Boolean(page.props.push?.serverRegistered);
    const serverTokenHashes = page.props.push?.serverTokenHashes || [];
    const serverFingerprints = page.props.push?.serverFingerprints || [];
    const multiDeviceTable = Boolean(page.props.push?.multiDeviceTable);
    const pushTest = page.props.flash?.push_test;

    const [permission, setPermission] = useState(
        typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
    );
    const [swState, setSwState] = useState('checking');
    const [swScript, setSwScript] = useState('checking');
    const [swScope, setSwScope] = useState('checking');
    const [pushManager, setPushManager] = useState('checking');
    const [subscription, setSubscription] = useState('checking');
    const [localRegistration, setLocalRegistration] = useState('checking');
    const [deviceMatch, setDeviceMatch] = useState('checking');
    const [busy, setBusy] = useState(false);
    const [localError, setLocalError] = useState('');
    const [lastForeground, setLastForeground] = useState('');
    const [lastBackground, setLastBackground] = useState('');
    const [lastDisplay, setLastDisplay] = useState('');
    const [currentToken, setCurrentToken] = useState('');
    const [localFingerprint, setLocalFingerprint] = useState('');

    const httpsOk = typeof window !== 'undefined' && window.isSecureContext;
    const notificationApi = typeof window !== 'undefined' && 'Notification' in window;
    const serviceWorkerApi = typeof navigator !== 'undefined' && 'serviceWorker' in navigator;
    const pushManagerApi = typeof window !== 'undefined' && 'PushManager' in window;

    const refreshStatus = async () => {
        if (!serviceWorkerApi) {
            setSwState('ERROR');
            setPushManager('UNAVAILABLE');
            setSubscription('NOT ACTIVE');
            setLocalRegistration('NONE');
            setDeviceMatch('UNKNOWN');
            return;
        }

        try {
            const registration = await getPushRegistration();
            setSwState(registration.active ? 'ACTIVE' : 'ERROR');
            setSwScript(registration.active?.scriptURL || registration.installing?.scriptURL || 'UNKNOWN');
            setSwScope(registration.scope || 'UNKNOWN');
            setPushManager(registration.pushManager ? 'AVAILABLE' : 'UNAVAILABLE');
            const sub = registration.pushManager ? await registration.pushManager.getSubscription() : null;
            setSubscription(sub ? 'ACTIVE' : 'NOT ACTIVE');

            let token = '';
            if (Notification.permission === 'granted' && sub) {
                token = await peekFBToken(vapidPublicKey);
            }
            setCurrentToken(token);
            setLocalRegistration(token ? 'PRESENT' : 'NONE');

            const clientHash = token ? await hashToken(token) : '';
            setLocalFingerprint(clientHash ? fingerprintFromHash(clientHash) : '');
            if (!token) {
                setDeviceMatch(serverTokenHashes.length ? 'NOT ON THIS DEVICE' : 'NOT SAVED');
            } else if (!serverTokenHashes.length) {
                setDeviceMatch('NOT SAVED');
            } else if (serverTokenHashes.includes(clientHash)) {
                setDeviceMatch('MATCHED');
            } else {
                setDeviceMatch('MISMATCHED');
            }

            if (token) {
                console.info('[FCM] current registration fingerprint', fingerprintFromHash(clientHash));
            }
        } catch (error) {
            setSwState('ERROR');
            setPushManager(pushManagerApi ? 'AVAILABLE' : 'UNAVAILABLE');
            setSubscription('NOT ACTIVE');
            setLocalRegistration('NONE');
            setDeviceMatch('UNKNOWN');
            setLocalError(error?.message || 'Service worker gagal didaftarkan');
        }

        if (notificationApi) {
            setPermission(Notification.permission);
        }
    };

    useEffect(() => {
        refreshStatus();
    }, [serverRegistered, serverTokenHashes.join('|')]);

    useEffect(() => {
        const onForeground = (event) => {
            const title = event.detail?.title || 'message';
            setLastForeground(`${title} · ${new Date(event.detail?.at || Date.now()).toLocaleTimeString()}`);
        };
        const onBackground = (event) => {
            const title = event.detail?.title || 'push';
            setLastBackground(`${title} · ${new Date(event.detail?.at || Date.now()).toLocaleTimeString()}`);
        };
        const onDisplay = (event) => {
            setLastDisplay(event.detail?.status || 'DISPLAY_REQUESTED');
        };

        window.addEventListener('controltb-fcm-foreground', onForeground);
        window.addEventListener('controltb-fcm-background', onBackground);
        window.addEventListener('controltb-fcm-display', onDisplay);

        const onSwMessage = (event) => {
            if (event.data?.type === 'controltb-push-diag' && event.data.diag) {
                const diag = event.data.diag;
                if (diag.last_background_push_received_at) {
                    setLastBackground(
                        `${diag.title || 'push'} · ${new Date(diag.last_background_push_received_at).toLocaleTimeString()} · ${diag.trace_id || ''}`,
                    );
                }
            }
        };
        navigator.serviceWorker?.addEventListener('message', onSwMessage);
        navigator.serviceWorker?.controller?.postMessage({ type: 'GET_PUSH_DIAG' });

        try {
            const stored = localStorage.getItem('controltb_last_foreground');
            if (stored) {
                const parsed = JSON.parse(stored);
                setLastForeground(`${parsed.title || 'message'} · ${new Date(parsed.at).toLocaleTimeString()} · ${parsed.traceId || ''}`);
            }
            const display = localStorage.getItem('controltb_last_foreground_display');
            if (display) {
                const parsed = JSON.parse(display);
                setLastDisplay(parsed.status || 'DISPLAY_REQUESTED');
            }
        } catch {
            // ignore
        }

        return () => {
            window.removeEventListener('controltb-fcm-foreground', onForeground);
            window.removeEventListener('controltb-fcm-background', onBackground);
            window.removeEventListener('controltb-fcm-display', onDisplay);
            navigator.serviceWorker?.removeEventListener('message', onSwMessage);
        };
    }, []);

    const fullyActive = useMemo(
        () =>
            httpsOk &&
            notificationApi &&
            permission === 'granted' &&
            swState === 'ACTIVE' &&
            pushManager === 'AVAILABLE' &&
            subscription === 'ACTIVE' &&
            localRegistration === 'PRESENT' &&
            serverRegistered &&
            deviceMatch === 'MATCHED',
        [httpsOk, notificationApi, permission, swState, pushManager, subscription, localRegistration, serverRegistered, deviceMatch],
    );

    const register = async () => {
        setLocalError('');
        if (!notificationApi || !serviceWorkerApi || !pushManagerApi) {
            setLocalError('Browser ini tidak mendukung Web Push.');
            return;
        }
        if (Notification.permission === 'denied') {
            return;
        }

        setBusy(true);
        try {
            if (Notification.permission === 'default') {
                const next = await Notification.requestPermission();
                setPermission(next);
                if (next !== 'granted') {
                    throw new Error('Izin notifikasi belum diberikan.');
                }
            }

            const registration = await getPushRegistration();
            await navigator.serviceWorker.ready;
            const token = await getFBToken(vapidPublicKey);
            const browserSub = await registration.pushManager.getSubscription();
            if (!browserSub) {
                throw new Error('Push subscription browser tidak terbentuk.');
            }

            router.post(
                route('user.register-device'),
                {
                    fcm_token: token,
                    platform: detectPlatform(),
                    device_label: detectDeviceLabel(),
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Notifikasi aktif. Subscription tersimpan di server.');
                        refreshStatus();
                    },
                    onError: () => {
                        toast.error('Gagal menyimpan subscription ke server.');
                    },
                    onFinish: () => setBusy(false),
                },
            );
        } catch (error) {
            setBusy(false);
            setLocalError(error?.message || 'Registrasi notifikasi gagal.');
            toast.error(error?.message || 'Registrasi notifikasi gagal.');
        }
    };

    const sendTest = async (mode) => {
        setBusy(true);
        try {
            const token = currentToken || (await peekFBToken(vapidPublicKey));
            const tokenHash = token ? await hashToken(token) : '';
            if (mode === 'current' && !tokenHash) {
                throw new Error('CURRENT DEVICE IS NOT REGISTERED. Daftar Notifikasi dulu di HP ini.');
            }
            router.post(
                route('user.test-notif'),
                { token_hash: tokenHash, mode },
                {
                    preserveScroll: true,
                    onSuccess: () => refreshStatus(),
                    onFinish: () => setBusy(false),
                },
            );
        } catch (error) {
            setBusy(false);
            toast.error(error?.message || 'Gagal mengirim test push.');
        }
    };

    const unsubscribe = async () => {
        setBusy(true);
        try {
            const token = currentToken || (await peekFBToken(vapidPublicKey));
            await clearFBToken();
            router.post(
                route('user.unregister-device'),
                { fcm_token: token },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Langganan notifikasi dihentikan.');
                        refreshStatus();
                    },
                    onFinish: () => setBusy(false),
                },
            );
        } catch (error) {
            setBusy(false);
            toast.error('Gagal berhenti berlangganan.');
        }
    };

    const matchOk = deviceMatch === 'MATCHED';

    return (
        <div className="grid gap-3 my-4">
            <div className="rounded-md border p-3">
                <h4 className="font-semibold mb-2">STATUS BROWSER</h4>
                <StatusRow label="HTTPS" value={httpsOk ? 'OK' : 'ERROR'} ok={httpsOk} />
                <StatusRow label="Firebase project" value={firebaseProjectId || 'MISSING'} ok={firebaseProjectId === 'controltb-3262b'} />
                <StatusRow label="Messaging sender" value={messagingSenderId || 'MISSING'} ok={Boolean(messagingSenderId)} />
                <StatusRow label="Notification API" value={notificationApi ? 'OK' : 'UNSUPPORTED'} ok={notificationApi} />
                <StatusRow label="Permission" value={(permission || 'unsupported').toUpperCase()} ok={permission === 'granted'} />
                <StatusRow label="Service Worker" value={swState} ok={swState === 'ACTIVE'} />
                <StatusRow label="Service Worker script" value={swScript} ok={String(swScript).includes('service-worker.js')} />
                <StatusRow label="Service Worker scope" value={swScope} ok={swScope.endsWith('/') || swScope.includes(window.location.origin + '/')} />
                <StatusRow label="Push Manager" value={pushManager} ok={pushManager === 'AVAILABLE'} />
                <StatusRow label="Local FCM registration" value={localRegistration} ok={localRegistration === 'PRESENT'} />
                <StatusRow label="Current local fingerprint" value={localFingerprint || 'NONE'} ok={Boolean(localFingerprint)} />
                <StatusRow
                    label="Server fingerprints"
                    value={serverFingerprints.length ? serverFingerprints.join(', ') : 'NONE'}
                    ok={serverFingerprints.length > 0}
                />
                <StatusRow label="Subscription" value={subscription} ok={subscription === 'ACTIVE'} />
                <StatusRow
                    label="Server registration"
                    value={
                        !serverRegistered
                            ? 'NOT SAVED'
                            : matchOk
                              ? 'SAVED (THIS DEVICE)'
                              : localRegistration === 'NONE'
                                ? 'SAVED (OTHER DEVICE)'
                                : 'SAVED (MULTIPLE / OTHER)'
                    }
                    ok={serverRegistered && matchOk}
                />
                <StatusRow label="CURRENT DEVICE REGISTERED" value={matchOk ? 'YES' : 'NO'} ok={matchOk} />
                <StatusRow label="CURRENT DEVICE MATCHES SERVER" value={deviceMatch} ok={matchOk} />
                <StatusRow label="Last foreground FCM" value={lastForeground || 'NONE'} ok={Boolean(lastForeground)} />
                <StatusRow label="Last background push" value={lastBackground || 'NONE'} ok={Boolean(lastBackground)} />
                <StatusRow label="Last display request" value={lastDisplay || 'NONE'} ok={lastDisplay === 'DISPLAY_REQUESTED'} />
            </div>

            {permission === 'denied' ? (
                <p className="text-sm text-destructive">
                    Notifikasi Chrome diblokir. Aktifkan melalui Setelan situs &gt; Notifikasi.
                </p>
            ) : null}

            {localRegistration === 'NONE' ? (
                <p className="text-sm text-destructive">
                    CURRENT DEVICE IS NOT REGISTERED. Daftar Notifikasi di perangkat ini. Tombol Test Current Device dinonaktifkan.
                </p>
            ) : null}

            {deviceMatch === 'MISMATCHED' || deviceMatch === 'NOT ON THIS DEVICE' ? (
                <p className="text-sm text-destructive">
                    Perangkat ini belum MATCHED ke server. Daftar Notifikasi di HP ini. Login laptop tidak boleh menimpa token HP.
                </p>
            ) : null}

            <p className="text-xs text-muted-foreground">
                Multi-device: setiap browser menyimpan token sendiri. Tabel user_push_subscriptions{' '}
                {multiDeviceTable ? 'sudah ada di database.' : 'belum dimigrasi; token disimpan di storage private sampai migrasi disetujui.'}
            </p>

            {localError ? <p className="text-sm text-destructive">{localError}</p> : null}

            {pushTest ? (
                <p className="text-sm">
                    Test {pushTest.mode}: {pushTest.delivery || pushTest.reason}
                    {pushTest.trace_id ? ` · trace ${pushTest.trace_id}` : ''} · found {pushTest.subscriptions_found}, sent{' '}
                    {pushTest.subscriptions_sent}, failed {pushTest.subscriptions_failed}
                    {pushTest.device_match ? ` · device ${pushTest.device_match}` : ''}
                    {pushTest.browser_received ? ' · BROWSER_RECEIVED' : ''}
                    {pushTest.display_requested ? ' · DISPLAY_REQUESTED' : lastDisplay ? ` · display ${lastDisplay}` : ' · DISPLAY_PENDING'}
                </p>
            ) : null}

            <p className="text-xs text-muted-foreground">
                FCM_ACCEPTED bukan bukti notification bar sudah tampil. Tes foreground: biarkan halaman terbuka. Tes background: Home + kunci layar.
            </p>

            <div className="grid gap-3 sm:grid-cols-2">
                {permission !== 'denied' ? (
                    <Button onClick={register} disabled={busy}>
                        {permission === 'default'
                            ? 'Aktifkan Notifikasi'
                            : fullyActive
                              ? 'Daftar Ulang Notifikasi'
                              : 'Daftar Notifikasi'}
                    </Button>
                ) : null}

                {fullyActive ? (
                    <Button disabled variant="secondary">
                        Notifikasi Aktif
                    </Button>
                ) : null}

                <Button
                    variant="secondary"
                    onClick={() => sendTest('current')}
                    disabled={busy || localRegistration !== 'PRESENT'}
                >
                    Test Current Device
                </Button>
                <Button variant="secondary" onClick={() => sendTest('all')} disabled={busy || !serverRegistered}>
                    Test All My Devices
                </Button>

                {serverRegistered ? (
                    <Button variant="destructive" onClick={unsubscribe} disabled={busy}>
                        Berhenti Berlangganan
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
