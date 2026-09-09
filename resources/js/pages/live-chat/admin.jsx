import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import AppLayout from '@/layouts/app-layout'
import { Head, router } from '@inertiajs/react'
import { MessageCircleIcon } from 'lucide-react'
import moment from 'moment'
import React, { useEffect, useState } from 'react'

export default function LiveChatAdmin({ pasiens, sas }) {
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
                    <div className="mt-3 font-semibold text-mky-600">Superadmin</div>
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3 mb-3">
                        {
                            sas.map((v, i) => <div className='card  shadow-md rounded-lg' key={i}>
                                <div className='card-body p-2'>
                                    <div className="flex gap-4">
                                        <div className="flex justify-center items-center">
                                            <div className="avatar">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div className={`w-10 h-10 cursor-pointer hover:shadow-lg transition-shadow text-white flex justify-center items-center rounded-full ${moment().diff(moment(v.last_seen), 'minutes') < 3 ? 'bg-primary' : 'bg-muted-foreground'}`}>
                                                            <MessageCircleIcon onClick={() => router.visit(route('live-chat.room', v.id))} />
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Chat</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                        </div>
                                        <div>
                                            <div className="card-title font-semibold text-mky-600 flex items-center gap-2"><span>{v.name}</span> {v.count > 0 ? <Badge variant={'destructive'} className='rounded-full relative top-1 right-1'>{v.count}</Badge> : ''}</div>
                                            <div className={`${moment().diff(moment(v.last_seen), 'minutes') < 3 ? 'text-mky-600' : 'text-muted-foreground'}`}>Last Online {moment(v.last_seen).fromNow()}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>)
                        }
                    </div>
                    <div className="mt-3 font-semibold text-primary">Pasien</div>
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        {
                            pasiens.map((v, i) => <div className='card  shadow-md rounded-lg' key={i}>
                                <div className='card-body p-2'>
                                    <div className="flex gap-4">
                                        <div className="flex justify-center items-center">
                                            <div className="avatar">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div className={`w-10 h-10 cursor-pointer hover:shadow-lg transition-shadow text-white flex justify-center items-center rounded-full ${moment().diff(moment(v.last_seen), 'minutes') < 3 ? 'bg-primary' : 'bg-muted-foreground'}`}>
                                                            <MessageCircleIcon onClick={() => router.visit(route('live-chat.room', v.id))} />
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Chat dengan pasien</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                        </div>
                                        <div>
                                            <div className="card-title font-semibold text-primary flex items-center gap-2"><span>{v.name}</span> {v.count > 0 ? <Badge variant={'destructive'} className='rounded-full relative top-1 right-1'>{v.count}</Badge> : ''}</div>
                                            <div className={`${moment().diff(moment(v.last_seen), 'minutes') < 3 ? 'text-primary' : 'text-muted-foreground'}`}>Last Online {moment(v.last_seen).fromNow()}</div>
                                            Pendamping: {v.pasien.pendamping ? <div className='flex gap-2'>{v.pasien.pendamping.name}
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <MessageCircleIcon onClick={() => router.visit(route('live-chat.room', v.pasien.pendamping.id))} className='text-primary cursor-pointer hover:animate-pulse' />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Chat dengan pendamping</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div> : '-'}
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
