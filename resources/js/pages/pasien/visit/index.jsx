import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'
import { Head } from '@inertiajs/react'
import moment from 'moment'
import React, { useState } from 'react'
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { EditIcon, TrashIcon } from 'lucide-react'

export default function JadwalVisitPasien({ jvs }) {
    const [tanggal, setTanggal] = useState(moment().format('YYYY-MM-DD'))
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
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title='Jadwal Visit' />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary font-bold">
                        JADWAL BEROBAT
                    </h3>
                    <div className="my-3">
                        <Label>Tanggal</Label>
                        <Input type='date' defaultValue={tanggal} onChange={e => setTanggal(e.target.value)} />
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
                                            <TableHead className={'font-semibold'}>Sudah Dilayani</TableHead>
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
                                                            <DropdownMenuItem>
                                                                <EditIcon className='me-1 text-amber-600' />Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem>
                                                                <TrashIcon className='me-1 text-red-600' />Delete
                                                            </DropdownMenuItem>

                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                                <TableCell>{o.pasien.name}</TableCell>
                                                <TableCell>{o.keluhan}</TableCell>
                                                <TableCell>{o.catatan}</TableCell>
                                                <TableCell>{o.active ? 'Belum' : 'Sudah'}</TableCell>
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
        </AppLayout>
    )
}
