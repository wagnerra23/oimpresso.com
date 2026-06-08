<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Sells/Create configure-search popover (R10 2026-05-28).
 *
 * Dor 3 do audit 2026-05-27 (parte ainda aberta — outra parte foi feita no R5):
 *   "Sem busca por código de barras / lote / custom fields (regressão
 *    configure-search modal Blade)"
 *
 *   Larissa @ Rota Livre não conseguia ativar busca por product_custom_field3
 *   (referência interna fornecedor). V2 mandava só `name+sku+lot` hardcoded.
 *   Paridade Blade: configure_search_modal.blade.php tinha 7 checkboxes
 *   (name, sku, lot, custom_field1-4) persistido em localStorage do user.
 *
 * Solução R10 (3 camadas):
 *   1. Constantes — ALL_SEARCH_FIELDS (7 entries) + SEARCH_FIELDS_STORAGE_KEY
 *      com prefixo canon `oimpresso.` (preference_persistent_layouts).
 *   2. State — useState<string[]>(() => loadSearchFields()) + toggle helper
 *      + saveSearchFields(safeArray) com failsafe (nunca vazio).
 *   3. UI — Popover trigger ícone SlidersHorizontal sempre visível ao lado
 *      do botão X, com 7 checkboxes que persistem no toggle.
 *   4. Integração — useEffect debounce + fetchProductsNow agora usam o state
 *      `searchFields` ao invés do hardcoded DEFAULT_SEARCH_FIELDS. Dep array
 *      do useEffect inclui searchFields pra re-busca imediata ao alternar.
 */

const COMP_PATH_R10 = 'resources/js/Pages/Sells/_components/ProductSearchAutocomplete.tsx';

function readCompR10(): string
{
    return file_get_contents(base_path(COMP_PATH_R10));
}

it('R10 — ALL_SEARCH_FIELDS define 7 fields (name + sku + lot + 4 custom_field)', function () {
    $src = readCompR10();
    expect($src)->toContain('ALL_SEARCH_FIELDS');
    foreach (['name', 'sku', 'lot', 'product_custom_field1', 'product_custom_field2', 'product_custom_field3', 'product_custom_field4'] as $key) {
        expect($src)->toContain("'$key'");
    }
});

it('R10 — SEARCH_FIELDS_STORAGE_KEY usa prefixo canon oimpresso.', function () {
    $src = readCompR10();
    expect($src)->toContain('SEARCH_FIELDS_STORAGE_KEY');
    expect($src)->toMatch("/SEARCH_FIELDS_STORAGE_KEY\s*=\s*'oimpresso\\./");
});

it('R10 — loadSearchFields tem failsafe contra localStorage corrompido (JSON.parse)', function () {
    $src = readCompR10();
    expect($src)->toContain('function loadSearchFields()');
    // Try/catch + filtros pra string + validKeys + failsafe vazio
    expect($src)->toContain('JSON.parse');
    expect($src)->toContain('Array.isArray(parsed)');
    expect($src)->toContain('validKeys.has');
});

it('R10 — loadSearchFields retorna defaults se localStorage vazio/inválido', function () {
    $src = readCompR10();
    $start = strpos($src, 'function loadSearchFields');
    $body = substr($src, $start, 700);
    // Deve cair em DEFAULT_SEARCH_FIELDS em ≥2 paths (no raw, no parse fail, no validKeys fail)
    expect(substr_count($body, 'DEFAULT_SEARCH_FIELDS'))->toBeGreaterThanOrEqual(2);
});

it('R10 — saveSearchFields envolto em try/catch (quota / private mode)', function () {
    $src = readCompR10();
    expect($src)->toContain('function saveSearchFields');
    $start = strpos($src, 'function saveSearchFields');
    $body = substr($src, $start, 400);
    expect($body)->toMatch('/try\s*\{/');
    expect($body)->toMatch('/catch\s*\{/');
    expect($body)->toContain('localStorage.setItem');
});

it('R10 — toggleSearchField tem failsafe (nunca permite array vazio)', function () {
    $src = readCompR10();
    $start = strpos($src, 'const toggleSearchField');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 600);
    // Failsafe: next.length > 0 ? next : ['name']
    expect($body)->toMatch("/next\\.length\\s*>\\s*0\\s*\\?\\s*next\\s*:\\s*\\['name'\\]/");
    expect($body)->toContain('saveSearchFields(safe)');
});

it('R10 — useEffect debounce inclui searchFields no dep array (re-fetch on toggle)', function () {
    $src = readCompR10();
    expect($src)->toMatch('/\}, \[query, locationId, searchFields\]\)/');
});

it('R10 — fetchProductsNow usa searchFields state (não DEFAULT_SEARCH_FIELDS hardcoded)', function () {
    $src = readCompR10();
    $start = strpos($src, 'const fetchProductsNow');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 600);
    expect($body)->toContain('searchFields.forEach');
});

it('R10 — JSX renderiza Popover com 7 checkboxes mapeados de ALL_SEARCH_FIELDS', function () {
    $src = readCompR10();
    expect($src)->toContain('configure-search-trigger');
    expect($src)->toContain('ALL_SEARCH_FIELDS.map');
    expect($src)->toContain('search-field-');
    expect($src)->toContain('toggleSearchField');
});

it('R10 — trigger usa ícone SlidersHorizontal acessível (aria-label)', function () {
    $src = readCompR10();
    expect($src)->toContain('SlidersHorizontal');
    expect($src)->toContain('aria-label="Configurar campos de busca"');
});

it('R10 — input padding ajustado pr-16 pra acomodar trigger configure + X', function () {
    $src = readCompR10();
    expect($src)->toContain('pl-9 pr-16');
});

it('R10 — loading spinner e X reposicionados pra right-10 (libera right-3 pro configure)', function () {
    $src = readCompR10();
    // Loading spinner Loader2 right-10
    expect($src)->toMatch('/Loader2[^}]*right-10/');
});
