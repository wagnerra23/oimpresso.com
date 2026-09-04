<?php

declare(strict_types=1);

/**
 * TEMPORARIO — diagnostico do 403 da lane backup-pest. REMOVER no mesmo PR.
 *
 * Rodada 1 (run 33912071371) mostrou: o mecanismo funciona — can(backup)=true e
 * GET /backup=200 — MAS so no caso que rodou PRIMEIRO. Todos os outros arquivos, depois,
 * deram 403. Duas explicacoes concorrentes, e elas exigem desenho experimental pra separar:
 *
 *   (V) VARIAVEL: o meu caso recarregava o user (User::find) depois do givePermissionTo;
 *       os testes reais usam a instancia que o factory devolveu.
 *   (O) ORDEM: o primeiro caso do processo passa e os seguintes nao (contaminacao entre
 *       testes — o banco volta pelo DatabaseTransactions, mas o processo PHP e o mesmo).
 *
 * Desenho que separa: 3 casos na ordem B / A / B.
 *   B=200, A=403, B=200  -> e a VARIAVEL (recarregar)
 *   B=200, A=403, B=403  -> e a ORDEM (contaminacao)
 */

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

/** Monta o cenario e devolve o status do GET /backup. $recarrega distingue A de B. */
function diagStatus(object $teste, bool $recarrega): array
{
    Permission::firstOrCreate(['name' => 'backup', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $biz = $teste->seededTenant();
    $u = User::factory()->create(['business_id' => $biz->id]);
    $u->givePermissionTo('backup');

    if ($recarrega) {
        $u = User::find($u->id);
    }

    $antesDoRequest = $u->can('backup');

    $teste->actingAs($u);
    session(['user.business_id' => $biz->id, 'business.id' => $biz->id]);

    return [
        'status' => $teste->get('/backup')->status(),
        'can_antes' => var_export($antesDoRequest, true),
        'perm_id' => (string) Permission::where('name', 'backup')->value('id'),
    ];
}

function diagEcho(string $rotulo, array $r): void
{
    fwrite(STDERR, sprintf(
        "\n===== DIAG %s ===== status=%s can_antes=%s permission_id=%s\n",
        $rotulo, $r['status'], $r['can_antes'], $r['perm_id']
    ));
}

test('DIAG 1 B recarregando (esperado 200 pela rodada 1)', function () {
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente');
    }
    diagEcho('1 B recarrega', diagStatus($this, true));
    expect(true)->toBeTrue();
});

test('DIAG 2 A sem recarregar (replica o BackupJobTest)', function () {
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente');
    }
    diagEcho('2 A sem recarregar', diagStatus($this, false));
    expect(true)->toBeTrue();
});

test('DIAG 3 B recarregando de novo (separa VARIAVEL de ORDEM)', function () {
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema ausente');
    }
    diagEcho('3 B recarrega', diagStatus($this, true));
    expect(true)->toBeTrue();
});
