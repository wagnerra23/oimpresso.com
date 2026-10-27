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
            // compras.create / compras.edit / compras.delete SAIRAM do catálogo
            // (decisão [W] 2026-09-24, thread 04 do playbook Compras): pela
            // convergência C1 o CRUD vive em /purchases e é gateado por
            // purchase.* — nenhum código checava as três. A caixinha no /roles
            // prometia um poder que não existia. compras.import_xml fica: é o
            // gate previsto da importação DF-e (US-COM-003, Wave 6).
            [
                'value' => 'compras.import_xml',
                'label' => 'Compras: importar XML DF-e como compra',
                'default' => false,
            ],
            // US-COM-032 (decisão [W] 2026-10-26) — checadas por Modules\Compras\Services\AlcadaDoPedido.
            [
                'value' => 'compras.alcada.gerente',
                'label' => 'Compras: aprovar pedido de compra até o limite do gerente',
                'default' => false,
            ],
            [
                'value' => 'compras.alcada.diretoria',
                'label' => 'Compras: aprovar pedido de compra de qualquer valor',
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
                        // Fornecedores = a lista que ja existe no cadastro de contatos
                        // (contacts type=supplier). Decisao [W] 2026-09-24, thread 03
                        // do playbook Compras: atalho, nao tela propria — a ficha do
                        // fornecedor (scorecard C11) segue segurada no SPEC.
                        ['key' => 'fornecedores', 'label' => 'Fornecedores', 'href' => '/cliente?type=supplier'],
                    ],
                ]
            )->order(45);
        });
    }

    /**
     * US-COM-024 · UC-FLX-38 — campo "Método de custo" nos formulários de novo e de editar produto.
     * Só nessas duas telas: o cadastro rápido (quickAdd) também desenha as partes dos módulos, mas o
     * salvar dele (saveQuickProduct) não chama `after_product_saved`, e o campo não gravaria.
     * O gravar (`after_product_saved`) não repete o gate do pacote: sem o campo no formulário, o
     * envio não traz `compras_metodo_custo` e nada é gravado.
     */
    public function product_form_part()
    {
        if (! app()->bound(\App\Contracts\Compras\CustoDoInsumo::class)
            || ! request()->routeIs('products.create', 'products.edit')) {
            return [];
        }

        // Só empresa cujo pacote tem o módulo Compras (o mesmo gate do WooCommerce no formulário).
        $businessId = (int) request()->session()->get('user.business_id');
        if ($businessId <= 0
            || ! app(ModuleUtil::class)->hasThePermissionInSubscription($businessId, 'compras_module', 'superadmin_package')) {
            return [];
        }
        $productId = (int) request()->route('product');

        return [
            'template_path' => 'compras::partials.metodo_custo_produto',
            'template_data' => [
                'compras_metodo_custo_atual' => $productId > 0
                    ? app(\Modules\Compras\Services\MetodoCustoDoProduto::class)->atual($businessId, $productId)
                    : '',
                'compras_metodo_custo_empresa' => app(\App\Contracts\Compras\CustoDoInsumo::class)->metodoDaEmpresa($businessId),
                'compras_metodo_custo_editavel' => (bool) auth()->user()?->can('purchase.update'),
            ],
        ];
    }

    /** Grava o "Método de custo" do formulário de produto (ver MetodoCustoDoProduto). */
    public function after_product_saved($data)
    {
        app(\Modules\Compras\Services\MetodoCustoDoProduto::class)->gravarDoFormulario($data['product'], $data['request']);
    }
}
