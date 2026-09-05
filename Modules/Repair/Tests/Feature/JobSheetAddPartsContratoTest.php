<?php

declare(strict_types=1);

use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Tests\Support\JobSheetFixtures as Fx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável da tela de PEÇAS DA OS — `/repair/job-sheet/add-parts/{id}`.
 *
 * Fixa o comportamento VIGENTE de `JobSheetController::addParts/saveParts` antes que a
 * tela Inertia saia do canary, para que a Page não descubra o contrato em produção.
 *
 * ⛔ ESCOPO — esta suíte MEDE, não corrige. A tela mexe em peças de uma OS, então
 * qualquer alteração de quantidade, preço, total ou baixa de estoque cai sob a REGRA
 * MESTRE de `memory/proibicoes.md` (dupla prova + tabela antes→depois + aval [W]).
 * Escrever o contrato é o intent; mudar o cálculo NÃO é. Onde um UC sai vermelho, o
 * `❌` é o achado com recibo — a correção é decisão [W], não conserto silencioso.
 *
 * O QUE O CHARTER MANDA DEFENDER (`AddParts.charter.md`)
 * ------------------------------------------------------
 *   Automation Anti-hooks:
 *     ❌ NÃO consome estoque ao salvar parts (consumo via FSM action)  → UC-JSP-01
 *   Automation Hooks:
 *     POST /repair/job-sheet/save-parts/{id} legacy preservado          → UC-JSP-02
 *     Permission `job_sheet.create` OR `edit`                           → UC-JSP-04
 *   Non-Goals:
 *     ❌ Sem FSM (action não-transitiva)                                → UC-JSP-06
 *
 * ORDEM DE FONTE: charter + RUNBOOK-jobsheet-add-parts.md + o controller real. NUNCA o
 * `.tsx` — teste derivado da implementação é tautológico (§5 2026-06-05). O módulo não
 * tem SDD/CU para esta tela; onde a fonte faltou, o UC descreve o que o CONTROLLER
 * garante hoje e diz isso na cara.
 *
 * TENANT: `seededTenant()` = 98 (fictício). biz=4 é proibido sem exceção; biz=1 é
 * empresa real (ADR 0358). O cross-tenant usa `seededSupportClientTenant()` = 99.
 *
 * ⚠️ ONDE ESTE ARQUIVO É PROVADO: a lane `modules-pest.yml` (matrix `Repair`) dispara
 * neste PR, mas roda sqlite `:memory:` SEM migrate — lá estes UCs PULAM, e o verde dela
 * prova só que o arquivo carrega. A prova real sai do CT 100 (MySQL), e é de lá que vem
 * o `Status:` de cada UC no `.casos.md`.
 *
 * @see resources/js/Pages/Repair/JobSheet/AddParts.casos.md
 * @see memory/requisitos/Repair/RUNBOOK-jobsheet-add-parts.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

beforeEach(function () {
    if (($motivo = Fx::motivoDeSkip()) !== null) {
        $this->markTestSkipped($motivo);
    }
});

/**
 * Fotografia do estoque — a prova de que salvar peças não movimenta nada.
 *
 * O estoque do UltimatePOS vive em `variation_location_details.qty_available`; o que
 * CONSOME é o side-effect `ConsumirEstoque` do FSM (ADR 0143), nunca esta tela. A foto
 * é global de propósito: um consumo indevido apareceria como linha nova OU como soma
 * diferente, e as duas coisas quebram a identidade abaixo.
 */
function addPartsFotoDoEstoque(): array
{
    if (! Schema::hasTable('variation_location_details')) {
        return ['linhas' => null, 'soma' => null];
    }

    return [
        'linhas' => (int) DB::table('variation_location_details')->count(),
        'soma' => (string) (DB::table('variation_location_details')->sum('qty_available') ?? 0),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSP-01 — salvar peças NÃO consome estoque (anti-hook Tier 0 do charter)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSP-01: salvar peças na OS não movimenta estoque', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);
    $variacao = Fx::peca((int) $biz->id, (int) $user->id);

    $antes = addPartsFotoDoEstoque();

    $this->actingAs($user)->post("/repair/job-sheet/save-parts/{$osId}", [
        'parts' => [(string) $variacao => ['quantity' => 3]],
    ]);

    $depois = addPartsFotoDoEstoque();

    // A peça FOI registrada na OS (senão o teste seria verde por não ter feito nada).
    expect(JobSheet::find($osId)->parts)->toHaveKey((string) $variacao);

    // E o estoque não se mexeu: consumo é do FSM (ADR 0143), não desta tela.
    expect($depois['linhas'])->toBe($antes['linhas']);
    expect($depois['soma'])->toBe($antes['soma']);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSP-02 — a gravação SUBSTITUI o conjunto inteiro (contrato destrutivo)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSP-02: salvar substitui a lista inteira de peças, não faz merge', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $pecaA = Fx::peca((int) $biz->id, (int) $user->id, 'Peca A');
    $pecaB = Fx::peca((int) $biz->id, (int) $user->id, 'Peca B');

    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id, [
        'parts' => json_encode([
            (string) $pecaA => ['quantity' => 2],
            (string) $pecaB => ['quantity' => 5],
        ]),
    ]);

    // Submete SÓ a peça A — o contrato vigente é substituição, então B some.
    $this->actingAs($user)->post("/repair/job-sheet/save-parts/{$osId}", [
        'parts' => [(string) $pecaA => ['quantity' => 2]],
    ]);

    $gravado = JobSheet::find($osId)->parts;

    expect($gravado)->toHaveKey((string) $pecaA);
    expect($gravado)->not->toHaveKey((string) $pecaB);
});

