<?php

declare(strict_types=1);

namespace Modules\ProjectMgmt\Services;

use App\Util\OtelHelper;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Spatie\Activitylog\Facades\LogBatch;
use Spatie\Activitylog\Models\Activity;

/**
 * ProjectMgmtAuditService — D7.b LGPD audit trail (Wave 16 Governance).
 *
 * ProjectMgmt **não possui Entities próprias** (opera sobre models Jana:
 * McpTask, McpProject, McpCycle, etc). Por isso o pattern Wave 9 Crm
 * (LogsActivity trait em Entity) não é aplicável diretamente — não podemos
 * adicionar trait em modelos de outro módulo sem invadir Jana (área proibida).
 *
 * Solução: Service próprio que registra eventos PM no `activity_log` Spatie
 * canônico via API direta. Mantém audit trail append-only (LGPD Art. 16 §I)
 * + isolamento do módulo ProjectMgmt.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093]):
 * Service exige `businessId` no constructor; toda entry no activity_log
 * carrega `properties.business_id` pra audit cross-tenant.
 *
 * PII protection ([Modules\Jana\Services\Privacy\PiiRedactor]):
 * Toda string livre logada (description, comment body, note) passa por
 * PiiRedactor::redact() antes de persistir — CPF/CNPJ/email/telefone
 * brasileiro substituídos por [REDACTED:TYPE] (LGPD Art. 7º).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0070-jira-style-task-management-current-md-removed.md
 * @see Modules\Crm\Entities\Campaign (pattern LogsActivity Wave 9)
 * @see Modules\Jana\Services\Privacy\PiiRedactor
 */
class ProjectMgmtAuditService
{
    /** Nome canônico do log no `activity_log.log_name` (queries audit filtram por aqui). */
    public const LOG_NAME = 'project-mgmt';

    /** Eventos canônicos do módulo (subject_type virtual — strings, não classes). */
    public const EVENT_PROJECT_CREATED   = 'project.created';
    public const EVENT_PROJECT_UPDATED   = 'project.updated';
    public const EVENT_PROJECT_DECOMPOSED = 'project.decomposed';
    public const EVENT_TASK_STATUS_CHANGED = 'task.status_changed';
    public const EVENT_TASK_COMMENT_ADDED  = 'task.comment_added';
    public const EVENT_TASK_WATCH_TOGGLED  = 'task.watch_toggled';
    public const EVENT_TASK_SUBTASK_ADDED  = 'task.subtask_added';
    public const EVENT_INBOX_MARKED_READ   = 'inbox.marked_read';

    public function __construct(
        private readonly int $businessId,
        private readonly PiiRedactor $redactor,
    ) {
        if ($this->businessId <= 0) {
            throw new \InvalidArgumentException(
                'ProjectMgmtAuditService exige business_id > 0 (multi-tenant Tier 0 — ADR 0093)'
            );
        }
    }

    /**
     * Registra evento de domínio ProjectMgmt no activity_log canônico Spatie.
     *
     * Toda string livre em `$properties` (description/body/note) passa por
     * PiiRedactor — preserva métricas sem reter PII brasileiro raw.
     *
     * @param  string  $event       Um dos EVENT_* (validado pra evitar typo)
     * @param  string  $description Descrição humana curta (PT-BR, sem PII)
     * @param  array<string,mixed>  $properties  Atributos do evento; strings são redacted
     * @param  string|null  $subjectType  Classe Eloquent do subject (Mcp*::class) opcional
     * @param  int|null     $subjectId    ID do subject opcional
     * @param  int|null     $causerId     User ID que causou (auth()->id() default)
     */
    public function log(
        string $event,
        string $description,
        array $properties = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $causerId = null,
    ): Activity {
        // D9 observabilidade (Wave 17): toda gravação no activity_log é
        // observável via OTel — auditoria LGPD + tracing unificados.
        return OtelHelper::spanBiz('project_mgmt.audit.log', function () use (
            $event, $description, $properties, $subjectType, $subjectId, $causerId
        ): Activity {
            $validEvents = $this->validEvents();
            if (! in_array($event, $validEvents, true)) {
                throw new \InvalidArgumentException(
                    "Event '{$event}' não está em EVENT_*. Permitidos: " . implode(', ', $validEvents)
                );
            }

            $sanitized = $this->sanitizeProperties($properties);
            $sanitized['business_id'] = $this->businessId;
            $sanitized['event'] = $event;

            $activity = new Activity();
            $activity->log_name = self::LOG_NAME;
            $activity->description = $this->redactor->redact($description);
            $activity->properties = collect($sanitized);
            $activity->causer_id = $causerId ?? (auth()->id() ?? null);
            $activity->causer_type = $causerId !== null || auth()->check() ? \App\User::class : null;

            if ($subjectType !== null && $subjectId !== null) {
                $activity->subject_type = $subjectType;
                $activity->subject_id = $subjectId;
            }

            $activity->save();

            return $activity;
        }, [
            'business_id' => $this->businessId,
            'event'       => $event,
        ]);
    }

    /**
     * Helper: registra mudança de status de task (evento mais frequente do módulo).
     */
    public function logTaskStatusChange(
        string $taskId,
        string $oldStatus,
        string $newStatus,
        ?string $author = null,
    ): Activity {
        return $this->log(
            event: self::EVENT_TASK_STATUS_CHANGED,
            description: "Task {$taskId}: status '{$oldStatus}' → '{$newStatus}'",
            properties: [
                'task_id'    => $taskId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'author'     => $author,
            ],
        );
    }

    /**
     * Helper: registra adição de comentário (corpo passa por PiiRedactor).
     */
    public function logTaskComment(string $taskId, string $body, string $author): Activity
    {
        return $this->log(
            event: self::EVENT_TASK_COMMENT_ADDED,
            description: "Comment adicionado em {$taskId} por {$author}",
            properties: [
                'task_id'      => $taskId,
                'body_excerpt' => mb_substr($body, 0, 200), // sanitizeProperties redaciona
                'author'       => $author,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function validEvents(): array
    {
        return [
            self::EVENT_PROJECT_CREATED,
            self::EVENT_PROJECT_UPDATED,
            self::EVENT_PROJECT_DECOMPOSED,
            self::EVENT_TASK_STATUS_CHANGED,
            self::EVENT_TASK_COMMENT_ADDED,
            self::EVENT_TASK_WATCH_TOGGLED,
            self::EVENT_TASK_SUBTASK_ADDED,
            self::EVENT_INBOX_MARKED_READ,
        ];
    }

    /**
     * Aplica PiiRedactor em todos os valores string do array (recursivo p/ nested).
     *
     * @param array<string,mixed> $properties
     * @return array<string,mixed>
     */
    private function sanitizeProperties(array $properties): array
    {
        $sanitized = [];
        foreach ($properties as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->redactor->redact($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeProperties($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
