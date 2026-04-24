<?php

namespace Modules\Officeimpresso\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Officeimpresso\Entities\LicencaLog;
use Yajra\DataTables\Facades\DataTables;

class LicencaLogController extends Controller
{
    /**
     * Lista de logs — pagina admin.
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            $business_id = session()->get('user.business_id');
        } else {
            // Superadmin pode filtrar por qualquer business via query string
            $business_id = $request->query('business_id') ?: null;
        }

        if ($request->ajax()) {
            $query = LicencaLog::query();

            if ($business_id !== null) {
                $query->where('business_id', $business_id);
            }

            if ($request->filled('event')) {
                $query->where('event', $request->input('event'));
            }
            if ($request->filled('licenca_id')) {
                $query->where('licenca_id', $request->input('licenca_id'));
            }
            if ($request->filled('from')) {
                $query->where('created_at', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->where('created_at', '<=', $request->input('to'));
            }

            return DataTables::of($query->orderBy('created_at', 'desc'))
                ->editColumn('created_at', fn ($r) => $r->created_at ? $r->created_at->format('d/m/Y H:i:s') : '')
                ->editColumn('event', fn ($r) => '<span class="event-badge event-' . e($r->event) . '">' . e($r->event) . '</span>')
                ->editColumn('http_status', fn ($r) => $r->http_status ? e($r->http_status) : '—')
                ->editColumn('duration_ms', fn ($r) => $r->duration_ms ? e($r->duration_ms) . 'ms' : '—')
                ->editColumn('endpoint', function ($r) {
                    if ($r->event === 'login_error' && $r->error_message) {
                        return '<span class="text-danger" title="' . e($r->error_message) . '">' . e(\Illuminate\Support\Str::limit($r->error_message, 100)) . '</span>';
                    }
                    return $r->endpoint ? e($r->endpoint) : '—';
                })
                ->editColumn('ip', fn ($r) => $r->ip ?: '—')
                ->addColumn('source_badge', fn ($r) => '<span class="source-tag">' . e($r->source) . '</span>')
                ->addColumn('business_info', function ($r) {
                    if (! $r->business_id) return '—';
                    $name = \DB::table('business')->where('id', $r->business_id)->value('name');
                    return '<a href="' . url('/officeimpresso/licenca_computado/licencas/' . $r->business_id) . '" title="Ver computadores" class="text-primary">' . e($name ?: ('#' . $r->business_id)) . '</a>';
                })
                ->addColumn('machine_info', function ($r) {
                    if (! $r->licenca_id) return '—';
                    $lic = \DB::table('licenca_computador')->where('id', $r->licenca_id)->first(['user_win', 'ip_interno']);
                    if (! $lic) return '—';
                    $machine = $lic->user_win ?: '(sem hostname)';
                    $ipInt = $lic->ip_interno ? ' · ' . $lic->ip_interno : '';
                    return '<a href="' . url('/officeimpresso/licenca_log?licenca_id=' . $r->licenca_id) . '" title="Filtrar por maquina"><strong>' . e($machine) . '</strong><small class="text-muted">' . e($ipInt) . '</small></a>';
                })
                ->addColumn('blocked_info', function ($r) {
                    $m = $r->metadata;
                    if (is_string($m)) $m = json_decode($m, true);
                    if (! is_array($m)) return '<span class="oi-pill oi-pill-ok">Liberada</span>';
                    if (! empty($m['business_blocked'])) {
                        return '<span class="oi-pill oi-pill-blocked" title="Empresa inteira bloqueada">🔒 Empresa</span>';
                    }
                    if (! empty($m['licenca_blocked'])) {
                        return '<span class="oi-pill oi-pill-blocked" title="Apenas essa licenca bloqueada">🔒 Máquina</span>';
                    }
                    return '<span class="oi-pill oi-pill-ok">Liberada</span>';
                })
                ->rawColumns(['event', 'source_badge', 'endpoint', 'business_info', 'machine_info', 'blocked_info'])
                ->make(true);
        }

        // KPIs das ultimas 24h
        $since = now()->subHours(24);
        $base = LicencaLog::where('created_at', '>=', $since);
        if ($business_id !== null) {
            $base = $base->where('business_id', $business_id);
        }

        $kpis = [
            'login_success' => (clone $base)->where('event', 'login_success')->count(),
            'login_error'   => (clone $base)->where('event', 'login_error')->count(),
            'api_call'      => (clone $base)->where('event', 'api_call')->count(),
            'block'         => (clone $base)->where('event', 'block')->count(),
        ];

        $events = [
            'login_attempt', 'login_success', 'login_error', 'token_refresh',
            'api_call', 'create_licenca', 'update_licenca', 'block', 'unblock',
            'businessupdate',
        ];

        $filter_licenca_id  = $request->query('licenca_id');
        $filter_business_id = $request->query('business_id');

        // ==========================================================
        // Status de Login por Máquina (aggregate) — 1 linha por
        // business_id+user_agent+ip (identidade da maquina ate hd chegar)
        // ==========================================================
        $statusQuery = LicencaLog::where('event', 'login_success')
            ->selectRaw("
                business_id,
                ip,
                user_id,
                MAX(created_at) as last_login,
                COUNT(*) as login_count_24h,
                SUM(CASE WHEN metadata LIKE '%\"was_blocked\":true%' THEN 1 ELSE 0 END) as blocked_attempts,
                MAX(metadata) as last_metadata
            ")
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('business_id', 'ip', 'user_id')
            ->orderByDesc('last_login');
        if ($business_id !== null) {
            $statusQuery->where('business_id', $business_id);
        }
        $maquinas = $statusQuery->get()->map(function ($row) {
            $business = $row->business_id ? \DB::table('business')->where('id', $row->business_id)->first(['name', 'officeimpresso_bloqueado']) : null;
            $meta = is_string($row->last_metadata) ? json_decode($row->last_metadata, true) : [];
            $hd = $meta['hd'] ?? null;

            // Tentar identificar maquina sem hd:
            // 1. Se tem hd, match exato
            // 2. Senao, listar todas as maquinas do business + sugerir a que bate com ip_interno
            $guessedMachine = null;
            $totalMaquinas  = 0;
            $knownMachines  = [];
            if ($row->business_id) {
                $query = \DB::table('licenca_computador')->where('business_id', $row->business_id);
                $totalMaquinas = (clone $query)->count();
                if ($hd) {
                    $guessedMachine = (clone $query)->where('hd', $hd)->first(['id', 'hd', 'user_win', 'ip_interno', 'bloqueado']);
                } else {
                    // Sem hd: tenta por ip_interno (ultimos octetos podem bater com o publico)
                    // Fallback: listar todas as maquinas ativas (nao bloqueadas)
                    $knownMachines = (clone $query)->select(['id', 'hd', 'user_win', 'ip_interno', 'bloqueado', 'dt_ultimo_acesso'])
                        ->orderByDesc('dt_ultimo_acesso')
                        ->limit(10)
                        ->get();
                }
            }

            return (object) [
                'business_id'       => $row->business_id,
                'business_name'     => $business->name ?? '—',
                'business_blocked'  => (bool) ($business->officeimpresso_bloqueado ?? false),
                'ip'                => $row->ip,
                'last_login'        => $row->last_login,
                'login_count_24h'   => $row->login_count_24h,
                'blocked_attempts'  => $row->blocked_attempts,
                'was_blocked_last'  => (bool) ($meta['was_blocked'] ?? false),
                'hd'                => $hd,
                'user_id'           => $row->user_id,
                'guessed_machine'   => $guessedMachine,
                'total_maquinas'    => $totalMaquinas,
                'known_machines'    => $knownMachines,
            ];
        });

        return view('officeimpresso::licenca_log.index', compact('kpis', 'events', 'filter_licenca_id', 'filter_business_id', 'maquinas'));
    }

    public function show($id)
    {
        $log = LicencaLog::findOrFail($id);
        if (! auth()->user()->can('superadmin')) {
            abort_unless($log->business_id === session()->get('user.business_id'), 403);
        }
        return response()->json([
            'id'            => $log->id,
            'event'         => $log->event,
            'created_at'    => $log->created_at?->toDateTimeString(),
            'user_id'       => $log->user_id,
            'business_id'   => $log->business_id,
            'licenca_id'    => $log->licenca_id,
            'client_id'     => $log->client_id,
            'token_hint'    => $log->token_hint,
            'ip'            => $log->ip,
            'user_agent'    => $log->user_agent,
            'endpoint'      => $log->endpoint,
            'http_method'   => $log->http_method,
            'http_status'   => $log->http_status,
            'duration_ms'   => $log->duration_ms,
            'error_code'    => $log->error_code,
            'error_message' => $log->error_message,
            'metadata'      => $log->metadata,
            'source'        => $log->source,
        ]);
    }
}
