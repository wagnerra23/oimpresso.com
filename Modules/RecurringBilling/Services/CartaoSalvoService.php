<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\RecurringBilling\Models\CartaoSalvo;

/**
 * Guarda o cartão do cliente como token do gateway e o liga à assinatura (US-RB-011).
 *
 * O cartão é digitado na página hospedada do gateway (Asaas). Quando a 1ª fatura é paga assim,
 * o webhook traz `creditCard` com o token, os últimos 4 e a bandeira — é isso que entra aqui.
 *
 * Recusa (e loga sem o valor) qualquer campo com uma sequência de 13 a 19 dígitos: seria número de
 * cartão, e número de cartão não entra no banco, no log nem no teste.
 */
class CartaoSalvoService
{
    /**
     * @param  array{token?:string, ultimos_4?:string, bandeira?:string}  $cartao
     * @return string salvo | ja_salvo | sem_token | recusado_numero
     */
    public function registrar(int $businessId, int $contactId, int $subscriptionId, string $gatewayKey, array $cartao, ?int $cobrancaId = null): string
    {
        foreach ($cartao as $campo => $valor) {
            if (preg_match('/\d{13,19}/', (string) preg_replace('/[\s.\-]/', '', (string) $valor))) {
                Log::warning('recurringbilling.cartao: campo com cara de número de cartão recusado', [
                    'business_id' => $businessId, 'campo' => $campo,
                ]);

                return 'recusado_numero';
            }
        }

        $token = trim((string) ($cartao['token'] ?? ''));
        if ($token === '') {
            return 'sem_token';
        }

        $digitos = (string) preg_replace('/\D/', '', (string) ($cartao['ultimos_4'] ?? ''));
        $ultimos4 = $digitos === '' ? null : substr($digitos, -4);
        $bandeira = trim((string) ($cartao['bandeira'] ?? ''));

        return DB::transaction(function () use ($businessId, $contactId, $subscriptionId, $gatewayKey, $token, $ultimos4, $bandeira, $cobrancaId) {
            $hash = hash('sha256', $gatewayKey.'|'.$token);
            // SUPERADMIN: chamado pelo webhook, sem sessão; o business vem do evento.
            $cartao = CartaoSalvo::withoutGlobalScopes()->where('business_id', $businessId)
                ->where('token_hash', $hash)->first();
            $resultado = $cartao ? 'ja_salvo' : 'salvo';
            // SUPERADMIN: idem — o business_id é gravado explícito.
            $cartao ??= CartaoSalvo::withoutGlobalScopes()->create([
                'business_id' => $businessId, 'contact_id' => $contactId, 'gateway_key' => $gatewayKey,
                'token' => $token, 'token_hash' => $hash, 'ultimos_4' => $ultimos4,
                'bandeira' => $bandeira === '' ? null : substr($bandeira, 0, 30),
                'status' => 'ativo', 'origem_cobranca_id' => $cobrancaId,
            ]);

            // Só liga se a assinatura ainda não tem cartão: trocar de cartão é ato do cliente/gestor.
            DB::table('rb_subscriptions')->where('business_id', $businessId)->where('id', $subscriptionId)
                ->whereNull('cartao_salvo_id')->update(['cartao_salvo_id' => $cartao->id, 'updated_at' => now()]);

            return $resultado;
        });
    }
}
