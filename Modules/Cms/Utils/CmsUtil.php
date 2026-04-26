<?php

namespace Modules\Cms\Utils;

use App\Utils\Util;
use Modules\Cms\Entities\CmsPage;

class CmsUtil extends Util
{
    public function findIdFromGivenUrl($url)
    {
        return last(explode('-', $url));
    }

    public function getPageByType($type = 'page')
    {
        $posts = CmsPage::where('type', $type)
                ->where('is_enabled', 1)
                ->orderBy('priority', 'desc')
                ->get();

        return $posts;
    }

    public function getPageByLayout($layout)
    {
        $page = CmsPage::with(['pageMeta'])
                ->where('type', 'page')
                ->where('layout', $layout)
                ->first();

        return $page;
    }

    /**
     * Buscar página pelo slug (slug = strtolower(replace(' ', '-', title))).
     * Usado pelas rotas /c/page/{slug}.
     */
    public function getPageBySlug($slug)
    {
        $title = str_replace('-', ' ', $slug);

        return CmsPage::with(['pageMeta'])
                ->where('title', $title)
                ->where('is_enabled', 1)
                ->first();
    }
}
