<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Compras\Services\ComprasService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * CU-COM-05 no detalhe e nos KPIs do cockpit (lote 2.2 da auditoria de Compras, D2).
 *
 * A listagem e o sumário já aplicavam `permitted_locations()` (UC-CMP-08). O detalhe
 * (`ComprasService::buscarDetalhe`) e os KPIs (`calcularKpis`) não: um usuário restrito à Loja A
 * abria por id a compra da Loja B e via os KPIs somando as duas.
 *
 * Tenant fictício 98 (ADR 0358). Duas lojas NOVAS por teste, para as contagens independentes
 * (consulta direta ao banco) não dependerem de compra que outro teste deixou no tenant.
 *
 * @see memory/requisitos/Compras/SDD-tela-cockpit-compras-v1.0.md §6 CU-COM-05
 * @see resources/js/Pages/Compras/Index.casos.md — UC-CMP-12 · UC-CMP-13
 * @covers-uc UC-CMP-12
 * @covers-uc UC-CMP-13
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Exige o schema MySQL do UltimatePOS (lane compras-pest).');
    }

    $this->biz = $this->seededTenant();

    $scheme = DB::table('invoice_schemes')->value('id')
        ?? DB::table('invoice_schemes')->insertGetId(['business_id' => $this->biz->id, 'name' => 'CI Scheme 2.2', 'scheme_type' => 'blank', 'created_at' => now(), 'updated_at' => now()]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?? DB::table('invoice_layouts')->insertGetId(['business_id' => $this->biz->id, 'name' => 'CI Layout 2.2', 'created_at' => now(), 'updated_at' => now()]);

    $loja = fn (string $nome) => DB::table('business_locations')->insertGetId([
        'business_id' => $this->biz->id, 'name' => $nome, 'location_id' => 'L22'.substr(uniqid(), -6),
        'country' => 'BR', 'state' => 'SC', 'city' => 'Teste', 'zip_code' => '0000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->locA = $loja('Loja A (2.2)');
    $this->locB = $loja('Loja B (2.2)');

    $role = Role::create(['name' => 'Compras22'.uniqid().'#'.$this->biz->id, 'business_id' => $this->biz->id, 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('compras.view', 'web'));

    // `permitted_locations()` lê as permissões DIRETAS do usuário: location.{A} vai no user.
    $this->restrito = User::factory()->create(['business_id' => $this->biz->id, 'user_type' => 'user', 'allow_login' => 1]);
    $this->restrito->assignRole($role);
    $this->restrito->givePermissionTo(Permission::findOrCreate('location.'.$this->locA, 'web'));

    $this->todas = User::factory()->create(['business_id' => $this->biz->id, 'user_type' => 'user', 'allow_login' => 1]);
    $this->todas->assignRole($role);
    $this->todas->givePermissionTo(Permission::findOrCreate('access_all_locations', 'web'));

    $compra = fn (int $loc, float $total) => Transaction::forceCreate([
        'business_id' => $this->biz->id, 'location_id' => $loc, 'type' => 'purchase',
        'status' => 'ordered', 'payment_status' => 'due',
        'transaction_date' => Carbon::now()->toDateTimeString(), 'ref_no' => 'C22-'.uniqid(),
        'final_total' => $total, 'total_before_tax' => $total, 'tax_amount' => 0, 'discount_amount' => 0,
        'created_by' => $this->todas->id, 'essentials_duration' => 0,
    ]);
    $this->compraA = $compra($this->locA, 300.00);
    $this->compraB = $compra($this->locB, 700.00);
});

it('UC-CMP-12 · usuário restrito à Loja A não abre o detalhe da compra da Loja B', function () {
    $sessao = ['user' => ['business_id' => $this->biz->id, 'id' => $this->restrito->id]];

    // Pré-condição anti-vácuo: a compra da loja permitida abre.
    $this->actingAs($this->restrito)->withSession($sessao)
        ->getJson('/compras/'.$this->compraA->id.'/detalhe')
        ->assertStatus(200);

    // Mesma empresa, outra loja: responde como inexistente (não revela que existe).
    $this->actingAs($this->restrito)->withSession($sessao)
        ->getJson('/compras/'.$this->compraB->id.'/detalhe')
        ->assertStatus(404);

    // Controle: quem tem todas as lojas abre a compra da Loja B.
    $this->actingAs($this->todas)
        ->withSession(['user' => ['business_id' => $this->biz->id, 'id' => $this->todas->id]])
        ->getJson('/compras/'.$this->compraB->id.'/detalhe')
        ->assertStatus(200);
});

it('UC-CMP-13 · os KPIs do cockpit contam só as lojas que o usuário pode ver', function () {
    $svc = app(ComprasService::class);

    // Caminho 2 (independente do service): o banco, filtrado à mão.
    $doBanco = function (array $locais): array {
        $base = DB::table('transactions')->where('business_id', $this->biz->id)->where('type', 'purchase');
        if ($locais !== []) {
            $base->whereIn('location_id', $locais);
        }

        return [
            'aberto' => (clone $base)->whereIn('payment_status', ['due', 'partial'])->count(),
            'transito' => (clone $base)->whereIn('status', ['ordered', 'pending'])->count(),
            'mes' => round((float) (clone $base)->whereBetween('transaction_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])->sum('final_total'), 2),
        ];
    };

    $this->actingAs($this->restrito);
    $restrito = $svc->calcularKpis($this->biz->id);

    $this->actingAs($this->todas);
    $todas = $svc->calcularKpis($this->biz->id);

    // Restrito = só a Loja A, conferido pelo banco.
    $esperadoA = $doBanco([$this->locA]);
    expect($restrito['aberto'])->toBe($esperadoA['aberto'])
        ->and($restrito['transito'])->toBe($esperadoA['transito'])
        ->and(round($restrito['mes'], 2))->toBe($esperadoA['mes']);
    // A Loja A nasceu agora com uma compra de 300,00: o número é exatamente esse.
    expect($esperadoA['aberto'])->toBe(1)
        ->and($esperadoA['mes'])->toBe(300.0);

    // Todas as lojas = o business inteiro; a Loja B (700,00) entra só aqui.
    $esperadoTudo = $doBanco([]);
    expect($todas['aberto'])->toBe($esperadoTudo['aberto'])
        ->and(round($todas['mes'], 2))->toBe($esperadoTudo['mes']);
    expect(round($todas['mes'] - $restrito['mes'], 2))->toBeGreaterThanOrEqual(700.0);
});
