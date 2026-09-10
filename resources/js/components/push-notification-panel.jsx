import { Button } from '@/components/ui/button';
import { router, usePage } from '@inertiajs/react';
import {
    clearFBToken,
    fingerprintFromHash,
    getFBToken,
    hashToken,
    peekFBToken,
    resetCurrentDeviceMessaging,
} from '@/firebase';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

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
    const serverTokenHashes = page.props.push?.serverTokenHashes || [];
    const pushTest = page.props.flash?.push_test;

    const [permission, setPermission] = useState(
        typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
    );
    const [busy, setBusy] = useState(false);
    const [currentToken, setCurrentToken] = useState('');
    const [localFingerprint, setLocalFingerprint] = useState('');
    const [matched, setMatched] = useState(false);
    const [localError, setLocalError] = useState('');

    const notificationApi = typeof window !== 'undefined' && 'Notification' in window;
    const serviceWorkerApi = typeof navigator !== 'undefined' && 'serviceWorker' in navigator;

    const refreshStatus = async () => {
        if (!serviceWorkerApi || !notificationApi) {
            return;
        }

        setPermission(Notification.permission);
        if (Notification.permission !== 'granted') {
            setCurrentToken('');
            setLocalFingerprint('');
            setMatched(false);
            return;
        }

        const token = await peekFBToken(vapidPublicKey);
        setCurrentToken(token);
        const clientHash = token ? await hashToken(token) : '';
        setLocalFingerprint(clientHash ? fingerprintFromHash(clientHash) : '');
        setMatched(Boolean(clientHash && serverTokenHashes.includes(clientHash)));
    };

    useEffect(() => {
        refreshStatus();
    }, [serverTokenHashes.join('|')]);

    const saveToken = async (token) => {
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
                    toast.success('Notifikasi perangkat ini aktif.');
                    refreshStatus();
                },
                onError: () => toast.error('Gagal menyimpan token ke server.'),
                onFinish: () => setBusy(false),
            },
        );
    };

    const register = async () => {
        setLocalError('');
        if (!notificationApi || !serviceWorkerApi) {
            setLocalError('Browser ini tidak mendukung Web Push.');
            return;
        }
        if (Notification.permission === 'denied') {
            setLocalError('Notifikasi Chrome diblokir. Aktifkan di Setelan situs.');
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

            const token = await getFBToken(vapidPublicKey);
            await saveToken(token);
        } catch (error) {
            setBusy(false);
            setLocalError(error?.message || 'Registrasi notifikasi gagal.');
            toast.error(error?.message || 'Registrasi notifikasi gagal.');
        }
    };

    const resetDevice = async () => {
        setLocalError('');
        setBusy(true);
        try {
            if (Notification.permission !== 'granted') {
                const next = await Notification.requestPermission();
                setPermission(next);
                if (next !== 'granted') {
                    throw new Error('Izin notifikasi belum diberikan.');
                }
            }

            const previousToken = currentToken || (await peekFBToken(vapidPublicKey));
            const token = await resetCurrentDeviceMessaging(vapidPublicKey);
            if (previousToken && previousToken !== token) {
                router.post(
                    route('user.unregister-device'),
                    { fcm_token: previousToken },
                    {
                        preserveScroll: true,
                        onFinish: () => saveToken(token),
                    },
                );
                return;
            }

            await saveToken(token);
        } catch (error) {
            setBusy(false);
            setLocalError(error?.message || 'Reset notifikasi gagal.');
            toast.error(error?.message || 'Reset notifikasi gagal.');
        }
    };

    const sendTest = async () => {
        setBusy(true);
        try {
            const token = currentToken || (await peekFBToken(vapidPublicKey));
            const tokenHash = token ? await hashToken(token) : '';
            if (!tokenHash) {
                throw new Error('Daftar notifikasi di HP ini dulu.');
            }
            router.post(
                route('user.test-notif'),
                { token_hash: tokenHash, mode: 'current' },
                {
                    preserveScroll: true,
                    onSuccess: () => refreshStatus(),
                    onFinish: () => setBusy(false),
                },
            );
        } catch (error) {
            setBusy(false);
            toast.error(error?.message || 'Gagal mengirim test.');
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

    const active = matched && permission === 'granted';

    return (
        <div className="grid gap-3 my-4 rounded-md border p-3">
            <div>
                <p className="font-semibold">Status Notifikasi</p>
                <p className={active ? 'text-primary font-medium' : 'text-destructive font-medium'}>
                    {active ? '● Aktif' : '○ Belum aktif'}
                </p>
                <p className="text-sm text-muted-foreground mt-1">Perangkat ini: {detectDeviceLabel()}</p>
                {localFingerprint ? (
                    <p className="text-xs text-muted-foreground">Perangkat: {localFingerprint}</p>
                ) : null}
            </div>

            {permission === 'denied' ? (
                <p className="text-sm text-destructive">Notifikasi Chrome diblokir. Aktifkan melalui Setelan situs.</p>
            ) : null}

            {localError ? <p className="text-sm text-destructive">{localError}</p> : null}

            {pushTest ? (
                <p className="text-sm">
                    {pushTest.success
                        ? 'FCM menerima pesan. Cek notification bar HP (kunci layar). FCM_ACCEPTED belum berarti notifikasi tampil.'
                        : `Test gagal: ${pushTest.reason || 'unknown'}`}
                </p>
            ) : null}

            <div className="grid gap-3 sm:grid-cols-2">
                <Button onClick={register} disabled={busy || permission === 'denied'}>
                    {active ? 'Daftar Ulang Perangkat' : 'Daftar Notifikasi'}
                </Button>
                <Button onClick={sendTest} disabled={busy || !matched} variant="secondary">
                    Test Notifikasi
                </Button>
                <Button onClick={resetDevice} disabled={busy || permission === 'denied'} variant="secondary">
                    Reset Notifikasi Perangkat Ini
                </Button>
                {matched ? (
                    <Button onClick={unsubscribe} disabled={busy} variant="destructive">
                        Berhenti Berlangganan
                    </Button>
                ) : null}
            </div>
        </div>
    );
}
