<?php

declare(strict_types=1);
// @covers-us US-FIN-037
// Cobre UC-ADVL-01..04, UC-CTD-01..06 e UC-ADVD-01/03/04
// (resources/js/Pages/Financeiro/Advisor/* e Configuracoes/Contador .casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Advisor;
use Modules\Financeiro\Models\AdvisorBusinessAccess;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Portal do contador pelas ROTAS (auditoria Financeiro 2026-11-05, achado D9).
 *
 * Os testes antigos chamavam `Auth::guard()->attempt()` direto e anotavam os UCs em
 * asserções de schema: nenhum fazia POST em /advisor/login nem abria a tela Contador.
 *
 * Tenant 98 contra o 99 (ADR 0358), nunca biz=4 nem biz=1. Cada caso usa um IP próprio
 * para o limite de tentativas de um não contar nos outros.
 * ⚠️ Skip fora do MySQL: leia as assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do Financeiro (MySQL).');
    }
    if (! Schema::hasTable('advisors') || ! Schema::hasTable('advisor_business_access')) {
        $this->markTestSkipped('Tabelas do portal do contador ausentes.');
    }

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->withServerVariables(['REMOTE_ADDR' => '10.20.'.random_int(1, 250).'.'.random_int(1, 250)]);
});

function advCnpj(): string
{
    return (string) random_int(10000000000000, 99999999999999);
}

function advCriar(string $senha = 'senha-correta-1', bool $ativo = true): Advisor
{
    return Advisor::create([
        'cnpj_contador' => advCnpj(),
        'nome'          => 'Contador Teste',
        'email'         => 'contador.'.uniqid().'@teste.local',
        'password_hash' => Hash::make($senha),
        'referral_code' => Advisor::generateReferralCode(),
        'ativo'         => $ativo,
    ]);
}

function advGrant(int $advisorId, int $biz, int $userId): AdvisorBusinessAccess
{
    return AdvisorBusinessAccess::create([
        'advisor_id' => $advisorId, 'business_id' => $biz, 'granted_at' => now(), 'granted_by' => $userId,
        'scope_json' => ['can_view_unificado' => true, 'can_view_reports' => true, 'consented_at' => now()->toIso8601String()],
    ]);
}

function advEntrarNaEmpresa($user, int $biz): void
{
    session(['user.business_id' => $biz, 'user.id' => $user->id, 'business.id' => $biz,
        'business' => ['id' => $biz, 'name' => 'Teste', 'currency_symbol' => 'R$']]);
    test()->actingAs($user);
}

function advLogin(string $email, string $senha, array $extra = [])
{
    return test()->from('/advisor/login')->post('/advisor/login', ['email' => $email, 'password' => $senha] + $extra);
}

it('UC-ADVL-01 · POST válido autentica só no guard do contador e cai em /advisor', function () {
    $advisor = advCriar();

    advLogin($advisor->email, 'senha-correta-1')->assertRedirect('/advisor');

    expect(Auth::guard('web-advisor')->id())->toBe($advisor->id)
        ->and(Auth::guard('web')->user())->toBeNull();
});

it('UC-ADVL-02 · senha errada, e-mail inexistente e conta inativa recebem o mesmo erro', function () {
    $advisor = advCriar();
    $inativo = advCriar(ativo: false);

    $mensagens = collect([
        [$advisor->email, 'senha-errada-1'],
        ['ninguem.'.uniqid().'@teste.local', 'senha-errada-1'],
        [$inativo->email, 'senha-correta-1'],
    ])->map(function (array $par) {
        $resposta = advLogin($par[0], $par[1]);
        $resposta->assertRedirect('/advisor/login');

        return session('error');
    });

    expect($mensagens->unique()->count())->toBe(1)
        ->and($mensagens->first())->toBe('Email ou senha incorretos.')
        ->and(Auth::guard('web-advisor')->check())->toBeFalse();
});

