<?php

declare(strict_types=1);

/**
 * Pest test estrutural — guard contra transaction_date drift +2h47 (R9 2026-05-28).
 *
 * Bug catalogado em session log 2026-05-27 (sub-bug A):
 *   Larissa salvou venda 18:00 → DB gravou transaction_date=20:47.
 *   Drift +2h47 = tempo que ficou na tela entre abrir Create e submeter.
 *
 * Root cause: input chega vazio no POST → SellPosController:435 fallback
 *   `\Carbon::now()` sobrescreve com hora do MOMENTO do submit.
 *
 *   Cenários que produzem input vazio:
 *   - User explicitly limpa o `<input type="datetime-local">` (Backspace)
 *   - toDatetimeLocal() regex falha em formatos AM/PM (time_format=12)
 *   - State perde o value durante navegação entre sub-views
 *
 * Solução R9 (3 camadas):
 *   1. Frontend pre-submit: validar via regex /^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}/
 *      antes do POST. Se inválido, re-aplica defaultDatetime + console.warn.
 *   2. Frontend transform: defesa em profundidade — re-fallback no payload final.
 *   3. Backend logging: SellPosController@store agora loga WARNING quando
 *      fallback dispara, com payload info pra rastreabilidade.
 */

const SELL_POS_CONTROLLER = 'app/Http/Controllers/SellPosController.php';
const CREATE_PAGE_R9 = 'resources/js/Pages/Sells/Create.tsx';

function readControllerR9(): string
{
    return file_get_contents(base_path(SELL_POS_CONTROLLER));
}

function readPageR9(): string
{
    return file_get_contents(base_path(CREATE_PAGE_R9));
}

// === Frontend pre-submit guard ===

it('R9 — handleSubmit valida transaction_date antes do POST', function () {
    $src = readPageR9();
    $start = strpos($src, 'const handleSubmit');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 2500);
    expect($body)->toContain('TX_DATE_RE');
    expect($body)->toMatch('/\/\^\\\\d\{2\}\\\\\/\\\\d\{2\}\\\\\/\\\\d\{4\}\\\\s\+\\\\d\{2\}:\\\\d\{2\}/');
});

it('R9 — handleSubmit chama console.warn quando transaction_date inválido', function () {
    $src = readPageR9();
    $start = strpos($src, 'const handleSubmit');
    $body = substr($src, $start, 2500);
    expect($body)->toContain('console.warn');
    expect($body)->toContain('transaction_date inválido');
});

it('R9 — handleSubmit re-aplica props.defaultDatetime no setData', function () {
    $src = readPageR9();
    $start = strpos($src, 'const handleSubmit');
    $body = substr($src, $start, 2500);
    expect($body)->toContain("setData('transaction_date', props.defaultDatetime)");
});

it('R9 — transform tem fallback transaction_date (defesa em profundidade)', function () {
    $src = readPageR9();
    $start = strpos($src, 'const handleSubmit');
    $body = substr($src, $start, 2500);
    // Padrão esperado: transaction_date: d.transaction_date && TX_DATE_RE.test(d.transaction_date) ? d.transaction_date : props.defaultDatetime
    expect($body)->toMatch('/transaction_date:\s*d\.transaction_date\s*&&\s*TX_DATE_RE\.test/');
});

// === Backend defensive logging ===

it('R9 — SellPosController loga WARNING quando transaction_date vazio (fallback Carbon::now())', function () {
    $src = readControllerR9();
    // Padrão esperado: \Log::warning(...transaction_date vazio...)
    expect($src)->toContain('SellPosController@store transaction_date vazio');
    expect($src)->toMatch('/\\\\Log::warning\(/');
});

it('R9 — log warning inclui business_id, user_id, raw_value (rastreabilidade)', function () {
    $src = readControllerR9();
    $start = strpos($src, 'SellPosController@store transaction_date vazio');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 600);
    expect($body)->toContain("'business_id'");
    expect($body)->toContain("'user_id'");
    expect($body)->toContain("'raw_value'");
    expect($body)->toContain('has_payload_key');
});

it('R9 — fallback Carbon::now() ainda funciona (backwards compatible)', function () {
    $src = readControllerR9();
    // Não removemos o fallback Carbon::now() — só adicionamos log antes.
    // Vendas legacy/Blade que não enviam transaction_date continuam funcionando.
    expect($src)->toContain("\$input['transaction_date'] = \\Carbon::now();");
});
