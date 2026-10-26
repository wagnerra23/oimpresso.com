<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notify;

use App\Contact;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Jobs\DespacharMensagemDoSistemaJob;

/**
 * Aviso automático ao cliente por WhatsApp, pela API, sem clique humano (US-WA-319).
 *
 * Um ponto só para todo módulo que precisa avisar o cliente de um evento — venda, pagamento,
 * cobrança, OS — com texto e anexos (boleto, DANFE, Pix). Antes, o núcleo
 * (`NotificationUtil::autoSendNotification`) só gerava um link para alguém clicar.
 *
 * Envia pelo schema omnichannel (`channels`/`conversations`/`messages`), o mesmo da Caixa
 * Unificada: medido em 2026-10-26, produção tem 0 linhas em `whatsapp_business_phones` e os
 * canais vivos são `channels` whatsmeow — um aviso resolvido pela tabela antiga nunca sairia.
 *
 * Travas, na ordem:
 * 1. **Canal:** canal WhatsApp ativo da empresa. `billing` prefere o marcado para cobrança
 *    (`handles_billing`), com fallback no de saída padrão; `outbound_default` usa o de saída
 *    padrão (`handles_outbound_default`). Canal com saúde `disconnected`/`banned` não serve.
 * 2. **Cliente:** sempre o da MESMA empresa (Tier 0, ADR 0093).
 * 3. **LGPD:** `Contact::canReceiveWhatsappNotification()` — opt-out explícito bloqueia, null permite.
 * 4. **Celular** preenchido.
 *
 * A conversa do cliente no canal é reaproveitada (por cliente ou número) ou criada; a mensagem
 * nasce `queued`, `sender_kind=system`, e o envio ao daemon vai para a fila
 * (`DespacharMensagemDoSistemaJob`). Os canais vivos só enviam texto, então os anexos viram
 * links no fim da mensagem (a URL é de quem chamou, assinada/temporária). Nunca lança por falta
 * de pré-condição — devolve o motivo e loga sem PII.
 */
class NotificadorClienteWhatsapp
{
    public const EVENTOS = ['billing', 'outbound_default'];

    public const TIPOS_ANEXO = ['document', 'image'];

    /** Saúde em que o canal não entrega. */
    private const CAIDO = ['disconnected', 'banned'];

    private const TIPOS_CANAL = [Channel::TYPE_WHATSAPP_WHATSMEOW, Channel::TYPE_WHATSAPP_BAILEYS];

    /**
     * @param  list<array{url: string, type?: string, caption?: string|null}>  $anexos
     */
    public function notificar(int $businessId, int $contactId, string $evento, string $texto, array $anexos = []): ResultadoNotificacao
    {
        if (! in_array($evento, self::EVENTOS, true)) {
            throw new \InvalidArgumentException("Evento '{$evento}' inválido. Use: ".implode('|', self::EVENTOS).'.');
        }
        $anexos = array_map(fn ($a) => $this->validarAnexo($a), $anexos);
        $ctx = ['business_id' => $businessId, 'contact_id' => $contactId, 'evento' => $evento];

        $canal = $this->resolverCanal($businessId, $evento);
        if ($canal === null) {
            Log::info('[whatsapp.notificador] sem canal ativo para o evento — skip', $ctx);

            return ResultadoNotificacao::SemNumeroConfigurado;
        }

        $contato = Contact::query()->where('business_id', $businessId)->where('id', $contactId)->first();
        if ($contato === null) {
            Log::info('[whatsapp.notificador] contato não é desta empresa — skip', $ctx);

            return ResultadoNotificacao::ContatoNaoEncontrado;
        }
        if (! $contato->canReceiveWhatsappNotification()) {
            Log::info('[whatsapp.notificador] cliente recusou WhatsApp (LGPD) — skip', $ctx);

            return ResultadoNotificacao::RecusouWhatsapp;
        }
        $numero = self::numeroE164((string) ($contato->mobile ?? ''));
        if ($numero === null) {
            Log::info('[whatsapp.notificador] cliente sem celular válido — skip', $ctx);

            return ResultadoNotificacao::SemCelular;
        }

        $conversa = $this->conversaDoCliente($businessId, $canal, $contato, $numero);
        $corpo = $this->comAnexos($texto, $anexos);
        $mensagem = Message::query()->create([
            'business_id' => $businessId,
            'conversation_id' => $conversa->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'provider' => $canal->type,
            'type' => 'text',
            'body' => $corpo,
            'status' => 'queued',
            'sender_kind' => 'system',
            'is_internal_note' => false,
        ]);
        $conversa->forceFill(['last_outbound_at' => now(), 'last_message_at' => now()])->save();

        DespacharMensagemDoSistemaJob::dispatch($businessId, (int) $mensagem->id);

        return ResultadoNotificacao::Enviado;
    }

