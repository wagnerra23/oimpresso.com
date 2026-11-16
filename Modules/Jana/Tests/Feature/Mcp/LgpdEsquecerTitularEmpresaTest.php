<?php

declare(strict_types=1);

// Mesmo padrão de AuthorizesMcpMutationTest: carrega a tool da worktree antes do autoload.
(function () {
    $base = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Mcp' . DIRECTORY_SEPARATOR . 'Tools' . DIRECTORY_SEPARATOR;
    $map = [
        'Modules\\Jana\\Mcp\\Tools\\Concerns\\AuthorizesMcpMutation' => $base . 'Concerns' . DIRECTORY_SEPARATOR . 'AuthorizesMcpMutation.php',
        'Modules\\Jana\\Mcp\\Tools\\LgpdEsquecerTitularTool' => $base . 'LgpdEsquecerTitularTool.php',
    ];
    foreach ($map as $class => $file) {
        if (is_file($file) && ! class_exists($class, false) && ! trait_exists($class, false)) {
            require_once $file;
        }
    }
})();

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\LgpdEsquecerTitularTool;
use Modules\Jana\Services\Lgpd\DsrEsquecimentoResult;
use Modules\Jana\Services\Lgpd\DsrService;

uses(Tests\TestCase::class);

/**
 * LgpdEsquecerTitularTool — empresa do pedido × empresa do usuário (Tier 0, ADR 0093).
 *
 * O scope jana.mcp.memory.manage autoriza USAR a tool; o business_id do pedido tem que ser o da
 * empresa do usuário, salvo superadmin de plataforma (gate `superadmin`). A asserção é sobre o
 * efeito: o DsrService (destrutivo) é ou não é chamado.
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('sqlite-only (lane ci-sqlite-pest.list, como AuthorizesMcpMutationTest).');
    }
});

afterEach(function () {
    app('auth')->resolveUsersUsing(fn ($guard = null) => null);
});

/** User com o scope da tool, empresa $biz, e superadmin opcional. */
function lgpdEmpUser(int $biz, bool $superadmin = false): Authenticatable
{
    return new class($biz, $superadmin) implements Authenticatable
    {
        public function __construct(public int $business_id, private bool $superadmin) {}

        public function can($abilities, $arguments = []): bool
        {
            return $abilities === 'superadmin' ? $this->superadmin : true;
        }

        public function getAuthIdentifierName() { return 'id'; }

        public function getAuthIdentifier() { return 77; }

        public function getAuthPasswordName() { return 'password'; }

        public function getAuthPassword() { return ''; }

        public function getRememberToken() { return ''; }

        public function setRememberToken($value) {}

        public function getRememberTokenName() { return 'remember_token'; }
    };
}

function lgpdEmpSpy(): object
{
    $spy = new class extends DsrService
    {
        public bool $chamado = false;

        public function __construct() {}

        public function esquecerTitular(string $cpfOuCnpj, int $businessId, string $mode = 'anonymize'): DsrEsquecimentoResult
        {
            $this->chamado = true;

            return new DsrEsquecimentoResult(
                cpfOuCnpj: preg_replace('/\D+/', '', $cpfOuCnpj) ?? '',
                businessId: $businessId,
                refsByEntity: [],
                auditTrailId: 'spy',
                startedAt: now()->toIso8601String(),
                finishedAt: now()->toIso8601String(),
                durationMs: 1,
                status: 'ok',
            );
        }
    };
    app()->instance(DsrService::class, $spy);

    return $spy;
}

function lgpdEmpChamar(int $biz): \Laravel\Mcp\Response
{
    $params = ['cpf_or_cnpj' => '123.456.789-00', 'business_id' => $biz, 'mode' => 'anonymize', 'confirm' => true]; // pii-allowlist
    request()->replace($params);

    return (new LgpdEsquecerTitularTool())->handle(new McpRequest($params));
}

it('nega pedido para empresa diferente da do usuário e não executa', function () {
    $spy = lgpdEmpSpy();
    app('auth')->resolveUsersUsing(fn ($guard = null) => lgpdEmpUser(1));

    $response = lgpdEmpChamar(2);

    expect($response->isError())->toBeTrue()
        ->and((string) $response->content())->toContain('Sem permissão')
        ->and($spy->chamado)->toBeFalse();
});

it('nega usuário sem empresa que não é superadmin', function () {
    $spy = lgpdEmpSpy();
    app('auth')->resolveUsersUsing(fn ($guard = null) => lgpdEmpUser(0));

    expect(lgpdEmpChamar(1)->isError())->toBeTrue()
        ->and($spy->chamado)->toBeFalse();
});

it('executa para a empresa do próprio usuário (controle)', function () {
    $spy = lgpdEmpSpy();
    app('auth')->resolveUsersUsing(fn ($guard = null) => lgpdEmpUser(1));

    expect(lgpdEmpChamar(1)->isError())->toBeFalse()
        ->and($spy->chamado)->toBeTrue();
});

it('superadmin de plataforma executa para qualquer empresa (controle)', function () {
    $spy = lgpdEmpSpy();
    app('auth')->resolveUsersUsing(fn ($guard = null) => lgpdEmpUser(1, superadmin: true));

    expect(lgpdEmpChamar(2)->isError())->toBeFalse()
        ->and($spy->chamado)->toBeTrue();
});
