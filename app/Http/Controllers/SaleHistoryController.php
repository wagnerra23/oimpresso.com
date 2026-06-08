<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Fsm\Models\SaleStageHistory;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-SELL-035 — Timeline FSM de uma transaction.
 *
 * GET /api/sells/{id}/history → retorna histórico de transições da venda
 *   pra rendering em <SaleTimeline /> (Wave 3 frontend) ou consumo CLI.
 *
 * GET /api/sells/{id}/timeline-unified → agrega FSM + payments + activities
 *   (+ comments/audit_log se tabelas existirem) num único stream cronológico
 *   reverso pra Sells/Show + drawer. Gap #11 do parking lot pós-PR #1663.
 *
 * Multi-tenant Tier 0 (ADR 0093): HasBusinessScope em SaleStageHistory já
 * escopa por business_id da sessão. Cross-tenant attempt retorna 404.
 *
 * Permissão: `sale.history.view` (default ON pra roles vendas.*,
 * financeiro.*, gerencial). User sem permission → 403.
 */
class SaleHistoryController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if (! auth()->user()->can('sale.history.view')) {
            abort(Response::HTTP_FORBIDDEN, 'Sem permissão pra ver histórico de vendas.');
        }

        $businessId = (int) $request->session()->get('user.business_id');

        $items = SaleStageHistory::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $id)
            ->with([
                'action:id,key,label,target_stage_id,side_effect_class,event_class',
                'fromStage:id,key,name,color',
                'toStage:id,key,name,color',
            ])
            ->orderByDesc('executed_at')
            ->limit(200)
            ->get();

        // user_id → name resolve manual (sem relationship em SaleStageHistory pra
        // evitar dependência circular com User; query 1+1 controlada)
        $userIds = $items->pluck('user_id')->filter()->unique()->values();
        $userNames = \DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('username', 'id')
            ->toArray();

        $payload = $items->map(function (SaleStageHistory $h) use ($userNames): array {
            return [
                'id' => $h->id,
                'executed_at' => $h->executed_at?->toIso8601String(),
                'user' => [
                    'id' => $h->user_id,
                    'name' => $h->user_id ? ($userNames[$h->user_id] ?? null) : null,
                ],
                'action' => $h->action ? [
                    'key' => $h->action->key,
                    'label' => $h->action->label,
                    'has_side_effect' => ! empty($h->action->side_effect_class),
                    'has_event' => ! empty($h->action->event_class),
                ] : null,
                'from_stage' => $h->fromStage ? [
                    'key' => $h->fromStage->key,
                    'name' => $h->fromStage->name,
                    'color' => $h->fromStage->color,
                ] : null,
                'to_stage' => $h->toStage ? [
                    'key' => $h->toStage->key,
                    'name' => $h->toStage->name,
                    'color' => $h->toStage->color,
                ] : null,
                'payload' => $h->payload_snapshot,
            ];
        });

        return response()->json([
            'transaction_id' => $id,
            'count' => $items->count(),
            'items' => $payload,
        ]);
    }

    /**
     * GET /api/sells/{id}/timeline-unified
     *
     * Agrega 5 sources num único stream cronológico reverso:
     *   1. fsm_transition (sale_stage_history)
     *   2. payment (transaction_payments)
     *   3. activity (activity_log via Spatie/Activitylog)
     *   4. comment (sale_item_comments — se tabela existir)
     *   5. audit (audit_log — se tabela existir)
     *
     * Multi-tenant Tier 0: business_id em TODA query (defense-in-depth).
     */
    public function timelineUnified(Request $request, int $id): JsonResponse
    {
        if (! auth()->check()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if (! auth()->user()->can('sale.history.view')) {
            abort(Response::HTTP_FORBIDDEN, 'Sem permissão pra ver histórico de vendas.');
        }

        $businessId = (int) $request->session()->get('user.business_id');

        // Multi-tenant guard antes de qualquer agregação — Transaction must exist + same biz + type=sell.
        $venda = Transaction::query()
            ->where('business_id', $businessId)
            ->where('id', $id)
            ->where('type', 'sell')
            ->first();

        if (! $venda) {
            return response()->json([
                'message' => 'Venda não encontrada',
                'transaction_id' => $id,
                'count' => 0,
                'events' => [],
            ], 404);
        }

        $events = collect();

        // ── 1. FSM transitions ─────────────────────────────────────────
        $fsm = SaleStageHistory::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $id)
            ->with([
                'action:id,key,label,target_stage_id',
                'fromStage:id,key,name,color',
                'toStage:id,key,name,color',
            ])
            ->orderByDesc('executed_at')
            ->limit(100)
            ->get();

        foreach ($fsm as $h) {
            $from = $h->fromStage?->name;
            $to = $h->toStage?->name ?? '—';
            $title = $from
                ? "{$from} → {$to}"
                : ($h->action?->label ?? 'Pipeline iniciado');

            $events->push([
                'type' => 'fsm_transition',
                'occurred_at' => $h->executed_at?->toIso8601String(),
                'user_id' => $h->user_id,
                'icon' => 'ArrowRight',
                'tone' => $this->mapStageToTone($h->toStage?->color),
                'title' => $title,
                'description' => $h->action?->label ?? null,
                'payload' => [
                    'history_id' => $h->id,
                    'from_stage' => $from,
                    'to_stage' => $to,
                    'action_key' => $h->action?->key,
                    'motivo' => is_array($h->payload_snapshot)
                        ? ($h->payload_snapshot['motivo'] ?? null)
                        : null,
                ],
            ]);
        }

        // ── 2. Payments ────────────────────────────────────────────────
        $payments = TransactionPayment::query()
            ->where('business_id', $businessId)
            ->where('transaction_id', $id)
            ->orderByDesc('paid_on')
            ->limit(100)
            ->get();

        foreach ($payments as $p) {
            $events->push([
                'type' => 'payment',
                'occurred_at' => $p->paid_on
                    ? (string) $p->paid_on
                    : ($p->created_at?->toIso8601String()),
                'user_id' => $p->created_by ?? null,
                'icon' => 'CreditCard',
                'tone' => 'green',
                'title' => sprintf('Pagamento R$ %s', number_format((float) $p->amount, 2, ',', '.')),
                'description' => $this->humanizePaymentMethod((string) ($p->method ?? '')),
                'payload' => [
                    'payment_id' => $p->id,
                    'amount' => (float) $p->amount,
                    'method' => $p->method,
                    'is_return' => (bool) ($p->is_return ?? false),
                ],
            ]);
        }

        // ── 3. Activity log (Spatie/Activitylog) ───────────────────────
        try {
            $activities = Activity::query()
                ->where('subject_type', \App\Transaction::class)
                ->where('subject_id', $id)
                ->where(function ($q) use ($businessId) {
                    // business_id pode estar nullable em logs antigos — defense-in-depth:
                    // aceita activity sem business_id (legacy) OU exatamente nosso biz.
                    $q->where('business_id', $businessId)
                        ->orWhereNull('business_id');
                })
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();

            foreach ($activities as $act) {
                $events->push([
                    'type' => 'activity',
                    'occurred_at' => $act->created_at?->toIso8601String(),
                    'user_id' => $act->causer_id,
                    'icon' => 'FileText',
                    'tone' => 'neutral',
                    'title' => $this->humanizeActivityDescription((string) $act->description),
                    'description' => $act->log_name ?: null,
                    'payload' => [
                        'activity_id' => $act->id,
                        'log_name' => $act->log_name,
                        'event' => $act->event ?? null,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // Skip silenciosamente se schema activity_log diferir do esperado.
        }

        // ── 4. Comments (sale_item_comments — se tabela existir) ───────
        if (\Schema::hasTable('sale_item_comments')) {
            try {
                $comments = \DB::table('sale_item_comments')
                    ->where('business_id', $businessId)
                    ->where('transaction_id', $id)
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get();

                foreach ($comments as $c) {
                    $events->push([
                        'type' => 'comment',
                        'occurred_at' => isset($c->created_at) ? (string) $c->created_at : null,
                        'user_id' => $c->user_id ?? null,
                        'icon' => 'MessageSquare',
                        'tone' => 'amber',
                        'title' => 'Comentário',
                        'description' => isset($c->body) ? mb_substr((string) $c->body, 0, 140) : null,
                        'payload' => [
                            'comment_id' => $c->id ?? null,
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                // Skip silenciosamente.
            }
        }

        // ── 5. Audit log (audit_log — se tabela existir) ───────────────
        if (\Schema::hasTable('audit_log')) {
            try {
                $audits = \DB::table('audit_log')
                    ->where('business_id', $businessId)
                    ->where('entity_id', $id)
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get();

                foreach ($audits as $a) {
                    $events->push([
                        'type' => 'audit',
                        'occurred_at' => isset($a->created_at) ? (string) $a->created_at : null,
                        'user_id' => $a->user_id ?? null,
                        'icon' => 'ShieldCheck',
                        'tone' => 'red',
                        'title' => isset($a->action) ? (string) $a->action : 'Auditoria',
                        'description' => isset($a->description) ? (string) $a->description : null,
                        'payload' => [
                            'audit_id' => $a->id ?? null,
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                // Skip silenciosamente.
            }
        }

        // Ordenar cronológico REVERSO + limit 100.
        $sorted = $events
            ->sortByDesc(fn ($e) => $e['occurred_at'] ?? '')
            ->take(100)
            ->values();

        // Resolver users em batch (1 query).
        $userIds = $sorted->pluck('user_id')->filter()->unique()->values();
        $userNames = \DB::table('users')
            ->whereIn('id', $userIds)
            ->select(['id', 'username', 'first_name', 'last_name'])
            ->get()
            ->keyBy('id');

        $finalEvents = $sorted->map(function (array $e) use ($userNames): array {
            $uid = $e['user_id'] ?? null;
            $u = $uid ? ($userNames[$uid] ?? null) : null;
            $name = $u
                ? trim(((string) ($u->first_name ?? '')).' '.((string) ($u->last_name ?? ''))) ?: (string) ($u->username ?? '')
                : null;
            $abbr = $name ? $this->makeAbbr((string) $name) : null;

            return [
                'type' => $e['type'],
                'occurred_at' => $e['occurred_at'],
                'user' => $uid ? [
                    'id' => (int) $uid,
                    'name' => $name,
                    'abbr' => $abbr,
                ] : null,
                'icon' => $e['icon'],
                'tone' => $e['tone'],
                'title' => $e['title'],
                'description' => $e['description'],
                'payload' => $e['payload'],
            ];
        });

        return response()->json([
            'transaction_id' => $id,
            'count' => $finalEvents->count(),
            'events' => $finalEvents->values(),
        ]);
    }

    /**
     * Map stage color (sale_process_stages.color) → tone do unified timeline.
     */
    private function mapStageToTone(?string $color): string
    {
        return match ($color) {
            'green', 'emerald' => 'green',
            'red' => 'red',
            'amber', 'orange' => 'amber',
            'blue', 'cyan', 'indigo', 'violet' => 'blue',
            default => 'neutral',
        };
    }

    /**
     * Humaniza method do TransactionPayment (cash → "Dinheiro" etc).
     */
    private function humanizePaymentMethod(string $method): ?string
    {
        $map = [
            'cash' => 'Dinheiro',
            'card' => 'Cartão',
            'cheque' => 'Cheque',
            'bank_transfer' => 'Transferência',
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'credit_card' => 'Cartão de Crédito',
            'debit_card' => 'Cartão de Débito',
        ];

        return $map[$method] ?? ($method ?: null);
    }

    /**
     * Humaniza activity_log.description (snake_case → "Snake Case").
     */
    private function humanizeActivityDescription(string $desc): string
    {
        if ($desc === '') {
            return 'Atividade';
        }

        return ucfirst(str_replace('_', ' ', $desc));
    }

    /**
     * Abbreviação 2 letras (Wagner Rosa → WR).
     */
    private function makeAbbr(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) === 0) {
            return '?';
        }
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
    }
}
