<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-CIDX-27 — a lista de clientes (`GET /cliente`) não mostra nem conta cadastro de outra empresa.
 *
 * Fonte: US-CRM-080 (`memory/requisitos/Crm/SPEC.md`) e o [BACKLOG] de `Cliente/Index.casos.md`
 * que registrava a lacuna: `App\Contact` não tem global scope, então o isolamento da lista é o
 * `where('contacts.business_id')` escrito à mão em `ContactController::buildClienteIndexKpis`,
 * `buildClienteIndexTabCounts` e `buildClienteIndexCustomers` (ADR 0093).
 *
 * As três props são deferidas (`Inertia::defer`), então o teste pede o recarregamento parcial com
 * os headers que o @inertiajs/core manda de fato (§5 2026-09-08), igual ao BuscaClienteVariasPalavrasTest.
 *
 * KPIs e contadores são medidos por DIFERENÇA (antes × depois de gravar), porque o tenant de teste
 * já tem cadastros do seed: cadastro na outra empresa não pode mexer no número, e cadastro na
 * própria empresa tem de mexer (controle positivo — prova que a medição enxerga a gravação).
 *
 * Tenant de teste 98 × adversário 99 (ADR 0358). Nomes fictícios.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->outro = $this->seededSupportClientTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $this->userOutro = $this->usuarioComPermissoes(['customer.view'], $this->outro);
});

function cixEntrar($teste, $user, int $businessId): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $businessId, 'user.id' => $user->id, 'business.id' => $businessId]);
}

function cixCadastro(int $businessId, int $userId, string $nome): int
{
    $linha = [
        'business_id' => $businessId, 'type' => 'customer', 'name' => $nome,
        'created_by' => $userId, 'contact_status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ];
    if (Schema::hasColumn('contacts', 'is_customer')) {
        $linha['is_customer'] = 1;
    }
    if (Schema::hasColumn('contacts', 'vip')) {
        $linha['vip'] = 1;
    }

    return (int) DB::table('contacts')->insertGetId($linha);
}

/** Props deferidas da lista (kpis, tab_counts, customers) para o tipo e a busca pedidos. */
function cixLista($teste, string $tipo, string $busca = ''): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $query = ['type' => $tipo];
    if ($busca !== '') {
        $query['q'] = $busca;
    }
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index',
        'X-Inertia-Partial-Data' => 'kpis,tab_counts,customers',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/cliente?'.http_build_query($query));
    $r->assertOk();

    expect($r->json('props.kpis'))->toBeArray()
        ->and($r->json('props.tab_counts'))->toBeArray()
        ->and($r->json('props.customers.data'))->toBeArray();

    return [
        'kpis' => $r->json('props.kpis'),
        'tab_counts' => $r->json('props.tab_counts'),
        'ids' => collect($r->json('props.customers.data'))->pluck('id')->map(fn ($id) => (int) $id)->all(),
    ];
}

test('UC-CIDX-27 — Tier 0: cadastro de outra empresa não aparece na lista da empresa logada', function () {
    $marca = 'ZQX'.random_int(1000, 9999);
    $alheio = cixCadastro($this->outro->id, $this->userOutro->id, "{$marca}ALHEIO");
    $meu = cixCadastro($this->business->id, $this->user->id, "{$marca}MEU");

    cixEntrar($this, $this->user, $this->business->id);

    foreach (['customer', 'all'] as $tipo) {
        $ids = cixLista($this, $tipo, $marca)['ids'];
        // Controle positivo: a busca pela marca acha o cadastro da própria empresa.
        expect($ids)->toContain($meu)
            ->and($ids)->not->toContain($alheio);
    }
});

test('UC-CIDX-27 — Tier 0: KPIs e contadores das abas não contam cadastro de outra empresa', function () {
    cixEntrar($this, $this->user, $this->business->id);
    $antes = cixLista($this, 'customer');

    $marca = 'ZQX'.random_int(1000, 9999);
    cixCadastro($this->outro->id, $this->userOutro->id, "{$marca}ALHEIO1");
    cixCadastro($this->outro->id, $this->userOutro->id, "{$marca}ALHEIO2");

    $depoisAlheio = cixLista($this, 'customer');
    expect($depoisAlheio['kpis']['total'])->toBe($antes['kpis']['total'])
        ->and($depoisAlheio['kpis']['novos_mes'])->toBe($antes['kpis']['novos_mes'])
        ->and($depoisAlheio['kpis']['vips'])->toBe($antes['kpis']['vips'])
        ->and($depoisAlheio['tab_counts']['all'])->toBe($antes['tab_counts']['all'])
        ->and($depoisAlheio['tab_counts']['customer'])->toBe($antes['tab_counts']['customer']);

    // Controle positivo: um cadastro na própria empresa move os mesmos números em 1.
    cixCadastro($this->business->id, $this->user->id, "{$marca}MEU");
    $depoisMeu = cixLista($this, 'customer');
    expect($depoisMeu['kpis']['total'])->toBe($antes['kpis']['total'] + 1)
        ->and($depoisMeu['kpis']['novos_mes'])->toBe($antes['kpis']['novos_mes'] + 1)
        ->and($depoisMeu['tab_counts']['all'])->toBe($antes['tab_counts']['all'] + 1)
        ->and($depoisMeu['tab_counts']['customer'])->toBe($antes['tab_counts']['customer'] + 1);
    if (Schema::hasColumn('contacts', 'vip')) {
        expect($depoisMeu['kpis']['vips'])->toBe($antes['kpis']['vips'] + 1);
    }
});

test('UC-CIDX-27 — Tier 0: no sentido inverso, a outra empresa não vê nem conta o cadastro da empresa de teste', function () {
    cixEntrar($this, $this->userOutro, $this->outro->id);
    $antes = cixLista($this, 'customer');

    $marca = 'ZQX'.random_int(1000, 9999);
    $doTeste = cixCadastro($this->business->id, $this->user->id, "{$marca}DOTESTE");
    $proprio = cixCadastro($this->outro->id, $this->userOutro->id, "{$marca}PROPRIO");

    $depois = cixLista($this, 'customer', $marca);
    expect($depois['ids'])->toContain($proprio)
        ->and($depois['ids'])->not->toContain($doTeste);

    $contagem = cixLista($this, 'customer');
    // Só o cadastro da própria empresa (99) entra na conta.
    expect($contagem['kpis']['total'])->toBe($antes['kpis']['total'] + 1)
        ->and($contagem['tab_counts']['all'])->toBe($antes['tab_counts']['all'] + 1)
        ->and($contagem['tab_counts']['customer'])->toBe($antes['tab_counts']['customer'] + 1);
});
