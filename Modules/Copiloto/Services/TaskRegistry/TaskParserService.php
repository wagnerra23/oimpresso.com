<?php

namespace Modules\Copiloto\Services\TaskRegistry;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\Mcp\McpTask;

/**
 * TaskRegistry Fase 0 — parseia US-* de SPECs canônicos e sincroniza com mcp_tasks.
 *
 * Fonte: memory/requisitos/<Mod>/SPEC.md
 * Formato esperado de cada US:
 *   ### US-NFSE-001 · Pesquisa fiscal Tubarão
 *
 *   > owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo
 *   > blocked_by: —
 *
 *   - [ ] Confirmar SN-NFSe vs ABRASF
 *   - [ ] ...
 *
 * Idempotente: rodar 2x sem mudança = 0 inserts/updates.
 * US deletada do SPEC vira status=cancelled (soft) em vez de DELETE.
 */
class TaskParserService
{
    /** Regex pra heading de US: ###(#)? US-XXX-NNN[N] · Título */
    public const US_HEADING_REGEX = '/^#{2,4}\s+(US-[A-Z0-9]+-\d{3,4})\s*[·\-:]?\s*(.*)$/m';

    /**
     * Parser SPEC + sync DB. Retorna relatório por módulo.
     *
     * @return array{tasks_processadas:int, inseridas:int, atualizadas:int, canceladas:int, modulos:array<string,int>}
     */
    public function syncAll(?string $apenasModulo = null): array
    {
        $base = base_path('memory/requisitos');
        if (! is_dir($base)) {
            return $this->relatorio(0, 0, 0, 0, []);
        }

        $reportadasNoSync = [];
        $inseridas = 0;
        $atualizadas = 0;
        $modulos = [];

        $iterator = new \DirectoryIterator($base);
        foreach ($iterator as $modDir) {
            if ($modDir->isDot() || ! $modDir->isDir()) {
                continue;
            }
            $module = $modDir->getFilename();
            if ($apenasModulo !== null && $module !== $apenasModulo) {
                continue;
            }
            $specPath = $modDir->getPathname() . '/SPEC.md';
            if (! is_file($specPath)) {
                continue;
            }

            $candidatos = $this->parseSpec($specPath, $module);
            $modulos[$module] = $candidatos->count();

            foreach ($candidatos as $cand) {
                $reportadasNoSync[] = $cand['task_id'];
                $existente = McpTask::where('task_id', $cand['task_id'])->first();

                if ($existente === null) {
                    McpTask::create($cand);
                    $inseridas++;
                } elseif ($this->precisaAtualizar($existente, $cand)) {
                    $existente->update($cand);
                    $atualizadas++;
                }
            }
        }

        // Tasks que não apareceram no SPEC mais → cancelar (soft)
        $canceladas = 0;
        $query = McpTask::where('status', '!=', 'cancelled');
        if ($apenasModulo !== null) {
            $query->where('module', $apenasModulo);
        }
        if (! empty($reportadasNoSync)) {
            $query->whereNotIn('task_id', $reportadasNoSync);
        }
        $orfas = $query->get();
        foreach ($orfas as $orfa) {
            $orfa->update(['status' => 'cancelled', 'parsed_at' => now()]);
            $canceladas++;
        }

        Log::channel('copiloto-ai')->info('TaskRegistry sync', [
            'modulos' => $modulos,
            'inseridas' => $inseridas,
            'atualizadas' => $atualizadas,
            'canceladas' => $canceladas,
        ]);

        return $this->relatorio(count($reportadasNoSync), $inseridas, $atualizadas, $canceladas, $modulos);
    }

    /**
     * Parseia 1 arquivo SPEC.md e retorna candidatas (sem persistir).
     *
     * @return Collection<int, array>
     */
    public function parseSpec(string $path, ?string $modulo = null): Collection
    {
        if (! is_file($path)) {
            return collect();
        }
        $modulo ??= basename(dirname($path));

        $conteudo = file_get_contents($path);
        $sha = $this->headSha();

        // Encontra todos os headings de US
        preg_match_all(self::US_HEADING_REGEX, $conteudo, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[1])) {
            return collect();
        }

        $candidatos = collect();
        $count = count($matches[1]);

