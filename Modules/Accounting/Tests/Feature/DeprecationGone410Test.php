<?php

declare(strict_types=1);

/**
 * Onda 2 deprecação Accounting (US-ACCO-012, ADR 0172 aceita 2026-05-20).
 *
 * Verifica:
 *  1. /accounting/* responde 410 Gone (era 82 rotas; agora catch-all)
 *  2. /report/accounting/* responde 410 Gone (era 12 rotas; agora catch-all)
 *  3. /media/{id}/download e /media/{id}/delete (named routes preservadas)
 *     respondem 410 — outros módulos podem chamar `route('media.download')`
 *     sem erro RouteNotFoundException; só o request HTTP retorna 410.
 *  4. Accept: application/json retorna JSON canon com adr=ADR 0172 + substituto.
 *  5. DataController::modifyAdminMenu retorna void sem efeito (sidebar oculta).
 *
 * Tests independentes de DB (rotas retornam 410 sem ler sessão ou banco).
 *
 * @see memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md
 * @see memory/requisitos/Accounting/DEPRECATION-PLAN.md (Onda 2)
 */

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\DataController;

uses(Tests\TestCase::class);

it('GET /accounting/dashboard retorna 410 com body HTML', function () {
    $response = $this->get('/accounting/dashboard');

    expect($response->status())->toBe(410);
    expect($response->headers->get('Content-Type'))->toContain('text/html');
    expect($response->getContent())->toContain('410');
    expect($response->getContent())->toContain('Modules/Financeiro');
});

it('GET /accounting/trial_balance retorna 410 (rota legacy raiz)', function () {
    $response = $this->get('/accounting/trial_balance');

    expect($response->status())->toBe(410);
});

it('GET /accounting/journal_entry retorna 410 (sub-rota nested)', function () {
    $response = $this->get('/accounting/journal_entry');

    expect($response->status())->toBe(410);
});

it('GET /accounting com Accept: application/json retorna JSON canon', function () {
    $response = $this->getJson('/accounting/dashboard');

    expect($response->status())->toBe(410);

    $payload = $response->json();

    expect($payload)->toHaveKeys(['error', 'message', 'deprecated_at', 'adr', 'substituto']);
    expect($payload['error'])->toBe('gone');
    expect($payload['adr'])->toBe('ADR 0172');
    expect($payload['substituto'])->toBe('/financeiro/*');
    expect($payload['deprecated_at'])->toBe('2026-05-20');
    expect($payload['message'])->toContain('Modules/Financeiro');
});

it('POST media.download named route resolve mas retorna 410', function () {
    // Importante: route('media.download', ['id' => 1]) precisa continuar funcionando
    // pra outros módulos que referenciam — só o request HTTP devolve 410.
    $url = route('media.download', ['id' => 1]);

    expect($url)->toContain('/media/1/download');

    $response = $this->post($url);

    expect($response->status())->toBe(410);
});

it('DELETE media.delete named route resolve mas retorna 410', function () {
    $url = route('media.delete', ['id' => 1]);

    expect($url)->toContain('/media/1/delete');

    $response = $this->delete($url);

    expect($response->status())->toBe(410);
});

it('GET /report/accounting/balance_sheet retorna 410', function () {
    $response = $this->get('/report/accounting/balance_sheet');

    expect($response->status())->toBe(410);
});

it('GET /report/accounting/ledger retorna 410 (rota nested report)', function () {
    $response = $this->get('/report/accounting/ledger');

    expect($response->status())->toBe(410);
});

it('DataController::modifyAdminMenu retorna void sem efeito (sidebar oculta)', function () {
    $controller = new DataController;

    // Antes da deprecação retornava array de menu items;
    // pós Onda 2 retorna void (null) — Sidebar.tsx não vê entry Accounting.
    $result = $controller->modifyAdminMenu();

    expect($result)->toBeNull();
});
