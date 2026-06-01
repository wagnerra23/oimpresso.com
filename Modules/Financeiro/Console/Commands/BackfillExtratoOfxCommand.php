<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 da [ADR 0236] — backfill OFX -> fin_extrato_lancamentos (tabela canonica).
 *
 * Migra as linhas de fin_bank_statement_lines (upload OFX) PARA
 * fin_extrato_lancamentos, unificando a chave anti-duplicata via external_id
 * prefixado ("ofx:<fitid>"). Tambem preenche external_id="api:<idempotency_key>"
 * nas linhas API existentes (pre-requisito do UNIQUE unificado).
 *
 * PRINCIPIOS (nao-negociaveis):
 *  - --business=ID OBRIGATORIO (Tier 0 IRREVOGAVEL — ADR 0093). Nunca cross-tenant.
 *  - --dry default-seguro: mostra o que faria sem escrever.
 *  - Idempotente: insertOrIgnore + external_id deterministico -> re-rodar e no-op.
 *  - Preserva created_at original das linhas OFX (nao usa now()).
 *  - NAO dropa nem altera fin_bank_statement_lines (read-only vem na flag, drop na Fase 3).
 *
 * MAPEAMENTO OFX -> canonica:
 *  - data_movimento -> data
 *  - tipo enum (credit/debit/...) -> char C/D (pelo SINAL do valor)
 *  - valor 15,4 (com sinal) -> valor 15,2 POSITIVO + tipo C/D (canonica = abs + tipo)
 *  - descricao -> descricao - source_file -> source_file - fitid -> external_id "ofx:".fitid
 *  - status/titulo_id/match_score/conciliado_by/conciliado_at -> preservados (Fase 1)
 *
 * CONTA OFX (DD-2): linhas OFX com conta_bancaria_id NULL recebem uma conta
 * "OFX avulso" generica por business (FK NOT NULL na canonica). Criada idempotente.
 *
 * Uso:
 *   php artisan financeiro:backfill-extrato-ofx --business=1 --dry
 *   php artisan financeiro:backfill-extrato-ofx --business=1 --detail
 *   php artisan financeiro:backfill-extrato-ofx --business=1
 *
 * @see memory/requisitos/Financeiro/PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO.md
 */
class BackfillExtratoOfxCommand extends Command
{
    protected $signature = 'financeiro:backfill-extrato-ofx
        {--business= : ID do business (obrigatorio — Tier 0 IRREVOGAVEL)}
        {--limit= : Maximo de linhas OFX a processar nesta rodada (opcional)}
        {--dry : Mostra o que vai fazer sem aplicar}
        {--detail : Lista cada linha processada}';

    protected $description = 'Backfill linhas OFX (fin_bank_statement_lines) -> fin_extrato_lancamentos (canonica). Idempotente. Fase 2 ADR 0236.';

    private const CHUNK = 500;

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        $dry = (bool) $this->option('dry');
        $detail = (bool) $this->option('detail');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($businessId <= 0) {
            $this->error('--business=ID obrigatorio (Tier 0 IRREVOGAVEL — ADR 0093)');

            return self::FAILURE;
        }

