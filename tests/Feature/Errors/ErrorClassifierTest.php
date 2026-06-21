<?php

declare(strict_types=1);

/**
 * Pest — ErrorClassifier (Fase 1 · E-1, a régua na origem).
 *
 * Cobre "PRONTO QUANDO" do handoff:
 *  - mapeia cada exceção-exemplo do Mapa pra severity+audience corretos (tabelado)
 *  - detector cross-tenant → S0 (Tier-0)
 *  - operatorMessage nunca vaza trace
 *  - só o S0 interrompe humano
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */

use App\Support\Errors\Audience;
use App\Support\Errors\ClassifiedError;
use App\Support\Errors\Classification;
use App\Support\Errors\CrossTenantViolation;
use App\Support\Errors\ErrorClassifier;
use App\Support\Errors\Severity;
use Illuminate\Validation\ValidationException;

// ── Fixtures: exceções-exemplo do Mapa de Severidade ──────────────────

class FakeCrossTenant extends RuntimeException implements CrossTenantViolation {}

class FakeFiscalFail extends RuntimeException implements ClassifiedError
{
    public function severity(): Severity { return Severity::S1; }

    public function audience(): Audience { return Audience::CONSTRUTOR; }

    public function owner(): string { return 'fiscal'; }

    public function operatorMessage(): string { return 'Não consegui emitir a NF-e agora — salvei como rascunho.'; }
}

class FakeCertExpiring extends RuntimeException implements ClassifiedError
{
    public function severity(): Severity { return Severity::S2; }

    public function audience(): Audience { return Audience::AMBOS; }

    public function owner(): string { return 'plataforma'; }

    public function operatorMessage(): string { return 'Certificado a vencer.'; }
}

// ── Tabela: exceção → [severity esperada, audience esperada] ──────────

dataset('mapa', [
    'cross-tenant (S0 silencioso)' => [fn () => new FakeCrossTenant('biz alheio'), Severity::S0, Audience::CONSTRUTOR],
    'banco indisponível (S0)'      => [fn () => new PDOException('SQLSTATE[08006] [2002] Connection refused'), Severity::S0, Audience::CONSTRUTOR],
    'emissão fiscal (S1)'          => [fn () => new FakeFiscalFail, Severity::S1, Audience::CONSTRUTOR],
    'certificado a vencer (S2)'    => [fn () => new FakeCertExpiring, Severity::S2, Audience::AMBOS],
    'validação (S3 default)'       => [fn () => ValidationException::withMessages(['x' => 'inválido']), Severity::S3, Audience::OPERADOR],
    'genérica (S3 default)'        => [fn () => new RuntimeException('boom'), Severity::S3, Audience::OPERADOR],
]);

it('classifica cada exceção-exemplo do Mapa pra severity+audience corretos', function (Closure $make, Severity $sev, Audience $aud) {
    $c = (new ErrorClassifier)->classify($make());

    expect($c)->toBeInstanceOf(Classification::class)
        ->and($c->severity)->toBe($sev)
        ->and($c->audience)->toBe($aud);
})->with('mapa');

it('cross-tenant é sempre S0 e não vaza detalhe técnico pro operador', function () {
    $c = (new ErrorClassifier)->classify(new FakeCrossTenant('SELECT ... business_id=99'));

    expect($c->severity)->toBe(Severity::S0)
        ->and($c->owner)->toBe('plataforma')
        ->and($c->operatorMessage)->not->toContain('SELECT')
        ->and($c->operatorMessage)->not->toContain('business_id');
});

it('dedupKey é estável pra mesma exceção e muda entre classes', function () {
    $clf = new ErrorClassifier;
    $a = new RuntimeException('x');

    expect($clf->dedupKey($a))->toBe($clf->dedupKey($a))
        ->and($clf->dedupKey(new LogicException('x')))->not->toBe($clf->dedupKey($a));
});

it('operatorMessage nunca contém o trace nem a mensagem técnica', function () {
    $c = (new ErrorClassifier)->classify(new RuntimeException('SQLSTATE secret stacktrace'));

    expect($c->operatorMessage)->not->toContain('SQLSTATE')
        ->and($c->operatorMessage)->not->toContain('stacktrace');
});

it('só o S0 interrompe humano', function () {
    expect(Severity::S0->interrompeHumano())->toBeTrue()
        ->and(Severity::S1->interrompeHumano())->toBeFalse()
        ->and(Severity::S2->interrompeHumano())->toBeFalse()
        ->and(Severity::S3->interrompeHumano())->toBeFalse();
});
