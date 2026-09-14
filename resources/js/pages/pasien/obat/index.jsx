import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { TableHead, Table, TableHeader, TableRow, TableBody, TableCell } from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import { Head, router, useForm } from '@inertiajs/react'
import { Trash } from 'lucide-react'
import moment from 'moment'
import React, { useMemo, useState } from 'react'

const TEST_SOURCE = 'manual_notification_test'

function isTestRow(ops) {
    return ops?.source === TEST_SOURCE
}

function isNotified(ops) {
    const sent = ops?.notifikasi_dikirim
    return Array.isArray(sent) && sent.length > 0
}

function canCancelTest(ops) {
    return isTestRow(ops) && !isNotified(ops) && moment(ops.waktu).isAfter(moment())
}

function defaultTestTime() {
    return moment().add(3, 'minutes').format('HH:mm')
}

export default function PIndexObat({ pasien, obatPasien, obatHariIni }) {
    const [delData, setDelData] = useState()
    const [delDialog, setDelDialog] = useState(false)
    const [delError, setDelError] = useState('')
    const [testOpen, setTestOpen] = useState(false)

    const selectableObat = useMemo(
        () => (obatPasien || []).filter((ops) => ops?.obat && ops?.jadwal?.details?.length),
        [obatPasien],
    )

    const closeDelDialog = () => {
        if (fDel.processing) {
            return
        }
        setDelDialog(false)
        setDelError('')
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
    const delJadwal = (row) => {
        if (fDel.processing || !row?.id) {
            return
        }
        setDelError('')
        fDel.delete(route('pasien.obat.hist.delete', { pasien: pasien.id, history: row.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setDelDialog(false)
                setDelError('')
                setTimeout(() => setDelData(false), 300)
            },
            onError: (errors) => {
                const first = errors && typeof errors === 'object' ? Object.values(errors)[0] : null
                setDelError(typeof first === 'string' && first ? first : 'Gagal menghapus jadwal minum obat.')
            },
        })
    }
    const fCancel = useForm()
    const cancelTest = (ops) => {
        fCancel.delete(route('pasien.obat.manual-reminder.cancel', [pasien.id, ops.id]))
    }

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        obat_pasien_id: '',
        waktu: defaultTestTime(),
    })

    const openTestModal = () => {
        clearErrors()
        reset()
        const firstId = selectableObat[0]?.id ? String(selectableObat[0].id) : ''
        setData({
            obat_pasien_id: firstId,
            waktu: defaultTestTime(),
        })
        setTestOpen(true)
    }

    const closeTestModal = () => {
        setTestOpen(false)
    }

    const selectedObatPasien = selectableObat.find((ops) => String(ops.id) === String(data.obat_pasien_id))
    const selectedNama = selectedObatPasien?.obat?.nama_obat || 'obat'
    const selectedDetail = selectedObatPasien?.jadwal?.details?.[0]
    const previewJam = data.waktu || '--:--'

    const submitTest = (e) => {
        e.preventDefault()
        post(route('pasien.obat.manual-reminder', pasien.id), {
            preserveScroll: true,
            onSuccess: () => closeTestModal(),
        })
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
                                    <div className='flex flex-wrap gap-2'>
                                        <Button onClick={generateJadwal} disabled={fGen.processing} >
                                            Generate
                                        </Button>
                                        <Button
                                            variant="outline"
                                            onClick={openTestModal}
                                            disabled={!selectableObat.length}
                                        >
                                            + Jadwal Uji Notifikasi
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
                                                        {canCancelTest(ops) ? (
                                                            <Button
                                                                size={'sm'}
                                                                variant={'outline'}
                                                                disabled={fCancel.processing}
                                                                onClick={() => cancelTest(ops)}
                                                            >
                                                                Batalkan Uji
                                                            </Button>
                                                        ) : null}
                                                        {!ops.sudah_minum ? (
                                                            <Button
                                                                size={'sm'}
                                                                variant={'icon'}
                                                                disabled={fDel.processing}
                                                                onClick={() => {
                                                                    setDelError('')
                                                                    setDelData(ops)
                                                                    setTimeout(() => setDelDialog(true), 100)
                                                                }}
                                                            >
                                                                <Trash className='text-destructive' />
                                                            </Button>
                                                        ) : null}
                                                    </TableCell>
                                                    <TableCell>{ops.obat.nama_obat}</TableCell>
                                                    <TableCell>{moment(ops.waktu).format('HH:mm')}</TableCell>
                                                    <TableCell>
                                                        {isTestRow(ops)
                                                            ? <span className='text-primary font-semibold'>UJI NOTIFIKASI{isNotified(ops) ? ' · Terkirim' : ''}</span>
                                                            : (ops.sudah_minum ? <span className='text-primary'>Sudah</span> : <span className='text-destructive animate-pulse'>Belum</span>)}
                                                    </TableCell>
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
                                    {delData.obat?.nama_obat} {moment(delData.waktu).format('HH:mm')}
                                </DialogDescription>
                            </DialogHeader>
                            {delError ? (
                                <p className='text-sm text-destructive'>{delError}</p>
                            ) : null}
                            <div className="flex gap-2 justify-end">
                                <Button onClick={closeDelDialog} disabled={fDel.processing}>Batal</Button>
                                <Button onClick={() => delJadwal(delData)} variant={'destructive'} disabled={fDel.processing}>
                                    {fDel.processing ? 'Menghapus...' : 'Hapus'}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                    : ''
            }
            <Dialog open={testOpen} onOpenChange={(open) => open ? openTestModal() : closeTestModal()}>
                <DialogContent>
                    <form onSubmit={submitTest} className='grid gap-4'>
                        <DialogHeader>
                            <DialogTitle>Jadwal Uji Notifikasi</DialogTitle>
                            <DialogDescription>
                                Pasien: {pasien.nama}. Jadwal ini hanya untuk menguji pengiriman otomatis hari ini, bukan resep jangka panjang.
                            </DialogDescription>
                        </DialogHeader>
                        <div className='grid gap-2'>
                            <Label>Obat</Label>
                            <Select
                                value={data.obat_pasien_id}
                                onValueChange={(value) => setData('obat_pasien_id', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih obat pasien" />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectableObat.map((ops) => (
                                        <SelectItem key={ops.id} value={String(ops.id)}>
                                            {ops.obat.nama_obat}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.obat_pasien_id} />
                        </div>
                        <div className='grid gap-2'>
                            <Label>Waktu hari ini</Label>
                            <Input
                                type='time'
                                value={data.waktu}
                                onChange={(e) => setData('waktu', e.target.value)}
                            />
                            <InputError message={errors.waktu} />
                        </div>
                        {selectedDetail ? (
                            <div className='text-sm text-muted-foreground'>
                                Dosis: {selectedDetail.dosis} {selectedDetail.satuan}
                            </div>
                        ) : null}
                        <div className='rounded-md border p-3 text-sm'>
                            <p className='font-semibold'>Pengingat Minum Obat {selectedNama}</p>
                            <p className='text-muted-foreground'>{selectedNama} pukul {previewJam} belum ditandai sudah diminum.</p>
                        </div>
                        <div className='flex flex-wrap gap-2 justify-end'>
                            <Button type='button' variant='outline' onClick={closeTestModal}>Batal</Button>
                            <Button type='submit' disabled={processing || !selectableObat.length}>Buat Jadwal Uji</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    )
}
