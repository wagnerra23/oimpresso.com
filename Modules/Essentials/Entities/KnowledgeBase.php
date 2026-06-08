<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class KnowledgeBase extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 12 D1 boost)
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'essentials_kb';

    /**
     * Auditoria LGPD (D7) — registra mudanças em artigos KB.
     * Conteúdo `description` é texto livre — pode citar PII de colaborador
     * (procedimento RH cite nome). Caller deve redact via PiiRedactor antes
     * de logar mudanças em `description` se necessário.
     *
     * @see Modules\Essentials\Config\retention.php
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['heading', 'parent_id', 'created_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.knowledge_base');
    }

    /**
     * Get all the children of the knowledge base.
     */
    public function children()
    {
        return $this->hasMany(\Modules\Essentials\Entities\KnowledgeBase::class, 'parent_id');
    }

    public function users()
    {
        return $this->belongsToMany(\App\User::class, 'essentials_kb_users', 'kb_id', 'user_id');
    }
}
