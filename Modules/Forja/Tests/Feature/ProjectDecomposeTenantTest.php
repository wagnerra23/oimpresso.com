<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forja\Services\ProjectDecomposerService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

// @covers-us US-ADS-004 — Detalhe do Project: ação de decompose.

/**
 * Tier 0 (ADR 0093): o decompose de um project respeita a empresa da sessão.
 *
 * Contrato: ADR 0093 (business_id em toda query de dado de negócio) + o `[BACKLOG]` do
 * `ProjectShow.casos.md` que registrou o achado de leitura: `ProjectDecomposerService::decompose`
 * buscava `mcp_projects` só por `id`, enquanto o `show` da mesma tela já filtra por `business_id`.
 *
 * COMO SE MEDE SEM CHAMAR A IA: o project tem uma part já gravada. O serviço para antes do agente
 * nos dois caminhos que interessam:
 *   - project de OUTRA empresa → tem de responder `project_not_found` (não pode nem ler o project);
 *   - project da MINHA empresa → responde `already_decomposed` (controle positivo: o mesmo estado,
 *     do lado certo, chega ao segundo portão — a resposta discrimina os dois casos).
 * Antes do conserto, o project da outra empresa respondia `already_decomposed`: o serviço lia o
 * project e as parts de outro tenant. Sem part gravada ele seguiria para a IA e gravaria parts no
 * project alheio.
 *
 * Tenant 98 (fictício) × 99 (adversário), ADR 0358. NUNCA biz=4. DatabaseTransactions: nada persiste.
 * Stack exige MySQL: em sqlite PULA (LC-13) — leia as assertions, não "0 failed".
 */
const DECOMP_ROTA = '/ads/admin/projects';

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
});

/** Project com UMA part já gravada: o decompose para antes do agente de IA. */
function decompProjetoComParte(int $bizId, string $nome): int
{
    $id = DB::table('mcp_projects')->insertGetId([
        'business_id'      => $bizId,
        'codigo'           => 'TDECO-'.strtoupper(bin2hex(random_bytes(4))),
        'nome'             => $nome,
        'objetivo_macro'   => "Objetivo de {$nome}",
        'metricas_sucesso' => json_encode([]),
        'constraints'      => json_encode([]),
        'status'           => 'active',
        'decision'         => 'pending',
        'created_at'       => now()->subDay(),
        'updated_at'       => now()->subDay(),
    ]);
    DB::table('mcp_project_parts')->insert([
        'project_id' => $id,
        'ordem'      => 1,
        'codigo'     => 'P1',
        'nome'       => 'Part P1',
        'objetivo'   => 'Objetivo da part P1',
        'status'     => 'pending',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    return $id;
}

it('UC-ADPS-03 · decompose no serviço: project de OUTRA empresa responde project_not_found; o meu chega ao portão seguinte', function () {
    $meu = decompProjetoComParte($this->tenant->id, 'DECO-meu-'.uniqid());
    $alheio = decompProjetoComParte($this->adversario->id, 'DECO-alheio-'.uniqid());
    $service = app(ProjectDecomposerService::class);

    // Controle positivo: o meu project passa da leitura e para em "já decomposto".
    expect($service->decompose($meu, $this->tenant->id)['error'] ?? null)->toBe('already_decomposed');

    // Tier 0: o project existe, mas é da empresa 99.
    expect($service->decompose($alheio, $this->tenant->id)['error'] ?? null)->toBe('project_not_found');

    // Nada do project alheio mudou.
    expect(DB::table('mcp_project_parts')->where('project_id', $alheio)->count())->toBe(1);
});

it('UC-ADPS-03 · POST /ads/admin/projects/{id}/decompose de project de outra empresa não lê nem escreve nele', function () {
    $meu = decompProjetoComParte($this->tenant->id, 'DECO-http-meu-'.uniqid());
    $alheio = decompProjetoComParte($this->adversario->id, 'DECO-http-alheio-'.uniqid());
    $antes = DB::table('mcp_projects')->where('id', $alheio)->value('updated_at');

    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
    $this->actingAs($this->usuarioComPermissoes([], $this->tenant));

    // Controle positivo: no meu project a requisição chega ao serviço e volta "já decomposto".
    $this->post(DECOMP_ROTA."/{$meu}/decompose")->assertSessionHas('error', fn ($e) => str_contains((string) $e, 'already_decomposed'));

    // Tier 0: no project da empresa 99 a resposta é "não encontrado".
    $this->post(DECOMP_ROTA."/{$alheio}/decompose")->assertSessionHas('error', fn ($e) => str_contains((string) $e, 'project_not_found'));

    expect(DB::table('mcp_project_parts')->where('project_id', $alheio)->count())->toBe(1);
    expect(DB::table('mcp_projects')->where('id', $alheio)->value('updated_at'))->toBe($antes);
});
