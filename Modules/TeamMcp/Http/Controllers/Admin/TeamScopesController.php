<?php

namespace Modules\TeamMcp\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ADS\Services\UserScopeService;

/**
 * UI para Wagner gerenciar quem pode tocar quais módulos.
 *
 * Caso Maiara:
 *   Wagner abre tela, vê lista de devs do business, clica "Maiara",
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

        // Wave 11 D6.a — Inertia::defer pra props caras: users (UserScopeService
        // faz JOIN users × mcp_user_module_access + agregação per-módulo) e modules
        // (scan filesystem Modules/ + 30+ stat() calls). Closures resolvidas após
        // first paint — frontend skeleton até chegarem.
        return Inertia::render('ads/Admin/TeamScopes', [
            'users'   => Inertia::defer(fn () => $service->listUsersWithAccess($businessId)),
            'modules' => Inertia::defer(fn () => $this->buildModulesPayload()),
        ]);
    }

    /**
     * Builder módulos disponíveis — scan filesystem (Wave 11 D6.a defer).
     *
     * @return array<int, string>
     */
    protected function buildModulesPayload(): array
    {
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

        return $modules;
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