        for ($i = 0; $i < $count; $i++) {
            $taskId = trim($matches[1][$i][0]);
            $title  = trim($matches[2][$i][0]);
            $offsetInicio = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $offsetFim    = $i + 1 < $count
                ? $matches[0][$i + 1][1]
                : strlen($conteudo);
            $bloco = substr($conteudo, $offsetInicio, $offsetFim - $offsetInicio);

            $meta = $this->parseFrontmatterInline($bloco);
            $description = $this->extrairDescription($bloco);

            $candidatos->push([
                'task_id' => $taskId,
                'module' => $modulo,
                'title' => $title !== '' ? $title : $taskId,
                'description' => $description,
                'status' => $meta['status'] ?? 'todo',
                'owner' => $meta['owner'] ?? null,
                'sprint' => $meta['sprint'] ?? null,
                'priority' => $meta['priority'] ?? 'p2',
                'estimate_h' => $meta['estimate_h'] ?? null,
                'blocked_by' => $meta['blocked_by'] ?? null,
                'source_path' => 'memory/requisitos/' . $modulo . '/SPEC.md#' . $taskId,
                'source_git_sha' => $sha,
                'parsed_at' => now(),
            ]);
        }

        return $candidatos;
    }

    /**
     * Parseia linhas tipo:
     *   > owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: todo
     *   > blocked_by: US-NFSE-001
     */
    protected function parseFrontmatterInline(string $bloco): array
    {
        $meta = [];
        if (! preg_match_all('/^>\s*(.+)$/m', $bloco, $linhas)) {
            return $meta;
        }
        foreach ($linhas[1] as $linha) {
            $partes = preg_split('/\s*·\s*|\s*\|\s*/', $linha);
            foreach ($partes as $par) {
                if (! preg_match('/^([a-z_]+)\s*[:=]\s*(.+)$/i', trim($par), $m)) {
                    continue;
                }
                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
                if ($val === '—' || $val === '-' || $val === '') {
                    continue;
                }
                switch ($key) {
                    case 'owner':
                        $meta['owner'] = strtolower($val);
                        break;
                    case 'sprint':
                        $meta['sprint'] = $val;
                        break;
                    case 'priority':
                        $val = strtolower($val);
                        if (in_array($val, McpTask::PRIORITIES, true)) {
                            $meta['priority'] = $val;
                        }
                        break;
                    case 'status':
                        $val = strtolower($val);
                        if (in_array($val, McpTask::STATUSES, true)) {
                            $meta['status'] = $val;
                        }
                        break;
                    case 'estimate':
                    case 'estimate_h':
                    case 'estimativa':
                        if (preg_match('/(\d+(?:\.\d+)?)/', $val, $mm)) {
                            $meta['estimate_h'] = (float) $mm[1];
                        }
                        break;
                    case 'blocked_by':
                    case 'depends_on':
                        $ids = preg_split('/[\s,]+/', $val);
                        $ids = array_filter($ids, fn ($x) => preg_match('/^US-/i', $x));
                        if (! empty($ids)) {
                            $meta['blocked_by'] = array_values(array_map('strtoupper', $ids));
                        }
                        break;
                }
            }
        }
        return $meta;
    }

    /** Description = primeiro bloco não-frontmatter, máx 1000 chars. */
    protected function extrairDescription(string $bloco): string
    {
        $linhas = explode("\n", $bloco);
        $body = [];
        foreach ($linhas as $l) {
            $trimmed = trim($l);
            if ($trimmed === '' || str_starts_with($trimmed, '>')) {
                if (! empty($body)) {
                    continue; // tolera linha vazia depois do início
                }
                continue;
            }
            $body[] = $l;
            if (count($body) > 30) {
                break;
            }
        }
        return mb_substr(trim(implode("\n", $body)), 0, 1000);
    }

    protected function precisaAtualizar(McpTask $existente, array $cand): bool
    {
        foreach (['title', 'description', 'status', 'owner', 'sprint', 'priority', 'estimate_h', 'source_path'] as $campo) {
            if ((string) ($existente->{$campo} ?? '') !== (string) ($cand[$campo] ?? '')) {
                return true;
            }
        }
        $existBlocked = $existente->blocked_by ?? [];
        $candBlocked = $cand['blocked_by'] ?? [];
        if (json_encode($existBlocked) !== json_encode($candBlocked)) {
            return true;
        }
        return false;
    }

    protected function headSha(): ?string
    {
        // Lê HEAD do filesystem (funciona em shared hosting onde shell_exec é desabilitado)
        $headFile = base_path('.git/HEAD');
        if (! is_file($headFile)) {
            return null;
        }
        $head = trim((string) file_get_contents($headFile));
        if (str_starts_with($head, 'ref: ')) {
            $refPath = base_path('.git/' . substr($head, 5));
            if (is_file($refPath)) {
                return substr(trim((string) file_get_contents($refPath)), 0, 40);
            }
            return null;
        }
        // HEAD detached — o próprio conteúdo é o SHA
        return $head !== '' ? substr($head, 0, 40) : null;
    }

    protected function relatorio(int $processadas, int $ins, int $upd, int $can, array $modulos): array
    {
        return [
            'tasks_processadas' => $processadas,
            'inseridas' => $ins,
            'atualizadas' => $upd,
            'canceladas' => $can,
            'modulos' => $modulos,
        ];
    }
}
