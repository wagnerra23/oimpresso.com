<?php

namespace Modules\ConsultaOs\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * Hooks UltimatePOS — modulo publico, sem entrada na sidebar admin.
 * Mantemos os 3 metodos como stub vazio para compatibilidade com o
 * carregamento por reflexao em ModuleUtil/AdminSidebarMenu.
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [];
    }

    public function user_permissions(): array
    {
        return [];
    }

    public function modifyAdminMenu(): void
    {
        // sem item de menu — feature publica acessada via /consulta-os.
    }
}
