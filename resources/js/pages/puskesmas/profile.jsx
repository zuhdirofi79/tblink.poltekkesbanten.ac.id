import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'
import { useForm } from '@inertiajs/react'
import React from 'react'

export default function PuskesmasProfile({ puskesmas }) {
    const bc = [
        {
            title: 'Puskesmas',
        }
    ]
    const { put, data, setData, errors, processing } = useForm({ ...puskesmas })
    const submitForm = e => {
        e.preventDefault()
        put(route('master.puskesmas.update', puskesmas.id))
    }
    return (
        <AppLayout breadcrumbs={bc}>
            <div className="container my-4">
                <div className="mx-auto md:max-w-4xl">
                    <Card>
                        <CardContent>
                            <CardTitle className={'font-semibold text-lg'}>Profil Puskesmas</CardTitle>
                            <form id='fProf' onSubmit={submitForm} className='grid gap-2'>
                                <div>
                                    <Label>Nama Puskesmas</Label>
                                    <Input type='text' defaultValue={data.nama_puskesmas} onChange={(e) => setData('nama_puskesmas', e.target.value)} />
                                    <InputError message={errors.nama_puskesmas} />
                                </div>
                                <div>
                                    <Label>Kode Puskesmas</Label>
                                    <Input type='text' defaultValue={data.kode_puskesmas} onChange={(e) => setData('kode_puskesmas', e.target.value)} />
                                    <InputError message={errors.kode_puskesmas} />
                                </div>
                                <div>
                                    <Label>Kecamatan</Label>
                                    <Input type='text' defaultValue={data.kecamatan} onChange={(e) => setData('kecamatan', e.target.value)} />
                                    <InputError message={errors.kecamatan} />
                                </div>
                                <div>
                                    <Label>Kota/Kab</Label>
                                    <Input type='text' defaultValue={data.kotakab} onChange={(e) => setData('kotakab', e.target.value)} />
                                    <InputError message={errors.kotakab} />
                                </div>
                                <div>
                                    <Label>Provinsi</Label>
                                    <Input type='text' defaultValue={data.provinsi} onChange={(e) => setData('provinsi', e.target.value)} />
                                    <InputError message={errors.provinsi} />
                                </div>
                                <div>
                                    <Label>Alamat</Label>
                                    <Input type='textarea' defaultValue={data.alamat} onChange={(e) => setData('alamat', e.target.value)} />
                                    <InputError message={errors.alamat} />
                                </div>
                                <Button type='submit' disabled={processing}>Simpan</Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    )
}
