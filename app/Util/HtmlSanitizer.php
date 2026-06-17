<?php

namespace App\Util;

/**
 * HtmlSanitizer — sanitização canônica de HTML rico antes de render cru (dangerouslySetInnerHTML).
 *
 * Defesa contra stored-XSS para conteúdo HTML editável (CMS page/blog, KB knowledge-base):
 * HTMLPurifier (ezyang/htmlpurifier, já no composer) remove <script>, handlers on*,
 * javascript:/data: e tags perigosas, preservando markup de texto rico (h/p/a/img/ul/strong/etc).
 * Null-safe, idempotente.
 *
 * Fonte única: usado por Modules\Cms\Services\SiteContentService (Site/Page, Site/BlogPost) e
 * Modules\Essentials\Http\Controllers\KnowledgeBaseController (Knowledge/Index, Knowledge/Show).
 * Origem: red-team 2026-06-17 — KB renderizava HTML de admin sem sanitização server-side
 * (o CMS já purificava; a KB não). Ver gate scripts/dsih-gate.mjs + proposals/handoff-loop-zero-paste.md.
 */
class HtmlSanitizer
{
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $cacheDir = storage_path('app/htmlpurifier');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cacheDir);
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return (new \HTMLPurifier($config))->purify($html);
    }
}
