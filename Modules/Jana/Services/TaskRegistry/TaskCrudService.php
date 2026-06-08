<?php

declare(strict_types=1);

namespace Modules\Jana\Services\TaskRegistry;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpComponent;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskComment;
use Modules\Jana\Entities\Mcp\McpTaskDependency;
use Modules\Jana\Entities\Mcp\McpTaskEvent;

/**
 * TaskRegistry CRUD (ADR 0070, supersedes ADR 0069 Fase 1).
 *
 * Source-of-truth = git (SPEC.md) pra US-XXX-NNN canônicas.
 * mcp_tasks aceita também tasks ad-hoc criadas direto via tasks-create
 * com identifier humano Linear-style (COPI-123).
 */
class TaskCrudService
{
    /**
     * Atualiza campos de uma task e registra evento de audit.
     *
     * Whitelist expandida (ADR 0070).
     *
     * @param  array<string,mixed> $fields
     * @return array{task: McpTask, events: list<McpTaskEvent>}
     */
    public function update(string $taskId, array $fields, string $author = 'system'): array
    {
        return OtelHelper::spanBiz('project_mgmt.task_crud.update', function () use ($taskId, $fields, $author): array {
            return $this->updateInner($taskId, $fields, $author);
        }, [
            'module'      => 'ProjectMgmt',
            'task_id'     => $taskId,
            'fields'      => implode(',', array_keys($fields)),
            'author'      => $author,
        ]);
    }

    /**
     * Inner update — assinatura preservada pra audit log de OTel não duplicar.
     *
     * @param  array<string,mixed> $fields
     * @return array{task: McpTask, events: list<McpTaskEvent>}
     */
    protected function updateInner(string $taskId, array $fields, string $author = 'system'): array
    {
        $task = $this->findTaskOrFail($taskId);

        $allowed = [
            'status', 'owner', 'sprint', 'priority',
            'epic_id', 'cycle_id', 'component_id', 'parent_task_id',
            'type', 'story_points', 'estimate_unit', 'estimate_value',
            'estimate_h', 'due_date', 'labels', 'custom_fields',
            'title', 'description', 'module',
        ];
        $events = [];

        foreach ($fields as $field => $newVal) {
            if (! in_array($field, $allowed, true)) {
                throw new \RuntimeException("Campo '{$field}' não pode ser atualizado via tasks-update. Permitidos: " . implode(', ', $allowed));
            }

            $oldVal = $this->stringValue($task->$field ?? null);
            $newStr = $this->stringValue($newVal);

            if ($oldVal === $newStr) {
                continue;
            }

            $eventType = match ($field) {
                'status' => 'status_changed',
                'owner' => 'assigned',
                default => 'field_updated',
            };

            // Side-effects: started_at / completed_at autopopulate
            if ($field === 'status') {
                if ($newVal === 'doing' && ! $task->started_at) {
                    $task->started_at = now();
                }
                if (in_array($newVal, ['done', 'cancelled'], true) && ! $task->completed_at) {
                    $task->completed_at = now();
                }
            }

            $task->$field = $newVal === '' ? null : $newVal;

            $events[] = McpTaskEvent::log(
                taskId: $task->task_id,
                eventType: $eventType,
                from: $oldVal ?: null,
                to: $newStr ?: null,
                author: $author,
                note: "Campo '{$field}' atualizado via tasks-update",
            );

            // Inbox notification pra owner se mudou
            if ($field === 'owner' && $newVal && $task->owner !== $oldVal) {
                $this->maybeNotifyAssignment($task, $author);
            }
            if ($field === 'status' && $newVal === 'review' && $task->owner) {
                $this->maybeNotifyReview($task, $author);
            }
        }

        $task->save();

        return ['task' => $task->fresh(), 'events' => $events];
    }

    /**
     * Adiciona um comentário DB-only a uma task.
     */
    public function comment(string $taskId, string $body, string $author): McpTaskComment
    {
        return OtelHelper::spanBiz('project_mgmt.task_crud.comment', function () use ($taskId, $body, $author): McpTaskComment {
            return $this->commentInner($taskId, $body, $author);
        }, [
            'module'      => 'ProjectMgmt',
            'task_id'     => $taskId,
            'author'      => $author,
            'body_length' => mb_strlen($body),
        ]);
    }

