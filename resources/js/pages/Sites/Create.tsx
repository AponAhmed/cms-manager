import { FormEventHandler, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ArrowLeft, Loader2, CheckCircle2, XCircle } from 'lucide-react';

interface Props {
    mode: 'local' | 'aws';
    domainSuffix: string;
}

export default function Create({ mode = 'local', domainSuffix = '.test' }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        domain: '',
        wp_admin_username: '',
        wp_admin_password: '',
        wp_admin_email: '',
    });

    const [passwordStrength, setPasswordStrength] = useState<string>('');

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/sites');
    };

    const handlePasswordChange = (password: string) => {
        setData('wp_admin_password', password);

        // Simple password strength indicator
        if (password.length < 8) {
            setPasswordStrength('weak');
        } else if (password.length < 12) {
            setPasswordStrength('medium');
        } else {
            setPasswordStrength('strong');
        }
    };

    const generatePassword = () => {
        const length = 32;
        const charset =
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        handlePasswordChange(password);
    };

    return (
        <AppLayout>
            <Head title="Provision New Site" />

            <div className="container mx-auto py-8 max-w-2xl">
                <div className="mb-4">
                    <Link href="/sites">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Sites
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Provision New WordPress Site</CardTitle>
                        <CardDescription>
                            {mode === 'local'
                                ? 'Fill in the details below to provision a WordPress site on your local machine'
                                : 'Fill in the details below to automatically provision a new WordPress site on your EC2 instance'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Domain */}
                            <div className="space-y-2">
                                <Label htmlFor="domain">Domain Name</Label>
                                <Input
                                    id="domain"
                                    type="text"
                                    placeholder="example.com"
                                    value={data.domain}
                                    onChange={(e) => setData('domain', e.target.value)}
                                    className={errors.domain ? 'border-red-500' : ''}
                                />
                                {errors.domain && (
                                    <p className="text-sm text-red-500">{errors.domain}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {mode === 'local'
                                        ? `Enter domain name (${domainSuffix} will be added automatically, e.g., mysite${domainSuffix})`
                                        : 'Enter the full domain name (e.g., example.com or subdomain.example.com)'}
                                </p>
                            </div>

                            {/* WordPress Admin Username */}
                            <div className="space-y-2">
                                <Label htmlFor="wp_admin_username">
                                    WordPress Admin Username
                                </Label>
                                <Input
                                    id="wp_admin_username"
                                    type="text"
                                    placeholder="admin"
                                    value={data.wp_admin_username}
                                    onChange={(e) =>
                                        setData('wp_admin_username', e.target.value)
                                    }
                                    className={errors.wp_admin_username ? 'border-red-500' : ''}
                                />
                                {errors.wp_admin_username && (
                                    <p className="text-sm text-red-500">
                                        {errors.wp_admin_username}
                                    </p>
                                )}
                            </div>

                            {/* WordPress Admin Email */}
                            <div className="space-y-2">
                                <Label htmlFor="wp_admin_email">WordPress Admin Email</Label>
                                <Input
                                    id="wp_admin_email"
                                    type="email"
                                    placeholder="admin@example.com"
                                    value={data.wp_admin_email}
                                    onChange={(e) => setData('wp_admin_email', e.target.value)}
                                    className={errors.wp_admin_email ? 'border-red-500' : ''}
                                />
                                {errors.wp_admin_email && (
                                    <p className="text-sm text-red-500">
                                        {errors.wp_admin_email}
                                    </p>
                                )}
                            </div>

                            {/* WordPress Admin Password */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="wp_admin_password">
                                        WordPress Admin Password
                                    </Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={generatePassword}
                                    >
                                        Generate Secure Password
                                    </Button>
                                </div>
                                <Input
                                    id="wp_admin_password"
                                    type="text"
                                    placeholder="Enter a strong password"
                                    value={data.wp_admin_password}
                                    onChange={(e) => handlePasswordChange(e.target.value)}
                                    className={errors.wp_admin_password ? 'border-red-500' : ''}
                                />
                                {errors.wp_admin_password && (
                                    <p className="text-sm text-red-500">
                                        {errors.wp_admin_password}
                                    </p>
                                )}
                                {data.wp_admin_password && (
                                    <div className="flex items-center gap-2 text-sm">
                                        {passwordStrength === 'weak' && (
                                            <>
                                                <XCircle className="h-4 w-4 text-red-500" />
                                                <span className="text-red-500">Weak password</span>
                                            </>
                                        )}
                                        {passwordStrength === 'medium' && (
                                            <>
                                                <CheckCircle2 className="h-4 w-4 text-yellow-500" />
                                                <span className="text-yellow-500">Medium password</span>
                                            </>
                                        )}
                                        {passwordStrength === 'strong' && (
                                            <>
                                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                <span className="text-green-500">Strong password</span>
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Info Alert */}
                            <Alert>
                                <AlertDescription>
                                    <strong>Note:</strong> Provisioning typically takes{' '}
                                    {mode === 'local' ? '1-2' : '3-5'} minutes.{' '}
                                    {mode === 'local' && 'Sites will be accessible locally at '}
                                    {mode === 'local' && (
                                        <code className="text-xs">http://[domain]{domainSuffix}</code>
                                    )}
                                    {mode === 'local'
                                        ? ''
                                        : "You'll be redirected to the logs page to monitor progress."}
                                </AlertDescription>
                            </Alert>

                            {/* Submit Button */}
                            <div className="flex justify-end gap-3">
                                <Link href="/sites">
                                    <Button type="button" variant="outline" disabled={processing}>
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    Provision Site
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
