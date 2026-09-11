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

export default function EditPasien({ pasien, userPasien, userPendamping, canSelectPuskesmas = false, puskesmasOptions = [], tbs = [] }) {
    const { setData, put, processing, errors, data } = useForm({
        ...pasien,
        no_hp: userPasien.no_hp,
        email: userPasien.email,
        nama_pendamping: userPendamping ? userPendamping.name : '',
        no_hp_pendamping: userPendamping ? userPendamping.no_hp : '',
        email_pendamping: userPendamping ? userPendamping.email : '',
        puskesmas_id: pasien.puskesmas_id ? String(pasien.puskesmas_id) : '',
    })
    const breadcrumb = [
        {
            href: route('pasien.index'),
            title: 'Pasien',
        },
        {
            title: 'Edit Pasien',
        },
    ]
    const submitPasien = () => {
        put(route('pasien.update', pasien.id))
    }
    return (
        <AppLayout breadcrumbs={breadcrumb} >
            <div className='container w-screen md:w-auto my-4 lg:px-4 px-2'>
                <Head title='Edit Pasien' />
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        EDIT PASIEN
                    </h3>
                    <div className='grid gap-3'>

                        <Card>
                            <CardContent>
                                <CardTitle className="text-xl">Form Pasien</CardTitle>
                                <div className="mt-3">
                                    {canSelectPuskesmas ? (
                                        <div className="mb-3">
                                            <Label>Puskesmas*</Label>
                                            <Select defaultValue={data.puskesmas_id ? String(data.puskesmas_id) : undefined} onValueChange={e => setData('puskesmas_id', e)} required>
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
                                        <Input defaultValue={data.nama} onChange={(e) => setData('nama', e.target.value)} type="text" required />
                                        <InputError message={errors.nama} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Email</Label>
                                        <Input defaultValue={data.email} type='email' onChange={(e) => setData('email', e.target.value)} />
                                        <InputError message={errors.email} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>NIK*</Label>
                                        <Input defaultValue={data.nik} onChange={(e) => setData('nik', e.target.value)} type="text" required />
                                        <InputError message={errors.nik} className="mt-2" />
                                    </div>

                                    <div className="mb-3">
                                        <Label>No HP*</Label>
                                        <Input defaultValue={data.no_hp} onChange={(e) => setData('no_hp', e.target.value)} type="text" required />
                                        <InputError message={errors.no_hp} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Jenis Kelamin*</Label>
                                        <Select defaultValue={data.jenis_kelamin} onValueChange={e => setData('jenis_kelamin', e)} required>
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
                                        <Input defaultValue={data.tgl_lahir} type='date' onChange={(e) => setData('tgl_lahir', e.target.value)} required />
                                        <InputError message={errors.tgl_lahir} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Tgl Diagnosa*</Label>
                                        <Input type='date' onChange={(e) => setData({ ...data, tgl_diagnosa: e.target.value, mulai_pengobatan: e.target.value })} required />
                                        <InputError message={errors.tgl_diagnosa} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Type TB*</Label>
                                        <Select defaultValue={data.tipe_tb} onValueChange={e => setData('tipe_tb', e)} required>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih Tipe TB" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(tbs.length ? tbs : ['TB Paru', 'TB Kelenjar', 'TB Selaput Otak', 'TB Usus', 'TB Ginjal']).map((item, key) => <SelectItem key={key} value={item}>{item}</SelectItem>)}
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
                                                <RadioGroupItem checked={data.jenjang_terapi == 3} value="3" id="3bln" />
                                                <Label htmlFor="3bln">3 Bulan</Label>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <RadioGroupItem checked={data.jenjang_terapi == 6} value="6" id="6bln" />
                                                <Label htmlFor="6bln">6 Bulan</Label>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <RadioGroupItem checked={data.jenjang_terapi == 12} value="12" id="1thn" />
                                                <Label htmlFor="1thn">1 Tahun</Label>
                                            </div>
                                        </RadioGroup>
                                        <InputError message={errors.jenjang_terapi} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Selesai Pengobatan</Label>
                                        <Input readOnly type='date' value={data.selesai_pengobatan} />
                                        <InputError message={errors.selesai_pengobatan} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Alamat</Label>
                                        <Input defaultValue={data.alamat} type='textarea' onChange={e => setData('alamat', e.target.value)} />
                                        <InputError message={errors.alamat} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Status Pasien*</Label>
                                        <Select defaultValue={data.status} onValueChange={e => setData('status', e)} required>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih Status Pasien" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {['Aktif', 'Non Aktif'].map((item, key) => <SelectItem key={key} value={item}>{item}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} className="mt-2" />
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
                                        <Input defaultValue={data.nama_pendamping} onChange={(e) => setData('nama_pendamping', e.target.value)} type="text" />
                                        <InputError message={errors.nama_pendamping} className="mt-2" />
                                    </div>
                                    <div>
                                        <Label>No HP Pendamping</Label>
                                        <Input defaultValue={data.no_hp_pendamping} onChange={(e) => setData('no_hp_pendamping', e.target.value)} type="text" />
                                        <InputError message={errors.no_hp_pendamping} className="mt-2" />
                                    </div>
                                    <div>
                                        <Label>Hubungan dengan Pasien</Label>
                                        <Input defaultValue={data.hub_dg_pasien} onChange={(e) => setData('hub_dg_pasien', e.target.value)} type="text" />
                                        <InputError message={errors.hub_dg_pasien} className="mt-2" />
                                    </div>
                                    <div>
                                        <Label>Email Pendamping</Label>
                                        <Input defaultValue={data.email_pendamping} onChange={(e) => setData('email_pendamping', e.target.value)} type="text" />
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
