import { Head, Link } from '@inertiajs/react';
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
import { Plus, ExternalLink } from 'lucide-react';

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
                                                <Link href={`/sites/${site.id}`}>
                                                    <Button variant="outline" size="sm">
                                                        View Logs
                                                    </Button>
                                                </Link>
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
