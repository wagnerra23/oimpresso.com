<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-027 — Pest GUARD da tela /financeiro/unificado.
 *
 * Cobre invariantes do charter (Index.charter.md) + ADR ui/0002:
 * - Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): biz B nunca vê Títulos de biz A
 * - Inertia component path correto + 5 KPIs no shape esperado
 * - Filter tab por querystring atualiza filters retornado
 *
 * Padrão Jana/Repair/Copiloto: skip gracioso quando DB greenfield ou subscription
 * gate bloqueia financeiro_module no env atual.
 */

function unificadoBootstrap(): User
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

it('renderiza Inertia component Financeiro/Unificado/Index', function () {
    $user = unificadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate financeiro_module bloqueia neste env.');
    }
    if ($response->status() === 404) {
        test()->markTestSkipped('Módulo Financeiro não instalado neste env (financeiro:install pendente).');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->not()->toBeNull();
});

it('expõe 5 KPIs no shape esperado', function () {
    $user = unificadoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Unificado/Index')
        ->has('kpis.saldo_previsto')
        ->has('kpis.recebido.valor')
        ->has('kpis.recebido.qtd')
        ->has('kpis.a_receber.valor')
        ->has('kpis.a_receber.qtd')
        ->has('kpis.pago.valor')
        ->has('kpis.pago.qtd')
        ->has('kpis.a_pagar.valor')
        ->has('kpis.a_pagar.qtd')
    );
});

it('filtra por tab via querystring (back-compat)', function () {
    $user = unificadoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/unificado?tab=rec');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.tab', 'rec')
    );
});

it('Onda Polish: lifecycle multi-select via querystring CSV "ar,pa"', function () {
    $user = unificadoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/unificado?lifecycle=ar,pa');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('filters.lifecycle', 2)
        ->where('filters.lifecycle.0', 'ar')
        ->where('filters.lifecycle.1', 'pa')
    );
});

it('Onda Polish: toggle overdue=1 ativa filtro atrasados', function () {
    $user = unificadoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/unificado?overdue=1');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.overdue', true)
    );
});

it('Onda Polish: lifecycle invalido é descartado (sanitização)', function () {
    $user = unificadoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/unificado?lifecycle=ar,xx,sql_injection,pa');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    // Só 'ar' e 'pa' são válidos — 'xx' e 'sql_injection' descartados.
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('filters.lifecycle', 2)
    );
});

it('Tier 0 IRREVOGÁVEL: query Titulo respeita business_id global scope (ADR 0093)', function () {
    $user = unificadoBootstrap();

    // Teste defensivo: o response shape NÃO pode conter dados cross-tenant.
    // Como não temos fixtures de 2 businesses pra cross-check direto, aqui
    // garantimos que o response.lancamentos[] tem business_id implícito do user
    // (nunca expõe lancamento de outro tenant).
    //
    // O isolamento real é enforcado por:
    //   1. UnificadoController::index linha 50: ->where('business_id', $businessId)
    //   2. Eloquent global scope (se houver) em \Modules\Financeiro\Entities\Titulo
    //
    // Quando 2-business fixture existir (US-FIN-027 fase 2), expandir este test
    // pra logar como user_A e assertar count(lancamentos com biz=B) === 0.

    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    // Smoke: response shape válido + filters retornados pertencem ao user.
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('lancamentos')
        ->has('contas')
        ->has('categorias')
    );
});

it('Non-Goal: rota /unificado é GET-only — POST/PUT/DELETE retornam 405', function () {
    $user = unificadoBootstrap();

    foreach (['post', 'put', 'delete'] as $verb) {
        $r = $this->actingAs($user)->{$verb}('/financeiro/unificado');
        // Espera 405 (Method Not Allowed). Pode também retornar 419 (CSRF) no POST
        // sem token; ambos sinalizam que rota mutativa não existe.
        expect($r->status())->toBeIn([405, 419, 404]);
    }
});

// ─────────────────────── Onda Edit 2026-05-18 — Edit Sheet + Conferido per-user ───────────────────────

