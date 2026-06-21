<?php

declare(strict_types=1);

/**
 * Pest — ErrorReporter: cano do S0 + rate-limit + render() (Fase 1 · E-1).
 *
 * Cobre "PRONTO QUANDO" do handoff:
 *  - S0 dispara S0Alert UMA vez por dedupKey na janela (rate-limit)
 *  - S2/S3 NÃO disparam
 *  - sem ERROR_S0_WEBHOOK → degrada pra log, sem exceção
 *  - render() pro operador devolve operatorMessage e NÃO vaza trace
 *
 * Sem dependência de MySQL: mcp_audit_log write é guarded por Schema::hasTable
 * (ausente no sqlite de teste → skip), OTel é no-op, e usamos Http::fake + Cache.
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */

use App\Exceptions\Handler;
use App\Support\Errors\Audience;
use App\Support\Errors\ClassifiedError;
use App\Support\Errors\ErrorClassifier;
use App\Support\Errors\ErrorReporter;
use App\Support\Errors\Severity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FakeS0Payment extends RuntimeException implements ClassifiedError
{
    public function severity(): Severity { return Severity::S0; }

    public function audience(): Audience { return Audience::CONSTRUTOR; }

    public function owner(): string { return 'pagamento'; }

    public function operatorMessage(): string { return 'Pagamento indisponível agora — tente em instantes.'; }
}

class FakeS3Noise extends RuntimeException implements ClassifiedError
{
    public function severity(): Severity { return Severity::S3; }

    public function audience(): Audience { return Audience::OPERADOR; }

    public function owner(): string { return 'app'; }

    public function operatorMessage(): string { return 'Tente novamente.'; }
}

beforeEach(function () {
    config(['errors.s0_channel' => 'https://hooks.slack.com/services/T1/B2/secret']);
    config(['errors.s0_window_minutes' => 15]);
    Http::fake(['hooks.slack.com/*' => Http::response(['ok' => true], 200)]);
});

it('exceção S0 dispara S0Alert UMA vez por dedupKey na janela', function () {
    $reporter = new ErrorReporter;
    $e = new FakeS0Payment('gateway fora');

    $reporter->report($e);
    $reporter->report($e); // reincidência na mesma janela — NÃO repete

    Http::assertSentCount(1);
    Http::assertSent(fn ($req) => str_contains($req->url(), 'hooks.slack.com')
        && str_contains($req->data()['text'], 'S0'));
});

it('S3 (ruído) NÃO dispara alerta', function () {
    (new ErrorReporter)->report(new FakeS3Noise('ruído conhecido'));

    Http::assertNothingSent();
});

it('sem ERROR_S0_WEBHOOK degrada pra log, sem exceção e sem HTTP', function () {
    config(['errors.s0_channel' => null]);
    $reporter = new ErrorReporter;

    expect(fn () => $reporter->report(new FakeS0Payment('x')))->not->toThrow(Throwable::class);
    Http::assertNothingSent();
});

it('render() pro operador devolve operatorMessage e NÃO vaza trace (JSON)', function () {
    config(['app.debug' => false]);

    $request = Request::create('/api/x', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    $handler = app(Handler::class);

    $response = $handler->render($request, new FakeS0Payment('segredo-trace-xyz'));
    $body = $response->getContent();

    expect($response->getStatusCode())->toBe(500)
        ->and(json_decode($body, true)['message'])->toBe('Pagamento indisponível agora — tente em instantes.')
        ->and($body)->not->toContain('segredo-trace-xyz')
        ->and($body)->not->toContain('FakeS0Payment');
});

it('render() não assume erros esperados (deixa o framework tratar)', function () {
    config(['app.debug' => false]);

    $request = Request::create('/api/x', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    $c = (new ErrorClassifier)->classify(new RuntimeException('qualquer'), $request);

    // RuntimeException genérica = S3 → render() NÃO assume.
    expect(ErrorReporter::shouldRenderOperatorMessage($c, $request))->toBeFalse();
});