    /**
     * Inner comment — wrap pra OTel não interceptar lógica.
     */
    protected function commentInner(string $taskId, string $body, string $author): McpTaskComment
    {
        $task = $this->findTaskOrFail($taskId);
        $body = trim($body);

        $comment = McpTaskComment::create([
            'task_id' => $task->task_id,
            'author' => $author,
            'body' => $body,
        ]);

        McpTaskEvent::log(
            taskId: $task->task_id,
            eventType: 'commented',
            author: $author,
            note: mb_substr($body, 0, 120),
        );

        // Detecta @mentions (formato @username) e gera notificações
        if (preg_match_all('/@([a-z][a-z0-9_-]+)/i', $body, $mm)) {
            foreach (array_unique($mm[1]) as $mentioned) {
                $userId = $this->resolveUserIdByUsername(strtolower($mentioned));
                if ($userId) {
                    McpInboxNotification::notify(
                        userId: $userId,
                        type: 'mention',
                        taskId: $task->task_id,
                        actorId: $this->resolveUserIdByUsername($author),
                        body: "Mencionou você em " . $task->getDisplayIdAttribute(),
                        payload: ['comment_id' => $comment->id],
                    );
                }
            }
        }

        return $comment;
    }

    /**
     * Cria nova task. Aceita 2 modos:
     *   - canônica (US-XXX-NNN): grava no SPEC.md do módulo
     *   - ad-hoc (Linear-style identifier): só DB, sem SPEC
     *
     * @param  array<string,mixed> $data
     * @return array{task_id: string, identifier: ?string, markdown: ?string, spec_path: ?string, written: bool}
     */
    public function create(array $data): array
    {
        return OtelHelper::spanBiz('project_mgmt.task_crud.create', function () use ($data): array {
            return $this->createInner($data);
        }, [
            'module'      => 'ProjectMgmt',
            'task_module' => (string) ($data['module'] ?? ''),
            'project'     => isset($data['project']) ? strtoupper((string) $data['project']) : '',
            'has_parent'  => isset($data['parent_task_id']) ? 'true' : 'false',
        ]);
    }

