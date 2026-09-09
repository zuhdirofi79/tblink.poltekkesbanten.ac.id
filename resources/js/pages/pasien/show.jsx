import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import { Head } from '@inertiajs/react'
import moment from 'moment'
import React from 'react'

export default function ShowPasien({ pasien }) {
    const bc = [
        {
            title: 'Pasien',
            href: route('pasien.index'),
        },
        {
            title: 'Detail Pasien',
            href: route('pasien.show', pasien.id),
        }
    ]
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title={pasien.nama} />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary font-bold">
                        Detail {pasien.nama}
                    </h3>
                    <div className="grid gap-3">
                        <Card>
                            <CardContent>
                                <CardTitle className={'text-primary mb-3'}>Biodata</CardTitle>
                                <div className="grid gap-3 md:grid-cols-2">
                                    <dl>
                                        <dt className='text-xs'>Nama</dt>
                                        <dd className='text-sm font-semibold'>{pasien.nama}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Puskesmas</dt>
                                        <dd className='text-sm font-semibold'>{pasien.puskesmas.nama_puskesmas}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Username</dt>
                                        <dd className='text-sm font-semibold'>{pasien.user.username}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>No HP</dt>
                                        <dd className='text-sm font-semibold'>{pasien.user.no_hp}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Jenis Kelamin</dt>
                                        <dd className='text-sm font-semibold'>{pasien.jenis_kelamin}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Tgl Lahir</dt>
                                        <dd className='text-sm font-semibold'>{moment(pasien.tgl_lahir).format('DD/MMM/YYYY')}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Alamat</dt>
                                        <dd className='text-sm font-semibold'>{pasien.alamat}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Tgl Diagnosa</dt>
                                        <dd className='text-sm font-semibold'>{moment(pasien.tgl_diagnosa).format('DD/MMM/YYYY')}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Mulai Pengobatan</dt>
                                        <dd className='text-sm font-semibold'>{moment(pasien.mulai_pengobatan).format('DD/MMM/YYYY')}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Selesai Pengobatan</dt>
                                        <dd className='text-sm font-semibold'>{moment(pasien.selesai_pengobatan).format('DD/MMM/YYYY')}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Jenjang Terapi</dt>
                                        <dd className='text-sm font-semibold'>{pasien.jenjang_terapi} Bulan</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Tipe TB</dt>
                                        <dd className='text-sm font-semibold'>{pasien.tipe_tb}</dd>
                                    </dl>
                                    <dl>
                                        <dt className='text-xs'>Status</dt>
                                        <dd className='text-sm font-semibold'>{pasien.status}</dd>
                                    </dl>
                                </div>
                            </CardContent>
                        </Card>
                        {
                            pasien.pendamping ?
                                <Card>
                                    <CardContent>
                                        <CardTitle className={'text-primary mb-3'}>Pendamping</CardTitle>
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <dl>
                                                <dt className='text-xs'>Pendamping</dt>
                                                <dd className='text-sm font-semibold'>{pasien.pendamping.name}</dd>
                                            </dl>
                                            <dl>
                                                <dt className='text-xs'>No HP</dt>
                                                <dd className='text-sm font-semibold'>{pasien.pendamping.no_hp}</dd>
                                            </dl>
                                            <dl>
                                                <dt className='text-xs'>Username</dt>
                                                <dd className='text-sm font-semibold'>{pasien.pendamping.username}</dd>
                                            </dl>
                                            <dl>
                                                <dt className='text-xs'>Hubungan Dengan Pasien</dt>
                                                <dd className='text-sm font-semibold'>{pasien.hub_dg_pasien}</dd>
                                            </dl>
                                        </div>
                                    </CardContent>
                                </Card>
                                : ''
                        }
                        <Card>
                            <CardContent>
                                <CardTitle className={'text-primary mb-3'}>Obat</CardTitle>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className={'font-semibold'}>Nama Obat</TableHead>
                                            <TableHead className={'font-semibold'}>Deskripsi</TableHead>
                                            <TableHead className={'font-semibold'}>Sisa</TableHead>
                                            <TableHead className={'font-semibold'}>Dosis</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {
                                            pasien.obats.map((obat) => <TableRow key={obat.id}>
                                                <TableCell>{obat.obat.nama_obat}</TableCell>
                                                <TableCell>{obat.obat.deskripsi}</TableCell>
                                                <TableCell>{obat.stock}</TableCell>
                                                {
                                                    obat.jadwal.details.length > 0 ?
                                                        <TableCell>{obat.jadwal.details.length} x {obat.jadwal.details[0].dosis} {obat.jadwal.details[0].satuan} Per Hari</TableCell>
                                                        : <TableCell>-</TableCell>
                                                }
                                            </TableRow>)
                                        }
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