        if (! Schema::hasTable('fin_extrato_lancamentos') || ! Schema::hasColumn('fin_extrato_lancamentos', 'external_id')) {
            $this->error('fin_extrato_lancamentos sem coluna external_id — rode a migration Fase 2 primeiro.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('fin_bank_statement_lines')) {
            $this->error('fin_bank_statement_lines ausente — nada a migrar.');

            return self::FAILURE;
        }

        $this->info(($dry ? '[DRY-RUN] ' : '')."Backfill OFX->extrato canonico em business={$businessId}");

        // (A) Preenche external_id nas linhas API existentes (api:<idempotency_key>).
        $apiPend = DB::table('fin_extrato_lancamentos')
            ->where('business_id', $businessId)
            ->whereNull('external_id')
            ->count();
        $this->line("  Linhas API sem external_id (recebem 'api:<key>'): {$apiPend}");
        if (! $dry && $apiPend > 0) {
            DB::table('fin_extrato_lancamentos')
                ->where('business_id', $businessId)
                ->whereNull('external_id')
                ->update([
                    'external_id' => DB::raw("CONCAT('api:', idempotency_key)"),
                    'origem' => DB::raw("COALESCE(origem, 'api')"),
                ]);
        }

        // (B) Linhas OFX ainda nao migradas (external_id ofx:<fitid> ausente na canonica).
        $jaMigrados = DB::table('fin_extrato_lancamentos')
            ->where('business_id', $businessId)
            ->where('origem', 'ofx')
            ->pluck('external_id')
            ->filter()
            ->all();
        $jaSet = array_flip($jaMigrados);

        $query = DB::table('fin_bank_statement_lines')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('id');
        if ($limit) {
            $query->limit($limit);
        }
        $ofxLinhas = $query->get();

        $contaOfxId = null; // lazy — so cria se precisar.

        $novos = 0;
        $skipped = 0;
        $rows = [];

        foreach ($ofxLinhas as $l) {
            $extId = 'ofx:' . $l->fitid;
            if (isset($jaSet[$extId])) {
                $skipped++;

                continue;
            }

            $contaId = $l->conta_bancaria_id;
            if ($contaId === null) {
                if ($contaOfxId === null) {
                    $contaOfxId = $this->ensureContaOfxAvulso($businessId, $dry);
                }
                $contaId = $contaOfxId;
            }

            $valorRaw = (float) $l->valor;
            $tipoCD = $valorRaw >= 0 ? 'C' : 'D';

            $rows[] = [
                'business_id' => $businessId,
                'conta_bancaria_id' => $contaId !== null ? (int) $contaId : 0,
                'origem' => 'ofx',
                'data' => Carbon::parse($l->data_movimento)->toDateString(),
                'valor' => abs($valorRaw),
                'tipo' => $tipoCD,
                'descricao' => mb_substr((string) $l->descricao, 0, 500),
                'source_file' => $l->source_file,
                'contraparte_documento' => null,
                'contraparte_nome' => null,
                'idempotency_key' => 'ofx-' . $l->fitid,
                'external_id' => $extId,
                'raw_payload' => json_encode(['bridged_from' => 'fin_bank_statement_lines', 'ofx_id' => $l->id, 'fitid' => $l->fitid], JSON_UNESCAPED_UNICODE),
                'status' => $l->status,
                'titulo_id' => $l->titulo_id !== null ? (int) $l->titulo_id : null,
                'match_score' => $l->match_score !== null ? (float) $l->match_score : null,
                'conciliado_by' => $l->conciliado_by ?? null,
                'conciliado_at' => $l->conciliado_at ?? null,
                'created_at' => $l->created_at ?? now(),
                'updated_at' => now(),
            ];
            $novos++;

            if ($detail) {
                $this->line(sprintf('  %s ofx#%d "%s" R$ %s -> %s', $dry ? '[dry]' : 'ok', $l->id, $extId, number_format(abs($valorRaw), 2, ',', '.'), $tipoCD));
            }
        }

        if ($dry) {
            $this->info("[DRY-RUN] Linhas OFX a migrar: {$novos} - ja migradas (skip): {$skipped}");
            if ($contaOfxId === 0) {
                $this->line('  -> conta "OFX avulso" seria criada (ha linha OFX sem conta).');
            }
            $this->info('[DRY-RUN] Re-rode sem --dry pra aplicar.');

            return self::SUCCESS;
        }

        $inserted = 0;
        foreach (array_chunk($rows, self::CHUNK) as $lote) {
            $inserted += DB::table('fin_extrato_lancamentos')->insertOrIgnore($lote);
        }

        $this->info("Backfill concluido em business={$businessId}");
        $this->info("  Inseridos: {$inserted} - skip (ja existiam): {$skipped} - API external_id preenchidos: {$apiPend}");
        $this->info('  fin_bank_statement_lines PRESERVADA (read-only vem com a flag financeiro.extrato_unificado).');

        return self::SUCCESS;
    }

    /**
     * Cria conta "OFX avulso" generica por business pra abrigar linhas OFX sem
     * conta detectada (FK NOT NULL na canonica — DD-2). Idempotente.
     */
    private function ensureContaOfxAvulso(int $businessId, bool $dry): int
    {
        $existing = DB::table('fin_contas_bancarias')
            ->where('business_id', $businessId)
            ->where('banco_codigo', 'OFX')
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        if ($dry) {
            return 0;
        }

        $accountId = DB::table('accounts')->where('business_id', $businessId)->value('id');
        if ($accountId === null) {
            $accountId = DB::table('accounts')->insertGetId([
                'business_id' => $businessId,
                'name' => 'OFX avulso (extrato sem conta)',
                'account_number' => 'OFX-AVULSO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $id = DB::table('fin_contas_bancarias')->insertGetId([
            'business_id' => $businessId,
            'account_id' => $accountId,
            'banco_codigo' => 'OFX',
            'agencia' => '0',
            'carteira' => '-',
            'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — placeholder conta tecnica
            'beneficiario_razao_social' => 'OFX avulso',
            'ativo_para_boleto' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line("  + Conta 'OFX avulso' criada (id={$id}) pra linhas OFX sem conta.");

        return (int) $id;
    }
}
