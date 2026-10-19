<?php

declare(strict_types=1);

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Modelos\CatalogoModelos;

/**
 * Catálogo dos 7 modelos de fluxo (ADR 0435) contra a fonte, `fluxos-data.jsx`.
 *
 * O contrato é o protótipo do [W]: o teste lê o .jsx e compara etapas e regras modelo a
 * modelo, para o catálogo não divergir em silêncio quando um dos dois mudar.
 */

/** @return array<string, array{processos: array<string, array{etapas: list<string>, regras: list<array{0:string,1:string}>}>}> */
function flxProtoModelos(): array
{
    $src = file_get_contents(__DIR__ . '/../../../prototipo-ui/cowork/Wagner/fluxos-data.jsx');
    expect($src)->toBeString();
    $bloco = substr($src, strpos($src, 'const MODELOS = ['));
    $bloco = substr($bloco, 0, strpos($bloco, 'function encaixe'));

    $modelos = [];
    $atual = null;
    foreach (explode("\n", $bloco) as $linha) {
        $linha = rtrim($linha);
        if (preg_match('/\{ id: "([a-z-]+)"/', $linha, $m)) {
            $atual = $m[1];
            $modelos[$atual] = ['processos' => []];
        }
        if ($atual !== null && preg_match('/dom: "([a-z]+)", etapas: \[([^\]]*)\], regras: \[(.*)\] \},?$/', $linha, $m)) {
            preg_match_all('/"([^"]*)"/', $m[2], $etapas);
            preg_match_all('/\["([^"]*)", "[^"]*", "(UC-FLX-\d+|)"\]/', $m[3], $regras, PREG_SET_ORDER);
            $modelos[$atual]['processos'][$m[1]] = [
                'etapas' => $etapas[1],
                'regras' => array_map(fn ($r) => [$r[1], $r[2]], $regras),
            ];
        }
    }

    return $modelos;
}

it('tem os 7 modelos do protótipo, na mesma ordem', function () {
    expect(array_keys(CatalogoModelos::modelos()))
        ->toBe(['balcao', 'cv-pequena', 'cv-pcp', 'grafica', 'brindes', 'instalacao', 'oficina'])
        ->and(array_keys(flxProtoModelos()))->toBe(array_keys(CatalogoModelos::modelos()));
});

it('cada processo tem as etapas na ordem do protótipo e as mesmas regras etapa → UC', function () {
    $proto = flxProtoModelos();
    $comparados = 0;

    foreach (CatalogoModelos::modelos() as $id => $modelo) {
        expect(array_keys($modelo['processos']))->toBe(array_keys($proto[$id]['processos']), "domínios de {$id}");

        foreach ($modelo['processos'] as $dom => $proc) {
            expect($proc['etapas'])->toBe($proto[$id]['processos'][$dom]['etapas'], "etapas de {$id}/{$dom}");
            $regras = array_map(fn ($r) => [$r[0], $r[2]], $proc['regras']);
            expect($regras)->toBe($proto[$id]['processos'][$dom]['regras'], "regras de {$id}/{$dom}");
            $comparados++;
        }
    }

    // Anti-vácuo: se o parser do .jsx quebrar, o laço acima não compara nada.
    expect($comparados)->toBe(19);
});

it('a venda do Balcão é Orçamento → Aprovado → Pronto → Entregue → Pago', function () {
    expect(CatalogoModelos::modelo('balcao')['processos']['venda']['etapas'])
        ->toBe(['Orçamento', 'Aprovado', 'Pronto', 'Entregue', 'Pago']);
});

it('toda regra cai numa etapa do próprio processo e aponta para UC existente', function () {
    foreach (CatalogoModelos::modelos() as $id => $modelo) {
        foreach ($modelo['processos'] as $dom => $proc) {
            expect(CatalogoModelos::DOMINIOS)->toHaveKey($dom);
            foreach ($proc['regras'] as [$etapa, , $uc]) {
                expect(in_array($etapa, $proc['etapas'], true))->toBeTrue("{$id}/{$dom}: etapa {$etapa}");
                if ($uc !== '') {
                    expect(CatalogoModelos::CASOS)->toHaveKey($uc);
                }
            }
        }
        foreach ($modelo['casos'] as $uc) {
            expect(CatalogoModelos::CASOS)->toHaveKey($uc);
        }
    }
});

