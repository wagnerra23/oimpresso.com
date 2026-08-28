<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Jobs\ApurarMetaJob;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * F2 do MWART (ADR 0104) — BASELINE do `MetasController` ANTES de a UI virar drawer.
 *
 * POR QUE EXISTE: medido em 2026-08-27, `MetasController` tinha **zero** teste que
 * exercitasse suas rotas — as 3 ocorrências do nome no corpus eram `expected_source`
 * num YAML de eval de IA, string, não teste. Os irmãos aninhados JÁ tinham baseline
 * (`PeriodosControllerCrossTenantTest`, `FontesControllerCrossTenantTest`); o pai, não.
 * Sem isto, a migração pro drawer regride em silêncio (proibição MWART).
 *
 * ÂNCORA DE CONTRATO — cada asserção deriva de fonte externa ao código, nunca do
 * próprio `.php` (o teste tautológico é lápide §5 2026-06-05):
 *   · `memory/requisitos/Jana/RUNBOOK-metas.md` §3 "Contrato preservado"
 *   · US-COPI-011 (detalhe · limite 12) · 012 (criar) · 013 (editar) · 031 (reapurar)
 *   · ADR 0093 (multi-tenant Tier 0) · ADR 0358 (tenant canônico 98, adversário 99)
 *
 * TENANT: 98 (canônico, empresa FICTÍCIA) vs 99 (adversário). NUNCA biz=4 (ROTA LIVRE,
 * cliente real) e NUNCA biz=1 (WR2, empresa em operação — no CT 100 a base é clone de
 * prod e não se limpa entre runs). O seed do CI cria o 98
 * (`.github/actions/pest-mysql-setup`); o 99 nasce aqui e some no rollback.
 *
 * @see Modules/Jana/Http/Controllers/MetasController.php
 * @see Modules/Jana/Tests/Feature/PeriodosControllerCrossTenantTest.php (harness base)
 */

const METAS_BIZ_CANONICO = 98;
const METAS_BIZ_ADVERSARIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    foreach (['jana_metas', 'jana_meta_apuracoes'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Jana.");
        }
    }

    $business = Business::find(METAS_BIZ_CANONICO);
    if (! $business) {
        $this->markTestSkipped('business_id=98 (tenant canônico ADR 0358) ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', METAS_BIZ_CANONICO)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98.');
    }
    $this->user = $user;

    if (! Business::find(METAS_BIZ_ADVERSARIO)) {
        Business::forceCreate([
            'id' => METAS_BIZ_ADVERSARIO,
            'name' => 'Test Biz Adversario#99 (Metas baseline)',
            'currency_id' => 1,
            'start_date' => now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => $user->id,
            'stop_selling_before' => 0,
            'weighing_scale_setting' => '',
            'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0,
        ]);
    }

    $this->metaAdversaria = metaCrua(METAS_BIZ_ADVERSARIO, 'adversaria');
    $this->metaPropria = metaCrua(METAS_BIZ_CANONICO, 'propria');

    // Pré-condição do gate `can:jana.access` do grupo /ia: sem ela o middleware corta
    // com 403 ANTES do controller, e o teste mediria o gate em vez do contrato.
    \Spatie\Permission\Models\Permission::findOrCreate('jana.access', 'web');
    $this->user->givePermissionTo('jana.access');
    $this->user->forgetCachedPermissions();

    $this->actingAs($this->user);
    session([
        'user.business_id' => METAS_BIZ_CANONICO,
        'business' => ['id' => METAS_BIZ_CANONICO, 'name' => $business->name],
    ]);
});

function metaCrua(int $bizId, string $tag): Meta
{
    return Meta::withoutGlobalScopes()->create([
        'business_id' => $bizId,
        'slug' => 'baseline_'.$tag.'_'.uniqid(),
        'nome' => 'Baseline '.$tag,
        'unidade' => 'R$',              // enum real da migration: R$ · qtd · % · dias
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ]);
}

function metaPayload(array $over = []): array
{
    return array_merge([
        'nome' => 'Faturamento mensal',
        'slug' => 'faturamento_'.uniqid(),
        'unidade' => 'R$',
        'tipo_agregacao' => 'soma',
    ], $over);
}

// ─────────────────────────────────────────────────────────────────────────────
// CONTRATO — RUNBOOK-metas.md §3. O que a migração pro drawer NÃO pode mudar.
// ─────────────────────────────────────────────────────────────────────────────

