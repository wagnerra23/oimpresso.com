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

            // UMA lei só (decisão D2, 2026-08-19). Antes eram duas para a mesma
            // capacidade: aqui `session('is_admin')` OU o papel `Admin#<biz>`; no item de
            // menu (AdminSidebarMenu:809) e no legado (Install/ModulesController, 4 usos)
            // a permissão `manage_modules`. Dava pra ver o item no menu e tomar 403 na tela.
            //
            // `Admin#<biz>` sai de propósito: é admin DE UM NEGÓCIO, e esta tela desliga
            // módulo do APP INTEIRO, para todos os tenants (furo medido — um user só com
            // Admin#1 entrava). O Gate::before (AuthServiceProvider) já trata
            // `manage_modules` como ability de superadmin: o atalho por papel do `else`
            // NÃO se aplica a ela, só a allowlist `ADMINISTRATOR_USERNAMES` (presente em
            // produção, verificado) ou concessão Spatie explícita.
            abort_unless(
                $request->user()->can('manage_modules'),
                403,
                'Acesso restrito a administradores.'
            );

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

            // O estado do módulo volta ao que era (Service), mas migrations que já rodaram
            // antes da exceção permanecem aplicadas — o operador precisa saber disso.
            return back()->with('status', ['error' =>
                "Falha ao instalar {$name}: " . $result['output']
                . ' — o módulo voltou ao estado anterior, mas migrations já aplicadas NÃO foram'
                . ' revertidas; corrija a causa e rode novamente.']);
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
