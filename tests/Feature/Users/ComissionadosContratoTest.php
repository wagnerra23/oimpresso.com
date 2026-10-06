<?php

declare(strict_types=1);
// Cobre UC-CMSN-01, UC-CMSN-02, UC-CMSN-03, UC-CMSN-04, UC-CMSN-05 (Comissionados/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/03 — /sales-commission-agents em Inertia.
 *
 * O store/update NAO mudou de forma: recebe o percentual como texto pt-BR (o que o input_number
 * da Blade mandava) e o `num_uf` converte. O caso de valor prova o mesmo numero por dois caminhos:
 * o endpoint gravando e o `num_uf` aplicado ao texto que a tela nova monta (regra mestre de valor).
 * Tenant de teste 98 x cliente ficticio 99 (ADR 0358).
 *
 * Nomes de funcao com prefixo `cmsn` de proposito: o SalesCommissionAgentGuardTest, na mesma
 * pasta, ja declara `agenteDoNegocio`/`operadorComPermissoes` no escopo global.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'is_cmmsn_agnt')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['commission_agent.view', 'commission_agent.manage'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function cmsnAgente(int $businessId, float $percentual = 5): \App\User
{
    return \App\User::factory()->create([
        'business_id' => $businessId, 'is_cmmsn_agnt' => 1, 'cmmsn_percent' => $percentual, 'allow_login' => 0,
    ]);
}

function cmsnAgentesDeferidos($teste): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'Comissionados/Index',
        'X-Inertia-Partial-Data' => 'agentes',
    ])->get('/sales-commission-agents');
    $r->assertOk();

    return $r->json('props.agentes') ?? [];
}

test('UC-CMSN-01 GET /sales-commission-agents renderiza Inertia Comissionados/Index (não a DataTable)', function () {
    // X-Requested-With junto do X-Inertia, como o browser manda: o ramo ajax() antigo engolia isso.
    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/sales-commission-agents');

    $r->assertOk();
    expect($r->json('component'))->toBe('Comissionados/Index');
    expect($r->json('props.pode.gerenciar'))->toBeTrue();
});

test('UC-CMSN-02 Tier 0 — a lista só traz comissionado do negócio da sessão, com as vendas vinculadas', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = cmsnAgente($this->business->id);
    $alheio = cmsnAgente($outro->id);

    $location = DB::table('business_locations')->where('business_id', $this->business->id)->value('id');
    $contato = DB::table('contacts')->where('business_id', $this->business->id)->value('id');
    expect($location)->not->toBeNull();
    expect($contato)->not->toBeNull();
    foreach ([1, 2] as $_) {
        DB::table('transactions')->insert([
            'business_id' => $this->business->id, 'location_id' => $location, 'type' => 'sell', 'status' => 'final',
            'payment_status' => 'due', 'contact_id' => $contato, 'transaction_date' => now(),
            'final_total' => 100, 'total_before_tax' => 100, 'created_by' => $this->user->id,
            'commission_agent' => $meu->id, 'invoice_no' => 'CMSN-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $ids = collect(cmsnAgentesDeferidos($this))->keyBy('id');

    expect($ids->has($meu->id))->toBeTrue('o comissionado do próprio negócio tem de aparecer (anti-vácuo)');
    expect($ids->has($alheio->id))->toBeFalse();
    expect($ids[$meu->id]['vendas'])->toBe(2);
});

test('UC-CMSN-03 valor — o percentual chega igual por dois caminhos, no cadastro e na edição', function () {
    $util = new \App\Utils\Util;
    $h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    // Texto que a tela nova monta (paraTexto = toLocaleString pt-BR, 2 casas) e o que a Blade mandava.
    $casos = ['2,50' => 2.5, '3,75' => 3.75, '10' => 10.0, '0,00' => 0.0];

    foreach ($casos as $texto => $esperado) {
        $nome = 'Agente '.uniqid();
        $r = $this->withHeaders($h)->post('/sales-commission-agents', ['first_name' => $nome, 'cmmsn_percent' => $texto]);
        $r->assertOk();
        expect($r->json('success'))->toBeTrue();

        $gravado = (float) DB::table('users')->where('business_id', $this->business->id)->where('first_name', $nome)->value('cmmsn_percent');
        // Caminho 1: o endpoint gravou. Caminho 2: num_uf direto sobre o mesmo texto.
        expect($gravado)->toBe($esperado);
        expect((float) $util->num_uf($texto))->toBe($esperado);
    }

    $agente = cmsnAgente($this->business->id, 5);
    $this->withHeaders($h)->post("/sales-commission-agents/{$agente->id}", [
        '_method' => 'PUT', 'first_name' => 'Editado', 'cmmsn_percent' => '1,25',
    ])->assertOk();
    expect((float) DB::table('users')->where('id', $agente->id)->value('cmmsn_percent'))->toBe(1.25);
});

test('UC-CMSN-04 Tier 0 — o formulário de edição não abre comissionado de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $alheio = cmsnAgente($outro->id);
    $meu = cmsnAgente($this->business->id);

    $this->get("/sales-commission-agents/{$alheio->id}/edit")->assertNotFound();
    // Contraprova: o do próprio negócio abre (sem isto, o 404 acima podia ser a rota quebrada).
    $this->get("/sales-commission-agents/{$meu->id}/edit")->assertOk();
});

test('UC-CMSN-05 quem só tem commission_agent.view abre a tela sem as ações', function () {
    $leitor = $this->usuarioComPermissoes(['commission_agent.view'], $this->business);
    expect($leitor->can('commission_agent.manage'))->toBeFalse();
    $this->actingAs($leitor);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia])->get('/sales-commission-agents');

    $r->assertOk();
    expect($r->json('component'))->toBe('Comissionados/Index');
    expect($r->json('props.pode.gerenciar'))->toBeFalse();
});
