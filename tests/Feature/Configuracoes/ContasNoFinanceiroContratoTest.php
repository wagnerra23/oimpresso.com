<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato: as contas do núcleo (`accounts`) passam a ser cadastradas na tela do Financeiro
 * (`/financeiro/contas-bancarias`) — decisão [W] 2026-10-27, opção A. Casos: Financeiro/ContasBancarias/Index.casos.md.
 *
 * Saldo e saldo de abertura são valor (regra mestre): a tela do Financeiro usa o MESMO serviço
 * (`App\Services\Contas\ContasDoNucleo`) que o `AccountController` — e cada caso de valor prova o número por
 * dois caminhos (à mão × banco/tela). Prefixo `contasFin` (escopo global do Pest). Tenant 98 × 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('accounts') || ! Schema::hasTable('fin_contas_bancarias')) {
        $this->markTestSkipped('Schema UltimatePOS/Financeiro ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['financeiro.contas_bancarias.manage', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
});

function contasFinConta(int $businessId, int $criadoPor, array $campos = []): int
{
    return DB::table('accounts')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Conta '.uniqid(), 'account_number' => 'N'.uniqid(),
        'created_by' => $criadoPor, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function contasFinLista($teste, string $situacao = 'ativa'): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/financeiro/contas-bancarias?situacao='.$situacao);
    $r->assertOk();

    return collect($r->json('props.accounts'))->keyBy('name');
}

test('UC-CTB-05 a lista traz só as contas da empresa, com o saldo do mesmo SQL do núcleo — por dois caminhos', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = 'Banco Meu '.uniqid();
    $alheia = 'Banco Alheio '.uniqid();
    $id = contasFinConta($this->business->id, $this->user->id, ['name' => $minha]);
    contasFinConta($outro->id, $this->user->id, ['name' => $alheia]);
    foreach ([['credit', 1000.50, null], ['debit', 200.25, null], ['credit', 999.00, now()]] as [$tipo, $valor, $apagado]) {
        DB::table('account_transactions')->insert([
            'account_id' => $id, 'type' => $tipo, 'amount' => $valor, 'operation_date' => now(),
            'created_by' => $this->user->id, 'deleted_at' => $apagado, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $lista = contasFinLista($this);

    expect($lista->keys()->implode('|'))->not->toContain($alheia);
    // Caminho 1: à mão. 1000,50 − 200,25 = 800,25 (o crédito apagado não entra).
    expect(round(1000.50 - 200.25, 2))->toBe(800.25);
    // Caminho 2: a prop da tela, número e texto formatado pelo num_f.
    expect(round((float) $lista[$minha]['saldo'], 2))->toBe(800.25);
    expect($lista[$minha]['saldo_texto'])->toContain('800,25');
});

test('UC-CTB-06 nova conta grava em accounts da empresa da sessão, com o saldo de abertura pelo num_uf — por dois caminhos', function () {
    $nome = 'Nova '.uniqid();

    $this->post('/financeiro/contas-bancarias', [
        'name' => $nome, 'account_number' => '12345-6', 'opening_balance' => '1.234,56',
        'account_details' => [['label' => 'Agência', 'value' => '0001'], ['label' => '', 'value' => '']],
        'business_id' => 99999,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $conta = DB::table('accounts')->where('name', $nome)->first();
    expect((int) $conta->business_id)->toBe($this->business->id);
    expect(json_decode($conta->account_details, true))->toBe([['label' => 'Agência', 'value' => '0001']]);

    // Caminho 1: o num_uf do núcleo sobre o mesmo texto. Caminho 2: o crédito gravado.
    $esperado = round((float) app(\App\Utils\Util::class)->num_uf('1.234,56'), 2);
    expect($esperado)->toBe(1234.56);
    $abertura = DB::table('account_transactions')->where('account_id', $conta->id)->where('sub_type', 'opening_balance')->get();
    expect($abertura)->toHaveCount(1);
    expect($abertura[0]->type)->toBe('credit');
    expect(round((float) $abertura[0]->amount, 2))->toBe($esperado);
});

test('UC-CTB-07 sem saldo de abertura não nasce lançamento', function () {
    $nome = 'Sem abertura '.uniqid();
    $this->post('/financeiro/contas-bancarias', ['name' => $nome])->assertSessionHasNoErrors();

    $id = DB::table('accounts')->where('name', $nome)->value('id');
    expect(DB::table('account_transactions')->where('account_id', $id)->count())->toBe(0);
});

test('UC-CTB-08 tipo de conta de outra empresa é recusado', function () {
    $outro = $this->seededSupportClientTenant();
    $tipoAlheio = DB::table('account_types')->insertGetId(['business_id' => $outro->id, 'name' => 'Alheio '.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
    $nome = 'Tipo alheio '.uniqid();

    $this->post('/financeiro/contas-bancarias', ['name' => $nome, 'account_type_id' => $tipoAlheio])
        ->assertSessionHasErrors('account_type_id');

    expect(DB::table('accounts')->where('name', $nome)->exists())->toBeFalse();
});

test('UC-CTB-09 editar muda o cadastro e não toca no saldo; conta de outra empresa dá 404', function () {
    $id = contasFinConta($this->business->id, $this->user->id);
    DB::table('account_transactions')->insert(['account_id' => $id, 'type' => 'credit', 'amount' => 50, 'operation_date' => now(), 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
    $outro = $this->seededSupportClientTenant();
    $alheia = contasFinConta($outro->id, $this->user->id, ['name' => 'Intocada']);

    $this->put("/financeiro/contas-bancarias/{$id}/cadastro", ['name' => 'Renomeada', 'note' => 'obs'])->assertSessionHasNoErrors();
    $this->put("/financeiro/contas-bancarias/{$alheia}/cadastro", ['name' => 'Invadida'])->assertNotFound();

    expect(DB::table('accounts')->where('id', $id)->value('name'))->toBe('Renomeada');
    expect(DB::table('accounts')->where('id', $alheia)->value('name'))->toBe('Intocada');
    expect((float) DB::table('account_transactions')->where('account_id', $id)->sum('amount'))->toBe(50.0);
});

test('UC-CTB-10 encerrar tira da lista de ativas, reativar devolve; outra empresa dá 404', function () {
    $nome = 'Ciclo '.uniqid();
    $id = contasFinConta($this->business->id, $this->user->id, ['name' => $nome]);
    $outro = $this->seededSupportClientTenant();
    $alheia = contasFinConta($outro->id, $this->user->id);

    $this->post("/financeiro/contas-bancarias/{$id}/encerrar")->assertRedirect();
    expect(contasFinLista($this, 'ativa')->has($nome))->toBeFalse();
    expect(contasFinLista($this, 'encerrada')->has($nome))->toBeTrue();

    $this->post("/financeiro/contas-bancarias/{$id}/reativar")->assertRedirect();
    expect(contasFinLista($this, 'ativa')->has($nome))->toBeTrue();

    $this->post("/financeiro/contas-bancarias/{$alheia}/encerrar")->assertNotFound();
    expect((int) DB::table('accounts')->where('id', $alheia)->value('is_closed'))->toBe(0);
});

test('UC-CTB-11 sem a permissão do Financeiro, criar dá 403', function () {
    $semPermissao = $this->usuarioComPermissoes(['access_all_locations'], $this->business);
    $this->actingAs($semPermissao);
    $nome = 'Bloqueada '.uniqid();

    $this->post('/financeiro/contas-bancarias', ['name' => $nome])->assertForbidden();

    expect(DB::table('accounts')->where('name', $nome)->exists())->toBeFalse();
});
