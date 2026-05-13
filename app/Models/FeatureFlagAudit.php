<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log de mudanças em feature flag (GrowthBook), gravado por
 * GrowthBookAdminService independente do canal (Artisan/MCP/painel).
 *
 * Append-only: sem UPDATE, sem DELETE. Schema em
 * database/migrations/2026_05_13_201220_create_feature_flag_audits_table.php.
 */
class FeatureFlagAudit extends Model
{
    use HasFactory;

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
}
