<?php

namespace Modules\Connector\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    public function superadmin_package()
    {
        return [
            [
                'name' => 'connector_module',
                'label' => __('connector::lang.connector_module'),
                'default' => false,
            ],
        ];
    }

    /**
     * Permissoes do modulo no UI de Roles: nenhuma ([W] D1, 2026-08-19 — CONN-O4).
     * A permissao de acesso que havia aqui saiu: era declarada e nenhuma checagem a usava; tudo no
     * painel e `superadmin`. O metodo fica (vazio) porque o ModuleUtil coleta
     * `user_permissions` de todo DataController.
     *
     * @return array<int, array<string, mixed>>
     */
    public function user_permissions()
    {
        return [];
    }

    /**
     * Adds Connector menus
     *
     * @return void
     */
    public function modifyAdminMenu()
    {
        // CONN-O4 · [W] D1 (2026-08-19): emitir/excluir credencial de API e de superadmin e
        // nao se delega. O catalogo de permissoes do modulo ficou vazio de proposito: a antiga
        // permissao de acesso era declarada e nunca verificada (sugeria uma delegacao que nao
        // existe). O menu so aparece para quem pode abrir o painel; sem superadmin, o unico
        // item que restava era o link /docs, que saiu ([W] D5 — vira a aba Documentacao).
        if (! auth()->user()->can('superadmin')) {
            return;
        }
        if (! (new ModuleUtil())->isModuleInstalled('Connector')) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown(
                __('connector::lang.connector'),
                function ($sub) {
                    $sub->url(
                        action([\Modules\Connector\Http\Controllers\ClientController::class, 'index']),
                        __('connector::lang.clients'),
                        ['icon' => 'fa fas fa-network-wired', 'active' => request()->segment(1) == 'connector' && request()->segment(2) == 'client']
                    );
                },
                [
                    'icon'    => 'fas fa-plug',
                    // ADR 0180 Fase 4 Wave E — Connector e ghost virtual de Plataforma no grupo
                    // canon `sistema` v3. CONN-O4: o primario abre o painel (`/connector/client`,
                    // onde "Novo API client" e um Dialog) — antes ia a `/connector/client/create`,
                    // view inexistente (500, UC-CONN-15). O ghost `/docs` saiu ([W] D5).
                    'primary' => [
                        'label'    => 'Novo API client',
                        'href'     => '/connector/client',
                        'shortcut' => 'N',
                    ],
                    'ghosts'  => [
                        ['key' => 'clients', 'label' => 'API Clients', 'href' => '/connector/client'],
                    ],
                ]
            )->order(6);
        });
    }
}
