import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'
import { Head, router, useForm } from '@inertiajs/react'
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import React, { useRef, useState } from 'react'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { EditIcon, EllipsisIcon, TrashIcon } from 'lucide-react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
export default function IndexObat({ obats }) {
    const [obat, setObat] = useState()
    const [dialogOpen, setDialogOpen] = useState(false)

    const bc = [
        {
            title: 'Obat',
            href: route('obat.index'),
        },
    ]
    const delObat = (obat) => {
        setObat(obat)
        setTimeout(() => setDialogOpen(true), 10)
    }
    const closeDialog = () => {
        setDialogOpen(false)
        setTimeout(() => setPsn(undefined), 300)
    }
    const fDel = useForm()
    const submitDel = () => {
        if (obat) {
            fDel.delete(route('obat.delete', obat.id), {
                onSuccess: () => closeDialog()
            })
        } else {
            toast.error('Obat tidak ditemukan')
        }
    }
    const txtSearch = useRef();
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title="Obat" />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto max-w-7xl">
                    <h3 className="text-primary font-bold">
                        LIST OBAT
                    </h3>
                    <div className="my-3">
                        <Button onClick={() => router.visit(route('obat.create'))}>Tambah Obat</Button>
                    </div>
                    <div className="my-2">
                        <Label>Cari obats</Label>
                        <Input onKeyUp={(e) => {
                            if (e.key === 'Enter') {
                                cari(e.target.value);
                            }
                        }} ref={txtSearch} placeholder="Masukkan Nama/Deskripsi Obat" />
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead></TableHead>
                                <TableHead className={'font-semibold'}>Nama Obat</TableHead>
                                <TableHead className={'font-semibold'}>Deskripsi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {obats.data.map((o, i) => (
                                <TableRow key={i}>
                                    <TableCell className='flex justify-center'>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button size='sm' variant='icon'>
                                                    <EllipsisIcon />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent>
                                                <DropdownMenuItem onClick={() => router.visit(route('obat.edit', o.id))}>
                                                    <EditIcon className='me-1 text-amber-600' />Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => delObat(o)}>
                                                    <TrashIcon className='me-1 text-red-600' />Delete
                                                </DropdownMenuItem>

                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                    <TableCell>{o.nama_obat}</TableCell>
                                    <TableCell>{o.deskripsi}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination>
                        <PaginationContent>
                            {obats.links.map((l, i) =>
                                i === 0 ? (
                                    <PaginationItem key={i}>
                                        <PaginationPrevious
                                            className="cursor-pointer"
                                            onClick={() => (obats.prev_page_url ? router.visit(obats.prev_page_url) : null)}
                                        />
                                    </PaginationItem>
                                ) : i + 1 === obats.links.length ? (
                                    <PaginationItem key={i}>
                                        <PaginationNext
                                            className="cursor-pointer"
                                            onClick={() => (obats.next_page_url ? router.visit(obats.next_page_url) : null)}
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
                obat ?
                    <Dialog open={dialogOpen} onOpenChange={fDel.processing ? null : closeDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Hapus {obat.nama_obat}?</DialogTitle>
                                <DialogDescription className="text-destructive">{obat.deskripsi}</DialogDescription>
                            </DialogHeader>

                            <DialogFooter className='flex gap-2'>
                                <Button variant='destructive' disabled={fDel.processing} onClick={submitDel}>Hapus</Button>
                                <Button onClick={closeDialog}>Batal</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    : ''
            }
        </AppLayout>
    )
}
