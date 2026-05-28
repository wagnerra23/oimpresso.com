<?php

declare(strict_types=1);

namespace App\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * PHPStan custom rule — NoSilentFallbackRule (ADR 0212 Camada 3, US-INFRA-020).
 *
 * Detecta o padrão R9 da sessão Larissa 2026-05-28:
 *
 *   if (empty($request->input('transaction_date'))) {
 *       $input['transaction_date'] = \Carbon::now();   // ← silent fallback
 *   }
 *
 * Quando `if (empty(...))` ou `if (! isset(...))` tem body com APENAS atribuição
 * (sem `Log::warning(...)` no mesmo bloco), reporta erro.
 *
 * Cobertura:
 *  - `if (empty($x)) { $y = <default>; }`               ← detecta
 *  - `if (! isset($x)) { $y = <default>; }`             ← detecta
 *  - `if (empty($x)) { Log::warning(...); $y = ...; }`  ← OK (log presente)
 *
 * Limitações conhecidas (escopo Onda 2):
 *  - Não detecta `$x = $y ?? <default>;` (null coalesce) — Onda 3 estende
 *  - Não detecta ternário `$x = empty($y) ? <default> : $y;` — Onda 3 estende
 *  - Não detecta `else` branches (foco no `if` direto)
 *
 * False-positive ok no início — ratchet baseline absorve violações pre-existentes
 * em `phpstan-baseline.neon`. Erro tier 5.
 *
 * @implements Rule<If_>
 */
class NoSilentFallbackRule implements Rule
{
    public function getNodeType(): string
    {
        return If_::class;
    }

    /**
     * @param If_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Só interessa `if (empty(...))` ou `if (! isset(...))`
        if (! $this->isEmptyOrNotIssetCondition($node->cond)) {
            return [];
        }

        // Body do if precisa ter pelo menos 1 statement
        if (empty($node->stmts)) {
            return [];
        }

        // Procura no body: tem Log::warning/Log::error/Log::critical/Log::alert/Log::emergency?
        $hasLog = false;
        $hasAssign = false;

        foreach ($node->stmts as $stmt) {
            if (! $stmt instanceof Expression) {
                continue;
            }

            // Atribuição?
            if ($stmt->expr instanceof Assign) {
                $hasAssign = true;
                continue;
            }

            // Log::warning/error/critical/alert/emergency call?
            if ($this->isLogCall($stmt->expr)) {
                $hasLog = true;
            }
        }

        // Pattern violador: tem assignment + NÃO tem log
        if ($hasAssign && ! $hasLog) {
            return [
                RuleErrorBuilder::message(
                    'Fallback silencioso detectado: `if (empty/isset)` com atribuição sem `Log::warning` no mesmo bloco. '
                    . 'Adicione `\\Log::warning(\'<contexto>\', [...]);` ANTES do assignment pra rastreabilidade. '
                    . 'Ver ADR 0212 Camada 2 (pattern canon) + AP-18 no LICOES_F3.'
                )
                    ->identifier('oimpresso.silentFallback')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Detecta `empty($x)` ou `! isset($x)` na condição do if.
     */
    private function isEmptyOrNotIssetCondition(Node $cond): bool
    {
        // empty($x) — empty() é language construct, parseado como Node\Expr\Empty_
        if ($cond instanceof Node\Expr\Empty_) {
            return true;
        }

        // ! isset($x) — booleanNot wrapping isset
        if ($cond instanceof Node\Expr\BooleanNot && $cond->expr instanceof Node\Expr\Isset_) {
            return true;
        }

        return false;
    }

    /**
     * Detecta `Log::warning/error/critical/alert/emergency(...)` ou `\Log::*(...)`.
     */
    private function isLogCall(Node $expr): bool
    {
        if (! $expr instanceof StaticCall) {
            return false;
        }

        // Class name: Log ou \Log ou \Illuminate\Support\Facades\Log
        if (! $expr->class instanceof Name) {
            return false;
        }

        $className = $expr->class->toString();
        $isLogClass = in_array($className, [
            'Log',
            'Illuminate\\Support\\Facades\\Log',
        ], true);

        if (! $isLogClass) {
            return false;
        }

        // Method name: warning/error/critical/alert/emergency
        if (! $expr->name instanceof Node\Identifier) {
            return false;
        }

        $methodName = $expr->name->toString();

        return in_array($methodName, [
            'warning',
            'error',
            'critical',
            'alert',
            'emergency',
        ], true);
    }
}
