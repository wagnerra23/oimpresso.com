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

            // Fix Wagner 2026-05-21: `cash_registers` core UltimatePOS NÃO tem
            // coluna `location_id` (migration 2018_01_30_181442 — só business_id +
            // user_id + status + closed_at + closing_amount + total_card_slips +
            // total_cheques + closing_note + timestamps). leftJoin com
            // `business_locations as bl ON bl.id = cr.location_id` causava
            // SQLSTATE[42S22] Unknown column → 500 server error em prod.
            //
            // Localização do caixa não é rastreada nesta tabela legacy. Quando
            // US-FIN-CAIXA-LOCATION migrar, adicionar coluna + reintroduzir JOIN.
            // Por enquanto: payload omite location_id/location_name (front cobre
            // com fallback '—').
            $query = DB::table('cash_registers as cr')
                ->where('cr.business_id', $businessId)
                ->leftJoin('users as u', 'u.id', '=', 'cr.user_id')
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
                    DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) as user_name")
                );

            if ($statusFilter === 'open' || $statusFilter === 'close') {
                $query->where('cr.status', $statusFilter);
            }

            $caixas = $query
                ->orderByDesc('cr.created_at')
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    // Soma transações por método (consulta agregada — N+1 OK pra ≤200 rows).
                    //
                    // Fix Wagner 2026-05-21 (PR após #1373): `cash_register_transactions`
                    // (migration 2018_01_31_125836) NÃO tem coluna `parent_id` — só id,
                    // cash_register_id, amount, pay_method, type, transaction_type,
                    // transaction_id, timestamps. `->whereNull('parent_id')` causava
                    // SQLSTATE[42S22] Unknown column → 500 server error.
                    //
                    // Lógica anterior tentava ignorar estornos via parent_id (pattern
                    // de `transactions` core que tem `return_parent_id`/`transfer_parent_id`).
                    // Pra cash_register_transactions não há campo equivalente — estornos
                    // viram registros novos com `type=debit`/`type=credit` invertidos.
                    // Overcount minimal (estornos raros nesta tabela). US-FIN-CAIXA-ESTORNOS
                    // (futuro) pode refinar via heurística de `transaction_id` cruzado.
                    $totals = DB::table('cash_register_transactions')
                        ->where('cash_register_id', $row->id)
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
                        // location_id/location_name omitidos — coluna não existe
                        // em cash_registers core (migration 2018). Front cobre
                        // com fallback '—' via default opcional no .tsx.
                        'location_id' => 0,
                        'location_name' => '—',
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
