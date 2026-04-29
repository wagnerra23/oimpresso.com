<?php

namespace Modules\Copiloto\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * MEM-MCP-1.a (ADR 0053) — Token MCP (extensão sobre Sanctum).
 *
 * Token raw é gerado, hashed com SHA256 e armazenado. Raw é exibido UMA VEZ
 * pro user copiar — depois disso, só hash. Lookup por sha256_token.
 */
class McpToken extends Model
{
    protected $table = 'mcp_tokens';

    protected $fillable = [
        'user_id', 'name', 'sha256_token', 'scopes_cache',
        'user_agent', 'last_used_ip', 'last_used_at',
        'expires_at', 'revoked_at', 'revoked_by',
    ];

    protected $casts = [
        'scopes_cache' => 'array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    protected $hidden = ['sha256_token'];

    /**
     * Gera token raw + cria registro com hash. Retorna [Model, raw_token].
     * Raw token tem formato: `mcp_<32-bytes-hex>` (compatível com Bearer header).
     */
    public static function gerar(int $userId, string $name, ?\DateTimeInterface $expiresAt = null): array
    {
        $raw = 'mcp_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $token = static::create([
            'user_id'       => $userId,
            'name'          => $name,
            'sha256_token'  => $hash,
            'expires_at'    => $expiresAt,
        ]);

        return [$token, $raw];
    }

    /**
     * Encontra token pelo raw enviado no header Authorization.
     */
    public static function encontrarPorRaw(string $raw): ?self
    {
        $hash = hash('sha256', $raw);

        return static::where('sha256_token', $hash)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function isAtivo(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public function revogar(int $byUserId): void
    {
        $this->update([
            'revoked_at' => now(),
            'revoked_by' => $byUserId,
        ]);
    }

    public function registrarUso(?string $ip, ?string $userAgent): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'user_agent'   => $userAgent ? Str::limit($userAgent, 200, '') : $this->user_agent,
        ]);
    }
}
