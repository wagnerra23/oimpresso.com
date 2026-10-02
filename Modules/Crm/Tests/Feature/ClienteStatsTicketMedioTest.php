<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Pessoas\PessoaVendas;
use Modules\Crm\Http\Controllers\ClienteIaController;

uses(Tests\TestCase::class);

/**
 * `ClienteIaController::calcularStatsCliente` — ticket médio e saldo em aberto da pessoa.
 *
 * O QUE ESTAVA ERRADO (achado D8 do MAPA-DE-DADOS-v1 do app Mobile, 2026-10-01):
 * o saldo somava `final_total - total_paid`, e `transactions.total_paid` NÃO existe
 * (database/schema/mysql-schema.sql). O `Unknown column` caía no `catch`, que devolvia
 * `statsZerados()` — jogando fora também o ticket médio, já calculado certo uma linha antes.
 * Resultado: ticket médio, total, nº de vendas e saldo sempre 0 para toda pessoa. Os 2
 * consumidores (2 de 2, `git grep calcularStatsCliente`) herdavam o zero: `scoreRisco()`
 * (GET /cliente/{id}/ia/score-risco) e `prepararDadosCliente()` (dados das 3 rotas de IA).
 *
 * CONTRATO (pedido da sessão BUGS PESSOAS + intenção já escrita no código; nenhum SPEC define
 * o número — registrado no PR):
 *   ticket médio = Σ final_total das vendas `sell`/`final` ÷ nº dessas vendas;
 *   saldo em aberto = Σ (final_total − Σ transaction_payments.amount) das vendas due/partial —
 *   a MESMA fórmula do `valor_aberto` da lista de pessoas (buildClienteIndexCustomers), para a
 *   pessoa não ter dois "em aberto" diferentes entre lista e detalhe.
 *
 * Regra mestre de valor: números esperados calculados À MÃO (caminho 1) e conferidos por SQL
 * direto, sem passar pelo controller (caminho 2).
 *
 * Tenant fictício 98 (ADR 0358) × outra empresa. NUNCA biz=4. ⚠️ SKIP em SQLite — leia
 * assertions (LC-13).
 */
const TM_BIZ = 98;

