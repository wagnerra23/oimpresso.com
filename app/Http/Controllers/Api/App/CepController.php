<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Contracts\Enderecos\BuscaCep;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/app/cep/{cep} — botão "Buscar" do CEP na Nova pessoa do app (tela 09).
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §4.3.
 *
 * O app nunca chama serviço de CEP externo direto: passa pelo ERP, que usa o proxy com cache
 * já existente (contrato App\Contracts\Enderecos\BuscaCep, implementado no Crm).
 */
class CepController extends Controller
{
    public function __construct(private BuscaCep $busca)
    {
    }

    public function show(string $cep): JsonResponse
    {
        $digitos = preg_replace('/\D/', '', $cep) ?? '';
        if (strlen($digitos) !== 8) {
            return response()->json(['erro' => 'validacao', 'campos' => ['cep' => 'O CEP tem 8 dígitos.']], 422);
        }

        $e = $this->busca->buscar($digitos);
        if ($e === null) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'CEP não encontrado. Preencha o endereço à mão.'], 404);
        }

        return response()->json(['cep' => $digitos] + $e);
    }
}
