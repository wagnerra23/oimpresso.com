<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\Services\PermissionRegistry;
use App\Services\UserLockoutService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

/**
 * Usuário 360° — vista única de tudo sobre um user.
 *
 * Resposta à dor do Wagner: "funcionários roubaram porque eu não conseguia
 * ver o todo. Não é legal pular de galho em galho pra saber o que tem de
 * permissão pra aquela função."
 *
 * Tela única consolidando: roles Spatie, permissions efetivas (via
 * PermissionRegistry), scopes ADS/MCP, tokens MCP, quotas Copiloto, sessions
 * ativas, auditoria recente, histórico de lockouts.
 *
 * Permissão: superadmin (middleware `superadmin`).
 *
 * @see app/Services/PermissionRegistry.php
 * @see app/Services/UserLockoutService.php
 * @see resources/js/Pages/superadmin/Usuario360/Show.tsx
 */
class Usuario360Controller extends Controller
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly UserLockoutService $lockout,
    ) {}

    /**
     * Lista de users com search — entry-point pra Wagner buscar por nome/email.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = User::query()
            ->select('id', 'username', 'email', 'first_name', 'last_name', 'business_id', 'status', 'user_type')
            ->orderBy('first_name');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
            });
        }

        $users = $query->limit(200)->get()->map(fn ($u) => [
            'id'          => $u->id,
            'username'    => $u->username,
            'email'       => $u->email,
            'nome'        => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->username,
            'business_id' => $u->business_id,
            'status'      => $u->status,
            'user_type'   => $u->user_type,
        ]);

        return Inertia::render('superadmin/Usuario360/Index', [
            'users'   => $users,
            'filters' => ['q' => $q],
        ]);
    }

    /**
     * Tela 360° de um único user.
     */
    public function show(int $id)
    {
        $user = User::with('roles')->findOrFail($id);
        $business = $user->business_id ? Business::find($user->business_id) : null;

        $isLocked = (string) $user->status === 'inactive'
            && $this->hasActiveLockout($user->id);

        return Inertia::render('superadmin/Usuario360/Show', [
            'user' => [
                'id'          => $user->id,
                'username'    => $user->username,
                'email'       => $user->email,
                'nome'        => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username,
                'first_name'  => $user->first_name,
                'last_name'   => $user->last_name,
                'business_id' => $user->business_id,
                'business_name' => $business?->name,
                'status'      => $user->status,
                'user_type'   => $user->user_type,
                'is_locked'   => $isLocked,
                'created_at'  => $user->created_at?->toIso8601String(),
            ],
            'roles'           => $user->getRoleNames()->all(),
            'permissions'     => $this->registry->forUser($user->id),
            'scopes_ads'      => $this->loadScopesAds($user->id),
            'tokens_mcp'      => $this->loadTokensMcp($user->id),
            'quotas_copiloto' => $this->loadQuotasCopiloto($user->id),
            'sessions_ativas' => $this->loadSessionsAtivas($user->id),
            'auditoria'       => $this->loadAuditoria($user->id),
            'lockouts'        => $this->lockout->history($user->id),
            'tabelas_ausentes' => $this->checkMissingTables(),
        ]);
    }

    /**
     * Trancar usuário com motivo (snapshot + revogação automática).
     */
    public function lock(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        $byUserId = (int) auth()->id();

        try {
            $lockoutId = $this->lockout->lock($id, (string) $request->input('reason'), $byUserId);
        } catch (\Throwable $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', "Usuário trancado (lockout #{$lockoutId}).");
    }

    /**
     * Destrancar — reativa status, NÃO restaura tokens MCP.
     */
    public function unlock(Request $request, int $id)
    {
        $byUserId = (int) auth()->id();
        $note = (string) $request->input('note', '');

        try {
            $this->lockout->unlock($id, $byUserId, $note ?: null);
        } catch (\Throwable $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with('status', 'Usuário destrancado.');
    }

    /**
     * Histórico de mudanças (lockouts) — endpoint JSON pra modal.
     */
    public function history(int $id)
    {
        return response()->json([
            'lockouts' => $this->lockout->history($id),
        ]);
    }

    // ── helpers ─────────────────────────────────────────────────────────

    private function hasActiveLockout(int $userId): bool
    {
        if (! Schema::hasTable('user_lockouts')) {
            return false;
        }
        return DB::table('user_lockouts')
            ->where('user_id', $userId)
            ->whereNull('unlocked_at')
            ->exists();
    }

    private function loadScopesAds(int $userId): array
    {
        // Tabela canônica desta branch: mcp_user_scopes (não mcp_user_module_access).
        if (! Schema::hasTable('mcp_user_scopes') || ! Schema::hasTable('mcp_scopes')) {
            return [];
        }

        return DB::table('mcp_user_scopes as us')
            ->leftJoin('mcp_scopes as s', 's.id', '=', 'us.scope_id')
            ->where('us.user_id', $userId)
            ->whereNull('us.revoked_at')
            ->orderBy('s.slug')
            ->get([
                's.slug',
                's.descricao',
                'us.business_id',
                'us.granted_at',
            ])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    private function loadTokensMcp(int $userId): array
    {
        if (! Schema::hasTable('mcp_tokens')) {
            return [];
        }

        return DB::table('mcp_tokens')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'name', 'sha256_token', 'last_used_at', 'last_used_ip', 'expires_at', 'revoked_at', 'created_at'])
            ->map(function ($r) {
                $masked = $r->sha256_token
                    ? 'mcp_***' . substr($r->sha256_token, -6)
                    : 'mcp_***';
                return [
                    'id'            => $r->id,
                    'name'          => $r->name,
                    'masked'        => $masked,
                    'last_used_at'  => $r->last_used_at,
                    'last_used_ip'  => $r->last_used_ip,
                    'expires_at'    => $r->expires_at,
                    'revoked_at'    => $r->revoked_at,
                    'created_at'    => $r->created_at,
                    'is_active'     => is_null($r->revoked_at) && (is_null($r->expires_at) || $r->expires_at > now()),
                ];
            })
            ->all();
    }

    private function loadQuotasCopiloto(int $userId): array
    {
        if (! Schema::hasTable('mcp_quotas')) {
            return [];
        }

        return DB::table('mcp_quotas')
            ->where('user_id', $userId)
            ->where('ativo', true)
            ->get(['period', 'kind', 'limit', 'current_usage', 'reset_at', 'block_on_exceed'])
            ->map(function ($r) {
                $limit = (float) $r->limit;
                $usage = (float) $r->current_usage;
                $pct = $limit > 0 ? min(100, round(($usage / $limit) * 100)) : 0;
                return [
                    'period'          => $r->period,
                    'kind'            => $r->kind,
                    'limit'           => $limit,
                    'current_usage'   => $usage,
                    'pct'             => $pct,
                    'reset_at'        => $r->reset_at,
                    'block_on_exceed' => (bool) $r->block_on_exceed,
                ];
            })
            ->all();
    }

    private function loadSessionsAtivas(int $userId): array
    {
        // Sessions só consultáveis se driver=database
        if (config('session.driver') !== 'database') {
            return [];
        }

        $table = config('session.table', 'sessions');
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($r) => [
                'id'            => substr((string) $r->id, 0, 12) . '…',
                'ip_address'    => $r->ip_address,
                'user_agent'    => mb_substr((string) $r->user_agent, 0, 120),
                'last_activity' => $r->last_activity ? date('Y-m-d H:i:s', (int) $r->last_activity) : null,
            ])
            ->all();
    }

    private function loadAuditoria(int $userId): array
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return [];
        }

        return DB::table('mcp_audit_log')
            ->where('user_id', $userId)
            ->orderByDesc('ts')
            ->limit(30)
            ->get(['ts', 'endpoint', 'tool_or_resource', 'status', 'error_code', 'duration_ms', 'ip'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Lista de tabelas que esperamos encontrar — devolvido pra UI mostrar
     * "(módulo X não instalado nesta branch)" graceful.
     */
    private function checkMissingTables(): array
    {
        $expected = [
            'mcp_user_scopes',
            'mcp_scopes',
            'mcp_tokens',
            'mcp_quotas',
            'mcp_audit_log',
            'user_lockouts',
            config('session.table', 'sessions'),
        ];

        $missing = [];
        foreach ($expected as $t) {
            if (! Schema::hasTable($t)) {
                $missing[] = $t;
            }
        }

        return $missing;
    }
}
