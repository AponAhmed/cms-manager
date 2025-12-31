<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Jobs\Aws\DestroyWordPressSite;
use App\Jobs\ProvisionWordPressSite;
use App\Models\Site;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class SiteController extends Controller
{
    /**
     * Display a listing of sites
     */
    public function index(): Response
    {
        $sites = Site::with('provisionLogs')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($site) {
                return [
                    'id' => $site->id,
                    'domain' => $site->domain,
                    'status' => $site->status,
                    'status_badge_color' => $site->status_badge_color,
                    'wp_admin_username' => $site->wp_admin_username,
                    'wp_admin_email' => $site->wp_admin_email,
                    'provisioned_at' => $site->provisioned_at?->format('Y-m-d H:i:s'),
                    'created_at' => $site->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
        ]);
    }

    /**
     * Show the form for creating a new site
     */
    public function create(): Response
    {
        return Inertia::render('Sites/Create', [
            'mode' => config('provisioning.mode', 'local'),
            'domainSuffix' => config('provisioning.local.domain_suffix', '.test'),
        ]);
    }

    /**
     * Store a newly created site in storage
     */
    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Auto-append .test suffix for local mode
        if (config('provisioning.mode') === 'local') {
            $domain = $validated['domain'];
            if (!str_ends_with($domain, '.test')) {
                $validated['domain'] = $domain . '.test';
            }
        }

        // Create the site record
        $site = Site::create([
            'domain' => $validated['domain'],
            'wp_admin_username' => $validated['wp_admin_username'],
            'wp_admin_password' => $validated['wp_admin_password'],
            'wp_admin_email' => $validated['wp_admin_email'],
            'status' => Site::STATUS_PENDING,
        ]);

        // Dispatch the appropriate provisioning job
        // Dispatch the provisioning job
        ProvisionWordPressSite::dispatch($site->id);

        return redirect()->route('sites.show', $site)
            ->with('success', 'Site provisioning started!');
    }

    /**
     * Display the specified site with logs
     */
    public function show(Site $site): Response
    {
        $site->load('provisionLogs');

        return Inertia::render('Sites/Show', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'status' => $site->status,
                'status_badge_color' => $site->status_badge_color,
                'wp_admin_username' => $site->wp_admin_username,
                'wp_admin_email' => $site->wp_admin_email,
                'ec2_path' => $site->ec2_path,
                'public_ip' => $site->public_ip,
                'provisioned_at' => $site->provisioned_at?->format('Y-m-d H:i:s'),
                'destroyed_at' => $site->destroyed_at?->format('Y-m-d H:i:s'),
                'created_at' => $site->created_at->format('Y-m-d H:i:s'),
                'provision_logs' => $site->provisionLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'step' => $log->step,
                        'step_display_name' => $log->step_display_name,
                        'status' => $log->status,
                        'output' => $log->output,
                        'error' => $log->error,
                        'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Remove the specified site from storage
     */
    public function destroy(Site $site): RedirectResponse
    {


        // Dispatch the appropriate destroy job based on mode
        if (config('provisioning.mode') === 'local') {
            \App\Jobs\Local\DestroyWordPressSite::dispatch($site->id);
        } else {
            DestroyWordPressSite::dispatch($site->id);
        }

        return redirect()->route('sites.index')
            ->with('success', 'Site destruction started!');
    }

    /**
     * Permanently delete a site record
     */
    public function forceDelete(Site $site): RedirectResponse
    {
        if ($site->status !== Site::STATUS_DESTROYED) {
            return redirect()->back()
                ->with('error', 'Only destroyed sites can be permanently deleted');
        }

        $site->delete();

        return redirect()->route('sites.index')
            ->with('success', 'Site record permanently deleted');
    }

    /**
     * Get provision logs for a site (API endpoint for polling)
     */
    public function logs(Site $site)
    {
        $logs = $site->provisionLogs()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'step' => $log->step,
                    'step_display_name' => $log->step_display_name,
                    'status' => $log->status,
                    'output' => $log->output,
                    'error' => $log->error,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'site_status' => $site->status,
            'logs' => $logs,
        ]);
    }
}
