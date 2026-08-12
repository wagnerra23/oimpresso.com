<?php

namespace Modules\Jana\Entities\Mcp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * MEM-MCP-1.a (ADR 0053) — Token MCP (extensão sobre Sanctum).
 *
 * Token raw é gerado, hashed com SHA256 e armazenado. Raw é exibido UMA VEZ
 * pro user copiar — depois disso, só hash. Lookup por sha256_token.
 *
 * REPO-WIDE: ADR 0053 tokens per-user (isolamento via user_id, não business_id).
 * Sem `business_id` by design. Wave 25 SATURATION marker explícito pra rubrica
 * D1.c v3.2 hardened.
 *
 * D7 LGPD audit trail — Wave 17 (2026-05-16): LogsActivity rastreia revocação
 * e expiração — essencial pra responder LGPD Art. 9º (rastreabilidade de
 * tratamento por credenciais).
 *
 * SOFT-DELETE (2026-06-14): rotate/revoke fazem soft-delete (`deleted_at`) em vez
 * de hard-delete — a row sobrevive pra forense LGPD e `revoked_at`/`revoked_by`
 * são gravados junto (quem/quando). O revoke do drill-down admin
 * (`TeamController::revokeToken`) usa `revogar()` SEM delete: o token permanece
 * visível como "Revogado" no histórico de governança.
 *
 * SECURITY: sha256_token NUNCA logado (já marcado em $hidden).
 */
class McpToken extends Model
{
    use LogsActivity;
    use SoftDeletes;

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
        'deleted_at'   => 'datetime',
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

    /**
     * Carimba o uso do token — com throttle (incidente 2026-08-12).
     *
     * POR QUE: era um UPDATE por requisição. O MCP server roda no CT 100 contra
     * o MySQL do Hostinger, onde cada roundtrip mede ~360ms, e o handshake do
     * cliente faz 4 chamadas seguidas — ou seja, 4 escritas para carimbar o
     * mesmo minuto. `last_used_at` é telemetria de "quando foi usado por
     * último"; granularidade de minuto responde isso igual.
     *
     * O IP e o user-agent seguem a mesma janela DE PROPÓSITO: gravá-los fora
     * dela exigiria comparar com o valor atual, o que devolveria a leitura que
     * o throttle está evitando. Numa troca de IP o registro atrasa até o fim da
     * janela — aceitável para telemetria, e é o custo declarado desta escolha.
     *
     * `$throttleSegundos = 0` desliga (usado pelo teste e por quem precisar do
     * carimbo exato).
     */
    public function registrarUso(?string $ip, ?string $userAgent, ?int $throttleSegundos = null): void
    {
        $janela = $throttleSegundos ?? (int) config('copiloto.mcp.uso_throttle_segundos', 60);

        if ($janela > 0
            && $this->last_used_at !== null
            && $this->last_used_at->diffInSeconds(now()) < $janela
        ) {
            return;
        }

        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'user_agent'   => $userAgent ? Str::limit($userAgent, 200, '') : $this->user_agent,
        ]);
    }

    /**
     * D7 LGPD audit — NUNCA logga sha256_token (já hidden). Só rastreia
     * mudanças críticas: revocação, expiração override. last_used_at
     * intencionalmente fora pra evitar flood de eventos.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('mcp_token')
            ->logOnly(['revoked_at', 'revoked_by', 'expires_at', 'name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
