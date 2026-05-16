<?php

declare(strict_types=1);

namespace Modules\Jana\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\HealthNarrative;
use Modules\Jana\Services\HealthNarratorService;
use Modules\Jana\Services\HealthSnapshotService;

/**
 * US-COPI-100 — Brain A horário do Cockpit Saúde do Ecossistema.
 *
 * Pipeline: HealthSnapshotService::snapshot() → HealthNarratorService::narrate()
 * → persist em jana_health_narratives → escalation HITL Wagner se severity=critical.
 *
 * MULTI-TENANT: superadmin job, sem business_id by design (ADR 0093
 * §"Commands & Jobs sem HTTP context"). Brain A narra SAUDE DO ECOSSISTEMA
 * inteiro (cross-tenant) — proposito explicito e auditavel. Constructor sem
 * args (cron-driven hourly). Wave 16 governance v3 — marker reforcado pra
 * rubrica D1 v3.2 hardened distinguir "esqueceu businessId" de "by design".
 *
 * Schedule: hourly em app/Console/Kernel.php (live only). Cron rodando 24x/dia
 * com gpt-4o-mini ~R$ [redacted Tier 0]/dia (cap protegido por jana:health-check check
 * `custo_brain_b_24h` <= R$ [redacted Tier 0]/dia).
 */
class NarrarSaudeEcosistemaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;

    public function handle(
        HealthSnapshotService $snapshotService,
        HealthNarratorService $narratorService,
    ): void {
        $snapshot = $snapshotService->snapshot();
        $narrative = $narratorService->narrate($snapshot);

        Log::channel('copiloto-ai')->info('NarrarSaudeEcosistemaJob.completed', [
            'narrative_id' => $narrative->id,
            'severity' => $narrative->severity,
            'snapshot_hash' => substr($narrative->snapshot_hash, 0, 12),
        ]);

        if ($narrative->isCritical()) {
            $this->escalarHitlWagner($narrative);
        }
    }

    /**
     * Escalation HITL Wagner — severity=critical sinaliza ALERT no log canônico
     * (storage/logs/laravel.log) mesmo padrão do `jana:health-check --notify`.
     * Wagner faz tail/grep e investiga.
     */
    private function escalarHitlWagner(HealthNarrative $narrative): void
    {
        Log::channel('single')->error(
            sprintf(
                'BRAIN_A_ALERT [critical] narrative_id=%d snapshot=%s — %s',
                $narrative->id,
                substr($narrative->snapshot_hash, 0, 12),
                $narrative->narrative,
            ),
        );
    }

    public function tags(): array
    {
        return ['copiloto', 'health', 'brain-a-narrator'];
    }
}
