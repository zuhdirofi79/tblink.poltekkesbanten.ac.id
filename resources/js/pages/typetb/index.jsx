import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import AppLayout from '@/layouts/app-layout'
import { Head, router } from '@inertiajs/react'
import { Edit, Trash } from 'lucide-react'
import React, { useState } from 'react'

export default function TypeTb({ tbs }) {
    const [delDialog, setDelDialog] = useState(false)
    const [delData, setDelData] = useState()
    const closeDelDialog = () => {
        setDelDialog(false)
        setTimeout(() => setDelData(false), 300)
    }
    const bc = [
        {
            title: 'Type TB',
        }
    ]
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title="Type TB" />
            <div className="w-full">
                <div className='mx-auto max-w-7xl'>
                    <h3 className="text-primary mb-3 font-bold">
                        Type TB
                    </h3>
                    <Button onClick={() => router.visit(route('typetb.create'))} className={'my-2 mx-2'}>Tambah</Button>
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-3 mx-2">
                        {
                            tbs.map((v, i) => <div className="card rounded-md px-4 py-2 bg-base-100 shadow-md" key={i}>
                                <div className="card-body">
                                    <div className="flex gap-1 justify-between items-center">
                                        <div>
                                            <div className="text-lg font-semibold">{v.tipe_tb}</div>
                                        </div>
                                        <div className="flex justify-center">
                                            <Button onClick={() => router.visit(route('typetb.edit', v.id))} variant={'icon'}>
                                                < Edit className='text-amber-600' />
                                            </Button>
                                            <Button onClick={() => { setDelData(v); setTimeout(() => setDelDialog(true), 10) }} variant={'icon'}>
                                                <Trash className='text-red-600' />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>)
                        }
                    </div>
                </div>
            </div>
            {
                delData ?
                    <Dialog open={delDialog} onOpenChange={closeDelDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Hapus Tipe TB
                                </DialogTitle>
                                <p className="text-sm text-muted-foreground">
                                    Apakah anda ingin menghapus {delData.tipe_tb}?
                                </p>
                            </DialogHeader>
                            <div className="flex justify-end gap-2">
                                <Button onClick={closeDelDialog}>Batal</Button>
                                <Button variant='destructive' onClick={() => router.delete(route('typetb.delete', delData.id), {
                                    onSuccess: () => closeDelDialog()
                                })}>Hapus</Button>
                            </div>
                        </DialogContent>
                    </Dialog> : ''
            }
        </AppLayout >
    )
}
