<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auditoria de `transactions.final_total` corrompido por bug histórico do
 * parser pt-BR `num_uf` (corrigido no PR #1867 — `app/Utils/Util.php@num_uf`).
 *
 * O bug devolvia `num_uf("80.00") = 8000` (ponto interpretado como milhar).
 * Resultado: vendas criadas/editadas com valor en-US no input gravavam
 * `final_total` 10x/100x/1000x maior que o esperado.
 *
 * Casos canon detectados em prod biz=4 (Larissa @ Rota Livre):
 *   - Invoice 17642 (id 69293) — total_before_tax=2788 → final_total=2.500.836 (ratio 931x)
 *   - Invoice 17639 (id 69287) — total_before_tax=79,90 → final_total=0 (discount 100%)
 *
 * Modo DRY-RUN por padrão — APENAS lista candidatos com diagnóstico. Não
 * modifica nada. Para aplicar correção é `--apply` + confirmação explícita
 * + nunca em transação com NFe emitida (chave preenchida).
 *
 * Refs:
 *   - PR #1867 (fix root cause num_uf)
 *   - Wagner sessão 2026-05-28 "blusa R$ 80 vira R$ 800+"
 *   - ADR 0093 (multi-tenant Tier 0 — scope obrigatório por --business)
 */
class SellsFinalTotalAuditCommand extends Command
{
    /**
     * Modos:
     *   final-total : detecta final_total > teto (total_before_tax + tax + shipping) — bug 17642
     *   times-ten   : detecta unit_price ≈ variation.default_sell_price × 10 — bug clássico legacy num_uf
     *                 ("89.9" → str_replace('.','','89.9') = '899' antes do PR #1867)
     *
     * Sintaxe:
     *   php artisan sells:final-total-audit --business=4
     *   php artisan sells:final-total-audit --business=4 --mode=times-ten
     *   php artisan sells:final-total-audit --business=4 --since=2026-05-01
     *   php artisan sells:final-total-audit --business=4 --apply --transaction=69293
     *   php artisan sells:final-total-audit --business=4 --mode=times-ten --apply --transaction=69300
     */
    protected $signature = 'sells:final-total-audit
        {--business= : ID do business (OBRIGATÓRIO — Tier 0 ADR 0093 scope)}
        {--mode=final-total : final-total | times-ten}
        {--since= : Data ISO (YYYY-MM-DD) a partir de quando auditar (default: últimos 30d)}
        {--ratio=5 : Razão final_total / esperado considerada suspeita (default 5x, só mode=final-total)}
        {--apply : Aplica correção (default DRY-RUN). Exige --transaction.}
        {--transaction= : ID da transaction única a corrigir (apply requer este)}';

    protected $description = 'Audita transactions corrompidas por bug num_uf histórico (DRY-RUN por padrão).';

    public function handle(): int
    {
        $business = (int) $this->option('business');
        if ($business <= 0) {
            $this->error('--business obrigatório (ADR 0093 Tier 0 multi-tenant scope).');

            return self::FAILURE;
        }

        $mode = (string) $this->option('mode');
        if (! in_array($mode, ['final-total', 'times-ten'], true)) {
            $this->error("--mode inválido. Aceita: 'final-total', 'times-ten'. Recebido: '{$mode}'.");

            return self::FAILURE;
        }

        $since = $this->option('since') ?: now()->subDays(30)->format('Y-m-d');
        $ratio = (float) ($this->option('ratio') ?: 5);

        if (! $this->option('apply')) {
            return $mode === 'times-ten'
                ? $this->runDryRunTimesTen($business, $since)
                : $this->runDryRun($business, $since, $ratio);
        }

        $tid = (int) $this->option('transaction');
        if ($tid <= 0) {
            $this->error('--apply exige --transaction=ID específico (correção per-row, nunca em massa).');

            return self::FAILURE;
        }

        return $mode === 'times-ten'
            ? $this->runApplyTimesTen($business, $tid)
            : $this->runApply($business, $tid);
    }

    private function runDryRun(int $business, string $since, float $ratio): int
    {
        $this->info("DRY-RUN — business={$business}, since={$since}, ratio_threshold={$ratio}x");
        $this->line('');

        // Heurística: final_total deveria ser ≈ (total_before_tax - discount) + tax + shipping.
        // Quando `discount_type=fixed`, esperado = total_before_tax - discount.
        // Quando `discount_type=percentage`, esperado = total_before_tax * (1 - discount/100).
        // tax e shipping são separados e geralmente 0 em biz=4.
        $rows = DB::select(
            <<<'SQL'
                SELECT
                    t.id,
                    t.invoice_no,
                    t.contact_id,
                    t.created_by,
                    u.username AS created_by_username,
                    t.total_before_tax,
                    t.discount_amount,
                    t.discount_type,
                    t.tax_amount,
                    t.shipping_charges,
                    t.final_total,
                    t.payment_status,
                    t.chave AS nfe_chave,
                    t.status,
                    t.created_at,
                    CASE
                        WHEN t.discount_type = 'percentage' THEN
                            ROUND(t.total_before_tax * (1 - t.discount_amount / 100), 2)
                        ELSE
                            ROUND(t.total_before_tax - t.discount_amount, 2)
                    END AS final_total_esperado
                FROM transactions t
                LEFT JOIN users u ON u.id = t.created_by
                WHERE t.business_id = ?
                  AND t.type = 'sell'
                  AND t.created_at >= ?
                  AND t.total_before_tax > 0
                  AND t.final_total > 0
                ORDER BY t.id DESC
            SQL,
            [$business, $since]
        );

        $suspeitas = [];
        foreach ($rows as $r) {
            $esperado = (float) $r->final_total_esperado;
            $real = (float) $r->final_total;
            $beforeTax = (float) $r->total_before_tax;

            // Heurística A: final_total >> total_before_tax + tax + shipping (impossível
            // matematicamente — desconto não pode AUMENTAR o total). Pega 17642 (931x).
            $extras = (float) $r->tax_amount + (float) $r->shipping_charges;
            $tetoRazoavel = $beforeTax + $extras;
            if ($tetoRazoavel > 0 && $real > $tetoRazoavel * 1.5) {
                $suspeitas[] = ['row' => $r, 'esperado' => $esperado > 0 ? $esperado : $tetoRazoavel, 'razao' => $real / max($tetoRazoavel, 0.01)];

                continue;
            }

            // Heurística B: razão final vs esperado fora do threshold (caso clássico).
            if ($esperado <= 0) {
                continue;
            }
            $razao = $real / $esperado;
            if ($razao >= $ratio || $razao <= (1.0 / $ratio)) {
                $suspeitas[] = ['row' => $r, 'esperado' => $esperado, 'razao' => $razao];
            }
        }

        if (empty($suspeitas)) {
            $this->info('Nenhuma transaction suspeita detectada.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Detectadas %d transactions suspeitas:', count($suspeitas)));
        $this->line('');

        $tableRows = [];
        foreach ($suspeitas as $s) {
            $r = $s['row'];
            $tableRows[] = [
                'id'         => $r->id,
                'invoice'    => $r->invoice_no,
                'created_by' => $r->created_by_username,
                'before_tax' => number_format((float) $r->total_before_tax, 2, ',', '.'),
                'disc'       => $r->discount_amount.' '.($r->discount_type === 'percentage' ? '%' : 'R$'),
                'esperado'   => number_format($s['esperado'], 2, ',', '.'),
                'real'       => number_format((float) $r->final_total, 2, ',', '.'),
                'ratio'      => sprintf('%.1fx', $s['razao']),
                'nfe'        => $r->nfe_chave ? '⚠️ EMITIDA' : 'não',
                'pgto'       => $r->payment_status,
            ];
        }

        $this->table(
            ['id', 'invoice', 'created_by', 'before_tax', 'disc', 'esperado', 'real', 'ratio', 'nfe', 'pgto'],
            $tableRows
        );

        $this->line('');
        $this->warn('Pra aplicar correção numa transaction específica:');
        $this->line('  php artisan sells:final-total-audit --business='.$business.' --apply --transaction=ID');
        $this->line('');
        $this->warn('REGRAS DE SEGURANÇA:');
        $this->line('  • NUNCA aplicar em transaction com NFe emitida (chave preenchida).');
        $this->line('  • SEMPRE confirmar com Larissa/operador antes de corrigir transaction com pagamento != 0.');
        $this->line('  • Validar visualmente o valor "esperado" contra o documento físico/cliente.');

        return self::SUCCESS;
    }

    private function runApply(int $business, int $tid): int
    {
        /** @var Transaction|null $t */
        $t = Transaction::where('business_id', $business)->where('id', $tid)->first();
        if (! $t) {
            $this->error("Transaction {$tid} não pertence a business {$business} (Tier 0 scope).");

            return self::FAILURE;
        }

        if (! empty($t->chave)) {
            $this->error("Transaction {$tid} tem NFe EMITIDA (chave={$t->chave}). Correção bloqueada — anular via SEFAZ antes.");

            return self::FAILURE;
        }

        $totalBeforeTax = (float) $t->total_before_tax;
        $disc = (float) $t->discount_amount;
        $esperado = $t->discount_type === 'percentage'
            ? round($totalBeforeTax * (1 - $disc / 100), 2)
            : round($totalBeforeTax - $disc, 2);

        $real = (float) $t->final_total;

        $this->info("Transaction #{$tid} (invoice {$t->invoice_no}):");
        $this->line('  total_before_tax: '.number_format($totalBeforeTax, 2, ',', '.'));
        $this->line('  discount:         '.$disc.' '.($t->discount_type === 'percentage' ? '%' : 'R$'));
        $this->line('  final_total esperado: '.number_format($esperado, 2, ',', '.'));
        $this->line('  final_total atual:    '.number_format($real, 2, ',', '.'));
        $this->line('  payment_status:   '.$t->payment_status);
        $this->line('');

        if (! $this->confirm("Confirma UPDATE final_total = {$esperado} (era {$real})?", false)) {
            $this->info('Cancelado pelo operador.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($t, $esperado, $real, $business): void {
            // Audit append-only (paridade com pattern UPOS activity_log).
            DB::table('activity_log')->insert([
                'log_name' => 'sells-final-total-audit',
                'description' => 'final_total corrigido via sells:final-total-audit (bug histórico num_uf PR #1867)',
                'subject_type' => 'App\\Transaction',
                'subject_id' => $t->id,
                'causer_type' => null,
                'causer_id' => null,
                'properties' => json_encode([
                    'business_id' => $business,
                    'attributes' => ['final_total' => $esperado],
                    'old' => ['final_total' => $real],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('transactions')->where('id', $t->id)->update([
                'final_total' => $esperado,
                'updated_at' => now(),
            ]);
        });

        $this->info("✅ Transaction {$tid} corrigida. final_total {$real} → {$esperado}.");
        $this->warn('Lembre de revisar payment_status / fin_titulos / commission manualmente se aplicável.');

        return self::SUCCESS;
    }

    /**
     * Modo times-ten: detecta vendas onde TODAS as sell_lines têm
     * `unit_price ≈ variations.default_sell_price × 10` — sinal canônico
     * do bug legacy `num_uf("89.9") = '899'` (str_replace ponto = '' antes de #1867).
     *
     * Filtra apenas vendas onde TODAS as lines têm o multiplicador 10 consistente
     * (evita falsos positivos de promoções/alteração manual de preço onde só 1 line
     * é × 10 e outras não).
     */
    private function runDryRunTimesTen(int $business, string $since): int
    {
        $this->info("DRY-RUN times-ten — business={$business}, since={$since}");
        $this->line('');

        // Subquery: por transaction, contar lines bugadas vs total. Só listar
        // quando 100% das lines estão bugadas (confiança alta no padrão).
        $rows = DB::select(
            <<<'SQL'
                SELECT
                    t.id AS tid,
                    t.invoice_no,
                    t.created_at,
                    u.username AS created_by_username,
                    t.final_total,
                    t.total_before_tax,
                    t.discount_amount,
                    t.discount_type,
                    t.payment_status,
                    t.chave AS nfe_chave,
                    COUNT(sl.id) AS qtd_lines_total,
                    SUM(
                        CASE WHEN pv.default_sell_price > 0
                             AND ABS(sl.unit_price - pv.default_sell_price * 10) / sl.unit_price < 0.05
                        THEN 1 ELSE 0 END
                    ) AS qtd_lines_bugadas
                FROM transactions t
                JOIN transaction_sell_lines sl ON sl.transaction_id = t.id
                JOIN variations pv ON pv.id = sl.variation_id
                LEFT JOIN users u ON u.id = t.created_by
                WHERE t.business_id = ?
                  AND t.type = 'sell'
                  AND t.created_at >= ?
                GROUP BY t.id
                HAVING qtd_lines_bugadas = qtd_lines_total AND qtd_lines_bugadas > 0
                ORDER BY t.id DESC
            SQL,
            [$business, $since]
        );

        if (empty($rows)) {
            $this->info('Nenhuma transaction × 10 detectada.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Detectadas %d transactions com bug × 10:', count($rows)));
        $this->line('');

        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                'id'         => $r->tid,
                'invoice'    => $r->invoice_no,
                'criada'     => $r->created_at,
                'created_by' => $r->created_by_username,
                'final_atual' => number_format((float) $r->final_total, 2, ',', '.'),
                'final_/10'  => number_format(((float) $r->final_total) / 10, 2, ',', '.'),
                'itens'      => $r->qtd_lines_total,
                'nfe'        => $r->nfe_chave ? '⚠️ EMITIDA' : 'não',
                'pgto'       => $r->payment_status ?: '-',
            ];
        }

        $this->table(
            ['id', 'invoice', 'criada', 'created_by', 'final_atual', 'final_/10', 'itens', 'nfe', 'pgto'],
            $tableRows
        );

        $this->line('');
        $this->warn('Pra aplicar correção numa transaction específica:');
        $this->line('  php artisan sells:final-total-audit --business='.$business.' --mode=times-ten --apply --transaction=ID');
        $this->line('');
        $this->warn('REGRAS DE SEGURANÇA:');
        $this->line('  • NUNCA aplicar em transaction com NFe emitida.');
        $this->line('  • SEMPRE confirmar com Larissa antes de transactions com pagamento != null.');
        $this->line('  • Divide TUDO por 10: final_total + total_before_tax + tax_amount + sell_lines (unit_price, unit_price_inc_tax, unit_price_before_discount).');

        return self::SUCCESS;
    }

    private function runApplyTimesTen(int $business, int $tid): int
    {
        /** @var Transaction|null $t */
        $t = Transaction::where('business_id', $business)->where('id', $tid)->first();
        if (! $t) {
            $this->error("Transaction {$tid} não pertence a business {$business} (Tier 0 scope).");

            return self::FAILURE;
        }

        if (! empty($t->chave)) {
            $this->error("Transaction {$tid} tem NFe EMITIDA (chave={$t->chave}). Correção bloqueada — anular via SEFAZ antes.");

            return self::FAILURE;
        }

        // Confirma que TODAS as lines estão × 10 antes de aplicar (defesa em profundidade)
        $checks = DB::select(
            <<<'SQL'
                SELECT
                    COUNT(sl.id) AS total,
                    SUM(
                        CASE WHEN pv.default_sell_price > 0
                             AND ABS(sl.unit_price - pv.default_sell_price * 10) / sl.unit_price < 0.05
                        THEN 1 ELSE 0 END
                    ) AS bugadas
                FROM transaction_sell_lines sl
                JOIN variations pv ON pv.id = sl.variation_id
                WHERE sl.transaction_id = ?
            SQL,
            [$tid]
        );

        $total = (int) ($checks[0]->total ?? 0);
        $bugadas = (int) ($checks[0]->bugadas ?? 0);

        if ($total === 0 || $bugadas !== $total) {
            $this->error("Transaction {$tid} NÃO está com TODAS as lines × 10 ({$bugadas}/{$total} bugadas). Correção bloqueada — pode haver promoção ou edição manual.");

            return self::FAILURE;
        }

        $this->info("Transaction #{$tid} (invoice {$t->invoice_no}):");
        $this->line('  total_before_tax atual: '.number_format((float) $t->total_before_tax, 2, ',', '.').' → '.number_format(((float) $t->total_before_tax) / 10, 2, ',', '.'));
        $this->line('  final_total atual:      '.number_format((float) $t->final_total, 2, ',', '.').' → '.number_format(((float) $t->final_total) / 10, 2, ',', '.'));
        $this->line('  '.$total.' sell_lines serão divididas por 10 (unit_price, unit_price_inc_tax, unit_price_before_discount)');
        $this->line('  payment_status: '.($t->payment_status ?? '-'));
        $this->line('');

        if (! $this->confirm('Confirma dividir TUDO por 10?', false)) {
            $this->info('Cancelado pelo operador.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($t, $business): void {
            $oldTotalBefore = (float) $t->total_before_tax;
            $oldFinalTotal = (float) $t->final_total;
            $oldTax = (float) $t->tax_amount;

            // Audit append-only ANTES do UPDATE
            DB::table('activity_log')->insert([
                'log_name' => 'sells-times-ten-audit',
                'description' => 'unit_price + final_total /= 10 via sells:final-total-audit mode=times-ten (bug histórico num_uf PR #1867)',
                'subject_type' => 'App\\Transaction',
                'subject_id' => $t->id,
                'causer_type' => null,
                'causer_id' => null,
                'properties' => json_encode([
                    'business_id' => $business,
                    'attributes' => [
                        'total_before_tax' => round($oldTotalBefore / 10, 4),
                        'tax_amount' => round($oldTax / 10, 4),
                        'final_total' => round($oldFinalTotal / 10, 4),
                    ],
                    'old' => [
                        'total_before_tax' => $oldTotalBefore,
                        'tax_amount' => $oldTax,
                        'final_total' => $oldFinalTotal,
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Atualiza sell_lines: divide unit_price + unit_price_inc_tax + unit_price_before_discount por 10
            DB::statement(
                <<<'SQL'
                    UPDATE transaction_sell_lines
                    SET unit_price = unit_price / 10,
                        unit_price_inc_tax = unit_price_inc_tax / 10,
                        unit_price_before_discount = unit_price_before_discount / 10,
                        item_tax = item_tax / 10,
                        updated_at = NOW()
                    WHERE transaction_id = ?
                SQL,
                [$t->id]
            );

            // Atualiza transaction: divide totais por 10
            DB::table('transactions')->where('id', $t->id)->update([
                'total_before_tax' => round($oldTotalBefore / 10, 4),
                'tax_amount' => round($oldTax / 10, 4),
                'final_total' => round($oldFinalTotal / 10, 4),
                'updated_at' => now(),
            ]);
        });

        $this->info("✅ Transaction {$tid} corrigida (÷ 10).");
        $this->warn('Lembre de revisar transaction_payments + payment_status + commission manualmente — não foram tocados.');

        return self::SUCCESS;
    }
}
