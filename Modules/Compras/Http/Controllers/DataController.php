<?php

namespace Modules\Compras\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;

/**
 * DataController do módulo Compras.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\Compras\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * Wave 1: registra permissions canônicas + entry "Compras" no sidebar.
 * Group visual via SIDEBAR_GROUPS frontend (skill `sidebar-menu-arch`), NÃO
 * Menu::dropdown cross-módulo.
 *
 * @see Modules/PontoWr2/Http/Controllers/DataController.php (template)
 * @see Modules/NfeBrasil/Http/Controllers/DataController.php (referência)
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para Superadmin > Packages.
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'compras_module',
                'label' => 'Módulo Compras',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     *
     * Tier 0 ADR 0093: hotfix #624 — Role::firstOrCreate com suffix `#{biz_id}`
     * é responsabilidade do PermissionsSeeder. Aqui declara apenas o catálogo.
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'compras.view',
                'label' => 'Compras: visualizar cockpit + lista',
                'default' => false,
            ],
            [
                'value' => 'compras.create',
                'label' => 'Compras: criar compra manual',
                'default' => false,
            ],
            [
                'value' => 'compras.edit',
                'label' => 'Compras: editar compra (status, lines, payments)',
                'default' => false,
            ],
            [
                'value' => 'compras.delete',
                'label' => 'Compras: excluir compra',
                'default' => false,
            ],
            [
                'value' => 'compras.import_xml',
                'label' => 'Compras: importar XML DF-e como compra',
                'default' => false,
            ],
            [
                // LGPD Art. 7º — sem esta permissão o drawer mostra PII do
                // fornecedor mascarada (últimos 4 dígitos CNPJ + e-mail mascarado).
                // Admin do business já recebe via Gate::before; financeiro via
                // financeiro.access. AUDIT-SENIOR-2026-05-25 D7.a/R4.
                'value' => 'compras.view_supplier_pii',
                'label' => 'Compras: ver dados completos do fornecedor (CNPJ/telefone/e-mail sem máscara)',
                'default' => false,
            ],
        ];
    }

    /**
     * Sidebar entry "Compras".
     *
     * Group visual fica em SIDEBAR_GROUPS no frontend (Components/cockpit/Sidebar.tsx).
     * Aqui só publica a entrada base — agrupamento é responsabilidade da camada UI.
     */
    public function modifyAdminMenu()
    {
        $moduleUtil = new ModuleUtil();
        $business_id = session('user.business_id');

        if (! $business_id) {
            return;
        }

        $is_enabled = $moduleUtil->isModuleEnabled('compras_module', $business_id);

        if (! $is_enabled) {
            return;
        }

        \Menu::modify('admin-sidebar-menu', function ($menu) {
            // ADR 0180 Fase 4 Wave B (2026-05-21): entry principal declara
            // atalho kbd + primary action + ghosts tabs.
            //  - `shortcut` G S → atalho overlay (Suprimentos/Estoque)
            //  - `primary`     → "Nova compra" delega trilho A Purchase MWART
            //    Wave 2 B5 via /purchases/create Inertia (ADR
            //    compras-purchase-convergencia-c1 · 2026-05-25). Frontend usa
            //    router.visit pra injetar header X-Inertia e disparar dual-path
            //    no PurchaseController:400 → Purchase/Create.tsx React.
            //  - `ghosts`      → Lista (index Wave 1); demais sub-views (importar XML,
            //    NF entrada, etc) entram nas Waves 6+ do SPEC.
            $menu->url(
                action([\Modules\Compras\Http\Controllers\ComprasController::class, 'index']),
                'Compras',
                [
                    'icon'     => 'fa fas fa-shopping-cart',
                    'active'   => request()->is('compras*'),
                    'shortcut' => 'G S',
                    'primary'  => [
                        'label'    => 'Nova compra',
                        'href'     => '/purchases/create',
                        'shortcut' => 'N',
                    ],
                    'ghosts'   => [
                        ['key' => 'lista', 'label' => 'Lista', 'href' => '/compras'],
                    ],
                ]
            )->order(45);
        });
    }
}
