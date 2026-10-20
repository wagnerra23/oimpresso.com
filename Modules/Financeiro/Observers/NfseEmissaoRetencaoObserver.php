<?php

declare(strict_types=1);

namespace Modules\Financeiro\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\NFSe\Models\NfseEmissao;

/**
 * US-FIN-072 · UC-FLX-30 — escuta a mudança de status da NFS-e e ajusta o título da venda.
 *
 * Só reage à TRANSIÇÃO (`wasChanged('status')`): nota autorizada antes deste deploy não
 * dispara nada. Falha aqui não pode derrubar a gravação da nota (o status fiscal já mudou na
 * prefeitura) — vai para o log e o dry-run `financeiro:iss-retido-ajuste` mostra o que falta.
 */
class NfseEmissaoRetencaoObserver
{
    public function updated(NfseEmissao $nota): void
    {
        // Vocabulário de status é do dono (NFSe): usa os helpers do Model, não literais.
        if (! $nota->wasChanged('status') || ! ($nota->isEmitida() || $nota->isCancelada())) {
            return;
        }

        try {
            $servico = app(RetencaoNfseTituloService::class);
            $acao = $nota->isEmitida() ? $servico->aplicar($nota) : $servico->estornar($nota);
            if (! in_array($acao, ['desligado', 'sem_titulo', 'sem_retencao', 'nada_a_estornar'], true)) {
                Log::info('financeiro.retencao_nfse', ['business_id' => $nota->business_id, 'nfse_id' => $nota->id, 'acao' => $acao]);
            }
        } catch (\Throwable $e) {
            Log::warning('financeiro.retencao_nfse.falhou', [
                'business_id' => $nota->business_id, 'nfse_id' => $nota->id, 'erro' => $e->getMessage(),
            ]);
        }
    }
}
