<?php

declare(strict_types=1);

namespace Modules\Vestuario\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Modules/Vestuario (vertical lojas de vestuário CNAE 4781).
 *
 * Convenção UltimatePOS: o middleware AdminSidebarMenu chama
 * `ModuleUtil::getModuleData('modifyAdminMenu')`, que resolve dinamicamente
 * `Modules\Vestuario\Http\Controllers\DataController::modifyAdminMenu()` em
 * cada request da sidebar admin. Sem este arquivo, o módulo somem do menu
 * (audit 2026-04-26 / GUARD-02 `tests/Feature/Audit/ModuleScaffoldingTest.php`)
 * e a tela Etiquetas TAG (US-VEST-020) fica sem ponto de entrada.
 *
 * Hooks superadmin_package()/user_permissions() são chamados nas telas de
 * Packages/Roles do UltimatePOS.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * a entry só é declarada quando o módulo está habilitado para o `business_id`
 * da sessão ativa — nunca vaza entre tenants. Não usa `withoutGlobalScopes`.
 *
 * Sidebar v3 ([ADR 0180](memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)):
 * Vestuário pertence ao grupo `vender`. Declara `group`/`shortcut`/`primary`/
 * `ghosts` que o `LegacyMenuAdapter` propaga ao frontend (Sidebar.tsx +
 * PageHeader). Hrefs absolutos (não `url()`) pra não serem reinterpretados
 * pelo adapter.
 *
 * @see Modules/Vestuario/Http/Controllers/EtiquetaTagController.php
 * @see memory/requisitos/Vestuario/SPEC.md (US-VEST-009, US-VEST-020)
 * @see memory/decisions/0024-instalacao-1-clique-modulos.md
 */
class DataController extends Controller
{
    /**
     * Pacote superadmin que liga/desliga o módulo por business.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'vestuario_module',
                'label'   => 'Módulo Vestuário (etiquetas TAG, vertical moda CNAE 4781)',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no UI de Roles do UltimatePOS.
     *
     * `vestuario.etiqueta.{view,create}` já são enforce pelo EtiquetaTagController
     * (US-VEST-020). `vestuario.access` gateia a entry de sidebar.
     */
    public function user_permissions(): array
    {
        return [
            ['value' => 'vestuario.access',          'label' => 'Vestuário: acessar módulo',            'default' => false],
            ['value' => 'vestuario.etiqueta.view',   'label' => 'Vestuário: ver etiquetas TAG',         'default' => false],
            ['value' => 'vestuario.etiqueta.create', 'label' => 'Vestuário: gerar etiquetas TAG (lote)', 'default' => false],
        ];
    }

    /**
     * Injeta a entry "Vestuário" na sidebar admin (grupo VENDER · ADR 0180).
     *
     * Ghost único "Etiquetas" → a única sub-feature roteada hoje
     * (`/vestuario/etiquetas`, US-VEST-020). Demais US-VEST-* do backlog
     * (devolução/comissão/liquidação/fidelidade) entram como ghosts quando
     * ganharem rota real — não declaradas aqui pra não criar link morto.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Vestuario');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'vestuario_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('vestuario.access')
            || auth()->user()->can('vestuario.etiqueta.view');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') === 'demo' ? '#f0d9e8' : '';
        $segmento_ativo   = request()->segment(1) === 'vestuario';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->url(
                    route('vestuario.etiquetas.index'),
                    'Vestuário',
                    [
                        'icon'     => 'fa fas fa-tshirt',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        'group'    => 'vender',
                        'shortcut' => 'G V',
                        'primary'  => [
                            'label'    => 'Gerar etiquetas',
                            'href'     => '/vestuario/etiquetas',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'etiquetas', 'label' => 'Etiquetas', 'href' => '/vestuario/etiquetas'],
                        ],
                    ]
                )->order(35);
            }
        );
    }
}
