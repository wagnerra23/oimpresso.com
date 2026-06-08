<?php

namespace Modules\Cms\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Arquivos\Entities\Arquivo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CmsPage extends Model
{
    use HasArquivos; // ADR 0123 — adopcao Sprint 4 (feature_image via arquivos)
    use LogsActivity;

    /**
     * Auditoria LGPD D7.b — registra alterações de páginas/posts/blogs públicos
     * em `activity_log` (append-only via spatie/laravel-activitylog).
     *
     * Conteúdo pode ser público mas autor/editor é identificável; mudanças
     * críticas (is_enabled flip, delete, conteúdo alterado) ficam audit trail
     * pra rastreio LGPD Art. 37 (responsável tratamento) + Art. 38 (registros).
     *
     * @see memory/requisitos/Cms/PII-REDACTION.md
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['slug', 'feature_image_url'];

    /**
     * Get the slug for post.
     *
     * @return string
     */
    public function getSlugAttribute()
    {
        return strtolower(str_replace(' ', '-', $this->title));
    }

    /**
     * Get the business that owns the user.
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by', 'id');
    }

    /**
     * Get the feature image.
     *
     * Sprint 4 US-ARQ-025+ (ADR 0123): preferir arquivos table SE existir,
     * fallback pra coluna legacy `feature_image` (path em `/public/uploads/cms/`).
     *
     * @return string|null
     */
    public function getFeatureImageUrlAttribute()
    {
        // Preferir arquivos table (Sprint 4+)
        $arquivo = $this->getFeatureImageArquivoAttribute();
        if ($arquivo) {
            // signedUrl gera URL temporário 60min; CMS precisa URL permanente —
            // usa arquivos.download direto sem expiração curta. Sprint 5 vai ter
            // helper publicUrl() pra contexto landing público.
            return route('arquivos.download', ['arquivo' => $arquivo->id]);
        }

        // Fallback legacy (mantém site oimpresso.com landing funcionando)
        if (! empty($this->feature_image)) {
            return asset('/uploads/cms/'.rawurlencode($this->feature_image));
        }

        return null;
    }

    /**
     * Accessor — Arquivo da feature_image via backbone Modules/Arquivos.
     * sub_destination convencional: 'cms-featured'.
     */
    public function getFeatureImageArquivoAttribute(): ?Arquivo
    {
        if (! method_exists($this, 'arquivos')) return null;
        return $this->arquivos()
            ->where('sub_destination', 'cms-featured')
            ->where('bucket', 'active')
            ->latest('created_at')
            ->first();
    }

    /**
     * Get the feature image path.
     *
     * @return string
     */
    public function getFeatureImagePathAttribute()
    {
        $image_path = null;

        if (! empty($this->feature_image)) {
            $image_path = public_path('uploads').'/cms/'.$this->feature_image;
        }

        return $image_path;
    }

    /**
     * Get the meta for the page.
     */
    public function pageMeta()
    {
        return $this->hasMany('Modules\Cms\Entities\CmsPageMeta', 'cms_page_id', 'id');
    }

    public static function getEnabledPages($type = 'page')
    {
        $pages = CmsPage::where('type', $type)
                    ->whereNull('layout')
                    ->orderBy('priority', 'asc')
                    ->where('is_enabled', 1)
                    ->get();

        return $pages;
    }

    public static function getPagesCount($type = '')
    {
        if (empty($type)) {
            return 0;
        }

        $pages_count = CmsPage::where('type', $type)
                        ->where('is_enabled', 1)
                        ->count();

        return $pages_count;
    }
}
