<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Modules\Jana\Console\Commands\HealthCheckCommand;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

uses(\Tests\TestCase::class);

/**
 * Sentinela `mcp_index_sync_gap` (handoff 2026-07-05-0130 next_step #3 —
 * "máquina anti-apodrecimento: sentinela 'doc canônico ausente do índice'").
 *
 * CONTRATO (âncora): o sync gap dos BRIEFINGs (doc canônico no git, AUSENTE do
 * índice mcp_memory_documents) ficou invisível por semanas e custou -11pp de
 * recall@5. O check compara os slugs que a MESMA coleta do sync derivaria agora
 * (IndexarMemoryGitParaDb::slugsEsperados) contra os vivos na tabela — fonte
 * única: glob novo no sync = sentinela cobre junto, sem lista paralela.
 * Anti-padrão caçado: "a suíte mente" (auditoria de sentinelas 2026-06-20) —
 * monitor que mede qualidade DENTRO do índice mas nunca a AUSÊNCIA.
 *
 * Cobertura (lógica pura + wiring, sem DB — molde SentinelBiteTest):
 *   (a) indexSyncGapStats detecta doc esperado fora do índice
 *   (b) zero ausentes quando índice cobre tudo (verde não é constante — bite)
 *   (c) doc EXTRA no índice não é gap (só ausência alarma)
 *   (d) slugsEsperados deriva da mesma coleta do sync (fixture com BRIEFING)
 */

it('(a) detecta doc canônico esperado fora do índice', function () {
    $esperados = ['briefing:Financeiro', 'briefing:Jana', 'spec-jana'];
    $vivos = ['spec-jana']; // os 2 BRIEFINGs sumiram — a classe do incidente

    $r = HealthCheckCommand::indexSyncGapStats($esperados, $vivos);

    expect($r['esperados'])->toBe(3);
    expect($r['ausentes'])->toBe(['briefing:Financeiro', 'briefing:Jana']);
});

it('(b) zero ausentes quando o índice cobre tudo — o verde responde ao estado', function () {
    $esperados = ['briefing:Financeiro', 'spec-jana'];

    $ok = HealthCheckCommand::indexSyncGapStats($esperados, $esperados);
    expect($ok['ausentes'])->toBe([]);

    // Bite: o MESMO input menos 1 doc vira alarme — prova que o verde não é constante.
    $bite = HealthCheckCommand::indexSyncGapStats($esperados, ['spec-jana']);
    expect($bite['ausentes'])->toBe(['briefing:Financeiro']);
});

it('(c) doc extra no índice não é gap — só ausência alarma', function () {
    $r = HealthCheckCommand::indexSyncGapStats(
        ['spec-jana'],
        ['spec-jana', 'session-doc-orfao-que-sumiu-do-git'],
    );

    // O soft-delete de órfãos é papel do sync completo, não da sentinela.
    expect($r['ausentes'])->toBe([]);
});

it('(d) slugsEsperados deriva da MESMA coleta do sync (fonte única, sem lista paralela)', function () {
    $tmpBase = storage_path('app/test-sentinela-gap-' . uniqid());
    @mkdir($tmpBase . '/memory/requisitos/Financeiro', 0777, true);
    @mkdir($tmpBase . '/memory/sessions', 0777, true);
    file_put_contents($tmpBase . '/memory/requisitos/Financeiro/BRIEFING.md', "# Briefing\n\nEstado.\n");
    file_put_contents($tmpBase . '/memory/sessions/2026-07-05-gap.md', "# Session\n\nTexto.\n");

    try {
        $slugs = (new IndexarMemoryGitParaDb($tmpBase, 'test-sentinela'))->slugsEsperados();

        // O slug do BRIEFING (o doc do incidente) tem que estar no esperado —
        // se um glob for removido do sync, este teste + a sentinela caem juntos.
        expect($slugs)->toContain('briefing:Financeiro');
        expect($slugs)->toContain('session-2026-07-05-gap');
    } finally {
        @unlink($tmpBase . '/memory/requisitos/Financeiro/BRIEFING.md');
        @unlink($tmpBase . '/memory/sessions/2026-07-05-gap.md');
        @rmdir($tmpBase . '/memory/requisitos/Financeiro');
        @rmdir($tmpBase . '/memory/requisitos');
        @rmdir($tmpBase . '/memory/sessions');
        @rmdir($tmpBase . '/memory');
        @rmdir($tmpBase);
    }
});
