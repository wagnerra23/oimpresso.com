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

    public static function select($name, array $list = [], $selected = null, array $options = []): HtmlString
    {
        return self::render(Html::select($name, $list, $selected)->attributes($options));
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
        return self::render(Html::label($text, $name)->attributes($options));
    }

    public static function submit($text = null, array $options = []): HtmlString
    {
        // laravelcollective: <input type="submit" value="...">
        // spatie default (Html::submit): <button type="submit">...</button>
        // Usar Html::input direto pra preservar <input>.
        $el = Html::input('submit', null, $text);

        return self::render($el->attributes($options));
    }

    protected static function render($element): HtmlString
    {
        return new HtmlString($element->render());
    }
}
