<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * UserLockoutService — tranca / destranca usuário com snapshot.
 *
 * lock():
 *   1. snapshot do estado atual (roles, permissions, mcp_tokens, mcp_user_scopes)
 *   2. insert em user_lockouts
 *   3. revoga mcp_tokens (revoked_at = now())
 *   4. mata sessions (driver=database) — DB::table('sessions')->where('user_id')...
 *   5. seta users.status = 'inactive' (UltimatePOS bloqueia login por aqui)
 *   6. NÃO remove roles Spatie (preserva pra restore)
 *
 * unlock():
 *   1. lê último lockout do user
 *   2. seta users.status = 'active'
 *   3. NÃO restaura tokens MCP automático — segurança (Wagner gera novo manual)
 *   4. marca lockout.unlocked_at = now()
 *
 * Auditoria: log via Laravel Log + insert em mcp_audit_log se a tabela existir.
 *
 * @see database/migrations/2026_05_03_100001_create_user_lockouts_table.php
 */
class UserLockoutService
{
    /**
     * Tranca o user. Retorna o id do lockout criado.
     *
     * @throws \RuntimeException quando user inexistente ou já trancado.
     */
    public function lock(int $userId, string $reason, int $byUserId): int
    {
        $user = User::findOrFail($userId);

        if ($user->status === 'inactive' && $this->latestActiveLockoutId($userId)) {
            throw new \RuntimeException('Usuário já está trancado.');
        }

        $snapshot = $this->buildSnapshot($userId);

        $lockoutId = null;

        DB::transaction(function () use ($userId, $reason, $byUserId, $snapshot, $user, &$lockoutId) {
            $lockoutId = DB::table('user_lockouts')->insertGetId([
                'user_id'     => $userId,
                'business_id' => $user->business_id,
                'locked_at'   => now(),
                'locked_by'   => $byUserId,
                'reason'      => mb_substr($reason, 0, 500),
                'snapshot'    => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Revoga tokens MCP
            if (Schema::hasTable('mcp_tokens')) {
                DB::table('mcp_tokens')
                    ->where('user_id', $userId)
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => now(),
                        'revoked_by' => $byUserId,
                        'updated_at' => now(),
                    ]);
            }

            // Mata sessions (apenas se driver = database)
            if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $userId)
                    ->delete();
            }

            // Bloqueia login (UltimatePOS verifica status=active)
            $user->status = 'inactive';
            $user->save();
        });

        Log::warning('user_lockout.lock', [
            'user_id'   => $userId,
            'locked_by' => $byUserId,
            'reason'    => $reason,
            'lockout'   => $lockoutId,
        ]);

        $this->auditMcp('user_lockout.lock', $userId, $byUserId, [
            'lockout_id' => $lockoutId,
            'reason'     => $reason,
        ]);

        return (int) $lockoutId;
    }

    /**
     * Destranca o user. Marca o último lockout ativo como unlocked.
     *
     * @throws \RuntimeException quando não há lockout ativo.
     */
    public function unlock(int $userId, int $byUserId, ?string $note = null): void
    {
        $user = User::findOrFail($userId);

        $lockoutId = $this->latestActiveLockoutId($userId);
        if (! $lockoutId) {
            throw new \RuntimeException('Usuário não está trancado.');
        }

        DB::transaction(function () use ($userId, $byUserId, $lockoutId, $note, $user) {
            DB::table('user_lockouts')
                ->where('id', $lockoutId)
                ->update([
                    'unlocked_at' => now(),
                    'unlocked_by' => $byUserId,
                    'unlock_note' => $note ? mb_substr($note, 0, 500) : null,
                    'updated_at'  => now(),
                ]);

            // Reativa login. Tokens MCP NÃO são restaurados (segurança).
            $user->status = 'active';
            $user->save();
        });

        Log::info('user_lockout.unlock', [
            'user_id'     => $userId,
            'unlocked_by' => $byUserId,
            'lockout'     => $lockoutId,
        ]);

        $this->auditMcp('user_lockout.unlock', $userId, $byUserId, [
            'lockout_id' => $lockoutId,
            'note'       => $note,
        ]);
    }

    /**
     * Histórico de lockouts do user, mais recente primeiro.
     */
    public function history(int $userId): array
    {
        return DB::table('user_lockouts')
            ->where('user_id', $userId)
            ->orderByDesc('locked_at')
            ->get()
            ->map(function ($r) {
                return [
                    'id'          => $r->id,
                    'locked_at'   => $r->locked_at,
                    'locked_by'   => $r->locked_by,
                    'reason'      => $r->reason,
                    'unlocked_at' => $r->unlocked_at,
                    'unlocked_by' => $r->unlocked_by,
                    'unlock_note' => $r->unlock_note,
                    'is_active'   => is_null($r->unlocked_at),
                ];
            })
            ->all();
    }

    /**
     * @return int|null id do lockout ativo (sem unlocked_at) mais recente
     */
    private function latestActiveLockoutId(int $userId): ?int
    {
        $row = DB::table('user_lockouts')
            ->where('user_id', $userId)
            ->whereNull('unlocked_at')
            ->orderByDesc('locked_at')
            ->first(['id']);

        return $row ? (int) $row->id : null;
    }

    /**
     * Constrói snapshot do estado atual do user (roles, permissions, tokens, scopes).
     */
    private function buildSnapshot(int $userId): array
    {
        $user = User::findOrFail($userId);

        $snapshot = [
            'taken_at'    => now()->toIso8601String(),
            'user_id'     => $userId,
            'status'      => $user->status,
            'roles'       => $user->getRoleNames()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            'mcp_tokens'  => [],
            'mcp_scopes'  => [],
        ];

        if (Schema::hasTable('mcp_tokens')) {
            $snapshot['mcp_tokens'] = DB::table('mcp_tokens')
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->get(['id', 'name', 'expires_at', 'last_used_at', 'last_used_ip'])
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        if (Schema::hasTable('mcp_user_scopes') && Schema::hasTable('mcp_scopes')) {
            $snapshot['mcp_scopes'] = DB::table('mcp_user_scopes as us')
                ->leftJoin('mcp_scopes as s', 's.id', '=', 'us.scope_id')
                ->where('us.user_id', $userId)
                ->whereNull('us.revoked_at')
                ->get(['us.id', 's.slug', 'us.business_id', 'us.granted_at'])
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        return $snapshot;
    }

    /**
     * Insert auxiliar em mcp_audit_log (se a tabela existir).
     */
    private function auditMcp(string $tool, int $userId, int $byUserId, array $payload): void
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return;
        }

        try {
            DB::table('mcp_audit_log')->insert([
                'request_id'       => (string) \Illuminate\Support\Str::uuid(),
                'user_id'          => $byUserId,
                'business_id'      => User::find($userId)?->business_id,
                'ts'               => now(),
                'endpoint'         => 'tool',
                'tool_or_resource' => $tool,
                'status'           => 'ok',
                'payload_summary'  => json_encode(array_merge(['target_user_id' => $userId], $payload)),
                'created_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('user_lockout.audit_failed', ['error' => $e->getMessage()]);
        }
    }
}
