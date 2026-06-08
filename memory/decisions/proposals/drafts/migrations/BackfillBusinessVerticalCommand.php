<?php

/**
 * DRAFT — NÃO EXECUTAR DIRETO.
 *
 * Command: backfill `business.vertical_id` + `business.cnae_principal` via BrasilAPI.
 *
 * Local final sugerido: Modules/Insights/Console/Commands/BackfillBusinessVerticalCommand.php
 * Registro em: Modules/Insights/Providers/InsightsServiceProvider.php (commands array).
 *
 * Uso:
 *   php artisan insights:backfill-vertical --dry-run
 *   php artisan insights:backfill-vertical --business-id=4   (apenas ROTA LIVRE)
 *   php artisan insights:backfill-vertical                    (todos os 56 businesses)
 *
 * ⚠️ TENANCY CRITICAL — Felipe deve verificar:
 *   1) Command roda em CLI (sem session) — não usa session('user.business_id'), usa loop direto na tabela `business`.
 *   2) `withoutGlobalScopes` NÃO usado — `DB::table('business')` ignora Eloquent scopes (intencional, é cross-tenant admin).
 *   3) Smoke biz=4 ROTA LIVRE com --dry-run primeiro (ver impacto sem escrever).
 *   4) Rate limit BrasilAPI: 1 req/s (sleep(1)) — pra 56 businesses = ~1min.
 *   5) tax_number pode estar vazio/inválido em alguns businesses legacy — command pula gracefully.
 *
 * BrasilAPI CNPJ endpoint:
 *   GET https://brasilapi.com.br/api/cnpj/v1/{cnpj}
 *   Response.cnae_fiscal = código primário (ex: "1813001" sem hifens — normalizar pra "1813-0/01")
 *   Free tier sem auth, rate limit ~3 req/s. Default timeout 10s.
 *
 * Idempotente:
 *   - Filtro `whereNull('vertical_id')` — só processa businesses ainda não populados.
 *   - Re-rodar é seguro.
 */

namespace Modules\Insights\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackfillBusinessVerticalCommand extends Command
{
    protected $signature = 'insights:backfill-vertical
                            {--business-id= : Specific business ID (default: all NULL vertical_id)}
                            {--dry-run : Não escreve nada, só mostra o que faria}
                            {--force : Re-roda em business com vertical_id já setado (sobrescreve via BrasilAPI). Útil pra correção CNAE.}';

    protected $description = 'Backfill business.vertical_id + cnae_principal via BrasilAPI';

    public function handle(): int
    {
        $query = DB::table('business');
        // D4 (Felipe 2026-05-11): default = só pendentes (idempotente). --force = re-processa
        // todos pra correção de CNAE incorreto em business já classificado.
        if (! $this->option('force')) {
            $query->whereNull('vertical_id');
        }
        if ($id = $this->option('business-id')) {
            $query->where('id', $id);
        }
        $businesses = $query->get();

        if ($businesses->isEmpty()) {
            $this->info('Nenhum business pendente de backfill.');
            return self::SUCCESS;
        }

        $this->info("Processando {$businesses->count()} businesses" . ($this->option('dry-run') ? ' (DRY RUN)' : '') . '...');
        $bar = $this->output->createProgressBar($businesses->count());

        $stats = ['ok' => 0, 'sem_cnpj' => 0, 'api_falha' => 0, 'sem_vertical_match' => 0];

        foreach ($businesses as $biz) {
            // D3 (Felipe 2026-05-11): coluna real em UltimatePOS é `tax_number_1`
            // (legacy multi-tax: pode ter tax_number_2 também). Confirmado via
            // SHOW COLUMNS em prod 2026-05-10 tarde.
            $cnpj = preg_replace('/\D/', '', (string) ($biz->tax_number_1 ?? ''));

            if (strlen($cnpj) !== 14) {
                $stats['sem_cnpj']++;
                $bar->advance();
                continue;
            }

            try {
                $resp = Http::timeout(10)->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
                if (!$resp->successful()) {
                    $stats['api_falha']++;
                    $bar->advance();
                    continue;
                }

                $data = $resp->json();
                $cnaeRaw = (string) ($data['cnae_fiscal'] ?? '');
                $cnaeFormatted = $this->formatCnae($cnaeRaw);
                $cnaesSecundarios = collect($data['cnaes_secundarios'] ?? [])
                    ->map(fn ($c) => $this->formatCnae((string) ($c['codigo'] ?? '')))
                    ->filter()
                    ->values()
                    ->toArray();

                $vertical = $cnaeFormatted ? $this->mapCnaeToVertical($cnaeFormatted) : null;
                if (!$vertical) {
                    $stats['sem_vertical_match']++;
                }

                if (!$this->option('dry-run')) {
                    DB::table('business')->where('id', $biz->id)->update([
                        'cnae_principal' => $cnaeFormatted,
                        'cnae_secundarios' => empty($cnaesSecundarios) ? null : json_encode($cnaesSecundarios),
                        'vertical_id' => $vertical?->id,
                        'updated_at' => now(),
                    ]);
                }

                $stats['ok']++;
                Log::info('insights.backfill.business', [
                    'business_id' => $biz->id,
                    'cnae' => $cnaeFormatted,
                    'vertical_id' => $vertical?->id,
                    'dry_run' => $this->option('dry-run'),
                ]);
            } catch (\Throwable $e) {
                $stats['api_falha']++;
                $this->newLine();
                $this->warn("Falhou biz {$biz->id}: {$e->getMessage()}");
            }

            $bar->advance();
            sleep(1); // rate limit BrasilAPI (~1 req/s)
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Backfill completo:');
        $this->table(
            ['Status', 'Count'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
        );

        return self::SUCCESS;
    }

    /**
     * Normaliza CNAE de "1813001" → "1813-0/01" (formato IBGE oficial).
     */
    private function formatCnae(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) !== 7) {
            return null;
        }
        return substr($digits, 0, 4) . '-' . substr($digits, 4, 1) . '/' . substr($digits, 5, 2);
    }

    /**
     * Procura vertical cujo cnae_codes JSON contém o CNAE.
     * MySQL JSON_CONTAINS seria mais robusto, mas LIKE funciona pra exact match em string.
     */
    private function mapCnaeToVertical(string $cnae): ?object
    {
        return DB::table('verticals')
            ->where('cnae_codes', 'like', '%"' . $cnae . '"%')
            ->where('active', true)
            ->first();
    }
}
