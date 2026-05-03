<?php

namespace Modules\ADS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\UserScopeService;

/**
 * UI para Wagner gerenciar quem pode tocar quais módulos.
 *
 * Caso Maíra:
 *   Wagner abre tela, vê lista de devs do business, clica "Maíra",
 *   marca switches: ✓ Compras (read+write+exec) ✗ NFSe (só read) etc.
 */
class TeamScopesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, UserScopeService $service): Response
    {
        $businessId = (int) $request->session()->get('user.business_id', 1);

        $users = $service->listUsersWithAccess($businessId);

        // Lista de módulos disponíveis (escaneia diretório Modules/)
        $modules = [];
        $modulesDir = base_path('Modules');
        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                if (is_dir($modulesDir . '/' . $entry) && is_file($modulesDir . '/' . $entry . '/module.json')) {
                    $modules[] = $entry;
                }
            }
        }
        sort($modules);

        return Inertia::render('ads/Admin/TeamScopes', [
            'users'   => $users,
            'modules' => $modules,
        ]);
    }

    public function grant(Request $request, UserScopeService $service): RedirectResponse
    {
        $data = $request->validate([
            'user_id'           => 'required|integer|exists:users,id',
            'module'            => 'required|string|max:50',
            'can_read'          => 'sometimes|boolean',
            'can_write'         => 'sometimes|boolean',
            'can_execute_tools' => 'sometimes|boolean',
            'can_commit'        => 'sometimes|boolean',
            'reason'            => 'nullable|string|max:500',
            'expires_at'        => 'nullable|date',
        ]);

        $service->grant(
            userId:          (int) $data['user_id'],
            module:          $data['module'],
            canRead:         (bool) ($data['can_read'] ?? true),
            canWrite:        (bool) ($data['can_write'] ?? false),
            canExecuteTools: (bool) ($data['can_execute_tools'] ?? false),
            canCommit:       (bool) ($data['can_commit'] ?? false),
            grantedBy:       'wagner',
            reason:          $data['reason'] ?? null,
            expiresAt:       $data['expires_at'] ?? null,
        );

        return back()->with('status', "Acesso ao módulo {$data['module']} concedido para user #{$data['user_id']}.");
    }

    public function revoke(Request $request, UserScopeService $service): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
            'module'  => 'required|string',
        ]);

        $service->revoke((int) $data['user_id'], $data['module']);

        return back()->with('status', "Acesso ao módulo {$data['module']} revogado.");
    }
}
