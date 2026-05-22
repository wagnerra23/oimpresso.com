<?php

namespace Modules\Fiscal\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Fiscal (cockpit unificado).
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\<Nome>\Http\Controllers\DataController).
 *
 * Padrão alinhado com Modules/Financeiro/Http/Controllers/DataController.php.
 *
 * Sidebar: 1 entrada top-level "Fiscal" levando direto a /fiscal/nfe no PR #1
 * (única sub-página entregue). Quando PR #2 entregar cockpit /fiscal,
 * trocar URL pra /fiscal e adicionar sub-itens em SIDEBAR_GROUPS frontend.
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para painel Superadmin > Packages.
     * Tier único Free no PR #1 — pricing tier definido em ADR futura.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'fiscal_module',
                'label'   => __('fiscal::fiscal.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no cadastro de Roles do UltimatePOS.
     * 6 permissões cobrindo PR #1 (NF-e view) e backlog (DF-e, SPED, Config).
     */
    public function user_permissions(): array
    {
        return [
            ['value' => 'fiscal.access',           'label' => __('fiscal::fiscal.permissao_acesso'),      'default' => false],
            ['value' => 'fiscal.nfe.view',         'label' => __('fiscal::fiscal.permissao_nfe_view'),    'default' => false],
            ['value' => 'fiscal.nfe.acoes',        'label' => __('fiscal::fiscal.permissao_nfe_acoes'),   'default' => false],
            ['value' => 'fiscal.nfse.view',        'label' => __('fiscal::fiscal.permissao_nfse_view'),   'default' => false],
            ['value' => 'fiscal.dfe.manage',       'label' => __('fiscal::fiscal.permissao_dfe_manage'),  'default' => false],
            ['value' => 'fiscal.sped.export',      'label' => __('fiscal::fiscal.permissao_sped_export'), 'default' => false],
            ['value' => 'fiscal.config.edit',      'label' => __('fiscal::fiscal.permissao_config_edit'), 'default' => false],
        ];
    }

    /**
     * Injeta menu na sidebar admin. Order=84 (antes do Financeiro 85, depois
     * de NfeBrasil tradicional — mantém afinidade visual fiscal-financeiro).
     *
     * Pattern Wagner 2026-05-18 — habilitação SEMPRE via package subscription
     * + permissão Spatie. NUNCA hardcode `if (business_id === N)`.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Fiscal');
        } else {
            $business_id = session('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'fiscal_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#ffd6a5' : '';
        $segmento_ativo = request()->segment(1) == 'fiscal';

        // Wagner 2026-05-22: Modules/Fiscal entry REMOVIDA do sidebar. Hub
        // canon "Fiscal" agora vem de Modules/NfeBrasil (consolidação ADR 0180
        // — 1 entry + 2 ghosts Notas fiscais·Manifestação). Modules/Fiscal
        // continua existindo (cockpit /fiscal acessível via URL direta), só
        // não declara entry própria pra evitar duplicação visual.
        //
        // Retomar quando PR #2 Wave consolidada entregar cockpit real /fiscal
        // — então renomear/promover entry pra principal e mover NfeBrasil pra
        // ghost. Por ora, NfeBrasil é o canonical Fiscal hub.
    }
}
