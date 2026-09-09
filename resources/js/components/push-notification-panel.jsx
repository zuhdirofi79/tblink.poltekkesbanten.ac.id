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
    const serverTokenHash = page.props.push?.serverTokenHash || '';
    const multiDeviceReady = Boolean(page.props.push?.multiDeviceReady);
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
            setLocalRegistration(token ? 'ACTIVE' : 'NONE');

            const clientHash = token ? await hashToken(token) : '';
            if (!serverTokenHash) {
                setDeviceMatch(token ? 'MISMATCHED' : 'NOT SAVED');
            } else if (!clientHash) {
                setDeviceMatch('UNKNOWN');
            } else {
                setDeviceMatch(clientHash === serverTokenHash ? 'MATCHED' : 'MISMATCHED');
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
    }, [serverRegistered, serverTokenHash]);

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

        return () => {
            window.removeEventListener('controltb-fcm-foreground', onForeground);
            window.removeEventListener('controltb-fcm-background', onBackground);
            window.removeEventListener('controltb-fcm-display', onDisplay);
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
            serverRegistered &&
            deviceMatch === 'MATCHED',
        [httpsOk, notificationApi, permission, swState, pushManager, subscription, serverRegistered, deviceMatch],
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

    const sendTest = async () => {
        setBusy(true);
        try {
            const token = currentToken || (await peekFBToken(vapidPublicKey));
            const tokenHash = token ? await hashToken(token) : '';
            router.post(
                route('user.test-notif'),
                { token_hash: tokenHash },
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
                <StatusRow label="Local FCM registration" value={localRegistration} ok={localRegistration === 'ACTIVE'} />
                <StatusRow label="Subscription" value={subscription} ok={subscription === 'ACTIVE'} />
                <StatusRow
                    label="Server registration"
                    value={serverRegistered ? (matchOk ? 'SAVED (THIS DEVICE)' : 'SAVED (OTHER DEVICE)') : 'NOT SAVED'}
                    ok={serverRegistered && matchOk}
                />
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

            {deviceMatch === 'MISMATCHED' ? (
                <p className="text-sm text-destructive">
                    Token di server milik perangkat lain (kemungkinan laptop menimpa HP). Klik Daftar Ulang Notifikasi di HP ini, lalu jangan daftar ulang lagi di laptop.
                </p>
            ) : null}

            {!multiDeviceReady ? (
                <p className="text-xs text-muted-foreground">
                    Arsitektur saat ini: satu kolom users.device_token per user. HP dan laptop saling menimpa sampai migrasi user_push_subscriptions dijalankan di production.
                </p>
            ) : null}

            {localError ? <p className="text-sm text-destructive">{localError}</p> : null}

            {pushTest ? (
                <p className="text-sm">
                    Test: {pushTest.delivery || pushTest.reason} · found {pushTest.subscriptions_found}, sent {pushTest.subscriptions_sent}, failed{' '}
                    {pushTest.subscriptions_failed}
                    {pushTest.device_match ? ` · device ${pushTest.device_match}` : ''}
                    {pushTest.message_id ? ` · ${pushTest.message_id}` : ''}
                    {pushTest.fcm_project_id ? ` · FCM ${pushTest.fcm_project_id}` : ''}
                    {lastDisplay ? ` · display ${lastDisplay}` : ' · display DISPLAY_PENDING'}
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

                <Button variant="secondary" onClick={sendTest} disabled={busy || !serverRegistered}>
                    Test Kirim Notifikasi
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
