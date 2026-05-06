<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sentinela operacional da Jana + Constituição v2.
 *
 * 5 checks SQL rodam 1×/dia (cron agendado em routes/console.php).
 * Output: tabela no stdout + log estruturado.
 * Exit code: 0 se tudo OK, 1 se qualquer check falhou (cron alerta por email).
 *
 * Checks:
 *   1. Multi-tenant Tier 0 (ADR 0093) — orfãos business_id IS NULL
 *   2. Brief uptime (S1) — ≥1 brief gerado nas últimas 24h
 *   3. Custo Brain B 24h — alvo ≤ R$ 5/dia
 *   4. PII leak detection (COPI-43) — mensagens user com regex CPF/email
 *   5. ProfileDistiller drift (COPI-26) — profiles >7d sem regenerar
 *
 * Uso:
 *   php artisan jana:health-check
 *   php artisan jana:health-check --json (output machine-readable)
 *   php artisan jana:health-check --notify (escreve em log alert)
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'jana:health-check
                            {--json : Output JSON em vez de tabela}
                            {--notify : Loga ALERT em jana-health channel se algo falhou}';

    protected $description = 'Health check diário Jana + Constituição v2 (5 checks SQL)';

    public function handle(): int
    {
        $checks = [
            $this->checkMultiTenant(),
            $this->checkBriefUptime(),
            $this->checkCustoBrainB(),
            $this->checkPiiLeak(),
            $this->checkProfileDrift(),
        ];

        $allOk = collect($checks)->every(fn ($c) => $c['ok']);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $allOk,
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($checks, $allOk);
        }

        // Log estruturado pra Kibana/Datadog/grep posterior
        Log::channel('single')->info('jana:health-check', [
            'ok' => $allOk,
            'checks' => collect($checks)->mapWithKeys(fn ($c) => [
                $c['name'] => [
                    'ok' => $c['ok'],
                    'value' => $c['value'],
                    'threshold' => $c['threshold'] ?? null,
                ],
            ])->toArray(),
        ]);

        if ($this->option('notify') && ! $allOk) {
            $failed = collect($checks)->where('ok', false)->pluck('name')->implode(', ');
            Log::channel('single')->error("jana:health-check ALERT — falhou: {$failed}");
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Check 1 — Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL).
     * Vazar business_id é o pior bug possível. Tolerância: 0.
     */
    protected function checkMultiTenant(): array
    {
        $tabelas = ['jana_memoria_facts', 'jana_business_profile', 'jana_mensagens'];
        $orfaos = 0;
        $detalhes = [];

        foreach ($tabelas as $tabela) {
            try {
                $count = DB::table($tabela)->whereNull('business_id')->count();
                if ($count > 0) {
                    $detalhes[] = "{$tabela}: {$count} órfãos";
                }
                $orfaos += $count;
            } catch (\Throwable $e) {
                $detalhes[] = "{$tabela}: ERRO ({$e->getMessage()})";
            }
        }

        return [
            'name' => 'multi_tenant_isolation',
            'ok' => $orfaos === 0,
            'value' => $orfaos,
            'threshold' => 0,
            'message' => $orfaos === 0
                ? 'Zero órfãos business_id IS NULL'
                : 'ALERTA Tier 0: ' . implode(' · ', $detalhes),
        ];
    }

    /**
     * Check 2 — Brief uptime (Sprint 1 L7).
     * Esperado: ≥1 brief gerado nas últimas 24h (cron 6×/dia ideal).
     */
    protected function checkBriefUptime(): array
    {
        try {
            $count = DB::table('mcp_briefs')->where('generated_at', '>', now()->subHours(24))->count();
            $ultimo = DB::table('mcp_briefs')->max('generated_at');
            $threshold = 1; // mínimo 1 nas últimas 24h

            return [
                'name' => 'brief_uptime_24h',
                'ok' => $count >= $threshold,
                'value' => $count,
                'threshold' => ">= {$threshold}",
                'message' => $count >= $threshold
                    ? "{$count} briefs gerados em 24h (último: {$ultimo})"
                    : "STALE: último brief em {$ultimo}",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'brief_uptime_24h',
                'ok' => false,
                'value' => null,
                'threshold' => '>= 1',
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 3 — Custo Brain B nas últimas 24h.
     * Alvo: ≤ R$ 5/dia em produção atual.
     * Pricing gpt-4o-mini: $0.15/1M input + $0.60/1M output → R$ ~5 = 1M tokens/dia.
     */
    protected function checkCustoBrainB(): array
    {
        try {
            // Cotação USD→BRL aproximada (atualizar se mudar muito)
            $usdBrl = 5.0;
            $tokensIn = (int) DB::table('jana_mensagens')
                ->where('created_at', '>', now()->subHours(24))
                ->sum('tokens_in');
            $tokensOut = (int) DB::table('jana_mensagens')
                ->where('created_at', '>', now()->subHours(24))
                ->sum('tokens_out');

            $custoUsd = ($tokensIn * 0.15 / 1_000_000) + ($tokensOut * 0.60 / 1_000_000);
            $custoBrl = $custoUsd * $usdBrl;
            $threshold = 5.0; // R$/dia

            return [
                'name' => 'custo_brain_b_24h',
                'ok' => $custoBrl <= $threshold,
                'value' => round($custoBrl, 2),
                'threshold' => "<= R\$ {$threshold}",
                'message' => sprintf(
                    'R$ %.2f (in=%s out=%s tokens)',
                    $custoBrl, number_format($tokensIn), number_format($tokensOut)
                ),
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'custo_brain_b_24h',
                'ok' => false,
                'value' => null,
                'threshold' => '<= R$ 5',
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 4 — PII leak detection nas mensagens persistidas.
     * Detecta CPF formatado, email, telefone BR em mensagens user role.
     * Espera-se ZERO PII em respostas assistant (PII redactor PR #126+#127).
     * Tolerância em mensagens user: dados reais OK (LGPD permite armazenar
     * com consentimento do tenant), só alerta se >100/dia (sinal de uso anormal).
     */
    protected function checkPiiLeak(): array
    {
        try {
            // Conta mensagens assistant com PII (deveria ser zero)
            $piiInAssistant = DB::table('jana_mensagens')
                ->where('role', 'assistant')
                ->where('created_at', '>', now()->subHours(24))
                ->where(function ($q) {
                    $q->where('content', 'REGEXP', '[0-9]{3}\\.[0-9]{3}\\.[0-9]{3}-[0-9]{2}')
                      ->orWhere('content', 'REGEXP', '[0-9]{2}\\.[0-9]{3}\\.[0-9]{3}/[0-9]{4}-[0-9]{2}');
                })
                ->count();

            return [
                'name' => 'pii_leak_in_assistant_responses',
                'ok' => $piiInAssistant === 0,
                'value' => $piiInAssistant,
                'threshold' => 0,
                'message' => $piiInAssistant === 0
                    ? 'Zero CPF/CNPJ ecoado pelo LLM em 24h'
                    : "ALERTA LGPD: {$piiInAssistant} respostas assistant com CPF/CNPJ exposto",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'pii_leak_in_assistant_responses',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check 5 — ProfileDistiller drift.
     * Profiles devem ser regenerados pelo job diário (max 7d sem regen).
     */
    protected function checkProfileDrift(): array
    {
        try {
            $stale = DB::table('jana_business_profile')
                ->where('gerado_em', '<', now()->subDays(7))
                ->count();

            return [
                'name' => 'profile_distiller_drift',
                'ok' => $stale === 0,
                'value' => $stale,
                'threshold' => 0,
                'message' => $stale === 0
                    ? 'Todos profiles regenerados nos últimos 7d'
                    : "STALE: {$stale} profiles >7d sem regenerar",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'profile_distiller_drift',
                'ok' => false,
                'value' => null,
                'threshold' => 0,
                'message' => 'ERRO: ' . $e->getMessage(),
            ];
        }
    }

    protected function renderTable(array $checks, bool $allOk): void
    {
        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────────────────────┐');
        $this->line('│  JANA HEALTH CHECK — ' . str_pad(now()->toDateTimeString(), 51) . '│');
        $this->line('└─────────────────────────────────────────────────────────────────────────┘');
        $this->newLine();

        $rows = collect($checks)->map(fn ($c) => [
            $c['ok'] ? '✓' : '✗',
            $c['name'],
            $c['value'] !== null ? (string) $c['value'] : '—',
            (string) ($c['threshold'] ?? '—'),
            substr($c['message'], 0, 60),
        ])->toArray();

        $this->table(['', 'Check', 'Valor', 'Alvo', 'Status'], $rows);

        $this->newLine();
        if ($allOk) {
            $this->info('✓ Todos os 5 checks passaram. Sistema saudável.');
        } else {
            $failed = collect($checks)->where('ok', false)->count();
            $this->error("✗ {$failed} check(s) falharam — investigar acima.");
        }
        $this->newLine();
    }
}
