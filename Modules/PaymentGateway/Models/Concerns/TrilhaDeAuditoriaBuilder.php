<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Builder das trilhas de auditoria do PaymentGateway (webhooks recebidos e
 * retornos CNAB): INSERT livre, nunca DELETE, e UPDATE só nas colunas que o
 * processamento preenche depois (status, processed_at, contadores...).
 *
 * O que chegou do banco/gateway (payload, txid, valor, arquivo) não muda depois
 * de gravado. As colunas permitidas vêm de `COLUNAS_DO_PROCESSAMENTO` do model.
 *
 * `Model::save()`/`update()`/`delete()` de um registro existente passam por este
 * builder, então a regra vale também pelo próprio model. `DB::table(...)` não
 * passa por aqui: segue como saída explícita de comando e de limpeza de teste.
 * Mesmo desenho do AppendOnlyBuilder do Financeiro, com a lista de colunas do
 * processamento por ser trilha que o worker completa.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class TrilhaDeAuditoriaBuilder extends Builder
{
    public function update(array $values)
    {
        $proibidas = array_diff(array_keys($values), $this->colunasPermitidas());
        if ($proibidas !== []) {
            $this->recusar('não pode alterar ' . implode(', ', $proibidas));
        }

        return parent::update($values);
    }

    public function delete()
    {
        $this->recusar('não pode ser apagada');
    }

    public function forceDelete()
    {
        $this->recusar('não pode ser apagada');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->recusar('não aceita increment');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->recusar('não aceita decrement');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->recusar('não aceita upsert');
    }

    /** @return list<string> */
    private function colunasPermitidas(): array
    {
        $model = $this->getModel();
        $colunas = defined($model::class . '::COLUNAS_DO_PROCESSAMENTO')
            ? $model::COLUNAS_DO_PROCESSAMENTO
            : [];

        if ($model->usesTimestamps() && $model->getUpdatedAtColumn() !== null) {
            $colunas[] = $model->getUpdatedAtColumn();
        }

        return $colunas;
    }

    private function recusar(string $motivo): never
    {
        throw new \DomainException(
            $this->getModel()->getTable() . ' é trilha de auditoria: ' . $motivo . '.'
        );
    }
}
