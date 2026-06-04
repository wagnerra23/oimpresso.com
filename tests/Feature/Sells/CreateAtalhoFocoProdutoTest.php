<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Sells/Create.tsx · feature "atalho-foco" (US-SELL-007).
 *
 * Critério de PRONTO desta feature (trava test-first):
 *   1. O atalho `/` (top-level) foca a busca de produto.
 *      - Existe listener de tecla `/` (keydown) que aciona o foco da busca.
 *      - O foco usa a função/lógica já existente `focusProductSearch`, que
 *        busca o input[type="search"] dentro do productSearchRef.
 *   2. O empty state NÃO diz mais "(em breve)" — o débito de UX foi quitado.
 *
 * Estado antes da feature (VERMELHO): não havia listener de `/` e o empty
 * state ainda exibia "(em breve)". Quando a feature existe, todos os it()
 * passam. Estilo estrutural igual a SaleSheetComponentTest /
 * CustomerAutoApplyOnSelectTest — lê o source via file_get_contents e usa
 * expect()->toContain()/->toMatch() (sem renderizar React).
 *
 * Refs: charter Sells/Create §UX Targets · ADR 0101 (biz=1 em smoke, não toca
 * Model aqui). Multi-tenant (ADR 0093) não se aplica — teste é puramente do
 * source da Page, sem query Eloquent.
 */

const CREATE_PATH_ATALHO = 'resources/js/Pages/Sells/Create.tsx';

function readCreateAtalho(): string
{
    return file_get_contents(base_path(CREATE_PATH_ATALHO));
}

it('atalho-foco — Create.tsx existe', function () {
    expect(file_exists(base_path(CREATE_PATH_ATALHO)))->toBeTrue();
});

// === Função de foco da busca (pré-requisito já presente) ===

it('atalho-foco — função focusProductSearch existe e foca o input de busca', function () {
    $src = readCreateAtalho();
    expect($src)->toContain('const focusProductSearch');
    // Foca o input[type="search"] dentro do container productSearchRef.
    $start = strpos($src, 'const focusProductSearch');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 400);
    expect($body)->toContain('productSearchRef');
    expect($body)->toContain('input[type="search"]');
    expect($body)->toContain('.focus()');
});

// === Listener da tecla "/" que foca a busca (núcleo da feature) ===

it('atalho-foco — existe listener keydown que reage à tecla "/"', function () {
    $src = readCreateAtalho();
    // Registra/remove o handler global de teclado.
    expect($src)->toContain("addEventListener('keydown'");
    // Há um guarda que detecta a tecla barra.
    expect($src)->toMatch('/e\.key\s*===\s*[\'"]\/[\'"]|e\.key\s*!==\s*[\'"]\/[\'"]/');
});

it('atalho-foco — o handler da tecla "/" foca a busca de produto', function () {
    $src = readCreateAtalho();
    // Localiza o bloco do handler do atalho `/` e confirma que ele aciona o
    // foco da busca (via focusProductSearch OU pela mesma lógica productSearchRef
    // + input[type="search"]).
    expect($src)->toMatch(
        '/e\.key\s*!==\s*[\'"]\/[\'"][\s\S]{0,800}?(focusProductSearch\(\)|input\[type="search"\][\s\S]{0,80}?\.focus\(\))/'
    );
});

it('atalho-foco — o handler de "/" chama preventDefault (não digita a barra no campo)', function () {
    $src = readCreateAtalho();
    // No bloco do atalho `/`, há preventDefault pra não vazar o caractere.
    expect($src)->toMatch('/e\.key\s*!==\s*[\'"]\/[\'"][\s\S]{0,600}?e\.preventDefault\(\)/');
});

it('atalho-foco — empty state aponta o atalho "/" pra focar a busca', function () {
    $src = readCreateAtalho();
    // O texto do empty state convida a apertar `/` pra focar.
    expect($src)->toMatch('/aperte\s*\/\s*pra focar|aperte\s*"\/"/i');
});

// === Débito de UX quitado: "(em breve)" não aparece mais ===

it('atalho-foco — texto "(em breve)" NÃO aparece no empty state (débito quitado)', function () {
    $src = readCreateAtalho();
    // Tolerância a espaços dentro dos parênteses, mas sempre o literal "(em breve)".
    expect($src)->not->toMatch('/\(\s*em breve\s*\)/i');
});
