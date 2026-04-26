<?php

namespace Modules\IProduction\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo IProduction.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\IProduction\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * Observações importantes do contexto deste módulo:
 *
 *  - `Modules/IProduction/Resources/lang/` está vazio (apenas .gitkeep). Por
 *    isso usamos strings literais em PT-BR como rótulos. Quando o módulo
 *    ganhar lang files, trocar pelas chaves `iproduction::lang.*`.
 *
 *  - `Modules/IProduction/Http/routes.php` tem prefix `boleto` e namespace
 *    `Modules\Boleto\Http\Controllers` (legado herdado do antigo módulo
 *    Boleto). Como o módulo Boleto NÃO existe mais neste worktree, NÃO
 *    podemos linkar `route(...)` direto pra rotas que apontam pra
 *    controllers ausentes — isso quebra o boot do menu. Em vez disso,
 *    apontamos os itens pra URLs literais (`/boleto/recipe`,
 *    `/boleto/production`, etc.). A correção definitiva das rotas é tarefa
 *    separada.
 *
 *  - Permissões seguem o padrão `iproduction.*` (não `manufacturing.*`)
 *    para não colidir com Modules/Manufacturing, que tem seu próprio
 *    DataController e perms `manufacturing.*`.
 *
 * @see Modules/Manufacturing/Http/Controllers/DataController.php (referência)
 * @see Modules/PontoWr2/Http/Controllers/DataController.php       (template)
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para Superadmin > Packages.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'iproduction_module',
                'label'   => 'Módulo IProduction',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'iproduction.access',
                'label'   => 'IProduction: acesso ao módulo',
                'default' => false,
            ],
            [
                'value'   => 'iproduction.recipe.access',
                'label'   => 'IProduction: acesso a receitas/fichas técnicas',
                'default' => false,
            ],
            [
                'value'   => 'iproduction.recipe.manage',
                'label'   => 'IProduction: gerenciar receitas/fichas técnicas',
                'default' => false,
            ],
            [
                'value'   => 'iproduction.production.access',
                'label'   => 'IProduction: acesso a ordens de produção',
                'default' => false,
            ],
            [
                'value'   => 'iproduction.production.manage',
                'label'   => 'IProduction: gerenciar ordens de produção',
                'default' => false,
            ],
            [
                'value'   => 'iproduction.settings.manage',
                'label'   => 'IProduction: gerenciar configurações',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('IProduction');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'iproduction_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('iproduction.access')
            || auth()->user()->can('iproduction.recipe.access')
            || auth()->user()->can('iproduction.production.access');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        // Rotas atuais usam prefix /boleto (legado). Comparamos com este
        // segmento para destacar o item ativo.
        $segmento_ativo = request()->segment(1) == 'boleto';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    'IProduction',
                    function ($sub) {
                        if (auth()->user()->can('superadmin') || auth()->user()->can('iproduction.recipe.access')) {
                            $sub->url(
                                url('/boleto/recipe'),
                                'Receitas / Fichas Técnicas',
                                [
                                    'icon'   => 'fa fas fa-utensils',
                                    'active' => request()->segment(2) == 'recipe',
                                ]
                            );
                        }

                        if (auth()->user()->can('superadmin') || auth()->user()->can('iproduction.production.access')) {
                            $sub->url(
                                url('/boleto/production'),
                                'Ordens de Produção',
                                [
                                    'icon'   => 'fa fas fa-cogs',
                                    'active' => request()->segment(2) == 'production',
                                ]
                            );
                        }

                        $sub->url(
                            url('/boleto/report'),
                            'Relatório de Produção',
                            [
                                'icon'   => 'fa fas fa-chart-line',
                                'active' => request()->segment(2) == 'report',
                            ]
                        );

                        if (auth()->user()->can('superadmin') || auth()->user()->can('iproduction.settings.manage')) {
                            $sub->url(
                                url('/boleto/settings'),
                                'Configurações',
                                [
                                    'icon'   => 'fa fas fa-cog',
                                    'active' => request()->segment(2) == 'settings',
                                ]
                            );
                        }
                    },
                    [
                        'icon'   => 'fa fas fa-industry',
                        'style'  => 'background-color:' . $background_color,
                        'active' => $segmento_ativo,
                    ]
                )->order(94);
            }
        );
    }
}
