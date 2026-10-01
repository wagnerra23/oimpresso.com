<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * R-WA-INBOX-REDIR — GET /atendimento/inbox redireciona pra Caixa Unificada V4
 * PRESERVANDO a query string.
 *
 * [W] 2026-10-01, D1 do playbook Atendimento (thread 01): "redirecionar
 * `/atendimento/inbox` pra Caixa Unificada, preservando `?thread=` `?channel_id=` `?tab=`".
 *
 * Por que existe: até 2026-10-01 a rota era `Route::redirect('/inbox', ...)`. O
 * `Illuminate\Routing\RedirectController` monta a URL de destino só com os parâmetros
 * de PATH — a query string se perdia, e um link como o do CSAT
 * (`/atendimento/inbox?thread=N`) abria a Caixa sem a conversa.
 *
 * Sem DB: chama o controller direto e lê a tabela de rotas registrada.
 */
it('R-WA-INBOX-REDIR-001 — a rota nomeada aponta pra InboxController@index (não pro RedirectController)', function () {
    $route = Route::getRoutes()->getByName('atendimento.inbox.index');

    expect($route)->not->toBeNull();
    expect($route->getActionName())->toBe(InboxController::class.'@index');
    expect($route->uri())->toBe('atendimento/inbox');
});

it('R-WA-INBOX-REDIR-002 — redirect 301 pra caixa-unificada levando thread e tab', function () {
    $request = Request::create('/atendimento/inbox', 'GET', ['thread' => '42', 'tab' => 'unread']);

    $response = (new InboxController())->index($request);

    expect($response->getStatusCode())->toBe(301);

    $alvo = parse_url($response->getTargetUrl());
    parse_str($alvo['query'] ?? '', $query);

    expect($alvo['path'])->toBe('/atendimento/caixa-unificada');
    expect($query['thread'] ?? null)->toBe('42');
    expect($query['tab'] ?? null)->toBe('unread');
});

it('R-WA-INBOX-REDIR-003 — channel_id é mantido e também vira account_id (nome que a Caixa lê)', function () {
    $request = Request::create('/atendimento/inbox', 'GET', ['channel_id' => '7']);

    $response = (new InboxController())->index($request);

    parse_str(parse_url($response->getTargetUrl())['query'] ?? '', $query);

    expect($query['channel_id'] ?? null)->toBe('7');
    expect($query['account_id'] ?? null)->toBe('7');
});

it('R-WA-INBOX-REDIR-004 — account_id explícito vence o channel_id', function () {
    $request = Request::create('/atendimento/inbox', 'GET', ['channel_id' => '7', 'account_id' => '9']);

    $response = (new InboxController())->index($request);

    parse_str(parse_url($response->getTargetUrl())['query'] ?? '', $query);

    expect($query['account_id'] ?? null)->toBe('9');
});

it('R-WA-INBOX-REDIR-005 — sem query, redireciona pra caixa-unificada sem query', function () {
    $request = Request::create('/atendimento/inbox', 'GET');

    $response = (new InboxController())->index($request);

    expect($response->getStatusCode())->toBe(301);
    expect(parse_url($response->getTargetUrl(), PHP_URL_PATH))->toBe('/atendimento/caixa-unificada');
    expect(parse_url($response->getTargetUrl(), PHP_URL_QUERY))->toBeNull();
});
