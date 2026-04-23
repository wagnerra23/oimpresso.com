<?php

/**
 * Snapshot tests dos helpers Form:: (laravelcollective/html).
 *
 * Objetivo: travar HTML exato produzido por cada chamada Form::*
 * antes da migração pra spatie/laravel-html. Se a nova lib gerar HTML
 * idêntico (via shim App\View\Helpers\Form), esses testes continuam
 * verdes — garantia de que as 6.433 chamadas em blade views não
 * mudam de comportamento.
 *
 * Form::* retorna HtmlString — casta pra (string) explícito.
 */

use Collective\Html\FormFacade as Form;

beforeEach(function () {
    config(['session.driver' => 'array']);
});

it('Form::text renderiza input type=text com value e attributes', function () {
    $html = (string) Form::text('username', 'wagner', ['class' => 'form-control', 'id' => 'u']);

    expect($html)
        ->toContain('type="text"')
        ->toContain('name="username"')
        ->toContain('value="wagner"')
        ->toContain('class="form-control"')
        ->toContain('id="u"');
});

it('Form::email renderiza input type=email', function () {
    $html = (string) Form::email('contact_email', 'a@b.com', ['required']);
    expect($html)
        ->toContain('type="email"')
        ->toContain('name="contact_email"')
        ->toContain('value="a@b.com"')
        ->toContain('required');
});

it('Form::password sempre renderiza com value vazio (security)', function () {
    // laravelcollective emite value="" (vazio) — password nunca pode ser pré-preenchido.
    // Migração spatie precisa garantir que continua assim.
    $html = (string) Form::password('senha', ['class' => 'x']);
    expect($html)
        ->toContain('type="password"')
        ->toContain('name="senha"')
        ->toContain('value=""')
        ->not->toMatch('/value="[^"]+"/'); // nenhum value com conteudo
});

it('Form::hidden renderiza input type=hidden', function () {
    $html = (string) Form::hidden('user_id', 42);
    expect($html)
        ->toContain('type="hidden"')
        ->toContain('name="user_id"')
        ->toContain('value="42"');
});

it('Form::textarea renderiza com conteudo escapado', function () {
    $html = (string) Form::textarea('bio', 'linha 1<script>', ['rows' => 4]);
    expect($html)
        ->toContain('<textarea')
        ->toContain('name="bio"')
        ->toContain('rows="4"')
        ->toContain('linha 1&lt;script&gt;');
});

it('Form::select renderiza option selected correto', function () {
    $options = ['BR' => 'Brasil', 'PT' => 'Portugal', 'US' => 'USA'];
    $html = (string) Form::select('pais', $options, 'PT', ['class' => 'select2']);

    expect($html)
        ->toContain('<select')
        ->toContain('name="pais"')
        ->toContain('class="select2"')
        ->toContain('<option value="BR">Brasil</option>')
        ->toContain('<option value="PT" selected="selected">Portugal</option>')
        ->toContain('<option value="US">USA</option>');
});

it('Form::select com null selected nao marca nenhuma option', function () {
    $options = ['a' => 'A', 'b' => 'B'];
    $html = (string) Form::select('x', $options, null);
    expect($html)->not->toContain('selected=');
});

it('Form::checkbox renderiza checked correto', function () {
    $checked = (string) Form::checkbox('terms', 1, true);
    $unchecked = (string) Form::checkbox('terms', 1, false);

    expect($checked)
        ->toContain('type="checkbox"')
        ->toContain('name="terms"')
        ->toContain('value="1"')
        ->toContain('checked="checked"');

    expect($unchecked)
        ->toContain('type="checkbox"')
        ->not->toContain('checked=');
});

it('Form::radio renderiza checked correto', function () {
    $html = (string) Form::radio('gender', 'F', true, ['id' => 'rf']);
    expect($html)
        ->toContain('type="radio"')
        ->toContain('name="gender"')
        ->toContain('value="F"')
        ->toContain('checked="checked"')
        ->toContain('id="rf"');
});

it('Form::label renderiza label com for', function () {
    $html = (string) Form::label('email', 'E-mail:', ['class' => 'control-label']);
    expect($html)
        ->toContain('<label')
        ->toContain('for="email"')
        ->toContain('E-mail:')
        ->toContain('class="control-label"');
});

it('Form::submit renderiza button type=submit', function () {
    $html = (string) Form::submit('Salvar', ['class' => 'btn btn-primary']);
    expect($html)
        ->toContain('type="submit"')
        ->toContain('value="Salvar"')
        ->toContain('class="btn btn-primary"');
});

it('Form::number renderiza input numerico com min/max/step', function () {
    $html = (string) Form::number('qty', 10, ['min' => 1, 'max' => 99, 'step' => 1]);
    expect($html)
        ->toContain('type="number"')
        ->toContain('name="qty"')
        ->toContain('value="10"')
        ->toContain('min="1"')
        ->toContain('max="99"')
        ->toContain('step="1"');
});

it('Form::date renderiza input type=date', function () {
    $html = (string) Form::date('birth', '1985-05-15');
    expect($html)
        ->toContain('type="date"')
        ->toContain('name="birth"')
        ->toContain('value="1985-05-15"');
});

it('Form::file renderiza input type=file (nunca tem value)', function () {
    $html = (string) Form::file('avatar', ['accept' => 'image/*']);
    expect($html)
        ->toContain('type="file"')
        ->toContain('name="avatar"')
        ->toContain('accept="image/*"')
        ->not->toContain('value=');
});
