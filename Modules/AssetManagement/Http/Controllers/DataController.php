<?php

namespace Modules\AssetManagement\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Modules\AssetManagement\Entities\Asset;
use Menu;

class DataController extends Controller
{
    public function superadmin_package()
    {
        return [
            [
                'name' => 'assetmanagement_module',
                'label' => __('assetmanagement::lang.asset_management'),
                'default' => false,
            ],
        ];
    }

    /**
     * Defines user permissions for the module.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'asset.view',
                'label' => __('assetmanagement::lang.view_asset'),
                'default' => false,
            ],
            [
                'value' => 'asset.create',
                'label' => __('assetmanagement::lang.add_asset'),
                'default' => false,
            ],
            [
                'value' => 'asset.update',
                'label' => __('assetmanagement::lang.edit_asset'),
                'default' => false,
            ],
            [
                'value' => 'asset.delete',
                'label' => __('assetmanagement::lang.delete_asset'),
                'default' => false,
            ],
            [
                'value' => 'asset.view_all_maintenance',
                'label' => __('assetmanagement::lang.view_all_maintenance'),
                'default' => false,
                'is_radio' => true,
                'radio_input_name' => 'view_maintenance',
            ],
            [
                'value' => 'asset.view_own_maintenance',
                'label' => __('assetmanagement::lang.view_own_maintenance'),
                'default' => false,
                'is_radio' => true,
                'radio_input_name' => 'view_maintenance',
            ],
        ];
    }

    /**
     * Function to add module taxonomies
     *
     * @return array
     */
    public function addTaxonomies()
    {
        $business_id = request()->session()->get('user.business_id');

        $module_util = new ModuleUtil();
        if (! (auth()->user()->can('superadmin') || $module_util->hasThePermissionInSubscription($business_id, 'assetmanagement_module'))) {
            return ['asset' => []];
        }

        return [
            'asset' => [
                'taxonomy_label' => __('assetmanagement::lang.asset_category'),
                'heading' => __('assetmanagement::lang.asset_categories'),
                'sub_heading' => __('assetmanagement::lang.manage_asset_categories'),
                'enable_taxonomy_code' => false,
                'enable_sub_taxonomy' => false,
                'navbar' => 'assetmanagement::layouts.nav',
            ],
        ];
    }

