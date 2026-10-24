<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Contracts\Fiscal\NotaDaVenda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nota válida da venda pelo NfeBrasil (US-SELL-072): NF-e/NFC-e em `nfe_emissoes` e as NFS-e que
 * este módulo emite em `nfse_emissoes` (vocabulário em inglês do NfseEmissao daqui).
 *
 * Conta como válida: `autorizada` e `contingencia` (nota emitida offline é nota válida ainda não
 * transmitida — docblock de NfeEmissao). Cancelada, rejeitada, denegada, inutilizada e pendente
 * não travam a venda. Consulta direta com business_id explícito, sem depender da sessão.
 */
final class NotaDaVendaNfeBrasil implements NotaDaVenda
{
    private const NFE_VALIDAS = ['autorizada', 'contingencia'];

    private const NFSE_VALIDAS = ['authorized'];

    public function temNotaValida(int $businessId, int $transactionId): bool
    {
        return $this->existe('nfe_emissoes', self::NFE_VALIDAS, $businessId, $transactionId)
            || $this->existe('nfse_emissoes', self::NFSE_VALIDAS, $businessId, $transactionId);
    }

    /** @param list<string> $status */
    private function existe(string $tabela, array $status, int $businessId, int $transactionId): bool
    {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'transaction_id')) {
            return false;
        }

        $q = DB::table($tabela)
            ->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)
            ->whereIn('status', $status);
        if (Schema::hasColumn($tabela, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return $q->exists();
    }
}
