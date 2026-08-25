<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Modules\Cms\Http\Requests\StoreCmsPageRequest;
use Modules\Cms\Http\Requests\SubmitContactFormRequest;
use Modules\Cms\Http\Requests\UpdateCmsPageRequest;

uses(Tests\TestCase::class);

/**
 * Wave 18 — Saturação D2 Pest coverage (Cms 63 → ≥90).
 *
 * Cobertura unitária das regras de validação de cada FormRequest do módulo Cms.
 * Foco: garantir que as rules() retornam o contrato esperado (defesa em profundidade
 * contra alterações inadvertidas).
 *
 * Não boota HTTP — usa Validator::make() direto contra as rules() de cada request.
 *
 * Multi-tenant: FormRequests Cms são neutros de business_id (cms_pages global hoje
 * por gap US-CMS-002); estes testes validam comportamento intrínseco de validação.
 */

// ---------- StoreCmsPageRequest ----------

it('011. StoreCmsPageRequest exige title obrigatório', function () {
    $rules = (new StoreCmsPageRequest)->rules();
    $v = Validator::make([], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('title'))->toBeTrue();
});

it('012. StoreCmsPageRequest aceita payload válido mínimo', function () {
    $rules = (new StoreCmsPageRequest)->rules();
    $v = Validator::make(['title' => 'Minha pagina', 'type' => 'page'], $rules);
    expect($v->fails())->toBeFalse();
});

it('013. StoreCmsPageRequest rejeita type fora do whitelist', function () {
    $rules = (new StoreCmsPageRequest)->rules();
    $v = Validator::make(['title' => 'x', 'type' => 'malicioso_xss'], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('type'))->toBeTrue();
});

it('014. StoreCmsPageRequest rejeita priority não-inteiro', function () {
    $rules = (new StoreCmsPageRequest)->rules();
    $v = Validator::make(['title' => 'x', 'priority' => 'abc'], $rules);
    expect($v->fails())->toBeTrue();
});

it('015. StoreCmsPageRequest aceita meta_description até 500 chars', function () {
    $rules = (new StoreCmsPageRequest)->rules();
    $v = Validator::make([
        'title' => 'x',
        'meta_description' => str_repeat('a', 501),
    ], $rules);
    expect($v->fails())->toBeTrue();
});

// ---------- UpdateCmsPageRequest ----------

it('016. UpdateCmsPageRequest permite payload parcial (sometimes)', function () {
    $rules = (new UpdateCmsPageRequest)->rules();
    $v = Validator::make(['priority' => 5], $rules);
    expect($v->fails())->toBeFalse();
});

it('017. UpdateCmsPageRequest rejeita feature_image que não seja imagem', function () {
    $rules = (new UpdateCmsPageRequest)->rules();
    // feature_image como string crua (não UploadedFile) é rejeitado em rules de file/image
    $v = Validator::make([
        'title' => 'ok',
        'feature_image' => 'nao-eh-arquivo.txt',
    ], $rules);
    expect($v->fails())->toBeTrue();
});

// ---------- SubmitContactFormRequest ----------

it('018. SubmitContactFormRequest exige nome+email+message', function () {
    $rules = (new SubmitContactFormRequest)->rules();
    $v = Validator::make([], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('name'))->toBeTrue();
    expect($v->errors()->has('email'))->toBeTrue();
    expect($v->errors()->has('message'))->toBeTrue();
});

it('019. SubmitContactFormRequest rejeita email malformado', function () {
    $rules = (new SubmitContactFormRequest)->rules();
    $v = Validator::make([
        'name' => 'Larissa',
        'email' => 'email-invalido',
        'message' => 'Quero comprar',
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('email'))->toBeTrue();
});

it('020. SubmitContactFormRequest rejeita honeypot _gotcha preenchido (anti-bot)', function () {
    $rules = (new SubmitContactFormRequest)->rules();
    $v = Validator::make([
        'name' => 'Larissa',
        'email' => 'larissa@exemplo.com',
        'message' => 'msg',
        '_gotcha' => 'sou-um-bot',
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->has('_gotcha'))->toBeTrue();
});

it('021. SubmitContactFormRequest aceita payload humano válido', function () {
    $rules = (new SubmitContactFormRequest)->rules();
    $v = Validator::make([
        'name' => 'Larissa',
        'email' => 'larissa@exemplo.com',
        'message' => 'Quero conhecer o oimpresso',
    ], $rules);
    expect($v->fails())->toBeFalse();
});

it('022. SubmitContactFormRequest limita message a 5000 chars (anti-flood)', function () {
    $rules = (new SubmitContactFormRequest)->rules();
    $v = Validator::make([
        'name' => 'x',
        'email' => 'x@x.com',
        'message' => str_repeat('a', 5001),
    ], $rules);
    expect($v->fails())->toBeTrue();
});

// ---------- Domínio de `type` (page · blog · testimonial) ----------

/**
 * O whitelist de `type` recusava o domínio REAL do módulo: validava `page,post,banner`
 * enquanto as consultas leem `blog` (CmsController@getBlogList/viewBlog + CmsServiceProvider)
 * e `testimonial` (CmsController@home). Salvar publicação de blog ou depoimento pelo painel
 * caía em erro de validação. `post` e `banner` não eram lidos por consulta nenhuma.
 */
it('023. Store e Update aceitam os três tipos do domínio do módulo', function (string $tipo) {
    $store = Validator::make(['title' => 'x', 'type' => $tipo], (new StoreCmsPageRequest)->rules());
    expect($store->fails())->toBeFalse("StoreCmsPageRequest recusou o tipo {$tipo}");

    $update = Validator::make(['type' => $tipo], (new UpdateCmsPageRequest)->rules());
    expect($update->fails())->toBeFalse("UpdateCmsPageRequest recusou o tipo {$tipo}");
})->with(['page', 'blog', 'testimonial']);