    /**
     * Adds Repair menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_asset_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'assetmanagement_module');

        $background_color = '';
        if (config('app.env') == 'demo') {
            $background_color = '#2e97bf !important';
        }

        // Aba "Auditoria" = DEEP-LINK para o Modules/Auditoria ja filtrado nos bens
        // (ADR 0414, decisao [W] 2026-09-24). Nao e tela deste modulo: o dono da trilha
        // por-registro e o Modules/Auditoria (ADR 0127). So aparece quando a tela de
        // destino ABRE para este usuario -- mesmas camadas do gate de
        // Modules/Auditoria/Http/Controllers/DataController::modifyAdminMenu (rota
        // existe + modulo no pacote + auditoria.view); sem isso seria aba que da 403.
        // `subject_type` aceita UM valor (AuditEntryService::list faz where =): `Asset`.
        $ghost_auditoria = $this->ghostAuditoria($module_util, $business_id);

        if ($is_asset_enabled && (auth()->user()->can('superadmin') || auth()->user()->can('asset.view') || auth()->user()->can('asset.view_own_maintenance') || auth()->user()->can('asset.view_all_maintenance'))) {
            Menu::modify('admin-sidebar-menu', function ($menu) use ($background_color, $ghost_auditoria) {
                // ADR 0180 Fase 4 Wave E — AssetManagement é ghost virtual de
                // Estoque no grupo canon `operar` v3. Sem `shortcut` (acoplado em
                // Estoque); sem `primary` (ver abaixo); `ghosts` = Painel + Bens + Alocações + Devoluções +
                // Manutenções + Configurações (sub-views gestão de patrimônio).
                //
                // RÓTULOS: "Bens" e "Manutenções" por decisão [W] de 2026-09-09, que
                // resolveu a divergência registrada no RUNBOOK §6 e na `_saida-07.md §0`.
                // Até então a aba dizia "Ativos"/"Manutenção" enquanto o `PageHeader` da
                // MESMA tela dizia "Bens"/"Manutenções" (`Bens.tsx:528`,
                // `Manutencoes.tsx:438`) — três vocabulários visíveis em dez linhas.
                // Aqui é o dono ÚNICO do rótulo: o `PatrimonioSubNav` DERIVA daí.
                // ⚠️ O `pt/lang.php` segue interno-inconsistente ("ativo/ativos" em 20
                // valores, "recurso" em 9, "Bens" em 1) e o módulo ainda se chama
                // "Gestão de ativos" na sidebar — unificar isso é escopo próprio, não feito.
                $menu->url(
                            action([\Modules\AssetManagement\Http\Controllers\AssetController::class, 'dashboard']),
                            __('assetmanagement::lang.asset_management'),
                            [
                                'icon'    => 'fas fa fa-boxes',
                                'active'  => request()->segment(1) == 'asset',
                                'style'   => 'background-color:'.$background_color,
                                // SEM `primary` desde 2026-09-23. Ele era "Novo ativo" ->
                                // `/asset/assets/create`, e `AssetController::create` so
                                // responde sob `ajax()`: o `<Link>` do Inertia recebia o
                                // fragmento de modal jQuery cru (resposta nao-Inertia) e a
                                // navegacao direta, 200 com 0 bytes -- medido em prod, biz=1.
                                // Volta quando o cadastro virar drawer (Bens.casos.md BACKLOG).
                                // Trava: MenuGhostsContratoTest ("o primary ... abre pagina").
                                'ghosts'  => [
                                    ['key' => 'dashboard',         'label' => 'Painel',         'href' => '/asset/dashboard'],
                                    ['key' => 'assets',            'label' => 'Bens',           'href' => '/asset/assets'],
                                    ['key' => 'allocation',        'label' => 'Alocações',      'href' => '/asset/allocation'],
                                    ['key' => 'revocation',        'label' => 'Devoluções',     'href' => '/asset/revocation'],
                                    ['key' => 'asset-maintenance', 'label' => 'Manutenções',    'href' => '/asset/asset-maintenance'],
                                    ['key' => 'settings',          'label' => 'Configurações',  'href' => '/asset/settings'],
                                    ...($ghost_auditoria ? [$ghost_auditoria] : []),
                                ],
                            ]
                        )
                ->order(87);
            });
        }
    }

    /**
     * Ghost "Auditoria" (ADR 0414): link para /auditoria filtrado por subject_type=Asset,
     * ou null quando a tela de destino nao abriria para este usuario.
     *
     * @return array{key: string, label: string, href: string}|null
     */
    private function ghostAuditoria(ModuleUtil $module_util, $business_id): ?array
    {
        if (! Route::has('auditoria.index')) {
            return null;
        }

        $user = auth()->user();
        $is_superadmin = $user->can('superadmin');

        $modulo_ativo = $is_superadmin
            ? $module_util->isModuleInstalled('Auditoria')
            : (bool) $module_util->hasThePermissionInSubscription($business_id, 'auditoria_module', 'superadmin_package');

        if (! $modulo_ativo || ! ($is_superadmin || $user->can('auditoria.view'))) {
            return null;
        }

        return [
            'key'   => 'auditoria',
            'label' => 'Auditoria',
            'href'  => '/auditoria?subject_type='.rawurlencode(Asset::class),
        ];
    }

    /**
     * Parses notification message from database.
     *
     * @return array
     */
    public function parse_notification($notification)
    {
        $notification_data = [];
        if ($notification->type ==
            'Modules\AssetManagement\Notifications\AssetSentForMaintenance' || $notification->type ==
            'Modules\AssetManagement\Notifications\AssetAssignedForMaintenance') {
            $notification_data = [
                'msg' => $notification->data['msg'],
                'icon_class' => 'fas fa-tools bg-green',
                'link' => action([\Modules\AssetManagement\Http\Controllers\AssetMaitenanceController::class, 'index']),
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at->diffForHumans(),
            ];
        }

        return $notification_data;
    }
}
