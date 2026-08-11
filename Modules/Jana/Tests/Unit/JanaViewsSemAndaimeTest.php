<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda 5 do pedido [CC] `JANA-ONDAS-PR-2026-08-09` — "verdade nos botões".
 *
 * As telas Blade de `/ia/*` renderizavam **pro cliente** a string
 * `STUB spec-ready — ver SPEC US-COPI-060`. Este teste guarda a cláusula de saída
 * daquela onda: string de andaime não volta pra view de TELA.
 *
 * `Resources/views/emails/` fica FORA de propósito — e-mail é Blade por definição
 * (o digest semanal não é tela e não migra pra Inertia).
 *
 * ## Por que o vocabulário é case-SENSITIVE
 *
 * "todo" é palavra portuguesa corriqueira ("todos os clientes", "todo mês"), então
 * `TODO` case-insensitive seria falso-positivo em massa no primeiro texto novo — é
 * a família de guard sintático que reprova o legítimo, já enterrada 5× no §5 de
 * `memory/proibicoes.md`. Medido no corpus em 2026-08-09, ANTES de ligar:
 * **8 views de tela, 0 hits** com os padrões abaixo.
 */
function janaPadraoAndaime(): string
{
    return '/\b(STUB|FIXME|TODO)\b|spec-ready|[Ee]m breve/';
}

/** Views de TELA do módulo Jana (exclui `emails/`), path curto => conteúdo. */
function janaViewsDeTela(): array
{
    $base = realpath(__DIR__ . '/../../Resources/views');
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

    $out = [];
    foreach ($iter as $arquivo) {
        if (! $arquivo->isFile() || ! str_ends_with($arquivo->getFilename(), '.blade.php')) {
            continue;
        }

        $path = str_replace('\\', '/', $arquivo->getPathname());
        if (str_contains($path, '/emails/')) {
            continue;
        }

        $curto = basename(dirname($path)) . '/' . basename($path);
        $out[$curto] = file_get_contents($arquivo->getPathname());
    }

    return $out;
}

it('varre um corpus real — sem isto, zero violações poderia ser zero arquivos', function () {
    // Pré-condição anti-vácuo (LC-13): "0 violações" só quer dizer algo se houve o
    // que varrer. Sem este caso, apagar as views deixaria o teste abaixo VERDE.
    expect(count(janaViewsDeTela()))->toBeGreaterThanOrEqual(6);
});

it('MORDE — o padrão pega a string exata que estava em prod, e libera a correção', function () {
    // Bite-test + controle negativo: sem isto, um regex quebrado ficaria verde pra
    // sempre e o teste viraria carimbo.
    $antes  = '<div class="alert alert-warning">STUB spec-ready — ver SPEC US-COPI-060.</div>';
    $depois = '<p><strong>A lista de alertas ainda não existe.</strong></p>';

    expect(preg_match(janaPadraoAndaime(), $antes))->toBe(1);
    expect(preg_match(janaPadraoAndaime(), $depois))->toBe(0);

    // Controle negativo do case-sensitive: português legítimo NÃO pode disparar.
    expect(preg_match(janaPadraoAndaime(), 'Mostra todos os clientes de todo mês.'))->toBe(0);
});

it('não renderiza string de andaime pro cliente em nenhuma view de tela da Jana', function () {
    $violacoes = [];

    foreach (janaViewsDeTela() as $curto => $conteudo) {
        foreach (preg_split('/\R/', $conteudo) as $i => $linha) {
            if (preg_match(janaPadraoAndaime(), $linha, $m) === 1) {
                $violacoes[] = $curto . ':' . ($i + 1) . ' → "' . $m[0] . '"';
            }
        }
    }

    expect($violacoes)->toBe([]);
});
