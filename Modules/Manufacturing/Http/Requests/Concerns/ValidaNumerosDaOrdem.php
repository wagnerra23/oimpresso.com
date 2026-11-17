<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Requests\Concerns;

use App\Utils\ModuleUtil;

/**
 * Os números da ordem de produção, conferidos no servidor antes de gravar — UC-OP-21.
 * Auditoria do Manufacturing, lote 2.2.
 *
 * Os campos chegam no formato da empresa ("1.234,5"), por isso cada regra lê o número com o mesmo
 * `num_uf` que o controller usa para gravar. Quantidade produzida tem de ser maior que zero (o custo
 * unitário divide por ela); perda e custo extra não podem ser negativos, e a perda tem de ser menor que
 * a produção; o consumo de cada insumo não pode ser negativo (somaria estoque em vez de baixar). Consumo
 * zero continua aceito: é rascunho com a linha ainda sem quantidade.
 *
 * Usado por `StoreProductionRequest` e `UpdateProductionRequest`: as duas portas gravam a mesma ordem.
 */
trait ValidaNumerosDaOrdem
{
    /** @return array<string, array<int, mixed>> */
    protected function regrasDosNumerosDaOrdem(): array
    {
        return [
            'quantity' => ['required', 'string', function (string $attribute, $value, $fail) {
                if (! is_array($value) && $this->numeroDaOrdem($value) <= 0) {
                    $fail('A quantidade produzida precisa ser maior que zero.');
                }
            }],
            'mfg_wasted_units' => ['nullable', 'string', function (string $attribute, $value, $fail) {
                if (is_array($value)) {
                    return;
                }
                $perda = $this->numeroDaOrdem($value);
                if ($perda < 0) {
                    $fail('A quantidade perdida não pode ser negativa.');

                    return;
                }
                $quantidade = $this->input('quantity');
                if (is_string($quantidade) && $perda >= $this->numeroDaOrdem($quantidade)) {
                    $fail('A quantidade perdida precisa ser menor que a produzida.');
                }
            }],
            'production_cost' => ['nullable', 'string', function (string $attribute, $value, $fail) {
                if (! is_array($value) && $this->numeroDaOrdem($value) < 0) {
                    $fail('O custo extra não pode ser negativo.');
                }
            }],
            'mfg_production_cost_type' => ['nullable', 'string', 'in:fixed,percentage,per_unit'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.quantity' => ['nullable', 'string', function (string $attribute, $value, $fail) {
                if (! is_array($value) && $this->numeroDaOrdem($value) < 0) {
                    $fail('O consumo do insumo não pode ser negativo.');
                }
            }],
        ];
    }

    /**
     * Sem tipo no parâmetro de propósito: o docblock de `Util::num_uf` diz `int`, embora todo o sistema
     * passe o texto do formulário (mesmo idioma do `StoreRecipeRequest`).
     */
    private function numeroDaOrdem($valor): float
    {
        return (float) app(ModuleUtil::class)->num_uf($valor);
    }
}
