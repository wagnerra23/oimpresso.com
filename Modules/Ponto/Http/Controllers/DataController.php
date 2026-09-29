<?php

namespace Modules\Ponto\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo PontoWr2.
 *
 * Este controller é descoberto automaticamente pelo middleware
 * `AdminSidebarMenu` do core UltimatePOS (convenção:
 * Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu).
 *
 * Ver como referência:
 *  - Modules/Jana/Http/Controllers/DataController.php      (dropdown)
 *  - Modules/Repair/Http/Controllers/DataController.php    (item simples)
 *  - Modules/Forja/Http/Controllers/DataController.php (item simples)
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para o painel Superadmin > Packages.
     *
     * Usado pelo UltimatePOS para permitir que o superadmin inclua ou não o
     * módulo Ponto WR2 em um pacote de assinatura.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'ponto_module',
                'label'   => __('pontowr2::ponto.module_label'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem no cadastro de papéis (Roles) do
     * UltimatePOS. Mantém o mesmo prefixo usado nas rotas e no middleware
     * `ponto.access` (CheckPontoAccess).
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'ponto.access',
                'label'   => __('pontowr2::ponto.permissao_acesso'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.colaboradores.manage',
                'label'   => __('pontowr2::ponto.permissao_colaboradores'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.aprovacoes.manage',
                'label'   => __('pontowr2::ponto.permissao_aprovacoes'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.relatorios.view',
                'label'   => __('pontowr2::ponto.permissao_relatorios'),
                'default' => false,
            ],
            [
                'value'   => 'ponto.configuracoes.manage',
                'label'   => __('pontowr2::ponto.permissao_configuracoes'),
                'default' => false,
            ],
            // US-GOV-059 classe C: o ImportacaoAfdRequest já exigia permissão pra
            // importar AFD, mas com o nome `ponto.importacoes.criar`, que não
            // existia em código nem na tabela `permissions` (verificado em prod).
            // Declarada no padrão `.manage` dos irmãos acima; o consumidor foi
            // alinhado no mesmo PR.
            [
                'value'   => 'ponto.importacoes.manage',
                'label'   => __('pontowr2::ponto.permissao_importacoes'),
                'default' => false,
            ],
            // ADR 0413 D1: fechar competência tem permissão própria (não reusa ponto.access).
            [
                'value'   => 'ponto.fechar',
                'label'   => __('pontowr2::ponto.permissao_fechar'),
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     *
     * Padrão UltimatePOS: o core chama este método a cada request via
     * middleware `AdminSidebarMenu`, e o item só aparece se o módulo estiver
     * habilitado para o business_id corrente (ou se o usuário for superadmin).
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_ponto_enabled = $module_util->isModuleInstalled('Ponto');
        } else {
            $business_id = session()->get('user.business_id');
            $is_ponto_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'ponto_module',
                'superadmin_package'
            );
        }

        if (! $is_ponto_enabled) {
            return;
        }

        // Superadmin sempre vê; usuário comum precisa de ponto.access (mínimo).
        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('ponto.access');

        if (! $usuario_pode_ver) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) == 'ponto';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                // Forma do item = protótipo (`prototipo-ui/cowork/Wagner/data.jsx`, grupo RH:
                // `{ id: "ponto", label: "Ponto" }`, 1º do grupo), soberano no eixo FORMA
                // (ADR UI-0029). Até 2026-09-28 era `$menu->dropdown(...)`: o item virava um
                // botão sem link (href "/#") que abria uma lista de 12 filhos, e ficava depois de
                // HRM e Essenciais. Agora é link direto pro painel + ghosts (ADR 0180), igual ao
                // HRM. `primary` e `ghosts` seguem aqui porque o `PontoSubNav` monta as abas das
                // telas do Ponto a partir deles.
                //
                // Os ghosts herdam o gate de permissão que os filhos do dropdown tinham: sem ele,
                // as abas mostravam Aprovações, Colaboradores e Configurações a quem não pode
                // abrir essas telas.
                $pode = fn (string $perm) => auth()->user()->can('superadmin') || auth()->user()->can($perm);

                $ghosts = [
                    ['key' => 'dashboard',       'label' => 'Dashboard',        'href' => '/ponto'],
                    ['key' => 'espelho',         'label' => 'Espelho',          'href' => '/ponto/espelho'],
                ];
                if ($pode('ponto.aprovacoes.manage')) {
                    $ghosts[] = ['key' => 'aprovacoes', 'label' => 'Aprovações', 'href' => '/ponto/aprovacoes'];
                }
                array_push(
                    $ghosts,
                    ['key' => 'intercorrencias', 'label' => 'Intercorrências',  'href' => '/ponto/intercorrencias'],
                    ['key' => 'banco-horas',     'label' => 'Banco de Horas',   'href' => '/ponto/banco-horas'],
                    ['key' => 'escalas',         'label' => 'Escalas',          'href' => '/ponto/escalas'],
                    ['key' => 'importacoes',     'label' => 'Importações',      'href' => '/ponto/importacoes'],
                    ['key' => 'relatorios',      'label' => 'Relatórios',       'href' => '/ponto/relatorios'],
                    // ADR 0413: ver o fechamento é `ponto.access`; fechar exige `ponto.fechar` (na rota POST).
                    ['key' => 'fechamento',      'label' => 'Fechamento',       'href' => '/ponto/fechamento'],
                    ['key' => 'conformidade',    'label' => 'Conformidade',     'href' => '/ponto/conformidade'],
                );
                if ($pode('ponto.colaboradores.manage')) {
                    $ghosts[] = ['key' => 'colaboradores', 'label' => 'Colaboradores', 'href' => '/ponto/colaboradores'];
                }
                if ($pode('ponto.configuracoes.manage')) {
                    $ghosts[] = ['key' => 'configuracoes', 'label' => 'Configurações', 'href' => '/ponto/configuracoes'];
                }

                $menu->url(
                    route('ponto.dashboard'),
                    __('pontowr2::ponto.module_label'),
                    [
                        'icon'    => 'fa fas fa-business-time',
                        'style'   => 'background-color:' . $background_color,
                        'active'  => $segmento_ativo,
                        'primary' => [
                            // Era "Bater ponto" até 2026-09-29: o destino é o painel (/ponto), e não
                            // existe tela web de bater ponto — o rótulo prometia uma ação que não há.
                            'label'    => 'Painel do ponto',
                            'href'     => '/ponto',
                            'shortcut' => 'N',
                        ],
                        'ghosts'  => $ghosts,
                    ]
                )->order(86); // 1º do grupo RH, antes do HRM (87) e do Essenciais (88) — protótipo
            }
        );
    }
}
