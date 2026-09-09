import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '../components/ui/button';
import { useEffect } from 'react';
import { toast } from 'sonner';
import axios from 'axios'
import { can } from '@/components/helper';
import DashboardPasien from './dashboards/pasien';
import DashboardAdmin from './dashboards/admin';
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Users } from 'lucide-react';
import DsPendamping from './dashboards/pendamping';
import DsSa from './dashboards/superadmin';

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    const user = usePage().props.auth.user;

    const testPost = () => {
        axios.post(route('test-post'), {
            text: 'Message from server'
        })
            .then(() => {
                console.log('message sent!');
                toast.success('Message Sent!')
            })
    }

    return (<AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Dashboard" />
        <div className='w-full p-4'>
            {
                can(['Pendamping'], user) ?
                    <DsPendamping /> : ''
            }
            {
                can(['Admin'], user) ?
                    <DashboardAdmin />
                    : ''
            }
            {
                can(['Pasien', 'Pendamping'], user) ?
                    <DashboardPasien />
                    : ''
            }
            {
                can(['Superadmin'], user) ?
                    <DsSa /> : ''
            }
            {
                can(['User Management'], user) ?
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant='icon'>
                                <Users />
                            </EmptyMedia>
                            <EmptyTitle>User Management</EmptyTitle>
                            <EmptyDescription>
                                Klik tombol dibawah untuk mengelola user
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <div className="flex gap-2">
                                <Button onClick={() => router.visit(route('users.index'))}>Manage User</Button>
                            </div>
                        </EmptyContent>
                    </Empty>
                    : ''
            }

        </div>
    </AppLayout>);
}

