import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeClosed, LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { useState } from 'react';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip';

export default function Login({ status, canResetPassword }) {
    const [visible, setVisible] = useState(false)
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (<AuthLayout title="Control" description="Isikan Username dan Password">
        <Head title="Log in" />

        <form className="flex flex-col gap-6" onSubmit={submit}>
            <div className="grid gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="username">Username</Label>
                    <Input id="username" type="text" required autoFocus tabIndex={1} autoComplete="username" value={data.username} onChange={(e) => setData('username', e.target.value)} placeholder="Username..." />
                    <InputError message={errors.username} />
                </div>

                <div className="grid gap-2">
                    <div className="flex items-center">
                        <Label htmlFor="password">Password</Label>
                        {canResetPassword && (<TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                            Forgot password?
                        </TextLink>)}
                    </div>
                    <InputGroup>
                        <InputGroupInput id="password" type={visible ? 'text' : 'password'} required tabIndex={2} autoComplete="current-password" value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder="Password" />
                        <InputGroupAddon align='end' className='pr-2'>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <InputGroupButton onClick={() => setVisible(!visible)} size='icon-xs'>
                                        {
                                            visible ? <Eye className='text-primary' /> : <EyeClosed />
                                        }
                                    </InputGroupButton>
                                </TooltipTrigger>
                                <TooltipContent>
                                    Show/Hide Password
                                </TooltipContent>
                            </Tooltip>
                        </InputGroupAddon>
                    </InputGroup>
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" checked={data.remember} onClick={() => setData('remember', !data.remember)} tabIndex={3} />
                    <Label htmlFor="remember">Remember me</Label>
                </div>

                <Button type="submit" className="mt-4 w-full" tabIndex={4} disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Log in
                </Button>
            </div>

        </form>

        {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
    </AuthLayout>);
}

