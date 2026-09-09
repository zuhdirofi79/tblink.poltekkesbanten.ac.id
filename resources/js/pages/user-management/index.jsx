import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from '@/components/ui/pagination';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { DotIcon, EditIcon, EllipsisIcon, EraserIcon, PointerIcon, TrashIcon } from 'lucide-react';
import Swal from 'sweetalert2'
import React, { useRef, useState } from 'react'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { toast } from 'sonner';

const breadcrumbs = [
    {
        title: 'User Management',
        href: route('users.index'),
    },
];
export default function index({ users }) {
    const [dialogOpen, setDialogOpen] = useState(false)
    const [user, setUser] = useState()
    const cari = (e) => {
        txtSearch.current.value = e
        router.visit('', {
            data: {
                q: e,
            },
            preserveState: true,
            preserveScroll: true,
        });
    }
    const delUser = u => {
        setUser(u)
        setTimeout(() => setDialogOpen(true))
    }
    const closeDialog = () => {
        setDialogOpen(false)
        setTimeout(() => setUser(undefined), 300)
    }
    const fDel = useForm()
    const submitDel = () => {
        if (user) {
            fDel.delete(route('users.delete', user.id), {
                onSuccess: () => closeDialog()
            })
        } else {
            toast.error('User tidak ditemukan')
        }
    }
    const txtSearch = useRef()
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title='User Management' />
            <div className="container my-4 px-2">
                <Card className="mx-auto max-w-7xl p-4">
                    <Button className="cursor-pointer lg:w-50" onClick={() => router.visit(route('users.create'))}>
                        Tambah User
                    </Button>
                    <div>
                        <Label>Pencarian Data</Label>
                        <div className="flex">
                            <Input
                                onKeyUp={(e) => {
                                    if (e.key === 'Enter') {
                                        cari(e.target.value);
                                    }
                                }}
                                className="md:w-60 rounded-r-none border-r-0"
                                placeholder="Cari Username/Name (↵ Enter)"
                                ref={txtSearch}
                            />
                            <Button onClick={() => cari('')} variant={'outline'} className='rounded-l-none border-l-0 bg-red-100'>
                                <EraserIcon />
                            </Button>
                        </div>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    <DotIcon />
                                </TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Username</TableHead>
                                <TableHead>No HP</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Akses</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((u, i) => (
                                <TableRow key={i}>
                                    <TableCell className='flex justify-center'>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button size='sm' variant='icon'>
                                                    <EllipsisIcon />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent>
                                                <DropdownMenuItem onClick={() => router.visit(route('users.edit', u.id))}>
                                                    <EditIcon className='me-1 text-amber-600' />Edit
                                                </DropdownMenuItem>
                                                {
                                                    u.id !== 1 ?
                                                        <DropdownMenuItem onClick={() => delUser(u)}>
                                                            <TrashIcon className='me-1 text-red-600' />Delete
                                                        </DropdownMenuItem>

                                                        : ''
                                                }
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                    <TableCell>
                                        {u.name}
                                    </TableCell>
                                    <TableCell>{u.username}</TableCell>
                                    <TableCell>{u.no_hp}</TableCell>
                                    <TableCell>{u.email}</TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-2">{u.permissions?.map((p) => <Badge key={p.id}>{p.name}</Badge>)}</div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination>
                        <PaginationContent>
                            {users.links.map((l, i) =>
                                i === 0 ? (
                                    <PaginationItem key={i}>
                                        <PaginationPrevious
                                            className="cursor-pointer"
                                            onClick={() => (users.prev_page_url ? router.visit(users.prev_page_url) : null)}
                                        />
                                    </PaginationItem>
                                ) : i + 1 === users.links.length ? (
                                    <PaginationItem key={i}>
                                        <PaginationNext
                                            className="cursor-pointer"
                                            onClick={() => (users.next_page_url ? router.visit(users.next_page_url) : null)}
                                        />
                                    </PaginationItem>
                                ) : (
                                    <PaginationItem key={i}>
                                        <PaginationLink className="cursor-pointer" onClick={() => (l.url ? router.visit(l.url) : null)}>
                                            {l.label}
                                        </PaginationLink>
                                    </PaginationItem>
                                ),
                            )}
                        </PaginationContent>
                    </Pagination>
                </Card>
            </div>
            {
                user ?
                    <Dialog open={dialogOpen} onOpenChange={fDel.processing ? null : closeDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Hapus User?</DialogTitle>
                                <DialogDescription>{user.name}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter className='flex gap-2'>
                                <Button variant='destructive' disabled={fDel.processing} onClick={submitDel}>Hapus</Button>
                                <Button onClick={closeDialog}>Batal</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    : ''
            }
        </AppLayout>
    )
}
