import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AppLayout from '@/layouts/app-layout'
import { Head, useForm } from '@inertiajs/react'
import React from 'react'

const bc = [
    {
        title: 'User Management',
        href: route('users.index'),
    },
    {
        title: 'Tambah User',
        href: route('users.create'),
    },
]
export default function UmCreate({ permissions }) {
    const { setData, data, errors, processing, post } = useForm({
        username: '',
        name: '',
        password: '',
        password_confirmation: '',
        email: '',
        permissions: [],
    });
    const submitAdd = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title='Tambah User' />
            <div className="container my-4 px-2">
                <Card className='mx-auto max-w-3xl p-4'>
                    <div className="text-primary text-xl font-bold">TAMBAH USER</div>
                    <form id="fAdd" onSubmit={submitAdd}>
                        <div className="grid gap-3">
                            <div>
                                <Label>Nama</Label>
                                <Input defaultValue={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>
                            <div>
                                <Label>Username</Label>
                                <Input defaultValue={data.username} onChange={(e) => setData('username', e.target.value)} />
                                <InputError message={errors.username} />
                            </div>
                            <div>
                                <Label>Email</Label>
                                <Input defaultValue={data.email} onChange={(e) => setData('email', e.target.value)} />
                                <InputError message={errors.email} />
                            </div>
                            <div>
                                <Label>Password</Label>
                                <Input defaultValue={data.password} type="password" onChange={(e) => setData('password', e.target.value)} />
                                <InputError message={errors.password} />
                            </div>
                            <div>
                                <Label>Confirm Password</Label>
                                <Input
                                    defaultValue={data.password_confirmation}
                                    type="password"
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>
                            <div>
                                <Label>Role</Label>
                                <div className="grid gap-2 md:grid-cols-2 lg:grid-cols-4">
                                    {permissions.map((p, i) => (
                                        <div key={i} className="flex items-center space-x-2">
                                            <Checkbox
                                                className="cursor-pointer"
                                                id={`cb${i}`}
                                                value={p.id}
                                                onClick={(e) =>
                                                    e.target.ariaChecked === 'true'
                                                        ? setData(
                                                            'permissions',
                                                            [...data.permissions].filter((x) => x !== p.id),
                                                        )
                                                        : setData('permissions', [...data.permissions, p.id])
                                                }
                                            />
                                            <Label className="cursor-pointer" htmlFor={`cb${i}`}>
                                                {p.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                        <div className="mt-2 grid">
                            <Button type="submit" form="fAdd" disabled={processing}>
                                Submit
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    )
}
