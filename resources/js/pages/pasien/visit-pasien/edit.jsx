import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'
import { Head, useForm } from '@inertiajs/react'
import moment from 'moment'
import React from 'react'

export default function EditVisitPasien({ jadwalVisit }) {
    const bc = [
        {
            title: 'Jdawal Berobat',
            href: route('visit.create', jadwalVisit.pasien_id),
        },
        {
            title: 'Edit Jadwal Berobat Pasien',
            href: route('visit.edit', jadwalVisit.id),
        },
    ]
    const { errors, data, setData, put, processing } = useForm({
        pasien_id: jadwalVisit.pasien_id,
        tgl: moment(jadwalVisit.tgl_visit).format('YYYY-MM-DD'),
        jam: moment(jadwalVisit.tgl_visit).format('HH:mm'),
        keluhan: jadwalVisit.keluhan,
        catatan: jadwalVisit.catatan
    })
    const submitEditVisit = () => {
        put(route('visit.update', jadwalVisit.id))
    }
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title="Edit Visit Pasien" />
            <div className="container w-screen md:w-auto my-4 lg:px-4 px-2">

                <div className="mx-auto my-4 max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        EDIT JADWAL BEROBAT
                    </h3>
                    <div className='grid gap-3'>

                        <Card>
                            <CardContent>
                                <div className="mt-3">
                                    <div className="mb-3">
                                        <Label>Tgl*</Label>
                                        <Input defaultValue={data.tgl} onChange={(e) => setData('tgl', e.target.value)} type="date" required />
                                        <InputError message={errors.tgl} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Jam*</Label>
                                        <Input defaultValue={data.jam} onChange={(e) => setData('jam', e.target.value)} type="time" required />
                                        <InputError message={errors.jam} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Keluhan</Label>
                                        <Input defaultValue={data.keluhan} onChange={(e) => setData('keluhan', e.target.value)} type="text" required />
                                        <InputError message={errors.keluhan} className="mt-2" />
                                    </div>
                                    <div className="mb-3">
                                        <Label>Catatan</Label>
                                        <Input defaultValue={data.catatan} onChange={(e) => setData('catatan', e.target.value)} type="text" required />
                                        <InputError message={errors.catatan} className="mt-2" />
                                    </div>
                                    <div className='grid'>
                                        <Button className={'w-full'} onClick={submitEditVisit} disabled={processing}>
                                            Simpan
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}


