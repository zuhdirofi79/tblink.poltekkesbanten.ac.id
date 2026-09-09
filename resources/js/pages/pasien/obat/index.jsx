import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { TableHead, Table, TableHeader, TableRow, TableBody, TableCell } from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import { Head, router, useForm } from '@inertiajs/react'
import { Trash } from 'lucide-react'
import moment from 'moment'
import React, { useState } from 'react'

export default function PIndexObat({ pasien, obatPasien, obatHariIni }) {
    const [delData, setDelData] = useState()
    const [delDialog, setDelDialog] = useState(false)

    const closeDelDialog = () => {
        setDelDialog(false)
        setTimeout(() => setDelData(false), 300)
    }
    const bc = [
        {
            title: 'Pasien',
            href: route('pasien.index'),
        },
        {
            title: 'Obat Pasien',
        }
    ]
    const fGen = useForm()
    const generateJadwal = () => {
        fGen.post(route('pasien.obat.generate', pasien.id))
    }
    const fDel = useForm()
    const delJadwal = (jo) => {
        fDel.delete(route('pasien.hist.delete', jo.id))
    }
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title='Pengobatan Pasien' />
            <div className='container w-screen md:w-auto my-4 lg:px-4 px-2'>
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        OBAT PASIEN {pasien.nama}
                    </h3>
                    <div className="grid gap-3">
                        <Card>
                            <CardContent>
                                <div className="grid md:flex gap-2 justify-between items-center">
                                    <CardTitle className={'text-primary uppercase'}>Jadwal Obat Hari Ini</CardTitle>
                                    <div>
                                        <Button onClick={generateJadwal} disabled={fGen.processing} >
                                            Generate
                                        </Button>
                                    </div>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead></TableHead>
                                            <TableHead className='font-bold'>Nama Obat</TableHead>
                                            <TableHead className='font-bold'>Jam</TableHead>
                                            <TableHead className='font-bold'>Sudah Diminum</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {
                                            obatHariIni.map(ops => (
                                                <TableRow key={ops.id}>
                                                    <TableCell className={'flex items-center justify-center gap-2'}>
                                                        {
                                                            ops.sudah_minum ? '' :
                                                                <Button size={'sm'} variant={'icon'} onClick={() => {
                                                                    setDelData(ops)
                                                                    setTimeout(() => setDelDialog(true), 100)
                                                                }}>
                                                                    <Trash className='text-destructive' />
                                                                </Button>
                                                        }
                                                    </TableCell>
                                                    <TableCell>{ops.obat.nama_obat}</TableCell>
                                                    <TableCell>{moment(ops.waktu).format('HH:mm')}</TableCell>
                                                    <TableCell>{ops.sudah_minum ? <span className='text-primary'>Sudah</span> : <span className='text-destructive animate-pulse'>Belum</span>}</TableCell>
                                                </TableRow>
                                            ))
                                        }
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent>
                                <div className="grid md:flex gap-2 justify-between items-center">
                                    <CardTitle className='text-primary uppercase'>List Obat</CardTitle>
                                    <Button onClick={() => router.visit(route('pasien.obat.create', pasien.id))} className={'my-3'}>Tambah Obat</Button>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className='font-bold'>Nama Obat</TableHead>
                                            <TableHead className='font-bold'>Deskripsi</TableHead>
                                            <TableHead className='font-bold'>Status</TableHead>
                                            <TableHead className='font-bold'>Stock</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {
                                            obatPasien.map(ops => (
                                                <TableRow className={'cursor-pointer'} onClick={() => router.visit(route('pasien.obat.edit', [pasien.id, ops.id]))} key={ops.id}>
                                                    <TableCell>{ops.obat.nama_obat}</TableCell>
                                                    <TableCell>{ops.obat.deskripsi}</TableCell>
                                                    <TableCell>{ops.active ? 'Aktif' : 'Non Aktif'}</TableCell>
                                                    <TableCell>{ops.stock}</TableCell>
                                                </TableRow>
                                            ))
                                        }
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                    </div>
                </div>
            </div>
            {
                delData ?
                    <Dialog open={delDialog} onOpenChange={closeDelDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Apakah anda yakin ingin menghapus jadwal minum obat ini ?</DialogTitle>
                                <DialogDescription>
                                    {delData.obat.nama_obat} {moment(delData.waktu).format('HH:mm')}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex gap-2 justify-end">
                                <Button onClick={closeDelDialog}>Batal</Button>
                                <Button onClick={() => delJadwal(delData)} variant={'destructive'}>Hapus</Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                    : ''
            }
        </AppLayout>
    )
}
