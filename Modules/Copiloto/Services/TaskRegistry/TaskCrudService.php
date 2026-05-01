<?php

declare(strict_types=1);

namespace Modules\Copiloto\Services\TaskRegistry;

use Modules\Copiloto\Entities\Mcp\McpTask;
use Modules\Copiloto\Entities\Mcp\McpTaskComment;
use Modules\Copiloto\Entities\Mcp\McpTaskEvent;

/**
 * TaskRegistry Fase 1 (US-TR-005) — operações de escrita nas tasks.
 *
 * Todas as escritas são DB-only (exceto tasks-create que também toca SPEC.md).
 * Source-of-truth permanece o git — o DB é cache governado.
 */
class TaskCrudService
{
    /**
     * Atualiza campos de uma task e registra evento de audit.
     *
     * @param  array<string,mixed> $fields  Campos permitidos: status, owner, sprint, priority
     * @return array{task: McpTask, events: list<McpTaskEvent>}
     * @throws \RuntimeException se task não encontrada ou campo inválido
     */
    public function update(string $taskId, array $fields, string $author = 'system'): array
    {
        $task = McpTask::where('task_id', strtoupper($taskId))->first()
            ?? McpTask::where('task_id', $taskId)->first();

        if (! $task) {
            throw new \RuntimeException("Task '{$taskId}' não encontrada.");
        }

        $allowed = ['status', 'owner', 'sprint', 'priority'];
        $events  = [];

        foreach ($fields as $field => $newVal) {
            if (! in_array($field, $allowed, true)) {
                throw new \RuntimeException("Campo '{$field}' não pode ser atualizado via tasks-update. Permitidos: " . implode(', ', $allowed));
            }

            $oldVal = (string) ($task->$field ?? '');
            $newStr = $newVal === null ? '' : (string) $newVal;

            if ($oldVal === $newStr) {
                continue;
            }

            $eventType = match ($field) {
                'status'   => 'status_changed',
                'owner'    => 'assigned',
                default    => 'field_updated',
            };

            $task->$field = $newVal ?: null;

            $events[] = McpTaskEvent::log(
                taskId:    $task->task_id,
                eventType: $eventType,
                from:      $oldVal ?: null,
                to:        $newStr ?: null,
                author:    $author,
                note:      "Campo '{$field}' atualizado via tasks-update",
            );
        }

        $task->save();

        return ['task' => $task, 'events' => $events];
    }

    /**
     * Adiciona um comentário DB-only a uma task.
     */
    public function comment(string $taskId, string $body, string $author): McpTaskComment
    {
        $task = McpTask::where('task_id', strtoupper($taskId))->first()
            ?? McpTask::where('task_id', $taskId)->first();

        if (! $task) {
            throw new \RuntimeException("Task '{$taskId}' não encontrada.");
        }

        $comment = McpTaskComment::create([
            'task_id' => $task->task_id,
            'author'  => $author,
            'body'    => trim($body),
        ]);

        McpTaskEvent::log(
            taskId:    $task->task_id,
            eventType: 'commented',
            author:    $author,
            note:      mb_substr(trim($body), 0, 120),
        );

        return $comment;
    }

    /**
     * Cria nova US no SPEC.md do módulo e retorna o texto gerado.
     *
     * NÃO comita — retorna o bloco markdown para o caller decidir o que fazer.
     * O próximo webhook/sync vai pegar a mudança do arquivo.
     *
     * @param  array<string,mixed> $data  Campos: module, title, owner, sprint, priority, estimate_h, blocked_by, description
     * @return array{task_id: string, markdown: string, spec_path: string, written: bool}
     */
    public function create(array $data): array
    {
        $module = trim((string) ($data['module'] ?? ''));
        if ($module === '') {
            throw new \RuntimeException("Campo 'module' é obrigatório.");
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException("Campo 'title' é obrigatório.");
        }

        $specPath = base_path("memory/requisitos/{$module}/SPEC.md");
        if (! is_file($specPath)) {
            throw new \RuntimeException("SPEC.md não encontrado em memory/requisitos/{$module}/SPEC.md — verifique o nome do módulo.");
        }

        $taskId = $this->gerarProximoId($module);

        $owner     = $data['owner'] ?? null;
        $sprint    = $data['sprint'] ?? null;
        $priority  = in_array($data['priority'] ?? 'p2', ['p0', 'p1', 'p2', 'p3'], true)
                     ? ($data['priority'] ?? 'p2')
                     : 'p2';
        $estimate  = isset($data['estimate_h']) ? (float) $data['estimate_h'] : null;
        $blockedBy = $data['blocked_by'] ?? null;
        $desc      = trim((string) ($data['description'] ?? ''));

        // Monta a linha de frontmatter
        $fm  = "owner: " . ($owner ?? '—');
        $fm .= $sprint   ? " · sprint: {$sprint}"  : '';
        $fm .= " · priority: {$priority}";
        $fm .= $estimate ? " · estimate: {$estimate}h" : '';
        $fm .= " · status: todo";

        $blockedFm = $blockedBy ? implode(', ', (array) $blockedBy) : '—';

        $block = "\n### {$taskId} · {$title}\n\n";
        $block .= "> {$fm}\n";
        $block .= "> blocked_by: {$blockedFm}\n";
        if ($desc !== '') {
            $block .= "\n{$desc}\n";
        }

        // Append no SPEC.md
        $written = false;
        try {
            file_put_contents($specPath, $block, FILE_APPEND | LOCK_EX);
            $written = true;
        } catch (\Throwable) {
            // shared hosting pode negar escrita — retorna o markdown pra o usuário colar
        }

        McpTaskEvent::log(
            taskId:    $taskId,
            eventType: 'created',
            author:    $data['author'] ?? 'system',
            note:      "Criada via tasks-create (written={$written})",
        );

        return [
            'task_id'   => $taskId,
            'markdown'  => $block,
            'spec_path' => "memory/requisitos/{$module}/SPEC.md",
            'written'   => $written,
        ];
    }

    /**
     * Determina o próximo task_id disponível para o módulo.
     * Padrão: US-{MODULE}-{NNN} onde NNN é zero-padded 3 dígitos.
     */
    private function gerarProximoId(string $module): string
    {
        $prefixo = 'US-' . strtoupper($module) . '-';
        $ultimo  = McpTask::where('task_id', 'LIKE', $prefixo . '%')
            ->orderByRaw('CAST(SUBSTRING(task_id, ' . (strlen($prefixo) + 1) . ') AS UNSIGNED) DESC')
            ->value('task_id');

        $n = $ultimo ? ((int) substr($ultimo, strlen($prefixo)) + 1) : 1;
        return $prefixo . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
