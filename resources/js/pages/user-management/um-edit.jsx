import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import React from 'react'

export default function UmEdit({ user, permissions }) {
    const bc = [
        {
            title: 'User Management',
            href: route('users.index'),
        },
        {
            title: 'Edit ' + user.name,
            href: route('users.edit', user.id),
        },
    ];
    const { data, put, setData, processing, errors } = useForm({ ...user });
    const submitUpd = (e) => {
        e.preventDefault();
        put(route('users.update', user.id));
    };
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title={`Edit ${user.name}`} />
            <div className="container my-4">
                <Card className="mx-auto max-w-3xl p-6">
                    <form id="fedit" onSubmit={submitUpd}>
                        <div className="grid gap-3">
                            <div>
                                <Label>Nama</Label>
                                <Input defaultValue={data.name} onChange={(e) => setData('name', e.target.value)} />
                                <InputError message={errors.name} />
                            </div>
                            <div>
                                <Label>Username</Label>
                                <Input value={data.username} readOnly />
                                <InputError message={errors.email} />
                            </div>
                            <div>
                                <Label>Email</Label>
                                <Input defaultValue={data.email} type="email" onChange={(e) => setData('email', e.target.value)} />
                                <InputError message={errors.email} />
                            </div>
                            <div className="text-sm">Jika ingin merubah password silahkan isi dibawah</div>
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
                                                checked={data.perms?.includes(p.name)}
                                                value={p.name}
                                                onClick={(e) =>
                                                    e.target.ariaChecked === 'true'
                                                        ? setData(
                                                            'perms',
                                                            data.perms?.filter((x) => x !== p.name),
                                                        )
                                                        : setData('perms', [...data.perms, p.name])
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
                            <Button type="submit" form="fedit" disabled={processing}>
                                Submit
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    )
}
