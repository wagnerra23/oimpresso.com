<?php

declare(strict_types=1);

/**
 * Bug Wagner (auditoria adversária da tela Cliente, P1): o subtítulo da linha
 * repetia o nome — o legado preenche `fantasia` = razão social, então
 * ".COM COMUNICAÇÃO E EVENTOS" aparecia DUAS vezes (título + subtítulo),
 * desperdiçando a linha de identidade (a mais valiosa pra "achar em ≤3s").
 *
 * Fix: só mostra a fantasia como subtítulo quando ela DIFERE do nome
 * (case-insensitive, trimmed); senão cai pro telefone ou nada.
 *
 * Canon: structural guard. Confirmação visual = smoke pós-deploy.
 */

$index = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';

test('subtítulo — suprime a fantasia quando == razão social (não duplica o nome)', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain("f.toLowerCase() !== row.name.trim().toLowerCase()")
        // fallback honesto: telefone ou nada (não renderiza o duplicado).
        ->toContain('if (!fant && !row.mobile) return null;');
});
