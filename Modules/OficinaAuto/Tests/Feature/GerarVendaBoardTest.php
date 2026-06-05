<?php

declare(strict_types=1);

/**
 * GUARD do "Gerar venda" no board producao-oficina (fluxo real oficina→venda).
 *
 * Wagner 2026-06-05 (delegação explícita "faz o que achar melhor pra uma oficina,
 * decisão sua"): a venda nasce na oficina. Este guard ESTRUTURAL (lê source, sem DB
 * → roda no lane SQLite do CI) tranca os invariantes críticos de VALOR e fiação:
 *
 *  1. FaturarServiceOrderService é a ÚNICA fonte do valor e NÃO faz matemática nova
 *     — delega aos accessors testados valor_receber / total_items (regra-mestre).
 *  2. É idempotente (checa transaction_id + os_ref) — nunca dupla-fatura.
 *  3. O ServiceOrderObserver DELEGA ao serviço (não cria mais Transaction inline) —
 *     single source of truth entre auto-faturar e botão manual.
 *  4. O endpoint do board existe (gerarVenda + previewVenda) + rotas registradas.
 *
 * @see Modules/OficinaAuto/Services/FaturarServiceOrderService.php
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 */

uses(Tests\TestCase::class);

function svcSrc(): string
{
    return (string) file_get_contents(base_path('Modules/OficinaAuto/Services/FaturarServiceOrderService.php'));
}

function obsSrc(): string
{
    return (string) file_get_contents(base_path('Modules/OficinaAuto/Observers/ServiceOrderObserver.php'));
}

function ctrlSrc(): string
{
    return (string) file_get_contents(base_path('Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php'));
}

function routesSrc(): string
{
    return (string) file_get_contents(base_path('Modules/OficinaAuto/Routes/web.php'));
}

// ─── Valor: zero matemática nova (regra-mestre) ─────────────────────────────

it('o serviço deriva o valor SÓ dos accessors testados (valor_receber / total_items)', function () {
    $src = svcSrc();
    expect($src)->toContain('valor_receber');
    expect($src)->toContain('total_items');
    // Não pode reintroduzir parsing de número locale-ambíguo nem multiplicação crua
    // de preço aqui (a inflação 2026-06-05 veio de num_uf). Math fica nos accessors.
    expect($src)->not->toContain('num_uf');
    expect($src)->not->toContain('->daily_rate *'); // o × dias vive no accessor da entity
});

it('o serviço é idempotente (transaction_id + os_ref) — nunca dupla-fatura', function () {
    $src = svcSrc();
    expect($src)->toContain('transaction_id !== null');
    expect($src)->toContain("->where('os_ref'");
});

it('a venda derivada nasce com origem oficina', function () {
    $src = svcSrc();
    expect($src)->toContain("'source'           => 'oficina'");
});

// ─── Single source of truth: observer delega ────────────────────────────────

it('o ServiceOrderObserver delega ao FaturarServiceOrderService (não cria Transaction inline)', function () {
    $src = obsSrc();
    expect($src)->toContain('FaturarServiceOrderService');
    expect($src)->toContain('->faturar($so)');
    // O create inline foi extraído pro serviço — o observer não pode mais montar
    // a Transaction sozinho (evita drift de valor entre auto e manual).
    expect($src)->not->toContain('Transaction::create');
});

// ─── Endpoint + rotas do board ──────────────────────────────────────────────

it('o controller do board expõe gerarVenda + previewVenda (preview = mostrar valor antes)', function () {
    $src = ctrlSrc();
    expect($src)->toContain('public function gerarVenda(');
    expect($src)->toContain('public function previewVenda(');
    expect($src)->toContain('FaturarServiceOrderService');
});

it('as rotas gerar-venda + preview-venda estão registradas', function () {
    $src = routesSrc();
    expect($src)->toContain("'gerarVenda'");
    expect($src)->toContain("'previewVenda'");
    expect($src)->toContain('producao-oficina/ordens/{order}/gerar-venda');
});
