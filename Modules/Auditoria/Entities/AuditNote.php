<?php

declare(strict_types=1);

namespace Modules\Auditoria\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * AuditNote — anotacao interna do modulo Auditoria sobre entries do activity_log.
 *
 * Permite que admin escreva uma nota livre (PT-BR) sobre uma entry de
 * activity_log para documentar contexto offline (ex "cliente pediu por email
 * dia X — revert aprovado", "auditor externo solicitou clarificacao").
 *
 * Diferenca de `revert_reason`:
 *   - revert_reason: campo single-line obrigatorio no momento do undo
 *   - audit_note: anotacao opcional, multi-line, append-only via LogsActivity
 *
 * D7.b: Auditoria NAO toca activity_log core (tabela compartilhada Spatie
 * UltimatePOS). Toca apenas sua propria tabela `auditoria_audit_notes` com
 * LogsActivity trait — auditoria-de-auditoria. Retention 2555d
 * (config/auditoria.retention.entities.audit_note — D7.c).
 *
 * Tier 0 IRREVOGAVEL: scope `forBusiness()` obrigatorio + observer
 * `static::creating` para enforce business_id.
 *
 * @property int $id
 * @property int $business_id
 * @property int $activity_id      FK virtual activity_log.id (sem FK formal por shared core)
 * @property int $user_id          autor da nota
 * @property string $note          texto livre PT-BR (max 5000 chars)
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @see Modules\Auditoria\Services\RevertService
 * @see memory/decisions/0127-modules-auditoria-ui-undo.md
 */
class AuditNote extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'auditoria_audit_notes';

    protected $fillable = [
        'business_id',
        'activity_id',
        'user_id',
        'note',
    ];

    /**
     * @var array<string,string>
     */
    protected $casts = [
        'business_id' => 'integer',
        'activity_id' => 'integer',
        'user_id'     => 'integer',
    ];

    /**
     * Configura LogsActivity (Spatie) — append-only audit de auditoria.
     *
     * `dontLogIfAttributesChangedOnly` em `updated_at` previne ruido.
     * `logFillable` evita logar PII inesperada (note pode conter dados sensiveis
     * — D7.a PiiRedactor aplicado no Service antes de save).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['activity_id', 'user_id']) // NAO loga `note` (pode ter PII residual)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => "audit_note.{$event}");
    }

    /**
     * Scope multi-tenant Tier 0 IRREVOGAVEL (ADR 0093).
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }
}
