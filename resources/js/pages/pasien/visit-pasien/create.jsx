import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import { Head, router, useForm } from '@inertiajs/react'
import { Check, EditIcon, EllipsisIcon, MessageCircleQuestion, Trash } from 'lucide-react'
import moment from 'moment'
import React, { use, useState } from 'react'

export default function CreateVisitPasien({ pasien, jvs }) {
    const [selesaiVisitDialog, setSelesaiVisitDialog] = useState(false)
    const [deleteDialog, setDeleteDialog] = useState(false)
    const [jv, setJv] = useState()

    const closeDeleteDialog = () => {
        setDeleteDialog(false)
        setTimeout(() => setJv(undefined), 300)
    }
    const closeSelesaiVisitDialog = () => {
        setSelesaiVisitDialog(false)
        setTimeout(() => setJv(undefined), 300)
    }
    const bc = [
        {
            title: 'Pasien',
            href: route('pasien.index'),
        },
        {
            title: 'Jadwal Berobat ' + pasien.nama,
        }
    ]
    const { data, setData, errors, processing, post } = useForm({
        pasien_id: pasien.id,
        tgl: '',
        jam: '',
        keluhan: '',
        catatan: '',
    })
    const simpanJadwal = () => {
        post(route('visit.store'), {
            onSuccess: () => {
                setData({
                    pasien_id: pasien.id,
                    tgl: '',
                    jam: '',
                    keluhan: '',
                    catatan: '',
                })
            }
        })
    }
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
            <Head title={'Jadwal Berobat Pasien ' + pasien.nama} />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary font-bold">
                        Jadwal Berobat
                    </h3>
                    <div className="my-3">
                        <div className="grid md:grid-cols-8 gap-3">
                            <div className='grid gap-2'>
                                <Label>Tgl</Label>
                                <Input onChange={(e) => setData('tgl', e.target.value)} defaultValue={data.tgl} type='date' />
                                <InputError message={errors.tgl} />
                            </div>
                            <div className='grid gap-2'>
                                <Label>Jam</Label>
                                <Input onChange={(e) => setData('jam', e.target.value)} defaultValue={data.jam} type='time' />
                                <InputError message={errors.jam} />
                            </div>
                            <div className='grid col-span-2 gap-2'>
                                <Label>Keluhan</Label>
                                <Input onChange={(e) => setData('keluhan', e.target.value)} defaultValue={data.keluhan} type='text' />
                                <InputError message={errors.keluhan} />
                            </div>
                            <div className='grid col-span-2 gap-2'>
                                <Label>Catatan</Label>
                                <Input onChange={e => setData('catatan', e.target.value)} defaultValue={data.catatan} type='text' />
                                <InputError message={errors.catatan} />
                            </div>
                            <div className="flex col-span-2 justify-center items-center">
                                <Button disabled={processing} onClick={simpanJadwal} className={'w-full h-full'}>Tambahkan Jadwal</Button>
                            </div>
                        </div>
                    </div>
                    <div className="grid gap-3">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead></TableHead>
                                    <TableHead className='font-bold'>Tanggal</TableHead>
                                    <TableHead className='font-bold'>Jam</TableHead>
                                    <TableHead className='font-bold'>Keluhan</TableHead>
                                    <TableHead className='font-bold'>Catatan</TableHead>
                                    <TableHead className='font-bold'>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {
                                    jvs.data.map((jv) => <TableRow key={jv.id}>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button size='sm' variant='icon'>
                                                        <EllipsisIcon />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    <DropdownMenuItem onClick={() => router.visit(route('visit.edit', jv.id))}>
                                                        <EditIcon className='me-1 text-amber-600' />Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => askSelesaiVisit(jv)}>
                                                        <Check className='me-1 text-mkg-600' />Sudah Selesai
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => askDelete(jv)}>
                                                        <Trash className='me-1 text-destructive' />Hapus
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                        <TableCell>{moment(jv.tgl_visit).format('dddd, DD/MM/YYYY')}</TableCell>
                                        <TableCell>{moment(jv.tgl_visit).format('HH:mm')}</TableCell>
                                        <TableCell>{jv.keluhan}</TableCell>
                                        <TableCell>{jv.catatan}</TableCell>
                                        <TableCell>{jv.active ? 'Aktif' : 'Selesai'}</TableCell>
                                    </TableRow>)
                                }
                            </TableBody>
                        </Table>
                        <Pagination>
                            <PaginationContent>
                                {jvs.links.map((l, i) =>
                                    i === 0 ? (
                                        <PaginationItem key={i}>
                                            <PaginationPrevious
                                                className="cursor-pointer"
                                                onClick={() => (jvs.prev_page_url ? router.visit(jvs.prev_page_url) : null)}
                                            />
                                        </PaginationItem>
                                    ) : i + 1 === jvs.links.length ? (
                                        <PaginationItem key={i}>
                                            <PaginationNext
                                                className="cursor-pointer"
                                                onClick={() => (jvs.next_page_url ? router.visit(jvs.next_page_url) : null)}
                                            />
                                        </PaginationItem>
                                    ) : (
                                        <PaginationItem key={i}>
                                            <PaginationLink className="cursor-pointer" onClick={() => (l.url ? router.visit(l.url) : null)}>
                                                {l.label}
                                            </PaginationLink>
                                        </PaginationItem>
                                    ),
                                )}
                            </PaginationContent>
                        </Pagination>
                    </div>
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
