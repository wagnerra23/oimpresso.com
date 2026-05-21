<?php

declare(strict_types=1);

/**
 * Pest tests pro ADR 0183 — ponte cash_registers → fin_titulos.
 *
 * Cobre 6 cenários canon:
 *   1. Happy path — caixa fecha → fin_titulo origem='caixa' criado
 *   2. Idempotência (P1) — re-disparar event 2x → 1 título só
 *   3. Multi-caixa (Wagner) — 2 caixas mesmo business → 2 títulos separados
 *   4. Caixa vazio (P6) — total=0 → skip silencioso
 *   5. business_id=0 (P2) — skip + log
 *   6. user_id=NULL (P7) — metadata.user_name='Sistema'
 */

use App\CashRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Events\CashRegisterClosed;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\TituloAutoService;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Cria business com conta-mãe Caixa (seed normalmente via PR A migration)
    DB::table('business')->insert([
        'id' => 1,
        'name' => 'Test Business',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => 1,
        'name' => 'Caixa Loja Test',
        'account_number' => 'CAIXA-1',
        'created_by' => 1,
        'is_closed' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('fin_contas_bancarias')->insert([
        'business_id' => 1,
        'account_id' => $accountId,
        'tipo_conta' => 'caixa',
        'banco_codigo' => '000',
        'agencia' => '0',
        'carteira' => '-',
        'beneficiario_documento' => '00.000.000/0000-00',
        'beneficiario_razao_social' => 'Test',
        'ativo_para_boleto' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

// ──────────────────────────────────────────────────────────────────
// 1. Happy path
// ──────────────────────────────────────────────────────────────────

it('cria fin_titulo quando caixa fecha com vendas', function () {
    $cr = CashRegister::create([
        'business_id' => 1,
        'user_id' => 1,
        'status' => 'open',
    ]);

    // Simula vendas via cash_register_transactions
    DB::table('cash_register_transactions')->insert([
        ['cash_register_id' => $cr->id, 'amount' => 100, 'pay_method' => 'cash',   'type' => 'credit', 'transaction_type' => 'sell', 'created_at' => now(), 'updated_at' => now()],
        ['cash_register_id' => $cr->id, 'amount' => 200, 'pay_method' => 'card',   'type' => 'credit', 'transaction_type' => 'sell', 'created_at' => now(), 'updated_at' => now()],
        ['cash_register_id' => $cr->id, 'amount' =>  50, 'pay_method' => 'cheque', 'type' => 'credit', 'transaction_type' => 'sell', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Fecha caixa
    $cr->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 350]);

    $titulo = Titulo::withoutGlobalScopes()
        ->where('origem', 'caixa')
        ->where('origem_id', $cr->id)
        ->first();

    expect($titulo)->not->toBeNull()
        ->and((float) $titulo->valor_total)->toBe(350.0)
        ->and($titulo->status)->toBe('quitado')
        ->and($titulo->tipo)->toBe('receber')
        ->and($titulo->numero)->toBe('CX-' . $cr->id)
        ->and($titulo->metadata['breakdown']['cash'])->toBe(100.0)
        ->and($titulo->metadata['breakdown']['card'])->toBe(200.0)
        ->and($titulo->metadata['breakdown']['cheque'])->toBe(50.0)
        ->and($titulo->metadata['caixa_id'])->toBe($cr->id);
});

// ──────────────────────────────────────────────────────────────────
// 2. Idempotência (P1)
// ──────────────────────────────────────────────────────────────────

it('é idempotente — re-disparar event 2x não duplica fin_titulo', function () {
    $cr = CashRegister::create([
        'business_id' => 1, 'user_id' => 1, 'status' => 'open',
    ]);
    DB::table('cash_register_transactions')->insert([
        'cash_register_id' => $cr->id, 'amount' => 100, 'pay_method' => 'cash',
        'type' => 'credit', 'transaction_type' => 'sell',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $cr->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 100]);

    // Re-dispara event 2x
    CashRegisterClosed::dispatch($cr);
    CashRegisterClosed::dispatch($cr);

    $count = Titulo::withoutGlobalScopes()
        ->where('origem', 'caixa')
        ->where('origem_id', $cr->id)
        ->count();

    expect($count)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────
// 3. Multi-caixa (Wagner cenário PME real)
// ──────────────────────────────────────────────────────────────────

it('cria 2 fin_titulos separados quando 2 caixas fecham no mesmo business', function () {
    // Caixa Larissa
    $cr1 = CashRegister::create(['business_id' => 1, 'user_id' => 1, 'status' => 'open']);
    DB::table('cash_register_transactions')->insert([
        'cash_register_id' => $cr1->id, 'amount' => 1000, 'pay_method' => 'cash',
        'type' => 'credit', 'transaction_type' => 'sell',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Caixa João (mesmo business)
    $cr2 = CashRegister::create(['business_id' => 1, 'user_id' => 2, 'status' => 'open']);
    DB::table('cash_register_transactions')->insert([
        'cash_register_id' => $cr2->id, 'amount' => 500, 'pay_method' => 'card',
        'type' => 'credit', 'transaction_type' => 'sell',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $cr1->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 1000]);
    $cr2->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 500]);

    $titulos = Titulo::withoutGlobalScopes()
        ->where('origem', 'caixa')
        ->orderBy('origem_id')
        ->get();

    expect($titulos)->toHaveCount(2)
        ->and((float) $titulos[0]->valor_total)->toBe(1000.0)
        ->and((float) $titulos[1]->valor_total)->toBe(500.0);
});

// ──────────────────────────────────────────────────────────────────
// 4. Caixa vazio (P6) — skip silencioso
// ──────────────────────────────────────────────────────────────────

it('skip silencioso quando caixa fecha com total=0', function () {
    $cr = CashRegister::create(['business_id' => 1, 'user_id' => 1, 'status' => 'open']);
    // Nenhuma transaction → total=0
    $cr->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 0]);

    $count = Titulo::withoutGlobalScopes()
        ->where('origem', 'caixa')
        ->where('origem_id', $cr->id)
        ->count();

    expect($count)->toBe(0);
});

// ──────────────────────────────────────────────────────────────────
// 5. business_id=0 (P2) — skip + log
// ──────────────────────────────────────────────────────────────────

it('skip caixa com business_id=0 (legacy data)', function () {
    $service = app(TituloAutoService::class);

    // Construct manual sem save (business_id=0 violaria FK)
    $cr = new CashRegister([
        'id' => 999,
        'business_id' => 0,
        'user_id' => 1,
        'status' => 'close',
        'closed_at' => now(),
        'closing_amount' => 100,
    ]);
    $cr->exists = true;

    $result = $service->sincronizarDeCashRegister($cr);

    expect($result)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────
// 6. user_id=NULL (P7) — fallback 'Sistema'
// ──────────────────────────────────────────────────────────────────

it('user_id NULL fica como Sistema no metadata', function () {
    $cr = CashRegister::create(['business_id' => 1, 'user_id' => null, 'status' => 'open']);
    DB::table('cash_register_transactions')->insert([
        'cash_register_id' => $cr->id, 'amount' => 100, 'pay_method' => 'cash',
        'type' => 'credit', 'transaction_type' => 'sell',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $cr->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 100]);

    $titulo = Titulo::withoutGlobalScopes()
        ->where('origem', 'caixa')
        ->where('origem_id', $cr->id)
        ->first();

    expect($titulo->metadata['user_name'])->toBe('Sistema')
        ->and($titulo->metadata['user_id'])->toBeNull();
});
