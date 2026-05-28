<?php

declare(strict_types=1);

namespace App\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan custom rule — NoMissingTenantScopeRule (ADR 0208 custom rule #1, US-INFRA-017).
 *
 * Codifica T-AP-2 + T-AP-8 do `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`
 * (multi-tenant scope ausente — Tier 0 IRREVOGÁVEL ADR 0093).
 *
 * Detecta em métodos de Controllers em `Modules/`:
 *   - Eloquent static call (`Model::query()`, `Model::where()`, `Model::find()`,
 *     `Model::all()`, `Model::create()`, `Model::firstOrCreate()`, etc)
 *   - SEM referência a `business_id` em qualquer parte do método
 *     (string literal OU variável `$business_id` OU método/property bem-nomeado)
 *
 * Heurística:
 *   - Se Model usa Global Scope `BusinessScope`, business_id é AUTOMÁTICO mas
 *     método ainda deve mencionar `business_id` em algum lugar (`session('user.business_id')`,
 *     `$request->business_id`, `$this->businessId`, etc).
 *   - Se método é puramente CRUD sem tenant context (ex: superadmin endpoint),
 *     método deve usar `withoutGlobalScope(BusinessScope::class)` — string aparece.
 *
 * False-positive aceitável durante adoção — ratchet baseline absorve violações
 * pre-existentes. Novo controller sem business_id mention = CI bloqueia.
 *
 * Limitações escopo Onda 2.1:
 *   - Não analisa traits / models pra confirmar BusinessScope global
 *   - Não detecta `business_id` em métodos auxiliares chamados
 *   - Heurística string-match `business_id` (não AST profundo)
 *
 * @implements Rule<ClassMethod>
 */
class NoMissingTenantScopeRule implements Rule
{
    /** @var list<string> Eloquent static methods que indicam query de tenant data. */
    private const ELOQUENT_METHODS = [
        'query',
        'where',
        'whereIn',
        'whereNotIn',
        'whereNull',
        'whereNotNull',
        'whereHas',
        'find',
        'findOrFail',
        'findMany',
        'first',
        'firstOrFail',
        'firstOrCreate',
        'firstOrNew',
        'all',
        'create',
        'updateOrCreate',
    ];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Só dispara em Controllers de Modules/*
        $file = $scope->getFile();
        // Normaliza separadores Windows/Linux
        $normalized = str_replace('\\', '/', $file);

        $isControllerInModules =
            str_contains($normalized, '/Modules/')
            && str_contains($normalized, '/Http/Controllers/')
            && str_ends_with($normalized, 'Controller.php');

        if (! $isControllerInModules) {
            return [];
        }

        // Skip métodos de boot/lifecycle/private helpers (heurística: public actions)
        if (! $node->isPublic() || $node->name->name === '__construct') {
            return [];
        }

        // Body do método precisa ter ≥1 statement
        if ($node->stmts === null || $node->stmts === []) {
            return [];
        }

        // Procura Eloquent static calls no body
        $finder = new NodeFinder();
        $eloquentCalls = $finder->find($node->stmts, function (Node $n): bool {
            if (! $n instanceof StaticCall) {
                return false;
            }
            if (! $n->name instanceof Identifier) {
                return false;
            }

            return in_array($n->name->toString(), self::ELOQUENT_METHODS, true);
        });

        if ($eloquentCalls === []) {
            return [];
        }

        // Verifica se método inteiro menciona `business_id` em qualquer forma
        // Serializa o body como string e busca substring (heurística pragmática)
        $methodSource = $this->serializeMethodBody($node);

        // Patterns aceitos como "tem business_id em algum lugar":
        $hasBusinessId =
            str_contains($methodSource, 'business_id')
            || str_contains($methodSource, 'businessId')
            || str_contains($methodSource, 'BusinessScope');

        if ($hasBusinessId) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Método `%s::%s()` faz Eloquent query (%s) sem mencionar `business_id`/`businessId`/`BusinessScope` em nenhum lugar. '
                . 'Tier 0 IRREVOGÁVEL (ADR 0093): toda query em Module Controller deve filtrar por tenant. '
                . 'Use `->where(\'business_id\', session(\'user.business_id\'))` OR confirme `BusinessScope` global no Model + comentário explícito. '
                . 'Ver T-AP-2 + T-AP-8 no LICOES_F3.',
                $scope->getClassReflection()?->getName() ?? '<unknown>',
                $node->name->name,
                count($eloquentCalls),
            ))
                ->identifier('oimpresso.missingTenantScope')
                ->line($node->getLine())
                ->build(),
        ];
    }

    /**
     * Serializa body do método como string (pra grep simples).
     * Usa NodeFinder pra concatenar names + strings + values numéricos.
     */
    private function serializeMethodBody(ClassMethod $method): string
    {
        if ($method->stmts === null) {
            return '';
        }

        $parts = [];
        $finder = new NodeFinder();

        // String literals
        foreach ($finder->findInstanceOf($method->stmts, Node\Scalar\String_::class) as $str) {
            $parts[] = $str->value;
        }

        // Variable names
        foreach ($finder->findInstanceOf($method->stmts, Node\Expr\Variable::class) as $var) {
            if (is_string($var->name)) {
                $parts[] = $var->name;
            }
        }

        // Method/property names (Identifier nodes)
        foreach ($finder->findInstanceOf($method->stmts, Identifier::class) as $id) {
            $parts[] = $id->name;
        }

        // Class names (Name nodes — handles `BusinessScope::class`)
        foreach ($finder->findInstanceOf($method->stmts, Node\Name::class) as $name) {
            $parts[] = $name->toString();
        }

        return implode(' ', $parts);
    }
}
