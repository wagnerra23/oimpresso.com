<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer;

/**
 * InboxController — UI omnichannel `/atendimento/inbox` (ADR 0135 Fase 0).
 *
 * Substitui long-term `/whatsapp/conversations` legacy. Lê do schema novo
 * (Channel + Conversation + Message polimórficos) em vez de
 * WhatsappConversation/WhatsappMessage.
 *
 * Coexiste com `/whatsapp/conversations` durante PR B+C. Drivers/webhooks
 * legacy ainda processam Z-API/Meta Cloud no schema antigo até migration
 * completa.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class InboxController extends Controller
{
    public function index(Request $request, CentrifugoTokenIssuer $tokenIssuer): Response
    {
        $businessId = (int) session('user.business_id');
        $tab = $request->input('tab', 'all');
        $q = $request->input('q', '');
        $threadId = $request->input('thread');
        $channelFilter = $request->input('channel'); // tipo: whatsapp_baileys, etc

        $convQuery = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel:id,label,type,status,channel_uuid,channel_health');

        // Filtros
        switch ($tab) {
            case 'unread':
                $convQuery->where('unread_count', '>', 0);
                break;
            case 'assigned':
                $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
                $convQuery->where('assigned_user_id', $userId);
                break;
            case 'bot':
                $convQuery->where('bot_handling', true);
                break;
            case 'resolved':
                $convQuery->where('status', 'resolved');
                break;
        }

        if ($q !== '') {
            $convQuery->where(function ($x) use ($q) {
                $x->where('contact_name', 'LIKE', "%{$q}%")
                  ->orWhere('customer_external_id', 'LIKE', "%{$q}%");
            });
        }

        if ($channelFilter) {
            $convQuery->whereHas('channel', fn ($c) => $c->where('type', $channelFilter));
        }

        $paginated = $convQuery
            ->orderByDesc('last_message_at')
            ->paginate(50);

        $conversationsForUi = $paginated->getCollection()->map(fn (Conversation $c) => $this->convToListArray($c));

        // Stats counters
        $stats = [
            'unread' => Conversation::query()->where('business_id', $businessId)->where('unread_count', '>', 0)->count(),
            'assigned' => Conversation::query()->where('business_id', $businessId)
                ->where('assigned_user_id', (int) (session('user.id') ?? auth()->id() ?? 0))->count(),
            'bot' => Conversation::query()->where('business_id', $businessId)->where('bot_handling', true)->count(),
        ];

        // Thread aberta?
        $thread = null;
        $messages = null;
        if ($threadId) {
            $threadModel = Conversation::query()
                ->where('business_id', $businessId)
                ->with('channel')
                ->find($threadId);
            if ($threadModel) {
                $thread = $this->convToThreadArray($threadModel);
                $messages = Message::query()
                    ->where('business_id', $businessId)
                    ->where('conversation_id', $threadId)
                    ->orderBy('created_at')
                    ->limit(200)
                    ->get()
                    ->map(fn (Message $m) => $this->msgToUiArray($m));

                // Zera unread quando abre
                if ($threadModel->unread_count > 0) {
                    $threadModel->forceFill(['unread_count' => 0])->save();
                }
            }
        }

        // Channels disponíveis pra filtro
        $availableChannels = Channel::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('label')
            ->get(['id', 'label', 'type'])
            ->map(fn ($ch) => ['id' => $ch->id, 'label' => $ch->label, 'type' => $ch->type]);

        // Centrifugo real-time (ADR 0058 + US-WA-059) — channel
        // `omnichannel:business:{id}` segregado por business_id (Tier 0).
        // Token JWT HS256 ttl curto, re-emitido a cada page load. Se
        // emissor falhar (secret ausente, etc), payload vira null e o
        // frontend cai pra polling fallback.
        $channel = "omnichannel:business:{$businessId}";
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $token = $tokenIssuer->issue(
            $userId,
            [$channel],
            (int) config('whatsapp.centrifugo.token_ttl_seconds', 3600)
        );
        $centrifugoConfig = $token !== null ? [
            'wsUrl' => config('whatsapp.centrifugo.ws_url'),
            'token' => $token,
            'channel' => $channel,
        ] : null;

        return Inertia::render('Atendimento/Inbox/Index', [
            'conversations' => [
                'data' => $conversationsForUi,
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
            'tab' => $tab,
            'q' => $q,
            'channelFilter' => $channelFilter,
            'stats' => $stats,
            'businessId' => $businessId,
            'thread' => $thread,
            'messages' => $messages,
            'availableChannels' => $availableChannels,
            'centrifugoConfig' => $centrifugoConfig,
        ]);
    }

    /**
     * Conversation pro componente lista (sidebar esquerda).
     */
    protected function convToListArray(Conversation $c): array
    {
        $channel = $c->channel;
        return [
            'id' => $c->id,
            'channel_id' => $c->channel_id,
            'channel_label' => $channel?->label,
            'channel_type' => $channel?->type,
            'customer_external_id' => $c->customer_external_id,
            'contact_name' => $c->contact_name ?? $c->customer_external_id,
            'status' => $c->status,
            'unread_count' => (int) $c->unread_count,
            'bot_handling' => (bool) $c->bot_handling,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            // Window 24h Meta — só pra type=whatsapp_meta
            'within_24h_window' => $channel?->type === 'whatsapp_meta'
                ? ($c->last_inbound_at && $c->last_inbound_at->diffInHours(now()) < 24)
                : true, // Z-API/Baileys/Insta/etc não têm essa restrição
        ];
    }

    /**
     * Conversation pro componente thread (header central).
     */
    protected function convToThreadArray(Conversation $c): array
    {
        $channel = $c->channel;
        return [
            'id' => $c->id,
            'channel_id' => $c->channel_id,
            'channel_label' => $channel?->label,
            'channel_type' => $channel?->type,
            'customer_external_id' => $c->customer_external_id,
            'contact_name' => $c->contact_name ?? $c->customer_external_id,
            'status' => $c->status,
            'bot_handling' => (bool) $c->bot_handling,
            'within_24h_window' => $channel?->type === 'whatsapp_meta'
                ? ($c->last_inbound_at && $c->last_inbound_at->diffInHours(now()) < 24)
                : true,
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'created_at' => optional($c->created_at)->toIso8601String(),
            'messages_total' => $c->messages()->count(),
        ];
    }

    /**
     * Message pro componente thread (bubble individual).
     */
    protected function msgToUiArray(Message $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'provider' => $m->provider,
            'type' => $m->type,
            'body' => $m->body,
            'status' => $m->status,
            'failed_reason' => $m->failed_reason,
            'sender_kind' => $m->sender_kind,
            'created_at' => $m->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
