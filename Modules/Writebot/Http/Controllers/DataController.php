<?php

namespace Modules\Writebot\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Writebot.
 *
 * Descoberto pelo middleware `AdminSidebarMenu` do core UltimatePOS
 * (convenção: Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu).
 *
 * STATUS 2026-04-26: Writebot é legacy quebrado — Http/routes.php ainda
 * contém rotas e namespace do Boleto antigo (copy-paste error não corrigido)
 * e não há controllers Writebot reais além do InstallController.
 *
 * Por isso este DataController é MINIMAL: declara o feature flag e a
 * permissão `writebot.access` para que o módulo apareça no painel
 * Superadmin > Packages e na tela de Roles, mas NÃO injeta item de
 * sidebar (nenhuma rota web nomeada `writebot.*` existe ainda).
 *
 * Reativar `modifyAdminMenu()` quando o routes.php for sanitizado e
 * controllers reais (RecipeController/ProductionController/etc.) forem
 * trazidos para o namespace Writebot.
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para o painel Superadmin > Packages.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'writebot_module',
                'label'   => __('writebot::writebot.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem no cadastro de papéis (Roles) do
     * UltimatePOS.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'writebot.access',
                'label'   => __('writebot::writebot.permissao_acesso'),
                'default' => false,
            ],
        ];
    }

    /**
     * Writebot ainda não tem rotas web reais (Http/routes.php aponta para
     * o namespace legado `Modules\Boleto`). Menu não é injetado por
     * enquanto. Reativar quando módulo tiver controllers/rotas próprias.
     *
     * Quando reativado, sugestão:
     *   - icon: 'fa fas fa-robot'
     *   - order: 96
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        return; // intentionally empty: routes.php legacy aponta pro Boleto
    }
}
