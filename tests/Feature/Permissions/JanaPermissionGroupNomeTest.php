<?php

declare(strict_types=1);

use App\Services\PermissionRegistry;

// SEM `uses(Tests\TestCase::class)` aqui de proposito: `tests/Pest.php:5` ja
// aplica `uses(TestCase::class)->in('Feature')` a esta pasta inteira, e declarar de
// novo faz o Pest ABORTAR o run com "Test case can not be used. The folder ... already
// uses the test case". Medido: zero dos outros arquivos de tests/Feature/ declaram.
// (Os testes de Modules/ declaram porque nao caem naquele `->in('Feature')`.)

/**
 * UC-JNAME-01 — a tela de permissões não oferece um módulo chamado "Copiloto".
 *
 * ── O QUE ESTE TESTE TRAVA (e por que ele nasce DEPOIS do conserto)
 * O rename Copiloto→Jana (ADR 0088/0092) foi PHP-only por decisão, e a fachada
 * ficou pra "PR-3+ posterior". O `permissions.php` seguiu declarando
 * `group: 'Copiloto'` com as labels em "Copiloto: …" — que é o que o cliente lê
 * ao montar um perfil de acesso em /roles/{id}/edit.
 *
 * O conserto veio no #6344 (2026-08-27, "as 134 chaves ficam byte-idênticas").
 * O que NÃO veio foi teste: medido em 2026-08-28, zero testes citavam
 * `permissions.php` do módulo. Ganho sem catraca é piso baixo — pode regredir
 * sem nada piscar. Este arquivo é a catraca.
 *
 * ── A ASSERÇÃO QUE IMPEDE O CONSERTO DE VIRAR O INCIDENTE
 * `group` e `label` são COPY; `key` é INCIDENTE. Em #4853 o rename de
 * `copiloto.mcp.*` → `jana.mcp.*` deixou um `givePermissionTo('copiloto.mcp.use')`
 * apontando pra permissão inexistente e quebrou TODO o onboarding
 * (governance-script-tests.yml:540). Por isso o 3º caso vigia as keys: quem
 * "terminar o rename" mexendo nelas quebra este teste antes de quebrar a prod.
 *
 * ── ANTI-VÁCUO
 * Os casos 2 e 3 iteram uma coleção. Coleção vazia passa em `every()`
 * trivialmente — o verde seria falso. Por isso o 1º caso assere que o módulo
 * FOI descoberto e que a lista NÃO está vazia, e os outros dois reancoram a
 * contagem. Sem isso, apagar o `permissions.php` deixaria a suíte verde.
 *
 * ÂNCORA DE CONTRATO (externa ao código — teste tautológico é lápide §5 2026-06-05):
 *   · UC-JNAME-01 em resources/js/Pages/Jana/Index.casos.md
 *   · Modules/Jana/Resources/permissions.php (o declarado)
 *   · ADR 0088 / 0092 (o rename) · #4853 (a cicatriz das keys)
 */
function janaRegistry(): array
{
    $reg = app(PermissionRegistry::class);
    $reg->flush();                       // cache filesystem TTL 5min

    $mod = $reg->discover()->get('Jana');
    if (! $mod) {
        test()->fail('PermissionRegistry não descobriu o módulo Jana — o auto-discovery mudou ou o permissions.php sumiu.');
    }

    return $mod;
}

it('UC-JNAME-01 · o registry descobre o módulo e a lista NÃO está vazia (anti-vácuo)', function () {
    $mod = janaRegistry();

    // Sem esta âncora, os dois casos seguintes passariam com a lista vazia.
    expect($mod['permissions'])->toBeArray();
    expect(count($mod['permissions']))->toBeGreaterThan(10);
});

it('UC-JNAME-01 · o grupo se chama Jana — é o rótulo que o cliente lê em /roles/{id}/edit', function () {
    $mod = janaRegistry();

    expect($mod['group'])->toBe('Jana');
    expect($mod['group'])->not->toContain('Copiloto');
});

it('UC-JNAME-01 · nenhuma label visível diz "Copiloto"', function () {
    $mod = janaRegistry();

    $sujas = array_values(array_filter(
        $mod['permissions'],
        fn (array $p) => stripos((string) ($p['label'] ?? ''), 'copiloto') !== false
    ));

    expect($sujas)->toBe([], 'label(s) ainda dizendo Copiloto: '
        . implode(' · ', array_map(fn ($p) => (string) ($p['key'] ?? '?'), $sujas)));
    expect(count($mod['permissions']))->toBeGreaterThan(10);   // reancora o vácuo
});

it('UC-JNAME-01 · as KEYS seguem no prefixo jana. — copy muda, chave não (cicatriz #4853)', function () {
    $mod = janaRegistry();

    $foraDoPrefixo = array_values(array_filter(
        $mod['permissions'],
        fn (array $p) => ! str_starts_with((string) ($p['key'] ?? ''), 'jana.')
    ));

    expect($foraDoPrefixo)->toBe([], 'key(s) fora do prefixo jana.: '
        . implode(' · ', array_map(fn ($p) => (string) ($p['key'] ?? '?'), $foraDoPrefixo)));

    // A regressão específica de #4853: key voltando ao vocabulário antigo.
    foreach ($mod['permissions'] as $p) {
        expect((string) ($p['key'] ?? ''))->not->toStartWith('copiloto.');
    }
    expect(count($mod['permissions']))->toBeGreaterThan(10);   // reancora o vácuo
});
