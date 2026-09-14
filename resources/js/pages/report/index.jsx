import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import { Head } from '@inertiajs/react'
import { Users } from 'lucide-react'
import React from 'react'

export default function ReportIndex() {
    const bc = [
        {
            title: "Report",
        }
    ]
    return (
        <AppLayout breadcrumbs={bc}>
            <Head title="Report" />
            <div className="container my-4">
                <div className="mx-auto md:mx-2 lg:max-w-7xl">
                    <h3 className="text-primary mb-3 font-bold">
                        Report
                    </h3>
                    <Card>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Users size={30} />
                                <div className='grid'>
                                    <div className="text-primary text-lg">Data Pasien</div>
                                    <a href={route('report.data-pasien')} className="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-[color,box-shadow] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 px-2 py-1">Download Data Pasien</a>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    )
}
