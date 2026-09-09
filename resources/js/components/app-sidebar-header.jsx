import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useEffect, useState } from 'react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from './ui/tooltip';

export function AppSidebarHeader({ breadcrumbs = [] }) {
    const [clicked, setClicked] = useState(true)
    useEffect(() => {
        const cl = localStorage.getItem('sidebar-ever-clicked') ?? 0;
        if (cl === 0) {
            setClicked(false)
        }
    }, [])

    const clickSidebar = () => {
        localStorage.setItem('sidebar-ever-clicked', 1)
        setClicked(true)
    }

    return (<header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
        <div className="flex items-center gap-2">
            <TooltipProvider>
                <Tooltip open={!clicked}>
                    <TooltipTrigger asChild>
                        <SidebarTrigger className={`-ml-1 ${!clicked ? 'text-red-600 animate-pulse' : ''} `} onClick={clickSidebar} />
                    </TooltipTrigger>
                    <TooltipContent>Klik disini untuk menampilkan/menyembunyikan menu</TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <Breadcrumbs breadcrumbs={breadcrumbs} />
        </div>
    </header>);
}

