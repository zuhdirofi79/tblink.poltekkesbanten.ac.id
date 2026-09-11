import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import AppLayout from '@/layouts/app-layout'
import { Head, useForm } from '@inertiajs/react'
import moment from 'moment'
import React from 'react'

export default function PasienCreate({ tbs, canSelectPuskesmas = false, puskesmasOptions = [] }) {
    const { data, setData, processing, post, errors } = useForm({
        nama: '',
        nik: '',
        tgl_lahir: '',
        jenis_kelamin: '',
        alamat: '',
        no_hp: '',
        email: '',
        tipe_tb: '',
        jenjang_terapi: '',
        tgl_diagnosa: '',
        mulai_pengobatan: '',
        selesai_pengobatan: '',
        status: 'aktif',
        nama_pendamping: '',
        no_hp_pendamping: '',
        email_pendamping: '',
        puskesmas_id: '',
        username: '',
    })
    const breadcrumb = [
        {
            href: route('pasien.index'),
            title: 'Pasien',
        },
        {
            title: 'Tambah Pasien',
        },
    ]
    const submitPasien = () => {
        post(route('pasien.store'))
    }
    return (
        <AppLayout breadcrumbs={breadcrumb} >
            <div className='container w-screen md:w-auto my-4 lg:px-4 px-2'>
                <Head title='Tambah Pasien' />
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        TAMBAH PASIEN
                    </h3>
                    <div className='grid gap-3'>

                        <Card>
                            <CardContent>
                                <CardTitle className="text-xl">Form Pasien</CardTitle>
                                <div className="mt-3">
                                    {canSelectPuskesmas ? (
                                        <div className="mb-3">
                                            <Label>Puskesmas*</Label>
                                            <Select value={data.puskesmas_id ? String(data.puskesmas_id) : undefined} onValueChange={e => setData('puskesmas_id', e)} required>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Pilih Puskesmas" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {puskesmasOptions.map((item) => (
                                                        <SelectItem key={item.id} value={String(item.id)}>{item.nama_puskesmas}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.puskesmas_id} className="mt-2" />
                                        </div>
                                    ) : null}
                                    <div className="mb-3">
                                        <Label>Nama*</Label>
                                        <Input value={data.nama} onChange={(e) => {
                                            const nama = e.target.value
                                            const suggested = nama.replace(/\s+/g, '').toLowerCase().slice(0, 6)
                                            const previousSuggested = data.nama.replace(/\s+/g, '').toLowerCase().slice(0, 6)
                                            const next = { ...data, nama }
                                            if (!data.username || data.username === previousSuggested || data.username === `${previousSuggested}1`) {
                                                next.username = suggested ? `${suggested}1` : ''
                                            }
                                            setData(next)
                                        }} type="text" required />
                                        <InputError message={errors.nama} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Username</Label>
                                        <Input value={data.username} onChange={(e) => setData('username', e.target.value)} type="text" placeholder="Otomatis dari nama jika dikosongkan" />
                                        <InputError message={errors.username} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Email</Label>
                                        <Input type='email' onChange={(e) => setData('email', e.target.value)} />
                                        <InputError message={errors.email} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>NIK*</Label>
                                        <Input onChange={(e) => setData('nik', e.target.value)} type="text" required />
                                        <InputError message={errors.nik} className="mt-2" />
                                    </div>

                                    <div className="mb-3">
                                        <Label>No HP*</Label>
                                        <Input onChange={(e) => setData('no_hp', e.target.value)} type="text" required />
                                        <InputError message={errors.no_hp} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Jenis Kelamin*</Label>
                                        <Select onValueChange={e => setData('jenis_kelamin', e)} required>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih Jenis Kelamin" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Laki-laki">Laki-laki</SelectItem>
                                                <SelectItem value="Perempuan">Perempuan</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.jenis_kelamin} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Tgl Lahir*</Label>
                                        <Input type='date' onChange={(e) => setData('tgl_lahir', e.target.value)} required />
                                        <InputError message={errors.tgl_lahir} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Tgl Diagnosa*</Label>
                                        <Input type='date' onChange={(e) => setData({ ...data, tgl_diagnosa: e.target.value, mulai_pengobatan: e.target.value })} required />
                                        <InputError message={errors.tgl_diagnosa} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Type TB*</Label>
                                        <Select onValueChange={e => setData('tipe_tb', e)} required>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih Tipe TB" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tbs.map((item, key) => <SelectItem key={key} value={item}>{item}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.tipe_tb} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Mulai Pengobatan*</Label>
                                        <Input type='date' defaultValue={data.mulai_pengobatan} onChange={(e) => setData('mulai_pengobatan', e.target.value)} required />
                                        <InputError message={errors.mulai_pengobatan} className="mt-2" />
                                    </div>
                                    <div className='mb-3 grid gap-2'>
                                        <Label>Jenjang Terapi</Label>
                                        <RadioGroup defaultValue={data.jenjang_terapi} onValueChange={e => {
                                            const next = moment(data.mulai_pengobatan).add((28 * e), 'days').format('YYYY-MM-DD')
                                            setData({ ...data, jenjang_terapi: e, selesai_pengobatan: next })
                                        }}>
                                            <div className="flex items-center gap-3">
                                                <RadioGroupItem value="3" id="3bln" />
                                                <Label htmlFor="3bln">3 Bulan</Label>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <RadioGroupItem value="6" id="6bln" />
                                                <Label htmlFor="6bln">6 Bulan</Label>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <RadioGroupItem value="12" id="1thn" />
                                                <Label htmlFor="1thn">1 Tahun</Label>
                                            </div>
                                        </RadioGroup>
                                        <InputError message={errors.jenjang_terapi} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Selesai Pengobatan</Label>
                                        <Input type='date' value={data.selesai_pengobatan} />
                                        <InputError message={errors.selesai_pengobatan} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Alamat*</Label>
                                        <Input type='textarea' onChange={e => setData('alamat', e.target.value)} />
                                        <InputError message={errors.alamat} className="mt-2" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent>
                                <CardTitle className='text-xl'>Form Pendamping Pasien</CardTitle>
                                <div className="grid gap-3">
                                    <div>
                                        <Label>Nama Pendamping</Label>
                                        <Input onChange={(e) => setData('nama_pendamping', e.target.value)} type="text" />
                                        <InputError message={errors.nama_pendamping} className="mt-2" />
                                    </div>
                                    <div>
                                        <Label>No HP Pendamping</Label>
                                        <Input onChange={(e) => setData('no_hp_pendamping', e.target.value)} type="text" />
                                        <InputError message={errors.no_hp_pendamping} className="mt-2" />
                                    </div>
                                    <div>
                                        <Label>Hubungan dengan Pasien</Label>
                                        <Input onChange={(e) => setData('hub_dg_pasien', e.target.value)} type="text" />
                                        <InputError message={errors.hub_dg_pasien} className="mt-2" />
                                    </div>
                                    <div>
                                        <Label>Email Pendamping</Label>
                                        <Input onChange={(e) => setData('email_pendamping', e.target.value)} type="text" />
                                        <InputError message={errors.email_pendamping} className="mt-2" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Button onClick={submitPasien} disabled={processing}>Simpan</Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
