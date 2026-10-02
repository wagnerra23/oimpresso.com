<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\Contract\AutosaveContractRunner;

/**
 * API de Pedidos do app das lojas (oimpresso-app) — GET /api/app/pedidos e /{id}, só leitura.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §2 (Pedido = venda do ERP, D11;
 * urgente = atrasado; tenant do token). Mesmas regras de visibilidade da lista web de vendas.
 * NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093): venda de OUTRO business nunca aparece, nem pelo id. Controle positivo
 * em par: a venda do próprio business APARECE — senão o "não aparece" seria verde por vácuo.
 */
uses(DatabaseTransactions::class);

/** Cria processo + estágio FSM no business e põe a venda nele (UPDATE cru: sem Observer FSM). */
function appPedEtapa(int $businessId, int $vendaId, string $chave, bool $terminal = false): void
{
    $proc = DB::table('sale_processes')->where('business_id', $businessId)->where('key', 'app_teste')->value('id')
        ?? DB::table('sale_processes')->insertGetId([
            'business_id' => $businessId, 'key' => 'app_teste', 'name' => 'App teste',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $stage = DB::table('sale_process_stages')->where('process_id', $proc)->where('key', $chave)->value('id')
        ?? DB::table('sale_process_stages')->insertGetId([
            'process_id' => $proc, 'key' => $chave, 'name' => 'Etapa ' . $chave, 'sort_order' => 1,
            'is_terminal' => $terminal, 'created_at' => now(), 'updated_at' => now(),
        ]);
    DB::table('transactions')->where('id', $vendaId)->update(['current_stage_id' => $stage]);
}

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transactions', 'contacts', 'sale_processes', 'sale_process_stages'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->biz = $ctx['business'];
    $this->user = $ctx['user'];
    $this->venda = (int) $ctx['transactionId'];
    $this->outroBiz = \App\Business::where('id', '!=', $this->biz->id)->first();
    if (! $this->outroBiz) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }

    foreach (['direct_sell.view', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }

    DB::table('transactions')->where('id', $this->venda)->update([
        'status' => 'final', 'sub_type' => null, 'final_total' => 248.00,
        'delivery_date' => now()->subDays(2)->toDateTimeString(),
    ]);
    appPedEtapa((int) $this->biz->id, $this->venda, 'in_production');

    Passport::actingAs($this->user, [], 'api');
});

it('sem token responde 401', function () {
    $this->app['auth']->forgetGuards();
    $this->withHeaders(['Accept' => 'application/json'])->get('/api/app/pedidos')->assertStatus(401);
});

it('lista a venda do meu business no pipeline, com etapa agrupada, valor, prazo e atrasado', function () {
    $r = $this->getJson('/api/app/pedidos?filtro=ativos')->assertOk();

    $item = collect($r->json('itens'))->firstWhere('id', $this->venda);
    expect($item)->not->toBeNull();
    expect((float) $item['valor'])->toBe(248.0);
    expect($item['etapa']['chave'])->toBe('in_production');
    expect($item['etapa']['grupo'])->toBe('producao');
    expect($item['atrasado'])->toBeTrue();
    expect($item)->toHaveKey('resumo');
    expect($r->json('contadores.ativos'))->toBeGreaterThanOrEqual(1);
});

it('venda de OUTRO business não aparece na lista nem pelo id', function () {
    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $this->outroBiz->id, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'invoice_no' => 'APP-ALHEIA-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 99, 'final_total' => 99,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    appPedEtapa((int) $this->outroBiz->id, $alheia, 'in_production');

    $ids = collect($this->getJson('/api/app/pedidos?filtro=todos')->assertOk()->json('itens'))->pluck('id');
    expect($ids)->toContain($this->venda);
    expect($ids)->not->toContain($alheia);

    $this->getJson('/api/app/pedidos/' . $alheia)->assertStatus(404);
});

it('concluída sai de "ativos" e entra em "concluidos"; atrasado vira falso', function () {
    appPedEtapa((int) $this->biz->id, $this->venda, 'completed', true);

    $ativos = collect($this->getJson('/api/app/pedidos?filtro=ativos')->json('itens'))->pluck('id');
    expect($ativos)->not->toContain($this->venda);

    $item = collect($this->getJson('/api/app/pedidos?filtro=concluidos')->json('itens'))->firstWhere('id', $this->venda);
    expect($item)->not->toBeNull();
    expect($item['etapa']['grupo'])->toBe('concluido');
    expect($item['atrasado'])->toBeFalse();
});

it('detalhe traz os 5 passos com o atual marcado e as ações só para leitura', function () {
    $r = $this->getJson('/api/app/pedidos/' . $this->venda)->assertOk();

    expect(collect($r->json('etapas'))->pluck('grupo')->all())
        ->toBe(['orcamento', 'aprovacao', 'producao', 'entrega', 'concluido']);
    expect(collect($r->json('etapas'))->firstWhere('grupo', 'producao')['estado'])->toBe('atual');
    expect(collect($r->json('etapas'))->firstWhere('grupo', 'orcamento')['estado'])->toBe('feito');
    expect($r->json('acoes'))->toBeArray();
    expect($r->json('itens_venda'))->toBeArray();
});

