<?php

declare(strict_types=1);
// @covers-us US-FISIO-020

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Entities\PacoteLancamento;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Exceptions\PacoteIndisponivelException;
use Modules\Fisio\Services\Pacotes\SaldoDoPacote;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Pacote de sessões (US-FISIO-020, etapa F3 §3.4.2): saldo derivado do ledger append-only,
 * sem coluna de dinheiro. Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite: schema
 * UltimatePOS + triggers MySQL — leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_packages', 'fisio_package_ledger'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->saldo = new SaldoDoPacote();
});

it('o pacote não tem coluna de dinheiro: preço é da venda do núcleo', function () {
    $colunas = collect(Schema::getColumns('fisio_packages'))->pluck('name')->all();

    expect(array_values(array_filter($colunas, fn ($c) => preg_match('/price|valor|amount|total_value|preco/i', $c))))->toBe([])
        ->and(in_array('transaction_id', $colunas, true))->toBeTrue();
});

it('saldo é a soma do ledger: abre com 10, consome 6, sobra 4', function () {
    F::como($this, $this->u98);
    $pac = F::paciente($this->b98, $this->u98->id);
    $pacote = $this->saldo->abrir($pac, '10 sessões · 2x por semana', 10, now()->addDays(90)->toDateString(), 2, $this->u98->id);

    foreach (range(1, 6) as $n) {
        $this->saldo->consumir($pacote, $this->u98->id, "agendamento:{$n}");
    }

    expect($this->saldo->saldo($pacote))->toBe(4)
        ->and((int) PacoteLancamento::where('package_id', $pacote->id)->sum('sessions'))->toBe(4)
        ->and(PacoteLancamento::where('package_id', $pacote->id)->where('kind', 'debito')->count())->toBe(6);
});

it('mesmo atendimento não debita duas vezes; depois do estorno pode debitar de novo', function () {
    F::como($this, $this->u98);
    $pacote = $this->saldo->abrir(F::paciente($this->b98, $this->u98->id), 'Pacote 3', 3, null, null, $this->u98->id);

    $d1 = $this->saldo->consumir($pacote, $this->u98->id, 'agendamento:7');
    $d2 = $this->saldo->consumir($pacote, $this->u98->id, 'agendamento:7');
    expect($d2->id)->toBe($d1->id)->and($this->saldo->saldo($pacote))->toBe(2);

    $this->saldo->estornar($d1, $this->u98->id, 'falta justificada');
    expect($this->saldo->saldo($pacote))->toBe(3);
    expect(fn () => $this->saldo->estornar($d1, $this->u98->id, 'de novo'))->toThrow(PacoteIndisponivelException::class);

    $d3 = $this->saldo->consumir($pacote, $this->u98->id, 'agendamento:7');
    expect($d3->id)->not->toBe($d1->id)->and($this->saldo->saldo($pacote))->toBe(2);
});

it('sem saldo, vencido ou encerrado: débito recusado e nada é lançado', function () {
    F::como($this, $this->u98);
    $pac = F::paciente($this->b98, $this->u98->id);

    $um = $this->saldo->abrir($pac, 'Avulsa', 1, null, null, $this->u98->id);
    $this->saldo->consumir($um, $this->u98->id);
    expect(fn () => $this->saldo->consumir($um, $this->u98->id))->toThrow(PacoteIndisponivelException::class)
        ->and($this->saldo->saldo($um))->toBe(0);

    $vencido = $this->saldo->abrir($pac, 'Vencido', 5, now()->subDay()->toDateString(), null, $this->u98->id);
    expect(fn () => $this->saldo->consumir($vencido, $this->u98->id))->toThrow(PacoteIndisponivelException::class);

    $this->saldo->estender($vencido, now()->addDays(30)->toDateString(), $this->u98->id, 'paciente operou');
    expect($this->saldo->consumir($vencido, $this->u98->id)->kind)->toBe('debito')
        ->and($this->saldo->saldo($vencido))->toBe(4)
        ->and(PacoteLancamento::where('package_id', $vencido->id)->where('kind', 'extensao')->count())->toBe(1);

    $encerrado = $this->saldo->abrir($pac, 'Encerrado', 5, null, null, $this->u98->id);
    $encerrado->update(['status' => Pacote::STATUS_ENCERRADO]);
    expect(fn () => $this->saldo->consumir($encerrado, $this->u98->id))->toThrow(PacoteIndisponivelException::class)
        ->and($this->saldo->saldo($encerrado))->toBe(5);
});

it('ledger é append-only: UPDATE e DELETE falham no banco e no Eloquent', function () {
    F::como($this, $this->u98);
    $pacote = $this->saldo->abrir(F::paciente($this->b98, $this->u98->id), 'P', 2, null, null, $this->u98->id);
    $l = PacoteLancamento::where('package_id', $pacote->id)->firstOrFail();

    expect(fn () => DB::table('fisio_package_ledger')->where('id', $l->id)->update(['sessions' => 99]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_package_ledger')->where('id', $l->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => $l->update(['sessions' => 99]))->toThrow(DadoClinicoImutavelException::class)
        ->and(fn () => $l->delete())->toThrow(DadoClinicoImutavelException::class)
        ->and(fn () => DB::table('fisio_package_ledger')->insert([
            'business_id' => $this->b98, 'package_id' => $pacote->id, 'kind' => 'bonus', 'sessions' => 5, 'created_by' => $this->u98->id,
        ]))->toThrow(QueryException::class)
        ->and($this->saldo->saldo($pacote))->toBe(2);
});

it('98 contra 99: pacote e lançamentos da 99 não aparecem para a 98, e o débito usa o escopo', function () {
    F::como($this, $this->u99);
    $pac99 = $this->saldo->abrir(F::paciente($this->b99, $this->u99->id), 'Da 99', 4, null, null, $this->u99->id);

    F::como($this, $this->u98);
    $pac98 = $this->saldo->abrir(F::paciente($this->b98, $this->u98->id), 'Da 98', 4, null, null, $this->u98->id);

    expect(Pacote::query()->find($pac99->id))->toBeNull()
        ->and(Pacote::query()->find($pac98->id))->not->toBeNull()
        ->and(PacoteLancamento::query()->where('package_id', $pac99->id)->count())->toBe(0)
        ->and(fn () => $this->saldo->consumir($pac99, $this->u98->id))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // Controle negativo: sem escopo o pacote da 99 existe e ficou com o saldo intacto.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect((int) PacoteLancamento::withoutGlobalScopes()->where('package_id', $pac99->id)->sum('sessions'))->toBe(4);
});
