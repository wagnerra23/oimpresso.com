<?php

declare(strict_types=1);
// Cobre UC-FCX-06 (resources/js/Pages/Financeiro/Caixa/Index.casos.md · ADR 0183).

use App\CashRegister;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Titulo;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Lançamento retroativo do caixa fechado (auditoria Financeiro 2026-11-05, achado D7).
 *
 * VALOR — dois caminhos: a soma das movimentações do caixa (inserida aqui) e o
 * `valor_total` do título + o `breakdown` que o serviço grava.
 * Tenant 98 contra o 99 (ADR 0358), nunca biz=4 nem biz=1.
 * ⚠️ Skip fora do MySQL: leia as assertions, não "0 failed" (LC-13).
 */
const CX_LANCAR = 'financeiro.lancamentos.create';

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do UltimatePOS (MySQL).');
    }
    if (! Schema::hasTable('cash_registers') || ! Schema::hasColumn('fin_contas_bancarias', 'tipo_conta')) {
        $this->markTestSkipped('Schema do caixa ausente.');
    }

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->user = $this->usuarioComPermissoes(['view_cash_register', CX_LANCAR]);

    if (! DB::table('fin_contas_bancarias')->where('business_id', $this->b98)->where('tipo_conta', 'caixa')->exists()) {
        $conta = DB::table('accounts')->insertGetId([
            'business_id' => $this->b98, 'name' => 'Caixa Loja Teste', 'account_number' => 'CX-'.bin2hex(random_bytes(3)),
            'created_by' => $this->user->id, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('fin_contas_bancarias')->insert([
            'business_id' => $this->b98, 'account_id' => $conta, 'tipo_conta' => 'caixa', 'banco_codigo' => '000',
            'agencia' => '0', 'carteira' => '-', 'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — placeholder de teste
            'beneficiario_razao_social' => 'Teste', 'ativo_para_boleto' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

function cxEntrar($user, int $biz): void
{
    session(['user.business_id' => $biz, 'user.id' => $user->id, 'business.id' => $biz,
        'business' => ['id' => $biz, 'name' => 'Teste', 'currency_symbol' => 'R$']]);
    test()->actingAs($user);
}

/** Caixa fechado na origem, sem passar pelo evento de fechamento. */
function cxFechado(int $biz, int $userId, string $status = 'close', ?int $locationId = null): int
{
    $id = (int) DB::table('cash_registers')->insertGetId([
        'business_id' => $biz, 'location_id' => $locationId, 'user_id' => $userId, 'status' => $status,
        'closed_at' => $status === 'close' ? now() : null, 'closing_amount' => 350,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([['cash', 100], ['card', 200], ['cheque', 50]] as [$metodo, $valor]) {
        DB::table('cash_register_transactions')->insert([
            'cash_register_id' => $id, 'amount' => $valor, 'pay_method' => $metodo, 'type' => 'credit',
            'transaction_type' => 'sell', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

function cxTitulos(int $biz, int $caixa)
{
    return Titulo::withoutGlobalScopes()->where('business_id', $biz)->where('origem', 'caixa')->where('origem_id', $caixa)->get();
}

it('UC-FCX-06 · sem a permissão de lançar, devolve 403 e não cria título', function () {
    $user = $this->usuarioComPermissoes(['view_cash_register']);
    $caixa = cxFechado($this->b98, $user->id);
    cxEntrar($user, $this->b98);

    $this->post("/financeiro/caixa/{$caixa}/lancar")->assertForbidden();

    expect(cxTitulos($this->b98, $caixa))->toHaveCount(0);
});

it('UC-FCX-06 · caixa de outra empresa devolve 404 e não cria título', function () {
    $caixa = cxFechado($this->b99, $this->user->id);
    cxEntrar($this->user, $this->b98);

    $this->post("/financeiro/caixa/{$caixa}/lancar")->assertNotFound();

    expect(cxTitulos($this->b99, $caixa))->toHaveCount(0);
});

it('UC-FCX-06 · caixa ainda aberto devolve 404 e não cria título', function () {
    $caixa = cxFechado($this->b98, $this->user->id, 'open');
    cxEntrar($this->user, $this->b98);

    $this->post("/financeiro/caixa/{$caixa}/lancar")->assertNotFound();

    expect(cxTitulos($this->b98, $caixa))->toHaveCount(0);
});

it('UC-FCX-06 · dois POSTs geram um título só, com o valor do caixa', function () {
    $local = DB::table('business_locations')->where('business_id', $this->b98)->first();
    expect($local)->not->toBeNull();
    $caixa = cxFechado($this->b98, $this->user->id, 'close', (int) $local->id);
    cxEntrar($this->user, $this->b98);

    $this->post("/financeiro/caixa/{$caixa}/lancar")->assertSessionHas('success');
    $this->post("/financeiro/caixa/{$caixa}/lancar")->assertSessionHas('success');

    $titulos = cxTitulos($this->b98, $caixa);
    expect($titulos)->toHaveCount(1);

    $titulo = $titulos->first();
    $somaOrigem = (float) DB::table('cash_register_transactions')->where('cash_register_id', $caixa)->sum('amount');
    $breakdown = $titulo->metadata['breakdown'];

    expect((float) $titulo->valor_total)->toBe(350.0)
        ->and($somaOrigem)->toBe(350.0)
        ->and((float) ($breakdown['cash'] + $breakdown['card'] + $breakdown['cheque'] + $breakdown['other']))->toBe(350.0)
        ->and($titulo->status)->toBe('quitado')
        ->and((float) $titulo->valor_aberto)->toBe(0.0)
        ->and((int) $titulo->metadata['location_id'])->toBe((int) $local->id)
        ->and($titulo->metadata['location_name'])->toBe($local->name);
});
