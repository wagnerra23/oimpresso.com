<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modules/Accounting — Routes
|--------------------------------------------------------------------------
|
| Onda 2 da deprecação (US-ACCO-012 — ADR 0172 aceita 2026-05-20):
| TODAS as rotas do módulo Accounting retornam HTTP 410 Gone.
|
| Estratégia (mantém nomes/named-routes que outros módulos podem referenciar
| via `route('media.download')` — drop só na Onda 5):
|   - Grupo `/accounting/*` (82 rotas)         → wildcard catch-all 410
|   - Grupo `/media/*` (2 named routes)        → closures 410 preservando ->name()
|   - Grupo `/report/accounting/*` (12 rotas)  → wildcard catch-all 410
|
| Middleware `auth`/`SetSessionData`/`CheckUserLogin` REMOVIDO:
|   410 é resposta pública informativa — não exige sessão Centrifugo/idioma/etc.
|
| Substituto canônico: Modules/Financeiro (`/financeiro/*`).
|   Ver memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md
|   e memory/requisitos/Accounting/DEPRECATION-PLAN.md
|
| Permissions Spatie `accounting.*` permanecem (defesa em profundidade —
|   Onda 5 limpa o seeder; ter permissão sem rota viva é inerte).
*/

/**
 * Mensagem canon 410 — usada por todas as rotas deprecadas do módulo.
 * Retorna JSON se `Accept: application/json`, senão view simples HTML.
 *
 * @return \Symfony\Component\HttpFoundation\Response
 */
$accountingGoneResponse = function (Request $request) {
    $message = 'Módulo Accounting foi deprecado em 2026-05-20 (ADR 0172). Use Modules/Financeiro em /financeiro/*';

    if ($request->expectsJson() || $request->wantsJson()) {
        return response()->json([
            'error'           => 'gone',
            'message'         => $message,
            'deprecated_at'   => '2026-05-20',
            'adr'             => 'ADR 0172',
            'substituto'      => '/financeiro/*',
        ], 410);
    }

    return response(
        '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<title>410 — Módulo Deprecado</title></head><body>'
        . '<h1>410 — Módulo Deprecado</h1>'
        . '<p>' . e($message) . '</p>'
        . '</body></html>',
        410,
        ['Content-Type' => 'text/html; charset=UTF-8']
    );
};

// -----------------------------------------------------------------------------
// Grupo 1 — /accounting/*  (era 82 rotas; vira 1 catch-all 410)
// -----------------------------------------------------------------------------
Route::any('accounting/{any?}', $accountingGoneResponse)
    ->where('any', '.*');

// -----------------------------------------------------------------------------
// Grupo 2 — /media/*  (named routes preservadas pra evitar quebrar route()
// helpers em outros módulos; closures retornam 410)
// -----------------------------------------------------------------------------
Route::group(['prefix' => 'media'], function () use ($accountingGoneResponse) {
    Route::post('{id}/download', $accountingGoneResponse)->name('media.download');
    Route::delete('{id}/delete', $accountingGoneResponse)->name('media.delete');
});

// -----------------------------------------------------------------------------
// Grupo 3 — /report/accounting/*  (era 12 rotas; vira 1 catch-all 410)
// -----------------------------------------------------------------------------
Route::any('report/accounting/{any?}', $accountingGoneResponse)
    ->where('any', '.*');
