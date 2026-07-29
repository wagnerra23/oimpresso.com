<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

uses(Tests\TestCase::class);

/**
 * Contrato do `governance:ui-catalog-generate` — resgatado de Modules/Admin
 * na depreciação do Admin Center.
 *
 * POR QUE ESTE TESTE EXISTE (o defeito que ele mata):
 * em Modules/Admin o comando existia em disco mas o AdminServiceProvider
 * registrava só 2 dos 3 commands — o Artisan NUNCA conheceu este. Medido em
 * 2026-07-29 no CT 100: `php artisan list` listava `admin:health` e
 * `admin:export-audit`, e não este. Os 30 UI-CATALOG.md do repo ainda mandam
 * rodar um comando que o Artisan não tinha.
 *
 * Por isso o assert é `Artisan::all()` — o registry VIVO — e não
 * `class_exists()`/`app(...)`, que provam só que o arquivo está no disco
 * (o container resolve qualquer classe concreta, registrada ou não).
 *
 * @see memory/proibicoes.md §5 2026-07-28 "Teste que afirma 'registrado' medindo app(Class::class)"
 */
it('governance:ui-catalog-generate está registrado no Artisan (registry vivo, não disco)', function () {
    expect(array_keys(Artisan::all()))->toContain('governance:ui-catalog-generate');
});

it('o nome antigo admin:ui-catalog-generate não responde mais', function () {
    $registrados = array_keys(Artisan::all());

    // Pré-condição anti-vácuo: `not->toContain` passa num array VAZIO. Sem app
    // Laravel bootado (faltando o `uses(TestCase::class)` acima), Artisan::all()
    // volta vazio e este caso ficaria verde provando nada — foi exatamente o que
    // aconteceu no primeiro push deste PR: 3 casos falharam e SÓ ESTE "passou".
    expect($registrados)->not->toBeEmpty();

    expect($registrados)->not->toContain('admin:ui-catalog-generate');
});

it('--dry-run carimba o comando novo no rodapé e não a cadência que nunca existiu', function () {
    // módulo concreto (7 telas) em vez de --all: varrer as 279 telas do repo
    // deixaria o caso lento e o output gigante sem provar nada a mais.
    Artisan::call('governance:ui-catalog-generate', ['modulo' => 'Governance', '--dry-run' => true]);
    $output = Artisan::output();

    // pré-condição anti-vácuo: se o comando não varreu tela nenhuma, o resto
    // dos asserts passa por AUSÊNCIA de saída, não por acerto (§5 2026-07-24).
    expect($output)->toContain('## Telas');

    // comportamento, não forma: é o texto que vai parar dentro de cada UI-CATALOG.md
    expect($output)->toContain('php artisan governance:ui-catalog-generate');
    expect($output)->not->toContain('admin:ui-catalog-generate');

    // `daily 09:30 BRT` era falso — não há schedule `admin:*` nem `governance:ui-catalog-*`
    // em app/Console/Kernel.php (grep = 0 linhas, medido 2026-07-29).
    expect($output)->not->toContain('daily 09:30 BRT');
});

it('--dry-run não escreve arquivo nenhum', function () {
    $alvo = base_path('memory/requisitos/Governance/UI-CATALOG.md');
    $antes = is_file($alvo) ? filemtime($alvo) : null;

    Artisan::call('governance:ui-catalog-generate', ['modulo' => 'Governance', '--dry-run' => true]);

    $depois = is_file($alvo) ? filemtime($alvo) : null;
    expect($depois)->toBe($antes);
});
