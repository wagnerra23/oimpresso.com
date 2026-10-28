<?php

declare(strict_types=1);
// @covers-us US-INFRA-002
// Cobre UC-VOZ-01, UC-VOZ-02, UC-VOZ-03, UC-VOZ-04, UC-VOZ-07, UC-VOZ-12, UC-VOZ-13 (VozDoCliente/Caixa.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread modulos-faltantes/playbook/03 — /voz-do-cliente em Inertia. A flag useV2VozDoClienteCaixa
 * valeu true para todas as empresas de 2026-10-13 a 2026-10-27; no F5 (2026-10-27) a flag, o ?legacy=1
 * e a Blade saíram, e a rota serve só a Page.
 *
 * Mora em tests/Feature e roda na lane acessos-pest.yml (MySQL): o teste autentica, monta
 * papel Spatie e bate na rota HTTP com sessão de negócio — na lista sqlite pularia. O teste
 * do módulo (SinalCrossTenantTest) segue na lista sqlite, e prova o global scope; este prova
 * a ROTA. Tenant de teste 98 × cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }
    // Sem a tabela do módulo a lane NÃO mediu — falha alto em vez de pular (LC-13).
    expect(Schema::hasTable('voz_sinais'))->toBeTrue();

    $this->business = $this->seededTenant();
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
});

function vozEntrar($teste, array $permissoes): void
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
}

function vozSinal(int $biz, string $texto, string $status, int $horasAtras, ?string $autor = 'Fulano', ?int $sev = null): void
{
    DB::table('voz_sinais')->insert([
        'business_id' => $biz, 'autor_nome' => $autor, 'canal' => 'sistema', 'texto' => $texto,
        'severidade' => $sev, 'url_vista' => '/sells/create', 'status' => $status,
        'hash_origem' => hash('sha256', $biz . '|' . $texto . '|' . uniqid()),
        'created_at' => now()->subHours($horasAtras),
    ]);
}

function vozAbrir($teste, string $query = '', array $extra = [])
{
    // X-Requested-With junto do X-Inertia, como o browser manda.
    return $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ] + $extra)->get('/voz-do-cliente' . $query);
}

function vozLista($teste, string $query = '')
{
    return vozAbrir($teste, $query, [
        'X-Inertia-Partial-Component' => 'VozDoCliente/Caixa', 'X-Inertia-Partial-Data' => 'sinais,filtro',
    ]);
}

function vozCenario(int $biz): void
{
    vozSinal($biz, 'pendente velho', 'pending', 30);
    vozSinal($biz, 'pendente novo', 'pending', 2, null, 4);
    vozSinal($biz, 'pendente meio', 'pending', 10);
    vozSinal($biz, 'triado A', 'triaged', 5);
    vozSinal($biz, 'triado B', 'triaged', 1);
    vozSinal($biz, 'fechado', 'closed', 0);
}

test('UC-VOZ-01 a caixa renderiza Inertia VozDoCliente/Caixa com contagem e lista adiada', function () {
    vozEntrar($this, ['vozdocliente.triar']);
    vozCenario($this->business->id);

    $r = vozAbrir($this)->assertOk();

    expect($r->json('component'))->toBe('VozDoCliente/Caixa');
    expect($r->json('props.contagem'))->toMatchArray(['total' => 6, 'pending' => 3, 'triaged' => 2, 'closed' => 1, 'alta' => 1]);
    expect($r->json('deferredProps.default'))->toContain('sinais');
    expect($r->json('props'))->not->toHaveKey('sinais');
});

test('UC-VOZ-02 a Blade saiu: ?legacy=1 também serve a Page', function () {
    vozEntrar($this, ['vozdocliente.triar']);

    expect(view()->exists('vozdocliente::caixa'))->toBeFalse();

    $r = vozAbrir($this, '?legacy=1')->assertOk();
    expect($r->json('component'))->toBe('VozDoCliente/Caixa');
});

test('UC-VOZ-03 ?situacao=pending traz só pendentes e situação desconhecida volta todos', function () {
    vozEntrar($this, ['vozdocliente.triar']);
    vozCenario($this->business->id);

    $r = vozLista($this, '?situacao=pending')->assertOk();
    expect($r->json('props.filtro'))->toBe('pending');
    expect(collect($r->json('props.sinais.data'))->pluck('status')->unique()->values()->all())->toBe(['pending']);
    expect($r->json('props.sinais.data'))->toHaveCount(3);

    $todos = vozLista($this, '?situacao=qualquer')->assertOk();
    expect($todos->json('props.filtro'))->toBeNull();
    expect($todos->json('props.sinais.data'))->toHaveCount(6);
});

test('UC-VOZ-04 pendente antes de triado antes de fechado, mais novo primeiro dentro da situação', function () {
    vozEntrar($this, ['vozdocliente.triar']);
    vozCenario($this->business->id);

    $textos = collect(vozLista($this)->assertOk()->json('props.sinais.data'))->pluck('texto')->all();

    expect($textos)->toBe(['pendente novo', 'pendente meio', 'pendente velho', 'triado B', 'triado A', 'fechado']);
});

test('UC-VOZ-07 relato anônimo chega com autor nulo', function () {
    vozEntrar($this, ['vozdocliente.triar']);
    vozSinal($this->business->id, 'sem autor', 'pending', 1, null);

    $item = vozLista($this)->assertOk()->json('props.sinais.data.0');

    expect($item['texto'])->toBe('sem autor');
    expect($item['autor'])->toBeNull();
});

test('UC-VOZ-12 sem vozdocliente.triar dá 403', function () {
    vozEntrar($this, ['vozdocliente.reportar']);
    vozSinal($this->business->id, 'texto que não pode vazar', 'pending', 1);

    $r = vozAbrir($this)->assertForbidden();

    expect($r->getContent())->not->toContain('texto que não pode vazar');
});

test('UC-VOZ-13 contagem e lista trazem só o negócio da sessão', function () {
    vozEntrar($this, ['vozdocliente.triar']);
    $outro = $this->seededSupportClientTenant();
    vozSinal($this->business->id, 'relato meu', 'pending', 1);
    vozSinal($outro->id, 'relato alheio', 'pending', 1);

    $pagina = vozAbrir($this)->assertOk();
    expect($pagina->json('props.contagem.total'))->toBe(1);

    $lista = vozLista($this)->assertOk();
    expect(collect($lista->json('props.sinais.data'))->pluck('texto')->all())->toBe(['relato meu']);
    expect($lista->getContent())->not->toContain('relato alheio');
});
