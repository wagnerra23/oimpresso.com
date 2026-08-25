<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * ARCHITECTURE TEST — o catálogo fechado não pode divergir das views de papel.
 *
 * PermissionCatalog::CORE é uma lista DERIVADA de role/create.blade.php e role/edit.blade.php.
 * Lista derivada mantida à mão apodrece: alguém adiciona um checkbox na view, esquece a constante,
 * e a permissão nova passa a ser DESCARTADA silenciosamente ao salvar o papel — um bug pior que o
 * que o catálogo veio consertar, porque não faz barulho.
 *
 * Este teste é a catraca que troca o esquecimento por vermelho no CI: ele re-extrai das views e
 * compara com a constante, nos DOIS sentidos (nada a mais, nada a menos).
 *
 * Também trava a premissa que a extração assume: create e edit oferecem o MESMO conjunto. Se um
 * dia divergirem, o catálogo passa a depender de qual view você olhou — e isso tem de doer aqui,
 * não em produção.
 */

use App\Utils\PermissionCatalog;
use Illuminate\Support\Facades\File;

/** Permissões que uma view de papel oferece: checkbox (permissions[]) + radio (radio_option[...]). */
function permissoesDaView(string $view): array
{
    $src = File::get(resource_path('views/role/'.$view.'.blade.php'));

    $checkbox = [];
    preg_match_all("/Form::checkbox\('permissions\[\]',\s*'([^']+)'/", $src, $checkbox);

    $radio = [];
    preg_match_all("/Form::radio\('radio_option\[[^\]]*\]',\s*'([^']+)'/", $src, $radio);

    $todas = array_merge($checkbox[1] ?? [], $radio[1] ?? []);
    $todas = array_values(array_unique($todas));
    sort($todas);

    return $todas;
}

it('as duas views de papel oferecem o mesmo conjunto de permissões', function () {
    $create = permissoesDaView('create');
    $edit = permissoesDaView('edit');

    // Se isto quebrar, o catálogo passou a depender de qual view foi olhada — decida qual manda
    // ANTES de mexer no PermissionCatalog.
    expect($create)->toBe($edit);
});

it('a extração das views não volta vazia (senão o teste passaria por não medir nada)', function () {
    // Controle positivo: um regex quebrado devolveria [] e faria os outros casos passarem à toa.
    expect(count(permissoesDaView('create')))->toBeGreaterThan(100);
});

it('PermissionCatalog::CORE bate exatamente com o que as views oferecem', function () {
    $daView = permissoesDaView('create');

    $doCatalogo = PermissionCatalog::CORE;
    sort($doCatalogo);

    $faltando = array_values(array_diff($daView, $doCatalogo));
    $sobrando = array_values(array_diff($doCatalogo, $daView));

    // Faltando = a view oferece e o backend DESCARTA em silêncio (o bug ruim).
    expect($faltando)->toBe([]);
    // Sobrando = o catálogo aceita algo que nenhuma tela oferece (superfície a mais).
    expect($sobrando)->toBe([]);
});

it('permitidas() incorpora o que os módulos declaram', function () {
    $doModulo = [
        'Financeiro' => [
            ['value' => 'financeiro.inventada.para.teste', 'label' => 'x', 'default' => false],
        ],
    ];

    $mapa = PermissionCatalog::permitidas($doModulo);

    expect($mapa)->toHaveKey('financeiro.inventada.para.teste');
    expect($mapa)->toHaveKey(PermissionCatalog::CORE[0]);
});

it('intrusas() aponta o que está fora do catálogo e só isso', function () {
    $legitima = PermissionCatalog::CORE[0];

    $intrusas = PermissionCatalog::intrusas([$legitima, 'isto.nao.existe.no.catalogo']);

    expect($intrusas)->toBe(['isto.nao.existe.no.catalogo']);
});
