<?php

namespace Modules\Whatsapp\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * DataController do módulo Whatsapp.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\Whatsapp\Http\Controllers\DataController@modifyAdminMenu` em cada request
 * da sidebar admin (e os hooks superadmin_package/user_permissions na tela de Roles).
 *
 * Espelha o topnav declarativo em Modules/Whatsapp/Resources/menus/topnav.php.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md
 * @see memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md
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

    public function modifyAdminMenu(): void
    {
        // Wagner 2026-05-11: entrada de sidebar removida — o shortcut fixo
        // "WhatsApp" no topo do Sidebar.tsx (SidebarShortcuts) ja e o ponto
        // de entrada unico pra /whatsapp/conversations. Sub-itens (Templates,
        // Configuracoes) permanecem disponiveis via topnav declarativo em
        // Resources/menus/topnav.php (montado pelo LegacyMenuAdapter::buildTopNavs).
    }
}
