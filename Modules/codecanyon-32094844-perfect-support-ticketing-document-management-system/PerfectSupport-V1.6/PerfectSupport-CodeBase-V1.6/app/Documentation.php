<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Documentation extends Model
{
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
    protected $appends = ['doc_slug'];

	/**
     * Get the sections for the documentation.
     */
    public function sections()
    {
        return $this->hasMany('App\Documentation', 'parent_id')
            ->where('doc_type', 'section');
    }

    /**
     * Get the articles for the section.
     */
    public function articles()
    {
        return $this->hasMany('App\Documentation', 'parent_id')
            ->where('doc_type', 'article');
    }

    /**
     * Get the section for the article.
     */
    public function section()
    {
        return $this->belongsTo('App\Documentation', 'parent_id')
            ->where('doc_type', 'section');
    }

    /**
     * Get the doc slug.
     *
     * @return string
     */
    public function getDocSlugAttribute()
    {   
        return Str::slug($this->title, '-');
    }

    public static function getDropdown($doctype)
    {
        $docs = Documentation::where('doc_type', $doctype)
                ->pluck('title', 'id');

        return $docs;
    }
}
