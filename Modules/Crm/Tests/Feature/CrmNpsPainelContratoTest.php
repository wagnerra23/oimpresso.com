<?php
// @covers-us US-CRM-097

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Painel NPS (`/crm/nps`) — US-CRM-097 critério 6 · Index.casos.md (UC-NPSP-01..02).
 *
 * Conta à mão: notas 10, 9, 8, 3 → 2 promotores, 1 neutro, 1 detrator → (2 − 1) / 4 × 100 = +25.
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 * Não roda local (proibicoes.md). Lane: verticais-pest.yml (MySQL).
 */
function npspUsuario(int $biz, string $username, array $permissoes): User
{
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'), 'business_id' => $biz,
        'first_name' => 'Nps', 'last_name' => 'Teste', 'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

function npspResposta(int $biz, int $nota): void
{
    DB::table('crm_nps_pesquisas')->insert([
        'business_id' => $biz, 'contact_id' => 1, 'origem_tipo' => 'venda', 'origem_id' => random_int(900000000, 999999999),
        'token' => Str::lower(Str::random(40)), 'status' => 'respondido', 'nota' => $nota,
        'enviada_em' => now()->subDays(2), 'respondida_em' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Pedido parcial como o browser faz (X-Inertia + X-Requested-With). */
function npspParcial(User $user, string $prop): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = test()->actingAs($user)->get('/crm/nps', [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop, 'X-Inertia-Partial-Component' => 'Crm/Nps/Index',
    ]);
    $r->assertOk();

    return $r->json('props.'.$prop);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('crm_nps_pesquisas')) {
        $this->markTestSkipped('crm_nps_pesquisas ausente — roda na lane MySQL / CT 100.');
    }
    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);
});

it('UC-NPSP-01 · sem crm.nps.ver é 403; com a permissão abre em 30 dias', function () {
    $sem = npspUsuario($this->bizId, 'nps_sem_test', []);
    $com = npspUsuario($this->bizId, 'nps_ver_test', ['crm.nps.ver']);

    $this->actingAs($sem)->get('/crm/nps')->assertForbidden();
    $this->actingAs($com)->get('/crm/nps')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Crm/Nps/Index', false)->where('dias', 30)->where('periodos', [7, 30, 90]));
});

it('UC-NPSP-02 · resumo e lista só com a empresa da sessão, detratores primeiro', function () {
    DB::table('crm_nps_pesquisas')->where('business_id', $this->bizId)->delete();
    foreach ([10, 9, 8, 3] as $n) {
        npspResposta($this->bizId, $n);
    }
    npspResposta($this->outroBizId, 0);
    $user = npspUsuario($this->bizId, 'nps_ver_test', ['crm.nps.ver']);

    expect(npspParcial($user, 'resumo'))->toBe([
        'respostas' => 4, 'promotores' => 2, 'neutros' => 1, 'detratores' => 1, 'nps' => 25, 'enviadas' => 4,
    ]);
    $notas = array_column(npspParcial($user, 'respostas'), 'nota');
    expect($notas)->toBe([3, 8, 9, 10]);
});
