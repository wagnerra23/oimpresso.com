<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Modules\Repair\Tests\Support\JobSheetFixtures as Fx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável do DETALHE DA OS — `/repair/job-sheet/{id}`.
 *
 * Fixa o comportamento VIGENTE de `JobSheetController::show` antes de a tela Inertia
 * sair do canary, para que a Page não descubra o contrato em produção.
 *
 * ⛔ ESCOPO — esta suíte MEDE, não corrige. Onde um UC sai vermelho, o `❌` é o achado
 * com recibo; a correção é decisão [W].
 *
 * O QUE O CHARTER MANDA DEFENDER (`Show.charter.md`)
 * --------------------------------------------------
 *   Automation Anti-hooks:
 *     ❌ NÃO acessa OS de outro biz (Tier 0)                     → UC-JSS-01
 *     ❌ NÃO faz UPDATE direto em `current_stage_id`             → UC-JSS-03/04 (só endereços + leitura)
 *   Automation Hooks:
 *     Permission `job_sheet.view_all` OU `view_assigned`         → UC-JSS-02 / UC-JSS-05
 *     FSM execute via POST /repair/job-sheets/{id}/fsm-action    → UC-JSS-03
 *   Goals:
 *     FSM Panel: actions disponíveis OU "Iniciar pipeline"       → UC-JSS-04
 *
 * UC-JSS-03 existe por causa do RISCO R1 (MÉDIO) do RUNBOOK: o `FsmActionPanel` é
 * compartilhado com Vendas e assume endpoints `/sells/...`; o Repair injeta os seus por
 * um wrapper. Se os endereços vazarem para os de Vendas, a tela dispara transição no
 * módulo errado — e nada além deste UC pegaria isso.
 *
 * ORDEM DE FONTE: charter + RUNBOOK-jobsheet-show.md + o controller real. NUNCA o
 * `.tsx` — teste derivado da implementação é tautológico (§5 2026-06-05). O módulo não
 * tem SDD/CU para esta tela; onde a fonte faltou, o UC descreve o que o CONTROLLER
 * garante hoje e diz isso na cara.
 *
 * TENANT: `seededTenant()` = 98 (fictício). biz=4 proibido; biz=1 é empresa real (ADR 0358).
 *
 * ⚠️ ONDE ESTE ARQUIVO É PROVADO: a lane `modules-pest.yml` (matrix `Repair`) dispara
 * neste PR, mas roda sqlite `:memory:` SEM migrate — lá estes UCs PULAM. A prova real
 * sai do CT 100 (MySQL), e é de lá que vem o `Status:` de cada UC no `.casos.md`.
 *
 * @see resources/js/Pages/Repair/JobSheet/Show.casos.md
 * @see memory/requisitos/Repair/RUNBOOK-jobsheet-show.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

beforeEach(function () {
    if (($motivo = Fx::motivoDeSkip()) !== null) {
        $this->markTestSkipped($motivo);
    }
});

/**
 * Liga o branch Inertia desta tela.
 *
 * As flags MWART nascem `false` (config/mwart.php), então o caminho vivo hoje é o Blade.
 * Os UCs que leem PROPS precisam do branch Inertia ligado explicitamente — e ligar aqui,
 * em vez de depender do `.env` do ambiente, é o que faz o resultado ser o mesmo no CI,
 * no CT 100 e na máquina de quem for revalidar.
 */
