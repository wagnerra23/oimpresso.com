<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Contrato da tela /essentials/document (Arquivos + Memos) — `DocumentController`.
 *
 * UC-EDOC-01 `[T0]` — a lista de memos mostra o meu e o compartilhado comigo, e mais nada.
 * UC-EDOC-02        — criar memo grava no tenant da sessão e volta pra aba Memos.
 * UC-EDOC-03        — remover só apaga item próprio.
 * UC-EDOC-04 `[T0]` — remover apaga junto os compartilhamentos, só no tenant da sessão.
 *
 * Os UC derivam do charter (`Index.charter.md`) + do controller real, nunca do protótipo.
 * Trio: resources/js/Pages/Essentials/Documents/{Index.charter.md,Index.casos.md}
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant do usuário = 98 (fictício), adversário = 2.
 * NUNCA biz=4. O memo alheio do UC-EDOC-01 é criado com `user_id` = EU no tenant 2 —
 * assim só o filtro de `business_id` o separa (o filtro de autoria o deixaria passar),
 * e o caso discrimina de verdade.
 *
 * Usa memos (texto) e não arquivos: o caminho `document` exige upload físico e
 * `Storage::delete` — fora do que este contrato precisa provar.
 *
 * `Tests\TestCase` NÃO se declara: `tests/Pest.php` já faz `uses(TestCase::class)->in('Feature')`.
 *
 * @covers-us US-ESS-007
 * @see Modules/Essentials/Http/Controllers/DocumentController.php
 */
uses(DatabaseTransactions::class);

const EDOC_BIZ = 98;        // tenant canônico de teste (ADR 0358)
const EDOC_BIZ_ALHEIO = 2;  // segundo tenant do seed

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL.');
    }
    foreach (['essentials_documents', 'essentials_document_shares', 'business', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode o migrate do módulo Essentials.");
        }
    }

    $user = User::where('business_id', EDOC_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 — o seed canônico (pest-mysql-setup) não rodou.');
    }
    // Outro autor REAL: a listagem faz INNER JOIN em `users`, então autor inexistente
    // sumiria por vácuo e o caso "não compartilhado não aparece" ficaria verde à toa.
    // Prefere o mesmo tenant; sem ele, qualquer outro user serve (o ACL é por user_id).
    $outro = User::where('id', '!=', $user->id)->orderByRaw('business_id = ? desc', [EDOC_BIZ])->first();
    if (! $outro) {
        $this->markTestSkipped('Precisa de um segundo usuário no seed.');
    }
    $this->eUser = $user;
    $this->eOutro = $outro;

    $role = Role::firstOrCreate(['name' => 'Admin#'.EDOC_BIZ, 'guard_name' => 'web'], ['business_id' => EDOC_BIZ]);
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
    $this->actingAs($user);
});

