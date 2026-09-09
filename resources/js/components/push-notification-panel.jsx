import { Button } from '@/components/ui/button';
import { router, usePage } from '@inertiajs/react';
import { clearFBToken, getFBToken, getPushRegistration } from '@/firebase';
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

export default function PushNotificationPanel() {
    const page = usePage();
    const vapidPublicKey = page.props.push?.vapidPublicKey;
    const firebaseProjectId = page.props.push?.firebaseProjectId || import.meta.env.VITE_FIREBASE_PROJECT_ID || '';
    const messagingSenderId = page.props.push?.messagingSenderId || import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || '';
    const serverRegistered = Boolean(page.props.push?.serverRegistered);
    const pushTest = page.props.flash?.push_test;

    const [permission, setPermission] = useState(
        typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
    );
    const [swState, setSwState] = useState('checking');
    const [pushManager, setPushManager] = useState('checking');
    const [subscription, setSubscription] = useState('checking');
    const [busy, setBusy] = useState(false);
    const [localError, setLocalError] = useState('');

    const httpsOk = typeof window !== 'undefined' && window.isSecureContext;
    const notificationApi = typeof window !== 'undefined' && 'Notification' in window;
    const serviceWorkerApi = typeof navigator !== 'undefined' && 'serviceWorker' in navigator;
    const pushManagerApi = typeof window !== 'undefined' && 'PushManager' in window;

    const refreshStatus = async () => {
        if (!serviceWorkerApi) {
            setSwState('ERROR');
            setPushManager('UNAVAILABLE');
            setSubscription('NOT ACTIVE');
            return;
        }

        try {
            const registration = await getPushRegistration();
            setSwState(registration.active ? 'ACTIVE' : 'ERROR');
            setPushManager(registration.pushManager ? 'AVAILABLE' : 'UNAVAILABLE');
            const sub = registration.pushManager ? await registration.pushManager.getSubscription() : null;
            setSubscription(sub ? 'ACTIVE' : 'NOT ACTIVE');
        } catch (error) {
            setSwState('ERROR');
            setPushManager(pushManagerApi ? 'AVAILABLE' : 'UNAVAILABLE');
            setSubscription('NOT ACTIVE');
            setLocalError(error?.message || 'Service worker gagal didaftarkan');
        }

        if (notificationApi) {
            setPermission(Notification.permission);
        }
    };

    useEffect(() => {
        refreshStatus();
    }, [serverRegistered]);

    const fullyActive = useMemo(
        () =>
            httpsOk &&
            notificationApi &&
            permission === 'granted' &&
            swState === 'ACTIVE' &&
            pushManager === 'AVAILABLE' &&
            subscription === 'ACTIVE' &&
            serverRegistered,
        [httpsOk, notificationApi, permission, swState, pushManager, subscription, serverRegistered],
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
                { fcm_token: token },
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

    const sendTest = () => {
        setBusy(true);
        router.post(
            route('user.test-notif'),
            {},
            {
                preserveScroll: true,
                onSuccess: () => refreshStatus(),
                onFinish: () => setBusy(false),
            },
        );
    };

    const unsubscribe = async () => {
        setBusy(true);
        try {
            await clearFBToken();
            router.post(
                route('user.unregister-device'),
                {},
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
                <StatusRow label="Push Manager" value={pushManager} ok={pushManager === 'AVAILABLE'} />
                <StatusRow label="Subscription" value={subscription} ok={subscription === 'ACTIVE'} />
                <StatusRow label="Server registration" value={serverRegistered ? 'SAVED' : 'NOT SAVED'} ok={serverRegistered} />
            </div>

            {permission === 'denied' ? (
                <p className="text-sm text-destructive">
                    Notifikasi Chrome diblokir. Aktifkan melalui Setelan situs &gt; Notifikasi.
                </p>
            ) : null}

            {localError ? <p className="text-sm text-destructive">{localError}</p> : null}

            {pushTest ? (
                <p className="text-sm">
                    Test push: found {pushTest.subscriptions_found}, sent {pushTest.subscriptions_sent}, failed{' '}
                    {pushTest.subscriptions_failed}
                    {pushTest.reason ? ` (${pushTest.reason})` : ''}
                    {pushTest.fcm_project_id ? ` · FCM ${pushTest.fcm_project_id}` : ''}
                </p>
            ) : null}

            <p className="text-xs text-muted-foreground">
                Token lama dari Firebase project lain tidak valid. Gunakan Daftar Ulang Notifikasi setelah deploy.
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
