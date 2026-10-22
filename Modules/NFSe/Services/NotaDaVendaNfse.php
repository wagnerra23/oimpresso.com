<?php

declare(strict_types=1);

namespace Modules\NFSe\Services;

use App\Contracts\Fiscal\NotaDaVenda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NFS-e válida da venda pelo módulo NFSe (US-SELL-072): `nfse_emissoes` com status `emitida`
 * (vocabulário do NfseEmissao deste módulo). `cancelada`, `erro`, `rascunho` e `processando` não
 * travam a venda. Consulta direta com business_id explícito, sem depender da sessão.
 */
final class NotaDaVendaNfse implements NotaDaVenda
{
    public function temNotaValida(int $businessId, int $transactionId): bool
    {
        if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasColumn('nfse_emissoes', 'transaction_id')) {
            return false;
        }

        return DB::table('nfse_emissoes')
            ->where('business_id', $businessId)
            ->where('transaction_id', $transactionId)
            ->where('status', 'emitida')
            ->whereNull('deleted_at')
            ->exists();
    }
}
