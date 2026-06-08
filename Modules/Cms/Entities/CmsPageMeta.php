<?php

namespace Modules\Cms\Entities;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CmsPageMeta extends Model
{
    use LogsActivity;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Auditoria LGPD D7.b — meta tags + open graph podem conter dados de autor
     * (og:author, twitter:creator) com identificadores.
     *
     * Audit trail append-only via spatie/laravel-activitylog (LGPD Art. 37/38).
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
     * Get the page for the meta.
     */
    public function page()
    {
        return $this->belongsTo('Modules\Cms\Entities\CmsPage', 'cms_page_id');
    }

    /**
     * Update or create meta data for given page
     */
    public static function updateOrCreateMetaForPage($metas, $page_id)
    {
        if (! empty($metas) && ! empty($page_id)) {
            foreach ($metas as $meta_key => $meta_value) {
                CmsPageMeta::updateOrCreate(
                    ['id' => $meta_value['id'], 'cms_page_id' => $page_id, 'meta_key' => $meta_key],
                    ['meta_value' => json_encode($meta_value)]
                );
            }
        }
    }
}
