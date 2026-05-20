<?php

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeCartaCorrecaoService;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-013 — NfeCartaCorrecaoService unit tests.
 *
 * Validações in-process (sem DB) — cobertura broad de SEFAZ real fica no
 * Pest browser MCP pós-merge biz=1 (homologação SP cstat=135 sandbox).
 *
 * Aqui foca em:
 *   - validarEntrada texto correção 15-1000 chars (CONFAZ Art. 14)
 *   - validarEntrada n_seq_evento 1-20
 *   - cross-tenant guard (session biz ≠ param business_id)
 */

it('aplicar lança InvalidArgumentException pra texto correção <15 chars', function () {
    $service = new NfeCartaCorrecaoService(app(CertificadoService::class));
    expect(fn () => $service->aplicar(1, 999, 'curto', 1))
        ->toThrow(InvalidArgumentException::class, '15-1000 caracteres');
});

it('aplicar lança InvalidArgumentException pra texto correção >1000 chars', function () {
    $service = new NfeCartaCorrecaoService(app(CertificadoService::class));
    expect(fn () => $service->aplicar(1, 999, str_repeat('a', 1001), 1))
        ->toThrow(InvalidArgumentException::class, '15-1000 caracteres');
});

it('aplicar lança InvalidArgumentException pra n_seq_evento fora 1-20', function () {
    $service = new NfeCartaCorrecaoService(app(CertificadoService::class));
    $texto = str_repeat('a', 20);

    expect(fn () => $service->aplicar(1, 999, $texto, 0))
        ->toThrow(InvalidArgumentException::class, '1-20');

    expect(fn () => $service->aplicar(1, 999, $texto, 21))
        ->toThrow(InvalidArgumentException::class, '1-20');
});

it('aplicar lança UnauthorizedActionException cross-tenant (session biz ≠ param)', function () {
    session(['user.business_id' => 1]);
    $service = new NfeCartaCorrecaoService(app(CertificadoService::class));
    $texto = str_repeat('a', 20);

    expect(fn () => $service->aplicar(99, 999, $texto, 1))
        ->toThrow(UnauthorizedActionException::class, 'Cross-tenant attempt');
});

it('NfeCartaCorrecaoService API contract — método aplicar com 4 args int/string', function () {
    $reflection = new ReflectionMethod(NfeCartaCorrecaoService::class, 'aplicar');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(4)
        ->and($params[0]->getName())->toBe('businessId')
        ->and($params[1]->getName())->toBe('nfeEmissaoId')
        ->and($params[2]->getName())->toBe('textoCorrecao')
        ->and($params[3]->getName())->toBe('nSeqEvento');

    expect((string) $params[0]->getType())->toBe('int')
        ->and((string) $params[1]->getType())->toBe('int')
        ->and((string) $params[2]->getType())->toBe('string')
        ->and((string) $params[3]->getType())->toBe('int');
});
