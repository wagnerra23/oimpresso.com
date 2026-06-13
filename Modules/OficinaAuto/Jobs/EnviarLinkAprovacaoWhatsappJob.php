<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Jobs;

use App\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;

/**
 * US-OFICINA-014 — Envia link público + PIN via WhatsApp pra cliente aprovar OS.
 *
 * Disparado pelo `ServiceOrderObserver::updated` quando OS transiciona pra
 * `status='orcamento'` (Wave 4.2). Best-effort: falha de notificação NÃO bloqueia
 * fluxo da oficina (operador segue contato manual se job falhar todas as tentativas).
 *
 * Fluxo de 2 mensagens (anti-hook charter "out-of-band"):
 *   1. Msg 1 freeform: "Olá! Aprove o orçamento da sua OS: <link>"
 *   2. Msg 2 freeform delayed +60s: "Seu PIN de aprovação é: 1234 — válido por 7 dias"
 *
 * Por que 60s delay entre msgs: charter Anti-hook obriga PIN separado do link.
 * Ideal seria SMS pro PIN (canal real out-of-band), mas até provider SMS chegar,
 * delay temporal no mesmo canal mitiga (atacante precisa ler 2 msgs separadas).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `$businessId` no constructor (NUNCA `session()` em job). Guard defensivo confere
 * `$so->business_id === $this->businessId` e apenas loga em mismatch (sem throw
 * pra não retryar infinito — pattern Whatsapp `NotificarClienteCancelamentoJob`).
 *
 * LGPD: respeita `Contact::canReceiveWhatsappNotification()` antes de dispatchar
 * SendWhatsappMessageJob. Sem fallback email aqui (link público + PIN só faz
 * sentido via canal mobile-aware).
 *
 * Idempotência (defesa contra trigger duplo do Observer):
 * Cache key `oficina:approval_dispatched:{os_id}` TTL 7 dias (= TOKEN_TTL).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-014
 * @see resources/js/Pages/OficinaAuto/AprovacaoPublica.charter.md
 * @see Modules/OficinaAuto/Services/AprovacaoOsService.php
 * @see Modules/Whatsapp/Jobs/NotificarClienteCancelamentoJob.php (pattern mãe)
 */
class EnviarLinkAprovacaoWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /** Cache TTL idempotência — deve bater com TOKEN_TTL_DAYS do Service. */
    private const DISPATCH_CACHE_TTL_DAYS = 7;

    /** Delay entre msg 1 (link) e msg 2 (PIN) — anti-hook charter "out-of-band". */
    private const PIN_DELAY_SECONDS = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly int $serviceOrderId,
    ) {}

    public function handle(AprovacaoOsService $aprovacaoService): void
    {
        // Idempotência — se já dispatched recentemente, skip (defesa Observer trigger duplo).
        $idempotencyKey = "oficina:approval_dispatched:{$this->serviceOrderId}";
        if (Cache::has($idempotencyKey)) {
            Log::info('[oficina.approval_whatsapp] skip · já dispatched recentemente', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
            ]);
            return;
        }

        // SUPERADMIN: job de fila roda sem session — bypass do global scope com filtro
        // explícito por business_id (Tier 0 guard manual, ADR 0093).
        $so = ServiceOrder::query()
            ->withoutGlobalScopes()
            ->where('id', $this->serviceOrderId)
            ->where('business_id', $this->businessId)
            ->first();

        if ($so === null) {
            Log::info('[oficina.approval_whatsapp] ServiceOrder não encontrada (deletada ou cross-tenant) — skip', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
            ]);
            return;
        }

        // Status mudou no meio do caminho? Job só faz sentido se ainda orcamento.
        if ($so->status !== 'orcamento') {
            Log::info('[oficina.approval_whatsapp] status mudou pós-dispatch — skip', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
                'status_atual' => $so->status,
            ]);
            return;
        }

        if (empty($so->contact_id)) {
            Log::info('[oficina.approval_whatsapp] OS sem contact_id (walk-in?) — skip', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
            ]);
            return;
        }

        $contact = Contact::find($so->contact_id);
        if ($contact === null) {
            Log::info('[oficina.approval_whatsapp] contact não encontrado — skip', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
                'contact_id' => (int) $so->contact_id,
            ]);
            return;
        }

        $phoneNumber = $this->resolveContactPhone($contact);
        if ($phoneNumber === null) {
            Log::warning('[oficina.approval_whatsapp] contact sem mobile/landline — operador contata manual', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
                'contact_id' => (int) $contact->id,
            ]);
            return;
        }

        // LGPD: opt-out explícito bloqueia (charter Anti-hook + ADR 0093).
        if (! $contact->canReceiveWhatsappNotification()) {
            Log::info('[oficina.approval_whatsapp] WhatsApp consent=false — skip (LGPD)', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
                'contact_id' => (int) $contact->id,
            ]);
            return;
        }

        $outboundPhone = $this->resolveOutboundDefaultPhone($this->businessId);
        if ($outboundPhone === null) {
            Log::info('[oficina.approval_whatsapp] business sem phone outbound_default configurado — skip', [
                'business_id' => $this->businessId,
                'service_order_id' => $this->serviceOrderId,
            ]);
            return;
        }

        // Gera token+PIN via Service (HMAC + cache PIN hash + reset attempts counter).
        $approval = $aprovacaoService->gerarTokenAprovacao($so);
        $token = $approval['token'];
        $pin = $approval['pin'];

        $link = URL::to('/aprovar-os/' . $token);

        // Marca idempotência ANTES de dispatch — evita race condition se Observer trigger 2x.
        Cache::put($idempotencyKey, true, now()->addDays(self::DISPATCH_CACHE_TTL_DAYS));

        // Msg 1: link (imediato)
        SendWhatsappMessageJob::dispatch(
            $this->businessId,
            $outboundPhone->id,
            $phoneNumber,
            'freeform',
            ['body' => $this->renderLinkMessage($so, $link)],
        );

        // Msg 2: PIN (delayed 60s — anti-hook charter out-of-band)
        SendWhatsappMessageJob::dispatch(
            $this->businessId,
            $outboundPhone->id,
            $phoneNumber,
            'freeform',
            ['body' => $this->renderPinMessage($pin)],
        )->delay(now()->addSeconds(self::PIN_DELAY_SECONDS));

        Log::info('[oficina.approval_whatsapp] link + PIN dispatched', [
            'business_id' => $this->businessId,
            'service_order_id' => $this->serviceOrderId,
            'contact_id' => (int) $contact->id,
            'phone_id' => $outboundPhone->id,
            'token_prefix' => substr($token, 0, 8) . '...',  // só prefix pra audit (sem leak)
        ]);
    }

    public function tags(): array
    {
        return [
            "business:{$this->businessId}",
            "service_order:{$this->serviceOrderId}",
            'oficina:approval_whatsapp',
        ];
    }

    private function resolveContactPhone(Contact $contact): ?string
    {
        $mobile = trim((string) ($contact->mobile ?? ''));
        if ($mobile !== '') {
            return $mobile;
        }
        $landline = trim((string) ($contact->landline ?? ''));
        if ($landline !== '') {
            return $landline;
        }

        return null;
    }

    private function resolveOutboundDefaultPhone(int $businessId): ?WhatsappBusinessPhone
    {
        // SUPERADMIN: job sem session — bypass global scope com filtro explícito (Tier 0).
        return WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('handles_outbound_default', true)
            ->orderBy('id')
            ->first();
    }

    private function renderLinkMessage(ServiceOrder $so, string $link): string
    {
        $numero = 'OS-' . str_pad((string) $so->id, 5, '0', STR_PAD_LEFT);

        return <<<TXT
            Olá! Sua Ordem de Serviço {$numero} está pronta pra aprovação.

            Revise o orçamento e aprove ou rejeite no link abaixo:
            {$link}

            (Link válido por 7 dias)
            TXT;
    }

    private function renderPinMessage(string $pin): string
    {
        return <<<TXT
            Seu PIN de aprovação é: {$pin}

            Digite no link enviado pra confirmar.
            Não compartilhe este código com ninguém.
            TXT;
    }
}
