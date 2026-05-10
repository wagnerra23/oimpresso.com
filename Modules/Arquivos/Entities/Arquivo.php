<?php

namespace Modules\Arquivos\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Arquivo — model do DMS backbone (ADR 0123).
 *
 * Multi-tenant Tier 0 (ADR 0093): global scope por business_id na sessão.
 * Quebra do scope só com `withoutGlobalScopes(['business_id'])` E comentário
 * `// SUPERADMIN: <razão>` mandatório.
 *
 * Polimorfismo: $arquivo->arquivable retorna o owner (Transaction, Ticket, etc).
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class Arquivo extends Model
{
    use SoftDeletes;

    protected $table = 'arquivos';

    protected $fillable = [
        'business_id',
        'arquivable_type',
        'arquivable_id',
        'disk',
        'storage_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'md5',
        'bucket',
        'sub_destination',
        'sensitive_flags',
        'classified_by',
        'classified_at',
        'uploaded_by_user_id',
        'visibility',
        'encrypted',
        'retention_days',
    ];

    protected $casts = [
        'sensitive_flags' => 'array',
        'classified_at'   => 'datetime',
        'encrypted'       => 'boolean',
        'size_bytes'      => 'integer',
        'business_id'     => 'integer',
    ];

    /**
     * Global scope multi-tenant Tier 0 (ADR 0093).
     */
    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('arquivos.business_id', $businessId);
            }
        });

        // Auto-fill business_id no create se não passado explícito.
        static::creating(function (Arquivo $arquivo) {
            if ($arquivo->business_id === null) {
                $arquivo->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    public function arquivable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Helper: arquivos classificados num bucket específico (memory/sensitive/etc).
     */
    public function scopeBucket(Builder $query, string $bucket): Builder
    {
        return $query->where('bucket', $bucket);
    }

    // -------------------------------------------------------------------------
    // Compatibilidade com interface App\Media — permite que partials legadas
    // como `document_table_view.blade.php` consomem tanto Arquivo quanto Media
    // sem branch condicional. Sprint 3 ADR 0123 §2.
    // -------------------------------------------------------------------------

    /**
     * display_name — nome exibível sem prefixo de timestamp (compatível com Media).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->original_name ?? basename($this->storage_path ?? '');
    }

    /**
     * display_url — URL pública ou signed URL pra download.
     *
     * Arquivos no disk 'arquivos' (public-like) usam asset() direto.
     * Arquivos no disk 'vault' (encrypted) usam signed route — 1h validade.
     * Fallback pro storage_path bruto se não há route disponível.
     */
    public function getDisplayUrlAttribute(): string
    {
        if ($this->encrypted || $this->disk === config('arquivos.disk_vault', 'vault')) {
            try {
                return app(\Modules\Arquivos\Services\ArquivosService::class)->signedUrl($this, 60);
            } catch (\Throwable) {
                // fallback em contexto sem sessão/request (ex: testes CLI)
                return asset('uploads/media/' . rawurlencode($this->original_name ?? 'arquivo'));
            }
        }

        // Disk local padrão — serve via asset path direto.
        return asset($this->storage_path ?? '');
    }

    /**
     * thumbnail() — HTML <img> inline compatível com a partial legada.
     */
    public function thumbnail(array $size = [60, 60], ?string $class = null): string
    {
        $html  = '<img src="' . e($this->display_url) . '"';
        $html .= ' width="' . $size[0] . '"';
        $html .= ' height="' . $size[1] . '"';
        if ($class !== null) {
            $html .= ' class="' . e($class) . '"';
        }
        $html .= '>';
        return $html;
    }
}
