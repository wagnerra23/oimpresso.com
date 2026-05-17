<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\HealthNarratorAgent;
use Modules\Jana\Entities\HealthNarrative;
use Throwable;

/**
 * US-COPI-099 — gera + persiste narrativa horária do Cockpit Saúde.
 *
 * Recebe snapshot (vem de HealthSnapshotService — desacoplado pra ficar
 * mockável e independente do PR #333 mergear). Aprende severity do agent
 * + valida shape + persiste em jana_health_narratives.
 *
 * Falha graciosamente: dry_run, parse error, exception → severity=info,
 * narrativa de fallback. Nunca quebra o caller (será chamado de Job hourly).
 */
class HealthNarratorService
{
    private const PRICING_USD_PER_1M_TOKENS_IN = 0.15;
    private const PRICING_USD_PER_1M_TOKENS_OUT = 0.60;
    private const USD_TO_BRL = 5.0;
    private const ALLOWED_SEVERITIES = ['info', 'warning', 'critical'];

    public function narrate(array $snapshot): HealthNarrative
    {
        // D9.a (Wave 18 SATURATION) — instrumentação OTel narração saúde.
        // Span captura tokens + custo + severity pra tracing CT 100.
        return OtelHelper::spanBiz('jana.health.narrate', fn () => $this->narrateInternal($snapshot), [
            'snapshot.hash' => substr(hash('sha256', json_encode($snapshot) ?: ''), 0, 12),
            'snapshot.bytes' => strlen(json_encode($snapshot) ?: ''),
        ]);
    }

    private function narrateInternal(array $snapshot): HealthNarrative
    {
        $hash = hash('sha256', json_encode($snapshot) ?: '');
        $model = (string) config('copiloto.openai.model_chat', 'gpt-4o-mini');

        if (config('copiloto.dry_run')) {
            return $this->persist($snapshot, $hash, $model, $this->fixture($snapshot), null, null);
        }

        try {
            $agent = new HealthNarratorAgent($snapshot);
            $response = $agent->prompt($agent->montarPromptUsuario());

            $tokensIn = $response->usage->promptTokens ?? null;
            $tokensOut = $response->usage->completionTokens ?? null;

            $parsed = $this->parseResponse((string) $response);

            Log::channel('copiloto-ai')->info('healthNarrator', [
                'severity' => $parsed['severity'],
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'snapshot_hash' => substr($hash, 0, 12),
            ]);

            return $this->persist($snapshot, $hash, $model, $parsed, $tokensIn, $tokensOut);
        } catch (Throwable $e) {
            Log::channel('copiloto-ai')->error('healthNarrator error: ' . $e->getMessage());

            return $this->persist($snapshot, $hash, $model, $this->fixture($snapshot), null, null);
        }
    }

    /**
     * @return array{severity: string, message: string}
     */
    private function parseResponse(string $raw): array
    {
        $decoded = json_decode(trim($raw), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Health narrator response is not valid JSON');
        }

        $severity = $decoded['severity'] ?? null;
        $message = $decoded['message'] ?? null;

        if (! in_array($severity, self::ALLOWED_SEVERITIES, true) || ! is_string($message) || $message === '') {
            throw new \RuntimeException('Health narrator response missing severity/message or invalid severity');
        }

        return ['severity' => $severity, 'message' => $message];
    }

    /**
     * @return array{severity: string, message: string}
     */
    private function fixture(array $snapshot): array
    {
        $health = $snapshot['health'] ?? [];
        $ok = (bool) ($health['ok'] ?? false);
        $severity = $ok ? 'info' : 'warning';
        $message = $ok
            ? 'Snapshot recebido. IA indisponível pra gerar narrativa — todos os checks SQL passaram.'
            : 'Snapshot recebido. IA indisponível pra gerar narrativa — algum check SQL falhou, investigar logs.';

        return ['severity' => $severity, 'message' => $message];
    }

    /**
     * @param  array{severity: string, message: string}  $parsed
     */
    private function persist(
        array $snapshot,
        string $hash,
        string $model,
        array $parsed,
        ?int $tokensIn,
        ?int $tokensOut,
    ): HealthNarrative {
        $custoBrl = $this->computeCustoBrl($tokensIn, $tokensOut);

        return HealthNarrative::create([
            'generated_at' => now(),
            'severity' => $parsed['severity'],
            'narrative' => $parsed['message'],
            'snapshot_hash' => $hash,
            'model' => $model,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'custo_brl' => $custoBrl,
            'payload_summary' => $this->reduzirPayload($snapshot),
        ]);
    }

    private function computeCustoBrl(?int $tokensIn, ?int $tokensOut): ?float
    {
        if ($tokensIn === null && $tokensOut === null) {
            return null;
        }

        $usd = (($tokensIn ?? 0) * self::PRICING_USD_PER_1M_TOKENS_IN / 1_000_000)
            + (($tokensOut ?? 0) * self::PRICING_USD_PER_1M_TOKENS_OUT / 1_000_000);

        return round($usd * self::USD_TO_BRL, 6);
    }

    private function reduzirPayload(array $snapshot): array
    {
        return [
            'health_ok' => $snapshot['health']['ok'] ?? null,
            'queues_failed_24h' => $snapshot['queues']['failed_24h'] ?? null,
            'mcp_taxa_erro' => $snapshot['mcp']['taxa_erro'] ?? null,
            'brain_b_custo_brl_24h' => $snapshot['brain_b']['custo_brl_24h'] ?? null,
        ];
    }
}
