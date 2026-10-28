<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Contracts\PagadorBanco;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\PagamentoBanco;
use Modules\Financeiro\Services\PagamentoBancoService;

uses(Tests\TestCase::class);

/**
 * US-FIN-079 — pagar título a pagar pelo banco (boleto e Pix), com aprovação e baixa só na
 * confirmação do banco. Contrato: resources/js/Pages/Financeiro/Unificado/Index.casos.md
 * (UC-PAGBAN-01..08). Banco SIMULADO (fake de `PagadorBanco`) — nenhuma chamada real.
 *
 * Tenant 98 (ADR 0358); 99 é o adversário. Tudo em transação desfeita (`fin_titulos` não
 * aceita delete). Valor conferido por dois caminhos: o aberto do título (1234.56) e o valor
 * da baixa gravada (1234.56), e o aberto depois (0.00).
 *
 * @covers-us US-FIN-079
 */
const PAGBAN_BOLETO = '03399123456789012345678901234567890120000123456';

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Pagamento pelo banco usa schema MySQL do Financeiro.');
    }
    if (! Schema::hasTable('fin_pagamentos_banco')) {
        $this->markTestSkipped('fin_pagamentos_banco ausente — rode migrate.');
    }

    DB::beginTransaction();
    config(['financeiro.pagamento_banco.enabled' => true]);
    $this->bizA = (int) $this->seededTenant()->id;
    $this->bizB = (int) $this->seededSupportClientTenant()->id;
    $this->ownerA = (int) DB::table('business')->where('id', $this->bizA)->value('owner_id');
    $this->ownerB = (int) DB::table('business')->where('id', $this->bizB)->value('owner_id');
    $this->contaA = pagbanConta($this->bizA);
    $this->contaB = pagbanConta($this->bizB);

    $this->banco = new class implements PagadorBanco {
        public array $respostaEnvio = ['status' => 'aguardando_banco', 'codigo_transacao' => 'TX-1', 'raw' => []];
        public array $respostaConsulta = ['status' => 'pago', 'codigo_transacao' => 'TX-1', 'raw' => []];
        public int $envios = 0;
        public function enviar(PagamentoBanco $p, ContaBancaria $c): array { $this->envios++; return $this->respostaEnvio; }
        public function consultar(PagamentoBanco $p, ContaBancaria $c): array { return $this->respostaConsulta; }
    };
    app()->instance(PagadorBanco::class, $this->banco);
    $this->svc = app(PagamentoBancoService::class);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function pagbanConta(int $biz): int
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'PAGBAN '.uniqid(), 'account_number' => '999',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $biz, 'account_id' => $accountId, 'banco_codigo' => '077', 'agencia' => '0001',
        'carteira' => '112', 'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture
        'beneficiario_razao_social' => 'Teste pagamento', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function pagbanTitulo(int $biz, int $owner, array $over = []): int
{
    return DB::table('fin_titulos')->insertGetId(array_merge([
        'business_id' => $biz, 'numero' => 'PB'.substr(uniqid(), -10), 'tipo' => 'pagar',
        'status' => 'aberto', 'valor_total' => 1234.56, 'valor_aberto' => 1234.56, 'moeda' => 'BRL',
        'emissao' => '2031-04-01', 'vencimento' => '2031-04-10', 'competencia_mes' => '2031-04',
        'origem' => 'manual', 'created_by' => $owner, 'created_at' => now(), 'updated_at' => now(),
    ], $over));
}

it('UC-PAGBAN-01 · desligado, nada vai ao banco', function () {
    config(['financeiro.pagamento_banco.enabled' => false]);
    $t = pagbanTitulo($this->bizA, $this->ownerA);

    expect(fn () => $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56'))
        ->toThrow(DomainException::class);
    expect($this->banco->envios)->toBe(0);
});

it('UC-PAGBAN-02 · valor confirmado diferente do aberto (um centavo) não envia', function () {
    $t = pagbanTitulo($this->bizA, $this->ownerA);

    foreach (['1234.55', '1234,56', '1234.5', '1234.560'] as $errado) {
        expect(fn () => $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, $errado))
            ->toThrow(DomainException::class);
    }
    expect($this->banco->envios)->toBe(0);
    expect(DB::table('fin_pagamentos_banco')->where('titulo_id', $t)->count())->toBe(0);
});

it('UC-PAGBAN-03 · pedir NÃO baixa o título; a trilha guarda quem, quanto e para onde', function () {
    $t = pagbanTitulo($this->bizA, $this->ownerA);

    $p = $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', '03399.12345 67890.123456 78901.234567 8 90120000123456', $this->contaA, '1234.56');

    expect($p->status)->toBe('aguardando_banco');
    $row = DB::table('fin_pagamentos_banco')->where('id', $p->id)->first();
    expect((int) $row->solicitado_por)->toBe($this->ownerA);
    expect((float) $row->valor)->toBe(1234.56);
    expect($row->destino)->toBe(PAGBAN_BOLETO);
    expect($row->codigo_transacao)->toBe('TX-1');
    expect(DB::table('fin_titulos')->where('id', $t)->value('status'))->toBe('aberto');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $t)->count())->toBe(0);
});

