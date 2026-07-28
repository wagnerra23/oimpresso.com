<?php

namespace Modules\VozDoCliente\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Modules/VozDoCliente.
 *
 * Convenção UltimatePOS: o middleware AdminSidebarMenu chama
 * `modifyAdminMenu` a cada request da sidebar admin; `superadmin_package` e
 * `user_permissions` são lidos nas telas de Pacotes e de Papéis.
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel),
 * nunca por `if ($business_id === N)` no código — Tier 0 pareado com o
 * `business_id` global scope ({@see memory/proibicoes.md}).
 */
class DataController extends Controller
{
    /**
     * `default => false`: Voz do Cliente é add-on VENDÁVEL, não capacidade
     * incluída no núcleo. Business só passa a ter depois de contratar — e a
     * ativação é marcar aqui + "Atualizar inscrições existentes".
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'vozdocliente_module',
                'label'   => 'Modulo Voz do Cliente (canal de sinal + triagem)',
                'default' => false,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'vozdocliente.reportar',
                'label'   => 'Voz do Cliente: relatar um problema',
                'default' => true,
            ],
            [
                'value'   => 'vozdocliente.triar',
                'label'   => 'Voz do Cliente: ver a caixa e triar sinais',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('VozDoCliente');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'vozdocliente_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        // Só quem tria enxerga a caixa no menu. Quem apenas relata usa o atalho
        // da própria tela (a rota de gravação), não precisa de item de sidebar.
        $pode_triar = auth()->user()->can('superadmin')
            || auth()->user()->can('vozdocliente.triar');

        if (! $pode_triar) {
            return;
        }

        // Label PT-BR literal: LegacyMenuAdapter lê a string crua e NÃO resolve
        // __('alias::file.key') — chave de tradução aqui sai crua em produção.
        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->url(
                action([\Modules\VozDoCliente\Http\Controllers\SinalController::class, 'index']),
                'Voz do Cliente',
                ['icon' => 'fa fas fa-comment-dots', 'active' => request()->segment(1) === 'voz-do-cliente']
            )->order(85);
        });
    }
}
