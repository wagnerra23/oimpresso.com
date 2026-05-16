<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Facades\LogBatch;

/**
 * JanaAuditService — D4.d (Wave 17 governance v3).
 *
 * Centraliza emissão de eventos críticos auditáveis do módulo Jana:
 *  - Spatie ActivityLog (UI dashboard via `activitylog_default` channel)
 *  - OpenTelemetry span (observability tracing CT 100)
 *  - Logger structured (storage/logs/laravel.log canal `copiloto-ai`)
 *
 * Quando usar:
 *  - Cancelamento/exclusão LGPD (memoria fato removal)
 *  - Mudança em config sensível (alertas, retention strategy)
 *  - Override superadmin com `withoutGlobalScopes`
 *  - LLM call com custo > R$ [redacted Tier 0] (cap protection)
 *  - HITL escalation Wagner (severity=critical health narratives)
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * O método `register()` sempre injeta `business_id` no event payload (auto-resolvido
 * via session ou param explícito pra jobs assíncronos).
 *
 * Custo IA tracking ([ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4):
 * Eventos com `cost_brl_cents` viram entries em `jana_audit_log`/activitylog
 * e podem ser agregados no `CustosService` painel.
 */
class JanaAuditService
{
    /**
     * Registra evento auditável com 3 sinks: ActivityLog + OTel + Log.
     *
     * @param  string  $eventKey   slug do evento (ex 'memoria.fato.esquecer', 'config.retention.changed')
     * @param  array<string, mixed>  $payload   shape arbitrário — viralizado em JSON
     * @param  int|null  $businessId  override pra job assíncrono; default session()
     * @param  string  $severity  info|warning|critical
     */
    public function register(
        string $eventKey,
        array $payload = [],
        ?int $businessId = null,
        string $severity = 'info',
    ): void {
        $bizId = $businessId ?? session()->get('user.business_id') ?? optional(Auth::user())->business_id;
        $userId = Auth::id();

        $enriched = array_merge($payload, [
            'business_id' => $bizId,
            'user_id'     => $userId,
            'severity'    => $severity,
            'recorded_at' => now()->toIso8601String(),
        ]);

        // Sink 1: ActivityLog (UI dashboard / LGPD audit)
        activity('jana_audit')
            ->withProperties($enriched)
            ->log("jana.{$eventKey}");

        // Sink 2: OTel span (tracing CT 100 — zero-cost se OTel disabled)
        OtelHelper::span('jana.audit.' . $eventKey, [
            'business_id' => $bizId,
            'user_id'     => $userId,
            'severity'    => $severity,
        ], fn () => null);

        // Sink 3: Logger structured (storage/logs canal copiloto-ai)
        $logMethod = match ($severity) {
            'critical' => 'error',
            'warning'  => 'warning',
            default    => 'info',
        };

        Log::channel('copiloto-ai')->$logMethod("jana.audit.{$eventKey}", $enriched);
    }

    /**
     * Helper específico pra LGPD — registro de direito de eliminação (Art. 18 §VI).
     * Sempre severity=critical pra rastreabilidade forte.
     */
    public function lgpdEliminacao(
        string $entidade,
        int $entityId,
        ?int $businessId = null,
        string $motivo = 'titular_request',
    ): void {
        $this->register('lgpd.eliminacao', [
            'entidade'  => $entidade,
            'entity_id' => $entityId,
            'motivo'    => $motivo,
        ], $businessId, 'critical');
    }

    /**
     * Helper específico pra cost tracking — eventos LLM caros (≥ R$ [redacted Tier 0]/call).
     */
    public function llmCallCaro(
        string $modelo,
        int $tokensIn,
        int $tokensOut,
        float $custoBrl,
        ?int $businessId = null,
    ): void {
        $severity = $custoBrl >= 0.50 ? 'warning' : 'info';

        $this->register('llm.call.caro', [
            'modelo'      => $modelo,
            'tokens_in'   => $tokensIn,
            'tokens_out'  => $tokensOut,
            'custo_brl'   => round($custoBrl, 4),
        ], $businessId, $severity);
    }

    /**
     * Helper específico pra superadmin override (withoutGlobalScopes ou bypass tenant).
     */
    public function superadminOverride(
        string $acao,
        array $contexto = [],
        ?int $businessId = null,
    ): void {
        $this->register('superadmin.override', array_merge($contexto, [
            'acao' => $acao,
        ]), $businessId, 'warning');
    }
}
