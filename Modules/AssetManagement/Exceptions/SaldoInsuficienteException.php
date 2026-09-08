<?php

namespace Modules\AssetManagement\Exceptions;

use Exception;

/**
 * Alocação recusada por saldo insuficiente (thread 02).
 *
 * Nasce em `AssetAllocationService::criar()` — o CAMINHO VIVO. Não vive no
 * `StoreAssetAllocationRequest`, que é ÓRFÃO: o controller recebe
 * `Illuminate\Http\Request` cru e tem 0 chamadas de validação, então regra escrita
 * lá passa no CI e é inerte em produção (`_saida-04.md §5`).
 *
 * ⚠️ LIMITE CONHECIDO, declarado em vez de escondido: hoje o
 * `AssetAllocationController::store()` (`:206`) captura `\Exception` genérica e
 * responde `messages.something_went_wrong`, então esta mensagem PT-BR chega ao LOG,
 * não à tela. Trocar isso exige tocar o controller, que está fora do prefixo desta
 * thread (`nao_toca: os controllers`) e é 1 PR = 1 intent. A trava em si funciona: a
 * gravação é recusada, que é o que impede o rastro de responsabilidade de nascer falso.
 */
class SaldoInsuficienteException extends Exception
{
    public function __construct(
        public readonly int $assetId,
        public readonly float $pedido,
        public readonly float $disponivel,
    ) {
        parent::__construct(sprintf(
            'Saldo insuficiente para alocar: pedido de %s unidade(s), disponível %s.',
            rtrim(rtrim(number_format($pedido, 4, ',', '.'), '0'), ','),
            rtrim(rtrim(number_format($disponivel, 4, ',', '.'), '0'), ','),
        ));
    }
}
