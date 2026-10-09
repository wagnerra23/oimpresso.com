<?php

declare(strict_types=1);

/**
 * Thread 08 PR-b do playbook Forja — estados de erro/vazio achados pelo scorecard da 04.
 *
 * Os três casos são comportamento de FRONT (fetch, filtro, defer) e as telas não têm E2E.
 * Esta é a perna de REGISTRO: lê o `.tsx` e prova que o mecanismo está lá. Não prova o
 * render — isso fica declarado em cada `.casos.md`, para o 🧪 não virar ✅ por leitura.
 * Roda em qualquer driver.
 *
 *   UC-EQP-09 · Team: os 4 fetch olham `r.ok`; 403/419/500 têm mensagem própria.
 *   UC-CCS-08 · CcSessions: "Limpar" zera também `from`/`to`.
 *   UC-SC-09  · Scorecard: 0 checks = estado vazio; falha do defer sai de "Carregando…".
 */

uses(Tests\TestCase::class);

function gapsPage(string $tela): string
{
    return (string) file_get_contents(base_path("Modules/Forja/Resources/js/Pages/team-mcp/{$tela}/Index.tsx"));
}

it('UC-EQP-09 · nenhum fetch da Equipe faz r.json() sem olhar r.ok', function () {
    $tsx = gapsPage('Team');

    // Controle positivo: os 4 endpoints de escrita/leitura JSON continuam na tela.
    foreach (['/token`', '/tokens`', '/token/${t.id}`', '/quota`'] as $rota) {
        expect(str_contains($tsx, $rota))->toBeTrue("rota {$rota} sumiu da tela — o assert abaixo viraria vácuo");
    }

    // O parse cru (que transformava 403/419/500 em "Erro de rede") não pode voltar.
    expect(preg_match('/\.then\(\s*\(?r\)?\s*=>\s*r\.json\(\)\s*\)/', $tsx))->toBe(0);
    expect(substr_count($tsx, '.then(jsonOuErro)'))->toBe(4);
});

it('UC-EQP-09 · 403, 419 e 5xx têm mensagem própria, distinta de "Erro de rede"', function () {
    $tsx = gapsPage('Team');

    expect(str_contains($tsx, 'status === 403'))->toBeTrue();
    expect(str_contains($tsx, 'status === 419'))->toBeTrue();
    expect(str_contains($tsx, 'status >= 500'))->toBeTrue();
    // .dxt já olhava res.ok, mas com mensagem genérica; agora usa a mesma tabela.
    expect(str_contains($tsx, 'mensagemHttp(res.status)'))->toBeTrue();
});

it('UC-CCS-08 · "Limpar" zera from e to junto com os outros filtros', function () {
    $tsx = gapsPage('CcSessions');

    // O hasFilters conta from/to: se o Limpar não os zera, o botão some e o filtro de
    // data fica preso sem saída.
    expect(str_contains($tsx, 'filters.from || filters.to'))->toBeTrue();
    expect(preg_match("/applyFilter\(\{[^}]*from: ''[^}]*to: ''[^}]*\}\)/", $tsx))->toBe(1);
});

it('UC-SC-09 · 0 checks vira estado vazio, não "0 de 0 checks falhando"', function () {
    $tsx = gapsPage('Scorecard');

    expect(str_contains($tsx, 'const semChecks = !isLoading && checkList.length === 0'))->toBeTrue();
    expect(str_contains($tsx, 'Nenhum check configurado.'))->toBeTrue();
});

it('UC-SC-09 · falha do defer sai de "Carregando…" para erro com nova tentativa', function () {
    $tsx = gapsPage('Scorecard');

    expect(str_contains($tsx, "'httpException'"))->toBeTrue();
    expect(str_contains($tsx, "'networkError'"))->toBeTrue();
    expect(str_contains($tsx, 'Não foi possível carregar os checks.'))->toBeTrue();
    expect(str_contains($tsx, 'Tentar de novo'))->toBeTrue();
});
