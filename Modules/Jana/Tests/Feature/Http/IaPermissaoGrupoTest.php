<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-JPERM-01 e UC-JPERM-02 — o grupo `/ia` é trancado por `jana.access`, e `jana.chat`
 * é outra permissão.
 *
 * ── O CONTRATO (emenda de casos do Cowork, NÃO o código — §5 2026-06-05)
 * `prototipo-ui/cowork/Wagner/cowork-inbox/jana/JANA-CASOS-EMENDA-PERMISSAO-2026-08-27.md`,
 * thread 04 do playbook `cowork-inbox/jana/playbook/`.
 *
 * ── O QUE JÁ EXISTIA (não duplicado aqui)
 * `Modules/Jana/Tests/Feature/JanaAccessGateTest.php` prova o gate SÓ em `/ia` e só
 * "não é 403" com a permissão. Este arquivo cobre o delta que o UC pede: as QUATRO
 * telas, 200 no positivo (anti-vácuo contra 500) e nenhuma rota escapando do grupo.
 *
 * ── UC-JPERM-02 fecha como LIMITE MEDIDO, não verde
 * MEDIDO 2026-09-23: `jana.chat` está declarada em `Resources/permissions.php` e aplicada
 * em ZERO lugares — nem middleware, nem `SendChatMessageRequest::authorize()` (que só
 * exige estar logado), nem prop `podeConversar` no Painel. Qual a trava e o rollout pros
 * funcionários é decisão [W]; o caso abaixo prova o estado atual e QUEBRA quando a
 * trava nascer — aí troque-o pelo UC completo (403 sem / 200 com). Mesmo idioma do
 * UC-MEM-08 em `MemoriaPermissaoTest.php`.
 *
 * TENANT: 98 (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 */
const IAPERM_BIZ = 98;
const IAPERM_TELAS = ['jana.index', 'jana.chat.index', 'jana.pro.index', 'jana.memoria.index'];

function iaPermConcede(User $u, string ...$perms): void
{
    $u->givePermissionTo($perms);
    $u->forgetCachedPermissions();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0062).');
    }
    if (! Business::find(IAPERM_BIZ)) {
        $this->markTestSkipped('business_id='.IAPERM_BIZ.' ausente — rode o seed do pest-mysql-setup.');
    }
    $user = User::where('business_id', IAPERM_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id='.IAPERM_BIZ.'.');
    }

    // Cache do Spatie sobrevive ao rollback da transação: sem limpar, uma permissão
    // criada (e revertida) por um caso anterior volta com id morto — FK/PermissionDoesNotExist
    // conforme a ordem aleatória. Medido no 1º run no CT 100.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('jana.access', 'web');
    Permission::findOrCreate('jana.chat', 'web');

    // NÃO-admin: com `Admin#98` o Gate::before (AuthServiceProvider) liberaria tudo e o
    // arquivo mediria o Gate, não a permissão. Rollback pela transação.
    $user->syncRoles([]);
    $user->syncPermissions([]);
    $user->forgetCachedPermissions();
    $this->user = $user;

    $this->actingAs($user);
    session([
        'user.business_id' => IAPERM_BIZ,
        'business' => ['id' => IAPERM_BIZ, 'name' => Business::find(IAPERM_BIZ)->name],
    ]);
});


// Limpa o cache do Spatie DEPOIS também: a permissão criada aqui some no rollback da
// transação, e um cache que sobrevive a ela vira `PermissionDoesNotExist`/FK no teste
// SEGUINTE (outro arquivo). Medido 2026-09-24 no CT 100 com a seleção da lane Jana.
afterEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('CONTROLE: o Gate::before não libera este usuário — sem isso nada abaixo mede permissão', function () {
    expect($this->user->can('jana.access'))->toBeFalse();
    expect($this->user->can('jana.chat'))->toBeFalse();
})->group('tier0');

it('UC-JPERM-01 · sem jana.access, as 4 telas de /ia são 403', function () {
    foreach (IAPERM_TELAS as $nome) {
        expect($this->get(route($nome))->status())->toBe(403, "{$nome} abriu sem jana.access");
    }
})->group('tier0');

it('UC-JPERM-01 · com jana.access, as 4 telas abrem com 200 (não só "não é 403")', function () {
    iaPermConcede($this->user, 'jana.access');

    foreach (IAPERM_TELAS as $nome) {
        expect($this->get(route($nome))->status())->toBe(200, "{$nome} não abriu com jana.access");
    }
})->group('tier0');

it('UC-JPERM-01 · nenhuma rota nomeada jana.* sob /ia escapa do can:jana.access', function () {
    // Oráculo = registry vivo de rotas, não leitura do routes.php (§5 2026-07-28).
    $vistas = [];
    $escaparam = [];
    foreach (Route::getRoutes() as $r) {
        $nome = (string) $r->getName();
        $uri = $r->uri();
        if (! str_starts_with($nome, 'jana.') || ($uri !== 'ia' && ! str_starts_with($uri, 'ia/'))) {
            continue;
        }
        // `/ia/install/*` é grupo PRÓPRIO por desenho (routes.php §2, ADR 0023): o
        // Install 1-clique do /manage-modules, não tela da Jana. Medido 2026-09-23 no
        // 1º run: são as 4 únicas rotas jana.* sob ia/ fora do gate.
        if (str_starts_with($nome, 'jana.install.')) {
            continue;
        }
        $vistas[] = $nome;
        if (! in_array('can:jana.access', $r->gatherMiddleware(), true)) {
            $escaparam[] = "{$nome} ({$uri})";
        }
    }

    // Anti-vácuo: o filtro enxergou as 4 telas — senão um filtro errado dava [] por nada.
    expect(array_values(array_diff(IAPERM_TELAS, $vistas)))->toBe([]);
    expect($escaparam)->toBe([]);
})->group('tier0');

it('UC-JPERM-02 · LIMITE MEDIDO: hoje jana.chat NÃO trava o envio — quando a trava existir, este caso DEVE quebrar', function () {
    iaPermConcede($this->user, 'jana.access');
    expect($this->user->can('jana.chat'))->toBeFalse();

    $id = DB::table('jana_conversas')->insertGetId([
        'business_id' => IAPERM_BIZ,
        'user_id' => $this->user->id,
        'titulo' => 'conversa do limite UC-JPERM-02',
        'status' => 'ativa',
        'iniciada_em' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Corpo VAZIO de propósito: sem trava, a FormRequest valida e reprova `content`
    // (302 + erro) — nenhuma mensagem gravada, nenhum LLM chamado. Com trava, vira 403.
    $this->post(route('jana.conversas.mensagens.store', $id), [])
        ->assertStatus(302)
        ->assertSessionHasErrors('content');

    // O Painel abre e ainda não diz à tela se o usuário pode conversar.
    $this->get(route('jana.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->missing('podeConversar'));
})->group('tier0');
