<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Spatie\Permission\Models\Permission;
use Tests\Contract\AutosaveContractRunner;

/**
 * API de Pagamentos do app das lojas (tela 15) — ESCRITA: referências, gerar, consultar, cancelar.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §10.6 (formato do oimpresso-app#48).
 * NÃO derivado do controller. Decisões [W] 2026-10-02: valor = saldo em aberto; credencial padrão.
 *
 * REGRA MESTRE (valor): o saldo é provado por DOIS caminhos independentes com números concretos —
 * (a) a conta à mão no teste (500 − 200 + 50 de devolução = 350) e (b) final_total −
 * TransactionUtil::getTotalPaid — e o valor que chega ao gateway é conferido em centavos.
 *
 * O provedor é um mock do PaymentGatewayContract: nenhum banco real é chamado. O mock grava a linha
 * de `cobrancas` como o serviço real faria (status emitida), para a resposta 201 sair dela.
 *
 * Tier 0 (ADR 0093): documento e cobrança do business 2 → 404, e nada é gravado.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['cobrancas', 'payment_gateway_credentials', 'fin_contas_bancarias', 'transaction_payments'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = (int) $ctx['business']->id;
    $this->venda = (int) $ctx['transactionId'];
    $this->outro = \App\Business::where('id', '!=', $this->biz)->orderBy('id')->first();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    foreach (['financeiro.access', 'direct_sell.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    // Pedido com saldo: 500 de total, 200 pagos e 50 devolvidos ao cliente (is_return) → 350 em aberto.
    DB::table('transactions')->where('id', $this->venda)->update(['status' => 'final', 'final_total' => 500.00]);
    DB::table('transaction_payments')->where('transaction_id', $this->venda)->delete();
    DB::table('transaction_payments')->insert([
        ['transaction_id' => $this->venda, 'business_id' => $this->biz, 'amount' => 200.00, 'is_return' => 0, 'method' => 'cash', 'created_at' => now(), 'updated_at' => now()],
        ['transaction_id' => $this->venda, 'business_id' => $this->biz, 'amount' => 50.00, 'is_return' => 1, 'method' => 'cash', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('cobrancas')->where('business_id', $this->biz)->where('origem_type', 'sale')->where('origem_id', $this->venda)->delete();

    // Conta padrão com credencial (a de menor id do business que tem credencial).
    $cred = DB::table('payment_gateway_credentials')->where('business_id', $this->biz)->value('id')
        ?? DB::table('payment_gateway_credentials')->insertGetId([
            'business_id' => $this->biz, 'gateway_key' => 'pagarme', 'ambiente' => 'sandbox', 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    if (! ContaBancaria::padraoParaCobranca($this->biz)) {
        $acc = DB::table('accounts')->insertGetId([
            'business_id' => $this->biz, 'name' => 'Conta APP cobrança', 'account_number' => 'APP-' . uniqid(),
            'created_by' => $ctx['user']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('fin_contas_bancarias')->insert([
            'business_id' => $this->biz, 'account_id' => $acc, 'banco_codigo' => '077', 'agencia' => '0001',
            'carteira' => '112', 'beneficiario_documento' => 'DOC-TESTE-APP', 'beneficiario_razao_social' => 'APP',
            'ativo_para_boleto' => false, 'payment_gateway_credential_id' => $cred,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $this->contaPadrao = ContaBancaria::padraoParaCobranca($this->biz);

    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(fn ($b, $perm) => $perm === 'financeiro_module');
    app()->instance(ModuleUtil::class, $mu);
});

function appPgeUsuario(int $biz, array $perms): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Pge', 'username' => 'app_pge_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($perms as $p) {
        $u->givePermissionTo($p);
    }

    return $u;
}

/**
 * Gateway falso: `for()` devolve ele mesmo; emitir grava a linha como o serviço real e devolve o id.
 * Guarda o input e a conta recebidos em `$box` para o teste conferir.
 */
