<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-OP-22 — quem só pode um local só vê e só grava ordem nesse local.
 * Auditoria do Manufacturing, lote 2.5 (eixo 5 M6).
 *
 * Contrato: a regra de local do sistema (`User::permitted_locations()`, a mesma que o dropdown de local
 * da ordem e o relatório de totais do módulo já aplicam — `ManufacturingUtil::getProductionTotals`).
 * Usuário sem `access_all_locations` só alcança os locais que tem como `location.{id}`. O dropdown
 * escondia os outros; o servidor não conferia, então a lista, o detalhe e a gravação aceitavam todos.
 *
 * Dois locais da mesma empresa: A (permitido) e B (não). Cada recusa tem a âncora em A.
 *
 * O pacote vem de um ModuleUtil parcial (o pacote semeado na lane não é o que se testa); as permissões
 * são reais. Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'products', 'variations', 'mfg_recipes', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);
});

function mfgLpLocal(string $nome): int
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => $nome.' '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

/** Usuário de produção, sem `access_all_locations`, com o local `$permitido` dado direto (é o que `permitted_locations()` lê). */
function mfgLpUsuario($teste, int $permitido): \App\User
{
    $user = $teste->usuarioComPermissoes(['manufacturing.access_production'], \App\Business::find(MFG_EMP_BIZ));
    $user->givePermissionTo(Permission::findOrCreate('location.'.$permitido, 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return \App\User::findOrFail($user->id);
}

function mfgLpPayload(int $variationId, int $localId, array $ingredientes): array
{
    $empresa = DB::table('business')->where('id', MFG_EMP_BIZ)->first(['date_format', 'time_format']);
    $formato = $empresa->date_format.((string) $empresa->time_format === '12' ? ' h:i A' : ' H:i');

    return [
        'transaction_date' => now()->format($formato), 'location_id' => $localId, 'final_total' => '15',
        'variation_id' => $variationId, 'quantity' => '1', 'ingredients' => $ingredientes,
    ];
}

function mfgLpOrdemCrua(int $local, int $userId): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'location_id' => $local, 'type' => 'production_purchase', 'status' => 'pending',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 15, 'mfg_is_final' => 0,
        'ref_no' => 'OP-LOC-'.random_int(1000, 9999), 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mfgLpUltimaOrdem(): int
{
    return (int) DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
}

describe('UC-OP-22 — ordem só no local permitido', function () {
    it('UC-OP-22 gravar no local não permitido dá 403 e não grava; no permitido grava', function () {
        $a = mfgLpLocal('Local A');
        $b = mfgLpLocal('Local B');
        $user = mfgLpUsuario($this, $a);
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo local permitido');
        $antes = DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->count();

        $this->actingAs($user)->post('/manufacturing/production', mfgLpPayload($acabado, $b, [$linha => ['quantity' => '2']]))
            ->assertForbidden();
        expect(DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->count())->toBe($antes);

        $this->actingAs($user)->post('/manufacturing/production', mfgLpPayload($acabado, $a, [$linha => ['quantity' => '2']]))
            ->assertRedirect('/manufacturing/production');
        expect((int) DB::table('transactions')->where('id', mfgLpUltimaOrdem())->value('location_id'))->toBe($a);
    });

    it('UC-OP-22 editar o rascunho para o local não permitido dá 403 e o local não muda', function () {
        $a = mfgLpLocal('Local A');
        $b = mfgLpLocal('Local B');
        $user = mfgLpUsuario($this, $a);
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo rascunho local');
        $this->actingAs($user)->post('/manufacturing/production', mfgLpPayload($acabado, $a, [$linha => ['quantity' => '2']]))
            ->assertRedirect('/manufacturing/production');
        $ordem = mfgLpUltimaOrdem();
        $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
        $linhaVenda = (int) DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('id');

        $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgLpPayload($acabado, $b, [$linhaVenda => ['quantity' => '2']]))
            ->assertForbidden();
        expect((int) DB::table('transactions')->where('id', $ordem)->value('location_id'))->toBe($a);

        // Âncora: o mesmo PUT no local permitido passa.
        $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgLpPayload($acabado, $a, [$linhaVenda => ['quantity' => '3']]))
            ->assertRedirect('/manufacturing/production');
    });

    it('UC-OP-22 a lista só traz ordem do local permitido', function () {
        $a = mfgLpLocal('Local A');
        $b = mfgLpLocal('Local B');
        $user = mfgLpUsuario($this, $a);
        $ordemA = mfgLpOrdemCrua($a, $user->id);
        $ordemB = mfgLpOrdemCrua($b, $user->id);

        $resp = $this->actingAs($user)->get('/manufacturing/production');
        $resp->assertOk();
        $ids = array_map(fn ($o) => (int) $o['id'], (array) data_get($resp->viewData('page'), 'props.productions', []));

        expect($ids)->toContain($ordemA);
        expect($ids)->not->toContain($ordemB);
    });

    it('UC-OP-22 o detalhe da ordem de local não permitido dá 403; o do permitido abre', function () {
        $a = mfgLpLocal('Local A');
        $b = mfgLpLocal('Local B');
        $user = mfgLpUsuario($this, $a);
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo detalhe local');
        $this->actingAs($user)->post('/manufacturing/production', mfgLpPayload($acabado, $a, [$linha => ['quantity' => '2']]))
            ->assertRedirect('/manufacturing/production');
        $ordemA = mfgLpUltimaOrdem();
        $ordemB = mfgLpOrdemCrua($b, $user->id);

        $this->actingAs($user)->get('/manufacturing/production/'.$ordemB)->assertForbidden();
        $this->actingAs($user)->get('/manufacturing/production/'.$ordemA)->assertOk();
    });
});