function tmVenda(int $biz, int $contato, int $user, string $total, string $status, string $pgto, string $tipo = 'sell'): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => $tipo, 'status' => $status, 'payment_status' => $pgto,
        'contact_id' => $contato, 'created_by' => $user, 'essentials_duration' => 0,
        'transaction_date' => now()->subDays(10), 'final_total' => $total,
        'total_before_tax' => $total, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function tmPagamento(int $biz, int $venda, string $valor, int $user): void
{
    DB::table('transaction_payments')->insert([
        'business_id' => $biz, 'transaction_id' => $venda, 'amount' => $valor,
        'method' => 'cash', 'paid_on' => now(), 'created_by' => $user,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function tmStats(int $contatoId): array
{
    $m = new ReflectionMethod(ClienteIaController::class, 'calcularStatsCliente');
    $m->setAccessible(true);

    return $m->invoke(app(ClienteIaController::class), \App\Contact::findOrFail($contatoId));
}

function tmNovoContato(int $user): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => TM_BIZ, 'type' => 'customer', 'is_customer' => 1,
        'name' => 'TM Pessoa ' . uniqid(), 'contact_id' => 'TM' . random_int(100000, 999999),
        'created_by' => $user, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: transactions/contacts requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'transactions', 'transaction_payments'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    if (! DB::table('business')->where('id', TM_BIZ)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente nesta lane.');
    }
    $this->user = (int) DB::table('users')->where('business_id', TM_BIZ)->value('id');
    if ($this->user === 0) {
        $this->markTestSkipped('Sem usuário no tenant 98.');
    }
    DB::beginTransaction();

    $this->contatoId = tmNovoContato($this->user);

    // 3 vendas finais: 100,00 (paga) · 200,00 (parcial, pagou 50,00) · 300,00 (em aberto).
    $v1 = tmVenda(TM_BIZ, $this->contatoId, $this->user, '100.0000', 'final', 'paid');
    $v2 = tmVenda(TM_BIZ, $this->contatoId, $this->user, '200.0000', 'final', 'partial');
    tmVenda(TM_BIZ, $this->contatoId, $this->user, '300.0000', 'final', 'due');
    tmPagamento(TM_BIZ, $v1, '100.0000', $this->user);
    tmPagamento(TM_BIZ, $v2, '50.0000', $this->user);
    // Ruído que NÃO entra: rascunho e devolução da mesma pessoa.
    tmVenda(TM_BIZ, $this->contatoId, $this->user, '999.0000', 'draft', 'due');
    tmVenda(TM_BIZ, $this->contatoId, $this->user, '77.0000', 'final', 'paid', 'sell_return');
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('ticket médio = Σ vendas finais ÷ nº de vendas (600,00 ÷ 3 = 200,00)', function () {
    $s = tmStats($this->contatoId);

    expect($s['total_invoice_count'])->toBe(3);
    expect(round((float) $s['total_invoice'], 2))->toBe(600.0);
    expect(round((float) $s['ticket_medio'], 2))->toBe(200.0);
});

it('saldo em aberto = (200,00 − 50,00) + 300,00 = 450,00', function () {
    $s = tmStats($this->contatoId);

    expect(round((float) $s['invoice_due'], 2))->toBe(450.0);
});

it('caminho 2 — SQL direto, sem o controller, chega nos mesmos números', function () {
    $s = tmStats($this->contatoId);

    $vendas = DB::table('transactions')
        ->where('business_id', TM_BIZ)->where('contact_id', $this->contatoId)
        ->where('type', 'sell')->where('status', 'final');
    $soma = (float) (clone $vendas)->sum('final_total');
    $qtd = (int) (clone $vendas)->count();

    $saldo = 0.0;
    foreach ((clone $vendas)->whereIn('payment_status', ['due', 'partial'])->get(['id', 'final_total']) as $t) {
        $pago = (float) DB::table('transaction_payments')->where('transaction_id', $t->id)->sum('amount');
        $saldo += (float) $t->final_total - $pago;
    }

    expect($qtd)->toBe(3);
    expect(round((float) $s['ticket_medio'], 2))->toBe(round($soma / $qtd, 2));
    expect(round((float) $s['invoice_due'], 2))->toBe(round($saldo, 2));
});

it('a aba IA e a API do app saem da MESMA fonte (PessoaVendas): 3 vendas, 600,00, saldo 450,00', function () {
    $s = tmStats($this->contatoId);
    $r = PessoaVendas::resumo(TM_BIZ, $this->contatoId);
    $saldos = PessoaVendas::saldosAbertos(TM_BIZ, [$this->contatoId]);

    expect($r['qtd'])->toBe(3);
    expect(round($r['soma'], 2))->toBe(600.0);
    expect(round($saldos[$this->contatoId], 2))->toBe(450.0);
    expect($s['total_invoice_count'])->toBe($r['qtd']);
    expect(round((float) $s['invoice_due'], 2))->toBe(round($saldos[$this->contatoId], 2));
});

it('saldo pago a mais: a fonte devolve o número cru (−50,00); a aba IA mantém o piso em zero', function () {
    $c = tmNovoContato($this->user);
    $v = tmVenda(TM_BIZ, $c, $this->user, '100.0000', 'final', 'due');
    tmPagamento(TM_BIZ, $v, '150.0000', $this->user);

    expect(round(PessoaVendas::saldosAbertos(TM_BIZ, [$c])[$c], 2))->toBe(-50.0);
    expect((float) tmStats($c)['invoice_due'])->toBe(0.0);
});

it('CROSS-TENANT: venda do mesmo contact_id em outra empresa não entra na conta', function () {
    $outraBiz = (int) DB::table('business')->where('id', '!=', TM_BIZ)->where('id', '!=', 4)->value('id');
    if ($outraBiz === 0) {
        $this->markTestSkipped('Lane sem segunda empresa para o caso cross-tenant.');
    }
    tmVenda($outraBiz, $this->contatoId, $this->user, '9000.0000', 'final', 'due');

    $s = tmStats($this->contatoId);

    expect($s['total_invoice_count'])->toBe(3);
    expect(round((float) $s['ticket_medio'], 2))->toBe(200.0);
    expect(round((float) $s['invoice_due'], 2))->toBe(450.0);
});

it('pessoa sem venda: ticket médio 0 e nenhuma venda contada', function () {
    $s = tmStats(tmNovoContato($this->user));

    expect($s['total_invoice_count'])->toBe(0);
    expect((float) $s['ticket_medio'])->toBe(0.0);
    expect((float) $s['invoice_due'])->toBe(0.0);
});
