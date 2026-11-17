<?php

namespace Modules\Financeiro\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Builder dos livros append-only: recusa toda escrita que não seja INSERT.
 *
 * `Model::save()` de um registro existente e `Model::increment()` passam por este builder,
 * então cobrem também a alteração pelo próprio model. `DB::table(...)` não passa por aqui:
 * segue como a saída explícita de comandos e limpeza de teste.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class AppendOnlyBuilder extends Builder
{
    public function update(array $values)
    {
        $this->recusar();
    }

    public function delete()
    {
        $this->recusar();
    }

    public function forceDelete()
    {
        $this->recusar();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->recusar();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->recusar();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->recusar();
    }

    private function recusar(): never
    {
        throw new \DomainException(
            $this->getModel()->getTable().' é append-only. Lance um estorno ou um movimento de ajuste.'
        );
    }
}
