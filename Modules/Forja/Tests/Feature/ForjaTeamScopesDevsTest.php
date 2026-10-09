<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Services\UserScopeService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread Forja/10 (D6, [CC] 2026-10-07 — "teste primeiro"): a lista de devs da tela
 * /ads/admin/team-scopes (prop deferida `users` ← `UserScopeService::listUsersWithAccess`).
 *
 * UC-TSCOPE-05 [T0] — a lista traz os devs do business da sessão, e só eles.
 *
 * No `main` de 2026-10-09 o serviço fazia JOIN com `user_businesses`, tabela que não existe
 * no schema: este teste nascia VERMELHO (QueryException), que é o que a thread pedia provar.
 * O vínculo real é `users.business_id`.
 *
 * Tenant 98 × 99 (ADR 0358). NUNCA biz=4. DatabaseTransactions: nada persiste.
 * ⛔ Não rodar local — Pest roda no CT 100 ou no CI. Skip sai exit 0: leia as assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['users', 'business', 'mcp_user_module_access'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — schema baseline não aplicado.");
        }
    }
});

function tsdevUsuario(int $businessId, string $rotulo): User
{
    return User::factory()->create([
        'business_id' => $businessId,
        'first_name' => 'TSDEV '.$rotulo,
        'username' => 'tsdev_'.$rotulo.'_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
}

it('UC-TSCOPE-05 · a lista de devs traz os do business da sessão, e só eles [T0]', function () {
    $biz = (int) $this->seededTenant()->id;
    $vizinhoBiz = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas distintas, senão o [T0] é tautológico.
    expect($vizinhoBiz)->not->toBe($biz);

    $devA = tsdevUsuario($biz, 'a');
    $devB = tsdevUsuario($biz, 'b');
    $vizinho = tsdevUsuario($vizinhoBiz, 'vizinho');

    $ids = collect(app(UserScopeService::class)->listUsersWithAccess($biz))->pluck('id')->all();

    // Os 2 devs do negócio (valores diferentes de propósito: um JOIN que ignorasse o
    // negócio traria o vizinho; um que não casasse nada traria nenhum dos dois).
    expect($ids)->toContain($devA->id);
    expect($ids)->toContain($devB->id);
    expect($ids)->not->toContain($vizinho->id);

    // Controle positivo do lado do vizinho: ele aparece na lista DO negócio dele.
    $idsVizinho = collect(app(UserScopeService::class)->listUsersWithAccess($vizinhoBiz))->pluck('id')->all();
    expect($idsVizinho)->toContain($vizinho->id);
    expect($idsVizinho)->not->toContain($devA->id);
});

it('UC-TSCOPE-05 · dev excluído (soft delete) não aparece', function () {
    $biz = (int) $this->seededTenant()->id;
    $ativo = tsdevUsuario($biz, 'ativo');
    $excluido = tsdevUsuario($biz, 'excluido');
    DB::table('users')->where('id', $excluido->id)->update(['deleted_at' => now()]);

    $ids = collect(app(UserScopeService::class)->listUsersWithAccess($biz))->pluck('id')->all();

    expect($ids)->toContain($ativo->id);
    expect($ids)->not->toContain($excluido->id);
});
