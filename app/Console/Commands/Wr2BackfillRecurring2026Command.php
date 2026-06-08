<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Backfill recorrência 2026 biz=1 WR2.
 *
 * Escopo (Wagner 2026-06-08):
 *   - Desconsiderar CONTRATO Firebird (incorreto)
 *   - Títulos a receber jun/2026 = base das assinaturas (já feito na migração 07/jun)
 *   - billing_anchor_date = 2025-12-30
 *   - Recorrência mensal 2026 inteiro
 *   - Reflexos em fin_titulos + cobrancas
 *   - BOLETOS a receber Firebird → cobrancas (sem remessa)
 *
 * Estado pré-execução (validado 2026-06-08):
 *   - rb_plans biz=1: 161 (109 ativos + 52 cancelados)
 *   - rb_subscriptions biz=1: 161
 *   - rb_invoices biz=1: 3.389 (2024-1351 + 2025-1375 + 2026-jan-jun-663)
 *   - cobrancas biz=1: 0
 *   - fin_titulos plano_conta_id=332 origem=recurring: 3.682 (mas origem_id apontando p/ subscription_id em vez de rb_invoice.id — bug 07/jun)
 *
 * Etapas:
 *   0 = Fix origem_id em fin_titulos origem=recurring (apontar invoice.id correto)
 *   1 = UPDATE billing_anchor_date=2025-12-30 nos 109 ativos
 *   2 = INSERT rb_invoices jul-dez/2026 + fin_titulos correspondentes (~654 cada)
 *   3 = INSERT cobrancas pra TODAS rb_invoices 2026 jan-dez (~1.317)
 *   4 = INSERT cobrancas adicionais de BOLETOS Firebird vivos (~1.282) — exige SQL pré-gerado
 */
class Wr2BackfillRecurring2026Command extends Command
{
    protected $signature = 'wr2:backfill-recurring-2026
        {--etapa= : Etapa específica (0..4) ou "all"}
        {--execute : Aplica de verdade (default: dry-run)}
        {--biz=1 : Business ID (default: 1 = WR2)}
        {--firebird-sql= : Caminho pro SQL pré-gerado dos BOLETOS Firebird (etapa 4)}';

    protected $description = 'Backfill 2026 recorrência WR2 biz=1: assinaturas+invoices+cobrancas+vínculos (idempotente, dry-run por padrão)';

    public function handle(): int
    {
        $bizId   = (int) $this->option('biz');
        $execute = (bool) $this->option('execute');
        $etapa   = $this->option('etapa');

        if ($bizId !== 1) {
            $this->error("Tier 0: comando hardcoded biz=1 (WR2). Use outro pra outros businesses.");
            return self::FAILURE;
        }

        $modo = $execute ? 'EXECUTE (vai aplicar)' : 'DRY-RUN (só simula)';
        $this->info("=== wr2:backfill-recurring-2026 — biz={$bizId} — {$modo} ===");

        $etapas = $etapa === 'all'
            ? [0, 1, 2, 3, 4]
            : (is_numeric($etapa) ? [(int) $etapa] : null);

        if (!$etapas) {
            $this->error("Use --etapa=0|1|2|3|4 ou --etapa=all");
            return self::FAILURE;
        }

        foreach ($etapas as $e) {
            $this->line("");
            $this->info(">>> ETAPA {$e}");
            $method = "etapa{$e}";
            $res = $this->{$method}($bizId, $execute);
            if (!$res) {
                $this->error("ETAPA {$e} FALHOU — abortando.");
                return self::FAILURE;
            }
        }

        $this->line("");
        $this->info("=== OK ===");
        return self::SUCCESS;
    }