it('UC-PAGBAN-04 · segundo pedido do mesmo título enquanto o primeiro aguarda é recusado', function () {
    $t = pagbanTitulo($this->bizA, $this->ownerA);
    $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'pix', 'chave@exemplo.test', $this->contaA, '1234.56');

    expect(fn () => $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'pix', 'chave@exemplo.test', $this->contaA, '1234.56'))
        ->toThrow(DomainException::class);
    expect($this->banco->envios)->toBe(1);
});

it('UC-PAGBAN-05 · banco confirma → baixa uma vez, com o valor do pedido, e o título quita', function () {
    $t = pagbanTitulo($this->bizA, $this->ownerA);
    $p = $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56');

    $this->svc->sincronizar($this->bizA, $p->id);
    $this->svc->sincronizar($this->bizA, $p->id);

    $baixas = DB::table('fin_titulo_baixas')->where('titulo_id', $t)->get();
    expect($baixas)->toHaveCount(1);
    expect((float) $baixas[0]->valor_baixa)->toBe(1234.56);
    expect($baixas[0]->meio_pagamento)->toBe('boleto');
    expect($baixas[0]->idempotency_key)->toBe($p->idempotency_key);
    $tit = DB::table('fin_titulos')->where('id', $t)->first();
    expect($tit->status)->toBe('quitado');
    expect((float) $tit->valor_aberto)->toBe(0.0);
    expect(DB::table('fin_pagamentos_banco')->where('id', $p->id)->value('status'))->toBe('pago');
});

it('UC-PAGBAN-06 · banco recusa → sem baixa, título segue aberto e pode ser pedido de novo', function () {
    $this->banco->respostaEnvio = ['status' => 'recusado', 'motivo' => 'saldo insuficiente', 'raw' => []];
    $t = pagbanTitulo($this->bizA, $this->ownerA);

    $p = $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56');

    expect($p->status)->toBe('recusado');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $t)->count())->toBe(0);
    $this->banco->respostaEnvio = ['status' => 'aguardando_banco', 'raw' => []];
    expect($this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56')->status)
        ->toBe('aguardando_banco');
});

it('UC-PAGBAN-07 · título quitado por outro caminho enquanto aguardava não recebe segunda baixa', function () {
    $t = pagbanTitulo($this->bizA, $this->ownerA);
    $p = $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56');
    DB::table('fin_titulos')->where('id', $t)->update(['status' => 'quitado', 'valor_aberto' => 0]);

    $res = $this->svc->sincronizar($this->bizA, $p->id);

    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $t)->count())->toBe(0);
    expect($res->status)->toBe('pago');
    expect($res->motivo)->toContain('conferir duplicidade');
});

it('UC-PAGBAN-08 · outra empresa não paga o título nem usa a conta de outra', function () {
    $t = pagbanTitulo($this->bizA, $this->ownerA);
    expect(fn () => $this->svc->solicitar($this->bizB, $t, $this->ownerB, 'boleto', PAGBAN_BOLETO, $this->contaB, '1234.56'))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $tB = pagbanTitulo($this->bizB, $this->ownerB);
    expect(fn () => $this->svc->solicitar($this->bizB, $tB, $this->ownerB, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56'))
        ->toThrow(DomainException::class);

    $receber = pagbanTitulo($this->bizA, $this->ownerA, ['tipo' => 'receber']);
    expect(fn () => $this->svc->solicitar($this->bizA, $receber, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56'))
        ->toThrow(DomainException::class);
    expect($this->banco->envios)->toBe(0);
});

it('UC-PAGBAN-11 · o sync só consulta: com a flag desligada não toca nada; ligada, baixa o confirmado', function () {
    $t = pagbanTitulo($this->bizA, $this->ownerA);
    $p = $this->svc->solicitar($this->bizA, $t, $this->ownerA, 'boleto', PAGBAN_BOLETO, $this->contaA, '1234.56');

    config(['financeiro.pagamento_banco.enabled' => false]);
    $this->artisan('financeiro:pagamentos-banco-sync', ['--business' => $this->bizA])->assertExitCode(0);
    expect(DB::table('fin_pagamentos_banco')->where('id', $p->id)->value('status'))->toBe('aguardando_banco');

    config(['financeiro.pagamento_banco.enabled' => true]);
    $this->artisan('financeiro:pagamentos-banco-sync', ['--business' => $this->bizA])->assertExitCode(0);
    expect(DB::table('fin_pagamentos_banco')->where('id', $p->id)->value('status'))->toBe('pago');
    expect(DB::table('fin_titulos')->where('id', $t)->value('status'))->toBe('quitado');
    expect($this->banco->envios)->toBe(1);
});
