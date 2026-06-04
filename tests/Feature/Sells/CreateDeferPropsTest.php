<?php

declare(strict_types=1);

/**
 * Pest test estrutural - SellController@create deve usar Inertia::defer() nas
 * 8 props pesadas do payload Inertia 'Sells/Create' (paridade com Show/Edit/Drafts).
 *
 * Feature "defer" da tela de VENDA (Create):
 *   As 8 props pesadas - taxes, priceGroups, commissionAgents, customerGroups,
 *   accounts, typesOfService, users, invoiceSchemes - hoje vao EAGER no
 *   Inertia::render('Sells/Create', [...]). Show/Edit/Drafts ja usam defer
 *   (ver SellController.php linhas ~2482/2926/2987/3038); Create NAO.
 *
 *   Estado-alvo (o "pronto"): cada uma dessas props vira
 *     'prop' => Inertia::defer(fn () => ...),
 *   pra nao bloquear o render inicial / pular execucao em partial reload.
 *   Refs: skill `inertia-defer-default` (Tier B), RUNBOOK-inertia-defer-pattern.md,
 *   .claude/rules/pages.md, ADR 0093 (business_id scope preservado nas closures).
 *
 * TESTE ESTRUTURAL (mesmo estilo de SaleSheetComponentTest +
 * CustomerAutoApplyOnSelectTest): le o source PHP do controller com
 * file_get_contents e assere via toContain/toMatch.
 *
 * TEST-FIRST: enquanto a feature NAO foi implementada, os it() de defer
 * ficam VERMELHOS (as props ainda estao eager). Passam quando o create()
 * envolver as 8 props em Inertia::defer(fn () => ...).
 *
 * biz=1 dogfooding (ADR 0101) - nao toca dados de cliente; e leitura de source.
 */

const SELL_CONTROLLER_PATH_DEFER = 'app/Http/Controllers/SellController.php';

/**
 * Recorta o corpo do bloco `Inertia::render('Sells/Create', [...])` dentro de
 * create() pra escopar as assercoes so ao payload da tela de venda
 * (evita falso-positivo de defer de OUTRA action - Show/Edit/Drafts ja usam).
 */
function readCreatePayloadDefer(): string
{
    $src = file_get_contents(base_path(SELL_CONTROLLER_PATH_DEFER));

    // Inicio do metodo create()
    $createPos = strpos($src, 'public function create()');
    expect($createPos)->not->toBeFalse('metodo create() deve existir no SellController');

    // Dentro de create(), achar o render do payload Inertia 'Sells/Create'.
    $renderPos = strpos($src, "Inertia::render('Sells/Create'", $createPos);
    expect($renderPos)->not->toBeFalse("create() deve renderizar Inertia 'Sells/Create'");

    // Recorta um bloco generoso a partir do render (cobre as ~35 linhas do array de props).
    return substr($src, $renderPos, 2500);
}

it('defer - create() existe e renderiza o payload Inertia Sells/Create', function () {
    $src = file_get_contents(base_path(SELL_CONTROLLER_PATH_DEFER));
    expect($src)->toContain('public function create()');
    expect($src)->toContain("Inertia::render('Sells/Create'");
});

// As 8 props pesadas devem usar Inertia::defer(fn () => ...)
// Hoje VERMELHO: cada prop esta eager (ex.: 'priceGroups' => $price_groups).
// Pronto: 'priceGroups' => Inertia::defer(fn () => $price_groups),

it('defer - taxes usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'taxes'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

it('defer - priceGroups usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'priceGroups'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

it('defer - commissionAgents usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'commissionAgents'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

it('defer - customerGroups usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'customerGroups'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

it('defer - accounts usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'accounts'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

it('defer - typesOfService usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'typesOfService'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

it('defer - users usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'users'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

it('defer - invoiceSchemes usa Inertia::defer (nao eager)', function () {
    $body = readCreatePayloadDefer();
    expect($body)->toMatch("/'invoiceSchemes'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/");
});

// Cobertura agregada: as 8 props de uma vez (trava o criterio de PRONTO)

it('defer - todas as 8 props pesadas usam Inertia::defer no payload Create', function () {
    $body = readCreatePayloadDefer();

    $heavyProps = [
        'taxes',
        'priceGroups',
        'commissionAgents',
        'customerGroups',
        'accounts',
        'typesOfService',
        'users',
        'invoiceSchemes',
    ];

    foreach ($heavyProps as $prop) {
        expect($body)->toMatch(
            "/'{$prop}'\\s*=>\\s*(\\\\?Inertia\\\\)?Inertia::defer\\(\\s*fn\\s*\\(\\)\\s*=>/",
            "prop '{$prop}' deve usar Inertia::defer(fn () => ...) no payload Sells/Create"
        );
    }
});

// Anti-regressao: nenhuma das 8 props pode voltar pro modo eager direto.
// Trava o estado-alvo: depois de implementado, atribuicao eager dessas props
// (ex.: 'users' => $users,) reabre a regressao de performance.

it('defer - anti-regressao: props pesadas nao sao atribuidas eager direto', function () {
    $body = readCreatePayloadDefer();

    // Padroes eager exatos que existem HOJE (devem sumir quando virar defer).
    expect($body)->not->toMatch("/'priceGroups'\\s*=>\\s*\\\$price_groups\\s*,/");
    expect($body)->not->toMatch("/'commissionAgents'\\s*=>\\s*\\\$commission_agent\\s*,/");
    expect($body)->not->toMatch("/'customerGroups'\\s*=>\\s*\\\$customer_groups\\s*,/");
    expect($body)->not->toMatch("/'accounts'\\s*=>\\s*\\\$accounts\\s*,/");
    expect($body)->not->toMatch("/'typesOfService'\\s*=>\\s*\\\$types_of_service\\s*,/");
    expect($body)->not->toMatch("/'users'\\s*=>\\s*\\\$users\\s*,/");
    expect($body)->not->toMatch("/'invoiceSchemes'\\s*=>\\s*\\\$invoice_schemes\\s*,/");
});
