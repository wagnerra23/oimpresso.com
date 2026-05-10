<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * DataController — Admin (Centro de Operações).
 *
 * Convenção UltimatePOS: middleware `AdminSidebarMenu` chama hooks deste
 * controller em cada request (e na tela de Roles).
 *
 * Admin é Wagner-only (gate `is-wagner` middleware). Não aparece em
 * Manage Modules pra outros usuários (default false em superadmin_package).
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'admin_module',
                'label'   => 'Admin Center (Wagner-only @ CT 100, ADR 0122)',
                'default' => false, // Wagner ativa manualmente; equipe nunca vê
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'admin.access',
                'label'   => 'Admin Center: acessar painel /admin (Wagner-only)',
                'default' => false,
            ],
        ];
    }

    /**
     * Não adiciona item ao sidebar do app principal (Hostinger). Admin vive
     * em subdomínio separado `admin.oimpresso.com` (CT 100/Tailscale).
     */
    public function modifyAdminMenu(): void
    {
        // No-op intencional. Admin Center NÃO está no sidebar Hostinger
        // (defense in depth: equipe não vê nem o link).
    }
}
