<?php

namespace Modules\ADS\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo ADS (Adaptive Decision System).
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\ADS\Http\Controllers\DataController@modifyAdminMenu` em cada request
 * da sidebar admin (e os hooks superadmin_package/user_permissions na tela de Roles).
 *
 * Espelha o topnav declarativo em Modules/ADS/Resources/menus/topnav.php.
 *
 * @see memory/requisitos/ADS/SPEC.md
 * @see memory/requisitos/ADS/adr/arq/ARQ-0001-ads-escopo-e-papel-unico.md
 */
class DataController extends Controller
{
    /**
     * Feature flag em Superadmin > Packages.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'ads_module',
                'label'   => 'Módulo ADS (Adaptive Decision System)',
                'default' => false,
            ],
        ];
    }

    /**
     * Permissões do módulo na tela de Roles.
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'ads.access',
                'label'   => 'ADS: acessar módulo',
                'default' => false,
            ],
            [
                'value'   => 'ads.decisoes.review',
                'label'   => 'ADS: revisar decisões pendentes',
                'default' => false,
            ],
            [
                'value'   => 'ads.decisoes.approve',
                'label'   => 'ADS: aprovar/rejeitar decisões (HiTL-2/HiTL-3)',
                'default' => false,
            ],
            [
                'value'   => 'ads.policy.manage',
                'label'   => 'ADS: gerenciar Policy Engine (firewall)',
                'default' => false,
            ],
        ];
    }

    /**
     * Injeta o item ADS na sidebar do AdminLTE.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('ADS');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'ads_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('ads.access')
            || auth()->user()->can('ads.decisoes.review');

        if (! $usuario_pode_ver) {
            return;
        }

        // Wagner 2026-05-22: ADS entry REMOVIDA do sidebar — virou ghost
        // do hub IA/Jana (Modules/Jana DataController). Tela /ads continua
        // acessível via URL direta + ghost no header /ia. Espelha pattern
        // NFSe/Fiscal/RecurringBilling (consolidação ADR 0180).
        return;

        // ↓ Código legacy preservado pra retomada futura se necessário ↓
        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo   = request()->segment(1) === 'ads';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    'ADS',
                    function ($sub) {
                        $segment3 = request()->segment(3);

                        // ─── ESTRATÉGIA ───
                        $sub->url(url('/ads/admin/projects'), 'Projects', [
                            'icon'   => 'fa fas fa-folder-tree',
                            'active' => $segment3 === 'projects',
                        ]);

                        // ─── DECISÃO (Conflitos virou tab interna) ───
                        $sub->url(url('/ads/admin/decisoes'), 'Decisões', [
                            'icon'   => 'fa fas fa-inbox',
                            'active' => $segment3 === 'decisoes' || $segment3 === 'conflicts',
                        ]);

                        // ─── CONHECIMENTO ───
                        $sub->url(url('/ads/admin/kb'), 'Knowledge Base', [
                            'icon'   => 'fa fas fa-book',
                            'active' => $segment3 === 'kb',
                        ]);
                        $sub->url(url('/ads/admin/skills'), 'Skills', [
                            'icon'   => 'fa fas fa-bolt',
                            'active' => in_array($segment3, ['skills', 'patterns', 'confidence'], true),
                        ]);
                        $sub->url(url('/ads/admin/tools'), 'Tools', [
                            'icon'   => 'fa fas fa-wrench',
                            'active' => $segment3 === 'tools',
                        ]);
                        $sub->url(url('/ads/admin/graph'), 'Knowledge Graph', [
                            'icon'   => 'fa fas fa-project-diagram',
                            'active' => $segment3 === 'graph',
                        ]);

                        // ─── GOVERNANÇA ───
                        $sub->url(url('/ads/admin/meta-skills'), 'Meta-skills', [
                            'icon'   => 'fa fas fa-brain',
                            'active' => $segment3 === 'meta-skills',
                        ]);
                        $sub->url(url('/ads/admin/team-scopes'), 'Team Scopes', [
                            'icon'   => 'fa fas fa-users-cog',
                            'active' => $segment3 === 'team-scopes',
                        ]);
                        $sub->url(url('/ads/admin/policy'), 'Policy', [
                            'icon'   => 'fa fas fa-shield-alt',
                            'active' => $segment3 === 'policy',
                        ]);

                        // ─── MEDIÇÃO (Learning virou tab interna) ───
                        $sub->url(url('/ads/admin/metricas'), 'Métricas', [
                            'icon'   => 'fa fas fa-chart-bar',
                            'active' => $segment3 === 'metricas' || $segment3 === 'learning',
                        ]);
                    },
                    [
                        'icon'    => 'fa fas fa-microchip',
                        'style'   => 'background-color:' . $background_color,
                        'active'  => $segmento_ativo,
                        // ADR 0180 Fase 4 Wave E — ADS é ghost virtual de
                        // Governança no grupo canon `sistema` v3. Sem `shortcut`
                        // (não tem atalho próprio); `primary` aponta para
                        // Decisões pendentes (operação mais comum HiTL-2/3);
                        // `ghosts` consolida as 10 sub-views (Estratégia,
                        // Decisão, Conhecimento, Governança, Medição).
                        'primary' => [
                            'label'    => 'Decisões pendentes',
                            'href'     => '/ads/admin/decisoes',
                            'shortcut' => 'D',
                        ],
                        'ghosts'  => [
                            ['key' => 'projects',     'label' => 'Projects',        'href' => '/ads/admin/projects'],
                            ['key' => 'decisoes',     'label' => 'Decisões',        'href' => '/ads/admin/decisoes'],
                            ['key' => 'kb',           'label' => 'Knowledge Base',  'href' => '/ads/admin/kb'],
                            ['key' => 'skills',       'label' => 'Skills',          'href' => '/ads/admin/skills'],
                            ['key' => 'tools',        'label' => 'Tools',           'href' => '/ads/admin/tools'],
                            ['key' => 'graph',        'label' => 'Knowledge Graph', 'href' => '/ads/admin/graph'],
                            ['key' => 'meta-skills',  'label' => 'Meta-skills',     'href' => '/ads/admin/meta-skills'],
                            ['key' => 'team-scopes',  'label' => 'Team Scopes',     'href' => '/ads/admin/team-scopes'],
                            ['key' => 'policy',       'label' => 'Policy',          'href' => '/ads/admin/policy'],
                            ['key' => 'metricas',     'label' => 'Métricas',        'href' => '/ads/admin/metricas'],
                        ],
                    ]
                )->order(98);
            }
        );
    }
}
