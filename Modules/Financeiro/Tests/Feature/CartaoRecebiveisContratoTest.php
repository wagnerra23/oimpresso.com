<?php

declare(strict_types=1);
// @covers-us US-FIN-083
// Cobre UC-FCC-23..26 (resources/js/Pages/Financeiro/Conciliacao/Index.casos.md · US-FIN-083).

use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Financeiro\Http\Controllers\ConciliacaoController;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Cartão da maquininha. REGRA MESTRE de VALOR: taxa e líquido conferidos por DOIS caminhos —
 * o que o serviço gravou e a conta feita à mão nas fixtures (bruto × taxa, meio-para-cima, e
 * líquido + taxa = bruto). O repasse baixa recebível, nunca título: a contagem de baixas da
 * venda é a mesma antes e depois da conciliação.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99. NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_* depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_cartao_recebiveis') || ! Schema::hasColumn('fin_extrato_lancamentos', 'regra_id')) {
        $this->markTestSkipped('Migrations 2026_10_27_120000/130000 não aplicadas — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = (int) (User::where('business_id', $this->b98)->value('id') ?? 0);
    $this->u99 = (int) (User::where('business_id', $this->b99)->value('id') ?? 0);
    if ($this->u98 === 0 || $this->u99 === 0) {
        $this->markTestSkipped('Sem usuário nos tenants 98/99.');
    }
    config(['financeiro.cartao.recebiveis_enabled' => true, 'financeiro.cartao.janela_repasse_dias' => 3]);
});

