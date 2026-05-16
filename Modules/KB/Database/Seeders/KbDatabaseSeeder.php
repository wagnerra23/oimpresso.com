<?php

declare(strict_types=1);

namespace Modules\KB\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * KbDatabaseSeeder — entry-point chamado pelo InstallController do módulo KB.
 *
 * Contrato: SCHEMA-DB-V1.md §13
 *
 * Sequência:
 *   1. KbCategoriesSeeder (8 categorias)
 *   2. KbSubcategoriesSeeder (16 subcats com auto_match)
 *   3. KbBridgeFromMcpSeeder (bridge canon — Wagner biz=1 ~700 nodes)
 *   4. KbOperacionalSeeder (3 arts + 1 trilha + 1 troubleshooter — piloto biz=4 recomendado)
 *
 * Multi-tenant Tier 0: aceita $businessId. NÃO usa session().
 *
 * Por convenção nWidart, `run()` sem args é o que o php artisan module:seed KB
 * chama — então a versão sem args itera os businesses ativos. Versão com args
 * é pro InstallController invocar per-business.
 */
class KbDatabaseSeeder extends Seeder
{
    /**
     * Chamado por `php artisan module:seed KB` — itera todos os businesses ativos.
     */
    public function run(): void
    {
        Model::unguard();

        // TODO[CL]: filtrar businesses 'is_active=1' ou similar. Por ora itera todos.
        // Em prod com 100 biz, isso pode ser lento — InstallController per-business é caminho preferido.
        $businessIds = \DB::table('business')->pluck('id');

        foreach ($businessIds as $bizId) {
            $this->runFor((int) $bizId);
        }

        Model::reguard();
    }

    /**
     * Roda pra um business específico — chamado pelo InstallController.
     */
    public function runFor(int $businessId): void
    {
        $this->command?->info("==> KB seeders pra biz={$businessId}");

        (new KbCategoriesSeeder())->setContainer($this->container)->setCommand($this->command)->run($businessId);
        (new KbSubcategoriesSeeder())->setContainer($this->container)->setCommand($this->command)->run($businessId);
        (new KbBridgeFromMcpSeeder())->setContainer($this->container)->setCommand($this->command)->run($businessId);

        // Operacional seed só pra biz piloto (ROTA LIVRE biz=4 ou explicit opt-in).
        // TODO[CL]: tornar isto config-driven pra não criar artigos fake em prod.
        if ($businessId === 4 || env('KB_SEED_OPERACIONAL_ALL', false)) {
            (new KbOperacionalSeeder())->setContainer($this->container)->setCommand($this->command)->run($businessId);
        }
    }
}
