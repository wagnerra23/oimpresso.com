<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Console\Commands\McpTasksUnassignedCommand;
use Throwable;

/**
 * FLAG de US NÃO ATRIBUÍDA no Daily Brief — ticket que nasce sem dono e ninguém cobra.
 *
 * Origem (2026-07-27, [W]): a doutrina *"toda entrada abre um ticket de backlog
 * para um dono, sempre"*. Medido no dia: o `triage` devolveu ≥50 US sem owner
 * (teto da consulta), incluindo US-AUDIT-001..010 que já estão `done` — trabalho
 * inteiro que nunca teve dono. A própria US-INFRA-043, que criou a sentinela
 * contra isso, tem `owner: —`.
 *
 * Por que nenhuma máquina cobrava: a sentinela `mcp:tasks:unassigned` EXISTE desde
 * 2026-06-24 (#3302), com Pest e com `--json` escrito explicitamente "pro Daily
 * Brief" — mas a acceptance #2 da US-INFRA-043 nunca foi ligada. Varredura contada
 * (`git grep "tasks:unassigned"`): 14 ocorrências, 7 arquivos, ZERO invocadores —
 * nada em Kernel, workflow, package.json ou .claude. Máquina órfã: existe, morde
 * em teste, e não roda em lugar nenhum. Isso é bug, não neutralidade
 * (memory/proibicoes.md §"Sempre fazer" — LIGUE A MÁQUINA, 2026-07-26).
 *
 * Distinção do irmão que JÁ roda (não duplica régua — §5): `mcp:tasks:health-check`
 * (Kernel 06:20) mede STALENESS (tempo sem movimento: todo >21d, blocked >30d).
 * Esta mede ATRIBUIÇÃO (falta de owner/cycle). Eixos diferentes: uma task pode ser
 * recém-criada (fresca pro health-check) e órfã desde o nascimento.
 *
 * Fonte-única: NÃO reimplementa a regra em PHP — chama
 * `McpTasksUnassignedCommand::detectarNaoAtribuidas()`, o método público que o
 * próprio autor deixou "testável sem console parsing". Espelha o que
 * `TasksHealthTool` faz com `scanStaleness()`.
 *
 * Determinística (pós-LLM): `inject()` roda DEPOIS do Brain B gerar o markdown —
 * o modelo nunca inventa esses números.
 *
 * Degrada graciosamente (brief NUNCA quebra por causa dela):
 *  - tabela `mcp_tasks` ausente / query falha → null (sem linha)
 *  - 0 não-atribuídas → null (flag só existe quando há o que reportar)
 * Kill-switch: `jana.tasks_sem_dono_brief_line` false → no-op (default ON).
 *
 * @see Modules/Jana/Console/Commands/McpTasksUnassignedCommand.php (a regra · US-INFRA-043)
 * @see Modules/Governance/Services/ObraParadaBriefLineService.php (pattern irmão)
 * @see Modules/Forja/Console/Commands/GenerateBriefCommand.php (plug-point inject)
 */
final class TasksSemDonoBriefLineService
{
    /**
     * Injeta a flag como bullet da seção `## FLAGS`.
     * Best-effort: qualquer falha (ou linha null) devolve o conteúdo intacto.
     */
    public function inject(string $content): string
    {
        if (! (bool) config('jana.tasks_sem_dono_brief_line', true)) {
            return $content;
        }

        try {
            $line = $this->line();
        } catch (Throwable) {
            return $content;
        }

        return $this->injetarEm($content, $line);
    }

    /**
     * Núcleo PURO da injeção (sem DB, sem config) — põe `$line` como 1º bullet de
     * `## FLAGS`. `null` devolve o conteúdo intacto.
     *
     * Público porque é o que os testes exercitam: `mcp_tasks` é tabela compartilhada
     * (no CT 100 os testes rodam contra MySQL real), então assertar contagem exata
     * vinda do banco seria não-determinístico. Separar o núcleo puro dá teste
     * hermético — mesma ideia do `--selftest` dos irmãos em Node.
     */
    public function injetarEm(string $content, ?string $line): string
    {
        if ($line === null) {
            return $content;
        }

        $injected = preg_replace('/^## FLAGS$/m', "## FLAGS\n- {$line}", $content, 1, $count);

        return ($count === 1 && is_string($injected)) ? $injected : $content;
    }

    /**
     * Flag de US não atribuída, ou null quando não há nada a reportar.
     *
     * Formato: `🟠 US não atribuída: 52 (47 sem dono) — mais antiga: US-COM-012 (24d)`.
     * Cita a MAIS ANTIGA com id e idade: número solto não diz por onde começar, e
     * flag que não aponta o alvo vira ruído que se aprende a ignorar (lição do
     * ObraParadaBriefLineService).
     *
     * `total` e `sem dono` se sobrepõem de propósito — um item pode faltar owner E
     * cycle. Por isso o formato NÃO soma as parcelas (somar mentiria).
     */
    public function line(): ?string
    {
        return $this->formatar($this->itens());
    }

    /**
     * Núcleo PURO da formatação (sem DB, sem config): recebe as não-atribuídas e
     * devolve o bullet, ou null quando não há o que reportar. Ver `injetarEm()`
     * sobre por que o núcleo é público.
     *
     * @param  list<array<string, mixed>>|null  $itens
     */
    public function formatar(?array $itens): ?string
    {
        if ($itens === null || $itens === []) {
            return null;
        }

        $total = count($itens);
        $semDono = count(array_filter($itens, static fn ($i) => ($i['owner'] ?? null) === null));

        $base = sprintf('🟠 US não atribuída: %d (%d sem dono)', $total, $semDono);
        $maisAntiga = $this->maisAntiga($itens);

        return $maisAntiga === null ? $base : $base.' — mais antiga: '.$maisAntiga;
    }

    /**
     * `<task_id> (<N>d)` do item mais velho, ou null se nenhum tiver data legível.
     *
     * A sentinela ordena por `task_id` (não por idade), então a mais antiga é
     * calculada aqui — assumir que o primeiro item é o pior daria o alvo errado.
     *
     * @param  list<array<string, mixed>>  $itens
     */
    private function maisAntiga(array $itens): ?string
    {
        $hoje = Carbon::today();
        $pior = null;
        $piorDias = -1;

        foreach ($itens as $item) {
            $criadaEm = $item['created_at'] ?? null;

            if (! is_string($criadaEm) || $criadaEm === '') {
                continue;
            }

            try {
                $dias = (int) Carbon::parse($criadaEm)->startOfDay()->diffInDays($hoje);
            } catch (Throwable) {
                continue;
            }

            if ($dias > $piorDias) {
                $piorDias = $dias;
                $pior = (string) ($item['task_id'] ?? '?');
            }
        }

        return $pior === null ? null : sprintf('%s (%dd)', $pior, $piorDias);
    }

    /**
     * Lista das não-atribuídas via a fonte-única da regra, ou null se a consulta
     * falhar (tabela ausente em instalação nova, DB fora do ar).
     *
     * @return list<array<string, mixed>>|null
     */
    private function itens(): ?array
    {
        try {
            return app(McpTasksUnassignedCommand::class)->detectarNaoAtribuidas();
        } catch (Throwable $e) {
            Log::debug('[tasks-sem-dono brief line] consulta falhou: '.$e->getMessage());

            return null;
        }
    }
}
