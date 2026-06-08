<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit log de mudanças em feature flag (GrowthBook), gravado por
 * GrowthBookAdminService independente do canal (Artisan/MCP/painel).
 *
 * Append-only: sem UPDATE, sem DELETE. Schema em
 * database/migrations/2026_05_13_201220_create_feature_flag_audits_table.php.
 *
 * **D7.b Wave 14 (2026-05-16):** trait `LogsActivity` (spatie/laravel-activitylog
 * ^4.8) registra qualquer INSERT na tabela `activity_log` central, dando
 * 2ª linha de auditoria pra Admin Center (defesa em profundidade Constituição
 * v2 §7 Transparência). Apesar do model ser append-only por design, o
 * activity_log permite filtros cross-módulo ("todas mudanças críticas
 * 2026-05") sem precisar UNION manual de N tabelas audit per-módulo.
 *
 * @see memory/decisions/0155-module-grade-v3-anti-injustica-na-justified.md D7.b
 */
class FeatureFlagAudit extends Model
{
    use HasFactory;
    use LogsActivity;

    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_label',
        'flag_key',
        'action',
        'environment',
        'payload_before',
        'payload_after',
        'diff_summary',
    ];

    protected $casts = [
        'payload_before' => 'array',
        'payload_after'  => 'array',
        'created_at'     => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * D7.b — spatie/activitylog opções. Loga só atributos relevantes
     * pra audit cross-módulo (sem duplicar payload completo, já está em
     * `payload_before` / `payload_after`).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['flag_key', 'action', 'environment', 'actor_label'])
            ->useLogName('admin.feature_flag')
            ->dontSubmitEmptyLogs();
    }
}
