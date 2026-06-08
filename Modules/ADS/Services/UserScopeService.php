<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;

/**
 * Observabilidade D9.a (ADR 0155): permission check ms-range; Tracer via
 * `OtelHelper::span(` pode envolver `canWriteToPath()` se hot path.
 *
 * Resolve permissões granulares de (usuário × módulo).
 *
 * Caso Maiara:
 *   canWriteToPath(maira, 'Modules/Compras/Models/X.php') → true
 *   canWriteToPath(maira, 'Modules/NFSe/Services/Y.php')  → false
 *   canWriteToPath(maira, 'Modules/ADS/Tools/Z.php')      → false
 *
 * REGRA DO SERVIDOR > REGRA LOCAL:
 *   WriteFileTool consulta isso ANTES de escrever — Maiara clonando repo
 *   localmente pode editar files no editor dela, mas commit/push pra branch
 *   protegida só passa pelo ADS que enforça aqui.
 */
class UserScopeService
{
    private const MODULE_PATH_REGEX = '/^Modules\/([A-Za-z0-9]+)\//';

    /**
     * Pode escrever neste path?
     * Estratégia:
     *   1. Path fora de Modules/ → exige permissão global (Wagner only)
     *   2. Path em Modules/<X>/  → consulta mcp_user_module_access
     *   3. Sem registro → DENY por default (whitelist explícito)
     */
    public function canWriteToPath(int $userId, string $path): bool
    {
        $module = $this->extractModule($path);
        if ($module === null) {
            // Path não é Modules/ — só usuários com `superadmin` Spatie
            return $this->isSuperadmin($userId);
        }

        $access = $this->fetchAccess($userId, $module);
        if ($access === null) return false;

        if ($access->expires_at !== null && strtotime($access->expires_at) < time()) {
            return false; // expirou
        }

        return (bool) $access->can_write;
    }

    public function canExecuteTool(int $userId, string $toolName, array $input = []): bool
    {
        // Ferramentas que tocam path: extrai módulo e checa
        if (in_array($toolName, ['write_file', 'git_commit_wip'], true)) {
            $path = $input['path']
                ?? ($input['paths'][0] ?? null);
            if (! $path) return false;
            return $this->canWriteToPath($userId, $path);
        }

        if ($toolName === 'run_test') {
            $path = $input['path'] ?? '';
            $module = $this->extractModule($path);
            if ($module === null) return $this->isSuperadmin($userId);
            $access = $this->fetchAccess($userId, $module);
            return $access !== null && (bool) $access->can_execute_tools;
        }

        // Tools read-only (Boost, GitInspect, etc) — qualquer user com `ads.access`
        return true;
    }

    /**
     * Lista módulos que o usuário pode tocar (com nível de permissão).
     * @return array<array{module:string, can_read:bool, can_write:bool, can_execute_tools:bool, can_commit:bool, expires_at:?string}>
     */
    public function getAllowedModules(int $userId): array
    {
        return DB::table('mcp_user_module_access')
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderBy('module')
            ->get()
            ->map(fn ($r) => [
                'module'            => $r->module,
                'can_read'          => (bool) $r->can_read,
                'can_write'         => (bool) $r->can_write,
                'can_execute_tools' => (bool) $r->can_execute_tools,
                'can_commit'        => (bool) $r->can_commit,
                'expires_at'        => $r->expires_at,
            ])
            ->all();
    }

    public function grant(
        int $userId,
        string $module,
        bool $canRead = true,
        bool $canWrite = false,
        bool $canExecuteTools = false,
        bool $canCommit = false,
        string $grantedBy = 'wagner',
        ?string $reason = null,
        ?string $expiresAt = null,
    ): void {
        DB::table('mcp_user_module_access')->updateOrInsert(
            ['user_id' => $userId, 'module' => $module],
            [
                'can_read'           => $canRead,
                'can_write'          => $canWrite,
                'can_execute_tools'  => $canExecuteTools,
                'can_commit'         => $canCommit,
                'granted_by'         => $grantedBy,
                'reason'             => $reason,
                'expires_at'         => $expiresAt,
                'updated_at'         => now(),
                'created_at'         => now(),
            ],
        );
    }

    public function revoke(int $userId, string $module): void
    {
        DB::table('mcp_user_module_access')
            ->where('user_id', $userId)
            ->where('module', $module)
            ->delete();
    }

    public function listUsersWithAccess(int $businessId): array
    {
        return DB::table('users')
            ->join('user_businesses', 'users.id', '=', 'user_businesses.user_id')
            ->where('user_businesses.business_id', $businessId)
            ->where('users.deleted_at', null)
            ->select('users.id', 'users.username', 'users.first_name', 'users.surname', 'users.email')
            ->orderBy('users.first_name')
            ->get()
            ->map(function ($u) {
                $modules = $this->getAllowedModules((int) $u->id);
                return [
                    'id'          => (int) $u->id,
                    'name'        => trim(($u->first_name ?? '') . ' ' . ($u->surname ?? '')) ?: $u->username,
                    'email'       => $u->email,
                    'modules'     => $modules,
                    'modules_count' => count($modules),
                ];
            })
            ->all();
    }

    private function extractModule(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match(self::MODULE_PATH_REGEX, $path, $m)) {
            return $m[1];
        }
        return null;
    }

    private function fetchAccess(int $userId, string $module): ?object
    {
        return DB::table('mcp_user_module_access')
            ->where('user_id', $userId)
            ->where('module', $module)
            ->first();
    }

    private function isSuperadmin(int $userId): bool
    {
        // Spatie role 'superadmin' (UltimatePOS canon)
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', 'App\\User')
            ->where('roles.name', 'like', 'superadmin%')
            ->exists();
    }
}
