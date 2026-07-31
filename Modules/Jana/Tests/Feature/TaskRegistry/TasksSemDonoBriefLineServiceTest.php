<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Jana\Services\TasksSemDonoBriefLineService;

// EXPLÍCITO (não confiar só no Pest.php do módulo): quando ci.yml roda este arquivo
// direto via .github/ci-sqlite-pest.list, o `uses(...)->in()` pode não casar → sem
// TestCase, facades vazam estado. Espelha ObraParadaBriefLineServiceTest.
uses(Tests\TestCase::class);

/**
 * Tests da FLAG de US NÃO ATRIBUÍDA no Daily Brief (US-INFRA-043 acceptance #2).
 *
 * Exercitam os núcleos PUROS (`formatar()` / `injetarEm()`), não o banco: `mcp_tasks`
 * é tabela compartilhada e no CT 100 os testes rodam contra MySQL real — assertar
 * contagem exata vinda de lá seria não-determinístico (e limpar a tabela, destrutivo).
 * A regra de detecção tem teste próprio em McpTasksUnassignedCommandTest.
 *
 * @see Modules/Jana/Services/TasksSemDonoBriefLineService.php
 * @see Modules/Jana/Console/Commands/McpTasksUnassignedCommand.php (a regra)
 * @see Modules/Forja/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */

beforeEach(function () {
    // Congela "hoje" — a idade da mais antiga é calculada contra a data corrente.
    Carbon::setTestNow(Carbon::parse('2026-07-27'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Item no shape que `detectarNaoAtribuidas()` devolve. */
function semDonoItem(string $taskId, ?string $owner, ?string $criadaEm): array
{
    return [
        'task_id' => $taskId,
        'module' => 'Teste',
        'owner' => $owner,
        'cycle_id' => null,
        'falta' => $owner === null ? 'cycle+owner' : 'cycle',
        'created_at' => $criadaEm,
        'title' => $taskId,
    ];
}

/** Brief mínimo válido (7 seções + ---END---) pra exercitar a injeção. */
function semDonoBrief(): string
{
    return "## ESTADO MACRO\n- x\n\n## EM VOO AGORA\n- x\n\n## DECISÕES RECENTES (24h)\n- x\n\n"
        ."## SKILLS USO 7d\n- x\n\n## CHARTERS APODRECENDO\n—\n\n## FLAGS\n- 🟢 Migration aging: ok\n\n"
        ."## METADATA\n- Gerado: hoje\n---END---";
}

it('conta o total e quantas estão sem dono, e nomeia a MAIS ANTIGA', function () {
    $linha = (new TasksSemDonoBriefLineService())->formatar([
        semDonoItem('US-COM-012', null, '2026-07-03'),
        semDonoItem('US-FIN-065', null, '2026-07-20'),
        semDonoItem('US-GOV-047', 'wagner', '2026-07-25'),  // tem dono, falta cycle
    ]);

    expect($linha)->toBe('🟠 US não atribuída: 3 (2 sem dono) — mais antiga: US-COM-012 (24d)');
});

it('NÃO soma as parcelas — item pode faltar owner E cycle ao mesmo tempo', function () {
    // 2 itens, ambos sem owner: "2 (2 sem dono)" — nunca "4".
    $linha = (new TasksSemDonoBriefLineService())->formatar([
        semDonoItem('US-A-001', null, '2026-07-26'),
        semDonoItem('US-A-002', null, '2026-07-26'),
    ]);

    expect($linha)->toBe('🟠 US não atribuída: 2 (2 sem dono) — mais antiga: US-A-001 (1d)');
});

it('a mais antiga vem da DATA, não da ordem da lista (a sentinela ordena por task_id)', function () {
    // task_id crescente, mas a mais VELHA é a última — pegar a [0] daria o alvo errado.
    $linha = (new TasksSemDonoBriefLineService())->formatar([
        semDonoItem('US-A-001', null, '2026-07-25'),
        semDonoItem('US-Z-999', null, '2026-06-01'),
    ]);

    expect($linha)->toContain('mais antiga: US-Z-999 (56d)');
});

it('sem nenhuma data legível → reporta as contagens e OMITE a mais antiga (não inventa)', function () {
    $linha = (new TasksSemDonoBriefLineService())->formatar([
        semDonoItem('US-A-001', null, null),
    ]);

    expect($linha)->toBe('🟠 US não atribuída: 1 (1 sem dono)');
});

it('lista vazia → null (flag só existe quando há o que reportar)', function () {
    expect((new TasksSemDonoBriefLineService())->formatar([]))->toBeNull();
});

it('consulta falhou (null) → null, nunca uma flag com zero', function () {
    expect((new TasksSemDonoBriefLineService())->formatar(null))->toBeNull();
});

it('injetarEm() põe a flag como 1º bullet de ## FLAGS e preserva o resto do brief', function () {
    $out = (new TasksSemDonoBriefLineService())
        ->injetarEm(semDonoBrief(), '🟠 US não atribuída: 3 (2 sem dono)');

    expect($out)->toContain('🟠 US não atribuída: 3 (2 sem dono)')
        ->and($out)->toContain('🟢 Migration aging: ok')   // não come o bullet que já existia
        ->and($out)->toContain('---END---')                 // marcador final intacto
        ->and(substr_count($out, '## FLAGS'))->toBe(1);     // não duplica a seção
});

it('injetarEm() com linha null devolve o brief INTACTO (brief nunca quebra por causa da flag)', function () {
    $brief = semDonoBrief();

    expect((new TasksSemDonoBriefLineService())->injetarEm($brief, null))->toBe($brief);
});

it('brief SEM a seção ## FLAGS sai intacto (não inventa seção)', function () {
    $semFlags = "## ESTADO MACRO\n- x\n\n## METADATA\n- y\n---END---";

    expect((new TasksSemDonoBriefLineService())->injetarEm($semFlags, '🟠 US não atribuída: 1 (1 sem dono)'))
        ->toBe($semFlags);
});

it('kill-switch OFF → inject() é no-op e nem consulta o banco', function () {
    config()->set('jana.tasks_sem_dono_brief_line', false);

    $brief = semDonoBrief();

    expect((new TasksSemDonoBriefLineService())->inject($brief))->toBe($brief);
});