function cartaoAdquirente(int $biz): int
{
    return (int) DB::table('fin_cartao_adquirentes')->insertGetId([
        'business_id' => $biz, 'nome' => 'PEST Stone', 'descricao_extrato' => 'STONE',
        'taxa_debito' => 1.99, 'prazo_debito_dias' => 1, 'taxa_credito' => 3.19, 'prazo_credito_dias' => 30,
        'padrao' => 1, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Venda paga no cartão (o observer do pagamento gera o recebível). */
function cartaoVenda(int $biz, int $user, float $valor, string $cardType, string $dia = '2031-05-05'): TransactionPayment
{
    $tx = Transaction::create([
        'business_id' => $biz,
        'location_id' => DB::table('business_locations')->where('business_id', $biz)->value('id'),
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'contact_id' => null,
        'invoice_no' => 'ZZ-FIN081-'.Str::random(8), 'transaction_date' => "{$dia} 10:00:00",
        'total_before_tax' => $valor, 'final_total' => $valor, 'created_by' => $user,
    ]);

    return TransactionPayment::create([
        'transaction_id' => $tx->id, 'business_id' => $biz, 'amount' => $valor, 'method' => 'card',
        'card_type' => $cardType, 'paid_on' => "{$dia} 10:00:00", 'created_by' => $user,
    ]);
}

function cartaoRecebivel(TransactionPayment $tp): object
{
    return DB::table('fin_cartao_recebiveis')->where('transaction_payment_id', $tp->id)->first();
}

function cartaoLinhaApi(int $biz, float $valor, string $dia, string $descricao = 'STONE PAGAMENTOS REPASSE'): int
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'PEST-CARTAO-'.uniqid(), 'account_number' => '999',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $conta = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $biz, 'account_id' => $accountId, 'banco_codigo' => '077', 'agencia' => '0001',
        'carteira' => '112',
        'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — CNPJ fixture de teste (nao-PII real)
        'beneficiario_razao_social' => 'Teste Cartão', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('fin_extrato_lancamentos')->insertGetId([
        'business_id' => $biz, 'conta_bancaria_id' => $conta, 'data' => $dia, 'valor' => $valor, 'tipo' => 'C',
        'descricao' => $descricao, 'idempotency_key' => 'PEST-CARTAO-'.uniqid(),
        'raw_payload' => json_encode(['source' => 'test']), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function cartaoRodarMotor(int $biz): void
{
    $controller = new ConciliacaoController();
    $ref = new ReflectionMethod($controller, 'sugerirMatches');
    $ref->setAccessible(true);
    $ref->invoke($controller, $biz);
}

it('UC-FCC-23 · venda no cartão vira recebível previsto com taxa, líquido e data; apagar o pagamento cancela', function () {
    cartaoAdquirente($this->b98);
    $credito = cartaoVenda($this->b98, $this->u98, 100.00, 'credit');
    $debito = cartaoVenda($this->b98, $this->u98, 80.00, 'debit');
    $meio = cartaoVenda($this->b98, $this->u98, 50.00, 'visa'); // bandeira → crédito

    // Caminho 1: o que o serviço gravou.
    $c = cartaoRecebivel($credito);
    expect($c->modalidade)->toBe('credito');
    expect((float) $c->valor_taxa)->toBe(3.19);
    expect((float) $c->valor_liquido)->toBe(96.81);
    expect(substr((string) $c->data_prevista, 0, 10))->toBe('2031-06-04'); // +30 dias corridos
    expect($c->status)->toBe('previsto');

    $d = cartaoRecebivel($debito);
    expect($d->modalidade)->toBe('debito');
    expect((float) $d->valor_taxa)->toBe(1.59);   // 80,00 × 1,99% = 1,592
    expect((float) $d->valor_liquido)->toBe(78.41);
    expect(substr((string) $d->data_prevista, 0, 10))->toBe('2031-05-06');

    $m = cartaoRecebivel($meio);
    expect((float) $m->valor_taxa)->toBe(1.60);   // 50,00 × 3,19% = 1,595 → meio-para-cima
    expect((float) $m->valor_liquido)->toBe(48.40);

    // Caminho 2: conta à mão — líquido + taxa fecha o bruto, em centavos, nos três.
    foreach ([[$c, 10000], [$d, 8000], [$m, 5000]] as [$r, $brutoCent]) {
        expect((int) round(((float) $r->valor_liquido + (float) $r->valor_taxa) * 100))->toBe($brutoCent);
        expect((int) round((float) $r->valor_bruto * 100))->toBe($brutoCent);
    }

    $debito->delete();
    expect(cartaoRecebivel($debito)->status)->toBe('cancelado');
});

it('UC-FCC-24 · repasse de um recebível baixa só ele e não mexe na venda', function () {
    cartaoAdquirente($this->b98);
    $debito = cartaoVenda($this->b98, $this->u98, 80.00, 'debit');
    $credito = cartaoVenda($this->b98, $this->u98, 100.00, 'credit');
    $baixasAntes = DB::table('fin_titulo_baixas')->where('business_id', $this->b98)->count();
    $linha = cartaoLinhaApi($this->b98, 78.41, '2031-05-06');

    cartaoRodarMotor($this->b98);

    $d = cartaoRecebivel($debito);
    expect($d->status)->toBe('recebido');
    expect($d->extrato_origem)->toBe('api');
    expect((int) $d->extrato_linha_id)->toBe($linha);
    expect(cartaoRecebivel($credito)->status)->toBe('previsto');

    $l = DB::table('fin_extrato_lancamentos')->where('id', $linha)->first();
    expect($l->status)->toBe('conciliado');
    expect($l->titulo_id)->toBeNull();
    expect(DB::table('fin_titulo_baixas')->where('business_id', $this->b98)->count())->toBe($baixasAntes);
});

it('UC-FCC-25 · repasse que soma os previstos do dia baixa todos; valor que não fecha não baixa nenhum', function () {
    cartaoAdquirente($this->b98);
    $a = cartaoVenda($this->b98, $this->u98, 100.00, 'credit', '2031-07-01');
    $b = cartaoVenda($this->b98, $this->u98, 50.00, 'credit', '2031-07-01');
    $errada = cartaoLinhaApi($this->b98, 145.20, '2031-07-31');   // 1 centavo a menos

    cartaoRodarMotor($this->b98);
    expect(cartaoRecebivel($a)->status)->toBe('previsto');
    expect(cartaoRecebivel($b)->status)->toBe('previsto');
    expect(DB::table('fin_extrato_lancamentos')->where('id', $errada)->value('status'))->not->toBe('conciliado');

    $certa = cartaoLinhaApi($this->b98, 145.21, '2031-07-31');    // 96,81 + 48,40
    cartaoRodarMotor($this->b98);
    expect(cartaoRecebivel($a)->status)->toBe('recebido');
    expect(cartaoRecebivel($b)->status)->toBe('recebido');
    expect((int) cartaoRecebivel($a)->extrato_linha_id)->toBe($certa);
    // Soma pelo banco = valor da linha.
    expect((float) DB::table('fin_cartao_recebiveis')->where('extrato_linha_id', $certa)->sum('valor_liquido'))->toBe(145.21);
});

it('UC-FCC-26 · flag desligada não gera recebível; recebível de outro business não é baixado [T0]', function () {
    config(['financeiro.cartao.recebiveis_enabled' => false]);
    cartaoAdquirente($this->b98);
    $off = cartaoVenda($this->b98, $this->u98, 100.00, 'credit');
    expect(cartaoRecebivel($off))->toBeNull();

    config(['financeiro.cartao.recebiveis_enabled' => true]);
    cartaoAdquirente($this->b99);
    $do99 = cartaoVenda($this->b99, $this->u99, 80.00, 'debit');
    $linha98 = cartaoLinhaApi($this->b98, 78.41, '2031-05-06');

    cartaoRodarMotor($this->b98);

    expect(cartaoRecebivel($do99)->status)->toBe('previsto');
    expect(DB::table('fin_extrato_lancamentos')->where('id', $linha98)->value('status'))->toBeNull();
});
