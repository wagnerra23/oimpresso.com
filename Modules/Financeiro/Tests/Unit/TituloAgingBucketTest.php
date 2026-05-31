<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Financeiro\Models\Titulo;

uses(Tests\TestCase::class);

/**
 * Bug Carbon 3 — Titulo::agingBucket() colapsava TODO título atrasado em '<30'.
 *
 * Em Carbon 3 `diffInDays()` é SINALIZADO por default. Para um vencimento no
 * passado (todo título vencido — único caminho que chega no `match` depois do
 * guard `isVencido()`), `now()->diffInDays($vencimento)` retornava NEGATIVO,
 * então `$dias < 30` era sempre verdadeiro e os buckets 30-60 / 60-90 / 90-180
 * / >180 ficavam mortos.
 *
 * Fix: flag absoluto — `now()->diffInDays($vencimento, true)` (mesma convenção
 * de NfeHealthCommand). Os casos de 45/75/120/250 dias abaixo FALHAM no código
 * bugado (todos davam '<30') e provam que cada bucket voltou a viver.
 *
 * Unit puro, sem DB: `agingBucket()` é lógica sobre `vencimento` + `status` num
 * modelo em memória — `BusinessScope` só atua em query/save, não na construção.
 * Tempo congelado pra diffs determinísticos (fronteiras viram inteiros exatos).
 *
 * Refs: auditoria Método 9.75 Financeiro (2026-05-31).
 */

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-05-31 00:00:00')));
afterEach(fn () => Carbon::setTestNow());

function tituloVencidoHa(int $dias, string $status = 'aberto'): Titulo
{
    // Modelo NÃO persistido — nenhuma query roda, então multi-tenant/scope não entra em jogo.
    return new Titulo([
        'status'     => $status,
        'vencimento' => now()->subDays($dias),
    ]);
}

dataset('buckets vivos', [
    'vencido há 5 dias  → <30'    => [5,   '<30'],
    'vencido há 45 dias → 30-60'  => [45,  '30-60'],
    'vencido há 75 dias → 60-90'  => [75,  '60-90'],
    'vencido há 120 dias → 90-180' => [120, '90-180'],
    'vencido há 250 dias → >180'  => [250, '>180'],
]);

it('classifica o título vencido no bucket de aging correto', function (int $dias, string $esperado) {
    expect(tituloVencidoHa($dias)->agingBucket())->toBe($esperado);
})->with('buckets vivos');

dataset('fronteiras semi-abertas', [
    // O limite inferior pertence ao bucket de cima ($dias < N).
    '29 dias  → <30'    => [29,  '<30'],
    '30 dias  → 30-60'  => [30,  '30-60'],
    '60 dias  → 60-90'  => [60,  '60-90'],
    '90 dias  → 90-180' => [90,  '90-180'],
    '180 dias → >180'   => [180, '>180'],
]);

it('respeita a fronteira semi-aberta de cada bucket', function (int $dias, string $esperado) {
    expect(tituloVencidoHa($dias)->agingBucket())->toBe($esperado);
})->with('fronteiras semi-abertas');

it('retorna em_dia quando o título ainda não venceu', function () {
    $titulo = new Titulo(['status' => 'aberto', 'vencimento' => now()->addDays(10)]);

    expect($titulo->agingBucket())->toBe('em_dia');
});

it('retorna em_dia para título quitado, mesmo vencido no passado (guard de status)', function () {
    expect(tituloVencidoHa(100, 'quitado')->agingBucket())->toBe('em_dia');
});

it('retorna em_dia para título cancelado, mesmo vencido no passado', function () {
    expect(tituloVencidoHa(100, 'cancelado')->agingBucket())->toBe('em_dia');
});
