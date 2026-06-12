<?php

declare(strict_types=1);

/**
 * Bug Wagner (P3): no header do drawer o badge de status ("Sem OS"/"Ativo") ficava
 * no canto superior direito ENCAVALADO com o X de fechar do Sheet. + usava cor crua
 * (stone/emerald) fora do token.
 *
 * Fix: badge movido pra DENTRO da linha de identidade (inline na SheetDescription,
 * inline-flex → não cria flex container novo) e cor tokenizada. Topo-direito fica
 * só com o X.
 *
 * Canon: structural guard. Confirmação visual (não encavala mais) = smoke pós-deploy.
 */

$index = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';

test('drawer header — badge status tokenizado (sem stone/emerald cru)', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain("? 'bg-muted text-muted-foreground border-border'")
        ->toContain("'bg-success/10 text-success border-success/30'")
        // o ternário antigo do BADGE (idle ? stone : emerald) saiu — específico do badge,
        // não file-wide (stone-* ainda existe em outros elementos, fora do escopo do P3).
        ->not->toContain("? 'bg-stone-50 text-stone-700 border-stone-200 dark:bg-stone-950/40 dark:text-stone-300'");
});

test('drawer header — badge saiu da colisão com o X (movido pra linha de identidade)', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)->toContain('movido pra ESTA linha');
});
