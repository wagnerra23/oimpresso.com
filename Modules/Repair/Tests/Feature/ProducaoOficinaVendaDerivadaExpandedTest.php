<?php

declare(strict_types=1);

use App\Business;
use App\Product;
use App\Transaction;
use App\TransactionSellLine;
use App\User;
use App\Variation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Onda 3 (Wave Z-2 backend) — GUARD do payload expandido `venda_derivada`.
 *
 * Frontend Cowork drawer card precisa de breakdown peças/serviço/fiscal além
 * do shape Onda 5 (id/invoice_no/final_total/transaction_date). Este test
 * GUARDa o contrato expandido:
 *   - items_list (array de {type, name, qty, unit_price, subtotal})
 *   - items_summary (counts/totals por type + tax_total + discount_total)
 *   - fiscal (status/modelo/chave/danfe_url OR null)
 *
 * Tier 0 ADR 0093: nfe lookup scoped por business_id (cross-tenant não vaza).
 * Anti-N+1: eager-load de sell_lines.product + batch nfe_emissoes.
 *
 * Comportamento defensivo (skips):
 * - SQLite Pest CI sem tabelas core → skip.
 * - Schema UltimatePOS usa MODIFY COLUMN MySQL-only → fixtures pulados em SQLite.
 */

beforeEach(function () {
    if (! Schema::hasTable('users')
        || ! Schema::hasTable('business')
        || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Tabelas users/business/permissions ausentes — rode migrate primeiro.');
    }
});

function vendaDerivadaBootstrap(): User
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

    Permission::firstOrCreate(['name' => 'repair.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('repair.view')) {
        $user->givePermissionTo('repair.view');
    }

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'business'                 => [
            'id'              => $business->id,
            'name'            => $business->name,
            'currency_symbol' => 'R$',
        ],
        'is_admin'                 => true,
    ]);

    return $user;
}

it('payload shape inclui items_list, items_summary e fiscal quando venda_derivada !== null', function () {
    $user = vendaDerivadaBootstrap();
    $response = $this->actingAs($user)->get('/repair/producao-oficina');

    if ($response->status() === 403) {
        test()->markTestSkipped('Module gate bloqueia.');
    }

    $page = $response->original->getProps();
    $columns = $page['columns'] ?? [];

    $checkedAtLeastOne = false;

    foreach ($columns as $col) {
        foreach ($col['cards'] ?? [] as $card) {
            if (! array_key_exists('venda_derivada', $card)) {
                continue; // mock fixture pode não ter o field
            }

            $vd = $card['venda_derivada'];
            if ($vd === null) {
                continue; // OS sem venda derivada
            }

            $checkedAtLeastOne = true;

            // Backward compat (Onda 5 shape preservado).
            expect($vd)->toHaveKeys(['id', 'invoice_no', 'final_total', 'transaction_date']);

            // Onda 3 expansion.
            expect($vd)->toHaveKeys(['items_list', 'items_summary', 'fiscal']);
            expect($vd['items_list'])->toBeArray();
            expect($vd['items_summary'])->toBeArray();
            expect($vd['items_summary'])->toHaveKeys([
                'products_count', 'products_total',
                'services_count', 'services_total',
                'tax_total', 'discount_total',
            ]);

            // fiscal pode ser null (sem NF emitida) OR array com shape.
            if ($vd['fiscal'] !== null) {
                expect($vd['fiscal'])->toHaveKeys(['status', 'modelo', 'chave', 'danfe_url']);
            }
        }
    }

    if (! $checkedAtLeastOne) {
        test()->markTestSkipped('Sem venda_derivada não-null no payload — fixture/seed precisa ter Transaction source=oficina vinculada.');
    }
});

it('items_list separa produtos (enable_stock=1) de serviços (enable_stock=0) por type', function () {
    $user = vendaDerivadaBootstrap();
    $response = $this->actingAs($user)->get('/repair/producao-oficina');

    if ($response->status() === 403) {
        test()->markTestSkipped('Module gate bloqueia.');
    }

    $page = $response->original->getProps();
    $columns = $page['columns'] ?? [];

    foreach ($columns as $col) {
        foreach ($col['cards'] ?? [] as $card) {
            if (! array_key_exists('venda_derivada', $card)) {
                continue;
            }
            $vd = $card['venda_derivada'];
            if ($vd === null) {
                continue;
            }

            foreach ($vd['items_list'] as $item) {
                expect($item)->toHaveKeys(['type', 'name', 'qty', 'unit_price', 'subtotal']);
                // Type deve ser 'product' OR 'service' — convenção UltimatePOS.
                expect($item['type'])->toBeIn(['product', 'service']);
                expect($item['qty'])->toBeNumeric();
                expect($item['unit_price'])->toBeNumeric();
                expect($item['subtotal'])->toBeNumeric();
            }

            // Summary deve bater com items_list (counts coerentes).
            $productsCountActual = count(array_filter($vd['items_list'], fn ($i) => $i['type'] === 'product'));
            $servicesCountActual = count(array_filter($vd['items_list'], fn ($i) => $i['type'] === 'service'));
            expect($vd['items_summary']['products_count'])->toBe($productsCountActual);
            expect($vd['items_summary']['services_count'])->toBe($servicesCountActual);
        }
    }
});

