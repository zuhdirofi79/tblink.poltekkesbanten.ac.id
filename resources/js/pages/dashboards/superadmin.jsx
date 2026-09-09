import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { ScrollArea } from '@/components/ui/scroll-area'
import { router } from '@inertiajs/react'
import axios from 'axios'
import { Hospital, MessageCircleIcon, Percent, RefreshCcw, Tablets } from 'lucide-react'
import React, { useEffect, useState } from 'react'
import { Pie, PieChart, Tooltip } from 'recharts'

export default function DsSa() {
    return (
        <div className='grid lg:grid-cols-2 gap-2'>
            <KurangPatuh />
            <Kepatuhan />
        </div>
    )
}

function KurangPatuh() {
    const [loading, setLoading] = useState(false)
    const [data, setData] = useState([])

    useEffect(() => {
        setLoading(true)
        axios.get(route('ds-data.sa.pasien-kurang-patuh'))
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
                                        <div className="flex items-center gap-2">
                                            <Hospital size={20} /><span>{d.puskesmas.nama_puskesmas}</span>
                                        </div>
                                    </div>
                                    <div className='flex flex-col items-center justify-around'>
                                        <Button className={'bg-mky-600 hover:bg-mky-500'} onClick={() => router.visit(route('live-chat.room', d.puskesmas.user_id))}>
                                            <div className="flex items-center gap-2">
                                                <MessageCircleIcon size={20} />
                                                <span>Admin</span>
                                            </div>
                                        </Button>
                                        <Button onClick={() => router.visit(route('live-chat.room', d.user_id))}>
                                            <div className="flex items-center gap-2">
                                                <MessageCircleIcon size={20} />
                                                <span>Pasien</span>
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
function Kepatuhan() {
    const [data1, setData1] = useState([])
    const [data2, setData2] = useState([])
    const [barData, setBarData] = useState([])
    const [loading, setLoading] = useState(false)
    useEffect(() => {
        setLoading(true)
        axios.get(route('ds-data.sa.skala-kepatuhan'))
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
