<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * Trait reusável pra publicar drift detectado no Centrifugo.
 *
 * Channel canônico: 'governance:drift' (generalizado de 'governance:secrets' ADR 0215).
 * Wrapper canônico: Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher
 * (dívida arquitetural §Pegadinha 4 — mora em Whatsapp; refator futuro).
 *
 * Payload canônico (alinhado ADR 0215 SecretsAuditCommand::publishCentrifugoAlert):
 *   {
 *     "type": "drift.detected",
 *     "checker": "<name>",
 *     "count": N,
 *     "severity": "critical|high|medium|low|info",
 *     "findings_preview": [ /* até 5 findings com target+severity *​/ ],
 *     "detected_at": "ISO8601"
 *   }
 *
 * Falha silenciosa (Centrifugo offline NÃO bloqueia cron).
 *
 * ADR 0216 §Trait PublishesDriftToCentrifugo
 */
trait PublishesDriftToCentrifugo
{
    public function publishDriftToCentrifugo(
        DriftCheckResult $result,
        string $channel = 'governance:drift',
    ): bool {
        if ($result->ok) {
            return true; // Nada a publicar quando clean
        }

        try {
            $payload = $result->centrifugo_payload ?? $this->defaultCentrifugoPayload($result);

            return app(CentrifugoPublisher::class)->publish($channel, $payload);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('governance:audit — falha publish Centrifugo (não-bloqueante)', [
                'channel' => $channel,
                'checker' => $result->name,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultCentrifugoPayload(DriftCheckResult $result): array
    {
        $previewLimit = 5;
        $findingsPreview = array_slice(
            array_map(
                static fn ($f) => [
                    'target' => $f->target,
                    'target_type' => $f->target_type,
                    'severity' => $f->severity,
                    'message' => mb_strimwidth($f->message, 0, 200, '…'),
                ],
                $result->findings,
            ),
            0,
            $previewLimit,
        );

        $maxSeverity = 'info';
        foreach ($result->findings as $f) {
            if ($this->severityRank($f->severity) > $this->severityRank($maxSeverity)) {
                $maxSeverity = $f->severity;
            }
        }

        return [
            'type' => 'drift.detected',
            'checker' => $result->name,
            'count' => $result->drift_count,
            'severity' => $maxSeverity,
            'duration_ms' => $result->duration_ms,
            'findings_preview' => $findingsPreview,
            'detected_at' => now()->toIso8601String(),
        ];
    }

    private function severityRank(string $severity): int
    {
        return match (strtolower($severity)) {
            'critical' => 5,
            'high' => 4,
            'medium' => 3,
            'low' => 2,
            'info' => 1,
            default => 0,
        };
    }
}
