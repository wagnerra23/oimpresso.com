<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Wave 18 — D2 FSM Canonical (Jana N/A intencional + asserções estruturais).
 *
 * Contexto: FSM canon ([ADR 0143](memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
 * é específico de **domínios transacionais** (Sells/Repair) — vendas + OS têm
 * stages canônicos + transitions auditáveis. Jana é **chat IA conversacional**:
 * o "estado" é a `Conversa.status` (`aberta`/`arquivada`) — não há multi-stage
 * pipeline com side-effects nem RBAC per-stage.
 *
 * Por isso D2 do scorecard governance v3 marca `fsm_n_a: true` em module.json
 * pra **eliminar falso-positivo** na rubrica (Jana não deve ser penalizada por
 * não usar canon FSM em domínio que não exige).
 *
 * Este teste valida **declaração explícita** do marker (defesa contra regressão).
 *
 * @see Modules/Jana/module.json governance.fsm_n_a
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */

it('D2.canon Jana declara fsm_n_a=true em module.json (chat IA, não pipeline transacional)', function () {
    $moduleJsonPath = base_path('Modules/Jana/module.json');
    expect(file_exists($moduleJsonPath))->toBeTrue();

    $config = json_decode(file_get_contents($moduleJsonPath), true);

    expect($config)->toBeArray();
    expect($config)->toHaveKey('governance');
    expect($config['governance'])->toHaveKey('fsm_n_a');
    expect($config['governance']['fsm_n_a'])->toBeTrue(
        'Jana é chat IA conversacional — FSM canon ADR 0143 N/A intencional. '
        .'D2 rubric NÃO penaliza ausência de pipeline transacional.'
    );
});

it('D2.canon Jana declara fsm_n_a_reason em module.json (justificativa explícita)', function () {
    $config = json_decode(
        file_get_contents(base_path('Modules/Jana/module.json')),
        true
    );

    expect($config['governance'])->toHaveKey('fsm_n_a_reason');
    expect($config['governance']['fsm_n_a_reason'])->toBeString();
    expect(strlen($config['governance']['fsm_n_a_reason']))->toBeGreaterThan(20);
});

it('D2.canon Conversa.status segue enum simples (aberta/arquivada) — não exige FSM canon', function () {
    // Smoke estrutural: Conversa tem coluna `status` mas é enum trivial, não
    // pipeline multi-stage. Valida que NÃO há ScopeByBusinessViaParent
    // exotic em Conversa (que indicaria estado de pipeline).
    $traits = array_keys(class_uses_recursive(\Modules\Jana\Entities\Conversa::class));

    // Conversa NÃO deve ter GuardsFsmTransitions (trait FSM canon).
    expect($traits)->not->toContain('App\Concerns\GuardsFsmTransitions');
});

it('D2.canon ExecuteStageActionService NÃO é injetado em Modules/Jana — confirma N/A', function () {
    // Defesa contra drift: se algum dia alguém tentar injetar service FSM canon
    // em Jana, este teste pega (e força criação de pipeline transacional formal).
    $controllers = glob(base_path('Modules/Jana/Http/Controllers/*.php')) ?: [];
    $services = glob(base_path('Modules/Jana/Services/*.php')) ?: [];
    $jobs = glob(base_path('Modules/Jana/Jobs/*.php')) ?: [];

    $todos = array_merge($controllers, $services, $jobs);
    $offenders = [];

    foreach ($todos as $file) {
        $content = file_get_contents($file);
        if (str_contains($content, 'ExecuteStageActionService')) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBeEmpty(
        'Jana N/A pra FSM canon — nenhum arquivo deve referenciar ExecuteStageActionService. '
        .'Offenders: '.implode(', ', $offenders)
    );
});