it('nenhuma regra aponta para classe inexistente', function () {
    $classes = 0;
    foreach (CatalogoModelos::modelos() as $modelo) {
        foreach ($modelo['processos'] as $proc) {
            foreach ($proc['regras'] as $regra) {
                foreach ([$regra[3], $regra[4]] as $classe) {
                    if ($classe === null) {
                        continue;
                    }
                    expect(class_exists($classe))->toBeTrue($classe)
                        ->and(is_subclass_of($classe, SideEffectInterface::class))->toBeTrue($classe);
                    $classes++;
                }
            }
        }
    }
    expect($classes)->toBeGreaterThan(0);
});

it('chaves de etapa são únicas no processo e cabem no motor', function () {
    expect(CatalogoModelos::chaveEtapa('Aprovação do cliente'))->toBe('aprovacao_do_cliente')
        ->and(CatalogoModelos::chaveEtapa('Pré-impressão'))->toBe('pre_impressao')
        ->and(CatalogoModelos::chaveProcesso('cv-pequena', 'venda'))->toBe('flx_cv_pequena_venda');

    foreach (CatalogoModelos::modelos() as $id => $modelo) {
        foreach ($modelo['processos'] as $dom => $proc) {
            $chaves = array_map([CatalogoModelos::class, 'chaveEtapa'], $proc['etapas']);
            expect(count(array_unique($chaves)))->toBe(count($chaves))
                ->and(in_array('cancelado', $chaves, true))->toBeFalse();
            expect(strlen(CatalogoModelos::chaveProcesso($id, $dom)))->toBeLessThanOrEqual(80);
        }
    }
});

it('encaixe igual ao protótipo: porte 2, porte vizinho 1, atividade 2', function () {
    expect(CatalogoModelos::encaixe('balcao', 'micro', 'cv'))->toBe(4)
        ->and(CatalogoModelos::encaixe('balcao', 'pequena', 'cv'))->toBe(3)
        ->and(CatalogoModelos::encaixe('balcao', 'grande', 'cv'))->toBe(2)
        ->and(CatalogoModelos::encaixe('oficina', 'grande', 'cv'))->toBe(1)
        ->and(CatalogoModelos::encaixe('cv-pcp', 'micro', 'rapida'))->toBe(0)
        ->and(CatalogoModelos::rotuloEncaixe(4))->toBe('Recomendado')
        ->and(CatalogoModelos::rotuloEncaixe(3))->toBe('Serve')
        ->and(CatalogoModelos::rotuloEncaixe(1))->toBe('Parcial')
        ->and(CatalogoModelos::rotuloEncaixe(0))->toBeNull();

    $rec = CatalogoModelos::recomendar('pequena', 'cv');
    expect($rec[0])->toBe(['id' => 'cv-pequena', 'encaixe' => 4, 'rotulo' => 'Recomendado'])
        ->and(count($rec))->toBe(7);
});

/** Pares `chave: "texto"` e `chave: ["a", "b"]` de uma linha de objeto JS do fluxos-data.jsx. */
function flxObjeto(string $linha): array
{
    preg_match_all('/(\w+):\s*(?:"([^"]*)"|\[([^\]]*)\])/', $linha, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $par) {
        if (isset($par[3])) {
            preg_match_all('/"([^"]*)"/', $par[3], $itens);
            $out[$par[1]] = $itens[1];
        } else {
            $out[$par[1]] = $par[2];
        }
    }

    return $out;
}

/** Linhas do bloco `const <nome> = ...` até a linha que fecha o bloco. */
function flxBloco(string $nome): array
{
    $src = (string) file_get_contents(__DIR__ . '/../../../prototipo-ui/cowork/Wagner/fluxos-data.jsx');
    $ini = strpos($src, "const {$nome} = ");
    $fim = strpos($src, "\n};", $ini) ?: PHP_INT_MAX;
    $fim2 = strpos($src, "\n];", $ini) ?: PHP_INT_MAX;

    return array_map('rtrim', explode("\n", substr($src, $ini, min($fim, $fim2) - $ini)));
}

