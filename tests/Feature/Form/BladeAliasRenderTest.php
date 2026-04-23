<?php

/**
 * Smoke test do alias global Form:: em contexto Blade real.
 *
 * Quando blade renderiza {!! Form::text(...) !!}, o "Form" resolve via alias
 * em config/app.php. Este teste prova que qualquer que seja o alvo do alias
 * (Collective ou App\View\Helpers\Form), a renderizacao final em HTML inclui
 * todos os elementos essenciais do kitchen sink.
 *
 * Baseline: passa com Collective\Html\FormFacade.
 * Pos-swap: deve passar com App\View\Helpers\Form sem mudar asserts.
 */

use Illuminate\Support\Facades\View;

beforeEach(function () {
    config(['session.driver' => 'array']);
    // Registra path do fixture pra blade encontrar
    View::addLocation(base_path('tests/fixtures/views'));
});

it('Blade renderiza form_kitchen_sink com todos os helpers Form::', function () {
    $html = View::make('form_kitchen_sink')->render();

    // Confirma que cada helper produziu tag correspondente
    expect($html)
        ->toContain('<label')
        ->toContain('for="username"')
        // Shim usa htmlspecialchars (e()) — acentos UTF-8 ficam raw. Browser renderiza igual.
        ->toContain('Usuário:')

        ->toContain('type="text"')
        ->toContain('name="username"')
        ->toContain('value="wagner"')
        ->toContain('class="form-control"')

        ->toContain('type="email"')
        ->toContain('name="email"')
        ->toContain('value="a@b.com"')

        ->toContain('type="password"')
        ->toContain('name="senha"')

        ->toContain('type="hidden"')
        ->toContain('name="user_id"')
        ->toContain('value="42"')

        ->toContain('<textarea')
        ->toContain('rows="3"')
        ->toContain('linha&lt;script&gt;') // HTML-escaped

        ->toContain('<select')
        ->toContain('name="pais"')
        ->toContain('<option value="PT" selected="selected">Portugal</option>')

        ->toContain('type="checkbox"')
        ->toContain('name="terms"')
        ->toContain('checked="checked"')

        ->toContain('type="radio"')
        ->toContain('name="gender"')

        ->toContain('type="number"')
        ->toContain('name="qty"')
        ->toContain('min="1"')
        ->toContain('max="99"')

        ->toContain('type="date"')
        ->toContain('name="birth"')
        ->toContain('value="1985-05-15"')

        ->toContain('type="file"')
        ->toContain('name="avatar"')
        ->toContain('accept="image/*"')

        ->toContain('type="submit"')
        ->toContain('value="Salvar"');
});
