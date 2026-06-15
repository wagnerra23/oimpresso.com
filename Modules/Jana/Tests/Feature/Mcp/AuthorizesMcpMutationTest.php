<?php

declare(strict_types=1);

// Local-dev worktree autoload fix: require_once ANTES de qualquer `use` pra
// garantir que a versão da worktree das tools/trait seja a carregada (vendor é
// symlink pra main repo em dev local; em CI/prod o autoload do worktree resolve
// nativo). Mesmo padrão de CyclesCloseToolTest.
(function () {
    $base = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Mcp' . DIRECTORY_SEPARATOR . 'Tools' . DIRECTORY_SEPARATOR;
    $map = [
        'Modules\\Jana\\Mcp\\Tools\\Concerns\\AuthorizesMcpMutation' => $base . 'Concerns' . DIRECTORY_SEPARATOR . 'AuthorizesMcpMutation.php',
        'Modules\\Jana\\Mcp\\Tools\\TasksCreateTool' => $base . 'TasksCreateTool.php',
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
use Laravel\Mcp\Response as McpResponse;
use Modules\Jana\Mcp\Tools\LgpdEsquecerTitularTool;
use Modules\Jana\Mcp\Tools\TasksCreateTool;
use Modules\Jana\Services\Lgpd\DsrEsquecimentoResult;
use Modules\Jana\Services\Lgpd\DsrService;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

/**
 * SDD Leva 2 · A4 — gate de escopo server-side em tools que MUTAM via trait
 * AuthorizesMcpMutation.
 *
 * BITE: a versão anterior (comentário mentiroso no McpAuthMiddleware: "cada Tool
 * checa o scope via $user->can") deixava tools mutadoras SEM checagem fina. Este
 * teste prova que:
 *   (a) caller SEM o scope recebe Response::error() mencionando o scope/'permiss'
 *       E a mutação NÃO acontece (o service nunca é invocado);
 *   (b) caller COM o scope segue adiante (o service É invocado).
 *
 * Se o gate regredir (for removido / não rodar como primeiro statement), o caller
 * negado alcançaria o service → o spy registraria a chamada → o teste FALHA.
 * Não é tautologia: a asserção é sobre o efeito colateral (service chamado ou não),
 * não só sobre a string da Response.
 *
 * Cobre TasksCreateTool (jana.mcp.tasks.write) + LgpdEsquecerTitularTool
 * (jana.mcp.memory.manage — o de maior risco).
 *
 * sqlite-only: a resolução de user é via auth userResolver (não toca schema),
 * mas mantemos o guard de driver pra consistência com a lane sqlite per-PR.
 *
 * @see Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: sqlite-only no burn-down do floor SDD (consistência com tools irmãs).');
    }
});

afterEach(function () {
    // Restaura o resolver pra closure neutra — evita vazar o stub pros testes
    // irmãos no mesmo processo Pest.
    app('auth')->resolveUsersUsing(fn ($guard = null) => null);
});

/**
 * Stub de user que implementa só o necessário: Authenticatable (contrato que
 * Laravel\Mcp\Request::user() devolve) + um método can() (a trait chama via
 * method_exists, NÃO pelo contrato Authorizable). A decisão de can() é injetada
 * via $granted.
 */
function makeMcpUser(bool $granted): Authenticatable
{
    return new class($granted) implements Authenticatable
    {
        public function __construct(private bool $granted) {}

        public function can($abilities, $arguments = []): bool
        {
            return $this->granted;
        }

        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return 42;
        }

        public function getAuthPasswordName()
        {
            return 'password';
        }

        public function getAuthPassword()
        {
            return '';
        }

        public function getRememberToken()
        {
            return '';
        }

        public function setRememberToken($value)
        {
            // no-op (stub)
        }

        public function getRememberTokenName()
        {
            return 'remember_token';
        }
    };
}

/** Injeta (ou limpa) o user resolvido por Laravel\Mcp\Request::user(). */
function actAsMcpUser(?Authenticatable $user): void
{
    app('auth')->resolveUsersUsing(fn ($guard = null) => $user);
}

/** Extrai o texto de uma Response MCP (Text::__toString). */
function mcpResponseText(McpResponse $response): string
{
    return (string) $response->content();
}

/**
 * Spy de TaskCrudService: registra se create() foi chamado e devolve um result
 * válido pra tool renderizar sucesso no caminho autorizado.
 */
function bindTaskCrudSpy(): object
{
    $spy = new class extends TaskCrudService
    {
        public bool $createCalled = false;

        public function __construct()
        {
            // não chama parent::__construct — TaskCrudService não tem ctor com deps,
            // mas explicitamos pra deixar o spy auto-contido.
        }

        public function create(array $data): array
        {
            $this->createCalled = true;

            return [
                'task_id'   => 'US-TEST-999',
                'written'   => true,
                'spec_path' => 'memory/requisitos/Test/SPEC.md',
                'markdown'  => "\n### US-TEST-999 — spy\n",
            ];
        }
    };

    app()->instance(TaskCrudService::class, $spy);

    return $spy;
}

/**
 * Spy de DsrService: registra se esquecerTitular() foi chamado. Override do ctor
 * pra não precisar das deps (PiiRedactor + JanaAuditService).
 */
function bindDsrSpy(): object
{
    $spy = new class extends DsrService
    {
        public bool $esquecerCalled = false;

        public function __construct()
        {
            // sem deps — override do ctor de DsrService.
        }

        public function esquecerTitular(string $cpfOuCnpj, int $businessId, string $mode = 'anonymize'): DsrEsquecimentoResult
        {
            $this->esquecerCalled = true;

            return new DsrEsquecimentoResult(
                cpfOuCnpj: preg_replace('/\D+/', '', $cpfOuCnpj) ?? '',
                businessId: $businessId,
                refsByEntity: [],
                auditTrailId: 'spy-batch',
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

// ─── TasksCreateTool (jana.mcp.tasks.write) ───────────────────────────────────

it('TasksCreateTool NEGA caller sem jana.mcp.tasks.write e NÃO muta', function () {
    $spy = bindTaskCrudSpy();
    actAsMcpUser(makeMcpUser(granted: false));

    $params = ['module' => 'Test', 'title' => 'tentativa sem scope'];
    request()->replace($params);

    $response = (new TasksCreateTool())->handle(new McpRequest($params));

    // Response é de erro e menciona o scope exigido.
    expect($response->isError())->toBeTrue();
    expect(mcpResponseText($response))
        ->toContain('jana.mcp.tasks.write')
        ->toContain('permiss');

    // BITE: o service de mutação NUNCA foi invocado.
    expect($spy->createCalled)->toBeFalse();
});

it('TasksCreateTool PERMITE caller com jana.mcp.tasks.write e segue pro service', function () {
    $spy = bindTaskCrudSpy();
    actAsMcpUser(makeMcpUser(granted: true));

    $params = ['module' => 'Test', 'title' => 'criação autorizada'];
    request()->replace($params);

    $response = (new TasksCreateTool())->handle(new McpRequest($params));

    // Passou do gate — o service foi chamado e a Response não é de negação.
    expect($spy->createCalled)->toBeTrue();
    expect(mcpResponseText($response))->toContain('US-TEST-999');
});

it('TasksCreateTool NEGA quando não há user autenticado (defesa em profundidade)', function () {
    $spy = bindTaskCrudSpy();
    actAsMcpUser(null);

    $params = ['module' => 'Test', 'title' => 'sem user'];
    request()->replace($params);

    $response = (new TasksCreateTool())->handle(new McpRequest($params));

    expect($response->isError())->toBeTrue()
        ->and(mcpResponseText($response))->toContain('jana.mcp.tasks.write')
        ->and($spy->createCalled)->toBeFalse();
});

// ─── LgpdEsquecerTitularTool (jana.mcp.memory.manage — maior risco) ────────────

it('LgpdEsquecerTitularTool NEGA caller sem jana.mcp.memory.manage e NÃO esquece', function () {
    $spy = bindDsrSpy();
    actAsMcpUser(makeMcpUser(granted: false));

    // confirm=true seria o caminho destrutivo — o gate (1º statement) tem que
    // barrar ANTES de tocar o DsrService.
    $params = [
        'cpf_or_cnpj' => '123.456.789-00', // pii-allowlist
        'business_id' => 1,
        'mode'        => 'anonymize',
        'confirm'     => true,
    ];
    request()->replace($params);

    $response = (new LgpdEsquecerTitularTool())->handle(new McpRequest($params));

    expect($response->isError())->toBeTrue();
    expect(mcpResponseText($response))
        ->toContain('jana.mcp.memory.manage')
        ->toContain('permiss');

    // BITE: o esquecimento LGPD (destrutivo) NUNCA rodou.
    expect($spy->esquecerCalled)->toBeFalse();
});

it('LgpdEsquecerTitularTool PERMITE caller com jana.mcp.memory.manage (confirm=true executa)', function () {
    $spy = bindDsrSpy();
    actAsMcpUser(makeMcpUser(granted: true));

    $params = [
        'cpf_or_cnpj' => '123.456.789-00', // pii-allowlist
        'business_id' => 1,
        'mode'        => 'anonymize',
        'confirm'     => true,
    ];
    request()->replace($params);

    $response = (new LgpdEsquecerTitularTool())->handle(new McpRequest($params));

    // Passou do gate → o service destrutivo foi de fato invocado.
    expect($spy->esquecerCalled)->toBeTrue();
    expect($response->isError())->toBeFalse();
});
