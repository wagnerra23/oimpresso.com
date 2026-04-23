<?php

/**
 * Equivalence tests — App\View\Helpers\Form (shim sobre spatie/laravel-html)
 * deve produzir HTML equivalente ao laravelcollective nos testes do
 * FormHelperSnapshotTest.
 *
 * Se todos verdes aqui E no baseline → shim pronto pra virar alias global.
 */

use App\View\Helpers\Form;

beforeEach(function () {
    config(['session.driver' => 'array']);
});

it('shim Form::text renderiza input type=text com value e attributes', function () {
    $html = (string) Form::text('username', 'wagner', ['class' => 'form-control', 'id' => 'u']);

    expect($html)
        ->toContain('type="text"')
        ->toContain('name="username"')
        ->toContain('value="wagner"')
        ->toContain('class="form-control"')
        ->toContain('id="u"');
});

it('shim Form::email renderiza input type=email', function () {
    $html = (string) Form::email('contact_email', 'a@b.com', ['required']);
    expect($html)
        ->toContain('type="email"')
        ->toContain('name="contact_email"')
        ->toContain('value="a@b.com"')
        ->toContain('required');
});

it('shim Form::password nunca renderiza value com conteudo (security)', function () {
    // Spatie renderiza value vazio como "value" (HTML5 boolean-style), laravelcollective
    // como value="" (XHTML). Funcionalmente equivalente — a invariante de seguranca que
    // importa e: NENHUM value com conteudo. Password nunca pode ser pre-preenchido.
    $html = (string) Form::password('senha', ['class' => 'x']);
    expect($html)
        ->toContain('type="password"')
        ->toContain('name="senha"')
        ->not->toMatch('/value="[^"]+"/'); // nenhum value com conteudo
});

it('shim Form::hidden renderiza input type=hidden', function () {
    $html = (string) Form::hidden('user_id', 42);
    expect($html)
        ->toContain('type="hidden"')
        ->toContain('name="user_id"')
        ->toContain('value="42"');
});

it('shim Form::textarea renderiza com conteudo escapado', function () {
    $html = (string) Form::textarea('bio', 'linha 1<script>', ['rows' => 4]);
    expect($html)
        ->toContain('<textarea')
        ->toContain('name="bio"')
        ->toContain('rows="4"')
        ->toContain('linha 1&lt;script&gt;');
});

it('shim Form::select renderiza option selected correto', function () {
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

it('shim Form::select com null selected nao marca nenhuma option', function () {
    $options = ['a' => 'A', 'b' => 'B'];
    $html = (string) Form::select('x', $options, null);
    expect($html)->not->toContain('selected=');
});

it('shim Form::select aceita Illuminate\\Support\\Collection (regressao)', function () {
    // Bug achado em /business/register: Eloquent/config retorna Collection.
    // laravelcollective aceitava, shim so aceitava array. Fix: converter internamente.
    $collection = collect(['BR' => 'Brasil', 'PT' => 'Portugal']);
    $html = (string) Form::select('pais', $collection, 'BR');

    expect($html)
        ->toContain('<select')
        ->toContain('name="pais"')
        ->toContain('<option value="BR" selected="selected">Brasil</option>')
        ->toContain('<option value="PT">Portugal</option>');
});

it('shim Form::select aceita objeto Arrayable', function () {
    $arrayable = new class implements \Illuminate\Contracts\Support\Arrayable {
        public function toArray(): array { return ['a' => 'Alpha', 'b' => 'Beta']; }
    };
    $html = (string) Form::select('x', $arrayable, 'b');
    expect($html)->toContain('<option value="b" selected="selected">Beta</option>');
});

it('shim Form::select com placeholder vira <option> prepended (regressao /products)', function () {
    // Bug real achado em /products: filtros brand_id/category_id/tax_id/unit_id
    // usavam Form::select(..., null, ['placeholder' => 'All']). Spatie NAO suporta
    // `placeholder` no attrs — virava <select placeholder="All"> e primeiro option
    // real ficava default-selected, quebrando o datatable com filtros acidentais
    // (brand_id=1 = "Imprimax" applied as filter, recordsTotal=0).
    $html = (string) Form::select('brand_id', ['1' => 'Imprimax', '2' => 'WR2'], null, [
        'placeholder' => 'All',
        'class' => 'select2',
    ]);

    expect($html)
        ->toContain('<select')
        ->toContain('class="select2"')
        ->not->toContain('placeholder="All"')          // nao virou atributo HTML invalido
        ->toContain('>All</option>')                    // option com label do placeholder
        ->toContain('>Imprimax</option>')               // options reais presentes
        ->not->toMatch('/value="1"\s+selected/');       // primeiro option REAL nao fica default-selected
});

it('shim Form::checkbox renderiza checked correto', function () {
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

it('shim Form::radio renderiza checked correto', function () {
    $html = (string) Form::radio('gender', 'F', true, ['id' => 'rf']);
    expect($html)
        ->toContain('type="radio"')
        ->toContain('name="gender"')
        ->toContain('value="F"')
        ->toContain('checked="checked"')
        ->toContain('id="rf"');
});

it('shim Form::label renderiza label com for', function () {
    $html = (string) Form::label('email', 'E-mail:', ['class' => 'control-label']);
    expect($html)
        ->toContain('<label')
        ->toContain('for="email"')
        ->toContain('E-mail:')
        ->toContain('class="control-label"');
});

it('shim Form::submit renderiza button type=submit', function () {
    $html = (string) Form::submit('Salvar', ['class' => 'btn btn-primary']);
    expect($html)
        ->toContain('type="submit"')
        ->toContain('value="Salvar"')
        ->toContain('class="btn btn-primary"');
});

it('shim Form::number renderiza input numerico com min/max/step', function () {
    $html = (string) Form::number('qty', 10, ['min' => 1, 'max' => 99, 'step' => 1]);
    expect($html)
        ->toContain('type="number"')
        ->toContain('name="qty"')
        ->toContain('value="10"')
        ->toContain('min="1"')
        ->toContain('max="99"')
        ->toContain('step="1"');
});

it('shim Form::date renderiza input type=date', function () {
    $html = (string) Form::date('birth', '1985-05-15');
    expect($html)
        ->toContain('type="date"')
        ->toContain('name="birth"')
        ->toContain('value="1985-05-15"');
});

it('shim Form::file renderiza input type=file (nunca tem value)', function () {
    $html = (string) Form::file('avatar', ['accept' => 'image/*']);
    expect($html)
        ->toContain('type="file"')
        ->toContain('name="avatar"')
        ->toContain('accept="image/*"')
        ->not->toContain('value=');
});
