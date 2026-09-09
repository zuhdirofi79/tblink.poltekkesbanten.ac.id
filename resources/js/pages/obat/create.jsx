import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'
import { Head, useForm } from '@inertiajs/react'
import React from 'react'

export default function CreateObat() {
    const { post, processing, data, setData, errors } = useForm({
        nama_obat: '',
        deskripsi: '',
    })
    const bc = [
        {
            href: route('obat.index'),
            title: 'Obat',
        },
        {
            title: 'Tambah Obat',
        },
    ]
    const simpanObat = () => {
        post(route('obat.store'));
    }
    return (
        <AppLayout breadcrumbs={bc} >
            <div className='container w-screen md:w-auto my-4 lg:px-4 px-2'>
                <Head title='Tambah Obat' />
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        TAMBAH OBAT
                    </h3>
                    <div className='grid gap-3'>
                        <Card>
                            <CardContent>
                                <CardTitle className="text-xl">Form Obat</CardTitle>
                                <div className="grid gap-3">
                                    <div>
                                        <Label>Nama Obat</Label>
                                        <Input defaultValue={data.nama_obat} onChange={(e) => setData('nama_obat', e.target.value)} />
                                        <InputError message={errors.nama_obat} />
                                    </div>
                                    <div>
                                        <Label>Deskripsi</Label>
                                        <Input defaultValue={data.deskripsi} onChange={(e) => setData('deskripsi', e.target.value)} />
                                        <InputError message={errors.deskripsi} />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Button disabled={processing} onClick={simpanObat}>Simpan</Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
