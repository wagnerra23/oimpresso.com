<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * KbCharterSuggestion — sugestão supervisionada a um charter (ADR 0243, F1).
 *
 * Ancora pelo `charter_path` (o *.charter.md no git). NÃO edita o núcleo do
 * charter (que é imutável, vem do git) — registra uma proposta que o owner
 * aprova/rejeita. Em F3, sugestão `accepted` de núcleo vira PR no .charter.md.
 *
 * Tier 0: business_id scope (BelongsToBusinessTrait). Audit LGPD via LogsActivity
 * (texto livre pode ter PII).
 *
 * @property int         $id
 * @property int         $business_id
 * @property string      $charter_path
 * @property string|null $anchor
 * @property string      $kind       suggestion|question|erratum|comment
 * @property string      $text
 * @property string      $status     proposed|under_review|accepted|rejected|merged
 * @property int         $author_user_id
 * @property int|null    $resolved_by_user_id
 * @property string|null $resolution_note
 */
class KbCharterSuggestion extends Model
{
    use BelongsToBusinessTrait, LogsActivity, SoftDeletes;

    protected $table = 'kb_charter_suggestions';

    public const KINDS = ['suggestion', 'question', 'erratum', 'comment'];
    public const STATUSES = ['proposed', 'under_review', 'accepted', 'rejected', 'merged'];
    public const OPEN_STATUSES = ['proposed', 'under_review'];

    protected $fillable = [
        'business_id', 'charter_path', 'anchor', 'kind', 'text', 'status',
        'author_user_id', 'resolved_by_user_id', 'resolution_note',
    ];

    protected $casts = [
        'business_id'         => 'integer',
        'author_user_id'      => 'integer',
        'resolved_by_user_id' => 'integer',
    ];

    /**
     * Audit trail LGPD Art. 37 — texto livre pode conter PII; registra autor +
     * timestamp + diff de status/resolução.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['charter_path', 'kind', 'status', 'resolution_note', 'resolved_by_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('kb.charter.suggestion');
    }

    public function scopeForCharter(Builder $q, string $path): Builder
    {
        return $q->where("{$this->getTable()}.charter_path", $path);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn("{$this->getTable()}.status", self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
