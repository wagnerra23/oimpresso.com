<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use App\Scopes\ScopeByBusiness;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Inbox\EnvioDeTextoNoCanal;

/**
 * Envia ao daemon uma mensagem do sistema já gravada em `queued` (US-WA-319).
 *
 * Usa o mesmo `EnvioDeTextoNoCanal` da Caixa Unificada. Só envia mensagem `queued` de saída da
 * própria empresa — reexecução não reenvia. Sem retry: o desfecho (sent/failed + motivo) fica na
 * mensagem, visível na caixa.
 *
 * Tier 0 (ADR 0093): `$businessId` no construtor; toda leitura filtra por ele.
 */
class DespacharMensagemDoSistemaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $businessId, public int $messageId)
    {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(EnvioDeTextoNoCanal $envio): void
    {
        // SUPERADMIN: job sem sessão; filtro explícito por business_id (ADR 0093).
        $mensagem = Message::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', $this->messageId)
            ->where('direction', Message::DIRECTION_OUTBOUND)->where('status', 'queued')->first();
        if ($mensagem === null) {
            return;
        }
        $conversa = Conversation::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', (int) $mensagem->conversation_id)->first();
        $canal = $conversa === null ? null : Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)->where('id', (int) $conversa->channel_id)->first();
        if ($conversa === null || $canal === null) {
            $mensagem->forceFill(['status' => 'failed', 'failed_reason' => 'Conversa ou canal não encontrado.'])->save();

            return;
        }

        $envio->despachar($mensagem, $conversa, $canal, (string) $mensagem->body);
    }
}
