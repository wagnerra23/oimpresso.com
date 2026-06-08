<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

beforeEach(function () {
    // CI SQLite :memory: — pula gracioso se migrate não criou tabelas core
    // (UltimatePOS usa MODIFY COLUMN MySQL-only que falha em SQLite).
    if (! Schema::hasTable('users')
        || ! Schema::hasTable('business')
        || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Tabelas users/business/permissions ausentes — rode migrate primeiro.');
    }
});

/**
 * US-REPAIR-PROD-5 — Pest GUARD da tela Produção · Oficina.
 *
 * Tests cobrem invariantes do charter (Index.charter.md):
 * - Kanban tem EXATAMENTE 5 colunas (Recepção, Diagnóstico, Aguardando peças,
 *   Em execução, Pronto)
 * - Tela é read-only — não tem rota de mutação
 * - Pelo menos 1 card aguardando aprovação pra UI testar banner
 *
 * Quando US-REPAIR-PROD-2 entrar (query real), adicionar tests de isolamento
 * `business_id`.
 */

function producaoOficinaBootstrap(): User
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

it('renderiza Page Inertia Repair/ProducaoOficina/Index', function () {
    $user = producaoOficinaBootstrap();

    $response = $this->actingAs($user)->get('/repair/producao-oficina');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->not()->toBeNull();
});

it('passa exatamente 5 colunas na ordem correta do charter', function () {
    $user = producaoOficinaBootstrap();
    $response = $this->actingAs($user)->get('/repair/producao-oficina');

    if ($response->status() === 403) {
        test()->markTestSkipped('Module gate bloqueia.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/ProducaoOficina/Index')
        ->has('columns', 5)
        ->where('columns.0.id', 'recepcao')
        ->where('columns.1.id', 'diagnostico')
        ->where('columns.2.id', 'aguardando-pecas')
        ->where('columns.3.id', 'em-execucao')
        ->where('columns.4.id', 'pronto')
    );
});

it('totals.aguardando_aprovacao é inteiro >= 0', function () {
    $user = producaoOficinaBootstrap();
    $response = $this->actingAs($user)->get('/repair/producao-oficina');

    if ($response->status() === 403) {
        test()->markTestSkipped('Module gate bloqueia.');
    }

    // PROD-2: data_source pode ser 'live' (biz com repair_statuses + jobSheets)
    // ou 'mock' (fallback). Ambos são válidos. Apenas garantir que prop existe.
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('totals.os', fn ($v) => is_int($v) && $v >= 0)
        ->where('totals.aguardando_aprovacao', fn ($v) => is_int($v) && $v >= 0)
        ->where('data_source', fn ($v) => in_array($v, ['live', 'mock'], true))
    );
});

it('mock fallback gera exatamente 17 OS e 3 aguardando aprovação', function () {
    $user = producaoOficinaBootstrap();
    $response = $this->actingAs($user)->get('/repair/producao-oficina');

    if ($response->status() === 403) {
        test()->markTestSkipped('Module gate bloqueia.');
    }

    // Só vale assertir contagem mock se for mesmo mock (biz sem repair_statuses).
    $page = $response->original->getProps();
    if (($page['data_source'] ?? null) !== 'mock') {
        test()->markTestSkipped('Biz tem dados live — assertion mock não aplica.');
    }

    expect($page['totals']['os'])->toBe(17);
    expect($page['totals']['aguardando_aprovacao'])->toBe(3);
});

it('Non-Goal: rota raiz é GET-only — POST/PUT/DELETE em /producao-oficina retornam 405', function () {
    $user = producaoOficinaBootstrap();

    $post = $this->actingAs($user)->post('/repair/producao-oficina');
    $put = $this->actingAs($user)->put('/repair/producao-oficina');
    $delete = $this->actingAs($user)->delete('/repair/producao-oficina');

    expect($post->status())->toBe(405);
    expect($put->status())->toBe(405);
    expect($delete->status())->toBe(405);
});

it('Non-Goal: rota não tem variantes /create, /edit, /{id} (CRUD vai pra /repair/job-sheet)', function () {
    $user = producaoOficinaBootstrap();

    foreach (['create', 'edit', '1', '1/edit'] as $suffix) {
        $r = $this->actingAs($user)->get('/repair/producao-oficina/'.$suffix);
        expect($r->status())->toBe(404);
    }
});

it('US-REPAIR-PROD-4: move endpoint respeita business_id (Tier 0 ADR 0093)', function () {
    $user = producaoOficinaBootstrap();

    // Tenta mover JobSheet ID inexistente / cross-tenant — deve retornar redirect com
    // session error, NUNCA 200 sem erro (vazaria isolamento Tier 0).
    $r = $this->actingAs($user)->post('/repair/producao-oficina/999999/move', ['column' => 'em-execucao']);

    if (in_array($r->status(), [403, 404, 419], true)) {
        test()->markTestSkipped('Rota não acessível neste env (gate, rota ausente, ou CSRF token).');
    }

    expect($r->status())->toBe(302);
});

it('US-REPAIR-PROD-4: move endpoint rejeita coluna inválida', function () {
    $user = producaoOficinaBootstrap();

    $r = $this->actingAs($user)->post('/repair/producao-oficina/1/move', ['column' => 'inexistente']);

    if (in_array($r->status(), [403, 404, 419], true)) {
        test()->markTestSkipped('Rota não acessível neste env.');
    }

    expect($r->status())->toBe(302);
});

/**
 * Onda 5 (ADR 0192) — Integração Vendas × Oficina A1 KB-9.75.
 *
 * GUARD do contrato `venda_derivada` no payload do controller. Frontend
 * `Index.tsx` Onda 5 espera o shape exato `{ id, invoice_no, final_total, transaction_date }`
 * em cards quando coluna='pronto' AND existe Transaction derivada. Este test
 * valida que controller preserva o contrato — quebrá-lo derruba drawer card.
 *
 * Não cria Transaction real (depende de tabela transactions com colunas Onda 1
 * + Observer Onda 2). Apenas confirma shape do payload + scope multi-tenant.
 */
it('Onda 5: cards expõem field venda_derivada com shape correto OR null', function () {
    $user = producaoOficinaBootstrap();
    $response = $this->actingAs($user)->get('/repair/producao-oficina');

    if ($response->status() === 403) {
        test()->markTestSkipped('Module gate bloqueia.');
    }

    $page = $response->original->getProps();
    $columns = $page['columns'] ?? [];

    foreach ($columns as $col) {
        foreach ($col['cards'] ?? [] as $card) {
            // Field é opcional no mock fallback (sem JobSheet real); quando
            // live data, deve ser null OR array com shape Onda 5 (ADR 0192).
            if (! array_key_exists('venda_derivada', $card)) {
                continue;  // mock fixture pode não ter o field
            }

            $vd = $card['venda_derivada'];
            if ($vd === null) {
                continue;  // OK — OS sem venda derivada
            }

            expect($vd)->toBeArray();
            expect($vd)->toHaveKeys(['id', 'invoice_no', 'final_total', 'transaction_date']);
            expect($vd['id'])->toBeInt();
            expect($vd['invoice_no'])->toBeString();
            expect($vd['final_total'])->toBeNumeric();
            // transaction_date pode ser null (Carbon não preenchido) OR string YYYY-MM-DD
            expect($vd['transaction_date'])->toSatisfy(
                fn ($v) => $v === null || (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v))
            );
        }
    }
});
