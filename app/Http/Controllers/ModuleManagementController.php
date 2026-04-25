<?php

namespace App\Http\Controllers;

use App\Services\ModuleManagerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gerenciador de módulos — UI React em /modulos.
 *
 * Substitui o /manage-modules antigo do UltimatePOS (que depende de assets
 * AdminLTE quebrados). Só admins podem acessar.
 */
class ModuleManagementController extends Controller
{
    public function __construct(protected ModuleManagerService $manager)
    {
        $this->middleware(function ($request, $next) {
            if (!$request->user()) {
                abort(401);
            }
            // Admin via session (padrão UltimatePOS) OU Spatie Role
            $isAdmin = (bool) $request->session()->get('is_admin', false);
            if (!$isAdmin && method_exists($request->user(), 'hasRole')) {
                $businessId = $request->session()->get('business.id');
                $isAdmin = $request->user()->hasRole('Admin#' . $businessId);
            }
            abort_unless($isAdmin, 403, 'Acesso restrito a administradores.');
            return $next($request);
        });
    }

    public function index(): Response
    {
        return Inertia::render('Modules/Index', [
            'modules' => $this->manager->list(),
        ]);
    }

    public function toggle(Request $request, string $name)
    {
        $request->validate(['active' => ['required', 'boolean']]);

        try {
            $this->manager->setActive($name, (bool) $request->input('active'));
            return back()->with('status', ['success' => "Módulo {$name} " . ($request->input('active') ? 'ativado' : 'desativado') . "."]);
        } catch (\Throwable $e) {
            return back()->with('status', ['error' => "Falha: {$e->getMessage()}"]);
        }
    }

    public function install(string $name)
    {
        try {
            $businessId = (int) session('user.business_id');
            $result = $this->manager->install($name, $businessId > 0 ? $businessId : null);

            if ($result['success']) {
                $msg = "Módulo {$name} instalado (migrations OK).";
                if (! empty($result['install_output'])) {
                    // Comando <modulo>:install rodou: permissões + package + seed
                    $msg .= ' Setup completo: permissões + plano de contas pré-populados.';
                }
                return back()->with('status', ['success' => $msg]);
            }

            return back()->with('status', ['error' => "Falha ao instalar {$name}: " . $result['output']]);
        } catch (\Throwable $e) {
            return back()->with('status', ['error' => "Falha: {$e->getMessage()}"]);
        }
    }

    public function uninstall(string $name)
    {
        try {
            $this->manager->uninstall($name);
            return back()->with('status', ['success' => "Módulo {$name} desativado (tabelas preservadas)."]);
        } catch (\Throwable $e) {
            return back()->with('status', ['error' => "Falha: {$e->getMessage()}"]);
        }
    }
}
