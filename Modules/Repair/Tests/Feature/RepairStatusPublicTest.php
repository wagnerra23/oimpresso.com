<?php

/**
 * Modulo: Repair — endpoint publico de consulta de status.
 *
 * Cobertura:
 *  - GET  /repair-status         : pagina publica (sem auth) renderiza o form
 *  - POST /post-repair-status    : busca AJAX por job_sheet_no/invoice_no/mobile_num
 *
 * O publico identifica a OS (job_sheet_no) como "token" — eh o segredo conhecido
 * apenas pelo cliente que retirou o ticket. Validamos que:
 *   - sem ajax -> retorno vazio (controller so responde a XHR)
 *   - "token" valido (job_sheet_no real do DB) -> success=true + html
 *   - "token" invalido (numero inexistente)    -> success=false + msg de erro
 *
 * Nao usa RefreshDatabase — UltimatePOS tem 100+ migrations + triggers MySQL.
 * Roda contra DB local seedado (mesmo padrao de PontoTestCase / EssentialsTestCase).
 */

use Modules\Repair\Entities\JobSheet;

beforeEach(function () {
    // Endpoint publico — garante sessao limpa pra evitar interferencia de outros testes.
    session()->flush();
    auth()->logout();
});

it('GET /repair-status renderiza pagina publica sem exigir auth (200)', function () {
    $r = $this->get('/repair-status');

    expect($r->getStatusCode())->toBe(200);
});

it('POST /post-repair-status sem AJAX retorna corpo vazio (controller so responde XHR)', function () {
    $r = $this->post('/post-repair-status', [
        'search_type' => 'job_sheet_no',
        'search_number' => 'OS-INEXISTENTE-XYZ-' . uniqid(),
    ]);

    // Controller faz `if ($request->ajax())` — sem o flag, return implicito = 200 + body vazio.
    expect($r->getStatusCode())->toBe(200);
    expect(trim($r->getContent()))->toBe('');
});

it('POST /post-repair-status com AJAX e token invalido retorna success=false', function () {
    $tokenInvalido = 'OS-INEXISTENTE-' . uniqid();

    $r = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->postJson('/post-repair-status', [
        'search_type' => 'job_sheet_no',
        'search_number' => $tokenInvalido,
    ]);

    expect($r->getStatusCode())->toBe(200);
    $json = $r->json();
    expect($json)->toHaveKey('success');
    expect($json['success'])->toBeFalse();
    expect($json)->toHaveKey('msg');
});

it('POST /post-repair-status com AJAX e job_sheet_no valido retorna success=true', function () {
    $os = JobSheet::query()->whereNotNull('job_sheet_no')->orderByDesc('id')->first();

    if (! $os) {
        $this->markTestSkipped('Nenhuma OS (repair_job_sheets) seedada no DB local — sem dado real, sem validacao de token valido.');
    }

    $r = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->postJson('/post-repair-status', [
        'search_type' => 'job_sheet_no',
        'search_number' => $os->job_sheet_no,
    ]);

    expect($r->getStatusCode())->toBe(200);
    $json = $r->json();
    expect($json)->toHaveKey('success');
    expect($json['success'])->toBeTrue();
    expect($json)->toHaveKey('repair_html');
});

it('POST /post-repair-status aceita busca por mobile_num inexistente sem 500', function () {
    $r = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ])->postJson('/post-repair-status', [
        'search_type' => 'mobile_num',
        'search_number' => '+55-99-99999-' . random_int(1000, 9999),
    ]);

    expect($r->getStatusCode())->toBe(200);
    expect($r->json('success'))->toBeFalse();
});

it('rotas autenticadas do prefix /repair redirecionam pra /login sem auth', function () {
    auth()->logout();
    session()->flush();

    $r = $this->get('/repair/repair');
    expect($r->getStatusCode())->toBeIn([302, 401, 403]);
});
