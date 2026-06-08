<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bridge Expense (core UltimatePOS) → fin_titulos AP (Modules/Financeiro).
 *
 * Wagner 2026-05-20 Fase 5 deprecação legacy. Após F1 (esconder dropdowns)
 * e F2 (redirects 301), os títulos pagar do Financeiro precisam refletir
 * TODAS as despesas históricas do business — não só vendas/compras que o
 * TransactionObserver já cobre desde Onda 2.
 *
 * Eliana opera com supervisão Wagner — pattern idempotente, --dry obrigatório
 * antes de aplicar, --business=ID obrigatório (Tier 0 IRREVOGÁVEL ADR 0093).
 *
 * Estratégia (Wagner aprovou 2026-05-21):
 *  - Itera transactions WHERE business_id=? AND type='expense' AND deleted_at IS NULL
 *  - Pra cada uma, INSERT idempotente em fin_titulos via UNIQUE
 *    (business_id, origem, origem_id, parcela_numero) — ADR TECH-0001
 *  - Mapeia payment_status → status fin_titulo:
 *      'paid'    → 'quitado'  (valor_aberto = 0)
 *      'partial' → 'parcial'  (valor_aberto = total_remaining_amount)
 *      'due'     → 'aberto'   (valor_aberto = final_total)
 *  - plano_conta_id default = '5.1.99.999 Despesas (a classificar)' (mesma
 *    convenção do BackfillPlanoContaCommand). Eliana reatribui via UI depois.
 *  - origem = 'despesa' (já no ENUM da migration)
 *  - Cross-link `#E-{txId}` pra FinCrossLinkify (frontend) renderizar pill
 *    clicável de volta pro `/expenses/{id}` legacy.
 *
 * Diferença vs sell/purchase: expense type GERA fin_titulo INCLUSIVE quando
 * payment_status='paid' (TituloAutoService cancela paid em sell/purchase
 * porque "pagou no caixa, não vira título"). DRE Eliana precisa ver toda
 * despesa histórica, paga ou não — então criamos como 'quitado'.
 *
 * Uso:
 *   php artisan financeiro:bridge-expense-to-titulos --business=4 --dry
 *   php artisan financeiro:bridge-expense-to-titulos --business=4 --since=2026-01-01 --limit=500
 *   php artisan financeiro:bridge-expense-to-titulos --business=4
 *
 * Idempotente: re-rodar só toca transactions sem fin_titulo correspondente
 * (UNIQUE blocked). Safe pra cron/CI/re-run após erro.
 */
class BridgeExpenseToTitulosCommand extends Command
{
    protected $signature = 'financeiro:bridge-expense-to-titulos
        {--business= : ID do business (obrigatório — Tier 0 IRREVOGÁVEL)}
        {--since= : Data mínima YYYY-MM-DD da transaction (opcional)}
        {--limit= : Máximo de transactions a processar nesta rodada (opcional)}
        {--dry : Mostra o que vai fazer sem aplicar}
        {--detail : Lista cada transaction processada (verbose mode canon)}';

    protected $description = 'Bridge transactions tipo expense (core) → fin_titulos AP (Financeiro). Idempotente.';

    private const CODIGO_DESPESA_ACL = '5.1.99.999';
    private const NOME_DESPESA_ACL = 'Despesas (a classificar)';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        $dry = (bool) $this->option('dry');
        $detail = (bool) $this->option('detail');
        $since = $this->option('since');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($businessId <= 0) {
            $this->error('--business=ID obrigatório (Tier 0 IRREVOGÁVEL — ADR 0093)');

            return self::FAILURE;
        }

