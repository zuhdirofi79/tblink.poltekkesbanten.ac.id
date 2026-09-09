import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Toaster } from '@/components/ui/sonner';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';

export default function ({ children, breadcrumbs, ...props }) {
    const flash = usePage().props.flash;

    return (<AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        {flash.error ? (
            <div className="max-w-7xl px-4">
                <Alert className="mx-auto my-4" variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle className='text-destructive font-semibold'>Error!</AlertTitle>
                    <AlertDescription >
                        <p className='text-destructive'>{flash.error}</p>
                    </AlertDescription>
                </Alert>
            </div>
        ) : (
            ''
        )}
        {flash.success ? (
            <div className="container px-4">
                <div className="mx-auto max-w-7xl">
                    <Alert className="border-primary text-primary my-4">
                        <CheckCircle className="h-4 w-4" />
                        <AlertTitle>Success!</AlertTitle>
                        <AlertDescription className="text-primary">{flash.success}</AlertDescription>
                    </Alert>
                </div>
            </div>
        ) : (
            ''
        )}
        {children}
        <Toaster />
    </AppLayoutTemplate>);
}

