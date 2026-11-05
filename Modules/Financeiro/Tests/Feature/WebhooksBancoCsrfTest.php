<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;

uses(Tests\TestCase::class);

/**
 * Webhooks de banco passam pelo CSRF sem token; o resto do app continua exigindo token.
 *
 * Medido em produção em 2026-11-05 (`curl -sv -X POST`, sem token): `paymentgateway/webhooks/*`,
 * `webhooks/inter/{credentialId}` e `webhooks/inter/pix/{businessId}` devolviam 419. O `$except`
 * tinha só `/webhook/*` (singular), e as rotas de banco usam o plural. O banco não manda token
 * CSRF; a autenticação desses endpoints é a assinatura HMAC conferida no controller.
 * Auditoria Financeiro 2026-11-05, lote 0, PR 0.3.
 *
 * O Laravel pula o CSRF quando roda teste (`runningUnitTests()`), então o teste usa uma
 * subclasse que desliga esse atalho e chama o middleware de verdade.
 */
function csrfLigado(): VerifyCsrfToken
{
    return new class(app(), app('encrypter')) extends VerifyCsrfToken
    {
        protected function runningUnitTests()
        {
            return false;
        }
    };
}

function csrfPost(string $uri): Response
{
    $request = Request::create($uri, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
    $request->setLaravelSession(app('session.store'));

    return csrfLigado()->handle($request, fn () => new Response('chegou no controller', 200));
}

it('webhooks de banco chegam ao controller sem token CSRF', function (string $uri) {
    expect(csrfPost($uri)->getContent())->toBe('chegou no controller');
})->with([
    'PaymentGateway Asaas' => '/paymentgateway/webhooks/asaas/98',
    'PaymentGateway Inter' => '/paymentgateway/webhooks/inter/98',
    'PaymentGateway C6' => '/paymentgateway/webhooks/c6/98',
    'PaymentGateway BCB Pix' => '/paymentgateway/webhooks/bcb-pix/98',
    'PaymentGateway Pagar.me' => '/paymentgateway/webhooks/pagarme/98',
    'PaymentGateway Sicoob' => '/paymentgateway/webhooks/sicoob-api/98',
    'Inter Pix por credencial' => '/webhooks/inter/12',
    'RecurringBilling Inter Pix' => '/webhooks/inter/pix/98',
    'Woocommerce (já liberado antes)' => '/webhook/order-created/98',
]);

it('rota autenticada do Financeiro continua exigindo token CSRF (controle)', function (string $uri) {
    expect(fn () => csrfPost($uri))->toThrow(TokenMismatchException::class);
})->with([
    'conciliação' => '/financeiro/conciliacao/1/match',
    'baixa de contas a pagar' => '/financeiro/contas-pagar/1/pagar',
    'prefixo parecido sem barra' => '/webhooksx/inter/1',
]);
