<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Sla;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\SlaPolicy;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * Observabilidade D9.a (ADR 0155): scanAndAlert envolto em `OtelHelper::span(`
 * (Tracer whatsapp.sla.scan) — mede policies × conversations × fired/skipped.
 *
 * SlaEnforcer — CYCLE-07 PR-2 (Gap P0 #2 COMPARATIVO-MERCADO-2026-05-12).
 *
 * Varre policies ativas de TODOS businesses (job cross-tenant), pra cada
 * uma resolve conversas que estão **vencendo o SLA** segundo `triggers_on`,
 * e dispara `action_kind` (notify Centrifugo / reassign / set_status).
 *
 * **Triggers (semantics):**
 *
 *   - `first_inbound_no_reply` — conversa tem `last_inbound_at <= now() - threshold`
 *     E (`last_outbound_at IS NULL` OU `last_outbound_at < last_inbound_at`).
 *     Mesma regra do filtro `inbound_aging` no InboxController.
 *
 *   - `open_aging` — conversa `status=open` + `last_message_at <= now() - threshold`.
 *     Sinaliza conversa esquecida em aberto.
 *
 *   - `awaiting_human_aging` — conversa `status=awaiting_human` há > threshold.
 *     Bot escalou, ninguém pegou.
 *
 * **Idempotência (anti-spam):**
 *
 * Lock por `(policy_id, conversation_id)` com TTL = `min(threshold, 10min)` —
 * uma conversa não recebe N alertas pela mesma policy dentro da janela curta.
 * Lock Cache do Laravel (driver Redis em prod, file em dev). Job rodando
 * `everyFiveMinutes` re-bate na mesma conv => lock segura.
 *
 * **Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):**
 *
 *   - Scan cross-tenant: `withoutGlobalScopes()` deliberado (job sem session).
 *   - Cada policy traz `business_id`; filtro de conversas usa esse id
 *     (mesmo com scope bypass, o WHERE explícito impede leak).
 *   - Canal Centrifugo é per-business `omnichannel:business:{id}:sla_alerts`.
 *
 * **Dry-run mode:** passa `dryRun=true` → nenhuma persistência (não publica
 * Centrifugo, não muda status, não toma lock). Só retorna o que faria.
 *
 * @see Modules/Whatsapp/Console/Commands/SlaScanCommand.php
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md
 */
class SlaEnforcer
{
    public function __construct(
        private readonly CentrifugoPublisher $publisher,
    ) {
    }

    /**
     * Scan + dispatch — retorna métricas por policy.
     *
     * @param  int|null  $businessId  null = todos businesses; int = filtro
     * @param  bool  $dryRun  true = não persiste nem publica
     * @return array{policies_scanned:int,alerts_fired:int,locked_skipped:int,by_policy:array<int,array<string,int|string>>}
     */
    public function scanAndAlert(?int $businessId = null, bool $dryRun = false): array
    {
        return OtelHelper::span('whatsapp.sla.scan', [
            'business_filter' => $businessId ?? 'all',
            'dry_run' => $dryRun,
        ], fn () => $this->doScanAndAlert($businessId, $dryRun));
    }

    private function doScanAndAlert(?int $businessId, bool $dryRun): array
    {
        $policyQuery = SlaPolicy::withoutGlobalScopes()->active();
        if ($businessId !== null) {
            $policyQuery->where('business_id', $businessId);
        }

        $policies = $policyQuery->get();

        $alertsFired = 0;
        $lockedSkipped = 0;
        $byPolicy = [];

        foreach ($policies as $policy) {
            $convs = $this->conversationsViolatingPolicy($policy);
            $firedForPolicy = 0;
            $skippedForPolicy = 0;

            foreach ($convs as $conv) {
                if ($this->isLocked($policy, $conv) && ! $dryRun) {
                    $skippedForPolicy++;
                    continue;
                }

                $minutesOverdue = $this->minutesOverdue($policy, $conv);

                if (! $dryRun) {
                    $this->acquireLock($policy, $conv);
                    $this->dispatchAction($policy, $conv, $minutesOverdue);
                }

                $firedForPolicy++;
            }

            $alertsFired += $firedForPolicy;
            $lockedSkipped += $skippedForPolicy;

            $byPolicy[(int) $policy->id] = [
                'business_id' => (int) $policy->business_id,
                'label' => (string) $policy->label,
                'triggers_on' => (string) $policy->triggers_on,
                'action_kind' => (string) $policy->action_kind,
                'fired' => $firedForPolicy,
                'locked_skipped' => $skippedForPolicy,
            ];
        }

        Log::info('[whatsapp.sla.scan]', [
            'business_filter' => $businessId,
            'dry_run' => $dryRun,
            'policies_scanned' => $policies->count(),
            'alerts_fired' => $alertsFired,
            'locked_skipped' => $lockedSkipped,
        ]);

        return [
            'policies_scanned' => $policies->count(),
            'alerts_fired' => $alertsFired,
            'locked_skipped' => $lockedSkipped,
            'by_policy' => $byPolicy,
        ];
    }

