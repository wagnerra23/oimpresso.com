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
        // Tenant fictício 98 (ADR 0358): é onde o seed da lane põe conta bancária,
        // location e contact. Business::first() caía na empresa 1, sem conta, e pulava.
        $business = test()->seededTenant();
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

    // NAO asserta o header X-Inertia da RESPOSTA: ele so existe quando o REQUEST e
    // XHR (X-Inertia: true) -- e nesse modo o Inertia devolve JSON, o que faz o
    // assertInertia() dos casos vizinhos falhar com "Not a valid Inertia response".
    // As duas formas sao mutuamente exclusivas: esta linha nunca podia passar num GET
    // simples. Mesma causa ja consertada no ProvaVivaControllerTest (#5196) e no
    // FluxoControllerTest (triagem 7/N), que o rodape da quarentena ja apontava aqui.
    // Fica a prova mais forte: qual componente a rota monta.
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Unificado/Index')
    );
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

it('Non-Goal: /unificado nao aceita PUT nem DELETE na COLECAO (POST virou Goal em US-FIN-021)', function () {
    $user = unificadoBootstrap();

    // ATENCAO -- este Non-Goal ENCOLHEU de proposito, e o motivo esta medido:
    // quando o caso nasceu, /financeiro/unificado era GET-only. A Onda 25
    // (US-FIN-021, insert manual via TituloCreateSheet) acrescentou o POST, que
    // HOJE existe e tem teste proprio provando que cria Titulo
    // (UnificadoStoreTest). Perguntado ao runtime, nao ao codigo:
    //
    //   php artisan route:list --path=financeiro/unificado
    //     GET|HEAD  financeiro/unificado        financeiro.unificado.index
    //     POST      financeiro/unificado        financeiro.unificado.store   <-- Goal
    //     PUT       financeiro/unificado/{id}   financeiro.unificado.update  <-- com ID
    //
    // Por isso o POST dava 302 (store validando payload vazio e redirecionando) e o
    // caso reprovava desde entao. Cobrar "GET-only" e cobrar o oposto de uma US
    // entregue -- mesma familia do Wave23SaturationTest (teste podre por CRESCIMENTO
    // legitimo). O que CONTINUA sendo Non-Goal, e o que este caso guarda agora:
    // mutacao na COLECAO por PUT/DELETE (sem id). Update existe, mas so em /{id}.
    foreach (['put', 'delete'] as $verb) {
        $r = $this->actingAs($user)->{$verb}('/financeiro/unificado');
        // 405 Method Not Allowed; 419 (CSRF) e 404 tambem sinalizam rota inexistente.
        expect($r->status())->toBeIn([405, 419, 404], "verbo {$verb} na colecao deveria ser recusado");
    }
});

// ─────────────────────── Onda Edit 2026-05-18 — Edit Sheet + Conferido per-user ───────────────────────

/**
 * Fixture própria (2026-11-05, lote 2.2 da auditoria). Os 5 casos abaixo pegavam "o
 * primeiro título aberto/quitado/não conferido do banco": no CI o banco não tem título
 * nenhum e eles pulavam sempre; num banco com dado eles editariam um título alheio.
 * Agora cada caso cria o seu (prefixo UNIF-TST-) e apaga no fim.
 */
function unificadoTitulo(int $businessId, int $userId, string $status = 'aberto', float $valor = 150.0): \Modules\Financeiro\Models\Titulo
{
    // SUPERADMIN: cria título com business_id explícito (pode ser de outra empresa no caso Tier 0).
    $t = new \Modules\Financeiro\Models\Titulo();
    $t->forceFill([
        'business_id'       => $businessId,
        'numero'            => 'UNIF-TST-'.bin2hex(random_bytes(4)),
        'tipo'              => 'receber',
        'status'            => $status,
        'cliente_descricao' => 'UNIF teste',
        'valor_total'       => $valor,
        'valor_aberto'      => $status === 'quitado' ? 0 : $valor,
        'moeda'             => 'BRL',
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(10)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => $userId,
    ]);
    $t->saveQuietly();

    return $t;
}

function unificadoLimpa(\Modules\Financeiro\Models\Titulo ...$ts): void
{
    foreach ($ts as $t) {
        \Illuminate\Support\Facades\DB::table('fin_titulos')->where('id', $t->id)->delete();
    }
}

it('Edit Sheet: PUT /unificado/{id} atualiza campos seguros', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');
    $titulo = unificadoTitulo($businessId, $user->id);

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
    unificadoLimpa($titulo);
});

it('Edit Sheet: valor_total mutável só se status aberto/parcial (ADR fin-tech/0002)', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');
    $quitado = unificadoTitulo($businessId, $user->id, 'quitado', 210.0);

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
    expect((float) $quitado->valor_total)->toBe(210.0);
    unificadoLimpa($quitado);
});

it('Tier 0: PUT /unificado/{id} retorna 404 quando título pertence a outro business', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');
    $outraEmpresa = (int) \App\Business::query()->where('id', '!=', $businessId)->orderBy('id')->value('id');
    if ($outraEmpresa === 0) {
        test()->markTestSkipped('Apenas 1 business no DB — não há cross-tenant pra testar.');
    }
    $outroTitulo = unificadoTitulo($outraEmpresa, $user->id);

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->put("/financeiro/unificado/{$outroTitulo->id}", [
            'cliente_descricao' => 'tentativa cross-tenant',
            'observacoes' => null,
            'categoria_id' => null,
            'vencimento' => now()->addDays(7)->toDateString(),
        ]);

    expect($response->status())->toBe(404);
    $intacto = \Illuminate\Support\Facades\DB::table('fin_titulos')->where('id', $outroTitulo->id)->value('cliente_descricao');
    expect($intacto)->toBe('UNIF teste', 'o título da outra empresa não pode ter sido editado');
    unificadoLimpa($outroTitulo);
});

it('Conferir: POST /unificado/{id}/conferir marca user + timestamp', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');
    $titulo = unificadoTitulo($businessId, $user->id);

    $response = $this->actingAs($user)
        ->from('/financeiro/unificado')
        ->post("/financeiro/unificado/{$titulo->id}/conferir");

    expect($response->status())->toBeIn([302, 303]);

    $titulo->refresh();
    expect($titulo->conferido_by)->toBe($user->id);
    expect($titulo->conferido_at)->not()->toBeNull();
    unificadoLimpa($titulo);
});

it('Unconferir: DELETE /unificado/{id}/conferir limpa user + timestamp', function () {
    $user = unificadoBootstrap();
    $businessId = (int) session('user.business_id');
    $titulo = unificadoTitulo($businessId, $user->id);

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
    unificadoLimpa($titulo);
});
