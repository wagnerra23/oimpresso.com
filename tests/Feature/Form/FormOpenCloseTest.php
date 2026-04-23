<?php

/**
 * Tests do shim para Form::open(), Form::close(), Form::token().
 *
 * Bug achado no browser test (/login): shim nao tinha esses metodos mas sao
 * usados em 535 chamadas em 260 arquivos. Sem eles, TODA view com form crasha.
 */

use App\View\Helpers\Form;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    config(['session.driver' => 'array']);
    View::addLocation(base_path('tests/fixtures/views'));
});

it('Form::open gera <form> com method POST e action', function () {
    $html = (string) Form::open(['url' => '/users', 'id' => 'f']);

    expect($html)
        ->toContain('<form')
        ->toContain('method="POST"')
        ->toContain('action="/users"')
        ->toContain('accept-charset="UTF-8"')
        ->toContain('id="f"')
        ->toContain('name="_token"'); // csrf
});

it('Form::open com method PUT faz spoofing (<input _method>)', function () {
    $html = (string) Form::open(['url' => '/users/42', 'method' => 'PUT']);

    expect($html)
        ->toContain('method="POST"') // <form> fica POST (spoofing)
        ->toContain('<input name="_method" type="hidden" value="PUT">')
        ->toContain('name="_token"');
});

it('Form::open com files=true adiciona enctype multipart', function () {
    $html = (string) Form::open(['url' => '/upload', 'files' => true]);
    expect($html)->toContain('enctype="multipart/form-data"');
});

it('Form::open method GET nao emite CSRF token', function () {
    $html = (string) Form::open(['url' => '/search', 'method' => 'GET']);
    expect($html)
        ->toContain('method="GET"')
        ->not->toContain('name="_token"');
});

it('Form::open com route resolve URL via helper route()', function () {
    // Usa rota ja existente da app (home). Nao precisa registrar nova.
    $routes = app('router')->getRoutes();
    $firstNamedRoute = collect($routes->getRoutesByName())->keys()->first();
    if (! $firstNamedRoute) {
        $this->markTestSkipped('Sem rotas nomeadas na app — teste sem fixture confiavel.');
    }

    $html = (string) Form::open(['route' => $firstNamedRoute]);
    expect($html)->toContain('action=');
});

it('Form::close gera </form>', function () {
    expect((string) Form::close())->toBe('</form>');
});

it('Form::token gera <input _token>', function () {
    $html = (string) Form::token();
    expect($html)
        ->toContain('name="_token"')
        ->toContain('type="hidden"')
        ->toContain('value="'); // csrf value presente
});

it('Blade renderiza form_open_close com todos os 4 forms', function () {
    $html = View::make('form_open_close')->render();

    expect($html)
        // post_form — POST com CSRF
        ->toContain('id="post_form"')
        ->toContain('method="POST" action="/users"')

        // put_form — POST com _method=PUT
        ->toContain('id="put_form"')
        ->toContain('<input name="_method" type="hidden" value="PUT">')

        // del_form — DELETE + multipart
        ->toContain('id="del_form"')
        ->toContain('enctype="multipart/form-data"')
        ->toContain('<input name="_method" type="hidden" value="DELETE">')

        // get_form — GET sem CSRF
        ->toContain('id="get_form"')
        ->toContain('method="GET"')

        // close emite </form>
        ->toContain('</form>');

    // get_form não deve ter CSRF/_method DENTRO (difícil testar fatia específica,
    // mas total de _token deve ser 3 (post/put/del) + 1 standalone = 4
    expect(substr_count($html, 'name="_token"'))->toBe(4);
});
