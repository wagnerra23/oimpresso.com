<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\RecurringBilling\Services\CartaoSalvoService;

/**
 * Fatura de assinatura paga no cartão → guarda o token do cartão para as próximas (US-RB-011).
 *
 * Lê o `creditCard` que o Asaas manda no webhook da cobrança (token, últimos 4, bandeira) e entrega
 * ao `CartaoSalvoService`. Não mexe em valor nem em status de fatura.
 *
 * Desligado por padrão (`recurringbilling.salvar_cartao`). Tier 0: tudo lido no `business_id` do
 * evento. Falha vai para o log — o webhook nunca cai por isto.
 */
final class SalvarCartaoDaCobrancaPaga
{
    public function handle(CobrancaPaga $event): void
    {
        if (! (bool) config('recurringbilling.salvar_cartao', false)) {
            return;
        }

        try {
            $this->salvar($event);
        } catch (\Throwable $e) {
            Log::error('recurringbilling.cartao: falha ao salvar o cartão da cobrança', [
                'cobranca_id' => $event->cobrancaId, 'business_id' => $event->businessId, 'erro' => $e->getMessage(),
            ]);
        }
    }

    /** @return string o resultado do CartaoSalvoService::registrar, ou `ignorado` / `sem_token` */
    public function salvar(CobrancaPaga $event): string
    {
        $biz = $event->businessId;
        // SUPERADMIN: webhook sem sessão; o business vem do evento e filtra a busca.
        $cob = Cobranca::withoutGlobalScopes()->where('business_id', $biz)->find($event->cobrancaId);
        if ($cob === null || $cob->origem_type !== 'invoice' || ! $cob->origem_id || $cob->forma_pagamento !== 'cartao') {
            return 'ignorado';
        }

        $fatura = DB::table('rb_invoices')->where('business_id', $biz)->where('id', (int) $cob->origem_id)
            ->first(['subscription_id', 'contact_id']);
        if ($fatura === null || ! $fatura->subscription_id) {
            return 'ignorado';
        }

        // SUPERADMIN: idem — eventos do webhook desta cobrança, no business do evento.
        $eventos = GatewayWebhookEvent::withoutGlobalScopes()->where('business_id', $biz)
            ->where('cobranca_id', $cob->id)->orderByDesc('id')->get(['gateway_key', 'payload']);
        foreach ($eventos as $e) {
            $cartao = (array) ($e->payload['payment']['creditCard'] ?? []);
            if (empty($cartao['creditCardToken'])) {
                continue;
            }

            return app(CartaoSalvoService::class)->registrar($biz, (int) $fatura->contact_id, (int) $fatura->subscription_id,
                (string) $e->gateway_key, [
                    'token' => (string) $cartao['creditCardToken'],
                    'ultimos_4' => (string) ($cartao['creditCardNumber'] ?? ''),
                    'bandeira' => (string) ($cartao['creditCardBrand'] ?? ''),
                ], (int) $cob->id);
        }

        return 'sem_token';
    }
}
