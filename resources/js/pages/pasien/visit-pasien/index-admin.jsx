import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'
import { Head, router } from '@inertiajs/react'
import moment from 'moment'
import React, { Fragment, useEffect, useState } from 'react'
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Check, EditIcon, EllipsisIcon, Tablets, Trash, TrashIcon, User } from 'lucide-react'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem } from '@/components/ui/command'
import { ScrollArea } from '@/components/ui/scroll-area'

export default function JadwalVisitPasienAdmin({ jvs, tgl, pasiens }) {
    const [tanggal, setTanggal] = useState(tgl)
    const [psDialog, setPsDialog] = useState(false)
    const bc = [
        {
            title: 'Pasien',
            href: route('pasien.index'),
        },
        {
            title: 'Jadwal Visit',
            href: route('pasien.visit.index'),
        },
    ]
    const postTanggal = (tgl) => {
        router.visit('', {
            data: {
                tgl: tgl
            }
        })
    }
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title='Jadwal Visit' />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary font-bold">
                        JADWAL BEROBAT
                    </h3>
                    <Button onClick={() => setPsDialog(true)}>Tambah Jadwal</Button>
                    <div className="my-3">
                        <Label>Tanggal</Label>
                        <Input type='date' defaultValue={tanggal} onChange={e => {
                            setTanggal(e.target.value)
                            postTanggal(e.target.value)
                        }} />
                    </div>
                    <div className="grid gap-3">
                        <Card>
                            <CardContent>
                                <CardTitle>List Jadwal Tanggal {moment(tanggal).format('DD/MMM/YYYY')}</CardTitle>
                                <Table className={'mt-3'}>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead></TableHead>
                                            <TableHead className={'font-semibold'}>Pasien</TableHead>
                                            <TableHead className={'font-semibold'}>Keluhan</TableHead>
                                            <TableHead className={'font-semibold'}>Catatan</TableHead>
                                            <TableHead className={'font-semibold'}>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {jvs.data.map((o, i) => (
                                            <TableRow key={i}>
                                                <TableCell className='flex justify-center'>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button size='sm' variant='icon'>
                                                                <EllipsisIcon />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent>
                                                            {
                                                                !o.active ?
                                                                    <DropdownMenuItem onClick={() => router.visit(route('pasien.obat.index', { id: o.pasien_id }))}>
                                                                        <Tablets className='me-1 text-green-600' />Input Obat
                                                                    </DropdownMenuItem>
                                                                    :
                                                                    <Fragment>
                                                                        <DropdownMenuItem onClick={() => router.visit(route('visit.edit', o.id))}>
                                                                            <EditIcon className='me-1 text-amber-600' />Edit
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuItem onClick={() => askSelesaiVisit(o)}>
                                                                            <Check className='me-1 text-mkg-600' />Sudah Selesai
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuItem onClick={() => askDelete(o)}>
                                                                            <Trash className='me-1 text-destructive' />Hapus
                                                                        </DropdownMenuItem>
                                                                    </Fragment>
                                                            }

                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                                <TableCell>{o.pasien.nama}</TableCell>
                                                <TableCell>{o.keluhan}</TableCell>
                                                <TableCell>{o.catatan}</TableCell>
                                                <TableCell>{o.active ? 'Aktif' : 'Selesai'}</TableCell>
                                            </TableRow>
                                        ))}
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
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
            <Dialog open={psDialog} onOpenChange={() => setPsDialog(false)}>
                <DialogContent>
                    <DialogTitle>Pilih Pasien</DialogTitle>
                    <Command>
                        <CommandInput placeholder="Pilih Pasien..." />
                        <CommandEmpty>Pasien tidak ditemukan.</CommandEmpty>
                        <CommandGroup>
                            <ScrollArea className="h-72">
                                {pasiens.map((pasien) => (
                                    <CommandItem
                                        key={pasien.id}
                                        value={pasien.nama}
                                        onSelect={() => {
                                            router.visit(route('visit.create', pasien.id))
                                            setPsDialog(false)
                                        }}
                                    >
                                        <User className="mr-2 h-4 w-4" />
                                        <span>{pasien.nama}</span>
                                    </CommandItem>
                                ))}
                            </ScrollArea>
                        </CommandGroup>
                    </Command>
                </DialogContent>
            </Dialog>
        </AppLayout>
    )
}
