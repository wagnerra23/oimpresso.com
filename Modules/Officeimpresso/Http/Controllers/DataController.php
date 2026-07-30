<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'officeimpresso_module',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões registradas no UI de Roles do UltimatePOS.
     *
     * Adicionado 2026-04-26 (audit DataController). Officeimpresso é
     * superadmin-only por design, mas registramos permissão pra aparecer
     * no UI de Roles (auditoria) e permitir delegação futura.
     */
    public function user_permissions()
    {
        return [
            [
                // Leitura da gestão de licenças de TODAS as empresas licenciadas
                // (empresas, máquinas, pacote, log de acesso). É a visão de
                // suporte: quem atende o cliente precisa ver a máquina DELE, não
                // a própria. Declarada desde 2026-04-26 mas só passou a ser
                // exigida pelos controllers em 2026-07-29 — até então o grupo
                // /officeimpresso/* pedia apenas `auth`.
                'value' => 'officeimpresso.access',
                'label' => __('officeimpresso::lang.officeimpresso_module'),
                'default' => false,
            ],
            [
                // Escrita em máquina individual: liberar/bloquear (toggle-block),
                // criar e editar licença. É a tarefa de assistência do dia a dia,
                // delegável ao suporte sem `superadmin`. Escopo empresa-inteira e
                // exclusão têm permissão própria (abaixo) — ter esta NÃO as concede.
                'value' => 'officeimpresso.licencas.gerenciar',
                'label' => 'Office Impresso: liberar/bloquear máquinas (suporte)',
                'default' => false,
            ],
            [
                // Escopo EMPRESA INTEIRA: versão obrigatória de todos os desktops
                // do cliente + bloquear/liberar o cliente inteiro. Era
                // superadmin-only; delegado por decisão [W] 2026-07-30 pra tirar o
                // superadmin do caminho da gestão de licenças. Ações REVERSÍVEIS —
                // o bloqueio é toggle, a versão se reescreve.
                'value' => 'officeimpresso.empresa.gerenciar',
                'label' => 'Office Impresso: bloquear empresa inteira + versão obrigatória',
                'default' => false,
            ],
            [
                // Exclusão de licença — destrutivo e irreversível (o registro sai
                // do banco e o histórico em licenca_log fica órfão). Permissão
                // separada de propósito: quem bloqueia uma máquina não deveria
                // apagá-la por tabela.
                'value' => 'officeimpresso.licencas.excluir',
                'label' => 'Office Impresso: EXCLUIR licença (destrutivo)',
                'default' => false,
            ],
            [
                // Delegável a um login próprio de funcionário SEM abrir o
                // Financeiro (gated por `superadmin`). Cobre ver a lista e
                // criar/liberar a credencial OAuth do Delphi. ClientController
                // destroy()/regenerate() seguem superadmin-only.
                'value' => 'officeimpresso.clientes.liberar',
                'label' => 'Office Impresso: liberar clientes (credenciais Delphi)',
                'default' => false,
            ],
        ];
    }

    /**
     * Adds Officeimpresso menus a sidebar admin.
     *
     * O menu é montado por permissão, não por cargo. Cada nível vê só os
     * atalhos que consegue abrir — e os controllers barram por conta própria
     * (`abort_unless`), então o menu é conveniência, nunca a autorização:
     *
     * - `superadmin` .................. tudo
     * - `officeimpresso.access` ....... Empresas + Computadores + Licenças + Logs (suporte)
     * - `officeimpresso.clientes.liberar` ... apenas Clientes (credenciais OAuth)
     *
     * Ordem 2 (logo depois de Superadmin order 1).
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        if (! auth()->check()) {
            return;
        }

        $isSuperadmin = auth()->user()->can('superadmin');
        $canAccess = $isSuperadmin || auth()->user()->can('officeimpresso.access');
        $canLiberarClientes = $isSuperadmin || auth()->user()->can('officeimpresso.clientes.liberar');
        $module_util = new ModuleUtil();

        // Sem nenhuma permissão do módulo OU módulo não instalado → nada.
        if ((! $canAccess && ! $canLiberarClientes) || ! $module_util->isModuleInstalled('Officeimpresso')) {
            return;
        }

        // ADR 0180 Fase 4 Wave E — Officeimpresso é ghost virtual de Plataforma
        // no grupo canon `sistema` v3. Ghosts montados por permissão.
        $ghosts = [];

        if ($canAccess) {
            $ghosts[] = ['key' => 'businessall',        'label' => 'Empresas',     'href' => '/officeimpresso/businessall'];
            $ghosts[] = ['key' => 'computadores',       'label' => 'Computadores', 'href' => '/officeimpresso/computadores'];
        }

        if ($canLiberarClientes) {
            $ghosts[] = ['key' => 'client',             'label' => 'Clientes',     'href' => '/officeimpresso/client'];
        }

        if ($canAccess) {
            $ghosts[] = ['key' => 'licenca_computador', 'label' => 'Licenças',     'href' => '/officeimpresso/licenca_computador'];
            $ghosts[] = ['key' => 'licenca_log',        'label' => 'Logs',         'href' => '/officeimpresso/licenca_log'];
        }

        // Base = a primeira tela que o usuário consegue abrir.
        $baseUrl = $canAccess
            ? action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores'])
            : '/officeimpresso/client';

        // `primary` só pra quem consegue abrir a tela — sem isso o atalho
        // levaria o suporte (que tem `access` mas não `clientes.liberar`)
        // direto pra um 403.
        $options = [
            'icon'   => 'fa fas fa-plug',
            'active' => request()->segment(1) == 'officeimpresso',
            'ghosts' => $ghosts,
        ];

        if ($canLiberarClientes) {
            $options['primary'] = [
                'label'    => 'Novo cliente',
                'href'     => '/officeimpresso/client/create',
                'shortcut' => 'N',
            ];
        }

        Menu::modify('admin-sidebar-menu', function ($menu) use ($baseUrl, $options) {
            $menu->url(
                $baseUrl,
                __('officeimpresso::lang.officeimpresso'),
                $options
            )->order(2);
        });
    }
}
