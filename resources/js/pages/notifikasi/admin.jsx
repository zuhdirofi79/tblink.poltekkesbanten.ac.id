import { Button } from '@/components/ui/button'
import PushNotificationPanel from '@/components/push-notification-panel'
import AppLayout from '@/layouts/app-layout'
import { Head, router, useForm } from '@inertiajs/react'
import { BellIcon, XCircle } from 'lucide-react'
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import React, { useState } from 'react'
import { toast } from 'sonner'

export default function NotifAdmin({ notifs }) {
    const [mrId, setMrId] = useState(0)
    const bc = [
        {
            title: 'Notifikasi'
        },
    ]
    const fRead = useForm();
    const readNotif = (id) => {
        setMrId(id)
        fRead.post(route('notif.mark-read', id), {
            onFinish: () => setMrId(0),
            onSuccess: () => toast.success('Notifikasi dibaca'),
        })
    }
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title="Notifikasi" />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto max-w-7xl">
                    <h3 className="text-primary font-bold">
                        NOTIFIKASI
                    </h3>
                    <PushNotificationPanel />
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3 my-4">
                        {notifs.data.map((notif, i) => (
                            <div key={i} className="card w-full bg-base-200 rounded-md shadow-lg">
                                <div className="card-body p-2">
                                    <div className="flex gap-2">
                                        <div className='flex items-center justify-center'>
                                            <BellIcon className="w-8 h-8 animate-pulse text-primary" />
                                        </div>
                                        <div>
                                            <h2 className="card-title text-xl text-primary">{notif.data.title}</h2>
                                            <p>{notif.data.message}</p>
                                        </div>
                                        <div className='flex items-center justify-center'>
                                            <Button onClick={() => readNotif(notif.id)} disabled={mrId === notif.id} className={'rounded-full p-0'} variant={'destructive'}>
                                                <XCircle className={mrId === notif.id ? 'animate-spin' : ''} />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                    {
                        notifs.data.length > 10 ?
                            <Pagination>
                                <PaginationContent>
                                    {notifs.links.map((l, i) =>
                                        i === 0 ? (
                                            <PaginationItem key={i}>
                                                <PaginationPrevious
                                                    className="cursor-pointer"
                                                    onClick={() => (notifs.prev_page_url ? router.visit(notifs.prev_page_url) : null)}
                                                />
                                            </PaginationItem>
                                        ) : i + 1 === notifs.links.length ? (
                                            <PaginationItem key={i}>
                                                <PaginationNext
                                                    className="cursor-pointer"
                                                    onClick={() => (notifs.next_page_url ? router.visit(notifs.next_page_url) : null)}
                                                />
                                            </PaginationItem>
                                        ) : (
                                            <PaginationItem key={i}>
                                                <PaginationLink className="cursor-pointer" onClick={() => (l.url ? router.visit(l.url) : null)}>
                                                    {l.label}
                                                </PaginationLink>
                                            </PaginationItem>
                                        ),
                                    )}
                                </PaginationContent>
                            </Pagination>
                            : ''
                    }
                </div>
            </div>
        </AppLayout>
    )
}
