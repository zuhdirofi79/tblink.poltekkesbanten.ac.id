import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import AppLayout from '@/layouts/app-layout'
import { cn } from '@/lib/utils'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useEcho } from '@laravel/echo-react'
import { ArrowLeft, Check, CheckCheck, RefreshCcw } from 'lucide-react'
import moment from 'moment'
import React, { useEffect, useRef, useState } from 'react'

export default function LiveChatRoom({ msgs, receiver }) {
    const user = usePage().props.auth.user;
    const [first, setFirst] = useState(false)
    useEcho('chat-notification.' + (user?.id ?? 'guest'), ["ChatNotifEvent", "ChatReadEvent"], e => {
        if (e.chat) {
            if (route().current('live-chat.room', e.sender_id)) {
                setMessages([...messages, e.chat])
            }
        }
        if (e.ids) {
            e.ids.map((id) => {
                setMessages(messages.map((m) => {
                    if (m.id == id) {
                        return { ...m, read_at: new Date() };
                    }
                    return m;
                }))
            })
        }
    })
    const { post, data, setData, errors, processing } = useForm({
        message: '',
        receiver_id: receiver.id,
    })
    const txtMsg = useRef()
    const sendMessage = () => {
        if (data.message && data.receiver_id) {
            post(route('live-chat.store'), {
                onSuccess: (data) => {

                    router.visit('', {
                        only: msgs,
                        preserveScroll: true,
                    })
                    setData('message', '')
                    txtMsg.current.value = ''
                }
            })
        }
    }

    const [messages, setMessages] = useState(msgs)
    useEffect(() => {
        setTimeout(() => {
            bottomRef.current?.scrollIntoView();
        }, 300);
    }, [])

    const bc = [
        {
            title: 'Live Chat dengan ' + receiver.name,
        },
    ]
    const [newMessage, setNewMessage] = useState("");
    const bottomRef = useRef(null);

    useEffect(() => {
        // if (!first) {
        //     setFirst(true)
        //     bottomRef.current?.scrollIntoView({ behavior: "smooth" });
        // }
        markRead();
    }, [messages]);

    const handleSend = () => {
        if (data.message.trim() === "") return;
        sendMessage();
        setNewMessage("");
    };
    const markRead = () => {
        const ids = messages.filter((m) => !m.read_at && m.sender_id !== user.id).map((m) => m.id);
        if (ids.length > 0) {
            router.visit(route('live-chat.mark-read'), {
                method: 'post',
                data: { ids: ids, receiver_id: receiver.id },
                onSuccess: () => {
                    setMessages(messages.map((m) => {
                        if (ids.includes(m.id)) {
                            return { ...m, read_at: new Date() };
                        }
                        return m;
                    }))
                }
            })
        }
    }

    return (
        <AppLayout breadcrumbs={bc}>
            <Head title="Live Chat" />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto max-w-7xl">
                    {/* Messages area */}
                    <ScrollArea className="p-4 flex-1 overflow-y-auto h-[80vh]">
                        {messages.map((msg) => (
                            <MessageBubble
                                key={msg.id}
                                content={msg.message}
                                isOwn={parseInt(msg.sender_id) === user.id}
                                sentAt={msg.created_at}
                                readAt={msg.read_at}
                                roleName={msg.role}
                            />
                        ))}
                        <div ref={bottomRef} />
                    </ScrollArea>

                    {/* Input area */}
                    <div className="flex items-center border-t p-3 gap-2">
                        <Button onClick={() => router.visit(route('live-chat.index'))} variant={'destructive'}>
                            <ArrowLeft />
                        </Button>
                        <Input
                            ref={txtMsg}
                            placeholder="Type a message..."
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === "Enter") handleSend();
                            }}
                        />
                        <Button onClick={handleSend}>Send</Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}

function MessageBubble({ content, isOwn, sentAt, readAt, roleName }) {
    return (
        <div
            className={cn(
                "flex mb-2",
                isOwn ? "justify-end" : "justify-start"
            )}
        >
            <div
                className={cn(
                    "max-w-xs p-3 rounded-md text-sm",
                    isOwn
                        ? "bg-primary text-primary-foreground"
                        : "bg-muted text-muted-foreground"
                )}
            >
                {

                    <span className="font-semibold">
                        {
                            isOwn ? 'Anda' : roleName
                        }
                    </span>

                }
                <p>{content}</p>
                <div className="flex justify-between">
                    <span>
                        {readAt ? <CheckCheck className={`w-[15px] ${isOwn ? 'text-white' : 'text-green-500'}`} /> : <Check />}
                    </span>
                    <span className="block text-[10px] opacity-60 mt-1 text-right">
                        {
                            moment(sentAt).diff(moment(), 'minutes') < moment().startOf('day').diff(moment(), 'minutes') ? moment(sentAt).format('DD/MM/YYYY HH:mm') : moment(sentAt).format('HH:mm')
                        }
                    </span>
                </div>
            </div>
        </div>
    );
}
