import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
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
    ArrowLeft,
    Trash2,
    ExternalLink,
    CheckCircle2,
    XCircle,
    Loader2,
    Clock,
} from 'lucide-react';
import axios from 'axios';
import type { ReactElement } from 'react';

interface ProvisionLog {
    id: number;
    step: string;
    step_display_name: string;
    status: string;
    output: string | null;
    error: string | null;
    created_at: string;
}

interface Site {
    id: number;
    domain: string;
    status: string;
    status_badge_color: string;
    wp_admin_username: string;
    wp_admin_email: string;
    ec2_path: string | null;
    public_ip: string | null;
    provisioned_at: string | null;
    destroyed_at: string | null;
    created_at: string;
    provision_logs: ProvisionLog[];
}

interface Props {
    site: Site;
}

const statusColorMap: Record<string, string> = {
    gray: 'bg-gray-500',
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    red: 'bg-red-500',
    yellow: 'bg-yellow-500',
};

const logStatusIcons: Record<string, ReactElement> = {
    pending: <Clock className="h-4 w-4 text-gray-500" />,
    running: <Loader2 className="h-4 w-4 text-blue-500 animate-spin" />,
    completed: <CheckCircle2 className="h-4 w-4 text-green-500" />,
    failed: <XCircle className="h-4 w-4 text-red-500" />,
};

export default function Show({ site: initialSite }: Props) {
    const [site, setSite] = useState(initialSite);
    const [deleting, setDeleting] = useState(false);

    // Poll for updates if site is provisioning
    useEffect(() => {
        if (site.status === 'provisioning') {
            const interval = setInterval(() => {
                axios.get(`/sites/${site.id}/logs`).then((response) => {
                    setSite((prev) => ({
                        ...prev,
                        status: response.data.site_status,
                        provision_logs: response.data.logs,
                    }));

                    // Stop polling if no longer provisioning
                    if (response.data.site_status !== 'provisioning') {
                        clearInterval(interval);
                    }
                });
            }, 3000); // Poll every 3 seconds

            return () => clearInterval(interval);
        }
    }, [site.id, site.status]);

    const handleDestroy = () => {
        setDeleting(true);
        router.delete(`/sites/${site.id}`, {
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`Site: ${site.domain}`} />

            <div className="container mx-auto py-8 max-w-4xl">
                <div className="mb-4">
                    <Link href="/sites">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Sites
                        </Button>
                    </Link>
                </div>

                {/* Site Information Card */}
                <Card className="mb-6">
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-3">
                                    {site.domain}
                                    {site.status === 'live' && (
                                        <a
                                            href={`http://${site.domain}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-blue-500 hover:text-blue-700"
                                        >
                                            <ExternalLink className="h-5 w-5" />
                                        </a>
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    Created on {site.created_at}
                                </CardDescription>
                            </div>
                            <Badge
                                className={statusColorMap[site.status_badge_color] || 'bg-gray-500'}
                            >
                                {site.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Admin Username
                                </p>
                                <p className="text-sm">{site.wp_admin_username}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Admin Email
                                </p>
                                <p className="text-sm">{site.wp_admin_email}</p>
                            </div>
                            {site.ec2_path && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        EC2 Path
                                    </p>
                                    <p className="text-sm font-mono">{site.ec2_path}</p>
                                </div>
                            )}
                            {site.public_ip && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Public IP
                                    </p>
                                    <p className="text-sm font-mono">{site.public_ip}</p>
                                </div>
                            )}
                            {site.provisioned_at && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Provisioned At
                                    </p>
                                    <p className="text-sm">{site.provisioned_at}</p>
                                </div>
                            )}
                        </div>

                        {/* Actions */}
                        {(site.status === 'live' || site.status === 'failed') && (
                            <div className="pt-4 border-t">
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="destructive" disabled={deleting}>
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            Destroy Site
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                This will permanently delete the WordPress site,
                                                database, files, and DNS records for{' '}
                                                <strong>{site.domain}</strong>. This action cannot be
                                                undone.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={handleDestroy}
                                                className="bg-red-600 hover:bg-red-700"
                                            >
                                                Destroy Site
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Provision Logs Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Provisioning Logs</CardTitle>
                        <CardDescription>
                            Step-by-step progress of the site provisioning
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {site.provision_logs.length === 0 ? (
                            <p className="text-muted-foreground text-center py-8">
                                No logs yet. Provisioning has not started.
                            </p>
                        ) : (
                            <Accordion type="single" collapsible className="w-full">
                                {site.provision_logs.map((log, index) => (
                                    <AccordionItem key={log.id} value={`log-${log.id}`}>
                                        <AccordionTrigger className="hover:no-underline">
                                            <div className="flex items-center gap-3 text-left">
                                                {logStatusIcons[log.status]}
                                                <div>
                                                    <p className="font-medium">
                                                        {index + 1}. {log.step_display_name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {log.created_at}
                                                    </p>
                                                </div>
                                            </div>
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <div className="pl-7 space-y-2">
                                                {log.output && (
                                                    <div>
                                                        <p className="text-sm font-medium mb-1">Output:</p>
                                                        <pre className="text-xs bg-muted p-3 rounded overflow-x-auto">
                                                            {log.output}
                                                        </pre>
                                                    </div>
                                                )}
                                                {log.error && (
                                                    <div>
                                                        <p className="text-sm font-medium mb-1 text-red-500">
                                                            Error:
                                                        </p>
                                                        <pre className="text-xs bg-red-50 text-red-900 p-3 rounded overflow-x-auto">
                                                            {log.error}
                                                        </pre>
                                                    </div>
                                                )}
                                                {!log.output && !log.error && (
                                                    <p className="text-sm text-muted-foreground italic">
                                                        {log.status === 'pending' && 'Waiting to start...'}
                                                        {log.status === 'running' && 'In progress...'}
                                                    </p>
                                                )}
                                            </div>
                                        </AccordionContent>
                                    </AccordionItem>
                                ))}
                            </Accordion>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
