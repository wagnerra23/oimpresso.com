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
     * 3 tiers: Free / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] (ver requisitos/Financeiro/README.md).
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
        $seg2 = request()->segment(2);

        // Wagner 2026-05-26 direção canon: grupo FINANÇAS agora tem 4 entries
        // FLAT no sidebar — substitui 1 entry "Financeiro" com 13 ghosts.
        // Espelha PR #1541 (Fiscal flat) e PR #1547 (Governança removida).
        //
        //   sidebar FINANÇAS
        //     ├── Caixa             /financeiro/caixa            (order 85.00)
        //     ├── Cobrança          /financeiro/cobranca         (order 85.10) — Gateway vira GHOST aqui
        //     ├── Financeiro (hub)  /financeiro/unificado        (order 85.20)
        //     └── Cobrança Recorrente /recurring-billing         (order 86.00 — RecurringBilling DataController)
        //
        // Gateway de Pagamento NÃO é mais entry própria do sidebar (PaymentGateway
        // DataController deixou de chamar Menu::modify). Vive como ghost da
        // Cobrança — fica acessível via PageHeader em /financeiro/cobranca.
        //
        // Sub-items legacy (DRE/Fluxo/Conciliação/Contas a Pagar/Plano de Contas/
        // Categorias/Dashboard/Extrato/Contador/Relatórios) continuam acessíveis
        // como ghosts do hub "Financeiro" (terceira entry).
        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $seg2) {
                // ENTRY 1 — Caixa (operação Larissa diária: abrir/fechar caixa).
                $menu->url(
                    url('/financeiro/caixa'),
                    'Caixa',
                    [
                        'icon'     => 'fa fas fa-cash-register',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $seg2 === 'caixa',
                        'group'    => 'financas',
                        'shortcut' => 'G C',
                        'primary'  => [
                            'label'    => 'Abrir caixa',
                            'href'     => '/financeiro/caixa',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'caixa',            'label' => 'Caixa',            'href' => '/financeiro/caixa'],
                            ['key' => 'conciliacao',      'label' => 'Conciliação',      'href' => '/financeiro/conciliacao'],
                            ['key' => 'contas-bancarias', 'label' => 'Contas Bancárias', 'href' => '/financeiro/contas-bancarias'],
                            ['key' => 'extrato',          'label' => 'Extrato',          'href' => '/financeiro/extrato'],
                        ],
                    ]
                )->order(85.00);

                // ENTRY 2 — Cobrança (emitir boleto/pix/cartão). Gateway de
                // Pagamento é GHOST aqui (PR #1577+ Wagner 2026-05-26).
                $cobrancaAtiva = in_array($seg2, ['cobranca', 'contas-receber', 'boletos'])
                    || (request()->segment(1) === 'settings' && $seg2 === 'payment-gateways');
                $menu->url(
                    url('/financeiro/cobranca'),
                    'Cobrança',
                    [
                        'icon'     => 'fa fas fa-file-invoice-dollar',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $cobrancaAtiva,
                        'group'    => 'financas',
                        'shortcut' => 'G B',
                        'primary'  => [
                            'label'    => 'Nova cobrança',
                            'href'     => '/financeiro/cobranca',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'cobranca',        'label' => 'Cobrança',         'href' => '/financeiro/cobranca'],
                            ['key' => 'contas-receber',  'label' => 'Contas a Receber', 'href' => '/financeiro/contas-receber'],
                            ['key' => 'gateway',         'label' => 'Gateway',          'href' => '/settings/payment-gateways'],
                        ],
                    ]
                )->order(85.10);

                // ENTRY 3 — Financeiro (hub geral). Mantém label do lang module_label
                // pra compat com modules superadmin + traduções i18n. Ghosts =
                // restante das telas legacy (Fluxo/DRE/Pagar/Categorias/etc).
                $financeiroAtiva = in_array($seg2, [
                    'unificado','lancamentos','contas-pagar','fluxo','dre','plano-contas',
                    'categorias','dashboard','configuracoes','relatorios',
                ]);
                $menu->url(
                    url('/financeiro/unificado'),
                    __('financeiro::financeiro.module_label'),
                    [
                        'icon'     => 'fa fas fa-coins',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $financeiroAtiva,
                        'group'    => 'financas',
                        'shortcut' => 'G F',
                        'primary'  => [
                            'label'    => 'Novo título',
                            'href'     => '/financeiro/lancamentos/create',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'unificado',     'label' => 'Lançamentos',    'href' => '/financeiro/unificado'],
                            ['key' => 'contas-pagar',  'label' => 'Contas a Pagar', 'href' => '/financeiro/contas-pagar'],
                            ['key' => 'fluxo',         'label' => 'Fluxo de Caixa', 'href' => '/financeiro/fluxo'],
                            ['key' => 'dre',           'label' => 'DRE',            'href' => '/financeiro/dre'],
                            ['key' => 'relatorios',    'label' => 'Relatórios',     'href' => '/financeiro/relatorios'],
                            // F2 PR-2 (2026-06-10) — Impostos & obrigações (estimativa Simples; oficial = Fiscal).
                            ['key' => 'impostos',      'label' => 'Impostos',       'href' => '/financeiro/impostos'],
                            // Dashboard deprecado 2026-06-06 (Wagner não usa) — landing = Unificada.
                            ['key' => 'plano-contas',  'label' => 'Plano de Contas','href' => '/financeiro/plano-contas'],
                            ['key' => 'categorias',    'label' => 'Categorias',     'href' => '/financeiro/categorias'],
                            ['key' => 'contador',      'label' => 'Contador',       'href' => '/financeiro/configuracoes/contador'],
                        ],
                    ]
                )->order(85.20);
            }
        );
    }
}
