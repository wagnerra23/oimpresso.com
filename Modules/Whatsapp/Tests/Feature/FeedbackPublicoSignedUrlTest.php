<?php

declare(strict_types=1);

// @covers-us US-INFRA-002

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\URL;

uses(Tests\TestCase::class);

/**
 * FeedbackPublicoSignedUrlTest — Tier 0 IRREVOGÁVEL (ADR 0093) · US-INFRA-002 · ADR 0334.
 *
 * O contrato que estes testes travam: **numa rota SEM auth, o business_id não pode vir do
 * input**. O global scope ScopeByBusiness é NO-OP aqui (retorna cedo em `!auth()->check()`
 * — ScopeByBusiness.php:26), então nada no Eloquent nos protege: quem isola o tenant é o
 * HMAC da URL assinada. Se estes testes ficarem verdes com a assinatura desligada, o canal
 * público virou vazamento cross-tenant.
 *
 * Não tocam DB de propósito: o middleware `signed` roda ANTES do controller, então o 403 é
 * decidido sem query. Isso os deixa rodar em qualquer runner (e falhar por motivo único).
 *
 * biz=1 e biz=99 conforme ADR 0101 (nunca biz=4 — cliente real ROTA LIVRE).
 *
 * Cobre UC-FBP-01, UC-FBP-02, UC-FBP-03, UC-FBP-04, UC-FBP-05 (FeedbackPublico.casos.md)
 * — G-2 rastreabilidade caso↔teste (ADR 0264):
 *   001 → UC-FBP-05  URL assinada válida NÃO dá 403 (a assinatura é aceita — release)
 *   002 → UC-FBP-03  sem assinatura → 403 (bite)
 *   003 → UC-FBP-01  ?biz adulterado 1→99 → 403 — o teste-âncora do isolamento (bite)
 *   004 → UC-FBP-04  assinatura expirada → 403 (bite)
 *   005 → UC-FBP-03  POST sem assinatura → 403 (bite — a escrita também é coberta)
 *   006 → UC-FBP-02  POST com ?biz adulterado → 403 (bite)
 *
 * @see resources/js/Pages/Whatsapp/FeedbackPublico.casos.md
 * @see memory/requisitos/Whatsapp/RUNBOOK-feedback-publico.md §3
 */

/**
 * Desliga SÓ o throttle — o `signed` (o que estamos medindo) continua ativo.
 *
 * Sem isto o arquivo é FLAKY e mente: o rate limiter guarda estado em cache entre
 * execuções, então o `throttle:10,1` das rotas de POST estoura depois de algumas rodadas e
 * devolve **429**, não 403 — os testes 005/006 falham por motivo ERRADO (rate limit), dando
 * a impressão de que a assinatura quebrou. Pego rodando o arquivo em série no CT 100:
 * 6/6 verde → 5 falhas → sozinho passa. Um teste que falha pelo motivo errado é tão ruim
 * quanto um que passa pelo motivo errado.
 *
 * O throttle das rotas públicas é real e continua lá (Routes/web.php) — só não é o alvo
 * DESTE arquivo, e misturá-lo aqui acopla o veredito Tier 0 a uma variável que não é a dele.
 */
beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

it('001 · URL assinada válida é aceita pelo middleware signed', function () {
    $url = URL::temporarySignedRoute('feedback.form', now()->addDays(30), ['biz' => 1]);

    $response = $this->get($url);

    // Não asserta 200: sem business id=1 semeado o controller dá 404 — o que importa aqui
    // é que a ASSINATURA passou (403 = assinatura recusada).
    expect($response->status())->not->toBe(403);
});

it('002 · sem assinatura → 403 (o link não é adivinhável)', function () {
    $this->get('/feedback?biz=1')->assertStatus(403);
});

it('003 · TIER 0 — adulterar ?biz=1 → 99 quebra a assinatura (403)', function () {
    $url = URL::temporarySignedRoute('feedback.form', now()->addDays(30), ['biz' => 1]);

    // O atacante tem um link legítimo do biz=1 e troca o tenant na query.
    $adulterada = str_replace('biz=1', 'biz=99', $url);

    expect($adulterada)->toContain('biz=99');   // a troca de fato aconteceu…
    $this->get($adulterada)->assertStatus(403); // …e o HMAC recusa.
});

it('004 · assinatura expirada → 403 (validade de 30d é real)', function () {
    $url = URL::temporarySignedRoute('feedback.form', now()->subMinute(), ['biz' => 1]);

    $this->get($url)->assertStatus(403);
});

it('005 · POST sem assinatura → 403 (escrita não entra por fora)', function () {
    $this->post('/feedback?biz=1', [
        'literal' => 'tentativa de gravar sem link assinado',
        'severity_self_reported' => 3,
    ])->assertStatus(403);
});

it('006 · TIER 0 — POST com ?biz adulterado → 403 (não grava no tenant do vizinho)', function () {
    $url = URL::temporarySignedRoute('feedback.form', now()->addDays(30), ['biz' => 1]);
    $adulterada = str_replace('biz=1', 'biz=99', $url);

    $this->post($adulterada, [
        'literal' => 'tentativa de gravar no business de outro',
        'severity_self_reported' => 3,
    ])->assertStatus(403);
});
