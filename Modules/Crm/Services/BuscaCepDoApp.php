<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Contracts\Enderecos\BuscaCep;

/**
 * Implementação do contrato App\Contracts\Enderecos\BuscaCep (botão "Buscar" do app das lojas).
 * Registrada no CrmServiceProvider. Reusa o BrLookupService (o mesmo proxy de ViaCEP com cache
 * que o drawer de cliente da web usa), em vez de abrir uma segunda chamada ao ViaCEP.
 */
final class BuscaCepDoApp implements BuscaCep
{
    public function __construct(private BrLookupService $lookup)
    {
    }

    public function buscar(string $cep): ?array
    {
        $r = $this->lookup->lookupCep($cep);
        if ($r === null) {
            return null;
        }

        return [
            'logradouro' => $r['logradouro'],
            'complemento' => $r['complemento'],
            'bairro' => $r['bairro'],
            'cidade' => $r['cidade'],
            'uf' => $r['uf'],
            // Entradas em cache de antes do campo `ibge` não o trazem; vêm null até expirarem.
            'codigo_ibge' => ($r['ibge'] ?? '') !== '' ? (string) $r['ibge'] : null,
        ];
    }
}