function showComInertiaLigado(): void
{
    config()->set('mwart.repair_job_sheet_show.enabled', true);
    config()->set('mwart.repair_job_sheet_show.business_ids', []);
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSS-01 — OS de outro negócio é invisível (Tier 0, ADR 0093)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSS-01: não abre o detalhe de OS de outro negócio', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $contatoVizinho = Fx::cliente((int) $vizinho->id, (int) $donoVizinho->id, 'Cliente do Vizinho');
    $statusVizinho = Fx::status((int) $vizinho->id);
    $osAlheia = Fx::os((int) $vizinho->id, $contatoVizinho, $statusVizinho, (int) $donoVizinho->id);

    $this->actingAs($user)->get("/repair/job-sheet/{$osAlheia}")->assertNotFound();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSS-02 — sem `view_all`, só se vê a OS própria
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSS-02: quem não tem job_sheet.view_all só enxerga a OS que criou ou atende', function () {
    $biz = $this->seededTenant();
    showComInertiaLigado();

    // `superadmin` satisfaz o gate de entrada do controller, mas NÃO concede
    // `job_sheet.view_all` (o Gate::before só bypassa quem tem a role Admin#<biz>) —
    // então este usuário cai justamente no filtro por criador/atendente.
    $tecnico = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $tecnico->id);

    $colega = Fx::usuario((int) $biz->id, false);
    $contato = Fx::cliente((int) $biz->id, (int) $tecnico->id);
    $status = Fx::status((int) $biz->id);

    $minhaOs = Fx::os((int) $biz->id, $contato, $status, (int) $tecnico->id);
    $osDoColega = Fx::os((int) $biz->id, $contato, $status, (int) $colega->id);
    $osAtribuidaAMim = Fx::os((int) $biz->id, $contato, $status, (int) $colega->id, [
        'service_staff' => (int) $tecnico->id,
    ]);

    expect($tecnico->can('job_sheet.view_all'))->toBeFalse();

    $this->actingAs($tecnico)->get("/repair/job-sheet/{$minhaOs}")->assertOk();
    $this->actingAs($tecnico)->get("/repair/job-sheet/{$osAtribuidaAMim}")->assertOk();
    $this->actingAs($tecnico)->get("/repair/job-sheet/{$osDoColega}")->assertNotFound();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSS-03 — os endereços do painel FSM são do Repair, não de Vendas (risco R1)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSS-03: o painel FSM recebe endereços do Repair, nunca os de Vendas', function () {
    $biz = $this->seededTenant();
    showComInertiaLigado();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);

    $this->actingAs($user)->get("/repair/job-sheet/{$osId}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Repair/JobSheet/Show')
            ->where('fsm.endpoints.actions', "/api/repair/job-sheets/{$osId}/fsm-actions")
            ->where('fsm.endpoints.execute', "/repair/job-sheets/{$osId}/fsm-action")
            ->where('fsm.endpoints.start_pipeline', "/repair/job-sheets/{$osId}/fsm-start-pipeline")
        );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSS-04 — a tela declara se a OS já entrou no pipeline
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSS-04: a tela informa se a OS está no pipeline FSM', function () {
    if (! Schema::hasColumn('repair_job_sheets', 'current_stage_id')) {
        $this->markTestSkipped('Coluna current_stage_id ausente — FSM (ADR 0143) não migrado neste banco.');
    }

    $biz = $this->seededTenant();
    showComInertiaLigado();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);

    // OS legada (nasce fora do pipeline): a tela precisa oferecer "Iniciar pipeline".
    $this->actingAs($user)->get("/repair/job-sheet/{$osId}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('fsm.in_pipeline', false));

    // Um estágio existente é pré-condição para o outro lado do contrato; sem pipeline
    // semeado no banco, afirmar `true` exigiria plantar FK inválida — e teste que planta
    // a própria pré-condição global mente sobre o que o ambiente tem (§5 2026-08-24).
    $estagio = Schema::hasTable('sale_process_stages')
        ? DB::table('sale_process_stages')->value('id')
        : null;

    if ($estagio === null) {
        return;
    }

    DB::table('repair_job_sheets')->where('id', $osId)->update(['current_stage_id' => $estagio]);

    $this->actingAs($user)->get("/repair/job-sheet/{$osId}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('fsm.in_pipeline', true));
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSS-05 — sem permissão, o detalhe não existe
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSS-05: usuário sem permissão de OS recebe 403 no detalhe', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id, superadmin: false);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $dono = Fx::usuario((int) $biz->id, false);
    $contato = Fx::cliente((int) $biz->id, (int) $dono->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $dono->id);

    $this->actingAs($user)->get("/repair/job-sheet/{$osId}")->assertForbidden();
});
