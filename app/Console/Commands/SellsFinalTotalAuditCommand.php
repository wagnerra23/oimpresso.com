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
 *   - Wagner sessão 2026-05-28 "blusa R$ [redacted Tier 0] vira R$ [redacted Tier 0]+"
 *   - ADR 0093 (multi-tenant Tier 0 — scope obrigatório por --business)
 */
class SellsFinalTotalAuditCommand extends Command
{
    /**
     * Sintaxe:
     *   php artisan sells:final-total-audit --business=4
     *   php artisan sells:final-total-audit --business=4 --since=2026-05-01
     *   php artisan sells:final-total-audit --business=4 --ratio=5
     *   php artisan sells:final-total-audit --business=4 --apply --transaction=69293
     */
    protected $signature = 'sells:final-total-audit
        {--business= : ID do business (OBRIGATÓRIO — Tier 0 ADR 0093 scope)}
        {--since= : Data ISO (YYYY-MM-DD) a partir de quando auditar (default: últimos 30d)}
        {--ratio=5 : Razão final_total / esperado considerada suspeita (default 5x)}
        {--apply : Aplica correção (default DRY-RUN). Exige --transaction.}
        {--transaction= : ID da transaction única a corrigir (apply requer este)}';

    protected $description = 'Audita transactions.final_total corrompidas por bug num_uf histórico (DRY-RUN por padrão).';

    public function handle(): int
    {
        $business = (int) $this->option('business');
        if ($business <= 0) {
            $this->error('--business obrigatório (ADR 0093 Tier 0 multi-tenant scope).');

            return self::FAILURE;
        }

        $since = $this->option('since') ?: now()->subDays(30)->format('Y-m-d');
        $ratio = (float) ($this->option('ratio') ?: 5);

        if (! $this->option('apply')) {
            return $this->runDryRun($business, $since, $ratio);
        }

        $tid = (int) $this->option('transaction');
        if ($tid <= 0) {
            $this->error('--apply exige --transaction=ID específico (correção per-row, nunca em massa).');

            return self::FAILURE;
        }

        return $this->runApply($business, $tid);
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
}
