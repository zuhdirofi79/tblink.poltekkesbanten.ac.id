import { Card, CardContent } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import { Head, useForm } from '@inertiajs/react'
import moment from 'moment'
import React, { useState } from 'react'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'

export default function IndexVisitPasien({ pasien, jvs }) {
    const [selesaiVisitDialog, setSelesaiVisitDialog] = useState(false)
    const [deleteDialog, setDeleteDialog] = useState(false)
    const [jv, setJv] = useState()
    const bc = [
        {
            title: 'Jadwal Berobat',
            href: route('pasien.visit-pasien.index'),
        },
    ]
    const fSelesai = useForm()
    const askSelesaiVisit = (data) => {
        setJv(data)
        setTimeout(() => setSelesaiVisitDialog(true), 100)
    }
    const askDelete = (id) => {
        setJv(id)
        setTimeout(() => setDeleteDialog(true), 100)
    }
    const fDel = useForm()
    const submitDelete = () => {
        fDel.delete(route('visit.destroy', jv), { onSuccess: () => closeDeleteDialog() })
    }
    const submitSelesai = () => {
        fSelesai.post(route('visit.selesai-visit', jv), {
            onSuccess: () => closeSelesaiVisitDialog(),
        })
    }
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title='Jadwal Berobat' />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary font-bold">
                        JADWAL BEROBAT
                    </h3>
                </div>
                <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                    {jvs.data.map((jv) => <Card className={'p-3'} key={jv.id}>
                        <CardContent className={'p-0'}>
                            <div className={`text-md ${jv.active ? 'text-mky-600' : 'text-primary'}`}>
                                {moment(jv.tgl_visit).format('dddd, DD/MM/YYYY HH:mm')}
                            </div>
                            {moment(jv.tgl_visit).format('YYYY-MM-DD') === moment().format('YYYY-MM-DD') ? <span className='rounded-md bg-mky-600 text-white px-2'>Hari Ini</span> : ''}
                            <dl>
                                <dt>Keluhan</dt>
                                <dd className='text-sm font-semibold'>{jv.keluhan}</dd>
                            </dl>
                            <dl>
                                <dt>Catatan</dt>
                                <dd className='text-sm font-semibold'>{jv.catatan}</dd>
                            </dl>
                        </CardContent>
                    </Card>)}
                </div>
            </div>
            {
                jv ?
                    <Dialog open={deleteDialog} onOpenChange={fDel.processing ? null : closeDeleteDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Hapus {moment(jv.tgl_visit).format('dddd, DD/MM/YYYY HH:mm')}?</DialogTitle>
                            </DialogHeader>
                            <div className="flex w-full justify-center items-center">
                                <MessageCircleQuestion className='text-destructive w-30 h-30' />
                            </div>
                            <DialogFooter className='flex gap-2'>
                                <Button variant='destructive' disabled={fDel.processing} onClick={submitDelete}>Hapus</Button>
                                <Button onClick={closeDeleteDialog}>Batal</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    : ''
            }
            {
                jv ?
                    <Dialog open={selesaiVisitDialog} onOpenChange={fSelesai.processing ? null : closeSelesaiVisitDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Jadwal {moment(jv.tgl_visit).format('dddd, DD/MM/YYYY HH:mm')} sudah selesai?</DialogTitle>
                            </DialogHeader>
                            <div className="flex w-full justify-center items-center">
                                <MessageCircleQuestion className='text-mky-600 w-30 h-30' />
                            </div>
                            <DialogFooter className='flex gap-2'>
                                <Button disabled={fSelesai.processing} onClick={submitSelesai}>Selesai</Button>
                                <Button variant={'secondary'} onClick={closeSelesaiVisitDialog}>Batal</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    : ''
            }
        </AppLayout>
    )
}
