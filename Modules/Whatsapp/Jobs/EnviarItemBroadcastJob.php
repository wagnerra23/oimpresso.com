<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use App\Contact;
use App\Scopes\ScopeByBusiness;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappBroadcast;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;

/**
 * Envia a mensagem de UMA conversa de uma campanha (US-WA-322 · ADR 0268 fase 2).
 *
 * Rechecagem no momento do envio (a campanha pode ter sido agendada dias antes):
 * campanha ainda em `dispatching` (cancelar = sair desse estado), conversa da empresa e do canal
 * da campanha, não bloqueada, contato com opt-in de marketing (`whatsapp_opt_in_at`) e sem
 * recusa de WhatsApp (`canReceiveWhatsappNotification`). Falhou alguma: conta como `pulado`.
 *
 * O envio usa o MESMO caminho da caixa de entrada (`EnvioDeTextoNoCanal`), com a mensagem
 * gravada antes em `queued`. Sem retry: reenvio automático em campanha é mensagem duplicada.
 *
 * Tier 0 (ADR 0093): `$businessId` no construtor; toda leitura filtra por ele.
 */
class EnviarItemBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $businessId, public int $broadcastId, public int $conversationId)
    {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(EnvioDeTextoNoCanal $envio): void
    {
        // SUPERADMIN: job sem sessão; todo acesso abaixo filtra business_id explícito (ADR 0093).
        $b = WhatsappBroadcast::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', $this->broadcastId)->first();
        if ($b === null || $b->status !== 'dispatching') {
            return;
        }

        $conversa = Conversation::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', $this->conversationId)
            ->where('channel_id', (int) $b->channel_id)->where('is_blocked', false)->first();
        $contato = $conversa?->contact_id === null ? null
            : Contact::query()->where('business_id', $this->businessId)->where('id', (int) $conversa->contact_id)->first();
        $canal = $conversa === null ? null : Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', (int) $conversa->channel_id)->first();

        if ($conversa === null || $contato === null || $canal === null
            || $contato->getAttribute('whatsapp_opt_in_at') === null || ! $contato->canReceiveWhatsappNotification()) {
            $this->contar('pulados');

            return;
        }

        $mensagem = Message::query()->create([
            'business_id' => $this->businessId,
            'conversation_id' => $conversa->id,
            'direction' => 'outbound',
            'provider' => $canal->type,
            'type' => 'text',
            'body' => (string) $b->body,
            'status' => 'queued',
            'sender_user_id' => $b->created_by_user_id ?: null,
            'sender_kind' => 'system',
            'is_internal_note' => false,
        ]);
        $conversa->forceFill(['last_outbound_at' => now(), 'last_message_at' => now()])->save();

        $r = $envio->despachar($mensagem, $conversa, $canal, (string) $b->body);
        $this->contar($r['ok'] ? 'enviados' : 'falhas');
    }

    private function contar(string $coluna): void
    {
        $q = DB::table('whatsapp_broadcasts')->where('business_id', $this->businessId)->where('id', $this->broadcastId);
        (clone $q)->increment($coluna);
        (clone $q)->where('status', 'dispatching')
            ->whereRaw('enviados + falhas + pulados >= total')
            ->update(['status' => 'done', 'concluido_em' => now(), 'updated_at' => now()]);
    }
}
