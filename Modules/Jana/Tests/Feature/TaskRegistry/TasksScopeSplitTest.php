<?php

declare(strict_types=1);

use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\TasksUpdateTool;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

/**
 * A3 (ADR 0070/0278) — split do scope jana.mcp.tasks.write em advance vs close.
 *
 * Fechar (status→done/cancelled) é transição terminal → exige jana.mcp.tasks.close.
 * Demais mutações → jana.mcp.tasks.advance. O umbrella legado jana.mcp.tasks.write
 * autoriza AMBOS (backward-safe). Enforçado no tool-layer (TasksUpdateTool), pois o
 * service não recebe o User.
 *
 * BITE: spy de TaskCrudService prova que a mutação foi (ou não foi) executada.
 * Se o gate regredir, um caller advance-only conseguiria fechar → spy chamado →
 * a asserção 'updateCalled false' falha.
 *
 * @see Modules/Jana/Mcp/Tools/TasksUpdateTool::handle
 * @see Modules/Jana/Mcp/Tools/Concerns/AuthorizesMcpMutation
 */
afterEach(function () {
    app('auth')->resolveUsersUsing(fn ($guard = null) => null);
});

/**
 * Stub de user (Authenticatable) cujo can() autoriza só os scopes do conjunto dado.
 * Nome único pra não colidir com makeMcpUser do AuthorizesMcpMutationTest no mesmo
 * processo Pest.
 *
 * @param  list<string> $scopes
 */
function makeScopedMcpUser(array $scopes): \Illuminate\Contracts\Auth\Authenticatable
{
    return new class($scopes) implements \Illuminate\Contracts\Auth\Authenticatable
    {
        /** @param list<string> $scopes */
        public function __construct(private array $scopes)
        {
        }

        public function can($abilities, $arguments = []): bool
        {
            return in_array((string) $abilities, $this->scopes, true);
        }

        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return 7;
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

/** Spy: registra se update() foi chamado e devolve um result válido pro tool renderizar. */
function bindScopeSplitSpy(): object
{
    $spy = new class extends TaskCrudService
    {
        public bool $updateCalled = false;

        public function __construct()
        {
            // sem deps
        }

        public function update(string $taskId, array $fields, string $author = 'system', ?string $principal = null): array
        {
            $this->updateCalled = true;

            return ['task' => (object) ['task_id' => $taskId], 'events' => []];
        }
    };

    app()->instance(TaskCrudService::class, $spy);

    return $spy;
}

function callTasksUpdate(array $scopes, array $params): array
{
    $spy = bindScopeSplitSpy();
    app('auth')->resolveUsersUsing(fn ($guard = null) => makeScopedMcpUser($scopes));

    $request = new McpRequest($params);
    $response = (new TasksUpdateTool())->handle($request);

    return ['response' => $response, 'spy' => $spy];
}

it('advance-only PODE avançar (status→doing) — mutação executa', function () {
    $r = callTasksUpdate(['jana.mcp.tasks.advance'], ['task_id' => 'US-A3-001', 'status' => 'doing']);

    expect($r['spy']->updateCalled)->toBeTrue();
})->group('scope-split', 'ci');

it('advance-only NÃO PODE fechar (status→done) — negado, mutação não executa', function () {
    $r = callTasksUpdate(['jana.mcp.tasks.advance'], ['task_id' => 'US-A3-002', 'status' => 'done']);

    // BITE: se o gate não distinguir close, o spy seria chamado.
    expect($r['spy']->updateCalled)->toBeFalse();
    expect((string) $r['response']->content())->toContain('jana.mcp.tasks.close');
})->group('scope-split', 'ci');

it('close PODE fechar (status→done) — mutação executa', function () {
    $r = callTasksUpdate(['jana.mcp.tasks.close'], ['task_id' => 'US-A3-003', 'status' => 'done']);

    expect($r['spy']->updateCalled)->toBeTrue();
})->group('scope-split', 'ci');

it('umbrella legado tasks.write fecha E avança (backward-safe)', function () {
    $close = callTasksUpdate(['jana.mcp.tasks.write'], ['task_id' => 'US-A3-004', 'status' => 'done']);
    expect($close['spy']->updateCalled)->toBeTrue();

    $advance = callTasksUpdate(['jana.mcp.tasks.write'], ['task_id' => 'US-A3-005', 'status' => 'doing']);
    expect($advance['spy']->updateCalled)->toBeTrue();
})->group('scope-split', 'ci');

it('sem scope nenhum — negado', function () {
    $r = callTasksUpdate([], ['task_id' => 'US-A3-006', 'status' => 'doing']);

    expect($r['spy']->updateCalled)->toBeFalse();
    expect((string) $r['response']->content())->toContain('jana.mcp.tasks.advance');
})->group('scope-split', 'ci');
