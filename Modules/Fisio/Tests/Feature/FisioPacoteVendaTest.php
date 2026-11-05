<?php

declare(strict_types=1);
// @covers-us US-FISIO-022
// Cobre UC-FPK-07..09 (Fisio/Pacientes/Pacote.casos.md).

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Entities\PacoteLancamento;
use Modules\Fisio\Observers\VendaDoPacoteObserver;
use Modules\Fisio\Services\Pacotes\SaldoDoPacote;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Venda do pacote pela venda do núcleo (US-FISIO-022). O Fisio liga a venda pelo id e nunca
 * grava valor; venda cancelada tira do saldo as sessões que sobraram. Tenant 98 contra 99
 * (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_packages', 'fisio_package_ledger', 'transactions', 'fin_titulos'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->pac = F::paciente($this->b98, $this->u98->id);
    $this->saldo = app(SaldoDoPacote::class);
});

/** Venda finalizada gravada direto no núcleo (sem observer). */
function fpvVenda(int $biz, int $contato, int $user, string $status = 'final'): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'sell', 'status' => $status, 'payment_status' => 'paid',
        'contact_id' => $contato, 'created_by' => $user, 'essentials_duration' => 0,
        'transaction_date' => now(), 'final_total' => '500.00', 'total_before_tax' => '500.00',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-FPK-07 · liga a venda finalizada do paciente ao pacote e não grava título', function () {
    F::como($this, $this->u98);
    $pacote = $this->saldo->abrir($this->pac, '10 sessões', 10, null, null, $this->u98->id);
    $venda = fpvVenda($this->b98, $this->pac->contact_id, $this->u98->id);
    $antes = DB::table('fin_titulos')->count();

    $opcoes = F::props($this, $this->u98, "/fisio/pacientes/{$this->pac->id}/pacote", 'Fisio/Pacientes/Pacote', 'vendas');
    expect(array_column($opcoes, 'id'))->toContain($venda);

    $this->post("/fisio/pacotes/{$pacote->id}/venda", ['transaction_id' => $venda])->assertRedirect()->assertSessionHasNoErrors();

    expect(Pacote::query()->findOrFail($pacote->id)->transaction_id)->toBe($venda)
        ->and(DB::table('fin_titulos')->count())->toBe($antes)
        ->and(array_column(F::props($this, $this->u98, "/fisio/pacientes/{$this->pac->id}/pacote", 'Fisio/Pacientes/Pacote', 'vendas'), 'id'))->not->toContain($venda);
});

it('UC-FPK-08 · recusa venda de outro paciente, de outra empresa, não finalizada ou já ligada', function () {
    F::como($this, $this->u98);
    $pacote = $this->saldo->abrir($this->pac, 'P', 4, null, null, $this->u98->id);
    $outroPaciente = F::paciente($this->b98, $this->u98->id);
    $daOutroPaciente = fpvVenda($this->b98, $outroPaciente->contact_id, $this->u98->id);
    $rascunho = fpvVenda($this->b98, $this->pac->contact_id, $this->u98->id, 'draft');
    $da99 = fpvVenda($this->b99, F::paciente($this->b99, $this->u99->id)->contact_id, $this->u99->id);

    foreach ([$daOutroPaciente, $rascunho, $da99] as $v) {
        $this->post("/fisio/pacotes/{$pacote->id}/venda", ['transaction_id' => $v])->assertSessionHasErrors('transaction_id');
    }
    expect(Pacote::query()->findOrFail($pacote->id)->transaction_id)->toBeNull();

    $boa = fpvVenda($this->b98, $this->pac->contact_id, $this->u98->id);
    $this->post("/fisio/pacotes/{$pacote->id}/venda", ['transaction_id' => $boa])->assertSessionHasNoErrors();
    $segundo = $this->saldo->abrir($this->pac, 'Q', 4, null, null, $this->u98->id);
    $this->post("/fisio/pacotes/{$segundo->id}/venda", ['transaction_id' => $boa])->assertSessionHasErrors('transaction_id');
    expect(Pacote::query()->findOrFail($segundo->id)->transaction_id)->toBeNull();
});

it('UC-FPK-09 · venda cancelada: sobra sai do saldo por estorno, pacote encerra, sessões usadas ficam', function () {
    F::como($this, $this->u98);
    $pacote = $this->saldo->abrir($this->pac, '10 sessões', 10, null, null, $this->u98->id);
    foreach (range(1, 3) as $n) {
        $this->saldo->consumir($pacote, $this->u98->id, "agendamento:{$n}");
    }
    $venda = fpvVenda($this->b98, $this->pac->contact_id, $this->u98->id);
    $this->saldo->ligarVenda($pacote, $venda);

    // Pelo evento real do model, como o cancelamento do núcleo faz (forceFill + save).
    Transaction::query()->findOrFail($venda)->forceFill(['status' => 'cancelled'])->save();

    $p = Pacote::query()->findOrFail($pacote->id);
    expect($p->status)->toBe(Pacote::STATUS_ENCERRADO)
        ->and($this->saldo->saldo($p))->toBe(0)
        ->and(PacoteLancamento::where('package_id', $p->id)->where('kind', 'debito')->count())->toBe(3)
        ->and((int) PacoteLancamento::where('package_id', $p->id)->where('ref', 'venda_cancelada:'.$venda)->value('sessions'))->toBe(-7);

    // Idempotente: o mesmo aviso de novo não lança outro estorno.
    app(VendaDoPacoteObserver::class)->deleted(Transaction::query()->findOrFail($venda));
    expect(PacoteLancamento::where('package_id', $p->id)->where('ref', 'venda_cancelada:'.$venda)->count())->toBe(1);
});