it('UC-JSP-02: submeter sem nenhuma peça zera a lista da OS', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $peca = Fx::peca((int) $biz->id, (int) $user->id);

    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id, [
        'parts' => json_encode([(string) $peca => ['quantity' => 7]]),
    ]);

    $this->actingAs($user)->post("/repair/job-sheet/save-parts/{$osId}", []);

    expect(JobSheet::find($osId)->parts)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSP-03 — OS de outro tenant é invisível (Tier 0, ADR 0093)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSP-03: não abre nem grava peças em OS de outro negócio', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $contatoVizinho = Fx::cliente((int) $vizinho->id, (int) $donoVizinho->id);
    $statusVizinho = Fx::status((int) $vizinho->id);
    $peca = Fx::peca((int) $biz->id, (int) $user->id);

    $osAlheia = Fx::os(
        (int) $vizinho->id,
        $contatoVizinho,
        $statusVizinho,
        (int) $donoVizinho->id,
        ['parts' => json_encode([])]
    );

    $this->actingAs($user)->get("/repair/job-sheet/add-parts/{$osAlheia}")->assertNotFound();

    $this->actingAs($user)->post("/repair/job-sheet/save-parts/{$osAlheia}", [
        'parts' => [(string) $peca => ['quantity' => 1]],
    ])->assertNotFound();

    // E a OS do vizinho continua intacta — 404 que ainda assim gravasse seria pior.
    expect(JobSheet::withoutGlobalScopes()->find($osAlheia)->parts)->toBe([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSP-04 — sem permissão, a tela não existe
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSP-04: usuário sem job_sheet.create nem job_sheet.edit recebe 403', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id, superadmin: false);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);

    $this->actingAs($user)->get("/repair/job-sheet/add-parts/{$osId}")->assertForbidden();
    $this->actingAs($user)->post("/repair/job-sheet/save-parts/{$osId}", ['parts' => []])->assertForbidden();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSP-05 — peça de OUTRO negócio não pode ser exibida na OS (Tier 0)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSP-05: peça de outro negócio não aparece na OS', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);

    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $pecaAlheia = Fx::peca((int) $vizinho->id, (int) $donoVizinho->id, 'Peca Secreta do Vizinho');

    $this->actingAs($user)->post("/repair/job-sheet/save-parts/{$osId}", [
        'parts' => [(string) $pecaAlheia => ['quantity' => 1]],
    ]);

    // `getPartsUsed()` é a FONTE do payload `parts` desta tela (o controller o chama e
    // passa por `buildJobSheetPartsPayload`). Medir aqui é medir o que a tela mostraria.
    $exibidas = JobSheet::find($osId)->getPartsUsed();

    expect($exibidas)->not->toHaveKey($pecaAlheia);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSP-06 — a tela não é caminho de transição de estágio (Non-Goal do charter)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSP-06: salvar peças não altera o estágio FSM da OS', function () {
    if (! Schema::hasColumn('repair_job_sheets', 'current_stage_id')) {
        $this->markTestSkipped('Coluna current_stage_id ausente — FSM (ADR 0143) não migrado neste banco.');
    }

    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);
    $peca = Fx::peca((int) $biz->id, (int) $user->id);

    $estagioAntes = DB::table('repair_job_sheets')->where('id', $osId)->value('current_stage_id');

    $this->actingAs($user)->post("/repair/job-sheet/save-parts/{$osId}", [
        'parts' => [(string) $peca => ['quantity' => 1]],
        // Tenta empurrar o estágio junto: a tela não é gateway de FSM.
        'current_stage_id' => 999999,
    ]);

    $estagioDepois = DB::table('repair_job_sheets')->where('id', $osId)->value('current_stage_id');

    expect($estagioDepois)->toBe($estagioAntes);
});
