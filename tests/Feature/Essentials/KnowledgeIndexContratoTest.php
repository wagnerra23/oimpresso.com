<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Contrato da tela /essentials/knowledge-base (Base de conhecimento interna) —
 * `KnowledgeBaseController@index`.
 *
 * UC-EKB-01        — a grade mostra os livros que a ACL me libera (público · meu · lista
 *                    que me inclui) e esconde o privado de terceiro.
 * UC-EKB-02 `[T0]` — livro de outro business não aparece, nem sendo público e de minha autoria.
 *
 * Os UC derivam do charter (`Index.charter.md` §Goals "ACL via Controller" + §Métricas) +
 * do controller real, nunca do protótipo.
 * Trio: resources/js/Pages/Essentials/Knowledge/{Index.charter.md,Index.casos.md}
 *
 * COMPLEMENTA o `Modules/Essentials/Tests/Feature/KnowledgeIndexTest.php` (render + props)
 * e o `KnowledgeXssSanitizationTest.php` (sanitização) — este prova a ACL e o Tier 0 no
 * payload que chega à tela, que nenhum dos dois exercita.
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant do usuário = 98 (fictício), adversário = 2.
 * NUNCA biz=4. O livro alheio é `public` e `created_by` = EU: as duas pernas da ACL o
 * deixariam passar, então só o filtro de `business_id` o segura — o caso discrimina.
 *
 * `Tests\TestCase` NÃO se declara: `tests/Pest.php` já faz `uses(TestCase::class)->in('Feature')`.
 *
 * @covers-us US-ESS-013
 * @see Modules/Essentials/Http/Controllers/KnowledgeBaseController.php
 */
uses(DatabaseTransactions::class);

const EKB_BIZ = 98;        // tenant canônico de teste (ADR 0358)
const EKB_BIZ_ALHEIO = 2;  // segundo tenant do seed
const EKB_OUTRO_AUTOR = 987654321; // `created_by` sem FK — autor que não sou eu

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['essentials_kb', 'essentials_kb_users', 'business', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode o migrate do módulo Essentials.");
        }
    }

    $user = User::where('business_id', EKB_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 — o seed canônico (pest-mysql-setup) não rodou.');
    }
    $this->kUser = $user;

    $role = Role::firstOrCreate(['name' => 'Admin#'.EKB_BIZ, 'guard_name' => 'web'], ['business_id' => EKB_BIZ]);
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
    $this->actingAs($user);
});

function ekbLivro(int $biz, int $autor, string $titulo, ?string $shareWith): int
{
    return DB::table('essentials_kb')->insertGetId([
        'business_id' => $biz,
        'title' => $titulo,
        'content' => '<p>conteúdo</p>',
        'status' => 'published',
        'kb_type' => 'knowledge_base',
        'parent_id' => null,
        'share_with' => $shareWith,
        'created_by' => $autor,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ekbInertiaVersion(): string
{
    try {
        return (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        return '';
    }
}

/** Títulos dos livros que o partial reload de `books` (Inertia::defer) devolve. */
function ekbTitulos($test): array
{
    $res = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => ekbInertiaVersion(),
        'X-Inertia-Partial-Data' => 'books',
        'X-Inertia-Partial-Component' => 'Essentials/Knowledge/Index',
    ])->get('/essentials/knowledge-base');

    $res->assertStatus(200);
    $res->assertJsonPath('component', 'Essentials/Knowledge/Index');

    return array_column($res->json('props.books') ?? [], 'title');
}

// ─────────────────────────────────────────────────────────────────────────────
it('UC-EKB-01 · a grade mostra público, meu e lista-que-me-inclui; esconde o privado de terceiro', function () {
    $tag = 'EKB01-'.uniqid();
    ekbLivro(EKB_BIZ, EKB_OUTRO_AUTOR, "$tag-publico", 'public');
    ekbLivro(EKB_BIZ, (int) $this->kUser->id, "$tag-meu-privado", 'private');
    $lista = ekbLivro(EKB_BIZ, EKB_OUTRO_AUTOR, "$tag-lista-comigo", 'only_with');
    DB::table('essentials_kb_users')->insert(['kb_id' => $lista, 'user_id' => $this->kUser->id]);
    ekbLivro(EKB_BIZ, EKB_OUTRO_AUTOR, "$tag-privado-alheio", 'private');
    ekbLivro(EKB_BIZ, EKB_OUTRO_AUTOR, "$tag-lista-sem-mim", 'only_with');

    $titulos = ekbTitulos($this);

    // Controle positivo: as três pernas da ACL liberam.
    expect($titulos)->toContain("$tag-publico");
    expect($titulos)->toContain("$tag-meu-privado");
    expect($titulos)->toContain("$tag-lista-comigo");

    // Contrato: o que nenhuma perna libera não chega à tela.
    expect($titulos)->not->toContain("$tag-privado-alheio");
    expect($titulos)->not->toContain("$tag-lista-sem-mim");
});

it('UC-EKB-02 · livro de outro business não aparece, nem público e de minha autoria', function () {
    $tag = 'EKB02-'.uniqid();
    ekbLivro(EKB_BIZ, (int) $this->kUser->id, "$tag-proprio-tenant", 'public');
    ekbLivro(EKB_BIZ_ALHEIO, (int) $this->kUser->id, "$tag-outro-tenant", 'public');

    $titulos = ekbTitulos($this);

    expect($titulos)->toContain("$tag-proprio-tenant");   // controle positivo
    expect($titulos)->not->toContain("$tag-outro-tenant"); // Tier 0
});
