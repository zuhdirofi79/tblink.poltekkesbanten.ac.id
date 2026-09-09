import { Head, useForm } from '@inertiajs/react'
import React from 'react'
import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'

export default function EditObat({ obat }) {
    const { put, processing, data, setData, errors } = useForm({ ...obat })
    const bc = [
        {
            href: route('obat.index'),
            title: 'Obat',
        },
        {
            title: 'Edit Obat',
        },
    ]
    const updateObat = () => {
        put(route('obat.update', obat.id));
    }
    return (
        <AppLayout breadcrumbs={bc} >
            <div className='container w-screen md:w-auto my-4 lg:px-4 px-2'>
                <Head title='Edit Obat' />
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        EDIT OBAT
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
                        <Button onClick={updateObat} disabled={processing}>Simpan</Button>

                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
