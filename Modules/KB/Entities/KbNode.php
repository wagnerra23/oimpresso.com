<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * KbNode — unidade atômica do grafo de conhecimento.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §3
 *
 * Tipos canônicos:
 *   - article      → operacional editável (Larissa Cowork)
 *   - adr|session|charter|runbook|briefing|spec|comparativo|reference
 *                  → bridge canônico read-only (source_doc_id != null)
 *   - os|customer|product|nfe|equipment
 *                  → bridge ERP (source_entity_type/id)
 *   - external_file → arquivos externos (manuais, fotos balcão)
 *
 * Invariante CRÍTICA Tier 0 (ADR 0093 + ADR 0061):
 *   is_editable=false  ⇒  body_blocks IS NULL
 *
 * Enforcement em runtime via KbNodeObserver (saving event).
 *
 * @property int     $id
 * @property int     $business_id
 * @property string  $type
 * @property string  $slug
 * @property string  $title
 * @property string|null $excerpt
 * @property array|null  $body_blocks
 * @property int|null    $source_doc_id
 * @property string|null $source_entity_type
 * @property int|null    $source_entity_id
 * @property bool    $is_editable
 * @property string  $status
 * @property bool    $pinned
 * @property int|null $category_id
 * @property int|null $subcategory_id
 * @property string|null $nivel
 * @property string|null $equip
 * @property array|null  $tags
 * @property int     $reads_count
 * @property int     $helpful_count
 * @property int     $outdated_votes
 * @property int     $os_linked_count
 * @property int|null    $author_user_id
 * @property int|null    $read_time_min
 * @property \Illuminate\Support\Carbon|null $last_verified_at
 */
class KbNode extends Model
{
    use BelongsToBusinessTrait, LogsActivity, SoftDeletes;

    protected $table = 'kb_nodes';

    /**
     * Audit trail LGPD Art. 37 (Wave 11 — boost D7 KB).
     *
     * Registra QUEM mudou QUE campo em artigos editáveis. Bridges canon
     * (is_editable=false) também passam aqui (raro, mas pode acontecer
     * em ajustes de meta tipo pinned/tags).
     *
     * Loga apenas campos dirty (não polui tabela). Não submete logs
     * vazios. activity_log retention controlada via
     * config('kb.retention.audit_log_days', 730).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('kb.node');
    }

    protected $fillable = [
        'business_id', 'type', 'slug', 'title', 'excerpt',
        'body_blocks', 'source_doc_id', 'source_entity_type', 'source_entity_id',
        'is_editable', 'status', 'pinned',
        'category_id', 'subcategory_id', 'nivel', 'equip', 'tags',
        'reads_count', 'helpful_count', 'outdated_votes', 'os_linked_count',
        'author_user_id', 'read_time_min', 'last_verified_at',
    ];

    protected $casts = [
        'business_id'      => 'integer',
        'body_blocks'      => 'array',
        'source_doc_id'    => 'integer',
        'source_entity_id' => 'integer',
        'is_editable'      => 'boolean',
        'pinned'           => 'boolean',
        'category_id'      => 'integer',
        'subcategory_id'   => 'integer',
        'tags'             => 'array',
        'reads_count'      => 'integer',
        'helpful_count'    => 'integer',
        'outdated_votes'   => 'integer',
        'os_linked_count'  => 'integer',
        'author_user_id'   => 'integer',
        'read_time_min'    => 'integer',
        'last_verified_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function sourceDoc(): BelongsTo
    {
        return $this->belongsTo(McpMemoryDocument::class, 'source_doc_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(KbSubcategory::class, 'subcategory_id');
    }

    public function edgesOut(): HasMany
    {
        return $this->hasMany(KbEdge::class, 'from_node_id');
    }

    public function edgesIn(): HasMany
    {
        return $this->hasMany(KbEdge::class, 'to_node_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KbNodeVersion::class, 'node_id')->orderByDesc('version_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(KbComment::class, 'node_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(KbFavorite::class, 'node_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where("{$this->getTable()}.type", $type);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn("{$this->getTable()}.status", ['ok', 'draft'])
            ->whereNull("{$this->getTable()}.deleted_at");
    }

    public function scopePinned(Builder $q): Builder
    {
        return $q->where("{$this->getTable()}.pinned", true);
    }

    public function scopeBridge(Builder $q): Builder
    {
        return $q->where("{$this->getTable()}.is_editable", false);
    }

    public function scopeEditable(Builder $q): Builder
    {
        return $q->where("{$this->getTable()}.is_editable", true);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }
        $like = '%'.str_replace('%', '\\%', $term).'%';
        return $q->where(function (Builder $sub) use ($like) {
            $sub->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('slug', 'like', $like);
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Retorna o markdown canônico quando bridge, ou serialize blocks quando artigo.
     *
     * Em runtime o controller carrega `sourceDoc` via eager load quando o
     * node é bridge — método é fallback defensivo.
     */
    public function renderContent(): string
    {
        if (! $this->is_editable && $this->sourceDoc) {
            return (string) $this->sourceDoc->content_md;
        }
        return json_encode($this->body_blocks ?? [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * True se o node é bridge canônico (não pode editar conteúdo).
     */
    public function isBridge(): bool
    {
        return ! $this->is_editable;
    }
}
