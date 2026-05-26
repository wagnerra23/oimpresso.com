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
        $is_fiscal = request()->segment(1) == 'fiscal';

        // 2026-05-25 — Onda ESTABILIZAR (GAP-FISCAL-001): cockpit Fiscal volta a
        // ser hub canon do sidebar com 4 entries flat no grupo fiscal apontando
        // pra /fiscal/*. NfeBrasil/DataController removeu as 3 entries duplicadas
        // (Notas/Manifestação/Certificado) — agora elas vivem aqui consolidadas
        // sob o cockpit. NfeBrasil vira motor headless (Services + rotas
        // backend ainda existem pra superadmin troubleshooting).
        //
        // 2026-05-26 — Wagner direção: REMOVIDA entry "Fiscal" (cockpit dashboard
        // order 93) do sidebar — duplicava visualmente com "Notas Fiscais" logo
        // abaixo. Rota /fiscal CONTINUA ativa (FiscalCockpitController.index) —
        // acesso via URL direta ou Cmd+K palette. Sidebar fica com 3 entries
        // flat: Notas Fiscais · Manifestação · Certificado.
        //
        // Ordering: Notas 95 · Manifestação 96 · Certificado 97.
        // Mantém afinidade visual antes do Financeiro (order 85).
        Menu::modify('admin-sidebar-menu', function ($menu) use ($background_color, $is_fiscal) {
            // Entry 1 — Notas Fiscais (cockpit NF-e/NFC-e — sub-página 2)
            // Active também quando segment(2) é null pra cobrir /fiscal raiz
            // (URL direta cai aqui visualmente até o user clicar).
            $menu->url(
                url('/fiscal/nfe'),
                'Notas Fiscais',
                [
                    'icon'   => 'fa fas fa-receipt',
                    'style'  => 'background-color:' . $background_color,
                    'active' => $is_fiscal && in_array(request()->segment(2), [null, 'nfe'], true),
                    'group'  => 'fiscal',
                ]
            )->order(95);

            // Entry 2 — Manifestação DF-e (Fiscal/Dfe.tsx — sub-página 4)
            // Pré-2026-05-25 apontava pra /nfe-brasil/manifestacao (legacy NfeBrasil).
            // Consolidado pra /fiscal/dfe canon (com 4 botões via ManifestacaoService).
            $menu->url(
                url('/fiscal/dfe'),
                'Manifestação',
                [
                    'icon'   => 'fa fas fa-inbox',
                    'style'  => 'background-color:' . $background_color,
                    'active' => $is_fiscal && request()->segment(2) === 'dfe',
                    'group'  => 'fiscal',
                ]
            )->order(96);

            // Entry 3 — Certificado A1 (Fiscal/Config.tsx — sub-página 6)
            // Pré-2026-05-25 apontava pra /nfe-brasil/configuracao/certificado.
            // Consolidado pra /fiscal/config canon (read-only + link Editar → NfeBrasil
            // pra upload cert — fluxo permanece em NfeBrasil canon).
            $menu->url(
                url('/fiscal/config'),
                'Certificado',
                [
                    'icon'   => 'fa fas fa-shield-alt',
                    'style'  => 'background-color:' . $background_color,
                    'active' => $is_fiscal && request()->segment(2) === 'config',
                    'group'  => 'fiscal',
                ]
            )->order(97);
        });
    }
}
