<?php

namespace Modules\Vestuario\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider Modules/Vestuario (ADR 0121 §P7 — vertical em prod via ROTA LIVRE biz=4).
 *
 * Estado especial: ROTA LIVRE roda há 2+ anos via núcleo UltimatePOS +
 * Modules/{Financeiro, NfeBrasil, Copiloto} com customizações pontuais.
 * Esta pasta formaliza o vertical pra:
 *   1. Habilitar revenda do módulo pra outras lojas vestuário CNAE 4781
 *   2. Consumir shared Modules/Repair (kanban opcional) com label_overrides
 *   3. Encapsular customizações ROTA LIVRE (format_date shift +3h ADR 0066)
 *
 * Sprint 1 scaffold: módulo nWidart vazio + RepairSettingsSeeder.
 * Code real (Models, Controllers, Migrations) entra Sprint 2+ conforme
 * sinal qualificado [ADR 0105].
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md
 * @see memory/requisitos/Vestuario/SPEC.md
 */
class VestuarioServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        // Service container: nada especial — Sprint 2+ adiciona.
    }
}
