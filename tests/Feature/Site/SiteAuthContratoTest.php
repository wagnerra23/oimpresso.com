<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de `/login` e `/register`. UCs de resources/js/Pages/Site/{Login,Register}.casos.md
 * (charters + login-spec-2026-04-26 + US-CMS-008). Nenhuma credencial real: usuários fictícios no
 * tenant 98, e-mails `@test.local`, senhas descartáveis. Escrita em transação revertida
 * (§5 2026-09-18). SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Requer o schema MySQL de users.');
    }
    config(['constants.enable_recaptcha' => false]);
    DB::beginTransaction();
});

afterEach(fn () => DB::rollBack());
const SITE_FIXTURE_SENHA = 'descartavel-123'; // fixture descartável, não é credencial de ninguém

function sauthHeaders(): array
{
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    return ['X-Inertia' => 'true', 'X-Inertia-Version' => $versao, 'X-Requested-With' => 'XMLHttpRequest'];
}

function sauthUsuario(string $username): User
{
    Business::firstOrCreate(['id' => 98], ['name' => 'Tenant fictício site auth', 'currency_id' => 1]);

    return User::create([
        'username' => $username, 'email' => $username.'@test.local', 'password' => bcrypt(SITE_FIXTURE_SENHA),
        'business_id' => 98, 'first_name' => 'Sauth', 'last_name' => 'Teste',
    ]);
}

it('UC-SLOGIN-01 · /login abre sem sessão, na tela Site/Login', function () {
    $this->assertGuest();
    $r = $this->withHeaders(sauthHeaders())->get('/login');

    $r->assertOk();
    expect($r->json('component'))->toBe('Site/Login');
});

it('UC-SLOGIN-02 · botões sociais e "Criar conta" seguem a configuração do servidor', function () {
    config(['services.google.client_id' => 'cliente-ficticio-google', 'services.microsoft.client_id' => null, 'constants.allow_registration' => false]);

    $r = $this->withHeaders(sauthHeaders())->get('/login');
    $r->assertOk();
    expect($r->json('props.socialEnabled.google'))->toBeTrue();
    expect($r->json('props.socialEnabled.microsoft'))->toBeFalse();
    expect($r->json('props.allowRegistration'))->toBeFalse();
});

it('UC-SLOGIN-03 · quem já está logado não vê a tela de login', function () {
    $r = $this->actingAs(sauthUsuario('sauth_logado_'.uniqid()))->withHeaders(sauthHeaders())->get('/login');

    $r->assertRedirect();
    expect($r->headers->get('X-Inertia'))->toBeNull();
});

it('UC-SLOGIN-04 · credencial errada não abre sessão e volta com erro de campo', function () {
    $r = $this->from('/login')->post('/login', ['username' => 'sauth_inexistente_'.uniqid(), 'password' => SITE_FIXTURE_SENHA.'-errada']);

    $r->assertRedirect('/login');
    $r->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('UC-SREG-01 · /register abre sem sessão, na tela Site/Register', function () {
    $this->assertGuest();
    $r = $this->withHeaders(sauthHeaders())->get('/register');

    $r->assertOk();
    expect($r->json('component'))->toBe('Site/Register');
});

it('UC-SREG-02 · cadastro fechado no servidor chega fechado na tela', function () {
    config(['constants.allow_registration' => false]);
    expect($this->withHeaders(sauthHeaders())->get('/register')->json('props.allowRegistration'))->toBeFalse();

    config(['constants.allow_registration' => true]);
    expect($this->withHeaders(sauthHeaders())->get('/register')->json('props.allowRegistration'))->toBeTrue();
});

it('UC-SREG-03 · senha curta ou sem confirmação é recusada e nenhum usuário nasce', function () {
    foreach ([['abc1234', 'abc1234'], [SITE_FIXTURE_SENHA, SITE_FIXTURE_SENHA.'-outra']] as $i => [$senha, $confirma]) {
        $email = "sreg_senha_{$i}_".uniqid().'@test.local';
        $this->from('/register')->post('/register', [
            'name' => 'Sreg Senha', 'email' => $email, 'password' => $senha, 'password_confirmation' => $confirma,
        ])->assertSessionHasErrors('password');
        expect(User::where('email', $email)->exists())->toBeFalse();
    }
    $this->assertGuest();
});

it('UC-SREG-04 · e-mail já usado é recusado no servidor', function () {
    $existente = sauthUsuario('sreg_dono_'.uniqid());

    $this->from('/register')->post('/register', [
        'name' => 'Sreg Repetido', 'email' => $existente->email,
        'password' => SITE_FIXTURE_SENHA, 'password_confirmation' => SITE_FIXTURE_SENHA,
    ])->assertSessionHasErrors('email');

    expect(User::where('email', $existente->email)->count())->toBe(1);
    $this->assertGuest();
});