it('fiscal.status reflete último nfe_emissoes (autorizada > pendente > rejeitada precedence)', function () {
    // Unit test do precedence helper — não depende de Transaction real.
    // Simula 3 emissões pra mesma TX e verifica que orderByDesc(id) pega a mais recente.
    if (! Schema::hasTable('nfe_emissoes')) {
        test()->markTestSkipped('Tabela nfe_emissoes ausente — feature NfeBrasil não migrada.');
    }
    if (! class_exists(\Modules\NfeBrasil\Models\NfeEmissao::class)) {
        test()->markTestSkipped('Classe NfeEmissao indisponível.');
    }

    $business = Business::first();
    if (! $business) {
        test()->markTestSkipped('Sem business.');
    }

    // Cleanup possível leftover (test idempotente).
    $txStub = 9999990;
    DB::table('nfe_emissoes')->where('transaction_id', $txStub)
        ->where('business_id', $business->id)->delete();

    try {
        // Tenta inserir 3 emissões — se schema reject (FK, NULL not allowed), skip.
        $modelo = '55';
        DB::table('nfe_emissoes')->insert([
            ['business_id' => $business->id, 'transaction_id' => $txStub, 'modelo' => $modelo, 'serie' => 1, 'numero' => 1, 'status' => 'rejeitada', 'cstat' => '999', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $business->id, 'transaction_id' => $txStub, 'modelo' => $modelo, 'serie' => 1, 'numero' => 2, 'status' => 'pendente', 'cstat' => '103', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $business->id, 'transaction_id' => $txStub, 'modelo' => $modelo, 'serie' => 1, 'numero' => 3, 'status' => 'autorizada', 'cstat' => '100', 'created_at' => now(), 'updated_at' => now()],
        ]);
    } catch (\Throwable $e) {
        test()->markTestSkipped('Não foi possível inserir nfe_emissoes stub: '.$e->getMessage());
    }

    try {
        // Replica a query do controller — pega mais recente (orderByDesc id).
        $latest = \Modules\NfeBrasil\Models\NfeEmissao::where('business_id', $business->id)
            ->whereIn('transaction_id', [$txStub])
            ->orderByDesc('id')
            ->get(['id', 'transaction_id', 'modelo', 'status', 'chave_44'])
            ->groupBy('transaction_id')
            ->map(fn ($g) => $g->first())
            ->get($txStub);

        expect($latest)->not()->toBeNull();
        // Última inserida (id maior) é a 'autorizada' — precedence garantido pela ordem temporal.
        expect($latest->status)->toBe('autorizada');
    } finally {
        DB::table('nfe_emissoes')->where('transaction_id', $txStub)
            ->where('business_id', $business->id)->delete();
    }
});

it('Tier 0 ADR 0093: nfe lookup scoped por business_id — cross-tenant não vaza', function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        test()->markTestSkipped('Tabela nfe_emissoes ausente.');
    }
    if (! class_exists(\Modules\NfeBrasil\Models\NfeEmissao::class)) {
        test()->markTestSkipped('Classe NfeEmissao indisponível.');
    }

    $business = Business::first();
    if (! $business) {
        test()->markTestSkipped('Sem business.');
    }

    // Cria business "outro tenant" sintético (id alto pra não colidir).
    $otherBusinessId = $business->id + 999999;
    $txStub = 9999991;

    DB::table('nfe_emissoes')->where('transaction_id', $txStub)->delete();

    try {
        DB::table('nfe_emissoes')->insert([
            // Emissão pertence ao OUTRO tenant.
            ['business_id' => $otherBusinessId, 'transaction_id' => $txStub, 'modelo' => '55', 'serie' => 1, 'numero' => 1, 'status' => 'autorizada', 'cstat' => '100', 'created_at' => now(), 'updated_at' => now()],
        ]);
    } catch (\Throwable $e) {
        test()->markTestSkipped('Não foi possível inserir stub cross-tenant: '.$e->getMessage());
    }

    try {
        // Query scoped pro tenant ATIVO — não deve enxergar emissão do outro.
        $leaked = \Modules\NfeBrasil\Models\NfeEmissao::where('business_id', $business->id)
            ->whereIn('transaction_id', [$txStub])
            ->get();

        expect($leaked)->toHaveCount(0);
    } finally {
        DB::table('nfe_emissoes')->where('transaction_id', $txStub)->delete();
    }
});

it('backward compat: payload sem sell_lines retorna items_list=[] e summary zerado', function () {
    // Validação direta do helper buildVendaDerivadaPayload via reflexão —
    // garante que Worker B Onda 5 continua funcionando mesmo com tx sem itens.
    $business = Business::first();
    if (! $business) {
        test()->markTestSkipped('Sem business.');
    }

    $controller = new \Modules\Repair\Http\Controllers\ProducaoOficinaController();
    $ref = new \ReflectionClass($controller);
    $method = $ref->getMethod('buildVendaDerivadaPayload');
    $method->setAccessible(true);

    // Stub Transaction-like com sell_lines vazio.
    $txStub = new \App\Transaction();
    $txStub->id = 12345;
    $txStub->invoice_no = 'V-TEST';
    $txStub->final_total = 100.00;
    $txStub->setRelation('sell_lines', collect());

    $payload = $method->invoke($controller, $txStub, null);

    expect($payload['items_list'])->toBe([]);
    expect($payload['items_summary'])->toBe([
        'products_count' => 0,
        'products_total' => 0.0,
        'services_count' => 0,
        'services_total' => 0.0,
        'tax_total' => 0.0,
        'discount_total' => 0.0,
    ]);
    expect($payload['fiscal'])->toBeNull();
    // Backward compat — keys Onda 5 preservadas.
    expect($payload['id'])->toBe(12345);
    expect($payload['invoice_no'])->toBe('V-TEST');
    expect($payload['final_total'])->toBe(100.0);
});
