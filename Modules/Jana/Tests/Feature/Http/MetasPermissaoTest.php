<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Meta;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-JPERM-05 (Tier 0) e UC-JPERM-03 — metas no Painel `/ia`.
 *
 * ── O CONTRATO: emenda de casos do Cowork (thread 04 do playbook jana), não o código.
 *
 * ── UC-JPERM-05 é VERDE esperado: o payload `metas` do Painel de biz A não traz meta de
 * biz B — nem pra quem tem `jana.superadmin` (que só abre as metas de PLATAFORMA, nulas).
 * `SuperadminMetasCrossTenantTest` cobre o `MetasController`; aqui o alvo é o PAINEL.
 *
 * ── UC-JPERM-03 — a trava foi LIGADA em 2026-09-23 ([W]). Até ali `jana.metas.manage`
 * (risk medium) estava aplicada em ZERO lugares e o caso era um LIMITE MEDIDO. Agora as
 * rotas de escrita de meta, período e fonte exigem a permissão; ler segue com `jana.access`.
 *
 * TENANT: 98 × 2 adversário (seed do pest-mysql-setup). NUNCA biz=4, NUNCA biz=1.
 */
const METAPERM_BIZ = 98;
const METAPERM_BIZ_ALHEIO = 2;

function metaPermCria(int $businessId, string $nome): int
{
    return (int) Meta::withoutGlobalScopes()->create([
        'business_id' => $businessId,
        'slug' => 'jperm_'.uniqid(),
        'nome' => $nome,
        'unidade' => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo' => true,
        'origem' => 'manual',
    ])->id;
}

/** Os ids de meta que o Painel entregou — lido do payload, não do HTML. */
function metaPermIdsNoPainel($test): array
{
    $ids = [];
    $test->get(route('jana.index'))
        ->assertStatus(200)
        ->assertInertia(function ($page) use (&$ids) {
            $ids = array_map(fn ($m) => (int) $m['id'], $page->toArray()['props']['metas'] ?? []);
        });

    return $ids;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    foreach ([METAPERM_BIZ, METAPERM_BIZ_ALHEIO] as $bid) {
        if (! Business::find($bid)) {
            $this->markTestSkipped("business_id={$bid} ausente — rode o seed do pest-mysql-setup.");
        }
    }
    $user = User::where('business_id', METAPERM_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id='.METAPERM_BIZ.'.');
    }

    // Cache do Spatie sobrevive ao rollback da transação: sem limpar, uma permissão
    // criada (e revertida) por um caso anterior volta com id morto — FK/PermissionDoesNotExist
    // conforme a ordem aleatória. Medido no 1º run no CT 100.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['jana.access', 'jana.metas.manage', 'jana.superadmin'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    // NÃO-admin (Gate::before). Só a permissão base — o "usuário comum" do UC.
    $user->syncRoles([]);
    $user->syncPermissions(['jana.access']);
    $user->forgetCachedPermissions();
    $this->user = $user;

    $this->actingAs($user);
    session([
        'user.business_id' => METAPERM_BIZ,
        'business' => ['id' => METAPERM_BIZ, 'name' => Business::find(METAPERM_BIZ)->name],
    ]);

    $this->minha = metaPermCria(METAPERM_BIZ, 'CANARIO-META-'.METAPERM_BIZ);
    $this->alheia = metaPermCria(METAPERM_BIZ_ALHEIO, 'CANARIO-META-'.METAPERM_BIZ_ALHEIO);
});


// Limpa o cache do Spatie DEPOIS também: a permissão criada aqui some no rollback da
// transação, e um cache que sobrevive a ela vira `PermissionDoesNotExist`/FK no teste
// SEGUINTE (outro arquivo). Medido 2026-09-24 no CT 100 com a seleção da lane Jana.
afterEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('UC-JPERM-05 · Tier 0 — o Painel de biz 98 não traz meta de outro business', function () {
    $ids = metaPermIdsNoPainel($this);

    // Anti-vácuo: a própria meta chegou — senão um payload vazio passava no assert de baixo.
    expect($ids)->toContain($this->minha);
    expect($ids)->not->toContain($this->alheia);
})->group('tier0');

it('UC-JPERM-05 · Tier 0 — jana.superadmin logado em 98 também não vê a meta de 2', function () {
    $this->user->givePermissionTo('jana.superadmin');
    $this->user->forgetCachedPermissions();
    expect($this->user->can('jana.superadmin'))->toBeTrue();

    $ids = metaPermIdsNoPainel($this);

    expect($ids)->toContain($this->minha);
    expect($ids)->not->toContain($this->alheia);
})->group('tier0');

it('UC-JPERM-03 · sem jana.metas.manage, meta é leitura: as 4 escritas do UC dão 403', function () {
    expect($this->user->can('jana.metas.manage'))->toBeFalse();

    $this->post(route('jana.metas.store'), [])->assertStatus(403);
    $this->patch(route('jana.metas.update', $this->minha), ['nome' => 'x'])->assertStatus(403);
    $this->post(route('jana.metas.reapurar', $this->minha))->assertStatus(403);
    $this->patch(route('jana.fontes.update', $this->minha), [])->assertStatus(403);

    // A leitura segue: o Painel abre COM a meta e diz à tela que ela não gerencia.
    expect(metaPermIdsNoPainel($this))->toContain($this->minha);
    $this->get(route('jana.index'))->assertInertia(fn ($page) => $page->where('podeGerenciarMetas', false));
})->group('tier0');

it('UC-JPERM-03 · com jana.metas.manage a trava deixa passar, e a tela recebe a flag', function () {
    $this->user->givePermissionTo('jana.metas.manage');
    $this->user->forgetCachedPermissions();

    // Corpo inválido de propósito: passou da trava = chegou na validação (302 + erro),
    // e nada foi gravado.
    $this->post(route('jana.metas.store'), [])
        ->assertStatus(302)
        ->assertSessionHasErrors('slug');
    $this->get(route('jana.index'))->assertInertia(fn ($page) => $page->where('podeGerenciarMetas', true));
})->group('tier0');
