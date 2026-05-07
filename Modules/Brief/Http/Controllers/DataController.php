<?php

namespace Modules\Brief\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Brief.
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama
 * `Modules\Brief\Http\Controllers\DataController@modifyAdminMenu` em cada
 * request (e os hooks superadmin_package / user_permissions na tela de Roles).
 *
 * Brief é primariamente backend (tool MCP `brief-fetch`), mas precisa do
 * DataController pra GUARD-02 Pest passar e pro botão Install funcionar
 * em /manage-modules.
 *
 * @see memory/decisions/0091-daily-brief.md
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'brief_module',
                'label'   => 'Módulo Brief (Daily Brief L7 — ADR 0091)',
                'default' => true, // L7 é Tier A always-on (camada Constituição V2)
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'brief.access',
                'label'   => 'Brief: acessar tool brief-fetch + admin (futuro)',
                'default' => false,
            ],
        ];
    }

    /**
     * Item no sidebar admin. Brief não tem tela própria ainda — placeholder
     * pra US-COPI-090/091 quando UI status admin entrar.
     */
    public function modifyAdminMenu(): void
    {
        // No-op por enquanto. Brief vive via tool MCP. Se Wagner quiser
        // tela admin de status (lista briefs gerados, token count), adicionar
        // aqui apontando pra /brief/admin/status.
    }
}