function appPgeGateway(object $box, ?\Throwable $falha = null): void
{
    $gw = Mockery::mock(PaymentGatewayContract::class);
    $gw->shouldReceive('for')->andReturnUsing(function ($account) use ($gw, $box) {
        $box->account = $account;

        return $gw;
    });
    $emitir = function (EmitirCobrancaInput $in) use ($box, $falha) {
        $box->input = $in;
        // O serviço real grava `pending` ANTES de chamar o provedor.
        $id = DB::table('cobrancas')->insertGetId([
            'business_id' => $in->businessId, 'tipo' => 'boleto', 'status' => 'pending', 'valor_centavos' => $in->valorCentavos,
            'vencimento' => $in->vencimento->format('Y-m-d'), 'descricao' => $in->descricao, 'idempotency_key' => $in->idempotencyKey,
            'contact_id' => $in->contactId, 'origem_type' => $in->origemType, 'origem_id' => $in->origemId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($falha) {
            throw $falha;
        }
        DB::table('cobrancas')->where('id', $id)->update(['status' => 'emitida', 'boleto_pdf_url' => 'https://exemplo.test/b.pdf']);

        return new CobrancaEmitidaResult(cobrancaId: $id, gatewayExternalId: 'ext-' . $id, tipo: 'boleto', emitidaEm: new \DateTimeImmutable());
    };
    $gw->shouldReceive('emitirBoleto')->andReturnUsing($emitir);
    $gw->shouldReceive('emitirPix')->andReturnUsing(fn ($in, $tipo) => $emitir($in));
    $box->gw = $gw;
    app()->instance(PaymentGatewayContract::class, $gw);
}

it('DUPLA PROVA do valor: saldo em aberto = 500 − 200 + 50 = 350 = final_total − getTotalPaid, nas referências e no gateway', function () {
    $box = new stdClass();
    appPgeGateway($box);
    Passport::actingAs(appPgeUsuario($this->biz, ['financeiro.access', 'direct_sell.view', 'access_all_locations']), [], 'api');

    // Caminho (a): a conta à mão. Caminho (b): o canônico do ERP.
    $aMao = 500.00 - 200.00 + 50.00;
    $canonico = round(500.00 - (float) app(TransactionUtil::class)->getTotalPaid($this->venda), 2);
    expect($aMao)->toBe(350.0);
    expect($canonico)->toBe($aMao);

    $ref = collect($this->getJson('/api/app/pagamentos/referencias')->assertOk()->json('itens'))
        ->first(fn ($i) => $i['tipo'] === 'pedido' && $i['id'] === $this->venda);
    expect((float) $ref['valor'])->toBe(350.0);

    $r = $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'boleto', 'vencimento_dias' => 7])
        ->assertStatus(201)->json();

    expect($box->input->valorCentavos)->toBe(35000);
    expect($box->input->origemType)->toBe('sale');
    expect($box->input->origemId)->toBe($this->venda);
    expect($box->input->vencimento->format('Y-m-d'))->toBe(now()->addDays(7)->toDateString());
    expect((int) $box->account->id)->toBe((int) $this->contaPadrao->account_id);
    expect($r)->toMatchArray(['valor' => 350, 'status' => 'pendente', 'metodo' => 'boleto', 'link' => 'https://exemplo.test/b.pdf']);
    // Nada além da cobrança: o pedido e os pagamentos dele não mudam.
    expect((float) DB::table('transactions')->where('id', $this->venda)->value('final_total'))->toBe(500.0);
    expect(DB::table('transaction_payments')->where('transaction_id', $this->venda)->count())->toBe(2);
});

it('o app não manda valor: um "valor" no corpo é ignorado e o gateway recebe o saldo', function () {
    $box = new stdClass();
    appPgeGateway($box);
    Passport::actingAs(appPgeUsuario($this->biz, ['financeiro.access', 'direct_sell.view', 'access_all_locations']), [], 'api');

    $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'pix', 'vencimento_dias' => 3, 'valor' => 1.00])
        ->assertStatus(201);
    expect($box->input->valorCentavos)->toBe(35000);
});

it('segunda cobrança para o mesmo documento em aberto → 409 ja_existe com o item, e o gateway não é chamado', function () {
    $box = new stdClass();
    appPgeGateway($box);
    Passport::actingAs(appPgeUsuario($this->biz, ['financeiro.access', 'direct_sell.view', 'access_all_locations']), [], 'api');
    $corpo = ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'boleto', 'vencimento_dias' => 7];
    $primeira = $this->postJson('/api/app/pagamentos', $corpo)->assertStatus(201)->json('id');
    $box->input = null;

    $this->postJson('/api/app/pagamentos', $corpo)->assertStatus(409)
        ->assertJsonPath('erro', 'ja_existe')->assertJsonPath('item.id', $primeira);
    expect($box->input)->toBeNull();
});

it('provedor fora → 503 provedor_indisponivel e a linha pending vira erro (não aparece, não bloqueia nova tentativa)', function () {
    $box = new stdClass();
    appPgeGateway($box, new GatewayUnavailableException('fora'));
    Passport::actingAs(appPgeUsuario($this->biz, ['financeiro.access', 'direct_sell.view', 'access_all_locations']), [], 'api');

    $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'boleto', 'vencimento_dias' => 7])
        ->assertStatus(503)->assertJsonPath('erro', 'provedor_indisponivel');

    expect(DB::table('cobrancas')->where('origem_id', $this->venda)->where('origem_type', 'sale')->pluck('status')->all())->toBe(['erro']);
    expect(array_column($this->getJson('/api/app/pagamentos')->assertOk()->json('itens'), 'descricao'))
        ->each->not->toContain((string) DB::table('transactions')->where('id', $this->venda)->value('invoice_no'));
});