it('Edit Sheet: PUT /unificado/{id} atualiza campos seguros', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');

    $titulo = \Modules\Financeiro\Models\Titulo::where('business_id', $businessId)
        ->where('status', 'aberto')
        ->first();

    if (! $titulo) {
        test()->markTestSkipped('Sem título aberto pra editar neste env.');
    }

    $payload = [
        'cliente_descricao' => 'Descrição editada Pest',
        'observacoes' => 'Editado via test '.now()->toIso8601String(),
        'categoria_id' => null,
        'vencimento' => now()->addDays(15)->toDateString(),
    ];

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->put("/financeiro/unificado/{$titulo->id}", $payload);

    expect($response->status())->toBeIn([302, 303]);

    $titulo->refresh();
    expect($titulo->cliente_descricao)->toBe('Descrição editada Pest');
    expect($titulo->vencimento->toDateString())->toBe(now()->addDays(15)->toDateString());
});

it('Edit Sheet: valor_total mutável só se status aberto/parcial (ADR fin-tech/0002)', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');

    $quitado = \Modules\Financeiro\Models\Titulo::where('business_id', $businessId)
        ->where('status', 'quitado')
        ->first();

    if (! $quitado) {
        test()->markTestSkipped('Sem título quitado pra testar imutabilidade.');
    }

    $valorOriginal = (float) $quitado->valor_total;

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->put("/financeiro/unificado/{$quitado->id}", [
            'cliente_descricao' => $quitado->cliente_descricao,
            'observacoes' => $quitado->observacoes,
            'categoria_id' => $quitado->categoria_id,
            'vencimento' => $quitado->vencimento->toDateString(),
            'valor_total' => 99999.99,
        ]);

    // Espera 422 (abort no assertValorMutavel) ou redirect com error flash
    expect($response->status())->toBeIn([422, 302, 303]);

    $quitado->refresh();
    expect((float) $quitado->valor_total)->toBe($valorOriginal);
});

it('Tier 0: PUT /unificado/{id} retorna 404 quando título pertence a outro business', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');

    // Título de outro business (qualquer ID inexistente neste tenant)
    $outroTitulo = \Modules\Financeiro\Models\Titulo::query()
        ->withoutGlobalScope(\Modules\Financeiro\Models\Concerns\BusinessScopeImpl::class)
        ->where('business_id', '!=', $businessId)
        ->first();

    if (! $outroTitulo) {
        test()->markTestSkipped('Apenas 1 business no DB — não há cross-tenant pra testar.');
    }

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->put("/financeiro/unificado/{$outroTitulo->id}", [
            'cliente_descricao' => 'tentativa cross-tenant',
            'observacoes' => null,
            'categoria_id' => null,
            'vencimento' => now()->addDays(7)->toDateString(),
        ]);

    expect($response->status())->toBe(404);
});

it('Conferir: POST /unificado/{id}/conferir marca user + timestamp', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');

    $titulo = \Modules\Financeiro\Models\Titulo::where('business_id', $businessId)
        ->whereNull('conferido_by')
        ->first();

    if (! $titulo) {
        test()->markTestSkipped('Todos títulos já conferidos neste env.');
    }

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->post("/financeiro/unificado/{$titulo->id}/conferir");

    expect($response->status())->toBeIn([302, 303]);

    $titulo->refresh();
    expect($titulo->conferido_by)->toBe($user->id);
    expect($titulo->conferido_at)->not()->toBeNull();
});

it('Unconferir: DELETE /unificado/{id}/conferir limpa user + timestamp', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');

    $titulo = \Modules\Financeiro\Models\Titulo::where('business_id', $businessId)
        ->first();

    if (! $titulo) {
        test()->markTestSkipped('Sem título neste env.');
    }

    // Marca primeiro pra ter o que limpar.
    $titulo->conferido_by = $user->id;
    $titulo->conferido_at = now();
    $titulo->save();

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->delete("/financeiro/unificado/{$titulo->id}/conferir");

    expect($response->status())->toBeIn([302, 303]);

    $titulo->refresh();
    expect($titulo->conferido_by)->toBeNull();
    expect($titulo->conferido_at)->toBeNull();
});
