<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

// @covers-us US-ADS-003 — Portfólio de Projects. @covers-us US-ADS-004 — Detalhe do Project.

/**
 * Thread 13 do playbook Forja — decisões [W] D10 + D11 (2026-10-07):
 *   D10 "exigir permissão do módulo Forja" — antes, `/ads/admin/projects*` só exigia login.
 *   D11 "tirar do charter" — a seção de decisões ligadas ao project, sempre vazia desde a ADR 0363.
 *
 * UC-ADPJ-05 `[T0]` — logado sem `jana.mcp.usage.all`: a lista e a criação dão 403 e nada é gravado.
 * UC-ADPS-04 `[T0]` — logado sem `jana.mcp.usage.all`: o detalhe e o decompose dão 403 e nada muda.
 * UC-ADPS-05        — o charter do detalhe não promete mais decisões linkadas ao project (D11).
 *
 * A permissão do módulo é `jana.mcp.usage.all`, a mesma de Aprovações/Trabalho/Roadmap/Team/Tasks
 * (DataController: "As telas da Forja reusam `jana.mcp.usage.all`").
 *
 * Controle positivo em cada caso: o MESMO pedido, com a permissão, chega ao controller (200/302 de
 * sucesso). Sem ele, um 403 vindo de outra camada (CheckUserLogin, pacote) faria o negativo passar
 * pelo motivo errado (§5 2026-09-27).
 *
 * Fonte: `prototipo-ui/cowork/Wagner/cowork-inbox/forja/playbook/13-projects-acesso.md`
 * + `_DECISOES-W-2026-10-07c.md` (D10, D11) + ADR 0093. Nunca o `.tsx`.
 *
 * Tier 0 (ADR 0358): tenant fictício 98. NUNCA biz=4. DatabaseTransactions: nada persiste.
 * Stack exige MySQL: em sqlite PULA (LC-13) — leia as assertions, não "0 failed".
 *
 * @see Modules/Forja/Http/Controllers/Admin/ProjectsController.php
 */
const PACESSO_ROTA = '/ads/admin/projects';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }
    foreach (['users', 'business', 'mcp_projects', 'mcp_project_parts'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente — schema baseline não aplicado.");
        }
    }

    $this->tenant = $this->seededTenant();
    session()->flush(); // SetSessionData reconstrói a sessão a partir do usuário autenticado
});

/** Usuário do tenant 98 com só as permissões pedidas. */
function pacessoUsuario(array $permissoes): User
{
    // Cache do Spatie limpo ANTES do helper: permissão criada numa transação já revertida
    // deixaria um id inexistente no cache (FK 1452 no syncPermissions).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return test()->usuarioComPermissoes($permissoes, test()->tenant);
}

/** Project com UMA part já gravada: um decompose que passasse pararia antes da IA. */
function pacessoProjeto(int $bizId, string $nome): int
{
    $id = DB::table('mcp_projects')->insertGetId([
        'business_id'      => $bizId,
        'codigo'           => 'TPACE-'.strtoupper(bin2hex(random_bytes(4))),
        'nome'             => $nome,
        'objetivo_macro'   => "Objetivo de {$nome}",
        'metricas_sucesso' => json_encode([]),
        'constraints'      => json_encode([]),
        'status'           => 'active',
        'decision'         => 'pending',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    DB::table('mcp_project_parts')->insert([
        'project_id'   => $id,
        'codigo'       => 'P1',
        'ordem'        => 1,
        'nome'         => "Parte de {$nome}",
        'objetivo'     => 'Parte do teste de acesso',
        'status'       => 'pending',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    return $id;
}

it('UC-ADPJ-05 · logado sem a permissão da Forja, a lista e a criação dão 403 e nada é gravado', function () {
    // Controle positivo: com a permissão, a lista abre.
    $this->actingAs(pacessoUsuario(['jana.mcp.usage.all']))->get(PACESSO_ROTA)->assertStatus(200);

    session()->flush();
    $this->actingAs(pacessoUsuario([]));

    $this->get(PACESSO_ROTA)->assertStatus(403);

    $nome = 'ADPJ05-'.uniqid();
    $this->post(PACESSO_ROTA, [
        'nome'           => $nome,
        'objetivo_macro' => 'Tentativa sem permissão',
    ])->assertStatus(403);

    expect(DB::table('mcp_projects')->where('nome', $nome)->count())->toBe(0);
});

it('UC-ADPS-04 · logado sem a permissão da Forja, o detalhe e o decompose dão 403 e nada muda', function () {
    $id = pacessoProjeto((int) $this->tenant->id, 'ADPS04-'.uniqid());

    // Controle positivo: com a permissão, o detalhe do MESMO project abre.
    $this->actingAs(pacessoUsuario(['jana.mcp.usage.all']))->get(PACESSO_ROTA."/{$id}")->assertStatus(200);

    session()->flush();
    $this->actingAs(pacessoUsuario([]));

    $antes = DB::table('mcp_projects')->where('id', $id)->value('updated_at');

    $this->get(PACESSO_ROTA."/{$id}")->assertStatus(403);
    $this->post(PACESSO_ROTA."/{$id}/decompose")->assertStatus(403);

    expect(DB::table('mcp_project_parts')->where('project_id', $id)->count())->toBe(1);
    expect(DB::table('mcp_projects')->where('id', $id)->value('updated_at'))->toBe($antes);
});

it('UC-ADPS-05 · o charter do detalhe não promete decisões linkadas ao project (D11)', function () {
    $charter = file_get_contents(base_path('Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.charter.md'));

    // Controle positivo: o arquivo lido é o charter do detalhe (não um vazio que passaria tudo).
    expect($charter)->toContain('/ads/admin/projects/{id}');

    expect(str_contains($charter, '/ads/admin/decisoes'))->toBeFalse();
    expect(str_contains($charter, 'decisões geradas'))->toBeFalse();
    expect(str_contains($charter, 'decisões linkadas'))->toBeFalse();
});