it('validação, cartão, documento de outro business (404) e sem conta configurada (503 sem_configuracao)', function () {
    $box = new stdClass();
    appPgeGateway($box);
    Passport::actingAs(appPgeUsuario($this->biz, ['financeiro.access', 'direct_sell.view', 'access_all_locations']), [], 'api');

    $this->postJson('/api/app/pagamentos', ['metodo' => 'pix', 'vencimento_dias' => 5])->assertStatus(422)
        ->assertJsonPath('erro', 'validacao')->assertJsonStructure(['campos' => ['referencia.tipo', 'vencimento_dias']]);
    $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'cartao', 'vencimento_dias' => 7])
        ->assertStatus(422)->assertJsonPath('campos.metodo', 'Cobrança no cartão não pode ser gerada pelo app.');

    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $this->outro->id, 'type' => 'sell', 'status' => 'final', 'final_total' => 900.00,
        'created_by' => DB::table('users')->where('business_id', $this->outro->id)->value('id') ?? 1,
        'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $alheia], 'metodo' => 'boleto', 'vencimento_dias' => 7])
        ->assertStatus(404)->assertJsonPath('erro', 'nao_encontrado');
    expect($box->input ?? null)->toBeNull();
    expect(DB::table('cobrancas')->where('origem_id', $alheia)->count())->toBe(0);

    DB::table('fin_contas_bancarias')->where('business_id', $this->biz)->update(['payment_gateway_credential_id' => null]);
    $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'boleto', 'vencimento_dias' => 7])
        ->assertStatus(503)->assertJsonPath('erro', 'sem_configuracao');
    expect($box->input ?? null)->toBeNull();
});

it('sem acesso ao Financeiro: referências e gerar → 403', function () {
    appPgeGateway(new stdClass());
    Passport::actingAs(appPgeUsuario($this->biz, ['direct_sell.view', 'access_all_locations']), [], 'api');
    $this->getJson('/api/app/pagamentos/referencias')->assertStatus(403);
    $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'boleto', 'vencimento_dias' => 7])
        ->assertStatus(403);
});

it('consultar: pago no gateway vira pago (reconciliação do webhook); cancelar recusa paga (409) e cancela aberta', function () {
    $box = new stdClass();
    appPgeGateway($box);
    Passport::actingAs(appPgeUsuario($this->biz, ['financeiro.access', 'direct_sell.view', 'access_all_locations']), [], 'api');
    $id = $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'boleto', 'vencimento_dias' => 7])
        ->assertStatus(201)->json('id');

    $box->gw->shouldReceive('consultar')->andReturn(new CobrancaStatus(status: 'paga', pagaEm: new \DateTimeImmutable('2026-10-02 10:00:00'), valorPagoCentavos: 35000, formaPagamento: 'boleto'));
    $this->postJson("/api/app/pagamentos/{$id}/consultar")->assertOk()->assertJsonPath('status', 'pago');
    expect(DB::table('cobrancas')->where('id', $id)->value('valor_pago_centavos'))->toBe(35000);

    $this->postJson("/api/app/pagamentos/{$id}/cancelar")->assertStatus(409)->assertJsonPath('erro', 'nao_cancelavel');

    // Paga no gateway NÃO baixa a venda (o saldo segue 350): cobrar de novo seria em dobro → 409.
    $box->input = null;
    $this->postJson('/api/app/pagamentos', ['referencia' => ['tipo' => 'pedido', 'id' => $this->venda], 'metodo' => 'pix', 'vencimento_dias' => 3])
        ->assertStatus(409)->assertJsonPath('erro', 'ja_existe')->assertJsonPath('item.id', $id)->assertJsonPath('item.status', 'pago');
    expect($box->input)->toBeNull();

    $aberta = DB::table('cobrancas')->insertGetId([
        'business_id' => $this->biz, 'tipo' => 'boleto', 'status' => 'emitida', 'valor_centavos' => 1000,
        'vencimento' => now()->addDays(3)->toDateString(), 'descricao' => 'APP cancelar', 'idempotency_key' => (string) Str::uuid(),
        'gateway_external_id' => 'ext-c', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $box->gw->shouldReceive('cancelar')->once()->andReturnUsing(fn ($c) => DB::table('cobrancas')->where('id', $c->id)->update(['status' => 'cancelada']));
    $this->postJson("/api/app/pagamentos/{$aberta}/cancelar")->assertOk()->assertJsonPath('status', 'cancelado');
});

it('consultar/cancelar cobrança de outro business → 404 e nada muda', function () {
    $box = new stdClass();
    appPgeGateway($box);
    Passport::actingAs(appPgeUsuario($this->biz, ['financeiro.access', 'direct_sell.view', 'access_all_locations']), [], 'api');
    $alheia = DB::table('cobrancas')->insertGetId([
        'business_id' => $this->outro->id, 'tipo' => 'boleto', 'status' => 'emitida', 'valor_centavos' => 1000,
        'vencimento' => now()->addDays(3)->toDateString(), 'descricao' => 'APP alheia', 'idempotency_key' => (string) Str::uuid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson("/api/app/pagamentos/{$alheia}/consultar")->assertStatus(404);
    $this->postJson("/api/app/pagamentos/{$alheia}/cancelar")->assertStatus(404);
    expect(DB::table('cobrancas')->where('id', $alheia)->value('status'))->toBe('emitida');
});