function edocMemo(int $biz, int $userId, string $titulo): int
{
    return DB::table('essentials_documents')->insertGetId([
        'business_id' => $biz,
        'user_id' => $userId,
        'type' => 'memos',
        'name' => $titulo,
        'description' => 'corpo de '.$titulo,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function edocInertiaVersion(): string
{
    try {
        return (string) (new \App\Http\Middleware\HandleInertiaRequests)->version(request());
    } catch (\Throwable $e) {
        return '';
    }
}

/** Partial reload da prop deferida `memos` — sem ele a prop nem existe no payload. */
function edocMemos($test): array
{
    $res = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => edocInertiaVersion(),
        'X-Inertia-Partial-Data' => 'memos',
        'X-Inertia-Partial-Component' => 'Essentials/Documents/Index',
    ])->get('/essentials/document?type=memos');

    $res->assertStatus(200);
    $res->assertJsonPath('component', 'Essentials/Documents/Index');

    return collect($res->json('props.memos') ?? [])->keyBy('name')->all();
}

// ─────────────────────────────────────────────────────────────────────────────
it('UC-EDOC-01 · memos: o meu e o compartilhado comigo aparecem; o não-compartilhado e o de outro tenant não', function () {
    $tag = 'EDOC01-'.uniqid();
    edocMemo(EDOC_BIZ, $this->eUser->id, "$tag-meu");
    $compartilhado = edocMemo(EDOC_BIZ, $this->eOutro->id, "$tag-compartilhado");
    edocMemo(EDOC_BIZ, $this->eOutro->id, "$tag-privado-alheio");
    edocMemo(EDOC_BIZ_ALHEIO, $this->eUser->id, "$tag-outro-tenant");

    DB::table('essentials_document_shares')->insert([
        'document_id' => $compartilhado,
        'value_type' => 'user',
        'value' => $this->eUser->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $memos = edocMemos($this);

    // Controle positivo: sem ele, lista vazia/403 deixaria os asserts negativos verdes.
    expect($memos)->toHaveKey("$tag-meu");
    expect($memos["$tag-meu"]['is_mine'])->toBeTrue();
    expect($memos)->toHaveKey("$tag-compartilhado");
    expect($memos["$tag-compartilhado"]['is_mine'])->toBeFalse();

    // Contrato
    expect($memos)->not->toHaveKey("$tag-privado-alheio");
    expect($memos)->not->toHaveKey("$tag-outro-tenant"); // Tier 0
});

it('UC-EDOC-02 · criar memo grava no tenant da sessão e volta pra aba Memos', function () {
    $titulo = 'EDOC02-'.uniqid();

    $res = $this->post('/essentials/document', ['name' => $titulo, 'body' => 'texto do memo']);

    $res->assertStatus(302);
    $res->assertSessionHasNoErrors();
    expect($res->headers->get('Location'))->toContain('type=memos');

    $linhas = DB::table('essentials_documents')->where('name', $titulo)->get();
    expect($linhas)->toHaveCount(1);
    expect((int) $linhas[0]->business_id)->toBe(EDOC_BIZ);
    expect((int) $linhas[0]->user_id)->toBe((int) $this->eUser->id);
    expect($linhas[0]->type)->toBe('memos');
    expect($linhas[0]->description)->toBe('texto do memo');
});

it('UC-EDOC-03 · remover apaga o item próprio e recusa em silêncio o de terceiro', function () {
    $tag = 'EDOC03-'.uniqid();
    $meu = edocMemo(EDOC_BIZ, $this->eUser->id, "$tag-meu");
    $alheio = edocMemo(EDOC_BIZ, $this->eOutro->id, "$tag-alheio");

    $this->delete("/essentials/document/{$alheio}")->assertStatus(302);
    expect(DB::table('essentials_documents')->where('id', $alheio)->exists())->toBeTrue();

    // Controle positivo: o mesmo endpoint de fato apaga quando o item é meu.
    $this->delete("/essentials/document/{$meu}")->assertStatus(302);
    expect(DB::table('essentials_documents')->where('id', $meu)->exists())->toBeFalse();
});

it('UC-EDOC-04 · remover o item próprio apaga junto os compartilhamentos dele, e só dentro do tenant da sessão', function () {
    $adversario = $this->seededSupportClientTenant(); // biz=99 fictício
    $tag = 'EDOC04-'.uniqid();

    $meu = edocMemo(EDOC_BIZ, $this->eUser->id, "$tag-meu");
    // Autoria MINHA no outro tenant: só o filtro de business_id do destroy o protege.
    $outroTenant = edocMemo((int) $adversario->id, $this->eUser->id, "$tag-outro-tenant");

    $share = fn (int $docId, string $tipo, int $valor) => DB::table('essentials_document_shares')->insert([
        'document_id' => $docId, 'value_type' => $tipo, 'value' => $valor,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $share($meu, 'user', (int) $this->eOutro->id);
    $share($meu, 'role', 1);
    $share($outroTenant, 'user', (int) $this->eOutro->id);

    $sharesDe = fn (int $docId) => DB::table('essentials_document_shares')->where('document_id', $docId)->count();

    // Pré-condição anti-vácuo: os compartilhamentos existem antes da exclusão.
    expect($sharesDe($meu))->toBe(2);
    expect($sharesDe($outroTenant))->toBe(1);

    // Tier 0: o id do outro tenant não é alcançável pela sessão do 98.
    $this->delete("/essentials/document/{$outroTenant}")->assertStatus(302);
    expect(DB::table('essentials_documents')->where('id', $outroTenant)->exists())->toBeTrue();
    expect($sharesDe($outroTenant))->toBe(1);

    // Contrato: apagar o meu leva os compartilhamentos junto — nada de linha órfã.
    $this->delete("/essentials/document/{$meu}")->assertStatus(302);
    expect(DB::table('essentials_documents')->where('id', $meu)->exists())->toBeFalse();
    expect($sharesDe($meu))->toBe(0);
});
