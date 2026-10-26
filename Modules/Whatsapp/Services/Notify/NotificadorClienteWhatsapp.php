<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notify;

use App\Contact;
use App\Scopes\ScopeByBusiness;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * Aviso automático ao cliente por WhatsApp, pela API, sem clique humano (US-WA-319).
 *
 * Um ponto só para todo módulo que precisa avisar o cliente de um evento — venda, pagamento,
 * cobrança, OS — com texto e anexos (boleto, DANFE, Pix). Antes, cada módulo repetia as mesmas
 * travas num listener próprio (`NotifyRepairCustomer`, `NotifyInstalacaoCvCustomer`) e o núcleo
 * (`NotificationUtil::autoSendNotification`) só gerava um link para alguém clicar.
 *
 * Travas, na ordem:
 * 1. **Número:** `billing` usa o número marcado para cobrança (com fallback no de saída padrão,
 *    `WhatsappBusinessPhone::resolveForEvent`); `outbound_default` usa o de saída padrão. Número
 *    caído (`disconnected`/`banned`) conta como ausente.
 * 2. **Cliente:** sempre o da MESMA empresa (Tier 0, ADR 0093) — contato de outra empresa não recebe.
 * 3. **LGPD:** `Contact::canReceiveWhatsappNotification()` — opt-out explícito bloqueia, null permite.
 * 4. **Celular** preenchido.
 *
 * Nada é enviado no request: enfileira 1 `SendWhatsappMessageJob` freeform com o texto e 1 `media`
 * por anexo. Nunca lança por falta de pré-condição — devolve o motivo e loga sem PII. Quem gera o
 * anexo gera também a URL (assinada/temporária); este serviço só a repassa ao driver.
 *
 * ⚠️ Texto livre fora da janela de 24h é recusado pela Meta Cloud; no driver não-oficial passa.
 * Template HSM por evento é passo seguinte, não deste serviço.
 */
class NotificadorClienteWhatsapp
{
    public const EVENTOS = ['billing', 'outbound_default'];

    public const TIPOS_ANEXO = ['document', 'image'];

    /** Saúde em que o módulo Whatsapp não entrega nem pelo fallback. */
    private const CAIDO = ['disconnected', 'banned'];

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

        $phone = $this->resolverNumero($businessId, $evento);
        if ($phone === null) {
            Log::info('[whatsapp.notificador] sem número ativo para o evento — skip', $ctx);

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
        $para = trim((string) ($contato->mobile ?? ''));
        if ($para === '') {
            Log::info('[whatsapp.notificador] cliente sem celular — skip', $ctx);

            return ResultadoNotificacao::SemCelular;
        }

        SendWhatsappMessageJob::dispatch($businessId, (int) $phone->id, $para, 'freeform', ['body' => $texto]);
        foreach ($anexos as $anexo) {
            SendWhatsappMessageJob::dispatch($businessId, (int) $phone->id, $para, 'media', $anexo);
        }

        return ResultadoNotificacao::Enviado;
    }

    private function resolverNumero(int $businessId, string $evento): ?WhatsappBusinessPhone
    {
        $phone = $evento === 'billing'
            ? WhatsappBusinessPhone::resolveForEvent($businessId, 'billing')
            // SUPERADMIN: chamado de job/listener sem sessão; filtro explícito por business_id (ADR 0093).
            : WhatsappBusinessPhone::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)
                ->where('handles_outbound_default', true)
                ->orderBy('id')
                ->first();

        if ($phone === null || in_array((string) $phone->driver_health, self::CAIDO, true)) {
            return null;
        }

        return $phone;
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