    /**
     * Etapa 0 — Fix origem_id em fin_titulos origem='recurring'.
     *
     * Bug 07/jun: origem_id ficou = subscription_id em vez de rb_invoice.id.
     * Reconcilia via numero_documento (formato RB-{sub}-{YYYY-MM}-L{fin_titulo_id}).
     */
    protected function etapa0(int $bizId, bool $execute): bool
    {
        $countErrado = DB::select("
            SELECT COUNT(*) AS qtd
            FROM fin_titulos t
            INNER JOIN rb_invoices i ON i.numero_documento = CONCAT(
                'RB-', (SELECT id FROM rb_subscriptions WHERE contact_id=t.cliente_id AND business_id=? AND status IN ('active','canceled') ORDER BY start_date LIMIT 1),
                '-', DATE_FORMAT(t.vencimento, '%Y-%m'),
                '-L', t.id
            )
            WHERE t.business_id=?
              AND t.plano_conta_id=332
              AND t.origem='recurring'
              AND (t.origem_id IS NULL OR t.origem_id != i.id)
        ", [$bizId, $bizId])[0]->qtd ?? 0;

        $this->line("  fin_titulos origem=recurring com origem_id incorreto: {$countErrado}");

        if ($countErrado === 0) {
            $this->info("  ✅ Nada a corrigir.");
            return true;
        }

        if (!$execute) {
            $this->warn("  DRY-RUN — não atualiza. Use --execute pra aplicar.");
            return true;
        }

        $affected = DB::statement("
            UPDATE fin_titulos t
            INNER JOIN rb_invoices i ON i.numero_documento = CONCAT(
                'RB-', (SELECT id FROM rb_subscriptions WHERE contact_id=t.cliente_id AND business_id=? AND status IN ('active','canceled') ORDER BY start_date LIMIT 1),
                '-', DATE_FORMAT(t.vencimento, '%Y-%m'),
                '-L', t.id
            )
            SET t.origem_id = i.id,
                t.updated_at = NOW()
            WHERE t.business_id=?
              AND t.plano_conta_id=332
              AND t.origem='recurring'
              AND (t.origem_id IS NULL OR t.origem_id != i.id)
        ", [$bizId, $bizId]);

        $this->info("  ✅ UPDATE aplicado.");
        return true;
    }

    /**
     * Etapa 1 — billing_anchor_date = 2025-12-30 nos 109 ativos.
     */
    protected function etapa1(int $bizId, bool $execute): bool
    {
        $count = DB::table('rb_subscriptions')
            ->where('business_id', $bizId)
            ->where('status', 'active')
            ->where('billing_anchor_date', '!=', '2025-12-30')
            ->count();

        $this->line("  rb_subscriptions ativos com billing_anchor != 2025-12-30: {$count}");

        if ($count === 0) {
            $this->info("  ✅ Já está configurado.");
            return true;
        }

        if (!$execute) {
            $this->warn("  DRY-RUN — não atualiza.");
            return true;
        }

        $updated = DB::table('rb_subscriptions')
            ->where('business_id', $bizId)
            ->where('status', 'active')
            ->where('billing_anchor_date', '!=', '2025-12-30')
            ->update(['billing_anchor_date' => '2025-12-30', 'updated_at' => now()]);

        $this->info("  ✅ {$updated} subscriptions atualizadas.");
        return true;
    }

    /**
     * Etapa 2 — INSERT rb_invoices jul-dez/2026 + fin_titulos correspondentes.
     *
     * Pra cada subscription ativa biz=1, pra cada mês jul..dez/2026:
     *   - vencimento = DATE(2026-MM-DD) onde DD = DAY(start_date)
     *   - valor = plans.valor
     *   - cria rb_invoice com numero_documento padrão
     *   - cria fin_titulo correspondente
     */
    protected function etapa2(int $bizId, bool $execute): bool
    {
        $subs = DB::table('rb_subscriptions as s')
            ->join('rb_plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.business_id', $bizId)
            ->where('s.status', 'active')
            ->select('s.id', 's.contact_id', 's.plan_id', 's.start_date', 's.conta_bancaria_id', 'p.valor')
            ->get();

        $this->line("  subscriptions ativas biz=1: " . count($subs));

        $meses = ['2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'];
        $totalInvoices = 0;
        $totalFin = 0;
        $skipInvoices = 0;
        $skipFin = 0;

        foreach ($subs as $sub) {
            $dia = (int) date('d', strtotime($sub->start_date));
            if ($dia < 1 || $dia > 28) $dia = 10; // safety

            foreach ($meses as $mes) {
                $venc = sprintf('%s-%02d', $mes, $dia);

                // Idempotência rb_invoice — checa via numero_documento prefixo (sem fin_titulo_id ainda)
                $jaTemInvoice = DB::table('rb_invoices')
                    ->where('subscription_id', $sub->id)
                    ->whereRaw('DATE_FORMAT(vencimento, "%Y-%m") = ?', [$mes])
                    ->exists();

                if ($jaTemInvoice) {
                    $skipInvoices++;
                    continue;
                }

                if (!$execute) {
                    $totalInvoices++;
                    $totalFin++;
                    continue;
                }

                // Cria fin_titulo PRIMEIRO (pq numero_documento referencia seu ID)
                $numeroFin = sprintf('RB-AUTO-%d-%s', $sub->id, $mes);
                $legacyFin = sprintf('rb-auto-%d-%s', $sub->id, $mes);

                $finId = DB::table('fin_titulos')->insertGetId([
                    'business_id'    => $bizId,
                    'numero'         => $numeroFin,
                    'legacy_id'      => $legacyFin,
                    'tipo'           => 'receber',
                    'status'         => 'aberto',
                    'cliente_id'     => $sub->contact_id,
                    'valor_total'    => $sub->valor,
                    'valor_aberto'   => $sub->valor,
                    'moeda'          => 'BRL',
                    'emissao'        => $venc,
                    'vencimento'     => $venc,
                    'conta_bancaria_id' => $sub->conta_bancaria_id,
                    'competencia_mes' => $mes,
                    'origem'         => 'recurring',
                    'origem_id'      => null, // será preenchido após invoice insert
                    'plano_conta_id' => 332,
                    'observacoes'    => sprintf('MENSALIDADE REFERENTE AO MÊS DE %s', $mes),
                    'metadata'       => json_encode([
                        'source' => 'wr2-backfill-2026',
                        'subscription_id' => $sub->id,
                        'cycle_month' => $mes,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_by'     => 1, // Wagner (user system)
                    'updated_by'     => 1,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $totalFin++;

                // Agora cria rb_invoice com numero_documento referenciando fin_titulo.id
                $numeroDoc = sprintf('RB-%d-%s-L%d', $sub->id, $mes, $finId);
                $invoiceId = DB::table('rb_invoices')->insertGetId([
                    'business_id'      => $bizId,
                    'subscription_id'  => $sub->id,
                    'contact_id'       => $sub->contact_id,
                    'numero_documento' => $numeroDoc,
                    'valor'            => $sub->valor,
                    'status'           => 'open',
                    'vencimento'       => $venc,
                    'conta_bancaria_id' => $sub->conta_bancaria_id,
                    'metadata'         => json_encode([
                        'source' => 'wr2-backfill-2026',
                        'cycle_month' => $mes,
                        'fin_titulo_id' => $finId,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $totalInvoices++;

                // Atualiza fin_titulo apontando origem_id pra invoice criada
                DB::table('fin_titulos')->where('id', $finId)->update([
                    'origem_id' => $invoiceId,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($skipInvoices > 0) {
            $this->line("  skipped (já existem): {$skipInvoices}");
        }

        if ($execute) {
            $this->info("  ✅ {$totalInvoices} rb_invoices + {$totalFin} fin_titulos inseridos.");
        } else {
            $this->warn("  DRY-RUN: criaria ~{$totalInvoices} rb_invoices + ~{$totalFin} fin_titulos.");
        }

        return true;
    }

    /**
     * Etapa 3 — INSERT cobrancas pra TODAS rb_invoices 2026 (jan-dez).
     *
     * 1 cobranca por rb_invoice, idempotente via idempotency_key.
     */
    protected function etapa3(int $bizId, bool $execute): bool
    {
        $invoices = DB::table('rb_invoices as i')
            ->join('rb_subscriptions as s', 's.id', '=', 'i.subscription_id')
            ->leftJoin('contacts as c', 'c.id', '=', 's.contact_id')
            ->where('s.business_id', $bizId)
            ->whereRaw('YEAR(i.vencimento) = 2026')
            ->select(
                'i.id as invoice_id',
                'i.subscription_id',
                'i.contact_id',
                'i.valor',
                'i.vencimento',
                'i.status as inv_status',
                'i.numero_documento',
                'c.name as payer_name',
                'c.tax_number as payer_cpf_cnpj',
                'c.email as payer_email'
            )
            ->get();

        $this->line("  rb_invoices 2026 biz=1: " . count($invoices));

        $total = 0;
        $skip = 0;

        foreach ($invoices as $inv) {
            $idemKey = sprintf('wr2-cobranca-2026-inv-%d', $inv->invoice_id);

            $existe = DB::table('cobrancas')
                ->where('business_id', $bizId)
                ->where('idempotency_key', $idemKey)
                ->exists();

            if ($existe) {
                $skip++;
                continue;
            }

            if (!$execute) {
                $total++;
                continue;
            }

            $status = $inv->inv_status === 'paid' ? 'paga' : 'aguardando';

            DB::table('cobrancas')->insert([
                'business_id'    => $bizId,
                'tipo'           => 'boleto',
                'status'         => $status,
                'valor_centavos' => (int) round($inv->valor * 100),
                'vencimento'     => $inv->vencimento,
                'contact_id'     => $inv->contact_id,
                'payer_name'     => $inv->payer_name,
                'payer_cpf_cnpj' => $inv->payer_cpf_cnpj,
                'payer_email'    => $inv->payer_email,
                'descricao'      => sprintf('Mensalidade %s - %s', date('m/Y', strtotime($inv->vencimento)), $inv->numero_documento),
                'idempotency_key' => $idemKey,
                'origem_type'    => 'rb_invoice',
                'origem_id'      => $inv->invoice_id,
                'forma_pagamento' => 'boleto',
                'payload_gateway' => json_encode([
                    'source' => 'wr2-backfill-2026',
                ], JSON_UNESCAPED_UNICODE),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $total++;
        }

        if ($skip > 0) $this->line("  skipped (já existem): {$skip}");

        if ($execute) {
            $this->info("  ✅ {$total} cobrancas inseridas.");
        } else {
            $this->warn("  DRY-RUN: criaria ~{$total} cobrancas.");
        }

        return true;
    }

    /**
     * Etapa 4 — Importar BOLETOS Firebird vivos como cobrancas.
     *
     * Lê SQL pré-gerado por script Python local (lê BANCO_VIVO.FDB).
     * Cada linha = 1 INSERT IGNORE em cobrancas (idempotency_key UNIQUE).
     */
    protected function etapa4(int $bizId, bool $execute): bool
    {
        $sqlPath = $this->option('firebird-sql')
            ?: base_path('scripts/legacy-migration/sql-wr2-pessoas/output/planos-mensalidade/etapa5-cobrancas-firebird-boletos.sql');

        if (!file_exists($sqlPath)) {
            $this->error("  Arquivo SQL não encontrado: {$sqlPath}");
            $this->line("  Gere primeiro: python scripts/legacy-migration/sql-wr2-pessoas/gerar-sql-cobrancas-boletos-firebird.py");
            return false;
        }

        $tam = filesize($sqlPath);
        $this->line("  SQL pré-gerado: {$sqlPath} ({$tam} bytes)");

        // Conta linhas INSERT (sem shell_exec — Hostinger bloqueia)
        $linhas = 0;
        $fh = fopen($sqlPath, 'r');
        while (($line = fgets($fh)) !== false) {
            if (str_starts_with($line, 'INSERT')) $linhas++;
        }
        fclose($fh);
        $this->line("  INSERTs no SQL: {$linhas}");

        if (!$execute) {
            $this->warn("  DRY-RUN — não aplica.");
            return true;
        }

        $sql = file_get_contents($sqlPath);
        DB::unprepared($sql);

        $this->info("  ✅ SQL aplicado.");
        return true;
    }
}
