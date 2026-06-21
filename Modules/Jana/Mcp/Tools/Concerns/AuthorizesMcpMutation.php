<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools\Concerns;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * SDD Leva 2 · A4 (RBAC server-side mutation gate) — fecha o furo deixado pelo
 * comentário mentiroso do McpAuthMiddleware (L51-54), que afirmava "cada Tool
 * checa o scope via $user->can". Na prática só o gate grosso `jana.mcp.use`
 * existia; tools que MUTAM (criam/atualizam/fecham/esquecem) não checavam scope
 * fino nenhum. Este trait dá o gate de escopo por-tool, chamado como PRIMEIRO
 * statement do handle().
 *
 * Resolução de user idêntica ao resto do codebase: $request->user() (que o
 * McpAuthMiddleware povoa via auth userResolver, L88) + guarda method_exists
 * pra users não-Spatie/de teste (espelha McpAuthMiddleware.php:55 e
 * CcSearchTool.php:86). Idioma de negação = Response::error() (laravel/mcp
 * v0.7.0 não tem ->asError(); error() já marca isError:true — mesmo idioma
 * usado em CcSearchTool, TasksClaimTool e LgpdEsquecerTitularTool).
 *
 * Uso:
 *   if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.tasks.write')) {
 *       return $deny;
 *   }
 */
trait AuthorizesMcpMutation
{
    /**
     * Gate de escopo pra tools que mutam estado.
     *
     * @param  Request  $request  Request MCP (protocolo) — user resolve via auth.
     * @param  string  $scope  Slug Spatie do scope exigido (ex: jana.mcp.tasks.write).
     * @return Response|null  Response de erro quando NEGADO; null quando autorizado.
     */
    protected function authorizeMcpMutation(Request $request, string $scope): ?Response
    {
        $user = $request->user();

        // Sem user autenticado → nega. O McpAuthMiddleware já barraria antes,
        // mas a tool não pode confiar nisso (defesa em profundidade).
        if ($user === null) {
            return Response::error("⛔ Sem permissão: requer scope {$scope} (autenticação ausente).");
        }

        // Guarda method_exists pra users sem Spatie HasRoles (test stubs,
        // contas legadas) — mesma defesa do middleware/CcSearchTool. Se o user
        // não sabe responder can(), tratamos como SEM o scope (fail-closed).
        if (! method_exists($user, 'can') || ! $user->can($scope)) {
            return Response::error("⛔ Sem permissão: requer scope {$scope}.");
        }

        return null;
    }
}
