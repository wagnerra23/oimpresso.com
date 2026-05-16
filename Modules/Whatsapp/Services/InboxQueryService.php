<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

/**
 * InboxQueryService — facade THIN sobre as queries reusadas por
 * `InboxController` / `CaixaUnificadaController` / `DataController` (sidebar
 * badge unread). Centraliza 4 leituras pra evitar drift entre Controller
 * web (Inertia) e API JSON do DataController.
 *
 * NÃO contém regra nova de negócio — delega pra Entities + Scopes
 * existentes (`Conversation::query()` já aplica global scope `business_id`
 * via trait do módulo). Apenas reúne padrões repetidos em 1 lugar
 * pra fechar gap D4 (Service ratio) sem mexer no comportamento atual.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — TODOS os métodos exigem
 * `$businessId` explícito; nunca depende de session() (compatível com
 * Jobs assíncronos).
 *
 * Canal == fila (ACL via ChannelUserAccess — US-WA-069) — quando $userId
 * informado, restringe aos canais do usuário (gerente vê tudo, atendente
 * vê só os seus).
 *
 * @see Modules\Whatsapp\Http\Controllers\Admin\CaixaUnificadaController
 * @see Modules\Whatsapp\Http\Controllers\DataController
 */
final class InboxQueryService
{
    /**
     * IDs dos canais visíveis a um usuário (todos do business se gerente,
     * subset via channel_user_access se atendente).
     *
     * @return Collection<int>
     */
    public function visibleChannelIdsForUser(int $businessId, ?int $userId = null): Collection
    {
        $channels = Channel::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->pluck('id');

        if ($userId === null) {
            return $channels;
        }

        $hasAcl = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->exists();

        if (! $hasAcl) {
            // Usuário sem ACL = gerente legacy / superadmin → vê tudo do biz
            return $channels;
        }

        return ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->pluck('channel_id');
    }

    /**
     * Conversations paginadas pro painel esquerdo do Cockpit.
     * Status default: open + awaiting_human (escondendo resolved/archived).
     */
    public function listConversations(
        int $businessId,
        ?int $userId = null,
        array $statuses = ['open', 'awaiting_human'],
        int $perPage = 30
    ): LengthAwarePaginator {
        $channelIds = $this->visibleChannelIdsForUser($businessId, $userId);

        return Conversation::query()
            ->where('business_id', $businessId)
            ->whereIn('channel_id', $channelIds)
            ->whereIn('status', $statuses)
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    /**
     * Mensagens de uma conversa em ordem cronológica (oldest first).
     * Filtra notas internas conforme flag — atendente comum não vê notas
     * de outro atendente; gerente ($includeInternalNotes=true) vê tudo.
     */
    public function listMessages(
        int $businessId,
        int $conversationId,
        bool $includeInternalNotes = false,
        int $limit = 200
    ): Collection {
        return Message::query()
            ->where('business_id', $businessId)
            ->where('conversation_id', $conversationId)
            ->when(! $includeInternalNotes, fn (Builder $q) => $q->where('is_internal_note', false))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Badge unread do sidebar (DataController hook).
     * Conta conversas com `unread_count > 0` nos canais visíveis ao usuário.
     */
    public function unreadBadgeForUser(int $businessId, int $userId): int
    {
        $channelIds = $this->visibleChannelIdsForUser($businessId, $userId);

        if ($channelIds->isEmpty()) {
            return 0;
        }

        return Conversation::query()
            ->where('business_id', $businessId)
            ->whereIn('channel_id', $channelIds)
            ->where('unread_count', '>', 0)
            ->whereIn('status', ['open', 'awaiting_human'])
            ->count();
    }
}
