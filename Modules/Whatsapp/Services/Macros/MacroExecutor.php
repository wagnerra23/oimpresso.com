<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Macros;

use App\Util\OtelHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Macro;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\Tag;

/**
 * MacroExecutor — aplica uma Macro a uma conversa (US-WA-048).
 *
 * Fluxo:
 *   1. Valida que macro + conversation pertencem ao mesmo business (Tier 0).
 *   2. Persiste Message outbound em status=queued (defesa em profundidade).
 *   3. Dispara texto via daemon Baileys (canais não-baileys: status=failed
 *      com motivo legível, mas actions JSON são aplicadas mesmo assim —
 *      tag/status valem como classificação operacional).
 *   4. Aplica cada ação em `actions_json` em ordem (add_tag, set_status,
 *      assign_user). Ações desconhecidas são silently skipped.
 *   5. Incrementa `used_count` da macro (analytics top-N futuro).
 *
 * **Não usa Service Container fancy** — mantém footprint baixo. Inboxes
 * trust boundary já validou ACL canal antes de chamar.
 *
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap P1 #6+#12
 */
class MacroExecutor
{
    public function __construct(
        protected ?MacroVariantPicker $variantPicker = null,
    ) {
        // Container resolve quando null — mantém retrocompat (testes que instanciam new MacroExecutor()).
        $this->variantPicker = $variantPicker ?? app(MacroVariantPicker::class);
    }

