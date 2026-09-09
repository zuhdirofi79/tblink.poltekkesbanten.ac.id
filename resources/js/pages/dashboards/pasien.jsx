import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useForm } from '@inertiajs/react'
import axios from 'axios'
import { Check, Clock, Tablets } from 'lucide-react'
import moment from 'moment'
import React, { useEffect, useState } from 'react'
import { toast } from 'sonner'

export default function DashboardPasien() {
    const [schLoading, setSchLoading] = useState(false)
    const [schedules, setSchedules] = useState([])
    const [pasien, setPasien] = useState()
    const [dialogOpen, setDialogOpen] = useState(false)
    const [sch, setSch] = useState(undefined)
    const [refreshCount, setRefreshCount] = useState(0)

    const closeDialog = () => {
        setDialogOpen(false)
        setTimeout(() => setSch(undefined), 300)
    }
    useEffect(() => {
        setSchLoading(true)
        axios.get(route('ds-data.pasien.jadwal-minum-obat'))
            .then(({ data }) => {
                setSchedules(data.schedules)
                setPasien(data.pasien)
            })
            .finally(() => setSchLoading(false))
    }, [refreshCount])

    const sudahMinum = (sch) => {
        setSch(sch)
        setTimeout(() => setDialogOpen(true), 10)
    }
    const { processing, post } = useForm();
    const submitSudahMinum = () => {
        if (sch) {
            post(route('ds-data.pasien.sudah-minum-obat', sch.id), {
                onSuccess: () => {
                    closeDialog();
                    setRefreshCount(Math.random() * 1000)
                },
                onError: () => { toast.error('Gagal menandai obat sudah diminum, silahkan coba lagi.') },
            })
        }
    }
    return (
        <div>
            <div className="flex items-center justify-between my-2">
                <h2 className="text-xl font-semibold tracking-tight">Jadwal Minum Obat {pasien ? pasien.nama : ''} Hari Ini </h2>
            </div>
            {
                schLoading ? <div className="flex justify-center">
                    <div className="loader ease-linear rounded-full border-4 border-t-4 border-gray-200 h-12 w-12 mb-4"></div>
                </div> :
                    <div className="grid lg:grid-cols-2 gap-4">
                        {
                            (schedules || []).map((sch, index) => <div key={index} className={`shadow-md rounded-md p-2 border ${!sch.sudah_minum ? 'border-destructive' : 'border-primary'}`}>
                                <div className="flex items-center justify-between">
                                    <div className='grid gap-2'>
                                        <h3 className="text-md font-semibold">{sch.obat.nama_obat}</h3>
                                        <p className="text-sm text-muted-foreground">{sch.obat.deskripsi}</p>
                                        <div className="flex gap-4">
                                            <div className="flex gap-2 items-center">
                                                <Clock className={`h-4 w-4 ${!sch.sudah_minum ? 'text-destructive' : 'text-primary'}`} />
                                                <p className="text-sm">{moment(sch.waktu).format('HH:mm')}</p>
                                            </div>
                                            <div className="flex gap-2 items-center">
                                                <Tablets className={`h-4 w-4 ${!sch.sudah_minum ? 'text-destructive' : 'text-primary'}`} />
                                                <p className="text-sm">{sch.dosis} {sch.satuan}</p>
                                            </div>
                                        </div>
                                    </div>
                                    {
                                        !sch.sudah_minum ?
                                            <Button onClick={() => sudahMinum(sch)} ><Check /></Button>
                                            :
                                            <div className='flex gap-2'>
                                                <Check className='text-primary' />
                                                <p>{moment(sch.waktu_aktual_minum).format('HH:mm')}</p>
                                            </div>
                                    }
                                </div>
                            </div>)
                        }
                    </div>
            }
            {
                sch &&
                <Dialog open={dialogOpen} onOpenChange={closeDialog} onClose={closeDialog} >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Obat ini sudah diminum?</DialogTitle>
                            <DialogDescription>{sch.obat.nama_obat}, Jadwal jam {moment(sch.waktu).format('HH:mm')}</DialogDescription>
                        </DialogHeader>
                        <DialogFooter className='flex gap-2'>
                            <Button disabled={processing} onClick={submitSudahMinum}>Sudah</Button>
                            <Button variant={'destructive'} onClick={closeDialog}>Batal</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            }
        </div >
    )
}
