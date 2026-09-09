import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import AppLayout from '@/layouts/app-layout'
import { Head, useForm } from '@inertiajs/react'
import React from 'react'

export default function CreateTypeTb() {
    const bc = [
        {
            title: 'Type TB',
            url: route('typetb.index'),
        },
        {
            title: 'Tambah',
        },
    ]
    const { data, post, processing, errors, setData } = useForm({
        tipe_tb: ''
    })
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title='Tambah' />
            <div className='container w-screen md:w-auto my-4 lg:px-4 px-2'>
                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        TAMBAH TYPE TB
                    </h3>
                    <Input type='text' placeholder='Type...' value={data.tipe_tb} onChange={(e) => setData('tipe_tb', e.target.value)} />
                    <InputError message={errors.tipe_tb} />
                    <Button onClick={() => post(route('typetb.store'))} className={'my-2'} disabled={processing}>Simpan</Button>
                </div>
            </div>
        </AppLayout>
    )
}
