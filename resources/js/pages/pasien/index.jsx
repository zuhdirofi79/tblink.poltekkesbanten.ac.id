import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import { Head, router, useForm } from '@inertiajs/react'
import { EditIcon, EllipsisIcon, Hospital, List, Tablets, TrashIcon } from 'lucide-react'
import React, { useRef, useState } from 'react'
import { toast } from 'sonner'

export default function Pasien({ pasien, canSelectPuskesmas = false }) {
    const [psn, setPsn] = useState()
    const [dialogOpen, setDialogOpen] = useState(false)
    const breadcrumbs = [
        {
            title: 'Pasien',
            href: '/pasien',
        },
        {
            title: 'List Pasien',
            href: '/pasien',
        },
    ]
    const txtSearch = useRef()
    const cari = (e) => {
        txtSearch.current.value = e
        router.visit('', {
            data: {
                q: e,
            },
            preserveState: true,
            preserveScroll: true,
        });
    }
    const fDel = useForm()
    const closeDialog = (force = false) => {
        if (fDel.processing && !force) {
            return
        }
        setDialogOpen(false)
        setTimeout(() => setPsn(undefined), 300)
    }
    const delPasien = p => {
        setPsn(p)
        setTimeout(() => setDialogOpen(true))
    }
    const submitDel = () => {
        if (fDel.processing) {
            return
        }
        if (psn) {
            fDel.delete(route('pasien.delete', psn.id), {
                onSuccess: () => closeDialog(true),
            })
        } else {
            toast.error('Pasien tidak ditemukan')
        }
    }
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pasien" />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary font-bold">
                        LIST PASIEN
                    </h3>
                    <div className="my-3">
                        <Button onClick={() => router.visit(route('pasien.create'))}>Tambah Pasien</Button>
                    </div>
                    <div className="my-2">
                        <Label>Cari Pasien</Label>
                        <Input onKeyUp={(e) => {
                            if (e.key === 'Enter') {
                                cari(e.target.value);
                            }
                        }} ref={txtSearch} placeholder="Masukkan Nama/NIK Pasien" />
                    </div>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead></TableHead>
                                    <TableHead>No</TableHead>
                                    <TableHead>Nama Pasien</TableHead>
                                    <TableHead>Username</TableHead>
                                    <TableHead>Puskesmas</TableHead>
                                    <TableHead>Pendamping</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>No HP</TableHead>
                                    <TableHead>Tipe TB</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pasien.data.map((p, i) => (
                                    <TableRow key={p.id ?? i}>
                                        <TableCell className='flex justify-center'>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button size='sm' variant='icon'>
                                                        <EllipsisIcon />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    <DropdownMenuItem onClick={() => router.visit(route('pasien.obat.index', p.id))}>
                                                        <Tablets className='me-1 text-primary' />Obat Pasien
                                                    </DropdownMenuItem>
                                                    {!canSelectPuskesmas ? (
                                                        <DropdownMenuItem onClick={() => router.visit(route('visit.create', p.id))}>
                                                            <Hospital className='me-1 text-mkg-800' />Jadwal Berobat
                                                        </DropdownMenuItem>
                                                    ) : null}
                                                    <DropdownMenuItem onClick={() => router.visit(route('pasien.show', p.id))}>
                                                        <List className='me-1 text-mky-600' />Detail
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => router.visit(route('pasien.edit', p.id))}>
                                                        <EditIcon className='me-1 text-amber-600' />Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => delPasien(p)}>
                                                        <TrashIcon className='me-1 text-red-600' />Hapus
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                        <TableCell>{i + 1}</TableCell>
                                        <TableCell>{p.nama}</TableCell>
                                        <TableCell>{p.user?.username ?? '-'}</TableCell>
                                        <TableCell>{p.puskesmas?.nama_puskesmas ?? '-'}</TableCell>
                                        <TableCell>{p.pendamping?.name ?? '-'}</TableCell>
                                        <TableCell>{p.status}</TableCell>
                                        <TableCell>{p.user?.no_hp ?? '-'}</TableCell>
                                        <TableCell>{p.tipe_tb}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    <Pagination>
                        <PaginationContent>
                            {pasien.links.map((l, i) =>
                                i === 0 ? (
                                    <PaginationItem key={i}>
                                        <PaginationPrevious
                                            className="cursor-pointer"
                                            onClick={() => (pasien.prev_page_url ? router.visit(pasien.prev_page_url) : null)}
                                        />
                                    </PaginationItem>
                                ) : i + 1 === pasien.links.length ? (
                                    <PaginationItem key={i}>
                                        <PaginationNext
                                            className="cursor-pointer"
                                            onClick={() => (pasien.next_page_url ? router.visit(pasien.next_page_url) : null)}
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
            {
                psn ?
                    <Dialog open={dialogOpen} onOpenChange={fDel.processing ? undefined : closeDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Hapus Pasien?</DialogTitle>
                                <DialogDescription>
                                    Anda akan menonaktifkan akun pasien: <span className="font-semibold text-foreground">{psn.nama}</span>.
                                    Data riwayat pengobatan akan dipertahankan. Pasien tidak dapat login dan tidak akan menerima pengingat obat.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className='flex gap-2'>
                                <Button variant='outline' disabled={fDel.processing} onClick={closeDialog}>Batal</Button>
                                <Button variant='destructive' disabled={fDel.processing} onClick={submitDel}>
                                    {fDel.processing ? 'Memproses...' : 'Hapus Pasien'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    : ''
            }
        </AppLayout>
    )
}
