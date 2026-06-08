<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Cockpit Saúde do Ecossistema (US-COPI-097, sub do epic US-COPI-095).
 *
 * Agrega 4 fontes em 1 array imutável (snapshot) pra alimentar:
 *   - Page Inertia /copiloto/admin/health (US-COPI-098)
 *   - Brain A narrador horário (US-COPI-099) — lê snapshot e gera narrativa
 *
 * Superadmin-only por design — atravessa tenants intencionalmente (cockpit
 * agrega plataforma toda, ADR 0094 Constituição V2 §5 SoC brutal).
 *
 * Cada fonte degrada graciosamente se sua tabela/comando estiver ausente —
 * o consumidor sempre recebe shape estável.
 */
class HealthSnapshotService
{
    private const PRICING_USD_PER_1M_TOKENS_IN = 0.15;
    private const PRICING_USD_PER_1M_TOKENS_OUT = 0.60;
    private const USD_TO_BRL = 5.0;

    public function snapshot(): array
    {
        // D9.a Observability — span zero-cost agrega 4 fontes (health/queues/mcp/brain_b).
        return OtelHelper::spanBiz('jana.health.snapshot', function () {
            return [
                'generated_at' => now()->toIso8601String(),
                'health' => $this->healthChecks(),
                'queues' => $this->queueStats(),
                'mcp' => $this->mcpStats(),
                'brain_b' => $this->brainBStats(),
            ];
        });
    }

    /**
     * Roda `jana:health-check --json` e devolve o shape decoded. Falhas
     * (comando ausente, parse error, exceção) viram shape de erro previsível.
     */
    private function healthChecks(): array
    {
        try {
            $output = new BufferedOutput;
            Artisan::call('jana:health-check', ['--json' => true], $output);
            $decoded = json_decode($output->fetch(), true);

            return is_array($decoded) ? $decoded : [
                'ok' => false,
                'error' => 'parse_failed',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'command_failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function queueStats(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'failed_24h' => (int) DB::table('failed_jobs')
                ->where('failed_at', '>', now()->subHours(24))
                ->count(),
            'failed_total' => (int) DB::table('failed_jobs')->count(),
        ];
    }

    private function mcpStats(): array
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return ['available' => false];
        }

        $base = DB::table('mcp_audit_log')->where('ts', '>', now()->subHours(24));
        $total = (int) (clone $base)->count();
        $errors = (int) (clone $base)
            ->whereIn('status', ['denied', 'error', 'quota_exceeded'])
            ->count();
        $custo = (float) (clone $base)->sum('custo_brl');

        return [
            'available' => true,
            'requests_24h' => $total,
            'errors_24h' => $errors,
            'taxa_erro' => $total > 0 ? round($errors / $total, 4) : 0.0,
            'custo_brl_24h' => round($custo, 4),
        ];
    }

    private function brainBStats(): array
    {
        if (! Schema::hasTable('jana_mensagens')) {
            return ['available' => false];
        }

        $base = DB::table('jana_mensagens')->where('created_at', '>', now()->subHours(24));
        $tokensIn = (int) (clone $base)->sum('tokens_in');
        $tokensOut = (int) (clone $base)->sum('tokens_out');

        $custoUsd = ($tokensIn * self::PRICING_USD_PER_1M_TOKENS_IN / 1_000_000)
            + ($tokensOut * self::PRICING_USD_PER_1M_TOKENS_OUT / 1_000_000);
        $custoBrl = round($custoUsd * self::USD_TO_BRL, 4);

        return [
            'available' => true,
            'tokens_in_24h' => $tokensIn,
            'tokens_out_24h' => $tokensOut,
            'custo_brl_24h' => $custoBrl,
        ];
    }
}
