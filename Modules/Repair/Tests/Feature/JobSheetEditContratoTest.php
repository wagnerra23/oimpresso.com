<?php

declare(strict_types=1);

use App\Utils\Util;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Repair\Tests\Support\JobSheetFixtures as Fx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável da EDIÇÃO DE OS — `/repair/job-sheet/{id}/edit` + PUT `/repair/job-sheet/{id}`.
 *
 * Fixa o comportamento VIGENTE de `JobSheetController::edit/update` antes de a tela
 * Inertia sair do canary, para que a Page não descubra o contrato em produção.
 *
 * ⛔ ESCOPO — esta suíte MEDE, não corrige. Onde um UC sai vermelho, o `❌` é o achado
 * com recibo; a correção é decisão [W].
 *
 * O QUE O CHARTER MANDA DEFENDER (`Edit.charter.md`)
 * --------------------------------------------------
 *   Automation Anti-hooks:
 *     ❌ NÃO UPDATE direto current_stage_id              → UC-JSE-01
 *   Non-Goals:
 *     ❌ Editar `current_stage_id` (FSM via Show panel)  → UC-JSE-01
 *   Automation Hooks:
 *     `business_id` scope                                → UC-JSE-02
 *     Permission `job_sheet.edit`                        → UC-JSE-05
 *
 * O anti-hook do estágio é Tier 0 (ADR 0143): quem muda estágio é o
 * `ExecuteStageActionService`, e a trait `GuardsFsmTransitions` bloqueia UPDATE direto.
 * UC-JSE-01 prova que ESTA rota não é um caminho alternativo para aquilo.
 *
 * ORDEM DE FONTE: charter + RUNBOOK-jobsheet-edit.md + o controller real. NUNCA o
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
 * @see resources/js/Pages/Repair/JobSheet/Edit.casos.md
 * @see memory/requisitos/Repair/RUNBOOK-jobsheet-edit.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

beforeEach(function () {
    if (($motivo = Fx::motivoDeSkip()) !== null) {
        $this->markTestSkipped($motivo);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSE-01 — a edição NÃO é caminho para mudar estágio (Tier 0, ADR 0143)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSE-01: salvar a edição não move o estágio FSM da OS', function () {
    if (! Schema::hasColumn('repair_job_sheets', 'current_stage_id')) {
        $this->markTestSkipped('Coluna current_stage_id ausente — FSM (ADR 0143) não migrado neste banco.');
    }

    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);

    $estagioAntes = DB::table('repair_job_sheets')->where('id', $osId)->value('current_stage_id');

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $status,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-EDIT',
        // O formulário insiste no estágio: quem transiciona é o FSM, não esta rota.
        'current_stage_id' => 999999,
    ]);

    $estagioDepois = DB::table('repair_job_sheets')->where('id', $osId)->value('current_stage_id');

    expect($estagioDepois)->toBe($estagioAntes);

    // E o resto do formulário FOI salvo — senão o verde acima seria por nada ter passado.
    expect((string) JobSheet::find($osId)->serial_no)->toBe('SN-EDIT');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSE-02 — OS de outro negócio é invisível (Tier 0, ADR 0093)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSE-02: não lê nem altera OS de outro negócio', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $contatoVizinho = Fx::cliente((int) $vizinho->id, (int) $donoVizinho->id);
    $statusVizinho = Fx::status((int) $vizinho->id);
    $osAlheia = Fx::os((int) $vizinho->id, $contatoVizinho, $statusVizinho, (int) $donoVizinho->id, [
        'serial_no' => 'SN-DO-VIZINHO',
    ]);

    // Leitura: a edição do vizinho não abre.
    $this->actingAs($user)->get("/repair/job-sheet/{$osAlheia}/edit")->assertNotFound();

    // Escrita: seja qual for o código de resposta (ver o UC-JSE-02 de resposta, abaixo),
    // o DADO do vizinho não pode se mexer. Esta é a metade que protege o outro negócio.
    $this->actingAs($user)->put("/repair/job-sheet/{$osAlheia}", [
        'contact_id' => $contatoVizinho,
        'serial_no' => 'INVADIDO',
    ]);

    $intacta = DB::table('repair_job_sheets')->where('id', $osAlheia)->value('serial_no');
    expect((string) $intacta)->toBe('SN-DO-VIZINHO');
});