    /**
     * Resolve as conversas que violam o SLA de uma policy.
     *
     * @return \Illuminate\Support\Collection<int, Conversation>
     */
    private function conversationsViolatingPolicy(SlaPolicy $policy): \Illuminate\Support\Collection
    {
        $cutoff = now()->subMinutes((int) $policy->threshold_minutes);

        // SUPERADMIN: bypass global scope pra varrer cross-tenant — WHERE
        // explícito com policy.business_id impede leak. Multi-tenant Tier 0
        // garantido pelo WHERE (não pelo scope) neste caso.
        $query = Conversation::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', (int) $policy->business_id);

        if ($policy->channel_id !== null) {
            $query->where('channel_id', (int) $policy->channel_id);
        }

        if ($policy->tag_id !== null) {
            $tagId = (int) $policy->tag_id;
            $query->whereHas('tags', fn ($q) => $q->where('whatsapp_tags.id', $tagId));
        }

        // Status awaiting/archived/resolved não considerados pra triggers
        // de aging — só `open` (com exceção do trigger awaiting_human_aging
        // que filtra explicitamente abaixo).
        match ($policy->triggers_on) {
            SlaPolicy::TRIGGER_FIRST_INBOUND_NO_REPLY => $this->scopeFirstInboundNoReply($query, $cutoff),
            SlaPolicy::TRIGGER_OPEN_AGING => $this->scopeOpenAging($query, $cutoff),
            SlaPolicy::TRIGGER_AWAITING_HUMAN_AGING => $this->scopeAwaitingHumanAging($query, $cutoff),
            default => $query->whereRaw('1=0'), // unknown trigger → no-op
        };

        return $query->get();
    }

    private function scopeFirstInboundNoReply(Builder $query, Carbon $cutoff): void
    {
        // Mesma lógica do filtro `inbound_aging` no InboxController:
        // cliente mandou msg há > threshold, atendente NÃO respondeu desde então.
        $query->whereIn('status', [
            Conversation::STATUS_OPEN,
            Conversation::STATUS_AWAITING_HUMAN,
        ])
            ->whereNotNull('last_inbound_at')
            ->where('last_inbound_at', '<=', $cutoff)
            ->where(function ($q) {
                $q->whereNull('last_outbound_at')
                  ->orWhereColumn('last_outbound_at', '<', 'last_inbound_at');
            });
    }

    private function scopeOpenAging(Builder $query, Carbon $cutoff): void
    {
        $query->where('status', Conversation::STATUS_OPEN)
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', $cutoff);
    }

    private function scopeAwaitingHumanAging(Builder $query, Carbon $cutoff): void
    {
        // updated_at marca a transição de status (Eloquent atualiza no save).
        // Usamos updated_at como proxy de "quando virou awaiting_human" —
        // pattern aceito até existir tabela de histórico dedicada.
        $query->where('status', Conversation::STATUS_AWAITING_HUMAN)
            ->where('updated_at', '<=', $cutoff);
    }

    /**
     * Minutos vencidos = agora - referência (depende do trigger).
     */
    private function minutesOverdue(SlaPolicy $policy, Conversation $conv): int
    {
        $reference = match ($policy->triggers_on) {
            SlaPolicy::TRIGGER_FIRST_INBOUND_NO_REPLY => $conv->last_inbound_at,
            SlaPolicy::TRIGGER_OPEN_AGING => $conv->last_message_at,
            SlaPolicy::TRIGGER_AWAITING_HUMAN_AGING => $conv->updated_at,
            default => null,
        };

        if ($reference === null) {
            return 0;
        }

        return (int) max(0, now()->diffInMinutes($reference, false) * -1);
    }