    /** Celular brasileiro sem DDI ganha 55; o resultado é o `customer_external_id` canônico ("+55…"). */
    public static function numeroE164(string $celular): ?string
    {
        $digitos = preg_replace('/\D+/', '', $celular) ?? '';
        if (strlen($digitos) < 10) {
            return null;
        }
        if (strlen($digitos) <= 11) {
            $digitos = '55'.$digitos;
        }

        return '+'.$digitos;
    }

    private function resolverCanal(int $businessId, string $evento): ?Channel
    {
        // SUPERADMIN: chamado de job/listener sem sessão; filtro explícito por business_id (ADR 0093).
        $base = Channel::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->whereIn('type', self::TIPOS_CANAL)
            ->where('status', 'active')
            ->whereNotIn('channel_health', self::CAIDO)
            ->orderBy('id');

        $especifico = $evento === 'billing' ? (clone $base)->where('handles_billing', true)->first() : null;

        return $especifico ?? (clone $base)->where('handles_outbound_default', true)->first();
    }

    private function conversaDoCliente(int $businessId, Channel $canal, Contact $contato, string $numero): Conversation
    {
        // SUPERADMIN: idem — conversa da empresa e do canal resolvidos acima.
        $q = Conversation::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)->where('channel_id', $canal->id);

        $conversa = (clone $q)->where('contact_id', $contato->id)->orderByDesc('last_message_at')->first()
            ?? (clone $q)->where('customer_external_id', $numero)->first();
        if ($conversa !== null) {
            if ($conversa->contact_id === null) {
                $conversa->forceFill(['contact_id' => $contato->id])->save();
            }

            return $conversa;
        }

        return Conversation::query()->withoutGlobalScope(ScopeByBusiness::class)->create([
            'business_id' => $businessId,
            'channel_id' => $canal->id,
            'contact_id' => $contato->id,
            'customer_external_id' => $numero,
            'contact_name' => (string) ($contato->name ?: $numero),
            'status' => 'open',
            'last_message_at' => now(),
        ]);
    }

    /** @param list<array{url: string, type: string, caption: string|null}> $anexos */
    private function comAnexos(string $texto, array $anexos): string
    {
        $linhas = array_map(fn ($a) => ($a['caption'] ? $a['caption'].': ' : '').$a['url'], $anexos);

        return $linhas === [] ? $texto : trim($texto)."\n\n".implode("\n", $linhas);
    }

    /**
     * @param  array<string, mixed>  $anexo
     * @return array{url: string, type: string, caption: string|null}
     */
    private function validarAnexo(array $anexo): array
    {
        $url = (string) ($anexo['url'] ?? '');
        if (! preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('Anexo precisa de URL absoluta (http/https).');
        }
        $tipo = (string) ($anexo['type'] ?? 'document');
        if (! in_array($tipo, self::TIPOS_ANEXO, true)) {
            throw new \InvalidArgumentException("Tipo de anexo '{$tipo}' inválido. Use: ".implode('|', self::TIPOS_ANEXO).'.');
        }

        return ['url' => $url, 'type' => $tipo, 'caption' => isset($anexo['caption']) ? (string) $anexo['caption'] : null];
    }
}
