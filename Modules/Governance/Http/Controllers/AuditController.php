<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit log drill-down — Constituição Art. 9.
 *
 * Filtros: actor, módulo, ação, outcome, período. mcp_audit_log é
 * append-only (trigger MySQL — ADR 0084) então read-only aqui.
 *
 * Export LGPD por business_id (Art. 18 LGPD) fica pra próxima iteração.
 */
class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): Response
    {
        $period   = $request->input('period', '24h');
        $actor    = $request->input('actor');
        $endpoint = $request->input('endpoint');
        $status   = $request->input('status');

        $cutoff = match ($period) {
            '1h'    => now()->subHour(),
            '24h'   => now()->subDay(),
            '7d'    => now()->subDays(7),
            '30d'   => now()->subDays(30),
            default => now()->subDay(),
        };

        // Schema mcp_audit_log: usa `ts` (auto current_timestamp) ou `created_at`.
        // Ambos existem; usar `ts` que é o canonical da tabela.
        $q = DB::table('mcp_audit_log')
            ->where('ts', '>', $cutoff);

        if ($actor) {
            // mcp_audit_log usa user_id; lookup actor_slug via mcp_actors join se necessário
            $userIds = DB::table('mcp_actors')
                ->where('slug', $actor)
                ->whereNull('revoked_at')
                ->pluck('user_id')
                ->filter()
                ->values()
                ->all();
            if (!empty($userIds)) {
                $q->whereIn('user_id', $userIds);
            } else {
                $q->whereRaw('1=0'); // actor não encontrado = zero results
            }
        }
        if ($endpoint) $q->where('endpoint', $endpoint);
        if ($status)   $q->where('status', $status);

        $entries = $q->orderByDesc('ts')
            ->limit(200)
            ->select('id', 'user_id', 'business_id', 'endpoint', 'tool_or_resource', 'status', 'duration_ms', 'ts as created_at')
            ->get();

        // KPIs do período
        $kpis = [
            'total'        => $entries->count(),
            'errors'       => $entries->where('status', '!=', 'ok')->count(),
            'unique_users' => $entries->pluck('user_id')->unique()->count(),
        ];

        // Distinct values pra filtros (endpoint é enum — 7 valores fixos)
        $availableEndpoints = DB::table('mcp_audit_log')
            ->where('ts', '>', now()->subDays(30))
            ->select('endpoint')
            ->distinct()
            ->orderBy('endpoint')
            ->limit(50)
            ->pluck('endpoint')
            ->all();

        $availableActors = DB::table('mcp_actors')
            ->whereNull('revoked_at')
            ->select('slug', 'display_name')
            ->orderBy('slug')
            ->get();

        return Inertia::render('governance/Audit', [
            'entries' => $entries,
            'kpis'    => $kpis,
            'filters' => [
                'period'   => $period,
                'actor'    => $actor,
                'endpoint' => $endpoint,
                'status'   => $status,
            ],
            'available_endpoints' => $availableEndpoints,
            'available_actors'    => $availableActors,
        ]);
    }
}
