<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Escopo da ficha Blade `/crm/leads/{id}` (LeadController::show) — thread Crm/08.
 *
 * A ficha filtrava só `business_id`: pelo id abria um cliente do mesmo negócio. Agora fora de
 * `type = lead` é 404, como fora do negócio. O caso do lead verde é a âncora positiva: prova que a
 * requisição chega ao controller, para o 404 do cliente não ser 404 de rota.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/LeadController::show()
 */
const SHOW_TAG = '[lead08]';
const SHOW_BIZ = 98;
const SHOW_OUTRO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: contacts/crm_lead_users requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_lead_users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    showLimpa();
});

afterEach(fn () => showLimpa());

function showLimpa(): void
{
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.SHOW_TAG.'%')->delete();
    }
}

/** `id` é guarded no Business: id e dono vão explícitos (mesmo motivo do CrmLeadsContratoTest). */
function showNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function showUsuario(string $username, int $biz = SHOW_BIZ): User
{
    showNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Lead', 'last_name' => 'Ficha',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions([Permission::firstOrCreate(['name' => 'crm.access_all_leads', 'guard_name' => 'web'])]);

    return $user;
}

function showContato(int $biz, string $nome, User $dono, string $tipo): int
{
    showNegocio($biz);
    $id = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => $tipo, 'name' => $nome.' '.SHOW_TAG, 'mobile' => '0',
        'contact_id' => 'S'.random_int(100000, 999999),
        'created_by' => $dono->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_lead_users')->insert(['contact_id' => $id, 'user_id' => $dono->id]);

    return $id;
}

function showAbre(User $user, int $id)
{
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    return test()->actingAs($user)->withSession($sessao)->get('/crm/leads/'.$id);
}

it('a ficha abre o lead do próprio negócio (âncora positiva)', function () {
    $user = showUsuario('lead_ficha_test');
    $lead = showContato(SHOW_BIZ, 'Lead da ficha', $user, 'lead');

    showAbre($user, $lead)->assertOk()->assertViewIs('crm::lead.show');
});

it('a ficha de lead não abre cliente do mesmo negócio pelo id → 404', function () {
    $user = showUsuario('lead_ficha_test');
    $cliente = showContato(SHOW_BIZ, 'Cliente nao lead', $user, 'customer');
    $fornecedor = showContato(SHOW_BIZ, 'Fornecedor nao lead', $user, 'supplier');

    showAbre($user, $cliente)->assertNotFound();
    showAbre($user, $fornecedor)->assertNotFound();
});

it('a ficha de lead não abre lead de outro negócio → 404 [T0]', function () {
    $user = showUsuario('lead_ficha_test');
    $vizinho = showUsuario('lead_ficha_vizinho_test', SHOW_OUTRO);
    $deFora = showContato(SHOW_OUTRO, 'Lead de fora', $vizinho, 'lead');

    showAbre($user, $deFora)->assertNotFound();
});
