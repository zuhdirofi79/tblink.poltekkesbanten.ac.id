import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link, router, usePage } from '@inertiajs/react';
import { BellIcon, BookOpen, Boxes, Calendar, Folder, Hospital, LayoutGrid, MessageCircleMoreIcon, Receipt, TabletsIcon, User, Users } from 'lucide-react';
import AppLogo from './app-logo';
import { useEffect, useState } from 'react';
import { can } from './helper';
import { useEcho } from '@laravel/echo-react';
import { toast } from 'sonner';

const mainNavItems = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        route: 'dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Puskesmas',
        href: route('master.puskesmas'),
        route: 'master.puskesmas',
        icon: Hospital,
        permissions: ['Admin']
    },
    {
        title: 'Type TB',
        href: route('typetb.index'),
        route: 'typetb.index',
        icon: Boxes,
        permissions: ['Superadmin']
    },
    {
        title: 'Identitas Pasien',
        href: route('pasien.data-pasien'),
        route: 'pasien.data-pasien',
        icon: User,
        permissions: ['Pasien', 'Pendamping']
    },
    {
        title: 'Jadwal Berobat',
        href: route('pasien.visit-pasien.index'),
        route: 'pasien.visit-pasien.index',
        icon: Calendar,
        permissions: ['Pasien', 'Pendamping']
    },
    {
        title: 'Notifikasi',
        href: route('pasien.notifikasi'),
        route: 'pasien.notifikasi',
        icon: BellIcon,
        permissions: ['Pasien', 'Pendamping']
    },
    {
        title: 'Notifikasi',
        href: route('notifikasi-admin'),
        route: 'notifikasi-admin',
        icon: BellIcon,
        permissions: ['Admin']
    },
    {
        title: 'Live Chat',
        href: route('live-chat.sa'),
        route: 'live-chat.sa',
        icon: MessageCircleMoreIcon,
        permissions: ['Superadmin']
    },
    {
        title: 'Live Chat',
        href: route('live-chat.admin'),
        route: 'live-chat.admin',
        icon: MessageCircleMoreIcon,
        permissions: ['Admin']
    },
    {
        title: 'Live Chat',
        href: route('live-chat.index'),
        route: 'live-chat.index',
        icon: MessageCircleMoreIcon,
        permissions: ['Pasien', 'Pendamping']
    },

    {
        title: 'Jadwal Berobat',
        url: route('visit.index'),
        icon: Calendar,
        route: 'visit.index',
        permissions: ['Admin'],
    },
    {
        title: 'Pasien',
        url: '/pasien',
        route: 'pasien.*',
        icon: Users,
        permissions: ['Admin', 'Superadmin'],
        items: [
            {
                title: 'Data Pasien',
                url: '/pasien',
                route: 'pasien.index',
            },
        ]
    },
    {
        title: 'Obat',
        url: '/obat',
        route: 'obat.index',
        icon: TabletsIcon,
        permissions: ['Admin'],
        items: [
            {
                title: 'Data Obat',
                url: '/obat',
                route: 'obat.index',
            },
        ]
    },
    {
        title: 'Report',
        url: route('report.index'),
        icon: Receipt,
        route: 'report.index',
        permissions: ['Admin', 'Superadmin'],
    },
];

const footerNavItems = [
    {
        title: 'User Management',
        href: route('users.index'),
        route: 'users.*',
        icon: Users,
    },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits',
    //     icon: BookOpen,
    // },
];

export function AppSidebar() {
    const user = usePage().props.auth.user;
    const [mainMenus, setMainMenus] = useState([]);
    useEcho('chat-notification.' + (user?.id ?? 'guest'), 'ChatNotifEvent', e => {
        if (route().current('live-chat.room', e.sender_id)) return
        toast(e.title, {
            description: e.body,
            action: {
                label: 'Lihat',
                onClick: () => {
                    router.visit(route('live-chat.room', e.sender_id))
                }
            }
        });
    })

    useEffect(() => {
        const mn = [];
        if (user) {
            mainNavItems.map((v, i) => {
                if (v.permissions) {
                    if (can(v.permissions, user)) {
                        mn.push(v);
                    }
                } else {
                    mn.push(v);
                }
                if (i + 1 === mainNavItems.length) {
                    setMainMenus(mn);
                }
            });
        }
    }, [user]);
    return (<Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild>
                        <Link href="/dashboard" prefetch>
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain items={mainMenus} />
        </SidebarContent>
        <SidebarFooter>
            {
                can(['User Management'], user) ?
                    <NavFooter items={footerNavItems} className="mt-auto" />
                    : ''
            }
            <NavUser />
        </SidebarFooter>
    </Sidebar>);
}

