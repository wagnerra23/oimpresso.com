<?php

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Modules/Producao (ADR 0431).
 *
 * Convenção UltimatePOS: `superadmin_package` e `user_permissions` são lidos nas
 * telas de Pacotes e de Papéis; `modifyAdminMenu` roda a cada request da sidebar.
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca
 * por `if ($business_id === N)` no código — Tier 0 pareado com o `business_id`
 * global scope ({@see memory/proibicoes.md}).
 */
class DataController extends Controller
{
    /**
     * `default => false`: Produção é add-on contratado. O piloto (biz=1, D-PILOTO)
     * entra marcando o pacote, não por código.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'producao_module',
                'label'   => 'Modulo Producao (PCP)',
                'default' => false,
            ],
        ];
    }

    /**
     * As duas últimas foram aprovadas por [W] em 2026-10-15 (D-PERM). Sem
     * `producao.desempenho.ver`, produtividade só aparece por setor, nunca por
     * pessoa (UC-PCV-27).
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'producao.access',
                'label'   => 'Producao: acessar o modulo',
                'default' => false,
            ],
            [
                'value'   => 'producao.etapa.mover',
                'label'   => 'Producao: mover etapa da OS no quadro',
                'default' => false,
            ],
            [
                'value'   => 'producao.desempenho.ver',
                'label'   => 'Producao: ver produtividade por pessoa',
                'default' => false,
            ],
        ];
    }

    /**
     * Item da sidebar desde a thread 03 (primeiras telas: Roteiros e Postos). Três camadas,
     * como o golden do Arquivos: pacote `producao_module` → permissão `producao.access` → item.
     * O item apontava para Roteiros até o Painel existir; desde a thread 06 (2026-10-21) abre o Painel,
     * que é a vista inicial do protótipo (`producao-cv-page.jsx`, VISTAS[0]).
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Producao');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'producao_module', 'superadmin_package');
        }

        if (! $is_enabled || ! (auth()->user()->can('superadmin') || auth()->user()->can('producao.access'))) {
            return;
        }

        $segmento_ativo = request()->segment(1) === 'producao';

        Menu::modify('admin-sidebar-menu', function ($menu) use ($segmento_ativo) {
            $menu->url(url('/producao/painel'), 'Produção', [
                'icon' => 'fa fa-industry',
                'active' => $segmento_ativo,
                // [W] 2026-10-19: Produção fica no grupo PRODUÇÃO do sidebar. Sem `group`
                // o label 'Produção' casava a whitelist de CADASTRO (Sidebar.tsx).
                'group' => 'producao',
            ]);
        });
    }
}
