<?php

declare(strict_types=1);

/**
 * Tela-linda Cliente · slice 1 (coração da listagem): Pills.tsx tokeniza o que é
 * ESTADO semântico e PRESERVA o que é COR DE CATEGORIA.
 *
 * Os tokens -soft/-fg foram extraídos desta exata tela-ouro (#2639), então os pills
 * semânticos ficam visualmente 1:1 (gate visual aprovado por Wagner via widget
 * antes/depois). As 9 cores de tag + PF/PJ são identidade de categoria — colapsá-las
 * nos 4 tokens semânticos apagaria a distinção → exceção documentada, mantida crua.
 *
 * Canon: structural guard. Prova visual = widget aprovado + smoke pós-deploy.
 */

$pills = __DIR__ . '/../../../resources/js/Pages/Cliente/_components/Pills.tsx';

test('Pills — Status/Frescor/Saldo (ESTADO) consomem tokens semânticos', function () use ($pills) {
    $src = file_get_contents($pills);
    expect($src)
        // StatusPill
        ->toContain("ativo: {\n    bg: 'bg-success-soft text-success-fg border-success/20'")
        ->toContain("bloqueado: {\n    bg: 'bg-destructive-soft text-destructive-fg border-destructive/20'")
        // FrescorPill
        ->toContain("fresc: 'bg-success-soft text-success-fg border-success/20'")
        ->toContain("recente: 'bg-warning-soft text-warning-fg border-warning/20'")
        ->toContain("distante: 'bg-destructive-soft text-destructive-fg border-destructive/20'")
        // SaldoCell
        ->toContain("isDevedor ? 'text-destructive-fg' : 'text-success-fg'")
        // os pills de ESTADO não usam mais rose/emerald cru
        ->not->toContain("text-rose-700 dark:text-rose-300")
        ->not->toContain("bg-rose-50 text-rose-700 border-rose-200");
});

test('Pills — TagChip (9 cores) e TipoPill (PF/PJ) PRESERVAM cor de categoria', function () use ($pills) {
    $src = file_get_contents($pills);
    expect($src)
        // o rainbow de categoria continua cru — de propósito (decisão "B")
        ->toContain("'bg-amber-50 text-amber-700 border-amber-200")   // varejo
        ->toContain("'bg-blue-50 text-blue-700 border-blue-200")      // corporativo
        ->toContain("'bg-indigo-50 text-indigo-700 border-indigo-200") // agencia
        ->toContain("bg-violet-50 text-violet-700")                    // PJ
        // e a exceção está DOCUMENTADA no código (não é descuido)
        ->toContain('EXCEÇÃO DE COR DE CATEGORIA')
        ->toContain('COR DE CATEGORIA');
});
