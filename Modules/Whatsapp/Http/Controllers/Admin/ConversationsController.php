<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;

/**
 * Inbox conversas — Cockpit pattern (lista esquerda + chat painel direita).
 *
 * Decisão visual canon: ADR 0039 (Chat Cockpit). Real-time via Centrifugo
 * channel `whatsapp:business:{id}`.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-012
 */
class ConversationsController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = (string) $request->query('tab', 'all'); // all|unread|assigned|bot|resolved
        $businessId = (int) session('user.business_id');

        $query = WhatsappConversation::query()
            ->with('contact:id,name')
            ->orderByDesc('last_message_at');

        $query = match ($tab) {
            'unread' => $query->where('unread_count', '>', 0),
            'assigned' => $query->where('assigned_user_id', $request->user()?->id),
            'bot' => $query->where('bot_handling', true),
            'resolved' => $query->where('status', 'resolved'),
            default => $query->whereNotIn('status', ['archived']),
        };

        $conversations = $query->paginate(50)->through(fn ($c) => [
            'id' => $c->id,
            'customer_phone' => $c->customer_phone,
            'contact_name' => $c->contact?->name ?? $c->customer_phone,
            'status' => $c->status,
            'unread_count' => $c->unread_count,
            'bot_handling' => (bool) $c->bot_handling,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'within_24h_window' => $c->isWithinMeta24hWindow(),
        ]);

        $stats = [
            'unread' => WhatsappConversation::where('unread_count', '>', 0)->count(),
            'assigned' => WhatsappConversation::where('assigned_user_id', $request->user()?->id)->count(),
            'bot' => WhatsappConversation::where('bot_handling', true)->count(),
        ];

        return Inertia::render('Whatsapp/Conversations/Index', [
            'conversations' => $conversations,
            'tab' => $tab,
            'stats' => $stats,
            'businessId' => $businessId,
        ]);
    }

    public function show(int $id): Response
    {
        $conversation = WhatsappConversation::with('contact:id,name')->findOrFail($id);

        $messages = WhatsappMessage::where('conversation_id', $id)
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'provider' => $m->provider,
                'type' => $m->type,
                'body' => $m->body,
                'status' => $m->status,
                'failed_reason' => $m->failed_reason,
                'sender_kind' => $m->sender_kind,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        // Marca como lida (zera unread_count)
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return Inertia::render('Whatsapp/Conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'customer_phone' => $conversation->customer_phone,
                'contact_name' => $conversation->contact?->name ?? $conversation->customer_phone,
                'status' => $conversation->status,
                'within_24h_window' => $conversation->isWithinMeta24hWindow(),
                'last_inbound_at' => optional($conversation->last_inbound_at)->toIso8601String(),
            ],
            'messages' => $messages,
            'centrifugoChannel' => "whatsapp:business:{$conversation->business_id}",
        ]);
    }
}
