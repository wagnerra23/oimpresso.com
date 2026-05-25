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
 * Onda 24 (2026-05-25) US-FIN-021 — Plano de Contas BR no Edit do título.
 *
 * Cobre 4 invariantes:
 *  (1) update salva plano_conta_id quando válido
 *  (2) update com plano_conta_id=null limpa o campo
 *  (3) plano de outro business é rejeitado 422 (Tier 0 ADR 0093)
 *  (4) plano de tipo incompatível (ex: receber + despesa) rejeitado 422
 *
 * Padrão graceful skip Jana/Repair/Copiloto: pula quando DB greenfield ou
 * subscription gate bloqueia financeiro_module no env atual.
 */

function planoContaBootstrap(): array
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

function planoContaCreatePlano(int $businessId, string $codigo, string $tipo): PlanoConta
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

function planoContaCreateTitulo(int $businessId, int $userId, string $tipo): Titulo
{
    return Titulo::create([
        'business_id'       => $businessId,
        'numero'            => 'TEST-'.bin2hex(random_bytes(4)),
        'tipo'              => $tipo,
        'status'            => 'aberto',
        'cliente_descricao' => 'Teste Onda 24',
        'valor_total'       => 100.00,
        'valor_aberto'      => 100.00,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(15)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'origem_id'         => null,
        'created_by'        => $userId,
    ]);
}

it('update salva plano_conta_id quando válido', function () {
    [$business, $user] = planoContaBootstrap();

    $plano = planoContaCreatePlano($business->id, '3.1.01.TST', 'receita');
    $titulo = planoContaCreateTitulo($business->id, $user->id, 'receber');

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => 'Atualizado teste',
        'observacoes'       => null,
        'categoria_id'      => null,
        'plano_conta_id'    => $plano->id,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        $plano->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBe($plano->id);

    $titulo->forceDelete();
    $plano->forceDelete();
});

it('update com plano_conta_id null limpa o campo', function () {
    [$business, $user] = planoContaBootstrap();

    $plano = planoContaCreatePlano($business->id, '3.1.01.TST', 'receita');
    $titulo = planoContaCreateTitulo($business->id, $user->id, 'receber');
    $titulo->update(['plano_conta_id' => $plano->id]);

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => 'Sem plano',
        'observacoes'       => null,
        'categoria_id'      => null,
        'plano_conta_id'    => null,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        $plano->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBeNull();

    $titulo->forceDelete();
    $plano->forceDelete();
});

it('rejeita plano de outro business (Tier 0 ADR 0093)', function () {
    [$business, $user] = planoContaBootstrap();

    // Cria business B falso (id absurdo evita colisão com seeders).
    $otherBizId = (int) (DB::table('business')->max('id') ?? 0) + 99999;
    DB::table('business')->insert([
        'id'         => $otherBizId,
        'name'       => 'TEST-OTHER-BIZ',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $planoCrossTenant = planoContaCreatePlano($otherBizId, '3.1.01.XTN', 'receita');
    $titulo = planoContaCreateTitulo($business->id, $user->id, 'receber');

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => 'Tentativa cross-tenant',
        'observacoes'       => null,
        'categoria_id'      => null,
        'plano_conta_id'    => $planoCrossTenant->id,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        $planoCrossTenant->forceDelete();
        DB::table('business')->where('id', $otherBizId)->delete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    // Laravel ValidationException retorna 302 com errors flash (Inertia/HTTP).
    expect(in_array($response->status(), [302, 422], true))->toBeTrue();
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBeNull();

    $titulo->forceDelete();
    $planoCrossTenant->forceDelete();
    DB::table('business')->where('id', $otherBizId)->delete();
});

it('rejeita plano de tipo incompatível (receber + despesa)', function () {
    [$business, $user] = planoContaBootstrap();

    $planoDespesa = planoContaCreatePlano($business->id, '5.1.99.TST', 'despesa');
    $titulo = planoContaCreateTitulo($business->id, $user->id, 'receber');

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => 'Tentativa incoerente',
        'observacoes'       => null,
        'categoria_id'      => null,
        'plano_conta_id'    => $planoDespesa->id,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        $titulo->forceDelete();
        $planoDespesa->forceDelete();
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    expect($response->status())->toBe(422);
    $titulo->refresh();
    expect($titulo->plano_conta_id)->toBeNull();

    $titulo->forceDelete();
    $planoDespesa->forceDelete();
});