        if ($since !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $since)) {
            $this->error('--since deve ser YYYY-MM-DD (ex: 2026-01-01)');

            return self::FAILURE;
        }

        $this->info(($dry ? '[DRY-RUN] ' : '')."Bridge expense→fin_titulos em business={$businessId}");
        if ($since) {
            $this->line("  Filtro --since={$since}");
        }
        if ($limit) {
            $this->line("  Filtro --limit={$limit}");
        }

        // (1) Garantir conta fallback "Despesas (a classificar)".
        $planoContaId = $this->ensureContaAClassificar($businessId, $dry);

        if ($dry && $planoContaId === 0) {
            $this->line('  → conta "Despesas (a classificar)" seria criada (não existe ainda).');
        }

        // (2) Query transactions expense ainda não bridged.
        $query = DB::table('transactions as t')
            ->where('t.business_id', $businessId)
            ->where('t.type', 'expense')
            ->whereNull('t.deleted_at')
            ->leftJoin('fin_titulos as ft', function ($join) {
                $join->on('ft.origem_id', '=', 't.id')
                    ->where('ft.origem', '=', 'despesa')
                    ->whereNull('ft.parcela_numero');
            })
            ->whereNull('ft.id')
            ->select(
                't.id',
                't.business_id',
                't.final_total',
                't.total_remaining_amount',
                't.transaction_date',
                't.payment_status',
                't.contact_id',
                't.expense_category_id',
                't.additional_notes',
                't.created_by',
                't.invoice_no',
            );

        if ($since) {
            $query->where('t.transaction_date', '>=', $since.' 00:00:00');
        }

        $query->orderBy('t.transaction_date', 'asc')->orderBy('t.id', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        $pending = $query->get();
        $total = $pending->count();

        $this->line("  Transactions tipo expense PENDENTES de bridge: {$total}");

        if ($total === 0) {
            $this->info('Nada a fazer — todas expenses já estão bridged.');

            return self::SUCCESS;
        }

        if ($dry) {
            if ($detail) {
                foreach ($pending as $tx) {
                    $this->line(sprintf(
                        '    tx#%d %s R$ %s status=%s contato=%d',
                        $tx->id,
                        substr((string) $tx->transaction_date, 0, 10),
                        number_format((float) $tx->final_total, 2, ',', '.'),
                        $tx->payment_status ?? 'due',
                        (int) ($tx->contact_id ?? 0)
                    ));
                }
            }

            $somaTotal = (float) $pending->sum('final_total');
            $this->info('[DRY-RUN] '.$total.' fin_titulos seriam criados, valor R$ '.number_format($somaTotal, 2, ',', '.'));
            $this->info('[DRY-RUN] Re-rode sem --dry pra aplicar.');

            return self::SUCCESS;
        }

        // (3) Insert batch via transaction (rollback completo se falhar metade).
        $inserted = 0;
        $skipped = 0;

        DB::transaction(function () use ($pending, $businessId, $planoContaId, $detail, &$inserted, &$skipped) {
            foreach ($pending as $tx) {
                $row = $this->montarLinha($tx, $businessId, $planoContaId);

                try {
                    // insertOrIgnore retorna 1 se inseriu, 0 se UNIQUE bloqueou.
                    $r = DB::table('fin_titulos')->insertOrIgnore($row);

                    if ($r === 1) {
                        $inserted++;
                        if ($detail) {
                            $this->line(sprintf('  ✓ tx#%d → fin_titulo (R$ %s)', $tx->id, number_format((float) $tx->final_total, 2, ',', '.')));
                        }
                    } else {
                        $skipped++;
                        if ($detail) {
                            $this->line(sprintf('  ⊘ tx#%d já tinha fin_titulo (skip UNIQUE)', $tx->id));
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error(sprintf('  ✗ tx#%d FALHOU: %s', $tx->id, $e->getMessage()));
                    throw $e; // bubble up → rollback batch inteiro
                }
            }
        });

        $this->info("✓ Bridge concluído em business={$businessId}");
        $this->info("  Inseridos: {$inserted}");
        $this->info("  Skipped (já existiam): {$skipped}");
        $this->info('  Eliana pode reatribuir plano_conta_id correto via /financeiro/plano-contas');

        return self::SUCCESS;
    }

    /**
     * Monta o array de INSERT pra fin_titulos a partir de uma transaction.
     */
    private function montarLinha(object $tx, int $businessId, int $planoContaId): array
    {
        $valorTotal = (float) ($tx->final_total ?? 0.0);
        $valorRemaining = (float) ($tx->total_remaining_amount ?? $tx->final_total ?? 0.0);
        $paymentStatus = (string) ($tx->payment_status ?? 'due');

        $status = match ($paymentStatus) {
            'paid' => 'quitado',
            'partial' => 'parcial',
            default => 'aberto',
        };

        $valorAberto = match ($status) {
            'quitado' => 0.0,
            'parcial' => $valorRemaining,
            default => $valorTotal,
        };

        $emissao = $tx->transaction_date
            ? Carbon::parse($tx->transaction_date)->toDateString()
            : now()->toDateString();
        $competenciaMes = $tx->transaction_date
            ? Carbon::parse($tx->transaction_date)->format('Y-m')
            : now()->format('Y-m');

        $crossLink = sprintf('#E-%d', (int) $tx->id);
        $clienteDescricao = $crossLink; // expense raramente tem fornecedor nomeado

        return [
            'business_id' => $businessId,
            'numero' => 'EXP-'.$tx->id,
            'tipo' => 'pagar',
            'status' => $status,
            'cliente_id' => $tx->contact_id ? (int) $tx->contact_id : null,
            'cliente_descricao' => $clienteDescricao,
            'valor_total' => $valorTotal,
            'valor_aberto' => $valorAberto,
            'moeda' => 'BRL',
            'emissao' => $emissao,
            'vencimento' => $emissao, // despesa sem due_date explícito = mesmo dia
            'competencia_mes' => $competenciaMes,
            'origem' => 'despesa',
            'origem_id' => (int) $tx->id,
            'parcela_numero' => null,
            'parcela_total' => null,
            'titulo_pai_id' => null,
            'plano_conta_id' => $planoContaId > 0 ? $planoContaId : null,
            'categoria_id' => null,
            'observacoes' => $tx->additional_notes ?: null,
            'metadata' => json_encode([
                'bridged_from' => 'core_transaction',
                'transaction_invoice_no' => $tx->invoice_no,
                'expense_category_id_legacy' => $tx->expense_category_id ? (int) $tx->expense_category_id : null,
                'cross_link' => $crossLink,
            ], JSON_UNESCAPED_UNICODE),
            'created_by' => (int) ($tx->created_by ?? 1),
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Cria a conta "Despesas (a classificar)" se não existir. Retorna o id
     * (ou 0 em dry-run quando a conta ainda não existe).
     *
     * Idempotente — mesmo pattern do BackfillPlanoContaCommand.
     */
    private function ensureContaAClassificar(int $businessId, bool $dry): int
    {
        $existing = DB::table('fin_planos_conta')
            ->where('business_id', $businessId)
            ->where('codigo', self::CODIGO_DESPESA_ACL)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        if ($dry) {
            return 0;
        }

        $parentId = DB::table('fin_planos_conta')
            ->where('business_id', $businessId)
            ->where('codigo', '5.1') // DESPESAS OPERACIONAIS
            ->value('id');

        $id = DB::table('fin_planos_conta')->insertGetId([
            'business_id' => $businessId,
            'codigo' => self::CODIGO_DESPESA_ACL,
            'nome' => self::NOME_DESPESA_ACL,
            'tipo' => 'despesa',
            'nivel' => 4,
            'parent_id' => $parentId,
            'natureza' => 'debito',
            'aceita_lancamento' => true,
            'protegido' => false,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line("  + Conta criada: ".self::CODIGO_DESPESA_ACL." \"".self::NOME_DESPESA_ACL."\" (id={$id})");

        return (int) $id;
    }
}
