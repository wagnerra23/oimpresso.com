<?php

namespace Modules\Essentials\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EssentialsMessage extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL (Wave 18 D1 SATURATION)
    use LogsActivity;     // D7 LGPD audit (mensagens internas podem citar PII de colaborador)

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD (D7 Wave 18) — registra mudanças metadata em mensagens internas.
     * Conteúdo `message` é texto livre — pode conter PII. NÃO logamos `message`
     * (apenas to/subject/timestamps) pra evitar vazamento em activity_log.
     *
     * @see Modules\Essentials\Config\retention.php (essentials_message: 730 dias)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subject', 'to_email', 'user_id', 'business_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('essentials.message');
    }

    /**
     * Get sender.
     */
    public function sender()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }
}
