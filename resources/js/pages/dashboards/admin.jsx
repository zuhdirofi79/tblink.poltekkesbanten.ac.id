import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { router, usePage } from '@inertiajs/react'
import axios from 'axios'
import { Bell, Clock, MessageCircleIcon, Percent, RefreshCcw, Tablets } from 'lucide-react'
import moment from 'moment'
import React, { useEffect, useState } from 'react'
import { Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'
import { toast } from 'sonner'

export default function DashboardAdmin() {
    return (
        <div className="grid md:grid-cols-2 gap-4">
            <BelumMinumObat />
            <JadwalBerobat />
            <Kepatuhan />
            <KurangPatuh />
        </div>
    )
}
function KurangPatuh() {
    const [loading, setLoading] = useState(false)
    const [data, setData] = useState([])

    useEffect(() => {
        setLoading(true)
        axios.get(route('ds-data.admin.pasien-kurang-patuh'))
            .then(({ data }) => setData(data))
            .finally(() => setLoading(false))
    }, [])
    return <Card className={'min-h-100'}>
        <CardContent>
            <CardTitle className={'font-semibold text-lg text-center uppercase'}>Pasien kurang patuh</CardTitle>
            {
                loading ? <div className="flex justify-center items-center h-full">
                    <RefreshCcw className='animate-spin' size={40} />
                </div> : <ScrollArea className='h-100'>
                    <div className='grid gap-2'>
                        {
                            data.map(d => (
                                <div className='shadow-md flex justify-between rounded-lg px-3 py-2 border border-destructive/30' key={d.id}>
                                    <div className='grid gap-1'>
                                        <span className="text-lg font-semibold text-primary mb-2">{d.nama}</span>
                                        <div className="flex items-center gap-2">
                                            <Percent size={20} /><span>Tingkat kepatuhan: {d.kepatuhan_percent.toFixed(2)}%</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Tablets size={20} /><span>Total {d.kepatuhan_minum} x Minum, {d.kepatuhan_tdk_minum} x Tidak Minum Obat</span>
                                        </div>
                                    </div>
                                    <div className='flex items-center  justify-end'>
                                        <Button onClick={() => router.visit(route('live-chat.room', d.user_id))}>
                                            <div className="flex items-center gap-2">
                                                <MessageCircleIcon size={20} />
                                                <span>Chat</span>
                                            </div>
                                        </Button>
                                    </div>
                                </div>
                            ))
                        }
                    </div>
                </ScrollArea>
            }
        </CardContent>
    </Card>
}
function BelumMinumObat() {
    const [data, setData] = useState([])
    const [loading, setLoading] = useState(false)
    const [notifTitle, setNotifTitle] = useState('')
    const [notifBody, setNotifBody] = useState('')
    const [pasienData, setPasienData] = useState()
    const [notifDialog, setNotifDialog] = useState(false)
    const [notifLoading, setNotifLoading] = useState(false)
    const user = usePage().props.auth.user
    const closeNotifDialog = () => {
        setNotifDialog(false)
        setTimeout(() => {
            setNotifTitle('')
            setNotifBody('')
            setPasienData(undefined)
        }, 300)
    }
    useEffect(() => {
        setLoading(true)
        axios.get(route('ds-data.admin.pasien-belum-minum-obat'))
            .then(({ data }) => setData(data))
            .finally(() => setLoading(false))
    }, [])
    const kirimNotif = () => {
        setNotifLoading(true)
        axios.post(route('pasien.send-notif', pasienData.pasien.id), {
            judul: notifTitle,
            isi: notifBody
        })
            .then(() => {
                toast.success('Notifikasi berhasil dikirim')
                closeNotifDialog()
            })
            .finally(() => setNotifLoading(false))
            .catch(() => {
                toast.error('Gagal mengirim notifikasi')
            })
    }


    return <Card className={'min-h-100'}>
        <CardContent>
            <CardTitle className={'font-semibold text-lg text-center uppercase'}>Pasien belum minum obat</CardTitle>
            {
                loading ? <div className="flex justify-center items-center h-full">
                    <RefreshCcw className='animate-spin' size={40} />
                </div> : <ScrollArea className='h-100'> <div className='grid gap-2'>
                    {
                        data.map(d => (
                            <div className='shadow-md flex justify-between rounded-lg px-3 py-2 border border-destructive/30' key={d.id}>
                                <div className='grid gap-1'>
                                    <span className="text-lg font-semibold text-primary mb-2">{d.pasien.nama}</span>
                                    <div className="flex items-center gap-2">
                                        <Tablets size={20} /><span>{d.obat.nama_obat}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Clock size={20} /><span>{moment(d.waktu).format('HH:mm')}</span>
                                    </div>
                                </div>
                                <div className='grid items-center justify-end'>
                                    <Button onClick={() => router.visit(route('live-chat.room', d.pasien.user_id))}>
                                        <div className="flex items-center gap-2">
                                            <MessageCircleIcon size={20} />
                                            <span>Chat</span>
                                        </div>
                                    </Button>
                                    <Button onClick={() => {
                                        if (d.pasien.user.has_push_subscription) {
                                            setPasienData(d)
                                            setNotifTitle(`Pengingat Minum Obat ${d.obat.nama_obat}`)
                                            setNotifBody(`Obat ${d.obat.nama_obat} jam ${moment(d.waktu).format('HH:mm')} belum diminum - ${user.name}`)
                                            setTimeout(() => setNotifDialog(true), 10)
                                        } else {
                                            toast.error('Pasien belum mendaftarkan perangkat untuk menerima notifikasi')
                                        }
                                    }}>
                                        <div className="flex items-center gap-2">
                                            <Bell size={20} />
                                            <span>Notifikasi</span>
                                        </div>
                                    </Button>
                                </div>
                            </div>
                        ))
                    }
                </div>
                </ScrollArea>
            }
        </CardContent>
        {
            pasienData ?
                <Dialog open={notifDialog} onOpenChange={closeNotifDialog}>
                    <DialogContent>
                        <DialogTitle>Kirim Notifikasi</DialogTitle>
                        <DialogDescription>{pasienData.pasien.nama}</DialogDescription>
                        <div className="grid gap-2">
                            <div className='grid gap-1'>
                                <Label>Judul Notifikasi</Label>
                                <Input value={notifTitle} onChange={(e) => setNotifTitle(e.target.value)} />
                            </div>
                            <div className='grid gap-1'>
                                <Label>Isi Notifikasi</Label>
                                <Input value={notifBody} onChange={(e) => setNotifBody(e.target.value)} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button onClick={closeNotifDialog}>Batal</Button>
                            <Button onClick={kirimNotif} disabled={notifLoading}>Kirim</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                : ''
        }
    </Card>
}
function Kepatuhan() {
    const [data1, setData1] = useState([])
    const [data2, setData2] = useState([])
    const [barData, setBarData] = useState([])
    const [loading, setLoading] = useState(false)
    useEffect(() => {
        setLoading(true)
        axios.get(route('ds-data.admin.skala-kepatuhan'))
            .then(({ data }) => {
                setData1([
                    {
                        name: 'Sudah Diminum (%)',
                        value: Math.round(data.sudahPersen),
                    },
                    {
                        name: 'Belum Diminum (%)',
                        value: Math.round(data.belumPersen),
                    },
                ])
                setData2([
                    {
                        name: 'Sudah Diminum (Jumlah Data)',
                        value: data.sudah,
                    },
                    {
                        name: 'Belum Diminum (Jumlah Data)',
                        value: data.belum,
                    },
                ])
            }).finally(() => setLoading(false))
    }, [])

    return <Card className={'min-h-120'}>
        <CardContent>

            <div className="text-center font-semibold text-lg uppercase">
                Skala Kepatuhan
            </div>
            {
                loading ? <div className="flex justify-center items-center h-full">
                    <RefreshCcw className='animate-spin' size={40} />
                </div> :
                    <PieChart width={400} height={400} style={{ width: '100%', aspectRatio: 1 }} margin={{ top: 50, right: 50, bottom: 50, left: 50 }}>
                        <Pie dataKey={'value'} nameKey='name' data={data2} cx="50%" cy="50%" fill="#82ca9d" outerRadius="50%" />
                        <Pie dataKey={'value'} nameKey='name' data={data1} cx="50%" cy="50%" innerRadius="60%" outerRadius="80%" fill="#8884d8" label />
                        <Tooltip />
                    </PieChart>
            }
        </CardContent>
    </Card>
}
function JadwalBerobat() {
    const [jbs, setJbs] = useState([])
    const [jbLoading, setJbLoading] = useState(false)
    useEffect(() => {
        setJbLoading(true)
        axios.get(route('ds-data.admin.jadwal-berobat'))
            .then(({ data }) => {
                setJbs(data)
            }).finally(() => setJbLoading(false))
    }, [])

    return <div>
        <Card className={'min-h-120'}>
            <CardContent>
                <CardTitle className='text-lg text-center font-semibold uppercase'>Jadwal Berobat Hari Ini</CardTitle>
                {
                    jbLoading ? <div>Loading...</div> :

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama Pasien</TableHead>
                                    <TableHead>Jam</TableHead>
                                    <TableHead>Admin</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {
                                    jbs.map(jb => (
                                        <TableRow key={jb.id}>
                                            <TableCell>{jb.pasien.nama}</TableCell>
                                            <TableCell>{moment(jb.tgl_visit).format('HH:mm')}</TableCell>
                                            <TableCell>{jb.admin.name}</TableCell>
                                        </TableRow>
                                    ))
                                }
                            </TableBody>
                        </Table>
                }
            </CardContent>
        </Card>
    </div>
}
