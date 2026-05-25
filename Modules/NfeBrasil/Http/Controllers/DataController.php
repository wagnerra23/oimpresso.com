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

        // Wagner 2026-05-22 direção canon: 1 entry "Fiscal" + 2 ghosts
        // (Notas fiscais · Manifestação). Substitui 8 entries duplicadas.
        // Sub-items (Emitir/SPED/Settings/Certificado/Tributação) ficam
        // acessíveis via URL direta ou tabs internas das telas (TODO Fase 4).
        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        // Wagner 2026-05-25: entry "Fiscal" aponta /fiscal (cockpit unificado
        // Modules/Fiscal — implementado, funcional, 7 abas) em vez de
        // /nfebrasil (controller chama view('create') que não existe → 500).
        // Highlight inclui /fiscal + rotas legacy /nfebrasil ainda usadas pelos
        // ghosts (Notas fiscais, Tributação, Certificado etc — discussão UX
        // separada pra eventual repointed pros equivalentes /fiscal/*).
        $segmento_ativo = in_array(request()->segment(1), ['fiscal', 'nfebrasil', 'nfe-brasil'], true);

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->url(
                    url('/fiscal'),
                    'Fiscal',
                    [
                        'icon'     => 'fa fas fa-file-invoice-dollar',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        // Wagner 2026-05-22: FISCAL virou grupo próprio (era ghost de FINANÇAS).
                        'group'    => 'fiscal',
                        'shortcut' => 'G X',
                        'primary'  => [
                            'label'    => 'Emitir NF-e',
                            'href'     => '/nfebrasil/create',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'notas',         'label' => 'Notas fiscais',     'href' => '/nfebrasil'],
                            ['key' => 'manifestacao',  'label' => 'Manifestação',      'href' => '/nfe-brasil/manifestacao'],
                            ['key' => 'certificado',   'label' => 'Certificado Digital','href' => '/nfe-brasil/configuracao/certificado'],
                            // Wagner 2026-05-22 P1: +4 ghosts pra zerar órfãs Fiscal.
                            ['key' => 'tributacao',    'label' => 'Tributação',        'href' => '/nfe-brasil/tributacao'],
                            ['key' => 'sped',          'label' => 'SPED',              'href' => '/nfebrasil/sped'],
                            ['key' => 'settings',      'label' => 'Configurações',     'href' => '/nfebrasil/settings'],
                            ['key' => 'nfse',          'label' => 'NFSe',              'href' => '/nfse'],
                        ],
                    ]
                )->order(95);
            }
        );
    }
}
