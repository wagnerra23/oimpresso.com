<?php

namespace Modules\LaravelAI\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo LaravelAI.
 *
 * Descoberto pelo middleware `AdminSidebarMenu` do core UltimatePOS
 * (convenção: Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu).
 *
 * STATUS 2026-04-26: LaravelAI é spec-ready (promovido em 2026-04-24 com
 * SPEC + ADRs por categoria, ainda sem código operacional). Rotas web
 * existentes (Routes/web.php) só cobrem install + um `Route::resource`
 * placeholder para `LaravelAIController` (CRUD vazio sem store/update).
 *
 * Por isso este DataController é MINIMAL: declara o feature flag e a
 * permissão `laravelai.access` para que o módulo apareça no painel
 * Superadmin > Packages e na tela de Roles, mas NÃO injeta item de
 * sidebar — não há tela funcional para apontar.
 *
 * Reativar `modifyAdminMenu()` quando módulo tiver UI (chat IA, dashboard
 * de embeddings, etc.) e rotas nomeadas correspondentes.
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
                'name'    => 'laravelai_module',
                'label'   => __('laravelai::laravelai.module_label'),
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
                'value'   => 'laravelai.access',
                'label'   => __('laravelai::laravelai.permissao_acesso'),
                'default' => false,
            ],
        ];
    }

    /**
     * LaravelAI ainda é spec — sem UI funcional. Menu não é injetado.
     *
     * Quando reativado, sugestão:
     *   - icon: 'fa fas fa-brain'
     *   - order: 97
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        return; // intentionally empty: módulo spec sem UI ainda
    }
}
