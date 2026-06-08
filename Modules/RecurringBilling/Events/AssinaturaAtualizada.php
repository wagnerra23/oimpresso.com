<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FIN-004 — Disparado quando AssinaturaCobrancaService::atualizarCobrancaAssinatura
 * persiste mudanca de valor / ciclo / forma_pagamento em rb_subscriptions.
 *
 * Listeners interessados: audit log (Spatie Activity), Brief Diario (cycle goal),
 * notificacao opt-in cliente (LGPD-checked).
 *
 * Multi-tenant Tier 0: businessId sempre presente.
 * ZERO PII no payload — apenas IDs e flags. Valor real nao trafega aqui
 * (consultar Subscription diretamente quando precisar do numero).
 */
class AssinaturaAtualizada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $businessId,
        public readonly int $subscriptionId,
        public readonly bool $mudouValor,
        public readonly bool $mudouCiclo,
        public readonly bool $mudouForma,
        public readonly bool $gatewayCall,
    ) {}
}
