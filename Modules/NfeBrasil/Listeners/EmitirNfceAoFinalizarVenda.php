<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Listeners;

use App\Events\SellCreatedOrModified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Jobs\EmitirNfceJob;
use Modules\NfeBrasil\Models\NfeBusinessConfig;

/**
 * US-NFE-002 fase 1 · Listener `SellCreatedOrModified` → dispatch `EmitirNfceJob`.
 *
 * **Como funciona:**
 * Core UltimatePOS dispara `App\Events\SellCreatedOrModified` quando uma venda
 * é criada ou modificada. Filtramos só transactions tipo `sell` com
 * `status='final'` e `payment_status` em (`paid`,`partial`) — vendas em rascunho
 * (`status='draft'`) ou pendentes não emitem NFC-e.
 *
 * Flag `nfebrasil.auto_emission_on_sell_completed` (default `false`) controla
 * ativação. Quando OFF: log only, no-op (importante pra rollout gradual).
 *
 * **Diferença vs `EmitirNFeAoReceberPagamento` (NFe55):**
 *   - Esse: modelo 65 (NFC-e B2C), gatilho = venda finalizada no POS
 *   - Aquele: modelo 55 (NFe B2B), gatilho = boleto pago em RecurringBilling
 *
 * Idempotência fica no Job (UNIQUE constraint nfe_emissoes + check explícito).
 *
 * @see memory/requisitos/NfeBrasil/SPEC.md US-NFE-002
 * @see Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php (pattern NFe55)
 */
class EmitirNfceAoFinalizarVenda implements ShouldQueue
{
    public string $queue = 'nfe';

    public function handle(SellCreatedOrModified $event): void
    {
        $tx = $event->transaction;
        $businessId = (int) $tx->business_id;
        $transactionId = (int) $tx->id;

        Log::info('NFC-e listener triggered', [
            'business_id'    => $businessId,
            'transaction_id' => $transactionId,
            'type'           => $tx->type ?? null,
            'status'         => $tx->status ?? null,
            'payment_status' => $tx->payment_status ?? null,
            'enabled'        => config('nfebrasil.auto_emission_on_sell_completed', false),
        ]);

        // Filtros de elegibilidade:
        //  - Tem que ser uma venda (type='sell') final (status='final')
        //  - Pagamento confirmado (paid|partial). Vendas a prazo "due" não emitem
        //    NFC-e — modelo 65 é pra varejo presencial com pagamento à vista.
        //    Pra crédito/parcelado modelo correto é NFe (55).
        if (($tx->type ?? null) !== 'sell') return;
        if (($tx->status ?? null) !== 'final') return;
        if (! in_array($tx->payment_status ?? null, ['paid', 'partial'], true)) return;

        if (! config('nfebrasil.auto_emission_on_sell_completed', false)) {
            Log::info('NFC-e auto-emission DISABLED — listener no-op (global flag)', [
                'business_id'    => $businessId,
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        // Per-business gate (ADR 0093 multi-tenant Tier 0). Tenant precisa
        // ter opt-in explícito via nfe_business_configs.auto_emission_enabled=true.
        // Default false — protege biz=4 (ROTA LIVRE Larissa) etc. quando flag
        // global foi ligada pra smoke biz=1.
        $bizConfig = NfeBusinessConfig::where('business_id', $businessId)->first();
        if (! $bizConfig || ! $bizConfig->auto_emission_enabled) {
            Log::info('NFC-e auto-emission DISABLED — listener no-op (per-business gate)', [
                'business_id'    => $businessId,
                'transaction_id' => $transactionId,
                'has_config'     => (bool) $bizConfig,
            ]);
            return;
        }

        EmitirNfceJob::dispatch($businessId, $transactionId);

        Log::info('NFC-e Job dispatched', [
            'business_id'    => $businessId,
            'transaction_id' => $transactionId,
        ]);
    }
}
