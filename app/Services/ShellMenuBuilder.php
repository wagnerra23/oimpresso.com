<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Constrói o menu do AppShell React.
 *
 * Estratégia: **reusa o mecanismo legado do UltimatePOS** (nwidart/laravel-menus
 * + middleware AdminSidebarMenu + DataController::modifyAdminMenu por módulo).
 * Isso garante que CADA módulo instalado em `Modules/` já aparece no shell sem
 * mexer em nada — a mesma `Menu::modify('admin-sidebar-menu', ...)` que popula
 * a sidebar velha popula a nova também.
 *
 * Como conversão vai de nwidart MenuItem → MenuItem JSON do React, ver
 * LegacyMenuAdapter.
 *
 * IMPORTANTE: este builder deve ser chamado DEPOIS que o middleware
 * `AdminSidebarMenu` rodou. Dentro de share() do HandleInertiaRequests como
 * closure lazy (`fn () => ...`) isso é garantido — o share só é resolvido
 * quando o Inertia renderiza a resposta, bem depois do pipeline de middleware.
 *
 * REQUISITO: rotas Inertia que usam este shell DEVEM ter o middleware
 * `AdminSidebarMenu` no stack (já é o padrão do UltimatePOS). Se uma rota
 * nova não tiver, o menu vem vazio.
 */
class ShellMenuBuilder
{
    public function __construct(protected LegacyMenuAdapter $legacy)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(Request $request): array
    {
        if (!$request->user()) {
            return [];
        }

        // Lê o menu que AdminSidebarMenu + módulos já populram via nwidart
        return $this->legacy->build();
    }
}
