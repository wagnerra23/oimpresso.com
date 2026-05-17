<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
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
     * Revoga (soft-delete + expires_at=now) um token existente.
     *
     * @return bool true se revoke aplicado; false se token não existia.
     */
    public function revoke(int $tokenId): bool
    {
        return OtelHelper::spanBiz('teammcp.token.revoke', function () use ($tokenId) {
            $token = McpToken::find($tokenId);
            if ($token === null) {
                return false;
            }
            $token->update(['expires_at' => now()]);
            $token->delete();

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
}
