<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Jobs\ReguaCobrancaJob;
use Modules\Financeiro\Mail\LembreteCobrancaMail;
use Modules\Financeiro\Services\ReguaCobrancaConfig;

uses(Tests\TestCase::class);

/**
 * US-FIN-071 · UC-FLX-29 — régua de cobrança do título vencido (D+1/D+5/D+15).
 * Contrato: resources/js/Pages/Financeiro/ContasReceber/Index.casos.md (UC-CRREG-01..06).
 *
 * Tenant 98 (ADR 0358) e, como business B, o cliente fictício 99 (nunca o 4). Hoje fixo em
 * 2031-03-20 para os vencimentos não dependerem do relógio; tudo dentro de transação desfeita
 * no fim (`fin_titulos` não aceita delete). Nenhum valor do lembrete muda dinheiro: a régua só
 * comunica e registra.
 */

const REGUA_HOJE = '2031-03-20';

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Régua usa schema MySQL do Financeiro.');
    }
    if (! Schema::hasTable('fin_titulo_lembretes') || ! Schema::hasTable('crm_schedules')) {
        $this->markTestSkipped('fin_titulo_lembretes/crm_schedules ausentes — rode migrate.');
    }

    DB::beginTransaction();
    Mail::fake();

    $this->bizA = (int) $this->seededTenant()->id;
    $this->bizB = (int) $this->seededSupportClientTenant()->id;
    $this->ownerA = (int) DB::table('business')->where('id', $this->bizA)->value('owner_id');
    $this->ownerB = (int) DB::table('business')->where('id', $this->bizB)->value('owner_id');
    app(ReguaCobrancaConfig::class)->definir($this->bizA, true);
    app(ReguaCobrancaConfig::class)->definir($this->bizB, true);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function reguaContato(int $biz, int $owner, ?bool $emailConsent = null): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente régua '.uniqid(),
        'mobile' => '', 'email' => 'regua'.uniqid().'@example.test', 'email_consent' => $emailConsent,
        'contact_id' => 'RG-'.uniqid(), 'created_by' => $owner,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function reguaTitulo(int $biz, int $owner, int $diasVencido, ?int $clienteId, array $over = []): int
{
    $venc = \Illuminate\Support\Carbon::parse(REGUA_HOJE)->subDays($diasVencido)->toDateString();

    return DB::table('fin_titulos')->insertGetId(array_merge([
        'business_id' => $biz, 'numero' => 'RG'.substr(uniqid(), -10), 'tipo' => 'receber',
        'status' => 'aberto', 'cliente_id' => $clienteId, 'valor_total' => 100, 'valor_aberto' => 100,
        'moeda' => 'BRL', 'emissao' => $venc, 'vencimento' => $venc, 'competencia_mes' => substr($venc, 0, 7),
        'origem' => 'manual', 'created_by' => $owner, 'created_at' => now(), 'updated_at' => now(),
    ], $over));
}

function reguaLembretes(int $tituloId): \Illuminate\Support\Collection
{
    return DB::table('fin_titulo_lembretes')->where('titulo_id', $tituloId)->get();
}

it('UC-CRREG-01 · envia em D+1, D+5 e D+15 e não em D+2', function () {
    $ids = [];
    foreach ([1, 2, 5, 15] as $d) {
        $ids[$d] = reguaTitulo($this->bizA, $this->ownerA, $d, reguaContato($this->bizA, $this->ownerA));
    }

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    foreach ([1, 5, 15] as $d) {
        $l = reguaLembretes($ids[$d]);
        expect($l)->toHaveCount(1);
        expect((int) $l[0]->etapa_dias)->toBe($d);
        expect($l[0]->status)->toBe('enviado');
    }
    expect(reguaLembretes($ids[2]))->toHaveCount(0);
    Mail::assertSent(LembreteCobrancaMail::class, 3);
});

it('UC-CRREG-02 · rodar duas vezes no mesmo dia registra e envia um lembrete só', function () {
    $id = reguaTitulo($this->bizA, $this->ownerA, 5, reguaContato($this->bizA, $this->ownerA));

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));
    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    expect(reguaLembretes($id))->toHaveCount(1);
    Mail::assertSent(LembreteCobrancaMail::class, 1);
});

it('UC-CRREG-03 · contato com opt-out de e-mail não recebe e fica registrado com o motivo', function () {
    $id = reguaTitulo($this->bizA, $this->ownerA, 1, reguaContato($this->bizA, $this->ownerA, false));

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    $l = reguaLembretes($id);
    expect($l)->toHaveCount(1);
    expect($l[0]->status)->toBe('nao_enviado');
    expect($l[0]->motivo)->toBe('opt_out_email');
    Mail::assertNothingSent();
});

it('UC-CRREG-04 · na última etapa cria tarefa para o vendedor da venda; sem vendedor, para o dono', function () {
    $vendedor = $this->usuarioComPermissoes([]);
    $vendaId = DB::table('transactions')->insertGetId([
        'business_id' => $this->bizA, 'type' => 'sell', 'status' => 'final',
        'transaction_date' => now(), 'created_by' => $this->ownerA, 'commission_agent' => $vendedor->id,
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $comVenda = reguaTitulo($this->bizA, $this->ownerA, 15, reguaContato($this->bizA, $this->ownerA), [
        'origem' => 'venda', 'origem_id' => $vendaId,
    ]);
    $manual = reguaTitulo($this->bizA, $this->ownerA, 15, reguaContato($this->bizA, $this->ownerA));
    $d5 = reguaTitulo($this->bizA, $this->ownerA, 5, reguaContato($this->bizA, $this->ownerA));

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    $tarefaVenda = DB::table('crm_schedules')->find(reguaLembretes($comVenda)[0]->tarefa_id);
    expect((int) $tarefaVenda->business_id)->toBe($this->bizA);
    expect((int) $tarefaVenda->created_by)->toBe((int) $vendedor->id);
    expect((int) DB::table('crm_schedule_users')->where('schedule_id', $tarefaVenda->id)->value('user_id'))
        ->toBe((int) $vendedor->id);

    $tarefaManual = DB::table('crm_schedules')->find(reguaLembretes($manual)[0]->tarefa_id);
    expect((int) $tarefaManual->created_by)->toBe($this->ownerA);

    expect(reguaLembretes($d5)[0]->tarefa_id)->toBeNull();
});

it('UC-CRREG-05 · régua desligada (padrão) não registra nem envia nada', function () {
    DB::table('business')->where('id', $this->bizA)->update(['common_settings' => json_encode([])]);
    expect(app(ReguaCobrancaConfig::class)->de($this->bizA)['ativo'])->toBeFalse();
    $id = reguaTitulo($this->bizA, $this->ownerA, 1, reguaContato($this->bizA, $this->ownerA));

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    expect(reguaLembretes($id))->toHaveCount(0);
    Mail::assertNothingSent();
});

it('UC-CRREG-06 · o job de um business não toca títulos de outro business', function () {
    $idA = reguaTitulo($this->bizA, $this->ownerA, 1, reguaContato($this->bizA, $this->ownerA));
    $idB = reguaTitulo($this->bizB, $this->ownerB, 1, reguaContato($this->bizB, $this->ownerB));

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    expect(reguaLembretes($idA))->toHaveCount(1);
    expect(reguaLembretes($idB))->toHaveCount(0);
    Mail::assertSent(LembreteCobrancaMail::class, 1);
});
