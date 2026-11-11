<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Requests\AprovarOrcamentoRequest;
use Tests\Support\WithSeededTenant;

/**
 * Cliente e vendedor do orçamento pertencem à empresa; calcular exige permissão (UC-CVEMP-01..04).
 *
 * Âncora: ADR 0093 (Tier 0) + auditoria 2026-11-05 S6/I-08 e S9: a web gravava o id de um contato de
 * outra empresa, enquanto o app (AppOrcamentoRapidoController) já recusava.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_orcamentos')) {
        $this->markTestSkipped('Requer MySQL com o schema do módulo.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: '.$e->getMessage());
    }

    $this->operador = $this->usuarioComPermissoes(['comvis.orcamento.view', 'comvis.orcamento.create'], $this->biz);

    $this->como = function (\App\User $user) {
        return $this->actingAs($user)->withSession([
            'user.business_id' => $this->biz->id, 'business.id' => $this->biz->id, 'user.id' => $user->id,
        ]);
    };

    $this->contato = fn (int $biz, string $tipo = 'customer') => (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => $tipo, 'name' => 'Cliente Teste', 'contact_status' => 'active',
        'created_by' => $this->operador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->payload = fn (array $extra = []) => $extra + [
        'data_emissao' => now()->toDateString(),
        'itens' => [['descricao' => 'Banner', 'largura_m' => 3, 'altura_m' => 1.5, 'quantidade' => 1, 'preco_unitario_m2' => 60]],
    ];
});

it('UC-CVEMP-01 · cliente de outra empresa, ou que não é cliente, é recusado ao salvar', function () {
    $antes = DB::table('comvis_orcamentos')->where('business_id', $this->biz->id)->count();

    foreach ([($this->contato)($this->outra->id), ($this->contato)($this->biz->id, 'supplier')] as $recusado) {
        ($this->como)($this->operador)
            ->postJson('/comunicacao-visual/api/orcamentos', ($this->payload)(['contato_id' => $recusado]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('contato_id');
    }

    expect(DB::table('comvis_orcamentos')->where('business_id', $this->biz->id)->count())->toBe($antes);

    // Par positivo: a mesma requisição com cliente da empresa grava.
    $meu = ($this->contato)($this->biz->id);
    $id = ($this->como)($this->operador)
        ->postJson('/comunicacao-visual/api/orcamentos', ($this->payload)(['contato_id' => $meu]))
        ->assertStatus(201)
        ->json('id');
    expect((int) DB::table('comvis_orcamentos')->where('id', $id)->value('contato_id'))->toBe($meu);
});

it('UC-CVEMP-01 · regravar não troca o cliente por um de outra empresa', function () {
    $meu = ($this->contato)($this->biz->id);
    $id = ($this->como)($this->operador)
        ->postJson('/comunicacao-visual/api/orcamentos', ($this->payload)(['contato_id' => $meu]))
        ->assertStatus(201)
        ->json('id');

    ($this->como)($this->operador)
        ->putJson("/comunicacao-visual/api/orcamentos/{$id}", ($this->payload)(['contato_id' => ($this->contato)($this->outra->id)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('contato_id');

    expect((int) DB::table('comvis_orcamentos')->where('id', $id)->value('contato_id'))->toBe($meu);
});

it('UC-CVEMP-02 · vendedor de outra empresa é recusado', function () {
    $alheio = \App\User::factory()->create(['business_id' => $this->outra->id]);

    ($this->como)($this->operador)
        ->postJson('/comunicacao-visual/api/orcamentos', ($this->payload)(['vendedor_id' => $alheio->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('vendedor_id');

    $id = ($this->como)($this->operador)
        ->postJson('/comunicacao-visual/api/orcamentos', ($this->payload)(['vendedor_id' => $this->operador->id]))
        ->assertStatus(201)
        ->json('id');
    expect((int) DB::table('comvis_orcamentos')->where('id', $id)->value('vendedor_id'))->toBe((int) $this->operador->id);
});

it('UC-CVEMP-03 · calcular exige permissão do módulo', function () {
    $semPermissao = \App\User::factory()->create(['business_id' => $this->biz->id, 'allow_login' => 1]);
    ($this->como)($semPermissao)->postJson('/comunicacao-visual/api/calcular', ($this->payload)())->assertStatus(403);

    foreach (['comvis.orcamento.view', 'comvis.orcamento.create', 'comvis.os.view'] as $permissao) {
        $user = $this->usuarioComPermissoes([$permissao], $this->biz);
        $resposta = ($this->como)($user)->postJson('/comunicacao-visual/api/calcular', ($this->payload)())->assertStatus(200);
        expect((float) $resposta->json('total'))->toBe(270.0);
    }
});

it('UC-CVEMP-04 · aprovar não autoriza quem não está logado, mesmo com token na rota', function () {
    $request = AprovarOrcamentoRequest::create('/qualquer', 'POST');
    $request->setRouteResolver(fn () => new class
    {
        public function parameter(string $nome, $padrao = null)
        {
            return $nome === 'token' ? str_repeat('a', 48) : $padrao;
        }
    });

    expect($request->authorize())->toBeFalse();
});