    /**
     * Inner create — wrap pra OTel não interceptar lógica.
     *
     * @param  array<string,mixed> $data
     * @return array{task_id: string, identifier: ?string, markdown: ?string, spec_path: ?string, written: bool}
     */
    protected function createInner(array $data): array
    {
        $module = trim((string) ($data['module'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $projectKey = isset($data['project']) ? strtoupper(trim((string) $data['project'])) : null;

        if ($title === '') {
            throw new \RuntimeException("Campo 'title' é obrigatório.");
        }

        $project = $projectKey ? McpProject::where('key', $projectKey)->first() : null;

        // Modo canônico: módulo informado + SPEC.md existe
        if ($module !== '' && is_file(base_path("memory/requisitos/{$module}/SPEC.md"))) {
            return $this->createCanonical($module, $title, $data, $project);
        }

        // Modo ad-hoc: precisa de project pra gerar identifier
        if (! $project) {
            throw new \RuntimeException("Sem 'module' canônico, é obrigatório passar 'project' (key existente em mcp_jira_projects).");
        }

        return $this->createAdHoc($title, $data, $project);
    }

    /**
     * Move task entre cycle/epic/component (operação comum no Kanban).
     */
    public function move(string $taskId, array $target, string $author = 'system'): McpTask
    {
        $allowedKeys = ['cycle_id', 'epic_id', 'component_id', 'parent_task_id'];
        $payload = array_intersect_key($target, array_flip($allowedKeys));
        if (empty($payload)) {
            throw new \RuntimeException("tasks-move precisa de pelo menos um destino: " . implode(', ', $allowedKeys));
        }
        $result = $this->update($taskId, $payload, $author);
        return $result['task'];
    }

    /**
     * Cria/atualiza dependência entre 2 tasks.
     * type: blocks | relates | duplicates | clones
     */
    public function link(string $taskId, string $dependsOnTaskId, string $type = 'blocks', string $author = 'system'): McpTaskDependency
    {
        if (! in_array($type, McpTaskDependency::TYPES, true)) {
            throw new \RuntimeException("link type '{$type}' inválido. Permitidos: " . implode(', ', McpTaskDependency::TYPES));
        }

        $task = $this->findTaskOrFail($taskId);
        $target = $this->findTaskOrFail($dependsOnTaskId);

        if ($task->task_id === $target->task_id) {
            throw new \RuntimeException("Task não pode depender de si mesma.");
        }

        $dep = McpTaskDependency::firstOrCreate(
            ['task_id' => $task->task_id, 'depends_on_task_id' => $target->task_id, 'type' => $type],
            ['created_by' => $author],
        );

        if ($dep->wasRecentlyCreated) {
            McpTaskEvent::log(
                taskId: $task->task_id,
                eventType: 'field_updated',
                author: $author,
                note: "Linked {$type} {$target->task_id}",
            );
        }

        return $dep;
    }

    /**
     * Atualiza assignee + opcionalmente adiciona watchers.
     */
    public function assign(string $taskId, ?string $owner, array $watcherUserIds = [], string $author = 'system'): McpTask
    {
        $result = $this->update($taskId, ['owner' => $owner], $author);
        $task = $result['task'];

        foreach (array_unique($watcherUserIds) as $userId) {
            DB::table('mcp_task_watchers')->updateOrInsert(
                ['task_id' => $task->task_id, 'user_id' => $userId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        return $task;
    }

    /**
     * Aplica bulk update em N tasks de uma vez.
     * Cada task gera 1 evento com bulk_op_id correlacionando.
     *
     * @param  list<string> $taskIds
     * @param  array<string,mixed> $fields
     * @return array{updated: int, errors: list<array{task_id:string,error:string}>}
     */
    public function bulkUpdate(array $taskIds, array $fields, string $author = 'system'): array
    {
        return OtelHelper::spanBiz('project_mgmt.task_crud.bulk_update', function () use ($taskIds, $fields, $author): array {
            $bulkOpId = (string) \Illuminate\Support\Str::uuid();
            $updated = 0;
            $errors = [];

            foreach ($taskIds as $tid) {
                try {
                    // Usa updateInner pra evitar criar span filho por task (N+1 ruído).
                    $this->updateInner($tid, $fields, $author);
                    $updated++;
                } catch (\Throwable $e) {
                    $errors[] = ['task_id' => $tid, 'error' => $e->getMessage()];
                }
            }

            return ['updated' => $updated, 'errors' => $errors, 'bulk_op_id' => $bulkOpId];
        }, [
            'module'      => 'ProjectMgmt',
            'task_count'  => count($taskIds),
            'fields'      => implode(',', array_keys($fields)),
            'author'      => $author,
        ]);
    }

    // ---------- helpers ----------

    protected function findTaskOrFail(string $taskId): McpTask
    {
        $task = McpTask::where('task_id', strtoupper($taskId))->first()
            ?? McpTask::where('task_id', $taskId)->first()
            ?? McpTask::where('identifier', strtoupper($taskId))->first();

        if (! $task) {
            throw new \RuntimeException("Task '{$taskId}' não encontrada.");
        }
        return $task;
    }

    protected function createCanonical(string $module, string $title, array $data, ?McpProject $project): array
    {
        $specPath = base_path("memory/requisitos/{$module}/SPEC.md");
        $taskId = $this->gerarProximoIdCanonical($module);
        $identifier = $project ? $project->allocateNextIdentifier() : null;

        $owner = $data['owner'] ?? null;
        $sprint = $data['sprint'] ?? null;
        $priority = in_array($data['priority'] ?? 'p2', ['p0', 'p1', 'p2', 'p3'], true)
            ? ($data['priority'] ?? 'p2')
            : 'p2';
        $estimate = isset($data['estimate_h']) ? (float) $data['estimate_h'] : null;
        $blockedBy = $data['blocked_by'] ?? null;
        $desc = trim((string) ($data['description'] ?? ''));
        $type = in_array($data['type'] ?? 'story', McpTask::TYPES, true) ? ($data['type'] ?? 'story') : 'story';

        // Frontmatter
        $fmParts = [
            "owner: " . ($owner ?? '—'),
        ];
        if ($sprint) $fmParts[] = "sprint: {$sprint}";
        $fmParts[] = "priority: {$priority}";
        if ($estimate) $fmParts[] = "estimate: {$estimate}h";
        $fmParts[] = "status: todo";
        $fmParts[] = "type: {$type}";

        $fm = implode(' · ', $fmParts);
        $blockedFm = $blockedBy ? implode(', ', (array) $blockedBy) : '—';
        $idLine = $identifier ? "> identifier: {$identifier}\n" : '';

        $block = "\n### {$taskId} · {$title}\n\n";
        $block .= "> {$fm}\n";
        $block .= "> blocked_by: {$blockedFm}\n";
        $block .= $idLine;
        if ($desc !== '') {
            $block .= "\n{$desc}\n";
        }

        $written = false;
        try {
            file_put_contents($specPath, $block, FILE_APPEND | LOCK_EX);
            $written = true;
        } catch (\Throwable) {
            // shared hosting pode negar escrita
        }

        McpTaskEvent::log(
            taskId: $taskId,
            eventType: 'created',
            author: $data['author'] ?? 'system',
            note: "Criada via tasks-create canonical (written={$written})",
        );

        return [
            'task_id' => $taskId,
            'identifier' => $identifier,
            'markdown' => $block,
            'spec_path' => "memory/requisitos/{$module}/SPEC.md",
            'written' => $written,
        ];
    }

    protected function createAdHoc(string $title, array $data, McpProject $project): array
    {
        $identifier = $data['identifier'] ?? $project->allocateNextIdentifier();
        $taskId = $identifier; // ad-hoc: identifier = task_id

        $payload = [
            'task_id' => $taskId,
            'identifier' => $identifier,
            'project_id' => $project->id,
            'module' => $data['module'] ?? $project->key,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'type' => in_array($data['type'] ?? 'task', McpTask::TYPES, true) ? ($data['type'] ?? 'task') : 'task',
            'owner' => $data['owner'] ?? null,
            'sprint' => $data['sprint'] ?? null,
            'priority' => in_array($data['priority'] ?? 'p2', ['p0', 'p1', 'p2', 'p3'], true) ? ($data['priority'] ?? 'p2') : 'p2',
            'epic_id' => $data['epic_id'] ?? null,
            'cycle_id' => $data['cycle_id'] ?? null,
            'component_id' => $data['component_id'] ?? null,
            'parent_task_id' => $data['parent_task_id'] ?? null,
            'story_points' => $data['story_points'] ?? null,
            'estimate_unit' => $data['estimate_unit'] ?? 'points',
            'estimate_value' => $data['estimate_value'] ?? $data['story_points'] ?? null,
            'estimate_h' => $data['estimate_h'] ?? null,
            'due_date' => isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            'labels' => $data['labels'] ?? null,
            'custom_fields' => $data['custom_fields'] ?? null,
            'source_path' => 'ad-hoc',
            'parsed_at' => now(),
        ];

        $task = McpTask::create($payload);

        McpTaskEvent::log(
            taskId: $taskId,
            eventType: 'created',
            author: $data['author'] ?? 'system',
            note: "Criada via tasks-create ad-hoc (project={$project->key})",
        );

        return [
            'task_id' => $taskId,
            'identifier' => $identifier,
            'markdown' => null,
            'spec_path' => null,
            'written' => false,
        ];
    }

    /**
     * Detecta o prefixo curto do módulo lendo o SPEC.md ('### US-XX-NNN').
     *
     * Convenção do projeto: módulos usam abreviação (ex: RecurringBilling → RB,
     * NfeBrasil → NFE). Sem isso, geraríamos 'US-RECURRINGBILLING-NNN' que não
     * bate com o que o SPEC já tem, e o counter ficaria preso em 001.
     *
     * Fallback: strtoupper($module) — preserva comportamento antigo em módulos
     * sem prefixo curto declarado.
     */
    protected function detectarPrefixoSpec(string $module): string
    {
        $specPath = base_path("memory/requisitos/{$module}/SPEC.md");
        if (is_file($specPath)) {
            $content = (string) @file_get_contents($specPath);
            if (preg_match('/^###\s+US-([A-Z]+)-\d+/m', $content, $m)) {
                return $m[1]; // ex: "RB", "NFE", "COPI"
            }
        }
        return strtoupper($module);
    }

    protected function gerarProximoIdCanonical(string $module): string
    {
        $prefix = $this->detectarPrefixoSpec($module);
        $prefixo = "US-{$prefix}-";

        $ultimoDb = McpTask::where('task_id', 'LIKE', $prefixo . '%')
            ->orderByRaw('CAST(SUBSTRING(task_id, ' . (strlen($prefixo) + 1) . ') AS UNSIGNED) DESC')
            ->value('task_id');
        $nDb = $ultimoDb ? (int) substr($ultimoDb, strlen($prefixo)) : 0;

        // Cobre o caso DB out-of-sync: webhook ainda não rodou pro último push
        // OU o operador escreveu US-RB-NNN à mão no SPEC. Pegamos max(DB, SPEC).
        // Captura 2 formatos (ADR 0134):
        //   1. "### US-XX-NNN ·" (story detalhada — section header)
        //   2. "- US-XX-NNN —" (placeholder em out-of-scope ou backlog futuro)
        // Antes só pegava headers → 2026-05-11 deu drift em US-WA-053 (placeholder
        // bullet no SPEC.md Whatsapp linha 572 colidiu com tasks-create).
        $nSpec = 0;
        $specPath = base_path("memory/requisitos/{$module}/SPEC.md");
        if (is_file($specPath)) {
            $content = (string) @file_get_contents($specPath);
            if (preg_match_all('/(?:^###|^-)\s+(?:\S+\s+)?' . preg_quote($prefixo, '/') . '(\d+)/m', $content, $matches)) {
                $nSpec = max(array_map('intval', $matches[1]));
            }
        }

        $n = max($nDb, $nSpec) + 1;
        return $prefixo . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    protected function stringValue(mixed $v): string
    {
        if ($v === null || $v === false) return '';
        if ($v instanceof Carbon) return $v->toDateTimeString();
        if (is_array($v)) return json_encode($v);
        return (string) $v;
    }

    protected function resolveUserIdByUsername(?string $username): ?int
    {
        if (! $username) return null;
        $id = DB::table('users')
            ->where(DB::raw('LOWER(username)'), strtolower($username))
            ->orWhere(DB::raw('LOWER(first_name)'), strtolower($username))
            ->value('id');
        return $id ? (int) $id : null;
    }

    protected function maybeNotifyAssignment(McpTask $task, string $actor): void
    {
        $userId = $this->resolveUserIdByUsername($task->owner);
        if (! $userId) return;
        McpInboxNotification::notify(
            userId: $userId,
            type: 'assigned',
            taskId: $task->task_id,
            actorId: $this->resolveUserIdByUsername($actor),
            body: "Atribuiu " . $task->getDisplayIdAttribute() . " pra você",
        );
    }

    protected function maybeNotifyReview(McpTask $task, string $actor): void
    {
        // Notifica watchers + lead do component (se houver)
        $watcherIds = DB::table('mcp_task_watchers')
            ->where('task_id', $task->task_id)
            ->pluck('user_id')
            ->all();
        foreach ($watcherIds as $uid) {
            McpInboxNotification::notify(
                userId: (int) $uid,
                type: 'review_requested',
                taskId: $task->task_id,
                actorId: $this->resolveUserIdByUsername($actor),
                body: "Revisão solicitada em " . $task->getDisplayIdAttribute(),
            );
        }
    }
}
