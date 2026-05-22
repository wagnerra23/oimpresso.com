<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Financeiro.
 *
 * Descoberto automaticamente pelo middleware `AdminSidebarMenu` do core
 * UltimatePOS (convenção: Modules\<Nome>\Http\Controllers\DataController).
 *
 * Ver pattern: Modules/PontoWr2/Http/Controllers/DataController.php
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para painel Superadmin > Packages.
     * 3 tiers: Free / Pro R$ 199 / Enterprise R$ 599 (ver requisitos/Financeiro/README.md).
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'financeiro_module',
                'label'   => __('financeiro::financeiro.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no cadastro de Roles do UltimatePOS.
     * 13 permissões alinhadas com SPEC.md US-FIN-001 .. US-FIN-013.
     */
    public function user_permissions(): array
    {
        return [
            ['value' => 'financeiro.access',                     'label' => __('financeiro::financeiro.permissao_acesso'),                     'default' => false],
            ['value' => 'financeiro.dashboard.view',             'label' => __('financeiro::financeiro.permissao_dashboard_view'),             'default' => false],
            ['value' => 'financeiro.contas_receber.view',        'label' => __('financeiro::financeiro.permissao_contas_receber_view'),        'default' => false],
            ['value' => 'financeiro.contas_receber.create',      'label' => __('financeiro::financeiro.permissao_contas_receber_create'),      'default' => false],
            ['value' => 'financeiro.contas_receber.baixar',      'label' => __('financeiro::financeiro.permissao_contas_receber_baixar'),      'default' => false],
            ['value' => 'financeiro.contas_pagar.view',          'label' => __('financeiro::financeiro.permissao_contas_pagar_view'),          'default' => false],
            ['value' => 'financeiro.contas_pagar.create',        'label' => __('financeiro::financeiro.permissao_contas_pagar_create'),        'default' => false],
            ['value' => 'financeiro.contas_pagar.pagar',         'label' => __('financeiro::financeiro.permissao_contas_pagar_pagar'),         'default' => false],
            ['value' => 'financeiro.titulo.aprovar',             'label' => __('financeiro::financeiro.permissao_titulo_aprovar'),             'default' => false],
            ['value' => 'financeiro.caixa.view',                 'label' => __('financeiro::financeiro.permissao_caixa_view'),                 'default' => false],
            ['value' => 'financeiro.contas_bancarias.manage',    'label' => __('financeiro::financeiro.permissao_contas_bancarias_manage'),    'default' => false],
            ['value' => 'financeiro.conciliacao.manage',         'label' => __('financeiro::financeiro.permissao_conciliacao_manage'),         'default' => false],
            ['value' => 'financeiro.relatorios.view',            'label' => __('financeiro::financeiro.permissao_relatorios_view'),            'default' => false],
            ['value' => 'financeiro.relatorios.share',           'label' => __('financeiro::financeiro.permissao_relatorios_share'),           'default' => false],
            ['value' => 'financeiro.extrato.view',               'label' => __('financeiro::financeiro.permissao_extrato_view'),               'default' => false],
            // Onda 31 (2026-05-20) #57 US-FIN-037 — Portal Advisor contadores parceiros.
            ['value' => 'financeiro.advisor.grant',              'label' => __('financeiro::financeiro.permissao_advisor_grant'),              'default' => false],
        ];
    }

    /**
     * Injeta menu na sidebar admin. Order=85 (antes do PontoWr2 order=88).
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Financeiro');
        } else {
            $business_id = session('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'financeiro_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        // Wagner 2026-05-18: removido gate SUPERADMIN-ONLY (era "em desenvolvimento"
        // 2026-04-25). Após Onda 7 KB-9.75 (Curadoria+IA+CrossLink) módulo está
        // piloto-ready. Visibilidade agora controlada pelo pacote subscription
        // do business (configurável via Modules/Superadmin/PackagesController) +
        // permissão de usuário `financeiro.access` — pattern UltimatePOS canon
        // (NÃO hardcode biz=N — Wagner 2026-05-18: "habilitar é compra de
        // pacote no modulo superadmin").
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('financeiro.access')) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#ffd6a5' : '';
        $segmento_ativo = request()->segment(1) == 'financeiro';

        // Wagner 2026-05-22 direção canon: 1 entry sidebar + 4 ghosts canon
        // (Caixa · Cobrança · Financeiro · Relatório). Remove 12 entries
        // duplicadas que poluíam o grupo FINANÇAS. Sub-items são absorvidos
        // como tabs/seções DENTRO dos 4 hubs canon (acessíveis via header
        // PageHeaderTabs ARIA + URL direta).
        //
        // Mapping canon (Wagner 2026-05-22):
        //   Caixa     → /financeiro/caixa      (absorve Conciliação + Contas Bancárias)
        //   Cobrança  → /financeiro/cobranca   (absorve Contas a Receber)
        //   Financeiro → /financeiro/unificado (default — absorve Contas a Pagar + Fluxo)
        //   Relatório → /financeiro/relatorios (absorve DRE)
        //
        // TODO Fase 4 user menu cascade (Wagner aprovou 2026-05-22):
        //   Plano de Contas + Categorias + Contador vão pra cascade
        //   "Configurações do business" no user menu (rodapé). Por ora,
        //   acessíveis via URL direta /financeiro/plano-contas, /categorias,
        //   /configuracoes/contador (rotas continuam existindo).
        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // Entry única — hub Financeiro com 4 ghosts canon no header.
                //
                // ADR 0180 + Wagner 2026-05-22 direção: reduz 13→4 ghosts.
                // PageHeaderTabs renderiza inline (Caixa/Cobrança/Financeiro/
                // Relatório), todos visíveis sem overflow.
                $menu->url(
                    url('/financeiro/unificado'),
                    __('financeiro::financeiro.module_label'),
                    [
                        'icon'     => 'fa fas fa-coins',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        'group'    => 'financas',
                        'shortcut' => 'G F',
                        'primary'  => [
                            'label'    => 'Novo título',
                            'href'     => '/financeiro/lancamentos/create',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'caixa',      'label' => 'Caixa',      'href' => '/financeiro/caixa'],
                            ['key' => 'cobranca',   'label' => 'Cobrança',   'href' => '/financeiro/cobranca'],
                            ['key' => 'financeiro', 'label' => 'Financeiro', 'href' => '/financeiro/unificado'],
                            ['key' => 'relatorio',  'label' => 'Relatório',  'href' => '/financeiro/relatorios'],
                        ],
                    ]
                )->order(85.00);
            }
        );
    }
}
