<?php

namespace Modules\Jana\Entities\Mcp;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ADR 0076 — entidade canônica de skill (DB primary).
 *
 * Multi-tenant Tier 0 (ADR 0093) — Wave 15: business_id direto + scope global.
 * Skills com business_id NULL = plataforma (superadmin scope handles).
 */
class McpSkill extends Model
{
    use HasBusinessScope;
    use SoftDeletes;

    protected $table = 'mcp_skills';

    protected $fillable = [
        'slug', 'business_id',
        'source', 'status', 'current_version_id', 'module',
        'origin', 'git_sync_mode', 'auto_publish_to_git',
        'git_path',
    ];

    protected $casts = [
        'auto_publish_to_git' => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(McpSkillVersion::class, 'skill_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(McpSkillVersion::class, 'current_version_id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(McpSkillLabel::class, 'skill_id');
    }

    public function productionLabel(): HasOne
    {
        return $this->hasOne(McpSkillLabel::class, 'skill_id')->where('label', 'production');
    }
}
