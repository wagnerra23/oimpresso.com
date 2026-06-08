<?php

namespace App\Console\Commands;

use Eduardokum\LaravelBoleto\Util;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `contacts.cpf_cnpj` a partir de `contacts.tax_number` quando
 * o valor legacy é um CPF (11 dígitos) ou CNPJ (14 dígitos) válido via
 * mod-11 SEFAZ.
 *
 * Migra dados existentes pro canon BR (`cpf_cnpj` restaurado em
 * migration 2026_05_21_140000 — ver memory/sessions/2026-05-21-investigar-campos-br-cliente.md)
 * sem perder o `tax_number` original (preservado pra back-compat).
 *
 * Idempotente:
 *   - Só toca contacts onde `cpf_cnpj IS NULL` (não sobrescreve cadastros
 *     já preenchidos manualmente via UI)
 *   - Re-run produz 0 mudanças
 *
 * LGPD:
 *   - Log JSON em storage/logs/backfill-cpfcnpj-{ts}.json guarda apenas
 *     id + business_id + length do tax_number (NUNCA o valor plain)
 *   - Update direto via DB::table não dispara activity_log do model
 *     (PII out of audit trail — ver Contact::getActivitylogOptions logOnly)
 *
 * Multi-tenant (ADR 0093):
 *   - --business-id permite operar 1 tenant por vez (rollout gradual)
 *   - Sem flag, processa todos os business simultaneamente (safe pois
 *     update é per-row scoped por id)
 *
 * Uso:
 *   php artisan cliente:backfill-cpf-cnpj                        # dry-run, todos
 *   php artisan cliente:backfill-cpf-cnpj --execute              # persiste, todos
 *   php artisan cliente:backfill-cpf-cnpj --business-id=4        # apenas biz=4
 *   php artisan cliente:backfill-cpf-cnpj --execute --limit=100  # teste gradual
 */
class BackfillCpfCnpjCommand extends Command
{
    protected $signature = 'cliente:backfill-cpf-cnpj
        {--execute : Persiste mudanças. Sem flag, opera em dry-run (preview only)}
        {--limit=0 : Limita N contacts elegíveis (0 = sem limite)}
        {--business-id= : Filtra por business_id específico (sem flag = todos)}';

    protected $description = 'Backfill cliente cpf_cnpj a partir de tax_number quando mod-11 SEFAZ válido. Default dry-run. Idempotente.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $limit = (int) $this->option('limit');
        $businessIdOpt = $this->option('business-id');
        $businessId = $businessIdOpt !== null ? (int) $businessIdOpt : null;

        $mode = $execute ? 'EXECUTE' : 'DRY-RUN';
        $this->info("Backfill cliente cpf_cnpj — modo {$mode}");
        if ($businessId !== null) {
            $this->info("Filtro: business_id={$businessId}");
        }
        if ($limit > 0) {
            $this->info("Limite: {$limit} registros");
        }

        // --- Safety report INICIAL (Wagner pediu 2026-05-21 — "verifique antes
        // de matar a informação, acho que antes era cpf_cnpj que eu já usava").
        // Mostra panorama ANTES de qualquer mudança pra confirmar que existing
        // cpf_cnpj NAO sera tocado.
        $scopeQuery = DB::table('contacts');
        if ($businessId !== null) {
            $scopeQuery->where('business_id', $businessId);
        }
        $scopeTotal = (clone $scopeQuery)->count();
        $cpfCnpjJaPopulado = (clone $scopeQuery)->whereNotNull('cpf_cnpj')->where('cpf_cnpj', '!=', '')->count();
        $semTaxNumber = (clone $scopeQuery)->where(function ($q) {
            $q->whereNull('tax_number')->orWhere('tax_number', '=', '');
        })->whereNull('cpf_cnpj')->count();

        $this->newLine();
        $this->info('=== ESTADO ATUAL (antes do backfill) ===');
        $this->info("Total contacts no escopo:                {$scopeTotal}");
        $this->line("  - cpf_cnpj JA populado (skip silencioso): <fg=green>{$cpfCnpjJaPopulado}</> — NUNCA sobrescritos");
        $this->line("  - sem tax_number nem cpf_cnpj (skip):     <fg=gray>{$semTaxNumber}</> — nada a fazer");

