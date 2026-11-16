<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Leva a empresa para o registro da trilha de quem não tem a coluna `business_id` — UC-RECIPE-25.
 * Auditoria do Manufacturing, lote 2.7.
 *
 * `mfg_recipes` e `mfg_recipe_ingredients` não têm `business_id`: a empresa vem pela cadeia até o produto
 * (ADR 0093). O `ActivityCauserKindObserver` resolve a empresa pela coluna ou pela Transaction-pai, e
 * aqui não acha nenhuma das duas: o registro nascia sem empresa e sumia da /auditoria, que filtra por ela.
 * O observer mantém o que o chamador setou quando não consegue resolver, então o valor daqui fica.
 *
 * Quem usa implementa `empresaDaTrilha()`.
 */
trait RegistraEmpresaNaTrilha
{
    abstract protected function empresaDaTrilha(): ?int;

    /** Chamado pelo `LogsActivity` antes de gravar o registro. */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        static $temColuna = null;
        if ($temColuna === null) {
            try {
                $temColuna = Schema::hasColumn('activity_log', 'business_id');
            } catch (\Throwable $e) {
                $temColuna = false;
            }
        }
        if (! $temColuna || ! $activity instanceof Model || ! empty($activity->getAttribute('business_id'))) {
            return;
        }

        $empresa = $this->empresaDaTrilha();
        if ($empresa !== null && $empresa > 0) {
            $activity->setAttribute('business_id', $empresa);
        }
    }
}
