<?php

namespace Modules\Vestuario\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * VestuarioSetting — settings per-business pra vertical Vestuario (ADR 0121 §P7).
 *
 * Sprint 1: tabela mínima (business_id + settings JSON). Sprint 2+ cada quirk
 * ROTA LIVRE migrado vira key em settings.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * UNIQUE(business_id) — 1 row per business. Global scope filtra automaticamente.
 *
 * Uso:
 *   $settings = VestuarioSetting::firstOrCreate(['business_id' => 4]);
 *   $settings->set('format_date_shift_hours', 3);
 *   $settings->get('format_date_shift_hours', default: 0);
 *
 * @see memory/requisitos/Vestuario/SPEC.md
 */
class VestuarioSetting extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'vestuario_settings';

    protected $fillable = [
        'business_id',
        'settings',
    ];

    protected $casts = [
        'settings'    => 'array',
        'business_id' => 'integer',
    ];

    /**
     * Global scope multi-tenant Tier 0.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('vestuario_settings.business_id', $businessId);
            }
        });

        static::creating(function (VestuarioSetting $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    /**
     * Get a key from settings JSON with default fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];
        return data_get($settings, $key, $default);
    }

    /**
     * Set a key in settings JSON (auto-saves).
     */
    public function set(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
        return $this;
    }

    /**
     * Convenience: pegar settings da sessão atual (auto-cria se vazio).
     */
    public static function current(): self
    {
        $businessId = session('user.business_id') ?? session('business.id') ?? 0;
        return static::firstOrCreate(['business_id' => $businessId], ['settings' => []]);
    }

    /**
     * Audit LGPD — registra mudanças em settings JSON via activity_log.
     * D7 dim v3 (audit trail append-only) — ver memory/requisitos/Vestuario/PII-LGPD.md.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
