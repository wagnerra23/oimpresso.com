<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use App\Contracts\Crm\AgendaDeTarefas;
use Modules\Financeiro\Jobs\ReguaCobrancaJob;
use Modules\Financeiro\Mail\LembreteCobrancaMail;
use Modules\Financeiro\Services\ReguaCobrancaConfig;

uses(Tests\TestCase::class);

/**
 * US-FIN-071 · UC-FLX-29 — régua de cobrança do título vencido (D+1/D+5/D+15).
 * Contrato: resources/js/Pages/Financeiro/ContasReceber/Index.casos.md (UC-CRREG-01..08).
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
    expect(app()->bound(AgendaDeTarefas::class))->toBeTrue(); // Crm liga o contrato no ambiente de teste

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    $tarefaVenda = DB::table('crm_schedules')->find(reguaLembretes($comVenda)[0]->tarefa_id);
    expect((int) $tarefaVenda->business_id)->toBe($this->bizA);
    expect((int) $tarefaVenda->created_by)->toBe((int) $vendedor->id);
    expect((int) DB::table('crm_schedule_users')->where('schedule_id', $tarefaVenda->id)->value('user_id'))
        ->toBe((int) $vendedor->id);
    expect((int) DB::table('crm_followup_invoices')->where('follow_up_id', $tarefaVenda->id)->value('transaction_id'))
        ->toBe((int) $vendaId);

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

it('UC-CRREG-07 · sem o contrato de agenda ligado (Crm ausente) o lembrete sai e nenhuma tarefa é criada', function () {
    unset(app()[AgendaDeTarefas::class]);
    expect(app()->bound(AgendaDeTarefas::class))->toBeFalse();
    $antes = DB::table('crm_schedules')->where('business_id', $this->bizA)->count();
    $id = reguaTitulo($this->bizA, $this->ownerA, 15, reguaContato($this->bizA, $this->ownerA));

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    $l = reguaLembretes($id);
    expect($l)->toHaveCount(1);
    expect($l[0]->status)->toBe('enviado');
    expect($l[0]->tarefa_id)->toBeNull();
    expect(DB::table('crm_schedules')->where('business_id', $this->bizA)->count())->toBe($antes);
});

it('UC-CRREG-08 · o painel Lembretes da tela lista os do business, com o motivo, e só carrega ao abrir', function () {
    $enviado = reguaTitulo($this->bizA, $this->ownerA, 1, reguaContato($this->bizA, $this->ownerA));
    $optOut = reguaTitulo($this->bizA, $this->ownerA, 5, reguaContato($this->bizA, $this->ownerA, false));
    $deB = reguaTitulo($this->bizB, $this->ownerB, 1, reguaContato($this->bizB, $this->ownerB));
    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));
    (new ReguaCobrancaJob($this->bizB, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));
    // Pré-condição anti-vácuo: B tem lembrete, então a ausência dele no painel de A é filtro, não falta de dado.
    expect(reguaLembretes($deB))->toHaveCount(1);

    $user = $this->usuarioComPermissoes(['financeiro.contas_receber.view'], \App\Business::findOrFail($this->bizA));
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $base = ['X-Inertia' => 'true', 'X-Inertia-Version' => $versao, 'X-Requested-With' => 'XMLHttpRequest'];
    $chamar = fn (array $headers) => $this->actingAs($user)
        ->withSession(['user.business_id' => $this->bizA, 'user.id' => $user->id, 'business.id' => $this->bizA])
        ->withHeaders($headers)
        ->get('/financeiro/contas-receber');

    // Carga normal: a prop é opcional e não vem.
    $inicial = $chamar($base);
    expect($inicial->status())->toBe(200);
    expect($inicial->json('props'))->not->toHaveKey('lembretes');

    // Abrir o painel = reload parcial pedindo só `lembretes`.
    $painel = $chamar($base + ['X-Inertia-Partial-Component' => 'Financeiro/ContasReceber/Index', 'X-Inertia-Partial-Data' => 'lembretes']);
    expect($painel->status())->toBe(200);
    expect($painel->json('props.lembretes.regua'))->toBe(['ativo' => true, 'dias' => [1, 5, 15], 'dias_antes' => []]);

    $itens = collect($painel->json('props.lembretes.itens'))->keyBy('titulo_id');
    expect($itens->keys()->sort()->values()->all())->toBe(collect([$enviado, $optOut])->sort()->values()->all());
    expect($itens[$enviado]['status'])->toBe('enviado');
    expect($itens[$enviado]['etapa_dias'])->toBe(1);
    expect($itens[$optOut]['status'])->toBe('nao_enviado');
    expect($itens[$optOut]['motivo'])->toBe('opt_out_email');
    expect($itens[$optOut]['etapa_dias'])->toBe(5);
});

it('UC-CRREG-09 · aviso antes do vencimento só com dias_antes configurado, sem tarefa', function () {
    // Sem dias_antes (o padrão): título que vence em 3 dias não recebe nada.
    $semConfig = reguaTitulo($this->bizA, $this->ownerA, -3, reguaContato($this->bizA, $this->ownerA));
    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));
    expect(reguaLembretes($semConfig))->toHaveCount(0);

    app(ReguaCobrancaConfig::class)->definir($this->bizA, true, null, [3]);
    $vence3 = reguaTitulo($this->bizA, $this->ownerA, -3, reguaContato($this->bizA, $this->ownerA));
    $vence2 = reguaTitulo($this->bizA, $this->ownerA, -2, reguaContato($this->bizA, $this->ownerA));
    $antes = DB::table('crm_schedules')->where('business_id', $this->bizA)->count();

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    $l = reguaLembretes($vence3);
    expect($l)->toHaveCount(1);
    expect($l[0]->fase)->toBe('antes');
    expect((int) $l[0]->etapa_dias)->toBe(3);
    expect($l[0]->status)->toBe('enviado');
    expect($l[0]->tarefa_id)->toBeNull();
    expect(reguaLembretes($vence2))->toHaveCount(0); // D-2 não é etapa
    expect(DB::table('crm_schedules')->where('business_id', $this->bizA)->count())->toBe($antes);
    Mail::assertSent(LembreteCobrancaMail::class, fn ($m) => $m->tituloId === $vence3
        && $m->fase === 'antes' && str_contains($m->build()->subject, 'vence em 3 dia'));
});

it('UC-CRREG-10 · aviso D-3 e lembrete D+3 do mesmo título convivem (UNIQUE por fase)', function () {
    app(ReguaCobrancaConfig::class)->definir($this->bizA, true, [3], [3]);
    $titulo = reguaTitulo($this->bizA, $this->ownerA, -3, reguaContato($this->bizA, $this->ownerA));
    $depois = \Illuminate\Support\Carbon::parse(REGUA_HOJE)->addDays(6)->toDateString();

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));
    (new ReguaCobrancaJob($this->bizA, $depois))->handle(app(ReguaCobrancaConfig::class));
    (new ReguaCobrancaJob($this->bizA, $depois))->handle(app(ReguaCobrancaConfig::class)); // idempotente

    $fases = reguaLembretes($titulo)->map(fn ($l) => $l->fase.':'.$l->etapa_dias)->sort()->values()->all();
    expect($fases)->toBe(['antes:3', 'apos:3']);
    Mail::assertSent(LembreteCobrancaMail::class, 2);
});

function reguaCobranca(int $biz, string $origemType, int $origemId, string $status, array $over = []): int
{
    return DB::table('cobrancas')->insertGetId(array_merge([
        'business_id' => $biz, 'tipo' => 'pix_cobv', 'status' => $status, 'valor_centavos' => 10000,
        'vencimento' => REGUA_HOJE, 'contact_id' => 1, 'descricao' => 'Teste régua meio de pagar',
        'idempotency_key' => uniqid('regua', true), 'origem_type' => $origemType, 'origem_id' => $origemId,
        'created_at' => now(), 'updated_at' => now(),
    ], $over));
}

it('UC-CRREG-11 · a mensagem leva o meio de pagar da cobrança aberta do título, nunca de cobrança paga ou de outro business', function () {
    if (! Schema::hasTable('cobrancas')) {
        $this->markTestSkipped('cobrancas ausente — rode migrate.');
    }
    $comPix = reguaTitulo($this->bizA, $this->ownerA, 1, reguaContato($this->bizA, $this->ownerA));
    reguaCobranca($this->bizA, 'fin_titulo', $comPix, 'emitida', ['pix_emv' => 'PIX-ABERTO-A']);

    $soPaga = reguaTitulo($this->bizA, $this->ownerA, 1, reguaContato($this->bizA, $this->ownerA));
    reguaCobranca($this->bizA, 'fin_titulo', $soPaga, 'paga', ['pix_emv' => 'PIX-JA-PAGO']);
    // Cobrança de OUTRO business apontando para o mesmo id de título: nunca entra (Tier 0).
    reguaCobranca($this->bizB, 'fin_titulo', $soPaga, 'emitida', ['pix_emv' => 'PIX-DO-B']);

    $venda = 900000000 + random_int(1, 99999);
    $deVenda = reguaTitulo($this->bizA, $this->ownerA, 5, reguaContato($this->bizA, $this->ownerA),
        ['origem' => 'venda', 'origem_id' => $venda]);
    reguaCobranca($this->bizA, 'sale', $venda, 'vencida', ['tipo' => 'boleto', 'linha_digitavel' => '23790.00000 LINHA-DA-VENDA']);

    (new ReguaCobrancaJob($this->bizA, REGUA_HOJE))->handle(app(ReguaCobrancaConfig::class));

    $corpoDe = function (int $tituloId): string {
        $corpo = null;
        Mail::assertSent(LembreteCobrancaMail::class, function ($m) use ($tituloId, &$corpo) {
            if ($m->tituloId === $tituloId) {
                $corpo = $m->corpo;
            }

            return $m->tituloId === $tituloId;
        });

        return (string) $corpo;
    };

    expect($corpoDe($comPix))->toContain('Pix copia e cola: PIX-ABERTO-A');
    expect($corpoDe($soPaga))->not->toContain('PIX-')
        ->and($corpoDe($soPaga))->not->toContain('copia e cola');
    expect($corpoDe($deVenda))->toContain('Linha digitável do boleto: 23790.00000 LINHA-DA-VENDA');
});
