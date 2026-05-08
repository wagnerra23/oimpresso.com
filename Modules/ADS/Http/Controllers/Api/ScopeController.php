<?php

namespace Modules\ADS\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\ADS\Services\UserScopeService;

/**
 * Endpoint que Claude Code (de qualquer dev junior) consulta ANTES
 * de propor uma mudança. Resposta clara: "pode" ou "não pode + motivo".
 *
 * Usage exemplo (Claude da Maiara):
 *   GET /api/ads/scope/check?user_id=3&path=Modules/Compras/Models/Compra.php
 *   → { "can_write": true, "module": "Compras" }
 *
 *   GET /api/ads/scope/check?user_id=3&path=Modules/NFSe/Services/X.php
 *   → { "can_write": false, "module": "NFSe", "reason": "user_no_write_access" }
 */
class ScopeController extends Controller
{
    public function check(Request $request, UserScopeService $scope): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);
        $path = $request->query('path', '');
        $action = $request->query('action', 'write'); // write|execute_tool|commit

        if ($userId <= 0 || empty($path)) {
            return response()->json(['error' => 'user_id_and_path_required'], 422);
        }

        $module = null;
        if (preg_match('/^Modules\/([A-Za-z0-9]+)\//', str_replace('\\', '/', $path), $m)) {
            $module = $m[1];
        }

        $canWrite = $scope->canWriteToPath($userId, $path);
        $allowedModules = $scope->getAllowedModules($userId);

        return response()->json([
            'user_id'         => $userId,
            'path'            => $path,
            'module'          => $module,
            'action'          => $action,
            'allowed'         => $canWrite,
            'allowed_modules' => array_column($allowedModules, 'module'),
            'reason'          => $canWrite ? null : ($module === null
                ? 'path_outside_modules_requires_superadmin'
                : "user_has_no_write_access_to_module_{$module}"),
            'next_steps'      => $canWrite
                ? "Pode prosseguir."
                : "Wagner precisa autorizar via /ads/admin/team-scopes — adicionar can_write=true para {$module}.",
        ]);
    }

    public function listUserModules(Request $request, int $userId, UserScopeService $scope): JsonResponse
    {
        $modules = $scope->getAllowedModules($userId);

        return response()->json([
            'user_id'      => $userId,
            'modules'      => $modules,
            'total_count'  => count($modules),
        ]);
    }
}
