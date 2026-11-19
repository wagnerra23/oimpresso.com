<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

/**
 * Quem pode abrir /governance/dashboard, testado pela requisição HTTP (UC-GDSH-07).
 *
 * Âncora: decisão [W] de 2026-11-19 (lote L0 da auditoria do Governance) + ADR 0392 §D-D
 * passo 2 (gate de URL igual ao gate de sidebar, `DataController::modifyAdminMenu`). Até
 * esse lote a rota era só `auth`: qualquer usuário de qualquer empresa via o custo de IA
 * somado de todas as empresas (`custo_ia_brl_24h`) e os destaques do log MCP.
 *
 * Cada recusa tem o par que passa com a MESMA requisição, mudando só a permissão — o 403
 * não pode vir de outra camada (sessão, pacote, middleware de login). O 200 confere o
 * componente e a prop que a recusa esconde, então os dois casos discriminam.
 *
 * O que NÃO prova: que o dono de uma empresa (papel `Admin#{empresa}`) é barrado. O
 * `Gate::before` ainda o libera em `governance.*` (ADR 0415 não as pôs na lista de
 * plataforma) — decisão [W] em aberto, passo 3 da ADR 0392.
 *
 * Tenant fictício 98 (ADR 0358), nunca biz=4. Transação revertida: o banco do CT 100
 * persiste entre runs.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a stack de rotas exige schema MySQL UltimatePOS (ADR 0358)');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }

    $this->b98 = $this->seededTenant();
});

function govDashGet(User $user, int $biz)
{
    return test()->actingAs($user)
        ->withSession(['user.business_id' => $biz, 'user' => ['business_id' => $biz, 'id' => $user->id]])
        ->get('/governance/dashboard');
}

it('UC-GDSH-07 · sem governance.dashboard.view, /governance/dashboard recusa com 403 e não entrega o custo de IA', function () {
    $semPerm = $this->usuarioComPermissoes([], $this->b98);

    $resposta = govDashGet($semPerm, (int) $this->b98->id);

    $resposta->assertStatus(403);
    $this->assertStringNotContainsString(
        'custo_ia_brl_24h',
        (string) $resposta->getContent(),
        'a recusa vazou a prop de custo de IA somado de todas as empresas'
    );
});

it('UC-GDSH-07 · com governance.dashboard.view, a mesma requisição abre o painel com o custo de IA', function () {
    $comPerm = $this->usuarioComPermissoes(['governance.dashboard.view'], $this->b98);

    govDashGet($comPerm, (int) $this->b98->id)
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('governance/Dashboard', false)
            ->has('health_kpis.custo_ia_brl_24h'));
});

it('UC-GDSH-07 · permissão de OUTRA tela do Governance não abre o painel', function () {
    // Controle de que o gate é da permissão certa, e não "qualquer governance.*".
    $outra = $this->usuarioComPermissoes(['governance.audit.view'], $this->b98);

    govDashGet($outra, (int) $this->b98->id)->assertStatus(403);
});
