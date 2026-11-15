<?php

declare(strict_types=1);

namespace App\Contracts\Fiscal;

/**
 * O que o cancelamento da venda precisa saber da nota fiscal, visto pelo núcleo.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * O módulo fiscal implementa e marca a classe com a tag {@see self::TAG} no próprio ServiceProvider.
 * Sem módulo fiscal ligado a tag vem vazia e a venda cancela sem olhar nota.
 *
 * Quem consome: App\Services\Sells\CancelamentoDaVenda e o CancelarVendaCascade.
 */
interface CancelamentoFiscalDaVenda
{
    public const TAG = 'venda.cancelamento-fiscal';

    /**
     * Por que a nota impede cancelar a venda agora. Lista vazia = não impede.
     * Sempre filtra por business_id (Tier 0).
     *
     * @return list<string>
     */
    public function impedimentos(int $businessId, int $transactionId): array;

    /**
     * Garante em `transaction_documents` uma linha por nota autorizada da venda, que é de onde o
     * cascade despacha os jobs de cancelamento. Idempotente. Devolve quantas notas encontrou.
     */
    public function registrarDocumentos(int $businessId, int $transactionId): int;
}
