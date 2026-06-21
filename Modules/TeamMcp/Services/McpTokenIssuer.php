<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpToken;

/**
 * McpTokenIssuer — Wave 18 D4 SATURATION (2026-05-16).
 *
 * Extrai lógica de geração/revogação de tokens MCP antes embutida em
 * `TeamController::gerarToken()` e `TeamController::revogarToken()`.
 *
 * **Tier 0 Segredo IRREVOGÁVEL** ({@see ADR 0081}): token raw devolvido APENAS
 * uma vez no response, jamais logado nem persistido em raw. Hash sha256 gravado
 * via `McpToken::gerar()` helper canônico — Service apenas orquestra.
 *
 * OTel spans pra observabilidade lifecycle crítico (issuance + revocation):
 * span NÃO inclui token raw (defesa em profundidade Tier 0 segredo).
 *
 * @see Modules\Jana\Entities\Mcp\McpToken (helper gerar/hash)
 * @see memory/decisions/0081-identity-mesh-actor-trust-mcp.md
 */
class McpTokenIssuer
{
    /**
     * Gera novo token MCP pra um user. Retorna ['token' => McpToken, 'raw' => string].
     *
     * @return array{token: McpToken, raw: string}
     */
    public function issue(int $userId, ?string $note = null): array
    {
        return OtelHelper::spanBiz('teammcp.token.issue', function () use ($userId, $note) {
            $name = $note ?: 'Gerado por admin em ' . now()->toDateString();
            [$token, $raw] = McpToken::gerar($userId, $name);

            // Tier 0 SEGREDO: raw NUNCA loga, mesmo em info-level.
            return ['token' => $token, 'raw' => $raw];
        }, ['module' => 'TeamMcp', 'target_user_id' => $userId]);
    }

    /**
     * Revoga um token: grava `revoked_at`/`revoked_by` (audit LGPD completo) +
     * `expires_at=now` e soft-delete (SoftDeletes — a row sobrevive pra forense).
     *
     * @param  int       $tokenId   token a revogar
     * @param  int|null  $byUserId  actor que revogou (default: o próprio dono — self-revoke)
     * @return bool true se revoke aplicado; false se token não existia.
     */
    public function revoke(int $tokenId, ?int $byUserId = null): bool
    {
        return OtelHelper::spanBiz('teammcp.token.revoke', function () use ($tokenId, $byUserId) {
            $token = McpToken::find($tokenId);
            if ($token === null) {
                return false;
            }
            // Audit LGPD (ADR 0081): grava quem/quando ANTES do soft-delete.
            $token->update([
                'expires_at' => now(),
                'revoked_at' => now(),
                'revoked_by' => $byUserId ?? (int) $token->user_id,
            ]);
            $token->delete(); // soft-delete — preserva row pro audit trail

            return true;
        }, ['module' => 'TeamMcp', 'token_id' => $tokenId]);
    }

    /**
     * Conta tokens ativos (não expirados) de um user — Pest helper + Admin UI.
     */
    public function countActive(int $userId): int
    {
        return McpToken::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    /**
     * Wave 23 G3 FICHA — Rotaciona token MCP atomicamente.
     *
     * Guarda Tier 0 IRREVOGÁVEL ({@see ADR 0081}):
     *   1. Ownership check — token deve pertencer ao $userId informado.
     *      Caso contrário retorna null (sem efeito colateral).
     *   2. Atômico — emit new + revoke old na MESMA DB::transaction.
     *      Se issue falhar, old permanece ativo.
     *   3. Raw devolvido 1× no array — JAMAIS logado/persistido em raw.
     *
     * Wave 25 D4 — Service permanece thin (issue + revoke já existem); rotate é
     * apenas a composição segura desses dois primitivos.
     *
     * @return array{old_token_id: int, new_token: McpToken, raw: string}|null
     */
    public function rotate(int $userId, int $oldTokenId, ?string $note = null): ?array
    {
        // Pre-check ownership (fora da transaction pra fail-fast sem custo DB)
        $oldToken = McpToken::find($oldTokenId);
        if ($oldToken === null || (int) $oldToken->user_id !== $userId) {
            // Tier 0 segredo: NÃO loga detalhes (anti-enumeration)
            return null;
        }

        return OtelHelper::spanBiz('teammcp.token.rotate', function () use ($userId, $oldTokenId, $note) {
            return DB::transaction(function () use ($userId, $oldTokenId, $note) {
                // Re-fetch dentro da transaction (lock implícito)
                $old = McpToken::lockForUpdate()->find($oldTokenId);
                if ($old === null || (int) $old->user_id !== $userId) {
                    return null;
                }

                // Issue novo
                $defaultNote = 'Rotated em ' . now()->toDateString();
                [$new, $raw] = McpToken::gerar($userId, $note ?: $defaultNote);

                // Revoke old: audit LGPD (revoked_at/revoked_by) + expires_at=now
                // + soft-delete (a row sobrevive pra forense — ADR 0081).
                $old->update([
                    'expires_at' => now(),
                    'revoked_at' => now(),
                    'revoked_by' => $userId, // dono rotaciona o próprio token
                ]);
                $old->delete();

                return [
                    'old_token_id' => $oldTokenId,
                    'new_token'    => $new,
                    'raw'          => $raw, // Tier 0: jamais re-logado downstream
                ];
            });
        }, ['module' => 'TeamMcp', 'old_token_id' => $oldTokenId, 'target_user_id' => $userId]);
    }
}
