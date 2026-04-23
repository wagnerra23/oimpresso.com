<?php

namespace App\View\Helpers;

use Illuminate\Support\HtmlString;
use Spatie\Html\Facades\Html;

/**
 * Shim de compatibilidade laravelcollective/html → spatie/laravel-html.
 *
 * Preserva API estática Form::text(...), Form::select(...) usada em ~6.433
 * chamadas em 460 blade views do UltimatePOS. Mapeia internamente pra builder
 * fluente do spatie.
 *
 * Divergências forçadas pra paridade com laravelcollective:
 *  - checked/selected forçados XHTML-style (`checked="checked"`, `selected="selected"`)
 *  - textarea content pré-escapado (spatie não escapa; XSS se user data passar direto)
 *  - submit renderiza <input type="submit">, não <button>
 *  - password sempre com value="" presente
 *
 * Divergência aceita (melhoria):
 *  - auto-id="name" em inputs (spatie padrão, laravelcollective nunca fazia)
 */
class Form
{
    public static function text($name, $value = null, array $options = []): HtmlString
    {
        return self::render(Html::text($name, $value)->attributes($options));
    }

    public static function email($name, $value = null, array $options = []): HtmlString
    {
        return self::render(Html::email($name, $value)->attributes($options));
    }

    public static function password($name, array $options = []): HtmlString
    {
        $el = Html::password($name)->attribute('value', '');

        return self::render($el->attributes($options));
    }

    public static function hidden($name, $value = null, array $options = []): HtmlString
    {
        return self::render(Html::hidden($name, $value)->attributes($options));
    }

    public static function number($name, $value = null, array $options = []): HtmlString
    {
        $el = Html::input('number', $name, $value);

        return self::render($el->attributes($options));
    }

    public static function date($name, $value = null, array $options = []): HtmlString
    {
        return self::render(Html::date($name, $value)->attributes($options));
    }

    public static function file($name, array $options = []): HtmlString
    {
        return self::render(Html::file($name)->attributes($options));
    }

    public static function textarea($name, $value = null, array $options = []): HtmlString
    {
        // spatie::textarea injeta $value raw via ->html($value) — XSS se user data passar direto.
        // laravelcollective sempre escapa. Replicar aqui.
        $escaped = e((string) ($value ?? ''));
        $el = Html::textarea($name)->value($escaped);

        return self::render($el->attributes($options));
    }

    public static function select($name, $list = [], $selected = null, array $options = []): HtmlString
    {
        // laravelcollective aceitava array, Collection, ou qualquer iterable.
        // Spatie's options() tambem aceita iterable. Convertemos pra array pra garantir.
        if ($list instanceof \Illuminate\Support\Collection || $list instanceof \Illuminate\Contracts\Support\Arrayable) {
            $list = $list->toArray();
        } elseif ($list instanceof \Traversable) {
            $list = iterator_to_array($list);
        } elseif (! is_array($list)) {
            $list = (array) $list;
        }

        // laravelcollective tratava `placeholder` key no array de options como special:
        // virava <option value="">TEXT</option> no topo (default-selected se nenhum value bate).
        // Spatie NAO tem esse comportamento — sem tratamento, `placeholder` virava atributo HTML
        // do <select>, e o primeiro option REAL ficava default-selected — bug grave nos filtros
        // do UltimatePOS onde primeira brand/category/tax virava filtro ativo automatico.
        $placeholder = null;
        if (array_key_exists('placeholder', $options)) {
            $placeholder = $options['placeholder'];
            unset($options['placeholder']);
        }

        $el = Html::select($name, $list, $selected);
        if ($placeholder !== null) {
            $el = $el->placeholder((string) $placeholder);
        }

        return self::render($el->attributes($options));
    }

    public static function checkbox($name, $value = 1, $checked = false, array $options = []): HtmlString
    {
        $el = Html::checkbox($name, $checked, $value);
        if ($checked) {
            $el = $el->attribute('checked', 'checked');
        }

        return self::render($el->attributes($options));
    }

    public static function radio($name, $value = null, $checked = false, array $options = []): HtmlString
    {
        $el = Html::radio($name, $checked, $value);
        if ($checked) {
            $el = $el->attribute('checked', 'checked');
        }

        return self::render($el->attributes($options));
    }

    public static function label($name, $text = null, array $options = []): HtmlString
    {
        // spatie nao escapa conteudo de label — laravelcollective sempre escapou (seguranca + entities UTF-8)
        $escaped = e((string) ($text ?? ''));

        return self::render(Html::label($escaped, $name)->attributes($options));
    }

    public static function submit($text = null, array $options = []): HtmlString
    {
        // laravelcollective: <input type="submit" value="...">
        // spatie default (Html::submit): <button type="submit">...</button>
        // Usar Html::input direto pra preservar <input>.
        $el = Html::input('submit', null, $text);

        return self::render($el->attributes($options));
    }

    /**
     * Form::open(['url' => ..., 'method' => 'post', 'files' => true, ...])
     *
     * Chaves internas (nao viram atributo HTML): url, route, action, method, files.
     * Renderiza <form> + CSRF token + _method spoof (PUT/PATCH/DELETE -> hidden input).
     *
     * laravelcollective default: accept-charset="UTF-8". Mantido pra paridade.
     */
    public static function open(array $options = []): HtmlString
    {
        $method = strtoupper((string) ($options['method'] ?? 'POST'));
        $httpMethod = in_array($method, ['GET', 'POST'], true) ? $method : 'POST';
        $spoofedMethod = in_array($method, ['PUT', 'PATCH', 'DELETE'], true) ? $method : null;

        $action = '';
        if (isset($options['url'])) {
            $action = is_array($options['url'])
                ? url($options['url'][0], array_slice($options['url'], 1))
                : (string) $options['url'];
        } elseif (isset($options['route'])) {
            $action = is_array($options['route'])
                ? route($options['route'][0], array_slice($options['route'], 1))
                : route($options['route']);
        } elseif (isset($options['action'])) {
            $action = is_array($options['action'])
                ? action($options['action'][0], array_slice($options['action'], 1))
                : action($options['action']);
        }

        $enctype = $options['enctype'] ?? null;
        if (! empty($options['files'])) {
            $enctype = 'multipart/form-data';
        }

        $internal = ['url', 'route', 'action', 'method', 'files', 'enctype'];
        $attrs = array_diff_key($options, array_flip($internal));

        $html = sprintf(
            '<form method="%s" action="%s" accept-charset="UTF-8"',
            $httpMethod,
            e($action)
        );
        if ($enctype) {
            $html .= sprintf(' enctype="%s"', e($enctype));
        }
        foreach ($attrs as $k => $v) {
            $html .= is_int($k)
                ? ' ' . e($v)
                : sprintf(' %s="%s"', $k, e((string) $v));
        }
        $html .= '>';

        if ($httpMethod !== 'GET') {
            $html .= csrf_field();
        }
        if ($spoofedMethod) {
            $html .= sprintf('<input name="_method" type="hidden" value="%s">', $spoofedMethod);
        }

        return new HtmlString($html);
    }

    public static function close(): HtmlString
    {
        return new HtmlString('</form>');
    }

    public static function token(): HtmlString
    {
        return new HtmlString(csrf_field());
    }

    protected static function render($element): HtmlString
    {
        return new HtmlString($element->render());
    }
}