it('UC-ADVL-03 · o cookie de lembrar só nasce quando o contador marca a opção', function () {
    $temCookie = fn ($resposta) => collect($resposta->headers->getCookies())
        ->contains(fn ($c) => str_starts_with($c->getName(), 'remember_web-advisor'));

    $marcado = advCriar();
    $comOpcao = advLogin($marcado->email, 'senha-correta-1', ['remember' => '1']);
    Auth::guard('web-advisor')->logout();

    $desmarcado = advCriar();
    $semOpcao = advLogin($desmarcado->email, 'senha-correta-1');

    expect($temCookie($comOpcao))->toBeTrue()
        ->and($temCookie($semOpcao))->toBeFalse();
});

it('UC-ADVL-04 · depois de 5 erros a conta trava, mesmo com a senha certa', function () {
    $advisor = advCriar();

    foreach (range(1, 5) as $_) {
        advLogin($advisor->email, 'senha-errada-1')->assertRedirect('/advisor/login');
    }
    expect(session('error'))->toBe('Email ou senha incorretos.');

    advLogin($advisor->email, 'senha-correta-1')->assertRedirect('/advisor/login');

    expect(session('error'))->toStartWith('Muitas tentativas')
        ->and(Auth::guard('web-advisor')->check())->toBeFalse();
});

it('UC-ADVL-04 · a trava de uma conta não pega outra, e o acerto zera a contagem', function () {
    $travada = advCriar();
    foreach (range(1, 5) as $_) {
        advLogin($travada->email, 'senha-errada-1');
    }

    $outra = advCriar();
    foreach (range(1, 4) as $_) {
        advLogin($outra->email, 'senha-errada-1');
    }
    advLogin($outra->email, 'senha-correta-1')->assertRedirect('/advisor');
    Auth::guard('web-advisor')->logout();
    session()->forget('url.intended');

    foreach (range(1, 4) as $_) {
        advLogin($outra->email, 'senha-errada-1');
    }
    advLogin($outra->email, 'senha-correta-1')->assertRedirect('/advisor');
});

it('UC-ADVL-04 · 20 erros do mesmo IP em contas diferentes travam o IP', function () {
    foreach (range(1, 20) as $_) {
        advLogin('varredura.'.uniqid().'@teste.local', 'senha-errada-1');
    }
    $advisor = advCriar();

    advLogin($advisor->email, 'senha-correta-1')->assertRedirect('/advisor/login');

    expect(session('error'))->toStartWith('Muitas tentativas');
});

it('UC-CTD-01 · conceder cria o acesso na empresa da sessão, com consentimento gravado', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    advEntrarNaEmpresa($user, $this->b98);
    $cnpj = advCnpj();

    $this->post('/financeiro/configuracoes/contador/grant', [
        'cnpj_contador' => $cnpj, 'nome' => 'Contador Novo', 'email' => 'novo.'.uniqid().'@teste.local',
        'consent_lgpd' => '1', 'can_view_reports' => false,
    ])->assertSessionHas('success');

    $acesso = AdvisorBusinessAccess::where('advisor_id', Advisor::where('cnpj_contador', $cnpj)->value('id'))->sole();
    expect((int) $acesso->business_id)->toBe($this->b98)
        ->and($acesso->hasConsent())->toBeTrue()
        ->and($acesso->canViewUnificado())->toBeTrue()
        ->and($acesso->canViewReports())->toBeFalse();
});

it('UC-CTD-02 · sem o consentimento marcado, nada é criado', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    advEntrarNaEmpresa($user, $this->b98);
    $cnpj = advCnpj();

    $this->post('/financeiro/configuracoes/contador/grant', [
        'cnpj_contador' => $cnpj, 'nome' => 'Sem Consentimento', 'email' => 'sem.'.uniqid().'@teste.local',
    ])->assertSessionHasErrors('consent_lgpd');

    expect(Advisor::where('cnpj_contador', $cnpj)->exists())->toBeFalse();
});