        $baseQuery = DB::table('contacts')
            ->whereNotNull('tax_number')
            ->where('tax_number', '!=', '')
            ->whereNull('cpf_cnpj');

        if ($businessId !== null) {
            $baseQuery->where('business_id', $businessId);
        }

        $total = (clone $baseQuery)->count();
        $this->line("  - elegiveis pra backfill (tax_number presente, cpf_cnpj vazio): <fg=yellow>{$total}</>");
        $this->newLine();

        if ($cpfCnpjJaPopulado > 0) {
            $this->info("🛡  Proteção ativa: {$cpfCnpjJaPopulado} cadastros com cpf_cnpj já preenchido");
            $this->info('   continuam INTOCADOS (filtro `WHERE cpf_cnpj IS NULL` na query).');
            $this->newLine();
        }

        if ($total === 0) {
            $this->warn('Nada elegível — exit.');

            return self::SUCCESS;
        }

        $processed = 0;
        $valid = 0;
        $invalid = 0;
        $invalidSample = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // chunkById é seguro durante update (cursor estável via id).
        // Sem ele, o update fora do chunk poderia "saltar" registros.
        $iterQuery = clone $baseQuery;
        if ($limit > 0) {
            $iterQuery->limit($limit);
        }

        $iterQuery->orderBy('id')->chunkById(500, function ($contacts) use (
            &$processed,
            &$valid,
            &$invalid,
            &$invalidSample,
            $execute,
            $bar,
            $limit,
        ) {
            foreach ($contacts as $contact) {
                if ($limit > 0 && $processed >= $limit) {
                    return false; // Encerra chunk
                }
                $processed++;
                $bar->advance();

                $taxNumber = (string) ($contact->tax_number ?? '');
                $digits = preg_replace('/\D/', '', $taxNumber);

                if (! Util::validarCnpjCpf($taxNumber)) {
                    $invalid++;
                    if (count($invalidSample) < 50) {
                        $invalidSample[] = [
                            'id' => (int) $contact->id,
                            'business_id' => (int) $contact->business_id,
                            'tax_number_len' => strlen((string) $digits),
                        ];
                    }

                    continue;
                }

                if ($execute) {
                    DB::table('contacts')
                        ->where('id', $contact->id)
                        ->update([
                            'cpf_cnpj' => $digits,
                            'updated_at' => now(),
                        ]);
                }
                $valid++;
            }

            return true;
        });

        $bar->finish();
        $this->newLine();

        $this->info("===== RESULTADO {$mode} =====");
        $this->info("Processed:       {$processed}");
        $this->info("Valid mod-11:    {$valid}");
        $this->info("Invalid mod-11:  {$invalid}");
        if ($execute) {
            $this->info("Persisted:       {$valid}");
        } else {
            $this->warn('DRY-RUN — nada persistido. Use --execute pra aplicar.');
        }

        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        $logPath = $logDir.DIRECTORY_SEPARATOR.'backfill-cpfcnpj-'.now()->format('Y-m-d-His').'.json';

        // LGPD: nunca grava tax_number completo. Só length + id + business_id.
        $payload = [
            'mode' => $mode,
            'timestamp' => now()->toIso8601String(),
            'business_id_filter' => $businessId,
            'limit' => $limit,
            'scope_snapshot_before' => [
                'total_in_scope' => $scopeTotal,
                'cpf_cnpj_already_populated' => $cpfCnpjJaPopulado,
                'no_tax_number_no_cpf_cnpj' => $semTaxNumber,
                'eligible_for_backfill' => $total,
            ],
            'processed' => $processed,
            'valid_mod11' => $valid,
            'invalid_mod11' => $invalid,
            'invalid_sample_first_50' => $invalidSample,
            'note' => 'PII redacted — tax_number values NOT logged. Use IDs to investigate. Contacts with pre-existing cpf_cnpj NEVER touched (whereNull guard).',
        ];
        file_put_contents($logPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Log: {$logPath}");

        if (! $execute && $valid > 0) {
            $this->newLine();
            $this->comment("Pra persistir: php artisan cliente:backfill-cpf-cnpj --execute".
                ($businessId !== null ? " --business-id={$businessId}" : '').
                ($limit > 0 ? " --limit={$limit}" : ''));
        }

        return self::SUCCESS;
    }
}