it('US-COPI-012 · store aplica os 3 defaults do servidor e redireciona pro detalhe', function () {
    $payload = metaPayload();

    $resp = $this->post('/ia/metas', $payload);

    $meta = Meta::withoutGlobalScopes()->where('slug', $payload['slug'])->first();
    expect($meta)->not->toBeNull();
    $resp->assertRedirect(route('jana.metas.show', $meta->id));

    // Os 3 defaults são do SERVIDOR — o form não os manda, e o drawer também não deve.
    expect($meta->ativo)->toBeTruthy()
        ->and($meta->origem)->toBe('manual')
        ->and((int) $meta->criada_por_user_id)->toBe((int) $this->user->id);

    // O dono também é do servidor. Sem `not->toBeNull()` ANTES, um `business_id` NULL
    // passaria por `(int) null === 0` e a mensagem diria "0 !== 98", escondendo que o
    // valor real é NULL — que aqui não é "vazio", é "meta de PLATAFORMA" (o
    // ScopeByBusiness devolve NULL pra superadmin de qualquer tenant). Foi exatamente
    // o defeito que este caso pegou no CI.
    expect($meta->business_id)->not->toBeNull()
        ->and((int) $meta->business_id)->toBe(METAS_BIZ_CANONICO);
});

it('US-COPI-012 · store recusa slug fora do padrão e NÃO cria', function () {
    $antes = Meta::withoutGlobalScopes()->count();

    // O RUNBOOK §3 fixa `pattern="[a-z0-9_]+"`; o StoreMetaRequest endurece a mesma
    // ideia. Maiúscula e espaço são o caso que a rota precisa recusar.
    $resp = $this->from('/ia/metas/create')->post('/ia/metas', metaPayload(['slug' => 'Slug Invalido!']));

    $resp->assertSessionHasErrors('slug');
    expect(Meta::withoutGlobalScopes()->count())->toBe($antes);
});

it('US-COPI-012 · store recusa unidade fora da whitelist', function () {
    $resp = $this->from('/ia/metas/create')->post('/ia/metas', metaPayload(['unidade' => 'BRL']));

    // 'BRL' não existe no enum da migration (R$ · qtd · % · dias) — fail-secure.
    $resp->assertSessionHasErrors('unidade');
});

it('§3 · destroy é SOFT: desativa e a linha SOBREVIVE (a UI não pode dizer "excluir")', function () {
    $id = $this->metaPropria->id;

    $this->delete("/ia/metas/{$id}")->assertRedirect(route('jana.metas.index'));

    $fresh = Meta::withoutGlobalScopes()->find($id);
    expect($fresh)->not->toBeNull()          // ← o ponto: NÃO apaga linha
        ->and($fresh->ativo)->toBeFalsy();
});

it('§7 risco 2 · index NÃO filtra inativas — meta desativada continua listada', function () {
    $this->delete("/ia/metas/{$this->metaPropria->id}");

    // Comportamento ATUAL declarado como risco no RUNBOOK: o index ordena por `ativo`
    // desc mas não filtra. Mudá-lo é decisão [W]; o baseline existe pra que a mudança
    // seja deliberada, e não efeito colateral do drawer.
    //
    // ⚠️ Lido do CONTROLLER, não por `$this->get()`: estas 2 views Blade fazem
    // `@extends('layouts.app')` (AdminLTE, 203 ln, 16 include/yield/stack) e renderizá-las
    // mediria o layout, não o contrato — não há no repo um só teste que renderize Blade
    // deste módulo com sucesso (os GETs de `/ia` que passam são todos Inertia). O que o
    // contrato promete é o DADO que o controller entrega; `getData()` o lê sem render.
    $metas = app(\Modules\Jana\Http\Controllers\MetasController::class)
        ->index(request())
        ->getData()['metas'];

    expect($metas->pluck('id')->all())->toContain($this->metaPropria->id);
});

it('US-COPI-013 · update é PARCIAL: muda nome sem tocar slug nem tipo_agregacao', function () {
    $slugAntes = $this->metaPropria->slug;

    $this->patch("/ia/metas/{$this->metaPropria->id}", ['nome' => 'Nome novo'])
        ->assertRedirect(route('jana.metas.show', $this->metaPropria->id));

    $fresh = Meta::withoutGlobalScopes()->find($this->metaPropria->id);
    expect($fresh->nome)->toBe('Nome novo')
        ->and($fresh->slug)->toBe($slugAntes)              // slug é imutável de fato (§7 risco 4)
        ->and($fresh->tipo_agregacao)->toBe('soma');
});

it('US-COPI-011 · show devolve no MÁXIMO 12 apurações, da mais recente pra mais antiga', function () {
    for ($i = 0; $i < 15; $i++) {
        // `calculado_em` e `fonte_query_hash` são NOT NULL na migration, e o trio
        // (meta_id, data_ref, fonte_query_hash) é UNIQUE — hash distinto por linha.
        MetaApuracao::withoutGlobalScopes()->create([
            'meta_id' => $this->metaPropria->id,
            'data_ref' => now()->subDays($i)->toDateString(),
            'valor_realizado' => 100 + $i,
            'calculado_em' => now(),
            'fonte_query_hash' => hash('sha256', 'baseline-'.$i),
        ]);
    }

    // Mesma razão do caso do index: lido do controller, sem renderizar AdminLTE.
    $apuracoes = app(\Modules\Jana\Http\Controllers\MetasController::class)
        ->show($this->metaPropria->id)
        ->getData()['apuracoes'];

    expect($apuracoes)->toHaveCount(12);

    $datas = $apuracoes->pluck('data_ref')->map(fn ($d) => $d->toDateString())->all();
    $ordenado = $datas;
    rsort($ordenado);
    expect($datas)->toBe($ordenado);   // mais recente primeiro
});

