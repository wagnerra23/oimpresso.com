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
            // event_in=login_success,login_error limita a eventos de autorizacao de uso
            if ($request->filled('event_in')) {
                $events_list = array_filter(array_map('trim', explode(',', $request->input('event_in'))));
                if (! empty($events_list)) {
                    $query->whereIn('event', $events_list);
                }
            }
            // Busca textual por nome empresa / cnpj / hd / hostname — resolve
            // pra business_ids e restringe na query
            if ($request->filled('q')) {
                $like = '%' . trim($request->input('q')) . '%';
                $biz  = \DB::table('business')->where(function ($qq) use ($like) {
                    $qq->where('name', 'like', $like)->orWhere('razao_social', 'like', $like)->orWhere('cnpj', 'like', $like);
                })->pluck('id');
                $maq  = \DB::table('licenca_computador')->where(function ($qq) use ($like) {
                    $qq->where('hd', 'like', $like)->orWhere('user_win', 'like', $like)->orWhere('hostname', 'like', $like);
                })->pluck('business_id');
                $ids = $biz->merge($maq)->unique()->values();
                $query->whereIn('business_id', $ids->isEmpty() ? [0] : $ids);
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
                ->editColumn('event', function ($r) {
                    // Tela de Autorizacao de Uso — traduz rotulos do login_*
                    $labels = [
                        'login_success' => 'Autorização concedida',
                        'login_error'   => 'Autorização negada',
                    ];
                    $label = $labels[$r->event] ?? $r->event;
                    return '<span class="event-badge event-' . e($r->event) . '">' . e($label) . '</span>';
                })
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

        // ==========================================================
        // FONTE: licenca_computador (registro oficial da maquina).
        // A rotina /connector/api/processa-dados-cliente + saveEquipamento
        // que popula/atualiza esta tabela. Aqui listamos TUDO que existe
        // registrado e enriquecemos cada linha com o ultimo acesso logado.
        // Filtros: por empresa e por equipamento (hyperlinks) + busca livre.
        // ==========================================================
        $filter_licenca_id   = $request->query('licenca_id');
        $filter_business_id  = $request->query('business_id');
        $filter_q            = trim((string) $request->query('q', ''));
        $filter_estado_atual = $request->query('estado_atual');
        $filter_hd           = trim((string) $request->query('hd', ''));

        $query = \DB::table('licenca_computador as lc')
            ->leftJoin('business as b', 'b.id', '=', 'lc.business_id')
            ->select([
                'lc.id as licenca_id',
                'lc.business_id',
                'lc.hd',
                'lc.user_win',
                'lc.hostname',
                'lc.ip_interno',
                'lc.versao_exe',
                'lc.versao_banco',
                'lc.sistema_operacional',
                'lc.sistema',
                'lc.bloqueado as machine_blocked',
                'lc.dt_ultimo_acesso',
                'b.name as business_name',
                \DB::raw('COALESCE(b.officeimpresso_bloqueado, 0) as business_blocked'),
            ]);

        if ($business_id !== null) {
            $query->where('lc.business_id', $business_id);
        }
        if ($filter_licenca_id) {
            $query->where('lc.id', $filter_licenca_id);
        }
        if ($filter_hd !== '') {
            $query->where('lc.hd', $filter_hd);
        }
        if ($filter_q !== '') {
            $like = '%' . $filter_q . '%';
            $query->where(function ($qq) use ($like) {
                $qq->where('b.name', 'like', $like)
                    ->orWhere('b.cnpj', 'like', $like)
                    ->orWhere('b.razao_social', 'like', $like)
                    ->orWhere('lc.hd', 'like', $like)
                    ->orWhere('lc.user_win', 'like', $like)
                    ->orWhere('lc.hostname', 'like', $like)
                    ->orWhere('lc.ip_interno', 'like', $like);
            });
        }
        if ($filter_estado_atual === 'bloqueada') {
            $query->where(function ($qq) {
                $qq->where('lc.bloqueado', 1)
                    ->orWhere('b.officeimpresso_bloqueado', 1);
            });
        } elseif ($filter_estado_atual === 'ativa') {
            $query->where(function ($qq) {
                $qq->where('lc.bloqueado', 0)->orWhereNull('lc.bloqueado');
            })->where(function ($qq) {
                $qq->where('b.officeimpresso_bloqueado', 0)->orWhereNull('b.officeimpresso_bloqueado');
            });
        }

        $maquinas = $query->orderByDesc('lc.dt_ultimo_acesso')->get();

        // Enriquecimento: ultimo registro de processa-dados-cliente
        // por licenca (1 query, dedupe em PHP pela ordem desc).
        $ids = $maquinas->pluck('licenca_id')->filter()->values();
        $lastByLicenca = [];
        if ($ids->isNotEmpty()) {
            $logs = LicencaLog::whereIn('licenca_id', $ids)
                ->where('source', 'delphi_middleware')
                ->where('endpoint', 'like', '%processa-dados-cliente%')
                ->orderByDesc('created_at')
                ->get(['licenca_id', 'created_at', 'metadata', 'ip', 'business_location_id']);
            foreach ($logs as $log) {
                if (! isset($lastByLicenca[$log->licenca_id])) {
                    $lastByLicenca[$log->licenca_id] = $log;
                }
            }
        }

        // Carrega dados das locations referenciadas (1 query so)
        $locationIds = collect($lastByLicenca)->pluck('business_location_id')->filter()->unique()->values();
        $locationsById = [];
        if ($locationIds->isNotEmpty()) {
            $locationsById = \DB::table('business_locations')
                ->whereIn('id', $locationIds)
                ->get(['id', 'name', 'cnpj', 'razao_social'])
                ->keyBy('id')
                ->toArray();
        }

        $maquinas = $maquinas->map(function ($m) use ($lastByLicenca, $locationsById) {
            $last = $lastByLicenca[$m->licenca_id] ?? null;
            $meta = $last && is_string($last->metadata) ? json_decode($last->metadata, true) : [];
            // last_login_ts: usa log se existir, senao dt_ultimo_acesso do cadastro
            $effectiveTs = $last?->created_at ?? $m->dt_ultimo_acesso;
            $lastLocation = $last && $last->business_location_id ? ($locationsById[$last->business_location_id] ?? null) : null;
            return (object) [
                'licenca_id'        => $m->licenca_id,
                'business_id'       => $m->business_id,
                'business_name'     => $m->business_name ?: '—',
                'business_blocked'  => (bool) $m->business_blocked,
                'machine_blocked'   => (bool) $m->machine_blocked,
                'hd'                => $m->hd,
                'user_win'          => $m->user_win,
                'hostname'          => $m->hostname,
                'ip_interno'        => $m->ip_interno,
                'versao_exe'        => $m->versao_exe,
                'versao_banco'      => $m->versao_banco,
                'sistema_operacional' => $m->sistema_operacional,
                'sistema'           => $m->sistema,
                'last_login'        => $last?->created_at,
                'last_ip'           => $last?->ip ?? $m->ip_interno,
                'was_blocked_last'  => $last ? (bool) ($meta['was_blocked'] ?? false) : null,
                'dt_ultimo_acesso'  => $m->dt_ultimo_acesso,
                'effective_ts'      => $effectiveTs,  // pra ordenacao
                'last_location'     => $lastLocation,  // business_location usada na ultima chamada
            ];
        });

        // Ordena pelo ultimo acesso efetivo desc (log > cadastro; nulls no fim)
        $maquinas = $maquinas->sortByDesc(fn ($m) => $m->effective_ts ?: '0000-00-00')->values();

        // KPIs gerais (nao dependem do filtro aplicado)
        $kpis = [
            'total_maquinas'      => \DB::table('licenca_computador')->count(),
            'maquinas_bloqueadas' => \DB::table('licenca_computador')->where('bloqueado', 1)->count(),
            'empresas_bloqueadas' => \DB::table('business')->where('officeimpresso_bloqueado', 1)->count(),
            'chamadas_24h'        => LicencaLog::where('source', 'delphi_middleware')
                ->where('endpoint', 'like', '%processa-dados-cliente%')
                ->where('created_at', '>=', now()->subHours(24))
                ->count(),
        ];

        return view('officeimpresso::licenca_log.index', compact(
            'kpis', 'filter_licenca_id', 'filter_business_id', 'filter_q',
            'filter_estado_atual', 'filter_hd', 'maquinas'
        ));
    }

    /**
     * Timeline de logins de uma maquina especifica.
     * URL: /officeimpresso/licenca_log/timeline/{licenca_id}
     */
    public function timeline($licenca_id)
    {
        $maquina = \DB::table('licenca_computador as lc')
            ->leftJoin('business as b', 'b.id', '=', 'lc.business_id')
            ->where('lc.id', $licenca_id)
            ->select([
                'lc.id', 'lc.business_id', 'lc.hd', 'lc.user_win', 'lc.hostname',
                'lc.ip_interno', 'lc.bloqueado as machine_blocked', 'lc.dt_ultimo_acesso',
                'b.name as business_name',
                \DB::raw('COALESCE(b.officeimpresso_bloqueado, 0) as business_blocked'),
            ])->first();
        if (! $maquina) abort(404);

        if (! auth()->user()->can('superadmin')) {
            abort_unless($maquina->business_id === session()->get('user.business_id'), 403);
        }

        $logs = LicencaLog::where('licenca_id', $licenca_id)
            ->where('source', 'delphi_middleware')
            ->where('endpoint', 'like', '%processa-dados-cliente%')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('officeimpresso::licenca_log.timeline', compact('maquina', 'logs'));
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
