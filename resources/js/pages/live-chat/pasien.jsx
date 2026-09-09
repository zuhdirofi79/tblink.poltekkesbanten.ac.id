import { Button } from '@/components/ui/button'
import AppLayout from '@/layouts/app-layout'
import { Head, router } from '@inertiajs/react'
import { MessageCircleIcon } from 'lucide-react'
import moment from 'moment'
import React, { useEffect, useState } from 'react'

export default function LiveChatPasien({ admins }) {
    const [users, setUsers] = useState([])
    const bc = [
        {
            title: 'Live Chat',
            href: route('live-chat.admin')
        }
    ]
    // useEffect(() => {
    //     Echo.join('presence-users').here((users) => {
    //         setUsers(users)
    //         console.log(users);
    //     })
    //     return () => {
    //         Echo.leaveChannel('presence-users');
    //     }
    // }, [])
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title='Live Chat' />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto max-w-7xl">
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        {
                            admins.map((v, i) => <div onClick={() => router.visit(route('live-chat.room', v.id))} className='card cursor-pointer hover:shadow-lg transition-shadow shadow-md rounded-lg' key={i}>
                                <div className='card-body p-2'>
                                    <div className="flex gap-4">
                                        <div className="flex justify-center items-center">
                                            <div className="avatar">
                                                <div className={`w-10 h-10 text-white flex justify-center items-center rounded-full ${moment().diff(moment(v.last_seen), 'minutes') < 3 ? 'bg-primary' : 'bg-muted-foreground'}`}>
                                                    <MessageCircleIcon />
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div className="card-title font-semibold text-primary">{v.name}</div>
                                            <div className={`${moment().diff(moment(v.last_seen), 'minutes') < 3 ? 'text-primary' : 'text-muted-foreground'}`}>Last Online {moment(v.last_seen).fromNow()}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>)
                        }
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
