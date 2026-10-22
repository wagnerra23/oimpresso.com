<?php

declare(strict_types=1);

namespace App\Contracts\Fiscal;

/**
 * "Esta venda tem nota fiscal válida?", vista pelo núcleo (US-SELL-072).
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Cada módulo fiscal implementa e marca a classe com a tag {@see self::TAG} no próprio
 * ServiceProvider: hoje o NfeBrasil (NF-e, NFC-e e as NFS-e que ele emite) e o NFSe. Sem módulo
 * fiscal ligado a tag vem vazia e nenhuma venda tem nota — o comportamento de antes.
 *
 * Quem consome: App\Services\Sells\TravaDaVenda.
 */
interface NotaDaVenda
{
    public const TAG = 'venda.notas-fiscais';

    /**
     * Nota válida da venda (autorizada ou em contingência), no business informado. Nota
     * cancelada, rejeitada ou denegada não conta. Sempre filtra por business_id (Tier 0).
     */
    public function temNotaValida(int $businessId, int $transactionId): bool;
}
