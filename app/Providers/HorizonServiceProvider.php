<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function register(): void
    {
        if (! $this->shouldExpose()) {
            return;
        }

        $this->app->register(\Laravel\Horizon\HorizonServiceProvider::class);

        parent::register();
    }

    public function boot(): void
    {
        if (! $this->shouldExpose()) {
            return;
        }

        parent::boot();
    }

    protected function gate(): void
    {
        Horizon::auth(static fn ($request) => $request->user()
            && $request->user()->can('superadmin'));
    }

    /**
     * ADR 0062: Hostinger NUNCA expõe Horizon UI. Apenas CT 100 Proxmox seta a flag.
     * Padrão idêntico ao MCP_TOOLS_EXPOSED (config/mcp.php).
     */
    protected function shouldExpose(): bool
    {
        return (bool) config('horizon.tools_exposed', false);
    }
}