it('casos de uso, fontes, portes e estados são os do protótipo', function () {
    $casos = [];
    foreach (flxBloco('CASOS') as $l) {
        if (str_contains($l, 'id: "UC-FLX-')) {
            $o = flxObjeto($l);
            $casos[] = [$o['id'], $o['t'], $o['proc'], $o['etapa'], $o['regra'], $o['fontes'], $o['porte'], $o['estado'], $o['us']];
        }
    }
    expect(count($casos))->toBe(38)
        ->and(\App\Domain\Fsm\Modelos\CasosDoPrototipo::CASOS)->toBe($casos);

    $fontes = [];
    foreach (flxBloco('FONTES') as $l) {
        if (preg_match('/^\s+(\w+):\s+\{/', $l, $k)) {
            $o = flxObjeto($l);
            $fontes[$k[1]] = ['nome' => $o['n'], 'url' => $o['url']];
        }
    }
    expect(count($fontes))->toBe(13)->and(CatalogoModelos::fontes())->toBe($fontes);

    $portes = [];
    foreach (flxBloco('PORTES') as $l) {
        if (str_contains($l, '{ v: ')) {
            $o = flxObjeto($l);
            $portes[$o['v']] = ['rotulo' => $o['l'], 'descricao' => $o['d']];
        }
    }
    expect(CatalogoModelos::portes())->toBe($portes)
        ->and(array_keys($portes))->toBe(array_keys(CatalogoModelos::PORTES))
        ->and(CatalogoModelos::portes()['pequena']['descricao'])->toBe('6 a 20 pessoas');

    $estados = [];
    foreach (flxBloco('ESTADOS') as $l) {
        if (preg_match('/^\s+(\w+):\s+\{/', $l, $k)) {
            $o = flxObjeto($l);
            $estados[$k[1]] = ['rotulo' => $o['l'], 'tom' => $o['tone']];
        }
    }
    expect(count($estados))->toBe(7)->and(CatalogoModelos::estadosCaso())->toBe($estados);

    // Todo caso aponta para fonte, porte e estado existentes; CASOS (título, domínio) concorda.
    foreach (CatalogoModelos::casos() as $c) {
        expect(array_diff($c['fontes'], array_keys($fontes)))->toBe([], $c['id'])
            ->and(array_key_exists($c['porte'], $portes))->toBeTrue($c['id'])
            ->and(array_key_exists($c['estado'], $estados))->toBeTrue($c['id'])
            ->and(CatalogoModelos::CASOS[$c['id']])->toBe([$c['titulo'], $c['processo']], $c['id']);
    }
});

it('cada modelo traz o inspirado em e a regra de custo da compra do protótipo', function () {
    $insp = [];
    $compra = [];
    $atual = null;
    foreach (flxBloco('MODELOS') as $l) {
        if (preg_match('/\{ id: "([a-z-]+)"/', $l, $m)) {
            $atual = $m[1];
        }
        if ($atual !== null && preg_match('/insp: \[([^\]]*)\]/', $l, $m)) {
            preg_match_all('/"([^"]*)"/', $m[1], $i);
            $insp[$atual] = $i[1];
        }
        if ($atual !== null && preg_match('/compra: (\{[^}]*\})/', $l, $m)) {
            $compra[$atual] = flxObjeto($m[1]);
        }
    }
    expect(count($insp))->toBe(7)->and(count($compra))->toBe(7);
    foreach (CatalogoModelos::modelos() as $id => $m) {
        expect($m['insp'])->toBe($insp[$id], "insp de {$id}")
            ->and($m['compra'])->toBe($compra[$id], "compra de {$id}")
            ->and(array_diff($m['insp'], array_keys(CatalogoModelos::fontes())))->toBe([], $id);
    }
});
