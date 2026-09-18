<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\PlanoConta;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Onda 25 (2026-05-25) US-FIN-021 — Insert manual de título via TituloCreateSheet.
 *
 * Cobre 6 invariantes:
 *  (1) POST /unificado tipo=receber cria Titulo aberto com numero R-NNNNN
 *  (2) POST /unificado tipo=pagar cria Titulo aberto com numero P-NNNNN
 *  (3) tipo ausente → 422
 *  (4) valor_total ausente → 422
 *  (5) plano de outro business → rejeitado (Tier 0 ADR 0093)
 *  (6) plano incompatível com tipo (receber + despesa) → 422
 *
 * Padrão graceful skip Jana/Repair/Copiloto.
 */

function storeBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return [$business, $user];
}

function storeCreatePlano(int $businessId, string $codigo, string $tipo): PlanoConta
{
    return PlanoConta::firstOrCreate(
        ['business_id' => $businessId, 'codigo' => $codigo],
        [
            'nome'              => "TEST {$codigo} {$tipo}",
            'tipo'              => $tipo,
            'nivel'             => 4,
            'natureza'          => in_array($tipo, ['receita', 'passivo', 'patrimonio'], true) ? 'credito' : 'debito',
            'aceita_lancamento' => true,
            'protegido'         => false,
            'ativo'             => true,
        ]
    );
}

/**
 * Remove titulo criado PELO teste, pelo query builder de proposito.
 *
 * `fin_titulos` e append-only por dominio: Titulo::delete() lanca DomainException
 * (Titulo.php:121) e forceDelete() do SoftDeletes chama delete() internamente, entao
 * a forma Eloquent SEMPRE estourou aqui -- o caso provava tudo e morria no TEARDOWN.
 * Mesmo padrao dos irmaos TituloCriadoEventTest e BaixaConservacaoValorContratoTest:96.
 */
function storeCleanupTitulo(Titulo $titulo): void
{
    DB::table('fin_titulo_baixas')->where('titulo_id', $titulo->id)->delete();
    DB::table('fin_titulos')->where('id', $titulo->id)->delete();
}

it('store tipo=receber cria Titulo aberto', function () {
    [$business, $user] = storeBootstrap();

    $response = $this->actingAs($user)->post('/financeiro/unificado', [
        'tipo'              => 'receber',
        'valor_total'       => 250.50,
        'vencimento'        => now()->addDays(15)->toDateString(),
        'cliente_descricao' => 'Cliente teste recebimento',
        'categoria_id'      => null,
        'plano_conta_id'    => null,
        'observacoes'       => null,
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();

    $created = Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'Cliente teste recebimento')
        ->latest('id')
        ->first();

    expect($created)->not->toBeNull();
    expect($created->tipo)->toBe('receber');
    expect($created->status)->toBe('aberto');
    expect((float) $created->valor_total)->toBe(250.50);
    expect((float) $created->valor_aberto)->toBe(250.50);
    expect($created->numero)->toStartWith('R-');
    expect($created->origem)->toBe('manual');
    expect($created->created_by)->toBe($user->id);

    storeCleanupTitulo($created);
});

it('store tipo=pagar cria Titulo aberto', function () {
    [$business, $user] = storeBootstrap();

    $response = $this->actingAs($user)->post('/financeiro/unificado', [
        'tipo'              => 'pagar',
        'valor_total'       => 100.00,
        'vencimento'        => now()->addDays(7)->toDateString(),
        'cliente_descricao' => 'Fornecedor teste pagamento',
        'categoria_id'      => null,
        'plano_conta_id'    => null,
        'observacoes'       => null,
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();

    $created = Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'Fornecedor teste pagamento')
        ->latest('id')
        ->first();

    expect($created)->not->toBeNull();
    expect($created->tipo)->toBe('pagar');
    expect($created->numero)->toStartWith('P-');

    storeCleanupTitulo($created);
});

it('rejeita store sem tipo (422)', function () {
    [$business, $user] = storeBootstrap();

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->post('/financeiro/unificado', [
            'valor_total' => 50,
            'vencimento'  => now()->addDays(5)->toDateString(),
        ]);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect(in_array($response->status(), [302, 422], true))->toBeTrue();
    $response->assertSessionHasErrors('tipo');
});

it('rejeita store sem valor_total (422)', function () {
    [$business, $user] = storeBootstrap();

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->post('/financeiro/unificado', [
            'tipo'       => 'receber',
            'vencimento' => now()->addDays(5)->toDateString(),
        ]);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect(in_array($response->status(), [302, 422], true))->toBeTrue();
    $response->assertSessionHasErrors('valor_total');
});

it('rejeita plano de outro business no store (Tier 0 ADR 0093)', function () {
    [$business, $user] = storeBootstrap();

    // O "outro business" precisa EXISTIR de verdade. O insert sintetico que estava
    // aqui nao passava `owner_id`, e `business.owner_id` tem FK NOT NULL pra `users`
    // -- estourava SQLSTATE 23000/1452 e o caso morria ANTES de exercer a rejeicao,
    // ou seja: este [T0] cross-tenant NUNCA foi provado. Padrao canonico do proprio
    // modulo: ExtratoControllerTest:96, BackfillExtratoOfxTest:256,
    // BridgeExpenseToTitulosCommandTest:281. Em CI o seed garante um 2o business real
    // exatamente "pros testes de isolamento multi-tenant" (pest-mysql-setup).
    $otherBusiness = Business::where('id', '!=', $business->id)->first();

    if (! $otherBusiness) {
        test()->markTestSkipped('Sem segundo business no banco pra provar isolamento cross-tenant.');
    }

    $otherBizId = (int) $otherBusiness->id;

    $planoCrossTenant = storeCreatePlano($otherBizId, '3.1.01.XST', 'receita');

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->post('/financeiro/unificado', [
            'tipo'              => 'receber',
            'valor_total'       => 50,
            'vencimento'        => now()->addDays(5)->toDateString(),
            'cliente_descricao' => 'Tentativa cross-tenant store',
            'plano_conta_id'    => $planoCrossTenant->id,
        ]);

    if (in_array($response->status(), [403, 404], true)) {
        $planoCrossTenant->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect(in_array($response->status(), [302, 422], true))->toBeTrue();
    $response->assertSessionHasErrors('plano_conta_id');

    // Não criou Titulo.
    $exists = Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'Tentativa cross-tenant store')
        ->exists();
    expect($exists)->toBeFalse();

    // So o plano sai: o business agora e REAL e do seed -- apagar seria destruir
    // fixture compartilhada das outras lanes.
    $planoCrossTenant->forceDelete();
});

it('rejeita plano de tipo incompatível no store (receber + despesa)', function () {
    [$business, $user] = storeBootstrap();

    $planoDespesa = storeCreatePlano($business->id, '5.1.99.STT', 'despesa');

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->post('/financeiro/unificado', [
            'tipo'              => 'receber',
            'valor_total'       => 50,
            'vencimento'        => now()->addDays(5)->toDateString(),
            'cliente_descricao' => 'Tentativa incoerente store',
            'plano_conta_id'    => $planoDespesa->id,
        ]);

    if (in_array($response->status(), [403, 404], true)) {
        $planoDespesa->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect($response->status())->toBe(422);

    $exists = Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'Tentativa incoerente store')
        ->exists();
    expect($exists)->toBeFalse();

    $planoDespesa->forceDelete();
});
