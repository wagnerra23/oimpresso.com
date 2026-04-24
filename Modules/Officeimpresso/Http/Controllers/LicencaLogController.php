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
                ->rawColumns(['event', 'source_badge', 'endpoint'])
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
        return view('officeimpresso::licenca_log.index', compact('kpis', 'events', 'filter_licenca_id', 'filter_business_id'));
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
