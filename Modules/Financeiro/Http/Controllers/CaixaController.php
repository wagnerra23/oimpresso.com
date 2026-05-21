<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela /financeiro/caixa — wrapper Inertia sobre cash_registers core UltimatePOS.
 *
 * Wagner 2026-05-21 Fase 6 deprecação legacy (Soft wrapper). NÃO migra dados —
 * reusa tabela `cash_registers` + `cash_register_transactions` do core.
 * Apenas adiciona view Inertia bonita + entrada no sidebar Financeiro pra Larissa
 * descobrir histórico de fechamentos sem precisar voltar à tela POS.
 *
 * Lifecycle real (abrir/fechar caixa) CONTINUA na header POS (`/sells/pos/create`)
 * via `CashRegisterController` core. Esta tela é READ-ONLY (histórico + atual).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): `business_id` lido de
 * `session('user.business_id')`, filtra SQL explícito.
 *
 * Permission gate: `view_cash_register` (mesmo do CashRegisterController core).
 *
 * Endpoints:
 *  - GET /financeiro/caixa → Inertia render
 *
 * Reversibilidade: deletar este Controller + Page Inertia + rota + entrada
 * sidebar não afeta NADA do POS/core. Nenhuma migration, nenhuma mudança em
 * cash_registers table.
 */
class CaixaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:view_cash_register');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) session('user.business_id');

        return OtelHelper::spanBiz('financeiro.caixa.index', function () use ($businessId, $request) {
            $statusFilter = $request->query('status'); // null|'open'|'close'
            $limit = (int) ($request->query('limit') ?? 50);
            $limit = max(10, min(200, $limit)); // clamp [10, 200]

            $query = DB::table('cash_registers as cr')
                ->where('cr.business_id', $businessId)
                ->leftJoin('users as u', 'u.id', '=', 'cr.user_id')
                ->leftJoin('business_locations as bl', 'bl.id', '=', 'cr.location_id')
                ->select(
                    'cr.id',
                    'cr.status',
                    'cr.created_at as open_time',
                    'cr.closed_at as close_time',
                    'cr.closing_amount',
                    'cr.total_card_slips',
                    'cr.total_cheques',
                    'cr.closing_note',
                    'cr.user_id',
                    DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) as user_name"),
                    'cr.location_id',
                    'bl.name as location_name'
                );

            if ($statusFilter === 'open' || $statusFilter === 'close') {
                $query->where('cr.status', $statusFilter);
            }

            $caixas = $query
                ->orderByDesc('cr.created_at')
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    // Soma transações por método (consulta agregada — N+1 OK pra ≤200 rows)
                    $totals = DB::table('cash_register_transactions')
                        ->where('cash_register_id', $row->id)
                        ->whereNull('parent_id') // ignora estornos
                        ->selectRaw('
                            SUM(CASE WHEN type="credit" THEN amount ELSE 0 END) as total_credit,
                            SUM(CASE WHEN type="debit" THEN amount ELSE 0 END) as total_debit
                        ')
                        ->first();

                    return [
                        'id' => (int) $row->id,
                        'status' => (string) $row->status,
                        'open_time' => $row->open_time,
                        'close_time' => $row->close_time,
                        'closing_amount' => (float) $row->closing_amount,
                        'total_credit' => (float) ($totals->total_credit ?? 0.0),
                        'total_debit' => (float) ($totals->total_debit ?? 0.0),
                        'total_card_slips' => (int) ($row->total_card_slips ?? 0),
                        'total_cheques' => (int) ($row->total_cheques ?? 0),
                        'closing_note' => $row->closing_note,
                        'user_id' => (int) ($row->user_id ?? 0),
                        'user_name' => trim((string) ($row->user_name ?? '')) ?: '—',
                        'location_id' => (int) ($row->location_id ?? 0),
                        'location_name' => $row->location_name ?? '—',
                    ];
                });

            // Total agregado pra cards do topo
            $stats = DB::table('cash_registers')
                ->where('business_id', $businessId)
                ->selectRaw('
                    COUNT(*) as total_caixas,
                    SUM(CASE WHEN status="open" THEN 1 ELSE 0 END) as caixas_abertos,
                    SUM(CASE WHEN status="close" THEN closing_amount ELSE 0 END) as soma_fechamentos
                ')
                ->first();

            return Inertia::render('Financeiro/Caixa/Index', [
                'caixas' => $caixas->values(),
                'stats' => [
                    'total_caixas' => (int) ($stats->total_caixas ?? 0),
                    'caixas_abertos' => (int) ($stats->caixas_abertos ?? 0),
                    'soma_fechamentos' => (float) ($stats->soma_fechamentos ?? 0.0),
                ],
                'filters' => [
                    'status' => $statusFilter,
                    'limit' => $limit,
                ],
                'links' => [
                    // Atalho pro POS legacy onde de fato abre/fecha caixa.
                    'pos_create' => '/pos/create',
                    'cash_register_legacy' => '/cash-register',
                ],
            ]);
        }, ['op' => 'index']);
    }
}
