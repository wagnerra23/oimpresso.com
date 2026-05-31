<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\IndexRegenCommand;

uses(Tests\TestCase::class);

/**
 * Gate de integridade & priorização do índice mestre memory/INDEX.md.
 *
 * Origem: regressão 2026-05-29 (Wagner) — Constituição/NORTE-ROI/Protocolo/Skills Tier A
 * sumiram da priorização do índice; contagens stale; 7 links quebrados. Este gate falha
 * o CI se um doc Tier 0 sumir do INDEX ou se um link relativo quebrar — impede a
 * regressão de voltar silenciosamente. Pareado com o comando `jana:index-regen`.
 */

function indexMestre(): string
{
    return (string) file_get_contents(base_path('memory/INDEX.md'));
}

it('todos os docs Tier 0 estão linkados no índice mestre', function () {
    $faltando = (new IndexRegenCommand())->tier0Faltando(indexMestre());
    expect($faltando)->toBeEmpty();
});

it('o índice mestre não tem links relativos quebrados', function () {
    $quebrados = (new IndexRegenCommand())->linksQuebrados(indexMestre(), base_path('memory'));
    expect($quebrados)->toBeEmpty();
});

it('a Constituição está priorizada no TOPO (LEI MÁXIMA antes do onboarding)', function () {
    $txt = indexMestre();
    $leiMaxima = mb_strpos($txt, 'LEI MÁXIMA');
    $comeceAqui = mb_strpos($txt, 'Comece aqui');

    expect($leiMaxima)->not->toBeFalse()
        ->and($comeceAqui)->not->toBeFalse()
        ->and($leiMaxima)->toBeLessThan($comeceAqui)
        ->and($txt)->toContain('0094-constituicao-v2');
});
