<?php

namespace Modules\NfeBrasil\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo NfeBrasil.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\NfeBrasil\Http\Controllers\DataController@modifyAdminMenu`
 * em cada request da sidebar admin.
 *
 * Observações importantes do contexto deste módulo:
 *
 *  - `Modules/NfeBrasil/Resources/lang/` está vazio (apenas .gitkeep).
 *    Usamos strings literais em PT-BR. Quando os lang files forem
 *    criados, trocar pelas chaves `nfebrasil::lang.*` (ou
 *    `nfebrasil::nfebrasil.*` conforme a convenção escolhida).
 *
 *  - Rotas web (Modules/NfeBrasil/Routes/web.php) hoje têm apenas:
 *      - prefix `nfebrasil/install` (Install/uninstall/update)
 *      - resource `nfebrasil` (named `nfebrasil.*`) — placeholder das
 *        próximas sub-ondas, ainda CRUD vazio.
 *    Os itens "Emitir NF-e", "Consultar", "SPED" abaixo são placeholders
 *    apontando para rotas que ainda não existem; vão habilitar conforme
 *    o roadmap (NFC-e em 1 clique, NF-e B2B, SPED).
 *
 * @see Modules/PontoWr2/Http/Controllers/DataController.php   (template)
 * @see Modules/NfeBrasil/module.json                          (descrição)
 */
class DataController extends Controller
{
    /**
     * Feature flag do módulo para Superadmin > Packages.
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name'    => 'nfebrasil_module',
                'label'   => 'Módulo NF-e Brasil',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo — aparecem na tela de Roles do UltimatePOS.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value'   => 'nfebrasil.access',
                'label'   => 'NF-e Brasil: acesso ao módulo',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.emit.manage',
                'label'   => 'NF-e Brasil: emitir notas fiscais',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.consult.view',
                'label'   => 'NF-e Brasil: consultar notas emitidas',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.sped.view',
                'label'   => 'NF-e Brasil: gerar/exportar SPED',
                'default' => false,
            ],
            [
                'value'   => 'nfebrasil.settings.manage',
                'label'   => 'NF-e Brasil: gerenciar configurações fiscais',
                'default' => false,
            ],
            [
                'value'   => 'nfe.configuracao.manage',
                'label'   => 'NF-e Brasil: configurar certificado A1',
                'default' => false,
            ],
            [
                'value'   => 'nfe.tributacao.manage',
                'label'   => 'NF-e Brasil: gerenciar tributação (regras NCM + default)',
                'default' => false,
            ],
            [
                'value'   => 'nfe.manifestacao.view',
                'label'   => 'NF-e Brasil: ver NF-e recebidas (manifestação)',
                'default' => false,
            ],
            [
                'value'   => 'nfe.manifestacao.manage',
                'label'   => 'NF-e Brasil: manifestar NF-e recebidas (Confirmação/Ciência/Desconhecimento/Não Realizada)',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item do módulo na sidebar do AdminLTE.
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('NfeBrasil');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'nfebrasil_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('nfebrasil.access')
            || auth()->user()->can('nfebrasil.emit.manage')
            || auth()->user()->can('nfebrasil.consult.view')
            || auth()->user()->can('nfe.configuracao.manage')
            || auth()->user()->can('nfe.tributacao.manage')
            || auth()->user()->can('nfe.manifestacao.view')
            || auth()->user()->can('nfe.manifestacao.manage');

        if (! $usuario_pode_ver) {
            return;
        }

        // Wagner 2026-05-25: 3 entries flat no grupo FISCAL (sem item "Fiscal"
        // raiz, sem ghosts no PageHeader). Substitui a tentativa 1-entry+ghosts
        // de 2026-05-22 — usuário (Larissa/Martinho) acessa direto Notas Fiscais
        // (cockpit NF-e/NFC-e), Manifestação (DF-e legacy), Certificado (A1).
        // Cockpit /fiscal raiz continua acessível via URL direta + popmenu
        // "+ Emitir" no PageHeader (NF-e/NFC-e/NFS-e — Pages/Fiscal/Cockpit.tsx).
        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color) {
                // Entry 1 — Notas Fiscais (cockpit NF-e/NFC-e funcional).
                $menu->url(
                    url('/fiscal/nfe'),
                    'Notas Fiscais',
                    [
                        'icon'   => 'fa fas fa-receipt',
                        'style'  => 'background-color:' . $background_color,
                        'active' => request()->segment(1) == 'fiscal' && request()->segment(2) == 'nfe',
                        'group'  => 'fiscal',
                    ]
                )->order(95);

                // Entry 2 — Manifestação DF-e (tela legacy NfeBrasil funcional).
                $menu->url(
                    url('/nfe-brasil/manifestacao'),
                    'Manifestação',
                    [
                        'icon'   => 'fa fas fa-inbox',
                        'style'  => 'background-color:' . $background_color,
                        'active' => request()->segment(1) == 'nfe-brasil' && request()->segment(2) == 'manifestacao',
                        'group'  => 'fiscal',
                    ]
                )->order(96);

                // Entry 3 — Certificado A1 (tela legacy NfeBrasil funcional, US-NFE-041).
                $menu->url(
                    url('/nfe-brasil/configuracao/certificado'),
                    'Certificado',
                    [
                        'icon'   => 'fa fas fa-shield-alt',
                        'style'  => 'background-color:' . $background_color,
                        'active' => request()->segment(1) == 'nfe-brasil' && request()->segment(2) == 'configuracao',
                        'group'  => 'fiscal',
                    ]
                )->order(97);
            }
        );
    }
}
