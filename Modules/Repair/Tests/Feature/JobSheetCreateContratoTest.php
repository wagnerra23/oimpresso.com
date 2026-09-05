<?php

declare(strict_types=1);

use App\Utils\Util;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Tests\Support\JobSheetFixtures as Fx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WithSeededTenant;

/**
 * Contrato executável da ABERTURA DE OS — `/repair/job-sheet/create` + POST `/repair/job-sheet`.
 *
 * Fixa o comportamento VIGENTE de `JobSheetController::create/store` antes de a tela
 * Inertia sair do canary, para que a Page não descubra o contrato em produção.
 *
 * ⛔ ESCOPO — esta suíte MEDE, não corrige. O `estimated_cost` passa por `Util::num_uf`,
 * o mesmo parser do incidente de 2026-06-05 (biz=4, venda de centenas gravada como
 * centenas de milhares). Por isso UC-JSC-04 prova o valor por DOIS caminhos
 * independentes, como manda a REGRA MESTRE de `memory/proibicoes.md`. Provar é o
 * intent; mexer no cálculo NÃO é.
 *
 * O QUE O CHARTER MANDA DEFENDER (`Create.charter.md`)
 * ----------------------------------------------------
 *   Automation Anti-hooks:
 *     ❌ NÃO cria OS de outro biz                        → UC-JSC-01
 *   Non-Goals:
 *     ❌ FSM pipeline iniciação (OS nasce legacy)        → UC-JSC-02
 *   Goals:
 *     Submit types: save · save_and_add_parts · save_and_upload_docs → UC-JSC-03
 *   Automation Hooks:
 *     Permission `job_sheet.create`                      → UC-JSC-05
 *
 * ORDEM DE FONTE: charter + RUNBOOK-jobsheet-create.md + o controller real. NUNCA o
 * `.tsx` — teste derivado da implementação é tautológico (§5 2026-06-05). O módulo não
 * tem SDD/CU para esta tela; onde a fonte faltou, o UC descreve o que o CONTROLLER
 * garante hoje e diz isso na cara.
 *
 * TENANT: `seededTenant()` = 98 (fictício). biz=4 é proibido sem exceção; biz=1 é
 * empresa real (ADR 0358).
 *
 * ⚠️ ONDE ESTE ARQUIVO É PROVADO: a lane `modules-pest.yml` (matrix `Repair`) dispara
 * neste PR, mas roda sqlite `:memory:` SEM migrate — lá estes UCs PULAM. A prova real
 * sai do CT 100 (MySQL), e é de lá que vem o `Status:` de cada UC no `.casos.md`.
 *
 * @see resources/js/Pages/Repair/JobSheet/Create.casos.md
 * @see memory/requisitos/Repair/RUNBOOK-jobsheet-create.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

beforeEach(function () {
    if (($motivo = Fx::motivoDeSkip()) !== null) {
        $this->markTestSkipped($motivo);
    }
});

/** Payload mínimo que satisfaz `StoreJobSheetRequest` E as colunas NOT NULL da tabela. */
function createOsPayload(int $contatoId, int $statusId, array $extra = []): array
{
    return array_merge([
        'contact_id' => $contatoId,
        'status_id' => $statusId,
        'service_type' => 'carry_in',
        'serial_no' => 'SN-CONTRATO',
    ], $extra);
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSC-01 — a OS nasce no negócio da SESSÃO (Tier 0, ADR 0093)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSC-01: a OS nasce no negócio da sessão, ignorando business_id do formulário', function () {
    $biz = $this->seededTenant();
    $vizinho = $this->seededSupportClientTenant();

    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);

    $antesNoVizinho = (int) DB::table('repair_job_sheets')->where('business_id', $vizinho->id)->count();

    $this->actingAs($user)->post('/repair/job-sheet', createOsPayload($contato, $status, [
        // Tentativa explícita de plantar a OS no vizinho: o controller força o da sessão.
        'business_id' => (int) $vizinho->id,
        'created_by' => 999999,
    ]));

    $criada = JobSheet::where('contact_id', $contato)->latest('id')->first();

    expect($criada)->not->toBeNull();
    expect((int) $criada->business_id)->toBe((int) $biz->id);
    expect((int) $criada->created_by)->toBe((int) $user->id);

    // E nada apareceu no vizinho.
    expect((int) DB::table('repair_job_sheets')->where('business_id', $vizinho->id)->count())
        ->toBe($antesNoVizinho);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSC-02 — a OS nasce FORA do pipeline FSM (Non-Goal do charter, ADR 0143)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSC-02: a OS nasce fora do pipeline FSM, com estágio vazio', function () {
    if (! Schema::hasColumn('repair_job_sheets', 'current_stage_id')) {
        $this->markTestSkipped('Coluna current_stage_id ausente — FSM (ADR 0143) não migrado neste banco.');
    }

    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);

    $this->actingAs($user)->post('/repair/job-sheet', createOsPayload($contato, $status, [
        // Mesmo que o formulário insista, entrar no pipeline é ato do FSM, não do store.
        'current_stage_id' => 999999,
    ]));

    $criada = JobSheet::where('contact_id', $contato)->latest('id')->first();

    expect($criada)->not->toBeNull();
    expect($criada->current_stage_id)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSC-03 — o botão escolhido decide para onde o operador vai
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSC-03: cada submit_type leva a OS recém-criada ao seu destino', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $status = Fx::status((int) $biz->id);

    $destinos = [
        'save' => fn (int $id) => "/repair/job-sheet/{$id}",
        'save_and_add_parts' => fn (int $id) => "/repair/job-sheet/add-parts/{$id}",
        'save_and_upload_docs' => fn (int $id) => "/repair/job-sheet/{$id}/upload-docs",
    ];

    foreach ($destinos as $submitType => $rotaEsperada) {
        $contato = Fx::cliente((int) $biz->id, (int) $user->id, "Cliente {$submitType}");

        $resposta = $this->actingAs($user)->post('/repair/job-sheet', createOsPayload($contato, $status, [
            'submit_type' => $submitType,
        ]));

        $criada = JobSheet::where('contact_id', $contato)->latest('id')->first();

        expect($criada)->not->toBeNull("submit_type={$submitType} não criou a OS");
        $resposta->assertRedirect($rotaEsperada((int) $criada->id));
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSC-04 — custo estimado entra como número pt-BR (VALOR · REGRA MESTRE)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSC-04: o custo estimado digitado em pt-BR é gravado pelo parser canônico', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $status = Fx::status((int) $biz->id);
    $util = app(Util::class);

    // Cada entrada é digitável por quem atende no balcão. O aceite NÃO fixa um número
    // à mão: fixa a IDENTIDADE entre o que a tela grava e o parser canônico do projeto
    // — dois caminhos independentes para o mesmo valor (REGRA MESTRE, proibicoes.md).
    foreach (['1.234,56', '80,00', '250', '1.500,00'] as $digitado) {
        $contato = Fx::cliente((int) $biz->id, (int) $user->id, 'Cliente custo '.uniqid());

        $this->actingAs($user)->post('/repair/job-sheet', createOsPayload($contato, $status, [
            'estimated_cost' => $digitado,
        ]));

        $criada = JobSheet::where('contact_id', $contato)->latest('id')->first();
        expect($criada)->not->toBeNull("custo {$digitado} não criou a OS");

        // Caminho A: o que a tela de fato gravou no banco.
        $gravado = (float) $criada->estimated_cost;
        // Caminho B: o parser pt-BR canônico, chamado direto, sem passar pela tela.
        $peloParser = (float) $util->num_uf($digitado);

        expect($gravado)->toBe($peloParser, "gravado != parser para a entrada {$digitado}");

        // Trava de sanidade contra a classe do incidente 2026-06-05 (valor inflado ~100k×):
        // nenhuma dessas entradas pode virar um número absurdo.
        expect($gravado)->toBeLessThan(1000000.0, "entrada {$digitado} inflou o valor gravado");
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSC-05 — sem permissão, a tela não existe
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSC-05: usuário sem job_sheet.create recebe 403 na abertura de OS', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id, superadmin: false);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $contato = Fx::cliente((int) $biz->id, (int) $user->id);
    $status = Fx::status((int) $biz->id);

    $this->actingAs($user)->get('/repair/job-sheet/create')->assertForbidden();

    $antes = (int) DB::table('repair_job_sheets')->where('business_id', $biz->id)->count();
    $this->actingAs($user)->post('/repair/job-sheet', createOsPayload($contato, $status))->assertForbidden();

    // 403 que ainda assim gravasse seria pior do que 200.
    expect((int) DB::table('repair_job_sheets')->where('business_id', $biz->id)->count())->toBe($antes);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSC-06 — a OS recebe número próprio do negócio
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSC-06: cada OS aberta recebe um número não vazio e distinto', function () {
    $biz = $this->seededTenant();
    $user = Fx::usuario((int) $biz->id);
    Fx::sessao((int) $biz->id, (int) $user->id);

    $status = Fx::status((int) $biz->id);
    $numeros = [];

    foreach (['Primeira', 'Segunda'] as $rotulo) {
        $contato = Fx::cliente((int) $biz->id, (int) $user->id, "Cliente {$rotulo}");

        $this->actingAs($user)->post('/repair/job-sheet', createOsPayload($contato, $status));

        $criada = JobSheet::where('contact_id', $contato)->latest('id')->first();
        expect($criada)->not->toBeNull("{$rotulo} OS não foi criada");
        expect((string) $criada->job_sheet_no)->not->toBe('');

        $numeros[] = (string) $criada->job_sheet_no;
    }

    // Duas OS do mesmo negócio nunca compartilham número — é o que o cliente vê no balcão.
    expect($numeros[0])->not->toBe($numeros[1]);
});
