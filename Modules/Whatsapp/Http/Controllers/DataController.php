<?php

namespace Modules\Whatsapp\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController do módulo Whatsapp (label visível: "Atendimento").
 *
 * @see memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
 * @see memory/decisions/0135-omnichannel-arquitetura-whatsapp-base.md
 * @see memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'whatsapp_module',
                'label'   => 'Módulo Whatsapp (Z-API + Meta Cloud)',
                'default' => false,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => 'whatsapp.access',           'label' => 'Whatsapp: acessar módulo',                  'default' => false],
            ['value' => 'whatsapp.send',             'label' => 'Whatsapp: enviar mensagem manual',          'default' => false],
            ['value' => 'whatsapp.assign',           'label' => 'Whatsapp: atribuir conversa a atendente',   'default' => false],
            ['value' => 'whatsapp.templates.manage', 'label' => 'Whatsapp: gerenciar templates HSM/locais',  'default' => false],
            ['value' => 'whatsapp.settings.manage',  'label' => 'Whatsapp: configurar drivers (Z-API/Meta)', 'default' => false],
            ['value' => 'whatsapp.metricas.view',    'label' => 'Whatsapp: ver métricas (custo/deflection)', 'default' => false],
        ];
    }

    /**
     * Wagner 2026-05-22 P0: refaz contrato v2 que estava no PR #1392 fechado.
     * Hub Atendimento canon ADR 0180 — declara group:'atendimento' (HIDDEN_GROUP
     * no sidebar — shortcut topo cobre) + 7 ghosts visíveis no header /atendimento.
     *
     * Zera 6 órfãs: Channels/Csat/Macros/Metricas/JanaTemplates/Employee Scorecards
     * (todas acessíveis agora via ghost no header em vez de só URL direta).
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Whatsapp');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'whatsapp_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        if (! (auth()->user()->can('superadmin') || auth()->user()->can('whatsapp.access'))) {
            return;
        }

        $background_color = config('app.env') == 'demo' ? '#a8d8ea' : '';
        $segmento_ativo = request()->segment(1) === 'atendimento';

        Menu::modify(
            'admin-sidebar-menu',
            function ($menu) use ($background_color, $segmento_ativo) {
                $menu->dropdown(
                    'Atendimento',
                    function ($sub) {
                        $sub->url(route('atendimento.caixa-unificada.index'), 'Caixa', [
                            'icon'   => 'fa fas fa-inbox',
                            'active' => request()->segment(2) === 'caixa-unificada',
                        ]);

                        if (auth()->user()->can('superadmin') || auth()->user()->can('whatsapp.settings.manage')) {
                            $sub->url(route('atendimento.channels.index'), 'Canais', [
                                'icon'   => 'fa fas fa-plug',
                                'active' => request()->segment(2) === 'canais',
                            ]);
                            $sub->url(route('atendimento.canais.jana_templates.show'), 'Templates', [
                                'icon'   => 'fa fas fa-file-alt',
                                'active' => request()->segment(3) === 'jana-templates',
                            ]);
                            $sub->url(route('atendimento.macros.index'), 'Macros', [
                                'icon'   => 'fa fas fa-bolt',
                                'active' => request()->segment(2) === 'macros',
                            ]);
                        }

                        $sub->url(route('atendimento.metricas.index'), 'Métricas', [
                            'icon'   => 'fa fas fa-chart-line',
                            'active' => request()->segment(2) === 'metricas',
                        ]);
                        $sub->url(route('atendimento.csat.index'), 'CSAT', [
                            'icon'   => 'fa fas fa-smile',
                            'active' => request()->segment(2) === 'csat',
                        ]);
                        $sub->url(route('atendimento.employee.scorecards'), 'Time', [
                            'icon'   => 'fa fas fa-trophy',
                            'active' => request()->segment(2) === 'employee',
                        ]);
                    },
                    [
                        'icon'     => 'fa fas fa-comments',
                        'style'    => 'background-color:' . $background_color,
                        'active'   => $segmento_ativo,
                        'group'    => 'atendimento',
                        'shortcut' => 'G A',
                        'primary'  => [
                            'label'    => 'Nova conversa',
                            'href'     => '/atendimento?new=1',
                            'shortcut' => 'N',
                        ],
                        'ghosts'   => [
                            ['key' => 'caixa',     'label' => 'Caixa',     'href' => '/atendimento/caixa-unificada'],
                            ['key' => 'canais',    'label' => 'Canais',    'href' => '/atendimento/canais'],
                            ['key' => 'templates', 'label' => 'Templates', 'href' => '/atendimento/canais/jana-templates'],
                            ['key' => 'macros',    'label' => 'Macros',    'href' => '/atendimento/macros'],
                            ['key' => 'metricas',  'label' => 'Métricas',  'href' => '/atendimento/metricas'],
                            ['key' => 'csat',      'label' => 'CSAT',      'href' => '/atendimento/csat'],
                            ['key' => 'time',      'label' => 'Time',      'href' => '/atendimento/employee/scorecards'],
                        ],
                    ]
                )->order(89);
            }
        );
    }
}