    /**
     * Executa macro em conversa. Retorna info pro Controller flash.
     *
     * US-WA-049 (gap P2 #18): se a macro tem MacroVariants ativas, sorteia
     * uma via weighted random e usa o body da variante (override sem
     * refactor — comportamento default segue inalterado quando 0 variantes).
     *
     * @return array{message_id: ?int, actions_applied: array<int, string>, send_failed: bool, macro_variant_id: ?int}
     */
    public function execute(int $businessId, int $macroId, int $conversationId, int $userId): array
    {
        return OtelHelper::spanBiz('whatsapp.macro.execute', function () use ($businessId, $macroId, $conversationId, $userId) {
            return $this->doExecute($businessId, $macroId, $conversationId, $userId);
        }, [
            'macro_id' => $macroId,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @internal Implementação real do execute — wrapped por OTel span em execute().
     *
     * @return array{message_id: ?int, actions_applied: array<int, string>, send_failed: bool, macro_variant_id: ?int}
     */
    protected function doExecute(int $businessId, int $macroId, int $conversationId, int $userId): array
    {
        $macro = Macro::query()
            ->where('business_id', $businessId)
            ->findOrFail($macroId);

        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel')
            ->findOrFail($conversationId);

        $channel = $conversation->channel;

        // US-WA-049: sorteia variante ativa (null se nenhuma cadastrada).
        $variant = $this->variantPicker->pickFor($macro);
        $bodyToSend = $variant?->body ?? $macro->body;

        // 1+2. Persiste Message outbound — mesmo se driver falhar, audit fica.
        $message = Message::query()->create([
            'business_id' => $businessId,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'provider' => $channel?->type ?? 'unknown',
            'type' => 'text',
            'body' => $bodyToSend,
            'status' => Message::STATUS_QUEUED,
            'sender_user_id' => $userId ?: null,
            'sender_kind' => 'human',
            'is_internal_note' => false,
            'macro_variant_id' => $variant?->id,
        ]);

        // US-WA-049: incrementa sent_count atomicamente (após persist da msg).
        if ($variant) {
            DB::table('macro_variants')
                ->where('id', $variant->id)
                ->where('business_id', $businessId)
                ->increment('sent_count');
        }

        $conversation->forceFill([
            'last_outbound_at' => now(),
            'last_message_at' => now(),
        ])->save();

        $sendFailed = false;

        // 3. Dispatch via daemon Baileys quando aplicável. Outros canais
        // ficam status=failed mas a macro segue (actions aplicam).
        if ($channel && $channel->type === Channel::TYPE_WHATSAPP_BAILEYS) {
            $sendFailed = ! $this->dispatchToBaileysDaemon($message, $channel, $conversation);
        } else {
            $message->forceFill([
                'status' => Message::STATUS_FAILED,
                'failed_reason' => 'Macros só enviam texto via Baileys nesta fase. Canal: ' . ($channel?->type ?? 'null'),
            ])->save();
            $sendFailed = true;
        }

        // 4. Aplica actions_json em ordem.
        $actionsApplied = $this->applyActions($macro, $conversation, $businessId, $userId);

        // 5. Increment used_count atomically.
        DB::table('macros')
            ->where('id', $macro->id)
            ->where('business_id', $businessId)
            ->increment('used_count');

        return [
            'message_id' => $message->id,
            'actions_applied' => $actionsApplied,
            'send_failed' => $sendFailed,
            'macro_variant_id' => $variant?->id,
        ];
    }

    /**
     * Dispara texto via daemon Baileys (mirror do InboxController::send).
     * Retorna true se sucesso, false se falhou (não throws — captura tudo).
     */
    protected function dispatchToBaileysDaemon(Message $message, Channel $channel, Conversation $conversation): bool
    {
        $daemonUrl = config('whatsapp.baileys.daemon_url');
        $apiKey = config('whatsapp.baileys.api_key');
        $instanceId = 'ch-' . str_replace('-', '', (string) $channel->channel_uuid);
        $toPhone = preg_replace('/^\+/', '', (string) $conversation->customer_external_id);

        try {
            /** @var PendingRequest $http */
            $http = Http::withToken($apiKey)
                ->withoutVerifying() // FIXME(US-WA-058) cert LE
                ->timeout(15);

            $response = $http->post("{$daemonUrl}/instances/{$instanceId}/text", [
                'to' => $toPhone,
                'text' => $message->body,
            ]);

            if (! $response->successful()) {
                $message->forceFill([
                    'status' => Message::STATUS_FAILED,
                    'failed_reason' => 'Daemon ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200),
                ])->save();
                Log::warning('[macros.execute] daemon error', [
                    'channel_id' => $channel->id,
                    'status' => $response->status(),
                ]);
                return false;
            }

            $payload = $response->json();
            $message->forceFill([
                'status' => $payload['status'] ?? Message::STATUS_SENT,
                'provider_message_id' => $payload['message_id'] ?? null,
            ])->save();
            return true;
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => Message::STATUS_FAILED,
                'failed_reason' => mb_substr($e->getMessage(), 0, 240),
            ])->save();
            Log::error('[macros.execute] exception', [
                'channel_id' => $channel->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);
            return false;
        }
    }

    /**
     * Aplica array de ações em ordem. Tipo desconhecido = silently skipped.
     * Retorna lista de descritores PT-BR pra flash UI.
     *
     * @return array<int, string>
     */
    protected function applyActions(Macro $macro, Conversation $conversation, int $businessId, int $userId): array
    {
        $applied = [];
        $actions = $macro->actions_json ?? [];
        if (! is_array($actions)) {
            return $applied;
        }

        foreach ($actions as $action) {
            if (! is_array($action) || ! isset($action['type'])) {
                continue;
            }
            $type = (string) $action['type'];

            switch ($type) {
                case Macro::ACTION_ADD_TAG:
                    $tagId = (int) ($action['tag_id'] ?? 0);
                    if ($tagId <= 0) {
                        break;
                    }
                    // Tier 0: tag deve pertencer ao mesmo business
                    $tagOk = Tag::query()
                        ->where('business_id', $businessId)
                        ->where('id', $tagId)
                        ->exists();
                    if (! $tagOk) {
                        break;
                    }
                    $conversation->tags()->syncWithoutDetaching([
                        $tagId => ['created_by_user_id' => $userId ?: null],
                    ]);
                    $applied[] = "tag #{$tagId}";
                    break;

                case Macro::ACTION_SET_STATUS:
                    $status = (string) ($action['status'] ?? '');
                    if (! in_array($status, Conversation::STATUSES, true)) {
                        break;
                    }
                    $conversation->forceFill(['status' => $status])->save();
                    $applied[] = "status={$status}";
                    break;

                case Macro::ACTION_ASSIGN_USER:
                    $assignTo = $action['user_id'] ?? null;
                    if ($assignTo === 'self') {
                        $assignTo = $userId;
                    }
                    $assignTo = $assignTo !== null ? (int) $assignTo : null;
                    if ($assignTo !== null && $assignTo <= 0) {
                        break;
                    }
                    $conversation->forceFill(['assigned_user_id' => $assignTo])->save();
                    $applied[] = $assignTo ? "assign=#{$assignTo}" : 'assign=null';
                    break;

                default:
                    // Forward-compat: tipo desconhecido = skip silenciosamente
                    break;
            }
        }

        return $applied;
    }
}
