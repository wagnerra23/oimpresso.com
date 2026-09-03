<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smoke das rotas principais do Modules/Forja + checagem do
 * middleware auth aplicado na stack canônica UltimatePOS.
 *
 * ONDA 11 (2026-09-02): 7 das 8 telas de /project-mgmt foram revogadas
 * (ADR 0367 D1 + PARIDADE §11). Este smoke deixou de exercitar rotas mortas e
 * passou a ser o GUARD DA PRÓPRIA REVOGAÇÃO — se alguém ressuscitar um dos
 * prefixos, ou quebrar um 301, ele reprova.
 *
 * Valida que:
 *   1. GET /project-mgmt/roadmap responde <500 (sobrevive — ADR 0367 D7)
 *   2. GET /forja/search responde <500 (a busca mudou de prefixo, não morreu)
 *   3. os 7 caminhos revogados respondem 301 pro receptor MEDIDO de cada um
 *   4. o 301 vale SEM sessão (está fora do grupo auth de propósito)
 *   5. acesso anônimo ao que sobrou segue bloqueado por middleware 'auth'
 *
 * Stack UltimatePOS canônica: ['web','SetSessionData','auth','language',
 * 'timezone','AdminSidebarMenu','CheckUserLogin'] (Http/routes.php).
 *
 * NUNCA usar biz=4 (ROTA LIVRE produção) — ADR 0101. Tests assumem rotas
 * existem (validado em ScaffoldForjaTest) e só checam status HTTP
 * não-500 (crash de Controller/middleware).
 *
 * @see memory/decisions/0070-jira-style-task-management-current-md-removed.md
 * @see memory/decisions/0100-projectmgmt-ui-redesign.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

uses(Tests\TestCase::class);

// Guard SQLite: middlewares UltimatePOS (SetSessionData/AdminSidebarMenu)
// requerem tables MySQL (business/users/permissions) — smoke real precisa
// schema completo.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: middlewares UltimatePOS (SetSessionData, AdminSidebarMenu) requerem schema MySQL com tables business/users/permissions (ADR 0101)');
    }
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('business table missing — rode migrations UltimatePOS base primeiro');
    }
});

// ------------------------------------------------------------------
// 1-2) O que sobreviveu segue de pé
// ------------------------------------------------------------------
it('GET /project-mgmt/roadmap (quarter view, ADR 0367 D7) responde com status < 500', function () {
    $response = $this->get(route('project-mgmt.roadmap.index'));
    expect($response->getStatusCode())->toBeLessThan(500);
});

it('GET /forja/search (busca global do ⌘K) responde com status < 500', function () {
    $response = $this->get(route('forja.search', ['q' => 'smoke']));
    expect($response->getStatusCode())->toBeLessThan(500);
});

// ------------------------------------------------------------------
// 3-4) A revogação: cada caminho morto aponta pro receptor certo.
// O destino NÃO é decorativo — foi medido contra os filtros que o
// TrabalhoController aceita (visao/cycle/q existem; `project` não).
// ------------------------------------------------------------------
dataset('rotas revogadas', [
    'board vira o quadro da lista única'      => ['/project-mgmt/board',    '/forja/trabalho?visao=quadro'],
    'backlog vira a lista única'              => ['/project-mgmt/backlog',  '/forja/trabalho?visao=lista'],
    'triagem virou aba do hub (D6)'           => ['/project-mgmt/triage',   '/forja'],
    'my-work sem receptor (D5) cai na lista'  => ['/project-mgmt/my-work',  '/forja/trabalho'],
    'inbox sem receptor (D5) cai no hub'      => ['/project-mgmt/inbox',    '/forja'],
    'activity sem receptor (D1)'              => ['/project-mgmt/activity', '/forja/changelog'],
    'burndown sem receptor (D5)'              => ['/project-mgmt/burndown', '/forja'],
]);

it('rota revogada responde 301 pro receptor', function (string $velho, string $novo) {
    $response = $this->get($velho);

    expect($response->getStatusCode())->toBe(301);
    expect($response->headers->get('Location'))->toEndWith($novo);
})->with('rotas revogadas');

it('o 301 das rotas revogadas NÃO exige sessão', function () {
    // Fora do grupo auth de propósito: link velho em doc/handoff tem de
    // resolver pro destino novo mesmo sem login — quem pede sessão é o destino.
    auth()->logout();

    $response = $this->get('/project-mgmt/board');

    expect($response->getStatusCode())->toBe(301);
});

// ------------------------------------------------------------------
// 5) O que sobrou continua atrás do middleware auth
// ------------------------------------------------------------------
it('rota /project-mgmt/roadmap bloqueia acesso anônimo via middleware auth', function () {
    auth()->logout();

    $response = $this->get(route('project-mgmt.roadmap.index'));

    expect($response->getStatusCode())->not->toBe(200);
});
