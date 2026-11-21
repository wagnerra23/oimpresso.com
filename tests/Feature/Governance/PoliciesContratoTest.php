<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

/**
 * /governance/policies — contrato HTTP de listagem e toggle (lote L0 da auditoria do
 * Governance, aprovado pelo [W] em 2026-11-19).
 *
 * Âncoras: Policies.charter.md (anti-padrão "Toggle sem registrar histórico — audit fica
 * cego"), Policies.casos.md (UC-GPOL-02, -06, -10, -11) e a rota em routes.php.
 *
 * Antes deste lote, `POST /governance/policies/{id}/toggle` fazia UPDATE por id sem
 * conferir existência (id inexistente respondia sucesso) e sem trilha. Agora: 404 e
 * uma linha só-inserção em `activity_log` (log `governance`, sem business_id — a tabela
 * de regras é de plataforma).
 *
 * Cada caso cria a SUA regra (rule_key único) e lê de volta pelo id; nada depende de
 * seed. Tenant fictício 98 (ADR 0358), nunca biz=4. Transação revertida: o banco do
 * CT 100 persiste entre runs.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a stack de rotas exige schema MySQL UltimatePOS (ADR 0358)');
    }
    if (! Schema::hasTable('mcp_governance_rules') || ! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('mcp_governance_rules/activity_log ausentes — rode as migrations.');
    }

    $this->b98 = $this->seededTenant();
});

function govPolRegra(bool $enabled): int
{
    return (int) DB::table('mcp_governance_rules')->insertGetId([
        'rule_key'    => 'teste-l0-' . uniqid(),
        'name'        => 'Regra de teste L0',
        'description' => 'fixture do PoliciesContratoTest',
        'category'    => 'review',
        'condition'   => '{}',
        'action'      => '{}',
        'enabled'     => $enabled ? 1 : 0,
        'created_by'  => 'teste',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

function govPolSessao(User $user, int $biz): array
{
    return ['user.business_id' => $biz, 'user' => ['business_id' => $biz, 'id' => $user->id]];
}

function govPolToggle(User $user, int $biz, int $id, bool $enabled)
{
    return test()->actingAs($user)->withSession(govPolSessao($user, $biz))
        ->post("/governance/policies/{$id}/toggle", ['enabled' => $enabled]);
}

/** Linhas da trilha desta regra, decodificadas (properties é text, não JSON nativo). */
function govPolTrilha(int $id): array
{
    return Activity::query()
        ->where('log_name', 'governance')
        ->where('description', 'governance.policy.toggle')
        ->get()
        ->filter(fn (Activity $a) => (int) ($a->properties['policy_id'] ?? 0) === $id)
        ->values()
        ->all();
}

it('UC-GPOL-02 · com governance.dashboard.view a listagem abre com a regra e os 4 KPIs', function () {
    $id = govPolRegra(true);
    $user = $this->usuarioComPermissoes(['governance.dashboard.view'], $this->b98);

    test()->actingAs($user)->withSession(govPolSessao($user, (int) $this->b98->id))
        ->get('/governance/policies')
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('governance/Policies', false)
            ->has('kpis', fn (AssertableInertia $k) => $k->hasAll(['total', 'enabled', 'triggered', 'categories']))
            ->where('rules_by_category', fn ($cats) => collect($cats)
                ->flatMap(fn ($c) => $c['rules'])
                ->contains(fn ($r) => (int) $r['id'] === $id)));
});

it('UC-GPOL-06 · sem governance.policies.edit o toggle recusa com 403 e a regra não muda', function () {
    $id = govPolRegra(true);
    $user = $this->usuarioComPermissoes(['governance.dashboard.view'], $this->b98);

    govPolToggle($user, (int) $this->b98->id, $id, false)->assertStatus(403);

    $this->assertSame(1, (int) DB::table('mcp_governance_rules')->where('id', $id)->value('enabled'));
    $this->assertCount(0, govPolTrilha($id), 'recusa gravou trilha');
});

it('UC-GPOL-10 · toggle de id inexistente responde 404 e não grava trilha', function () {
    $user = $this->usuarioComPermissoes(['governance.policies.edit'], $this->b98);
    $inexistente = (int) DB::table('mcp_governance_rules')->max('id') + 1000;

    govPolToggle($user, (int) $this->b98->id, $inexistente, true)->assertStatus(404);

    $this->assertCount(0, govPolTrilha($inexistente), 'id inexistente gravou trilha');
});

it('UC-GPOL-11 · toggle muda a regra e grava quem, quando e antes→depois', function () {
    $id = govPolRegra(true);
    $user = $this->usuarioComPermissoes(['governance.policies.edit'], $this->b98);

    govPolToggle($user, (int) $this->b98->id, $id, false)->assertStatus(302);

    $this->assertSame(0, (int) DB::table('mcp_governance_rules')->where('id', $id)->value('enabled'));

    $trilha = govPolTrilha($id);
    $this->assertCount(1, $trilha, 'toggle deveria gravar exatamente 1 linha de trilha');
    $linha = $trilha[0];
    $this->assertSame($user->id, (int) $linha->causer_id, 'trilha sem o autor');
    $this->assertNotNull($linha->created_at, 'trilha sem o quando');
    $this->assertSame(['enabled' => true], $linha->properties['old'], 'trilha sem o estado anterior');
    $this->assertSame(['enabled' => false], $linha->properties['attributes'], 'trilha sem o estado novo');
    $this->assertNull($linha->business_id, 'regra é de plataforma: a trilha não pode cair na /auditoria de uma empresa');
});

it('UC-GPOL-11 · pedir o estado que a regra já tem não grava trilha', function () {
    // Controle do caso acima: a linha só nasce quando o estado MUDA.
    $id = govPolRegra(true);
    $user = $this->usuarioComPermissoes(['governance.policies.edit'], $this->b98);

    govPolToggle($user, (int) $this->b98->id, $id, true)->assertStatus(302);

    $this->assertCount(0, govPolTrilha($id), 'toggle sem mudança gravou trilha');
});
