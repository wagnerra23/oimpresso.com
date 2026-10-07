<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

// @covers-us US-ADS-004 — Detalhe do Project: parts decompostas + ação de decompose.

/**
 * Contrato da tela /ads/admin/projects/{id} — `Admin\ProjectsController@show`.
 *
 * UC-ADPS-01        — o detalhe do meu project abre com as parts na ordem da decomposição.
 * UC-ADPS-02 `[T0]` — project de outro business, inexistente ou com id não numérico → 404.
 *
 * Os UC derivam do `ProjectShow.charter.md` (Goals, Non-Goals) e da US-ADS-004
 * (memory/requisitos/ADS/SPEC.md) — nunca do `.tsx`.
 * Trio: Modules/Forja/Resources/js/Pages/ads/Admin/{ProjectShow.charter.md,ProjectShow.casos.md}
 *
 * O `POST …/decompose` NÃO é exercitado aqui: o caminho feliz chama o agente de IA
 * (custo e rede). Os casos dele estão como [BACKLOG] no casos.md.
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant 98 (fictício) × 99 (adversário). NUNCA biz=4.
 * DatabaseTransactions: nada persiste. ⛔ Pest só no CT 100 ou no CI (proibicoes §Ambiente).
 * Skip sai exit 0: leia as ASSERTIONS, não "0 failed" (LC-13).
 */
const ADPS_ROTA = '/ads/admin/projects';

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

function adpsProject(int $bizId, string $nome): int
{
    return DB::table('mcp_projects')->insertGetId([
        'business_id'      => $bizId,
        'codigo'           => 'TADPS-'.strtoupper(bin2hex(random_bytes(4))),
        'nome'             => $nome,
        'objetivo_macro'   => "Objetivo de {$nome}",
        'metricas_sucesso' => json_encode([]),
        'constraints'      => json_encode([]),
        'status'           => 'active',
        'decision'         => 'pending',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

function adpsPart(int $projectId, int $ordem, string $codigo): void
{
    DB::table('mcp_project_parts')->insert([
        'project_id' => $projectId,
        'ordem'      => $ordem,
        'codigo'     => $codigo,
        'nome'       => "Part {$codigo}",
        'objetivo'   => "Objetivo da part {$codigo}",
        'status'     => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function adpsVersao(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Visita Inertia com os headers que o navegador manda. */
function adpsGet($test, string $sufixo)
{
    return $test->withHeaders([
        'X-Requested-With'  => 'XMLHttpRequest',
        'Accept'            => 'text/html, application/xhtml+xml',
        'X-Inertia'         => 'true',
        'X-Inertia-Version' => adpsVersao(),
    ])->get(ADPS_ROTA.'/'.$sufixo);
}

it('UC-ADPS-01 · o detalhe do meu project abre com as parts na ordem da decomposição', function () {
    $id = adpsProject($this->tenant->id, 'ADPS01-'.uniqid());
    // Inseridas FORA de ordem de propósito: a ordem da tela é a da decomposição (`ordem`),
    // não a de gravação.
    adpsPart($id, 3, 'P3');
    adpsPart($id, 1, 'P1');
    adpsPart($id, 2, 'P2');

    $resposta = adpsGet($this, (string) $id);

    $resposta->assertStatus(200);
    $resposta->assertJsonPath('component', 'ads/Admin/ProjectShow');
    expect((int) $resposta->json('props.project.id'))->toBe($id);
    expect(array_column($resposta->json('props.parts') ?? [], 'codigo'))->toBe(['P1', 'P2', 'P3']);
});

it('UC-ADPS-02 · project de outro business, inexistente ou com id não numérico responde 404', function () {
    $meu = adpsProject($this->tenant->id, 'ADPS02-meu-'.uniqid());
    $alheio = adpsProject($this->adversario->id, 'ADPS02-alheio-'.uniqid());

    // Controle positivo: o MEU abre — o 404 abaixo é do escopo, não de rota quebrada.
    adpsGet($this, (string) $meu)->assertStatus(200);

    // Tier 0: o id existe, mas é de outro business.
    expect(adpsGet($this, (string) $alheio)->status())->toBe(404);

    // Inexistente: um id acima do maior existente.
    $inexistente = (int) DB::table('mcp_projects')->max('id') + 1000;
    expect(adpsGet($this, (string) $inexistente)->status())->toBe(404);

    // Não numérico: cai em 404 de rota (whereNumber), antes do controller.
    expect(adpsGet($this, 'abc')->status())->toBe(404);
});
