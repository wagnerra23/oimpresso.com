<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

// @covers-us US-ADS-003 — Portfólio de Projects: lista + KPIs + criação.

/**
 * Contrato da tela /ads/admin/projects — `Admin\ProjectsController@index|store`.
 *
 * UC-ADPJ-01 `[T0]` — a lista traz os projects do meu business e não traz os de outro.
 * UC-ADPJ-02        — criar com nome + objetivo grava um project `draft` no meu business,
 *                     leva pro detalhe e NÃO dispara a decomposição.
 * UC-ADPJ-03        — sem nome ou sem objetivo, nada é gravado.
 * UC-ADPJ-04        — os KPIs (total, ativos, draft, concluídos) contam só o meu business.
 *
 * Os UC derivam do `Projects.charter.md` (Goals, Non-Goals, Anti-hooks) e da US-ADS-003
 * (memory/requisitos/ADS/SPEC.md) — nunca do `.tsx`. Os asserts de contagem comparam o
 * payload com o banco, não com um número fixo: o CT 100 é clone de prod e já tem linhas.
 * Trio: Modules/Forja/Resources/js/Pages/ads/Admin/{Projects.charter.md,Projects.casos.md}
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 (fictício) × 99 (adversário). NUNCA biz=4.
 * DatabaseTransactions: nada persiste. ⛔ Pest só no CT 100 ou no CI (proibicoes §Ambiente).
 * Skip sai exit 0: leia as ASSERTIONS, não "0 failed" (LC-13).
 */
const ADPJ_ROTA = '/ads/admin/projects';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['mcp_projects', 'mcp_project_parts'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente — schema baseline não aplicado.");
        }
    }

    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
    $this->actingAs($this->usuarioComPermissoes([], $this->tenant));
});

function adpjProject(int $bizId, string $nome, string $status = 'draft'): int
{
    return DB::table('mcp_projects')->insertGetId([
        'business_id'      => $bizId,
        'codigo'           => 'TADPJ-'.strtoupper(bin2hex(random_bytes(4))),
        'nome'             => $nome,
        'objetivo_macro'   => "Objetivo de {$nome}",
        'metricas_sucesso' => json_encode([]),
        'constraints'      => json_encode([]),
        'status'           => $status,
        'decision'         => 'pending',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

function adpjVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Props da lista, com os headers que o navegador manda numa visita Inertia. */
function adpjProps($test): array
{
    $resposta = $test->withHeaders([
        'X-Requested-With'  => 'XMLHttpRequest',
        'Accept'            => 'text/html, application/xhtml+xml',
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => adpjVersao(),
    ])->get(ADPJ_ROTA);

    $resposta->assertStatus(200);
    $resposta->assertJsonPath('component', 'ads/Admin/Projects');

    return $resposta->json('props') ?? [];
}

it('UC-ADPJ-01 · a lista traz os projects do meu business e não traz os de outro', function () {
    $tag = 'ADPJ01-'.uniqid();
    adpjProject($this->tenant->id, "{$tag}-meu");
    adpjProject($this->adversario->id, "{$tag}-alheio");

    $nomes = array_column(adpjProps($this)['projects'] ?? [], 'nome');

    expect($nomes)->toContain("{$tag}-meu");        // controle positivo
    expect($nomes)->not->toContain("{$tag}-alheio"); // Tier 0
});

it('UC-ADPJ-02 · criar com nome e objetivo grava um project draft no meu business, leva pro detalhe e não decompõe', function () {
    $nome = 'ADPJ02-'.uniqid();

    $resposta = $this->post(ADPJ_ROTA, [
        'nome'           => $nome,
        'objetivo_macro' => 'Objetivo macro do teste de contrato',
    ]);

    $criado = DB::table('mcp_projects')->where('nome', $nome)->first();
    expect($criado)->not->toBeNull();
    expect((int) $criado->business_id)->toBe((int) $this->tenant->id);
    expect($criado->status)->toBe('draft');

    $resposta->assertRedirect(ADPJ_ROTA.'/'.$criado->id);

    // Anti-hook do charter: criar NÃO dispara a decomposição por IA (ação separada, cara).
    expect(DB::table('mcp_project_parts')->where('project_id', $criado->id)->count())->toBe(0);
});

it('UC-ADPJ-03 · sem nome ou sem objetivo o project não é gravado', function () {
    $antes = DB::table('mcp_projects')->where('business_id', $this->tenant->id)->count();

    $semNome = $this->post(ADPJ_ROTA, ['objetivo_macro' => 'só objetivo']);
    $semNome->assertSessionHasErrors('nome');

    $semObjetivo = $this->post(ADPJ_ROTA, ['nome' => 'ADPJ03-'.uniqid()]);
    $semObjetivo->assertSessionHasErrors('objetivo_macro');

    expect(DB::table('mcp_projects')->where('business_id', $this->tenant->id)->count())->toBe($antes);
});

it('UC-ADPJ-04 · os KPIs contam só os projects do meu business, por status', function () {
    $tag = 'ADPJ04-'.uniqid();
    adpjProject($this->tenant->id, "{$tag}-d1", 'draft');
    adpjProject($this->tenant->id, "{$tag}-a1", 'active');
    adpjProject($this->tenant->id, "{$tag}-c1", 'completed');
    // O adversário ganha um de cada: se o KPI vazasse, os totais ficariam acima do banco.
    adpjProject($this->adversario->id, "{$tag}-xd", 'draft');
    adpjProject($this->adversario->id, "{$tag}-xa", 'active');
    adpjProject($this->adversario->id, "{$tag}-xc", 'completed');

    $kpis = adpjProps($this)['kpis'] ?? [];

    $meus = fn (?string $status = null) => DB::table('mcp_projects')
        ->where('business_id', $this->tenant->id)
        ->when($status, fn ($q) => $q->where('status', $status))
        ->count();

    expect($kpis['total'] ?? null)->toBe($meus());
    expect($kpis['draft'] ?? null)->toBe($meus('draft'));
    expect($kpis['active'] ?? null)->toBe($meus('active'));
    expect($kpis['completed'] ?? null)->toBe($meus('completed'));
    // Pré-condição anti-vácuo: os 3 do tenant estão no total.
    expect($meus())->toBeGreaterThanOrEqual(3);
});