it('UC-JSE-02: a tentativa de editar OS de outro negócio responde 404', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $donoVizinho = Fx::usuario((int) $vizinho->id, false);
    $contatoVizinho = Fx::cliente((int) $vizinho->id, (int) $donoVizinho->id);
    $statusVizinho = Fx::status((int) $vizinho->id);
    $osAlheia = Fx::os((int) $vizinho->id, $contatoVizinho, $statusVizinho, (int) $donoVizinho->id);

    // ⚠️ ACHADO ABERTO (medido no CT 100 em 2026-09-05): esta asserção FALHA hoje com
    // "302 is identical to 404". O `update` envolve o `findOrFail` num try/catch que engole
    // a ModelNotFoundException e cai no `redirect()->back()` com "algo deu errado" — a
    // mesma família do achado em `saveParts`, que ali produz 500 em vez de 302.
    //
    // Fica VERMELHO de propósito. O dado do vizinho está protegido (o UC-JSE-02 acima
    // prova isso, verde); o que quebra é o contrato da RESPOSTA — um recurso de outro
    // negócio precisa ser indistinguível de inexistente, e um 302 com mensagem genérica
    // confirma que o id existe. A correção é decisão [W].
    $this->actingAs($user)->put("/repair/job-sheet/{$osAlheia}", [
        'contact_id' => $contatoVizinho,
        'serial_no' => 'INVADIDO',
    ])->assertNotFound();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSE-03 — o cliente só é avisado quando o status REALMENTE muda
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSE-03: mudar o status da OS anuncia a mudança', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $statusInicial = Fx::status((int) $biz->id, 'Recebido');
    $statusNovo = Fx::status((int) $biz->id, 'Pronto para retirada');
    $osId = Fx::os((int) $biz->id, $contato, $statusInicial, (int) $user->id);

    Event::fake([RepairStatusChanged::class]);

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $statusNovo,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-EDIT',
    ]);

    Event::assertDispatched(RepairStatusChanged::class);
});

it('UC-JSE-03: salvar sem mexer no status não anuncia nada ao cliente', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id, 'Recebido');
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);

    Event::fake([RepairStatusChanged::class]);

    // Só corrige um dado do aparelho; o status é o MESMO.
    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $status,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-CORRIGIDO',
    ]);

    // Reemitir a cada salvamento viraria spam para quem deixou o aparelho na loja.
    Event::assertNotDispatched(RepairStatusChanged::class);
    expect((string) JobSheet::find($osId)->serial_no)->toBe('SN-CORRIGIDO');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSE-04 — o checklist é substituído, não mesclado (contrato destrutivo)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSE-04: salvar sem enviar o checklist apaga o checklist da OS', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id, [
        'checklist' => json_encode(['Carregador', 'Capa protetora']),
    ]);

    expect(JobSheet::find($osId)->checklist)->toBe(['Carregador', 'Capa protetora']);

    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'status_id' => $status,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-EDIT',
        // `repair_checklist` ausente de propósito: é assim que a tela some com o dado.
    ]);

    // Contrato VIGENTE: ausência apaga. A Page precisa reenviar o conjunto completo a
    // cada submit — quem mandar só o que mudou perde o resto sem aviso.
    expect(JobSheet::find($osId)->checklist)->toBe([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSE-05 — sem permissão, a edição não existe
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSE-05: usuário sem job_sheet.edit recebe 403 na edição', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id, superadmin: false);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id, ['serial_no' => 'SN-ORIGINAL']);

    $this->actingAs($user)->get("/repair/job-sheet/{$osId}/edit")->assertForbidden();
    $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
        'contact_id' => $contato,
        'serial_no' => 'SN-ALTERADO',
    ])->assertForbidden();

    expect((string) JobSheet::find($osId)->serial_no)->toBe('SN-ORIGINAL');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSE-06 — custo estimado editado entra como número pt-BR (VALOR · REGRA MESTRE)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSE-06: o custo estimado editado em pt-BR é gravado pelo parser canônico', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);
    $osId = Fx::os((int) $biz->id, $contato, $status, (int) $user->id);
    $util = app(Util::class);

    // Mesma doutrina do UC-JSC-04: o aceite fixa a IDENTIDADE entre o que a tela grava
    // e o parser canônico — dois caminhos independentes (REGRA MESTRE, proibicoes.md).
    foreach (['1.234,56', '80,00', '250'] as $digitado) {
        $this->actingAs($user)->put("/repair/job-sheet/{$osId}", [
            'contact_id' => $contato,
            'status_id' => $status,
            'service_type' => 'carry_in',
            'serial_no' => 'SN-EDIT',
            'estimated_cost' => $digitado,
        ]);

        $gravado = (float) JobSheet::find($osId)->estimated_cost;
        $peloParser = (float) $util->num_uf($digitado);

        expect($gravado)->toBe($peloParser, "gravado != parser para a entrada {$digitado}");
        expect($gravado)->toBeLessThan(1000000.0, "entrada {$digitado} inflou o valor gravado");
    }
});
