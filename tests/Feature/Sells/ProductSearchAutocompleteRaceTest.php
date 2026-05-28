<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Sells/Create.tsx ProductSearchAutocomplete (R7 2026-05-28).
 *
 * Fixa o BUG SCANNER DUPLICAÇÃO catalogado pós-PR #1778 R6:
 *
 *   Cenário: Larissa @ Rota Livre bipa código de barras. Item é incluído (path
 *   scanner sync), mas um useEffect debounce agendado ANTES do Enter resolve
 *   tardiamente e reabre o dropdown via setResults+setOpen(true). Larissa vê
 *   item "fantasma" no dropdown, clica achando que não foi adicionado, e
 *   duplica a linha (qty incrementa).
 *
 *   Root cause: clearTimeout não cancela Promise em flight. Sem AbortController
 *   nem token de staleness, o fetch resolve órfão.
 *
 * Solução R7 (3 camadas):
 *   1. AbortController + signal no fetch + cleanup chama controller.abort()
 *   2. lastSelectedAtRef + POST_SELECT_GRACE_MS — guards pre/pós-await ignoram
 *      setResults+setOpen se uma seleção rolou nos últimos 500ms
 *   3. Guard `if (loading) return` no Enter handler — bloqueia 2º Enter rápido
 *      enquanto scanner-path fetch ainda em flight
 *
 * Smoke comportamental validado em prod biz=4 via Chrome MCP javascript_tool —
 * 13 KeyboardEvents + Enter em <50ms (paridade scanner USB físico).
 */

const COMPONENT_PATH = 'resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx';

function readComponent(): string
{
    return file_get_contents(base_path(COMPONENT_PATH));
}

it('R7 — constante POST_SELECT_GRACE_MS definida (sentinela anti-race)', function () {
    $src = readComponent();
    expect($src)->toContain('POST_SELECT_GRACE_MS');
    expect($src)->toMatch('/const\s+POST_SELECT_GRACE_MS\s*=\s*\d+/');
});

it('R7 — lastSelectedAtRef declarado como useRef<number>', function () {
    $src = readComponent();
    expect($src)->toContain('lastSelectedAtRef');
    expect($src)->toMatch('/const\s+lastSelectedAtRef\s*=\s*useRef\(/');
});

it('R7 — useEffect debounce usa AbortController + signal', function () {
    $src = readComponent();
    expect($src)->toContain('new AbortController()');
    expect($src)->toContain('signal: controller.signal');
});

it('R7 — cleanup do useEffect chama controller.abort()', function () {
    $src = readComponent();
    expect($src)->toContain('controller.abort()');
});

it('R7 — guards pós-await checam signal.aborted antes de setState', function () {
    $src = readComponent();
    // Pelo menos 2 ocorrências esperadas (após fetch, após .json())
    expect(substr_count($src, 'controller.signal.aborted'))->toBeGreaterThanOrEqual(2);
});

it('R7 — guards pré e pós-await checam POST_SELECT_GRACE_MS sentinela', function () {
    $src = readComponent();
    // Padrão esperado: `Date.now() - lastSelectedAtRef.current < POST_SELECT_GRACE_MS`
    expect($src)->toMatch('/Date\.now\(\)\s*-\s*lastSelectedAtRef\.current\s*<\s*POST_SELECT_GRACE_MS/');
    // Pelo menos 3 ocorrências: pré-setLoading, pós-fetch, pós-.json()
    expect(substr_count($src, 'POST_SELECT_GRACE_MS'))->toBeGreaterThanOrEqual(3);
});

it('R7 — handleSelectVariation marca lastSelectedAtRef.current = Date.now() ANTES do onSelect', function () {
    $src = readComponent();
    // Captura bloco da função handleSelectVariation
    $start = strpos($src, 'const handleSelectVariation');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 400);

    // Ordem importa: timestamp ANTES do onSelect (que pode triggerar re-render
    // do parent + ciclos React que disparam outros fetches).
    $posTimestamp = strpos($body, 'lastSelectedAtRef.current = Date.now()');
    $posOnSelect = strpos($body, 'onSelect(');
    expect($posTimestamp)->not->toBeFalse();
    expect($posOnSelect)->not->toBeFalse();
    expect($posTimestamp)->toBeLessThan($posOnSelect);
});

it('R7 — handleClear também marca lastSelectedAtRef (X button no input)', function () {
    $src = readComponent();
    $start = strpos($src, 'const handleClear');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 300);
    expect($body)->toContain('lastSelectedAtRef.current = Date.now()');
});

it('R7 — Enter handler bloqueia se loading=true (anti-double-submit operadora)', function () {
    $src = readComponent();
    // Captura o bloco do Enter
    $pos = strpos($src, "e.key === 'Enter'");
    expect($pos)->not->toBeFalse();
    $body = substr($src, $pos, 800);
    expect($body)->toMatch('/if\s*\(\s*loading\s*\)\s*return\s*;/');
});

it('R7 — catch do useEffect ignora AbortError silenciosamente (não polui console)', function () {
    $src = readComponent();
    expect($src)->toMatch("/AbortError/");
});

it('R7 — finally do useEffect não chama setLoading(false) se aborted (evita race com fetch novo)', function () {
    $src = readComponent();
    expect($src)->toMatch('/if\s*\(\s*!\s*controller\.signal\.aborted\s*\)\s*setLoading\(\s*false\s*\)/');
});
