<?php

namespace Modules\Arquivos\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * DataController — Arquivos (DMS backbone).
 *
 * Hooks UltimatePOS pra Manage Modules (superadmin_package, user_permissions,
 * modifyAdminMenu).
 *
 * Arquivos é módulo backbone — outros módulos consumem via trait HasArquivos.
 * UI admin própria virá em Sprint 2 (US-ARQ-013 Pages/Arquivos integrada
 * no Admin Center).
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'arquivos_module',
                'label'   => 'Módulo Arquivos (DMS backbone — ADR 0123)',
                'default' => true,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'arquivos.access',
                'label'   => 'Arquivos: gerenciar anexos via Admin Center (Sprint 2)',
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu(): void
    {
        // No-op — Arquivos é backbone consumido via trait, não tem tela própria.
        // UI admin entra via Modules/Admin/Pages/Arquivos em Sprint 2.
    }
}
