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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Financeiro\Events\CashRegisterClosed;
use Modules\Financeiro\Listeners\OnCashRegisterClosedCreateFinanceiroTitulo;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\TituloAutoService;

/*
 * 2026-11-05 (lote 2.5 da auditoria): saiu da quarentena. Antes usava RefreshDatabase
 * e inseria o business id=1 à mão — no processo compartilhado da lane MySQL isso
 * dropava o schema e colidia com o business 1 do seed: 6 failed, 0 assertions,
 * nenhum cenário da ponte caixa→título era exercido. Agora roda em transação
 * (DatabaseTransactions) no tenant fictício 98 (ADR 0358), com a própria conta-mãe
 * Caixa, e cada consulta fica presa aos caixas que o caso criou.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    // FinanceiroServiceProvider registra os listeners atrás de uma flag ESTÁTICA: num
    // processo com vários apps (a suíte) só o 1º recebe o listener do caixa, e do 2º teste
    // em diante o evento dispara sem ninguém escutar (4 falhas no run 42113800989). Mesmo
    // padrão do Onda26InterWebhookIntegrationTest. Isto prova o trecho evento → listener →
    // serviço → título, não o registro no provider; a flag é decisão [W] (cria título).
    if (! Event::hasListeners(CashRegisterClosed::class)) {
        Event::listen(CashRegisterClosed::class, [OnCashRegisterClosedCreateFinanceiroTitulo::class, 'handle']);
    }

    $this->biz = 98;
    if (! DB::table('business')->where('id', $this->biz)->exists()) {
        $this->markTestSkipped('Tenant fictício 98 ausente — seed .github/actions/pest-mysql-setup.');
    }
    $this->uid = (int) (DB::table('users')->where('business_id', $this->biz)->value('id') ?? 0);
    if ($this->uid === 0) {
        $this->markTestSkipped('Sem usuário no tenant 98.');
    }
    $this->uid2 = (int) DB::table('users')->insertGetId([
        'first_name' => 'Caixa', 'last_name' => 'Dois', 'username' => 'cx2_'.bin2hex(random_bytes(3)),
        'business_id' => $this->biz, 'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Conta-mãe Caixa do tenant (o serviço procura tipo_conta='caixa' no business do caixa).
    if (! DB::table('fin_contas_bancarias')->where('business_id', $this->biz)->where('tipo_conta', 'caixa')->exists()) {
        $accountId = DB::table('accounts')->insertGetId([
            'business_id' => $this->biz, 'name' => 'Caixa Loja Test', 'account_number' => 'CAIXA-T98-'.bin2hex(random_bytes(3)),
            'created_by' => $this->uid, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('fin_contas_bancarias')->insert([
            'business_id' => $this->biz, 'account_id' => $accountId, 'tipo_conta' => 'caixa',
            'banco_codigo' => '000', 'agencia' => '0', 'carteira' => '-',
            'beneficiario_documento' => '00.000.000/0000-00', 'beneficiario_razao_social' => 'Test',
            'ativo_para_boleto' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});

// ──────────────────────────────────────────────────────────────────
// 1. Happy path
// ──────────────────────────────────────────────────────────────────

it('cria fin_titulo quando caixa fecha com vendas', function () {
    $cr = CashRegister::create([
        'business_id' => $this->biz,
        'user_id' => $this->uid,
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
        'business_id' => $this->biz, 'user_id' => $this->uid, 'status' => 'open',
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
    $cr1 = CashRegister::create(['business_id' => $this->biz, 'user_id' => $this->uid, 'status' => 'open']);
    DB::table('cash_register_transactions')->insert([
        'cash_register_id' => $cr1->id, 'amount' => 1000, 'pay_method' => 'cash',
        'type' => 'credit', 'transaction_type' => 'sell',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Caixa João (mesmo business)
    $cr2 = CashRegister::create(['business_id' => $this->biz, 'user_id' => $this->uid2, 'status' => 'open']);
    DB::table('cash_register_transactions')->insert([
        'cash_register_id' => $cr2->id, 'amount' => 500, 'pay_method' => 'card',
        'type' => 'credit', 'transaction_type' => 'sell',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $cr1->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 1000]);
    $cr2->update(['status' => 'close', 'closed_at' => now(), 'closing_amount' => 500]);

    $titulos = Titulo::withoutGlobalScopes()
        ->where('origem', 'caixa')
        ->whereIn('origem_id', [$cr1->id, $cr2->id])
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
    $cr = CashRegister::create(['business_id' => $this->biz, 'user_id' => $this->uid, 'status' => 'open']);
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
        'user_id' => $this->uid,
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
    $cr = CashRegister::create(['business_id' => $this->biz, 'user_id' => null, 'status' => 'open']);
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
