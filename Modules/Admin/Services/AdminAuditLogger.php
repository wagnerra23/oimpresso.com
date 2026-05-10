<?php

namespace Modules\Admin\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminAuditLogger — append-only audit pra mutations Admin Center.
 *
 * Toda mutation (apply Curador / regenerate token / run-now health) gera
 * linha em `mcp_admin_audit_log` com user_id + business_id + action + payload + IP.
 *
 * @see memory/decisions/0122-admin-center-ct100.md §3
 */
class AdminAuditLogger
{
    public function log(string $action, array $payload = [], ?Request $request = null): void
    {
        try {
            DB::table('mcp_admin_audit_log')->insert([
                'user_id'     => Auth::id(),
                'business_id' => session('user.business_id') ?? session('business.id') ?? 0,
                'action'      => $action,
                'route'       => $request?->path(),
                'ip'          => $request?->ip(),
                'payload'     => json_encode($payload),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('admin.audit.fail', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
