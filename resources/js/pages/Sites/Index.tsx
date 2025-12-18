import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Plus, ExternalLink, Eye, Trash2, XCircle } from 'lucide-react';

interface Site {
    id: number;
    domain: string;
    status: string;
    status_badge_color: string;
    wp_admin_username: string;
    wp_admin_email: string;
    provisioned_at: string | null;
    created_at: string;
}

interface Props {
    sites: Site[];
}

const statusColorMap: Record<string, string> = {
    gray: 'bg-gray-500',
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    red: 'bg-red-500',
    yellow: 'bg-yellow-500',
};

export default function Index({ sites = [] }: Props) {
    const [deletingSiteId, setDeletingSiteId] = useState<number | null>(null);
    const [forceDeletingSiteId, setForceDeletingSiteId] = useState<number | null>(
        null
    );

    const handleDelete = (siteId: number) => {
        setDeletingSiteId(siteId);
        router.delete(`/sites/${siteId}`, {
            onFinish: () => setDeletingSiteId(null),
        });
    };

    const handleForceDelete = (siteId: number) => {
        setForceDeletingSiteId(siteId);
        router.delete(`/sites/${siteId}/force`, {
            onFinish: () => setForceDeletingSiteId(null),
        });
    };

    return (
        <AppLayout>
            <Head title="WordPress Sites" />

            <div className="container mx-auto py-8">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>WordPress Sites</CardTitle>
                            <CardDescription>
                                Manage your WordPress site provisioning
                            </CardDescription>
                        </div>
                        <Link href="/sites/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Provision New Site
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {sites.length === 0 ? (
                            <div className="text-center py-12">
                                <p className="text-muted-foreground mb-4">
                                    No sites yet. Create your first WordPress site!
                                </p>
                                <Link href="/sites/create">
                                    <Button variant="outline">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Get Started
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Domain</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Admin Username</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead>Provisioned</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sites.map((site) => (
                                        <TableRow key={site.id}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    {site.domain}
                                                    {site.status === 'live' && (
                                                        <a
                                                            href={`http://${site.domain}`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-blue-500 hover:text-blue-700"
                                                        >
                                                            <ExternalLink className="h-4 w-4" />
                                                        </a>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={
                                                        statusColorMap[site.status_badge_color] ||
                                                        'bg-gray-500'
                                                    }
                                                >
                                                    {site.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{site.wp_admin_username}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {site.created_at}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {site.provisioned_at || '-'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link href={`/sites/${site.id}`}>
                                                        <Button variant="outline" size="sm">
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            View
                                                        </Button>
                                                    </Link>

                                                    <AlertDialog>
                                                        <AlertDialogTrigger asChild>
                                                            {site.status === 'destroyed' ? (
                                                                <Button
                                                                    variant="destructive"
                                                                    size="sm"
                                                                    title="Permanently Delete"
                                                                    disabled={
                                                                        forceDeletingSiteId === site.id
                                                                    }
                                                                >
                                                                    <XCircle className="h-4 w-4" />
                                                                </Button>
                                                            ) : (
                                                                <Button
                                                                    variant="destructive"
                                                                    size="sm"
                                                                    disabled={deletingSiteId === site.id || site.status === 'destroyed'}
                                                                    title="Destroy Site"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                        </AlertDialogTrigger>
                                                        <AlertDialogContent>
                                                            <AlertDialogHeader>
                                                                <AlertDialogTitle>
                                                                    {site.status === 'destroyed'
                                                                        ? 'Permanently Delete Record?'
                                                                        : 'Destroy Site?'}
                                                                </AlertDialogTitle>
                                                                <AlertDialogDescription>
                                                                    {site.status === 'destroyed' ? (
                                                                        <>
                                                                            This will permanently
                                                                            remove the record for{' '}
                                                                            <strong>{site.domain}</strong>{' '}
                                                                            from the database. The site
                                                                            files have already been
                                                                            destroyed.
                                                                        </>
                                                                    ) : (
                                                                        <>
                                                                            This will permanently delete{' '}
                                                                            <strong>{site.domain}</strong>,
                                                                            including all files, database,
                                                                            and DNS records. This action
                                                                            cannot be undone.
                                                                        </>
                                                                    )}
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                                <AlertDialogCancel>
                                                                    Cancel
                                                                </AlertDialogCancel>
                                                                <AlertDialogAction
                                                                    onClick={() =>
                                                                        site.status === 'destroyed'
                                                                            ? handleForceDelete(
                                                                                site.id
                                                                            )
                                                                            : handleDelete(site.id)
                                                                    }
                                                                    className="bg-red-600 hover:bg-red-700"
                                                                >
                                                                    {site.status === 'destroyed'
                                                                        ? 'Permanently Delete'
                                                                        : 'Destroy Site'}
                                                                </AlertDialogAction>
                                                            </AlertDialogFooter>
                                                        </AlertDialogContent>
                                                    </AlertDialog>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
