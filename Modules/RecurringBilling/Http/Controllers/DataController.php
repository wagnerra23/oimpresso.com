<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo RecurringBilling.
 *
 * Descoberto pelo middleware `AdminSidebarMenu` do core UltimatePOS
 * (convenção: Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu).
 *
 * STATUS 2026-04-26: RecurringBilling é spec-ready (promovido em
 * 2026-04-24 com SPEC + ADRs por categoria, ainda sem código operacional).
 * Rotas web existentes (Routes/web.php) só cobrem install + um
 * `Route::resource` placeholder para `RecurringBillingController` (CRUD
 * vazio sem store/update).
 *
 * Por isso este DataController é MINIMAL: declara o feature flag e a
 * permissão `recurringbilling.access` para que o módulo apareça no
 * painel Superadmin > Packages e na tela de Roles, mas NÃO injeta item
 * de sidebar — não há tela funcional para apontar.
 *
 * Reativar `modifyAdminMenu()` quando módulo tiver UI (assinaturas, Pix
 * Automático, régua de inadimplência, etc.) e rotas nomeadas
 * correspondentes.
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
                'name'    => 'recurringbilling_module',
                'label'   => __('recurringbilling::recurringbilling.module_label'),
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
                'value'   => 'recurringbilling.access',
                'label'   => __('recurringbilling::recurringbilling.permissao_acesso'),
                'default' => false,
            ],
        ];
    }

    /**
     * RecurringBilling ainda é spec — sem UI funcional. Menu não é injetado.
     *
     * Quando reativado, sugestão:
     *   - icon: 'fa fas fa-sync-alt'
     *   - order: 98
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        return; // intentionally empty: módulo spec sem UI ainda
    }
}
