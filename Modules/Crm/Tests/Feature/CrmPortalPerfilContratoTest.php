<?php

declare(strict_types=1);
// Cobre UC-CRMPO-04, UC-CRMPO-05 (Crm/Portal/Perfil/Index.casos.md).

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do Perfil do portal do contato (`/contact/contact-profile`) em Inertia — thread Crm/19,
 * MWART F3. O GET novo fica atrás da flag useV2CrmPortalPerfil (ligada aqui por `feature-flags.forced_on`,
 * inerte em produção). Os POST de hoje reconhecem a visita Inertia e só nela gravam o cadastro do contato.
 *
 * É ESCRITA feita por um terceiro com login. Por isso todo caso semeia o contato A, o contato B do
 * mesmo negócio e o A' do negócio 99, e lê as linhas antes e depois.
 * Tenant 98 (seededTenant, ADR 0358) e 99 (seededSupportClientTenant). NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ManageProfileController
 */
const PERFC_FLAG = 'useV2CrmPortalPerfil';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: contacts/users requerem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasColumn('users', 'crm_contact_id') || ! Schema::hasColumn('contacts', 'contato')) {
        $this->markTestSkipped('Colunas users.crm_contact_id / contacts.contato ausentes — migrations não rodaram.');
    }

    $this->business = $this->seededTenant();
    config(['feature-flags.forced_on' => PERFC_FLAG]);

    // O gate de assinatura (`crm_module`) não é o que este contrato prova (ver CrmPortalPainelContratoTest).
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $fake);
});

function perfcContato(int $biz, string $marca): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Razao '.$marca.' '.uniqid(), 'tax_number' => 'DOC-'.$marca,
        'mobile' => '1100000000', 'email' => $marca.'@antes.test', 'contato' => 'Pessoa '.$marca, 'address_line_1' => 'Rua '.$marca,
        'created_by' => (int) DB::table('users')->min('id'), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function perfcLogin(int $biz, int $contato, string $senha = 'senha-antiga'): User
{
    $id = DB::table('users')->insertGetId([
        'username' => 'perfc19_'.uniqid(), 'email' => 'perfc19_'.uniqid().'@test.local', 'password' => Hash::make($senha),
        'business_id' => $biz, 'crm_contact_id' => $contato, 'first_name' => 'Contato', 'last_name' => 'Teste',
        'user_type' => 'user_customer', 'allow_login' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::findOrFail($id);
}

/** POST como a tela React faz (visita Inertia). */
function perfcPost($teste, User $user, string $rota, array $corpo)
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return $teste->actingAs($user)->from('/contact/contact-profile')->post($rota, $corpo, [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
    ]);
}

function perfcLinha(int $id): array
{
    return (array) DB::table('contacts')->where('id', $id)
        ->first(['id', 'business_id', 'contact_id', 'type', 'name', 'tax_number', 'mobile', 'email', 'contato', 'address_line_1']);
}

it('UC-CRMPO-04 · meu cadastro fiscal não muda pelo portal [T0]', function () {
    $biz = $this->business->id;
    $a = perfcContato($biz, 'a');
    $b = perfcContato($biz, 'b');
    $outro = $this->seededSupportClientTenant();
    $a2 = perfcContato($outro->id, 'a2');
    $user = perfcLogin($biz, $a);

    // A tela abre com os dados do contato A (e só dele).
    $this->actingAs($user)->get('/contact/contact-profile')->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Portal/Perfil/Index', false)
            ->where('contato.nome', perfcLinha($a)['name'])
            ->where('contato.email', 'a@antes.test')
            ->has('shell.portal.menu')
            ->missing('shell.menu'));

    $antesA = perfcLinha($a);
    $antesB = perfcLinha($b);
    $antesA2 = perfcLinha($a2);

    perfcPost($this, $user, '/contact/contact-profile-update', [
        'name' => 'RAZAO TROCADA', 'tax_number' => '00000000000191', 'contact_id' => 'CLI-HACK', 'business_id' => $outro->id, 'type' => 'supplier',
        'id' => $b,
        'email' => 'a@depois.test', 'mobile' => '1199999999', 'contato' => 'Pessoa nova', 'address_line_1' => 'Rua nova, 10',
    ])->assertRedirect();

    $depoisA = perfcLinha($a);
    foreach (['name', 'tax_number', 'contact_id', 'business_id', 'type'] as $k) {
        $this->assertSame($antesA[$k], $depoisA[$k], "o portal alterou o campo protegido `{$k}` do contato");
    }
    expect($depoisA['email'])->toBe('a@depois.test');
    expect($depoisA['mobile'])->toBe('1199999999');
    expect($depoisA['contato'])->toBe('Pessoa nova');
    expect($depoisA['address_line_1'])->toBe('Rua nova, 10');

    $this->assertEquals($antesB, perfcLinha($b), 'o contato B do mesmo negócio mudou');
    $this->assertEquals($antesA2, perfcLinha($a2), 'o contato do negócio 99 mudou');
});

it('UC-CRMPO-05 · troco a minha senha, e só a minha', function () {
    $biz = $this->business->id;
    $a = perfcLogin($biz, perfcContato($biz, 'sa'), 'senha-a');
    $b = perfcLogin($biz, perfcContato($biz, 'sb'), 'senha-b');
    $hashB = (string) DB::table('users')->where('id', $b->id)->value('password');
    $hashA = (string) DB::table('users')->where('id', $a->id)->value('password');

    // Atual errada: erro no campo, nada muda.
    perfcPost($this, $a, '/contact/contact-password-update', [
        'current_password' => 'errada', 'new_password' => 'nova-1', 'confirm_password' => 'nova-1',
    ])->assertSessionHasErrors('current_password');
    $this->assertSame($hashA, (string) DB::table('users')->where('id', $a->id)->value('password'), 'senha mudou com a atual errada');

    // Atual certa + user_id de B no corpo: só a de A muda.
    perfcPost($this, $a, '/contact/contact-password-update', [
        'current_password' => 'senha-a', 'new_password' => 'nova-1', 'confirm_password' => 'nova-1', 'user_id' => $b->id,
    ])->assertSessionHasNoErrors();

    $this->assertTrue(Hash::check('nova-1', (string) DB::table('users')->where('id', $a->id)->value('password')), 'a senha de A não mudou');
    $this->assertSame($hashB, (string) DB::table('users')->where('id', $b->id)->value('password'), 'a senha de B mudou pelo portal de A');
});
