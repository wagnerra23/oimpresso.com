<?php

declare(strict_types=1);
// Cobre UC-IMPOS-01, UC-IMPOS-02, UC-IMPOS-03, UC-IMPOS-04 (Configuracoes/Impostos/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/05, F3 — /tax-rates em Inertia atrás da flag useV2ConfiguracoesImpostos.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * store/update/destroy não mudaram: ImpostosBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('tax_rates') || ! Schema::hasTable('group_sub_taxes')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['tax_rate.view', 'tax_rate.update'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function impContratoAliquota(int $businessId, int $criadoPor, string $nome, float $valor, int $grupo = 0): int
{
    return DB::table('tax_rates')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'amount' => $valor, 'is_tax_group' => $grupo,
        'for_tax_group' => 0, 'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('UC-IMPOS-01 com a flag ligada, GET /tax-rates renderiza Inertia (não a DataTable), com as permissões', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesImpostos']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/tax-rates');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Impostos/Index');
    expect($r->json('props.pode.editar'))->toBeTrue();
    expect($r->json('props.pode.criar'))->toBeFalse();
    expect($r->json('props.pode.excluir'))->toBeFalse();
});

test('UC-IMPOS-02 com a flag desligada, GET /tax-rates segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/tax-rates')->assertOk()->assertViewIs('tax_rate.index');
});

test('UC-IMPOS-03 Tier 0 e valor — alíquotas e grupos do negócio, com o número do banco', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesImpostos']);
    $outro = $this->seededSupportClientTenant();
    $pis = impContratoAliquota($this->business->id, $this->user->id, 'PIS '.uniqid(), 1.65);
    $cofins = impContratoAliquota($this->business->id, $this->user->id, 'COFINS '.uniqid(), 7.6);
    $grupo = impContratoAliquota($this->business->id, $this->user->id, 'PIS+COFINS '.uniqid(), 9.25, 1);
    DB::table('group_sub_taxes')->insert([['group_tax_id' => $grupo, 'tax_id' => $pis], ['group_tax_id' => $grupo, 'tax_id' => $cofins]]);
    $alheia = impContratoAliquota($outro->id, $this->user->id, 'Alheia '.uniqid(), 18);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/Impostos/Index', 'X-Inertia-Partial-Data' => 'impostos',
    ])->get('/tax-rates');
    $r->assertOk();
    $aliquotas = collect($r->json('props.impostos.aliquotas') ?? [])->keyBy('id');
    $grupos = collect($r->json('props.impostos.grupos') ?? [])->keyBy('id');

    expect($aliquotas->has($pis))->toBeTrue();
    expect($aliquotas->has($alheia))->toBeFalse();
    expect($aliquotas->has($grupo))->toBeFalse();
    expect($aliquotas[$pis]['aliquota'])->toBe(1.65);
    expect($aliquotas[$cofins]['aliquota'])->toBe(7.6);
    expect($aliquotas[$pis]['em_grupo'])->toBeTrue();
    expect($grupos[$grupo]['aliquota'])->toBe(9.25);
    expect(count($grupos[$grupo]['sub_impostos']))->toBe(2);
});

test('UC-IMPOS-04 valor — o texto que o drawer monta volta ao banco como o mesmo número (cadastro e edição)', function () {
    $util = new \App\Utils\Util;
    $ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    $this->actingAs($this->usuarioComPermissoes(['tax_rate.view', 'tax_rate.create', 'tax_rate.update'], $this->business));
    // O paraTexto() do Index.tsx: pt-BR, 2 a 4 casas, SEM separador de milhar (useGrouping: false).
    $casos = ['1,65' => 1.65, '7,60' => 7.6, '18,00' => 18.0, '1234,50' => 1234.5, '0,1250' => 0.125];

    foreach ($casos as $texto => $esperado) {
        $nome = 'Drawer '.uniqid();
        $r = $this->withHeaders($ajax)->post('/tax-rates', ['name' => $nome, 'amount' => $texto]);
        expect($r->json('success'))->toBeTrue();
        $id = (int) DB::table('tax_rates')->where('business_id', $this->business->id)->where('name', $nome)->value('id');
        // Caminho 1: o endpoint gravou. Caminho 2: num_uf direto sobre o mesmo texto.
        expect((float) DB::table('tax_rates')->where('id', $id)->value('amount'))->toBe($esperado);
        expect((float) $util->num_uf($texto))->toBe($esperado);

        // Editar sem mexer no percentual: a tela reenvia paraTexto(aliquota) e o número não pode andar.
        $this->withHeaders($ajax)->put("/tax-rates/{$id}", ['name' => $nome.' editada', 'amount' => $texto])->assertOk();
        expect((float) DB::table('tax_rates')->where('id', $id)->value('amount'))->toBe($esperado);
    }
});
