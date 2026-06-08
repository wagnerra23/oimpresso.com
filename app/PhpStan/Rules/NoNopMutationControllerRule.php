<?php

declare(strict_types=1);

namespace App\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan custom rule — NoNopMutationControllerRule (ADR 0208 custom rule #3, US-INFRA-019).
 *
 * Codifica T-AP-13 do `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`:
 *
 *   "Mutações NO-OP — agente externo gera Controller::aceitar() / desfazer() /
 *    confirmar() / etc retornando APENAS `return back()` sem nenhuma mutação,
 *    validação, ou side-effect. Endpoint vira API de mentirinha — botão clica,
 *    HTTP 302 volta, dado NÃO foi persistido. Bug catastrófico em conciliação
 *    bancária/aceite-de-ordem/aprovação fiscal."
 *
 * Detecta methods públicos em Module Controllers que:
 *   - Têm EXATAMENTE 1 statement no body
 *   - Esse statement é `return back();` ou `return redirect()->back();` ou
 *     `return redirect()->route(...);` sem qualquer call de Service/Model antes
 *
 * Limitações escopo Onda 2.3:
 *   - 1 statement só (não detecta no-op com `// TODO` comentado ou logs vazios)
 *   - Não verifica `@param Request` (heurística pragmática — todo controller action
 *     tem Request em UPOS)
 *   - Aceita `index`/`show`/`create`/`edit` que retornam view direto (não-mutação)
 *
 * False-positive aceitável durante adoção — ratchet baseline absorve violações
 * pre-existentes. Novo controller no-op = CI bloqueia.
 *
 * @implements Rule<ClassMethod>
 */
class NoNopMutationControllerRule implements Rule
{
    /** @var list<string> Method names READ-only — escape valve (retornam view direto OK). */
    private const READ_ONLY_METHODS = [
        'index',
        'show',
        'create',
        'edit',
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
        // Só dispara em Controllers de Modules/* OU app/Http/Controllers/*
        $file = $scope->getFile();
        $normalized = str_replace('\\', '/', $file);

        $isController =
            (str_contains($normalized, '/Modules/') || str_contains($normalized, '/app/Http/Controllers/'))
            && str_ends_with($normalized, 'Controller.php');

        if (! $isController) {
            return [];
        }

        // Public methods only — privates/helpers OK serem simples
        if (! $node->isPublic()) {
            return [];
        }

        // Skip __construct + read-only methods canon CRUD
        $methodName = $node->name->name;
        if ($methodName === '__construct' || in_array($methodName, self::READ_ONLY_METHODS, true)) {
            return [];
        }

        // Skip métodos abstract/sem body
        if ($node->stmts === null || count($node->stmts) !== 1) {
            return [];
        }

        // Body tem EXATAMENTE 1 statement — é `return ...`?
        $only = $node->stmts[0];
        if (! $only instanceof Return_ || $only->expr === null) {
            return [];
        }

        // Detecta `back()` ou `redirect()->back()` ou `redirect()->route(...)`
        if (! $this->isNoOpReturn($only->expr)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Método `%s::%s()` é mutação NO-OP — retorna apenas `back()`/`redirect()` sem qualquer mutação ou validação. '
                . 'Endpoint vira API de mentirinha — botão clica, HTTP 302 volta, NADA persiste. '
                . 'Adicione validação/serviço/Eloquent call OU marque explicitamente com `// @phpstan-ignore-next-line oimpresso.nopMutation` justificando. '
                . 'Ver T-AP-13 no LICOES_F3.',
                $scope->getClassReflection()?->getName() ?? '<unknown>',
                $methodName,
            ))
                ->identifier('oimpresso.nopMutation')
                ->line($node->getLine())
                ->build(),
        ];
    }

    /**
     * Detecta:
     *   - `back()` (helper function)
     *   - `redirect()->back()` (chain)
     *   - `redirect()->route(...)` (chain)
     *   - `redirect('/path')` (helper com arg simples)
     */
    private function isNoOpReturn(Node $expr): bool
    {
        // Caso 1: `back()` direto
        if ($expr instanceof FuncCall
            && $expr->name instanceof Name
            && $expr->name->toString() === 'back'
        ) {
            return true;
        }

        // Caso 2: `redirect()->...()` (any chain)
        if ($expr instanceof MethodCall
            && $expr->var instanceof FuncCall
            && $expr->var->name instanceof Name
            && $expr->var->name->toString() === 'redirect'
        ) {
            return true;
        }

        // Caso 3: `redirect('/path')` direto
        if ($expr instanceof FuncCall
            && $expr->name instanceof Name
            && $expr->name->toString() === 'redirect'
        ) {
            return true;
        }

        return false;
    }
}