it('UC-CTD-03 · revogar grava a data e o acesso de outra empresa devolve 404', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    $advisor = advCriar();
    $meu = advGrant($advisor->id, $this->b98, $user->id);
    $alheio = advGrant($advisor->id, $this->b99, $user->id);
    advEntrarNaEmpresa($user, $this->b98);

    $this->delete("/financeiro/configuracoes/contador/{$alheio->id}")->assertNotFound();
    expect($alheio->fresh()->revoked_at)->toBeNull();

    $this->delete("/financeiro/configuracoes/contador/{$meu->id}")->assertSessionHas('success');
    $revogado = AdvisorBusinessAccess::withTrashed()->find($meu->id);
    expect($revogado->revoked_at)->not->toBeNull()
        ->and((int) $revogado->revoked_by)->toBe((int) $user->id)
        ->and($revogado->trashed())->toBeTrue();
});

it('UC-CTD-04 · o contador logado não escreve no Financeiro, mesmo com grant e advisor_view', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    $advisor = advCriar();
    advGrant($advisor->id, $this->b98, $user->id);

    // Login real: em produção o contador só tem a sessão do guard `web-advisor`, e o `auth`
    // das rotas do Financeiro usa o guard padrão (`web`). `actingAs` trocaria o padrão.
    advLogin($advisor->email, 'senha-correta-1')->assertRedirect('/advisor');

    $resposta = $this->post("/financeiro/unificado/1/baixar?advisor_view=1&business_id={$this->b98}", []);

    expect($resposta->status())->toBe(302)
        ->and($resposta->headers->get('Location'))->toContain('login');
});

it('UC-CTD-05 · a tela Contador mostra o CNPJ mascarado e nunca inteiro', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    $advisor = advCriar();
    advGrant($advisor->id, $this->b98, $user->id);
    advEntrarNaEmpresa($user, $this->b98);

    $resposta = $this->get('/financeiro/configuracoes/contador')->assertOk();

    $resposta->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Configuracoes/Contador')
        ->where('accesses.0.advisor_cnpj_mascarado', $advisor->cnpj_masked));
    expect($resposta->getContent())->not->toContain($advisor->cnpj_contador);
});

it('UC-CTD-06 · sem a permissão, tela, concessão e revogação devolvem 403', function () {
    $dono = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    $acesso = advGrant(advCriar()->id, $this->b98, $dono->id);
    $user = $this->usuarioComPermissoes(['financeiro.access']);
    advEntrarNaEmpresa($user, $this->b98);

    $this->get('/financeiro/configuracoes/contador')->assertForbidden();
    $this->post('/financeiro/configuracoes/contador/grant', [])->assertForbidden();
    $this->delete("/financeiro/configuracoes/contador/{$acesso->id}")->assertForbidden();

    expect($acesso->fresh()->revoked_at)->toBeNull();
});

it('UC-ADVD-01 · o painel lista só as empresas com acesso ativo', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    $advisor = advCriar();
    advGrant($advisor->id, $this->b98, $user->id);

    $this->actingAs($advisor, 'web-advisor')->get('/advisor')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Financeiro/Advisor/Dashboard')
            ->where('total_clientes', 1)
            ->where('clientes.0.business_id', $this->b98));
});

it('UC-ADVD-04 · acesso revogado sai do painel na hora', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    $advisor = advCriar();
    $acesso = advGrant($advisor->id, $this->b98, $user->id);
    advGrant($advisor->id, $this->b99, $user->id);
    $acesso->update(['revoked_at' => now(), 'revoked_by' => $user->id]);
    $acesso->delete();

    $this->actingAs($advisor, 'web-advisor')->get('/advisor')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('total_clientes', 1)
            ->where('clientes.0.business_id', $this->b99));
});

it('UC-ADVD-03 · o link do cliente hoje manda o contador para o login, sem mostrar dado', function () {
    $user = $this->usuarioComPermissoes(['financeiro.advisor.grant']);
    $advisor = advCriar();
    advGrant($advisor->id, $this->b98, $user->id);

    advLogin($advisor->email, 'senha-correta-1')->assertRedirect('/advisor');

    $resposta = $this->get("/financeiro/unificado?advisor_view=1&business_id={$this->b98}");

    expect($resposta->status())->toBe(302)
        ->and($resposta->headers->get('Location'))->toContain('login');
});
