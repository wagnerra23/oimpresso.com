<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Paridade do PIPELINE entre as três declarações que existem hoje.
 *
 * O buraco que este arquivo fecha (achado 2026-08-09, cobrança do [W] "porque não
 * copiou do protótipo?"):
 *
 *   protótipo (fonte de design)  ←— NINGUÉM TRAVAVA —→  backend  ←— UC-TRAB-07 —→  front
 *
 * O `UC-TRAB-07` (em TrabalhoListaTest) liga front↔backend, e é bom. Mas ele trava
 * o espelho contra o espelho: se o BACKEND divergir da FONTE DE DESIGN, os dois
 * lados concordam entre si e ficam verdes enquanto a tela contradiz o protótipo.
 * Foi exatamente o que aconteceu — o `TrabalhoQuadro.tsx` nasceu espelhando o
 * backend, sem ninguém abrir `forja-data.jsx`.
 *
 * ⚠️ ESTE ARQUIVO NÃO É PRESENCE-GATE. Ele não checa se o charter cita o protótipo
 * (isso seria "o artefato foi tocado", família já morta no §5). Ele lê os DOIS
 * arquivos e compara os VALORES.
 *
 * @see prototipo-ui/cowork/forja-data.jsx        (FONTE de design — ADR 0299/0282)
 * @see Modules/Forja/Services/ForjaQuadroService.php
 * @see resources/js/Pages/Forja/Trabalho/_components/TrabalhoQuadro.tsx
 */

/**
 * DIVERGÊNCIA CONHECIDA, declarada com data e dono — não é allowlist de conveniência.
 *
 * `F4 Merge` existe no protótipo (com `owner: W2`) e NÃO existe no backend. A
 * pergunta "F4 é coluna do quadro ou é a saída dele?" é decisão de produto do [W],
 * não conserto silencioso de agente — por isso ela entra aqui como exceção datada
 * em vez de eu escolher um lado e escrever no charter como se fosse lei (que foi o
 * erro original: o charter afirmava "F4 NÃO é coluna" sem consultar a fonte).
 *
 * Enquanto [W] não decide, o teste garante que NENHUMA OUTRA divergência apareça.
 * Quando decidir: ou o backend ganha F4 e esta lista fica vazia, ou o protótipo é
 * atualizado — e aí a lista fica vazia do mesmo jeito. Os dois caminhos esvaziam.
 */
const DIVERGENCIA_DECLARADA = ['F4'];   // [W] pendente desde 2026-08-09

/** Extrai as fases do protótipo Cowork (a FONTE de design). */
function fasesDoPrototipo(): array
{
    $src = file_get_contents(base_path('prototipo-ui/cowork/forja-data.jsx'));
    expect($src)->not->toBeFalse('forja-data.jsx sumiu — a âncora de design do hub Forja.');

    preg_match('/const FORJA_PHASES\s*=\s*\[(.*?)\];/s', $src, $m);
    expect($m[1] ?? null)->not->toBeNull('FORJA_PHASES mudou de forma no protótipo.');

    preg_match_all('/id:\s*"([^"]+)"/', $m[1], $ids);

    return $ids[1];
}

/** Extrai as fases do backend (quem serve o quadro). */
function fasesDoBackend(): array
{
    $src = file_get_contents(base_path('Modules/Forja/Services/ForjaQuadroService.php'));
    preg_match('/private const FASES\s*=\s*\[(.*?)\];/s', $src, $m);
    expect($m[1] ?? null)->not->toBeNull('FASES mudou de forma no ForjaQuadroService.');

    preg_match_all("/'key'\s*=>\s*'([^']+)'/", $m[1], $keys);

    return $keys[1];
}

it('UC-PIPE-01 — os extratores acham fase nos dois lados (anti-falso-verde)', function () {
    // Sem isto, dois vazios comparariam "iguais" e o arquivo inteiro viraria carimbo.
    // É a mesma guarda do UC-TRAB-07 e do UC-FORJA-14.
    expect(count(fasesDoPrototipo()))->toBeGreaterThan(3, 'Nenhuma fase extraída do protótipo.');
    expect(count(fasesDoBackend()))->toBeGreaterThan(3, 'Nenhuma fase extraída do backend.');
});

it('UC-PIPE-02 — as fases COMUNS aparecem na mesma ORDEM nos dois', function () {
    // Ordem importa: é a sequência do funil. Comparo só a interseção porque a
    // diferença de conjunto é o caso do UC-PIPE-03 — misturar os dois faria um
    // teste falhar por duas razões e não dizer qual.
    $proto = fasesDoPrototipo();
    $back  = fasesDoBackend();

    $comuns = array_values(array_intersect($proto, $back));
    $ordemNoBackend = array_values(array_intersect($back, $proto));

    expect($ordemNoBackend)->toBe($comuns,
        "A ORDEM das fases divergiu entre a fonte de design e o backend.\n".
        '  protótipo: '.implode(' → ', $proto)."\n".
        '  backend  : '.implode(' → ', $back)."\n".
        'O funil é uma sequência — trocar a ordem muda o significado do quadro.'
    );
});

it('UC-PIPE-03 — nenhuma divergência NOVA além da que [W] ainda não decidiu', function () {
    $proto = fasesDoPrototipo();
    $back  = fasesDoBackend();

    $soNoProto = array_values(array_diff($proto, $back));
    $soNoBack  = array_values(array_diff($back, $proto));

    // O backend NUNCA pode ter fase que a fonte de design não tem — isso seria o
    // código inventando pipeline, que é a raiz do incidente que gerou este arquivo.
    expect($soNoBack)->toBe([],
        'O backend tem fase que a FONTE DE DESIGN não conhece: '.implode(', ', $soNoBack)."\n".
        'Pipeline se inventa no protótipo, não no Service.'
    );

    // E do outro lado, só a divergência declarada sobrevive.
    sort($soNoProto);
    $esperado = DIVERGENCIA_DECLARADA;
    sort($esperado);

    expect($soNoProto)->toBe($esperado,
        "A fonte de design tem fase(s) que o backend ignora, além da já declarada.\n".
        '  no protótipo e não no backend: '.(implode(', ', $soNoProto) ?: '(nenhuma)')."\n".
        '  declarada (aguarda [W])      : '.implode(', ', $esperado)."\n".
        'Se F4 foi decidido, esvazie DIVERGENCIA_DECLARADA e alinhe os dois lados.'
    );
});

it('UC-PIPE-04 — a fonte de design carrega hue e owner por fase (o que o backend NÃO traz)', function () {
    // Isto não é cosmético: `owner` diz QUEM responde por cada fase (F0=W, F1=CC,
    // F3=CL…), que é o vocabulário do loop Cowork↔Code. O backend serve só
    // {key,label}, então a tela não tem como mostrar dono de fase nem cor por fase.
    //
    // O caso NÃO exige que o backend passe a servir — exige que a PERDA seja
    // visível. Enquanto ele passar, quem ler o teste sabe que a tela é mais pobre
    // que a fonte, e por quê.
    $src = file_get_contents(base_path('prototipo-ui/cowork/forja-data.jsx'));
    preg_match('/const FORJA_PHASES\s*=\s*\[(.*?)\];/s', $src, $m);

    expect(substr_count($m[1], 'hue:'))->toBeGreaterThan(3, 'O protótipo perdeu `hue` por fase.');
    expect(substr_count($m[1], 'owner:'))->toBeGreaterThan(3, 'O protótipo perdeu `owner` por fase.');

    $back = file_get_contents(base_path('Modules/Forja/Services/ForjaQuadroService.php'));
    preg_match('/private const FASES\s*=\s*\[(.*?)\];/s', $back, $mb);

    // Documenta o estado ATUAL. Quando o backend passar a servir hue/owner, este
    // assert cai — e a queda é o sinal de que a tela pode ficar mais rica.
    expect(str_contains($mb[1], 'hue'))->toBeFalse(
        'O backend agora serve `hue` — ótimo: atualize este caso e leve a cor por fase pra tela.'
    );
});
