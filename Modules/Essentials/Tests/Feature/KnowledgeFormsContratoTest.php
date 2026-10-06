<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato das telas Essentials/Knowledge/{Create,Edit,Show} — os três `.casos.md` ao lado
 * dos `.tsx`. Os UC derivam dos charters + `KnowledgeBaseController` (create/store/edit/
 * update/show), nunca do `.tsx` (§5 2026-06-05). Cada `it()` cita o UC-id (casos-gate G-2).
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 (fictício) × 2 (alheio do seed). NUNCA biz=4.
 * Fixtures por `DB::table`: `KnowledgeBase` usa `HasBusinessScope`, e o que se mede é o
 * filtro do controller — o fixture não pode depender do escopo que está sob teste.
 */
const EKBF_BIZ = 98;
const EKBF_BIZ_ALHEIO = 2;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['essentials_kb', 'essentials_kb_users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode o migrate do Essentials.");
        }
    }
    $user = User::where('business_id', EKBF_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 — o seed canônico não rodou.');
    }
    $role = Role::firstOrCreate(['name' => 'Admin#'.EKBF_BIZ, 'guard_name' => 'web'], ['business_id' => EKBF_BIZ]);
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->kUser = $user;
    session()->flush();
    $this->actingAs($user);
});

function ekbfNo(int $biz, int $autor, string $tipo, ?int $pai, string $conteudo = '<p>ok</p>'): int
{
    return DB::table('essentials_kb')->insertGetId([
        'business_id' => $biz, 'title' => $tipo.'-'.uniqid(), 'content' => $conteudo,
        'status' => 'published', 'kb_type' => $tipo, 'parent_id' => $pai,
        'share_with' => $tipo === 'knowledge_base' ? 'public' : null,
        'created_by' => $autor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function ekbfGet($test, string $url)
{
    try {
        $versao = (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        $versao = '';
    }

    return $test->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])->get($url);
}

it('UC-EKBC-01 · dentro de uma seção, o form recebe o pai; sem pai, recebe os usuários', function () {
    $livro = ekbfNo(EKBF_BIZ, (int) $this->kUser->id, 'knowledge_base', null);
    $secao = ekbfNo(EKBF_BIZ, (int) $this->kUser->id, 'section', $livro);

    $comPai = ekbfGet($this, "/essentials/knowledge-base/create?parent={$secao}");
    $comPai->assertStatus(200)->assertJsonPath('component', 'Essentials/Knowledge/Create');
    expect($comPai->json('props.parent.id'))->toBe($secao);
    expect($comPai->json('props.parent.kb_type'))->toBe('section');

    $semPai = ekbfGet($this, '/essentials/knowledge-base/create');
    expect($semPai->json('props.parent'))->toBeNull();
    // A lista é a do dropdown do MEU business (users tipo `user`, sem comissionado) — e só ela.
    $esperado = DB::table('users')->where('business_id', EKBF_BIZ)->where('user_type', 'user')
        ->where('is_cmmsn_agnt', 0)->whereNull('deleted_at')->orderBy('id')->pluck('id')->map('intval')->all();
    $recebido = array_column($semPai->json('props.users') ?? [], 'id');
    sort($recebido);
    expect($recebido)->toBe($esperado);
});

it('UC-EKBC-02 · salvar um livro restrito grava no meu business com a lista de acesso', function () {
    $titulo = 'EKBC02-'.uniqid();

    $this->post('/essentials/knowledge-base', [
        'title' => $titulo, 'content' => '<p>x</p>', 'kb_type' => 'knowledge_base',
        'share_with' => 'only_with', 'user_ids' => [(int) $this->kUser->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $kb = DB::table('essentials_kb')->where('title', $titulo)->first();
    expect($kb)->not->toBeNull();
    expect((int) $kb->business_id)->toBe(EKBF_BIZ);
    expect(DB::table('essentials_kb_users')->where('kb_id', $kb->id)->pluck('user_id')->map('intval')->all())
        ->toBe([(int) $this->kUser->id]);
});

it('UC-EKBE-01 · a edição abre com os usuários de acesso já marcados', function () {
    $livro = ekbfNo(EKBF_BIZ, (int) $this->kUser->id, 'knowledge_base', null);
    DB::table('essentials_kb_users')->insert(['kb_id' => $livro, 'user_id' => $this->kUser->id]);

    $res = ekbfGet($this, "/essentials/knowledge-base/{$livro}/edit");
    $res->assertStatus(200)->assertJsonPath('component', 'Essentials/Knowledge/Edit');
    expect($res->json('props.kb.id'))->toBe($livro);
    expect($res->json('props.kb.assigned_user_ids'))->toBe([(int) $this->kUser->id]);
});

it('UC-EKBE-02 · [T0] atualizar nó de outro business devolve 404 e não muda o título', function () {
    $alheio = ekbfNo(EKBF_BIZ_ALHEIO, (int) $this->kUser->id, 'knowledge_base', null);
    $antes = DB::table('essentials_kb')->where('id', $alheio)->value('title');

    $res = $this->put("/essentials/knowledge-base/{$alheio}", ['title' => 'invadido', 'share_with' => 'public']);

    expect($res->status())->toBe(404);
    expect(DB::table('essentials_kb')->where('id', $alheio)->value('title'))->toBe($antes);
});

it('UC-EKBS-01 · abrir um artigo monta o livro de topo e entrega o conteúdo sanitizado', function () {
    $livro = ekbfNo(EKBF_BIZ, (int) $this->kUser->id, 'knowledge_base', null);
    $secao = ekbfNo(EKBF_BIZ, (int) $this->kUser->id, 'section', $livro);
    $artigo = ekbfNo(EKBF_BIZ, (int) $this->kUser->id, 'article', $secao, '<p>passo 1</p><script>alert(1)</script>');

    $res = ekbfGet($this, "/essentials/knowledge-base/{$artigo}");
    $res->assertStatus(200)->assertJsonPath('component', 'Essentials/Knowledge/Show');
    expect($res->json('props.book.id'))->toBe($livro);
    expect($res->json('props.sectionId'))->toBe($secao);
    expect($res->json('props.articleId'))->toBe($artigo);
    expect($res->json('props.item.content'))->toContain('passo 1');
    expect($res->json('props.item.content'))->not->toContain('<script');
});

it('UC-EKBS-02 · [T0] nó de outro business não abre', function () {
    $proprio = ekbfNo(EKBF_BIZ, (int) $this->kUser->id, 'knowledge_base', null);
    $alheio = ekbfNo(EKBF_BIZ_ALHEIO, (int) $this->kUser->id, 'knowledge_base', null);

    ekbfGet($this, "/essentials/knowledge-base/{$proprio}")->assertStatus(200); // controle positivo
    expect(ekbfGet($this, "/essentials/knowledge-base/{$alheio}")->status())->toBe(404);
});