    /**
     * Lock key idempotência — formato sla:fired:{policy_id}:{conversation_id}.
     * TTL = min(threshold_minutes, 10) minutos. Cobre re-runs do scan job
     * (everyFiveMinutes) sem spamar 4× pelo mesmo limite.
     */
    private function lockKey(SlaPolicy $policy, Conversation $conv): string
    {
        return sprintf('sla:fired:%d:%d', (int) $policy->id, (int) $conv->id);
    }

    private function lockTtlSeconds(SlaPolicy $policy): int
    {
        $minutes = min((int) $policy->threshold_minutes, 10);
        return max(60, $minutes * 60); // mínimo 1 min de proteção
    }

    private function isLocked(SlaPolicy $policy, Conversation $conv): bool
    {
        return Cache::has($this->lockKey($policy, $conv));
    }

    private function acquireLock(SlaPolicy $policy, Conversation $conv): void
    {
        Cache::put(
            $this->lockKey($policy, $conv),
            now()->toIso8601String(),
            $this->lockTtlSeconds($policy),
        );
    }

    /**
     * Dispatcher de action_kind. Cada um isolado em método pra audit fácil.
     */
    private function dispatchAction(SlaPolicy $policy, Conversation $conv, int $minutesOverdue): void
    {
        match ($policy->action_kind) {
            SlaPolicy::ACTION_CENTRIFUGO_NOTIFY => $this->doNotify($policy, $conv, $minutesOverdue),
            SlaPolicy::ACTION_REASSIGN => $this->doReassign($policy, $conv, $minutesOverdue),
            SlaPolicy::ACTION_SET_STATUS => $this->doSetStatus($policy, $conv, $minutesOverdue),
            default => Log::warning('[whatsapp.sla.action_unknown]', [
                'policy_id' => (int) $policy->id,
                'action_kind' => (string) $policy->action_kind,
            ]),
        };
    }

    private function doNotify(SlaPolicy $policy, Conversation $conv, int $minutesOverdue): void
    {
        $channel = sprintf('omnichannel:business:%d:sla_alerts', (int) $policy->business_id);

        $this->publisher->publish($channel, [
            'type' => 'sla_alert',
            'conversation_id' => (int) $conv->id,
            'policy_id' => (int) $policy->id,
            'policy_label' => (string) $policy->label,
            'triggers_on' => (string) $policy->triggers_on,
            'minutes_overdue' => $minutesOverdue,
            'contact_name' => (string) ($conv->contact_name ?? ''),
            'fired_at' => now()->toIso8601String(),
        ]);
    }

    private function doReassign(SlaPolicy $policy, Conversation $conv, int $minutesOverdue): void
    {
        $toUserId = (int) ($policy->action_params['to_user_id'] ?? 0);
        if ($toUserId <= 0) {
            Log::warning('[whatsapp.sla.reassign.missing_to_user_id]', [
                'policy_id' => (int) $policy->id,
                'conversation_id' => (int) $conv->id,
            ]);
            return;
        }

        // forceFill + save bypassa observers de Conversation (não há trait
        // GuardsFsmTransitions aqui — Conversation não é FSM Sells/Repair).
        $conv->forceFill(['assigned_user_id' => $toUserId])->save();

        // Notifica também via Centrifugo pro novo assignee saber.
        $this->publisher->publish(
            sprintf('user:%d', $toUserId),
            [
                'type' => 'sla_reassign',
                'conversation_id' => (int) $conv->id,
                'policy_label' => (string) $policy->label,
                'minutes_overdue' => $minutesOverdue,
            ],
        );
    }

    private function doSetStatus(SlaPolicy $policy, Conversation $conv, int $minutesOverdue): void
    {
        $newStatus = (string) ($policy->action_params['status'] ?? '');
        if (! in_array($newStatus, Conversation::STATUSES, true)) {
            Log::warning('[whatsapp.sla.set_status.invalid]', [
                'policy_id' => (int) $policy->id,
                'requested_status' => $newStatus,
            ]);
            return;
        }

        $conv->forceFill(['status' => $newStatus])->save();
    }
}
