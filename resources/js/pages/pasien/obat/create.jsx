import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Command, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Input } from '@/components/ui/input'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput, InputGroupText } from '@/components/ui/input-group'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import { Head, useForm } from '@inertiajs/react'
import { ChevronDown, XCircle, XIcon } from 'lucide-react'
import moment from 'moment'
import React, { useState } from 'react'

export default function PCreateObat({ pasien, obats }) {
    const [dtJadwal, setDtJadwal] = useState({
        waktu: '14:00',
        dosis: '1',
    })
    const bc = [
        {
            title: 'Pasien',
            href: route('pasien.index'),
        },
        {
            title: 'Obat Pasien',
            href: route('pasien.obat.index', pasien.id),
        },
        {
            title: 'Tambah Obat',
        },
    ]
    const { data, setData, processing, post, errors } = useForm({
        obat_id: '',
        qty_diberikan: '',
        satuan: 'Tablet',
        mulai_tanggal: moment().format('YYYY-MM-DD'),
        selesai_tanggal: moment().add(28, 'days').format('YYYY-MM-DD'),
        jadwal_obat: [],
    })
    const submitObatPasien = () => {
        post(route('pasien.obat.store', pasien.id))
    }
    const addWaktuDetail = () => {
        setData('jadwal_obat', [...data.jadwal_obat, { ...dtJadwal, satuan: data.satuan }])
        setDtJadwal({
            ...dtJadwal,
            waktu: moment(dtJadwal.waktu).add(6, 'hours').format('HH:mm'),
        })

    }
    const delWaktuDetail = i => {
        let arr = [...data.jadwal_obat]
        arr.splice(i, 1)
        setData('jadwal_obat', arr)
    }
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title={"Tambah Obat " + pasien.nama} />
            <div className='container w-screen md:w-auto my-4 lg:px-4 px-2'>
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        TAMBAH OBAT PASIEN
                    </h3>
                    <div className='grid gap-3'>
                        <Card>
                            <CardContent>
                                <CardTitle className={'text-lg'}>Form Obat</CardTitle>
                                <div className="grid gap-3">
                                    <div>
                                        <Label>Obat</Label>
                                        <Popover>
                                            <PopoverTrigger asChild>
                                                <Button variant={'outline'} className='w-full'>{data.obat_id ? obats.find((obat) => obat.id === data.obat_id).nama_obat : 'Pilih Obat'} <ChevronDown className="ml-2 h-4 w-4 shrink-0 opacity-50" /></Button>
                                            </PopoverTrigger>
                                            <PopoverContent>
                                                <Command>
                                                    <CommandInput placeholder="Pilih Obat" />
                                                    <CommandList>
                                                        {
                                                            obats.map((obat) => (
                                                                <CommandItem
                                                                    key={obat.id}
                                                                    value={obat.id}
                                                                    onSelect={() => setData('obat_id', obat.id)}
                                                                >
                                                                    {obat.nama_obat}
                                                                </CommandItem>
                                                            ))
                                                        }
                                                    </CommandList>
                                                </Command>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                    <div>
                                        <Label>Mulai Tanggal</Label>
                                        <Input type={'date'} value={data.mulai_tanggal} onChange={(e) => setData({ ...data, mulai_tanggal: e.target.value, selesai_tanggal: moment(e.target.value).add(28, 'days').format('YYYY-MM-DD') })} />
                                    </div>
                                    <div>
                                        <Label>Selesai Tanggal</Label>
                                        <Input type={'date'} value={data.selesai_tanggal} onChange={(e) => setData('selesai_tanggal', e.target.value)} />
                                    </div>
                                    <div>
                                        <Label>QTY Diberikan</Label>
                                        <Input type={'number'} value={data.qty_diberikan} onChange={(e) => setData('qty_diberikan', e.target.value)} />
                                    </div>
                                    <div>
                                        <Label>Satuan</Label>
                                        <Input type={'text'} value={data.satuan} onChange={(e) => setData('satuan', e.target.value)} />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent>
                                <CardTitle className={'text-lg'}>Jadwal Obat</CardTitle>
                                <div className="flex gap-3 ">
                                    <div>
                                        <Label>Waktu</Label>
                                        <Input type='time' value={dtJadwal.waktu} onChange={(e) => setDtJadwal({ ...dtJadwal, waktu: e.target.value })} />
                                    </div>
                                    <div>
                                        <Label>Dosis</Label>
                                        <Input type='number' defaultValue={dtJadwal.dosis} onChange={(e) => setDtJadwal({ ...dtJadwal, dosis: parseFloat(e.target.value) })} />
                                        <span className='text-xs text-gray-500'>Jika 1/2 tablet tuliskan dalam desimal (0.5)</span>
                                    </div>
                                    <div>
                                        <Label>Satuan</Label>
                                        <Input readOnly type={'text'} value={data.satuan} onChange={(e) => setData('satuan', e.target.value)} />
                                    </div>
                                    <div className='flex items-center justify-center'>
                                        <Button onClick={() => addWaktuDetail()}>Tambahkan</Button>
                                    </div>
                                </div>
                                <div className="mt-3">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead></TableHead>
                                                <TableHead>Waktu</TableHead>
                                                <TableHead>Dosis</TableHead>
                                                <TableHead>Satuan</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {
                                                data.jadwal_obat.map((d, i) => (
                                                    <TableRow key={i}>
                                                        <TableCell className='flex items-center justify-center'><Button onClick={() => delWaktuDetail(i)} variant={'icon'}>
                                                            <XCircle color='red' />
                                                        </Button></TableCell>
                                                        <TableCell>{d.waktu}<InputError message={errors[`jadwal_obat.${i}.waktu`]} /></TableCell>
                                                        <TableCell>{d.dosis}<InputError message={errors[`jadwal_obat.${i}.dosis`]} /></TableCell>
                                                        <TableCell>{d.satuan}</TableCell>
                                                    </TableRow>
                                                ))
                                            }
                                        </TableBody>
                                    </Table>
                                    <InputError message={errors.waktu} />
                                    <InputError message={errors.dosis} />
                                    <p className={'text-sm text-destructive'}>Pastikan waktu dan dosis di tabel tidak kosong saat data disimpan!</p>
                                </div>
                            </CardContent>
                        </Card>
                        <Button onClick={submitObatPasien} disabled={processing} >Simpan</Button>
                    </div>
                </div>
            </div>

        </AppLayout>
    )
}
