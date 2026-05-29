<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Checkers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

/**
 * MeilisearchSettingsDriftChecker — settings vivos do índice != config desejada.
 *
 * ADR 0216 (framework DriftChecker plugável). Plugado em `governance.drift_checkers` →
 * roda no `governance:audit --all` (já agendado). NÃO é comando bespoke nem cron próprio.
 *
 * Motivo (bug recorrente 2026-05-29): o embedder do Meilisearch foi setado MANUAL via curl
 * (Sprint 9b) e SE PERDEU 2× — `jana_memoria_facts` voltou a embedders `{}` → recall
 * semântico do chat degradou em SILÊNCIO até alguém descobrir na mão. Sem detecção, drift
 * de settings de índice era invisível. Este checker fecha esse buraco.
 *
 * Desired = `config copiloto.meilisearch_indexes` (config-as-code). Observed = GET vivo.
 * Cura = `php artisan jana:meilisearch-setup` (runtime PATCH — fica no domínio Jana).
 *
 * Severity high (recall degrada) · enforcement warn (Brief Jana, não bloqueia merge) ·
 * cadence daily.
 */
final class MeilisearchSettingsDriftChecker implements DriftChecker
{
    public function name(): string
    {
        return 'meilisearch_settings_drift';
    }

    public function description(): string
    {
        return 'Embedder/filterable do índice Meilisearch != config (recall degrada em silêncio)';
    }

    public function tags(): array
    {
        return ['tier_1', 'retrieval', 'memory_canon'];
    }

    public function severity(): string
    {
        return 'high';
    }

    public function enforcement(): string
    {
        return 'warn';
    }

    public function cadence(): string
    {
        return 'daily';
    }

    public function check(array $opts = []): DriftCheckResult
    {
        $start = microtime(true);

        /** @var array<string, array<string, mixed>> $indexes */
        $indexes = (array) config('copiloto.meilisearch_indexes', []);
        if ($indexes === []) {
            return DriftCheckResult::clean($this->name(), 0, ['skipped' => 'config copiloto.meilisearch_indexes vazia']);
        }

        $host = rtrim((string) config('scout.meilisearch.host', 'http://localhost:7700'), '/');
        $key  = (string) config('scout.meilisearch.key', '');

        $findings = [];

        foreach ($indexes as $uid => $cfg) {
            try {
                $resp = Http::withToken($key)->timeout(30)->get("{$host}/indexes/{$uid}/settings");
            } catch (\Throwable $e) {
                Log::channel('copiloto-ai')->warning(
                    "MeilisearchSettingsDriftChecker: falha ao ler settings de '{$uid}' (registrado como finding): ".$e->getMessage()
                );
                $findings[] = new DriftFinding(
                    target: $uid,
                    target_type: 'meilisearch_index',
                    severity: 'medium',
                    message: "Não consegui ler settings do índice '{$uid}': {$e->getMessage()}",
                    evidence: ['index' => $uid, 'error' => $e->getMessage()],
                );

                continue;
            }

            if ($resp->failed()) {
                $findings[] = new DriftFinding(
                    target: $uid,
                    target_type: 'meilisearch_index',
                    severity: 'medium',
                    message: "GET settings do índice '{$uid}' falhou (HTTP {$resp->status()})",
                    evidence: ['index' => $uid, 'http_status' => $resp->status()],
                );

                continue;
            }

            foreach ($this->driftsDoIndice($uid, $cfg, (array) $resp->json()) as $finding) {
                $findings[] = $finding;
            }
        }

        $duration = (int) round((microtime(true) - $start) * 1000);

        if ($findings === []) {
            return DriftCheckResult::clean($this->name(), $duration, ['indexes' => array_keys($indexes)]);
        }

        return DriftCheckResult::drifted($this->name(), $findings, $duration, [
            'cura' => 'php artisan jana:meilisearch-setup',
        ]);
    }

    /**
     * Compara a config desejada de UM índice com os settings vivos. Pura/testável.
     * Drift = embedder esperado ausente (o bug recorrente), source/model/dimensions
     * divergente, ou filterableAttributes diferente (como conjunto).
     *
     * @param  array<string, mixed> $cfg   config desejada (embedders + filterableAttributes)
     * @param  array<string, mixed> $vivo  settings vivos do Meilisearch
     * @return DriftFinding[]
     */
    public function driftsDoIndice(string $uid, array $cfg, array $vivo): array
    {
        $findings = [];

        /** @var array<string, array<string, mixed>> $expEmb */
        $expEmb = (array) ($cfg['embedders'] ?? []);
        /** @var array<string, array<string, mixed>> $vivoEmb */
        $vivoEmb = (array) ($vivo['embedders'] ?? []);

        foreach ($expEmb as $nome => $espec) {
            if (! isset($vivoEmb[$nome])) {
                Log::warning("MeilisearchSettingsDriftChecker: embedder '{$nome}' ausente no índice '{$uid}' — recall degrada (ADR 0212 rastreabilidade).", ['index' => $uid, 'embedder' => $nome]);
                $findings[] = new DriftFinding(
                    target: $uid,
                    target_type: 'meilisearch_index',
                    severity: 'high',
                    message: "Índice '{$uid}': embedder '{$nome}' AUSENTE (esperado na config). "
                        .'Recall semântico degrada. Cura: php artisan jana:meilisearch-setup',
                    evidence: ['index' => $uid, 'embedder' => $nome, 'tipo' => 'embedder_ausente'],
                );

                continue;
            }
            foreach (['source', 'model', 'dimensions'] as $campo) {
                if (! array_key_exists($campo, (array) $espec)) {
                    continue;
                }
                $vivoVal = $vivoEmb[$nome][$campo] ?? null;
                if ($vivoVal != $espec[$campo]) {
                    $findings[] = new DriftFinding(
                        target: $uid,
                        target_type: 'meilisearch_index',
                        severity: 'high',
                        message: "Índice '{$uid}': embedder '{$nome}'.{$campo} divergente "
                            .'(config='.json_encode($espec[$campo]).' vivo='.json_encode($vivoVal).')',
                        evidence: ['index' => $uid, 'embedder' => $nome, 'campo' => $campo],
                    );
                }
            }
        }

        $expFilt  = (array) ($cfg['filterableAttributes'] ?? []);
        $vivoFilt = (array) ($vivo['filterableAttributes'] ?? []);
        sort($expFilt);
        sort($vivoFilt);
        if ($expFilt !== $vivoFilt) {
            $findings[] = new DriftFinding(
                target: $uid,
                target_type: 'meilisearch_index',
                severity: 'medium',
                message: "Índice '{$uid}': filterableAttributes divergente "
                    .'(config=['.implode(',', $expFilt).'] vivo=['.implode(',', $vivoFilt).'])',
                evidence: ['index' => $uid, 'tipo' => 'filterable_diff'],
            );
        }

        return $findings;
    }
}