it('US-COPI-031 · reapurar enfileira o Job com o businessId EXPLÍCITO da meta', function () {
    Queue::fake();
    $metaId = (int) $this->metaPropria->id;   // `$this` não fica bound dentro da closure abaixo

    $this->post("/ia/metas/{$metaId}/reapurar")
        ->assertRedirect(route('jana.metas.show', $metaId));

    // O worker do CT 100 não tem `session()` — se o businessId não viajar no Job, a
    // apuração roda sem tenant. É defesa Tier 0, não detalhe de implementação.
    Queue::assertPushed(ApurarMetaJob::class, function (ApurarMetaJob $job) use ($metaId) {
        return (int) $job->businessId === METAS_BIZ_CANONICO
            && (int) $job->meta->id === $metaId;
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// TIER 0 — ADR 0093. `MetaApuracao` não tem `business_id`: o isolamento das rotas
// de detalhe depende inteiramente do `Meta::findOrFail` passar pelo global scope.
// ─────────────────────────────────────────────────────────────────────────────

it('Tier 0 · show/edit de meta de OUTRO tenant → 404', function () {
    $this->get("/ia/metas/{$this->metaAdversaria->id}")->assertNotFound();
    $this->get("/ia/metas/{$this->metaAdversaria->id}/edit")->assertNotFound();
});

it('Tier 0 · update cross-tenant → 404 e o nome da meta alheia NÃO muda', function () {
    $nomeAntes = $this->metaAdversaria->nome;

    $this->patch("/ia/metas/{$this->metaAdversaria->id}", ['nome' => 'Invadido'])->assertNotFound();

    expect(Meta::withoutGlobalScopes()->find($this->metaAdversaria->id)->nome)->toBe($nomeAntes);
});

it('Tier 0 · destroy cross-tenant → 404 e a meta alheia CONTINUA ativa', function () {
    $this->delete("/ia/metas/{$this->metaAdversaria->id}")->assertNotFound();

    expect(Meta::withoutGlobalScopes()->find($this->metaAdversaria->id)->ativo)->toBeTruthy();
});

it('Tier 0 · reapurar cross-tenant → 404 e NENHUM job enfileirado', function () {
    Queue::fake();

    $this->post("/ia/metas/{$this->metaAdversaria->id}/reapurar")->assertNotFound();

    Queue::assertNotPushed(ApurarMetaJob::class);
});

it('Tier 0 · store NÃO aceita business_id de outro tenant vindo do payload', function () {
    $payload = metaPayload(['business_id' => METAS_BIZ_ADVERSARIO]);

    $this->post('/ia/metas', $payload);

    // Contrato declarado pelo PRÓPRIO StoreMetaRequest (docblock): "se business_id veio
    // no payload, controller verifica que matcha session OU user é superadmin antes de
    // persistir". O form Blade nunca manda o campo — não há uso legítimo pela UI.
    // É a mesma família de IDOR que o #4474 fechou no PeriodosController; aqui o alvo
    // é a ESCRITA, que nenhum global scope cobre (scope filtra SELECT, não INSERT).
    $vazou = Meta::withoutGlobalScopes()
        ->where('slug', $payload['slug'])
        ->where('business_id', METAS_BIZ_ADVERSARIO)
        ->exists();

    expect($vazou)->toBeFalse();
});

it('Tier 0 · CONTROLE POSITIVO: superadmin AINDA cria meta de plataforma (business_id null)', function () {
    // Sem este caso, o teste acima provaria só que a porta fechou — não que ela fechou
    // no lugar certo. A capacidade "meta da plataforma" é declarada pelo StoreMetaRequest
    // ("business_id NULLABLE permite meta da plataforma, superadmin only") e consumida
    // pelo SuperadminController (`whereNull('business_id')`); um gate que a matasse
    // trocaria um defeito por outro.
    \Spatie\Permission\Models\Permission::findOrCreate('jana.superadmin', 'web');
    $this->user->givePermissionTo('jana.superadmin');
    $this->user->forgetCachedPermissions();

    $payload = metaPayload(['business_id' => null]);

    $this->post('/ia/metas', $payload);

    $meta = Meta::withoutGlobalScopes()->where('slug', $payload['slug'])->first();
    expect($meta)->not->toBeNull()
        ->and($meta->business_id)->toBeNull();   // plataforma, e foi um superadmin que pediu
});