it('sem nenhuma permissão de venda responde 403 sem_permissao', function () {
    $semPerm = \App\User::query()->whereKey(
        DB::table('users')->insertGetId([
            'first_name' => 'APP sem perm', 'username' => 'app_sp_' . uniqid(), 'password' => 'x',
            'business_id' => $this->biz->id, 'created_at' => now(), 'updated_at' => now(),
        ])
    )->firstOrFail();
    Passport::actingAs($semPerm, [], 'api');

    $this->getJson('/api/app/pedidos')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');
});

it('produção: a venda em produção aparece na coluna da etapa; de outro business e concluída não aparecem', function () {
    $alheia = DB::table('transactions')->insertGetId([
        'business_id' => $this->outroBiz->id, 'created_by' => $this->user->id, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'due', 'invoice_no' => 'APP-PROD-ALHEIA-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    appPedEtapa((int) $this->outroBiz->id, $alheia, 'in_production');

    $r = $this->getJson('/api/app/producao')->assertOk();
    expect(collect($r->json('colunas'))->pluck('id')->all())
        ->toBe(['quote_approved', 'in_production', 'on_hold', 'ready_for_invoice']);

    $emProducao = collect($r->json('colunas'))->firstWhere('id', 'in_production');
    $ids = collect($emProducao['itens'])->pluck('id');
    expect($ids)->toContain($this->venda);
    expect($ids)->not->toContain($alheia);

    appPedEtapa((int) $this->biz->id, $this->venda, 'completed', true);
    $todas = collect($this->getJson('/api/app/producao')->json('colunas'))->flatMap(fn ($c) => collect($c['itens'])->pluck('id'));
    expect($todas)->not->toContain($this->venda);
});

it('produção: o limite de 50 vale POR coluna — uma etapa cheia não esvazia as outras; total traz a contagem real', function () {
    // O beforeEach deixa a venda em produção: pega esse estágio e move a venda para "aprovado",
    // com prazo DEPOIS das 201 vendas abaixo.
    $emProducao = (int) DB::table('transactions')->where('id', $this->venda)->value('current_stage_id');
    appPedEtapa((int) $this->biz->id, $this->venda, 'quote_approved');
    DB::table('transactions')->where('id', $this->venda)->update(['delivery_date' => now()->addDays(30)]);

    // 201 vendas em produção, todas com prazo ANTES da venda aprovada: num limit único de
    // 200 no total, elas ocupariam todas as vagas e a coluna "aprovado" sairia vazia.
    $linhas = [];
    for ($n = 0; $n < 201; $n++) {
        $linhas[] = [
            'business_id' => $this->biz->id, 'created_by' => $this->user->id, 'type' => 'sell',
            'status' => 'final', 'payment_status' => 'due', 'invoice_no' => 'APP-CHEIA-' . $n . '-' . uniqid(),
            'transaction_date' => now(), 'delivery_date' => now()->addDay(), 'total_before_tax' => 1, 'final_total' => 1,
            'current_stage_id' => $emProducao, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    DB::table('transactions')->insert($linhas);

    $colunas = collect($this->getJson('/api/app/producao')->assertOk()->json('colunas'));
    $aprovado = $colunas->firstWhere('id', 'quote_approved');
    $producao = $colunas->firstWhere('id', 'in_production');

    expect(collect($aprovado['itens'])->pluck('id'))->toContain($this->venda);
    expect(count($producao['itens']))->toBe(50);
    expect($producao['total'])->toBeGreaterThanOrEqual(201);
    expect($aprovado['total'])->toBe(count($aprovado['itens']));
});

it('orçamentos (tela 04): rascunho e enviado vêm das vendas em draft; a venda final em produção não entra; de outro business não aparece', function () {
    $novo = fn (int $biz, ?string $sub = null) => DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'created_by' => $this->user->id, 'type' => 'sell', 'status' => 'draft',
        'sub_status' => $sub, 'payment_status' => 'due', 'invoice_no' => 'APP-ORC-' . uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 100, 'final_total' => 100,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $rascunho = $novo((int) $this->biz->id);
    $enviado = $novo((int) $this->biz->id, 'quotation');
    $alheio = $novo((int) $this->outroBiz->id, 'quotation');

    $r = $this->getJson('/api/app/orcamentos')->assertOk();
    $porId = collect($r->json('itens'))->keyBy('id');
    expect($porId[$rascunho]['status'])->toBe('rascunho');
    expect($porId[$enviado]['status'])->toBe('enviado');
    expect($porId->has($alheio))->toBeFalse();
    expect($porId->has($this->venda))->toBeFalse();
    expect($r->json('contadores'))->toHaveKeys(['todos', 'rascunho', 'enviado', 'aprovado', 'convertido']);

    $soEnviados = collect($this->getJson('/api/app/orcamentos?status=enviado')->assertOk()->json('itens'))->pluck('id');
    expect($soEnviados)->toContain($enviado);
    expect($soEnviados)->not->toContain($rascunho);
});

