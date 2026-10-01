<?php

use Illuminate\Support\Facades\Route;
use Modules\Arquivos\Http\Controllers\ArquivosAdminController;
use Modules\Arquivos\Http\Controllers\DownloadController;
use Modules\Arquivos\Http\Controllers\InstallController;
use Modules\Arquivos\Http\Controllers\RetencaoSimulacaoController;

/*
|--------------------------------------------------------------------------
| Arquivos — rotas web
|--------------------------------------------------------------------------
|
| Sprint 1 — ADR 0123 (Modules/Arquivos DMS backbone).
|
| Arquivos é backbone consumido via trait HasArquivos. Não tem UI própria.
| UI admin entra em Sprint 2 (Pages/Arquivos no Modules/Admin).
|
| Rotas:
| - 3 Install obrigatórias (ADR 0024)
| - download signed-URL (Sprint 1 dia 4 — placeholder via name 'arquivos.download')
*/

// ── UI admin (US-ARQ-013 · Sprint 2) ─────────────────────────────────────────
// A tela nasce no PROPRIO modulo, em resources/js/Pages/Arquivos/ — decisao [W]
// 2026-07-29 registrada no SPEC (ADR 0360 deprecou o Admin Center, que era o destino
// anterior). Mesma stack de middleware do grupo Install + can() da permissao declarada.
//
// `arquivos.access` ja existia declarada em DataController::user_permissions (default
// false) e ate aqui NAO tinha nenhum consumidor no repo: esta rota e o primeiro.
//
// Ate 2026-09-30 era so GET. Classificar (thread 02 · PR-6) e o primeiro POST: mesma
// permissao, o motivo obrigatorio vem da ReclassifyArquivoRequest, e `whereNumber` porque o
// controller recebe o id cru e resolve pelo model (global scope) — sem route-model binding,
// que rodaria antes do SetSessionData. Excluir/restaurar = thread 03; retencao/purge
// dependem da proposta de ADR `arquivos-retencao-ui-aviso-titular`. Excluir/restaurar
// entraram na thread 03 (2026-10-01).
Route::middleware(['throttle:60,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('arquivos')
    ->group(function () {
        Route::get('/', [ArquivosAdminController::class, 'index'])
            ->middleware('can:arquivos.access')
            ->name('arquivos.index');
        Route::post('{arquivo}/classificar', [ArquivosAdminController::class, 'classificar'])
            ->whereNumber('arquivo')
            ->middleware('can:arquivos.access')
            ->name('arquivos.classificar');
        // Thread 03 (PR-7): excluir = SOFT-delete (grace 30d, `arquivos_retention.grace_period_days`);
        // restaurar só dentro do grace. Hard-delete/purge NUNCA pela UI (D4) — segue só no
        // `arquivos:retention-cleanup`. As Requests barram arquivo de outro business.
        Route::post('{arquivo}/excluir', [ArquivosAdminController::class, 'excluir'])
            ->whereNumber('arquivo')
            ->middleware('can:arquivos.access')
            ->name('arquivos.excluir');
        Route::post('{arquivo}/restaurar', [ArquivosAdminController::class, 'restaurar'])
            ->whereNumber('arquivo')
            ->middleware('can:arquivos.access')
            ->name('arquivos.restaurar');
        // Thread 04 (PR-8): simular a retenção em DRY-RUN — o controller força dry_run=true e
        // recusa purge (D4: a UI nunca apaga). Permissão própria de governança, separada de
        // `arquivos.access` (ver o acervo não é o mesmo que mexer na política).
        Route::post('retencao/simular', [RetencaoSimulacaoController::class, 'simular'])
            ->middleware('can:arquivos.governanca')
            ->name('arquivos.retencao.simular');
    });

// Wave 14 D8 Security — throttle:60,1 (60 req/min/IP) em rotas Arquivos.
// Arquivos é backbone DMS multi-tenant; throttle limita abuso (brute-force install,
// scraping de signed URLs expiradas, varredura sequencial de arquivo_id).
// Stack canonica UltimatePOS preservada apos throttle (web/auth/SetSessionData/etc).
Route::middleware(['throttle:60,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('arquivos')
    ->group(function () {
        // Thread 06 (2026-10-01): instalar/desinstalar/atualizar NAO agem mais por GET — o
        // GET so mostra uma confirmacao sem efeito; a acao roda no POST com CSRF (mesmo
        // padrao do Connector, CONN-O2). O GET segue registrado porque /manage-modules
        // monta <a href> para estas rotas e cairia em 405.
        Route::get('install',           [InstallController::class, 'index']);
        Route::post('install',          [InstallController::class, 'install']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });

// Download via signed URL (Sprint 1 dia 4 — US-ARQ-008).
// Middleware `signed` valida expiração + assinatura HMAC (Laravel built-in).
// Auth obrigatório — multi-tenant Tier 0 aplica global scope no Arquivo::find.
// Wave 14 D8 — throttle:60,1 anti-brute-force em arquivo_id sequencial (signed URLs
// curtas têm TTL mas atacante pode varrer enquanto válidas).
Route::middleware(['throttle:60,1', 'web', 'auth', 'signed'])
    ->get('arquivos/download/{arquivo}', DownloadController::class)
    ->name('arquivos.download');
