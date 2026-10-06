<?php

declare(strict_types=1);

use App\Services\Support\SupportAuditService;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// Tests\TestCase já é aplicado globalmente em tests/Pest.php. NÃO redeclarar (Pest 4 lança TestCaseAlreadyInUse).

/**
 * Modo Suporte — tela Suporte/Log (leitura da trilha append-only support_access_logs, RF3).
 *
 * Cobre os UCs de resources/js/Pages/Suporte/Log.casos.md (G-2: cada UC citado aqui).
 *
 * Tenants: a OPERADORA aqui é o tenant de teste canônico (seededTenant, biz=98) — o config
 * `operator_business_id` é apontado pra ele, e pela ADR 0309 todo usuário da operadora já é
 * agente. Cliente = biz=99 (seededSupportClientTenant). NUNCA biz=4.
 *
 * `logs` é prop DEFERIDA (Inertia::defer): só vem num partial reload por HEADER
 * (X-Inertia-Partial-Component + X-Inertia-Partial-Data), nunca por query string.
 *
 * As linhas gravadas aqui são append-only (o Model barra delete) — cada caso marca a sua com
 * uma rota única e procura por ID, então rodar de novo num banco persistente (CT 100) não
 * confunde um run com outro.
 *
 * @see app/Http/Controllers/Support/SupportController.php (log)
 * @see resources/js/Pages/Suporte/Log.casos.md
 */

function suplogSchemaPronto(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return false;
    }

    return Schema::hasTable('users') && Schema::hasTable('business')
        && Schema::hasTable('support_access_logs') && Schema::hasTable('support_agents');
}

function suplogInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** GET /suporte/log com partial reload da prop deferida `logs`; devolve as linhas. */
function suplogLinhas($teste): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => suplogInertiaVersion(),
        'X-Inertia-Partial-Component' => 'Suporte/Log',
        'X-Inertia-Partial-Data' => 'logs',
    ])->get('/suporte/log');

    $r->assertOk();
    expect($r->json('component'))->toBe('Suporte/Log');

    $linhas = $r->json('props.logs.data');
    expect($linhas)->toBeArray();

    return $linhas;
}

/** IDs presentes na primeira página. */
function suplogIds(array $linhas): array
{
    return array_map(fn (array $l): int => (int) $l['id'], $linhas);
}

beforeEach(function () {
    if (! suplogSchemaPronto()) {
        test()->markTestSkipped('Schema MySQL UltimatePOS ausente (ADR 0101).');
    }

    $this->operadora = $this->seededTenant();
    $this->cliente = $this->seededSupportClientTenant();

    config(['constants.operator_business_id' => (int) $this->operadora->id]);
    config(['constants.administrator_usernames' => 'um_admin_que_nao_e_o_agente']);

    // Agente = usuário da operadora (ADR 0309: o time da operadora É o suporte).
    $this->agente = User::factory()->create(['business_id' => $this->operadora->id]);
});

it('UC-SUP-08 · agente lê a trilha da empresa-cliente', function () {
    $marca = 'suplog-08-'.uniqid();
    $linha = app(SupportAuditService::class)->recordAccess($this->agente, (int) $this->cliente->id, $marca);

    $this->actingAs($this->agente);
    $linhas = suplogLinhas($this);

    $achada = collect($linhas)->firstWhere('id', (int) $linha->id);
    expect($achada)->not->toBeNull();
    expect($achada['empresa_id'])->toBe((int) $this->cliente->id);
    expect($achada['acao'])->toBe('entrou');
    expect($achada['agente'])->toBe((string) $this->agente->username);
    expect($achada['alvo'])->toBeNull();
});

it('UC-SUP-09 · negação contra a operadora fica fora do log', function () {
    $audit = app(SupportAuditService::class);
    // Controle POSITIVO: sem ele, um filtro largo demais ("não mostra nada") passaria no negativo.
    $doCliente = $audit->recordAccess($this->agente, (int) $this->cliente->id, 'suplog-09c-'.uniqid());
    $daOperadora = $audit->recordDenied($this->agente, (int) $this->operadora->id, 'suplog-09o-'.uniqid());

    $this->actingAs($this->agente);
    $ids = suplogIds(suplogLinhas($this));

    expect($ids)->toContain((int) $doCliente->id);
    expect($ids)->not->toContain((int) $daOperadora->id);
});

it('UC-SUP-10 · não-agente recebe 403 no log', function () {
    // Usuário de CLIENTE sem concessão em support_agents — não pode ser da operadora
    // (pela ADR 0309 todo usuário dela já é agente, e o teste mentiria).
    $naoAgente = User::factory()->create(['business_id' => $this->cliente->id]);

    $this->actingAs($naoAgente)->get('/suporte/log')->assertStatus(403);
});

it('rota suporte.log é só GET e roda support.access + AdminSidebarMenu', function () {
    $route = Route::getRoutes()->getByName('suporte.log');
    expect($route)->not->toBeNull();
    expect($route->methods())->toEqualCanonicalizing(['GET', 'HEAD']);

    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('support.access');
    expect($middleware)->toContain('AdminSidebarMenu');
});
