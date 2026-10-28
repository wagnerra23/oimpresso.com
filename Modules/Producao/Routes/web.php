<?php

use Modules\Producao\Http\Controllers\AlocacaoController;
use Modules\Producao\Http\Controllers\ApontamentoController;
use Modules\Producao\Http\Controllers\AprovacaoArteController;
use Modules\Producao\Http\Controllers\AproveitamentoMidiaController;
use Modules\Producao\Http\Controllers\ColaboradorCustoController;
use Modules\Producao\Http\Controllers\EtiquetaOsController;
use Modules\Producao\Http\Controllers\FerramentasController;
use Modules\Producao\Http\Controllers\InstallController;
use Modules\Producao\Http\Controllers\LinhaController;
use Modules\Producao\Http\Controllers\LoteRastreioController;
use Modules\Producao\Http\Controllers\OrdemCustoController;
use Modules\Producao\Http\Controllers\OrdemMaterialController;
use Modules\Producao\Http\Controllers\PainelController;
use Modules\Producao\Http\Controllers\PostoOperadoresController;
use Modules\Producao\Http\Controllers\PostosController;
use Modules\Producao\Http\Controllers\PrevistoRealizadoController;
use Modules\Producao\Http\Controllers\PrazoDaVendaController;
use Modules\Producao\Http\Controllers\QuadroController;
use Modules\Producao\Http\Controllers\RomaneioController;
use Modules\Producao\Http\Controllers\RoteirosController;

/*
 * Esqueleto (ADR 0431): só as rotas Install 1-clique (ADR 0024 /
 * BaseModuleInstallController). Sem elas o helper action() em
 * Install/ModulesController vira '#' e o botão "Install" de /manage-modules não
 * faz nada. Rotas de negócio entram nas threads seguintes do playbook.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('producao')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });

/*
 * Thread 04 + E04 — celular do operador (PWA). Bloco próprio: a thread 03 (cadastros) acrescenta o
 * dela em outro bloco. As escritas são JSON e aceitam a mesma `chave` reenviada pela fila offline.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('producao/apontamento')
    ->name('producao.apontamento.')
    ->group(function () {
        Route::get('/', [ApontamentoController::class, 'index'])->name('index');
        Route::post('qr', [ApontamentoController::class, 'qr'])->name('qr');
        Route::post('iniciar', [ApontamentoController::class, 'iniciar'])->name('iniciar');
        Route::post('{inicio}/pausar', [ApontamentoController::class, 'pausar'])->whereNumber('inicio')->name('pausar');
        Route::post('{inicio}/concluir', [ApontamentoController::class, 'concluir'])->whereNumber('inicio')->name('concluir');
        Route::post('{inicio}/corrigir', [ApontamentoController::class, 'corrigir'])->whereNumber('inicio')->name('corrigir');
        // US-PCP-021 · UC-FLX-37 — material consumido na etapa (baixa pelo lote que saiu).
        Route::post('material', [ApontamentoController::class, 'material'])->name('material');
        // US-PCP-027 · UC-FLX-16 — refugo com motivo na etapa (e, se pedido, a OS para Retrabalho).
        Route::post('refugo', [ApontamentoController::class, 'refugo'])->name('refugo');
    });

// ── Thread 03 + E03 · cadastros (postos, motivos, roteiros, ferramentas, custo-hora) ──
// Bloco próprio: as threads seguintes acrescentam o delas embaixo, sem mexer neste.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::get('roteiros', [RoteirosController::class, 'index'])->name('roteiros.index');
        Route::post('roteiros', [RoteirosController::class, 'store'])->name('roteiros.store');
        Route::put('roteiros/{id}', [RoteirosController::class, 'update'])->whereNumber('id')->name('roteiros.update');
        Route::put('roteiros/{id}/produtos', [RoteirosController::class, 'produtos'])->whereNumber('id')->name('roteiros.produtos');
        Route::get('roteiros/produtos/busca', [RoteirosController::class, 'buscarProdutos'])->name('roteiros.produtos.busca');

        Route::get('postos', [PostosController::class, 'index'])->name('postos.index');
        Route::post('postos', [PostosController::class, 'store'])->name('postos.store');
        Route::put('postos/{id}', [PostosController::class, 'update'])->whereNumber('id')->name('postos.update');
        Route::put('postos/{id}/motivos', [PostosController::class, 'motivos'])->whereNumber('id')->name('postos.motivos');
        // US-PCP-030 · UC-FLX-08 — operadores do posto (só eles são responsáveis e começam a etapa).
        Route::get('postos/{id}/operadores', [PostoOperadoresController::class, 'index'])->whereNumber('id')->name('postos.operadores');
        Route::put('postos/{id}/operadores', [PostoOperadoresController::class, 'update'])->whereNumber('id')->name('postos.operadores.update');

        Route::post('ferramentas', [FerramentasController::class, 'store'])->name('ferramentas.store');
        Route::put('ferramentas/{id}', [FerramentasController::class, 'update'])->whereNumber('id')->name('ferramentas.update');

        Route::put('colaboradores/{userId}/custo-hora', [ColaboradorCustoController::class, 'update'])->whereNumber('userId')->name('colaboradores.custo');
    });

// ── PR 07 propostas-app · E05 modelo a4_os · PDF da OS com o QR da etiqueta ──
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::get('os/{id}/etiqueta', [EtiquetaOsController::class, 'show'])->whereNumber('id')->name('os.etiqueta');
        // US-PCP-022 · UC-FLX-36 — custo da abertura × hoje da OS por item em andamento (JSON, gestor).
        Route::get('ordens/{id}/custo', [OrdemCustoController::class, 'show'])->whereNumber('id')->name('ordens.custo');
        // D3 · revisão 2026-10-21 — o que falta de material na OS e o conferir que a tira de Aguardando material (JSON).
        Route::get('ordens/{id}/material', [OrdemMaterialController::class, 'show'])->whereNumber('id')->name('ordens.material');
        Route::post('ordens/{id}/material/conferir', [OrdemMaterialController::class, 'conferir'])->whereNumber('id')->name('ordens.material.conferir');
        // US-COMVIS-005 · UC-FLX-15 — previsto × realizado por OS e por venda (JSON, gestor).
        Route::get('ordens/{id}/previsto-realizado', [PrevistoRealizadoController::class, 'ordem'])->whereNumber('id')->name('ordens.previsto-realizado');
        Route::get('vendas/{id}/previsto-realizado', [PrevistoRealizadoController::class, 'venda'])->whereNumber('id')->name('vendas.previsto-realizado');
        // US-COMVIS-NEW-004 · UC-FLX-02 — gera o link da prova para o cliente aprovar (JSON).
        Route::post('ordens/{id}/aprovacao-arte', [AprovacaoArteController::class, 'solicitar'])->whereNumber('id')->name('ordens.aprovacao-arte');
        // UC-PCV-11 · arte aprovada travada: trocar o arquivo pede nova aprovação; a vigente é a última aprovada (JSON).
        Route::post('ordens/{id}/arte/trocar', [AprovacaoArteController::class, 'trocar'])->whereNumber('id')->name('ordens.arte.trocar');
        // UC-PCART-07 · fila do Painel: reenviar o link ou enviar a nova prova (JSON).
        Route::post('ordens/{id}/aprovacao-arte/reenviar', [AprovacaoArteController::class, 'reenviar'])->whereNumber('id')->name('ordens.aprovacao-arte.reenviar');
        Route::get('ordens/{id}/arte', [AprovacaoArteController::class, 'vigente'])->whereNumber('id')->name('ordens.arte.vigente');
        // US-PCP-028 · UC-FLX-06 — prazo de entrega da venda pela fila dos postos (JSON, só leitura).
        Route::get('vendas/{id}/prazo', [PrazoDaVendaController::class, 'show'])->whereNumber('id')->name('vendas.prazo');
        // US-COM-025 · UC-FLX-18 — rastreio do rolo: OS que usaram fração do lote + saldo (JSON, quem vê compras).
        Route::get('lotes/{id}/rastreio', [LoteRastreioController::class, 'show'])->whereNumber('id')->name('lotes.rastreio');
    });

// ── UC-FLX-02 · US-COMVIS-NEW-004 · página pública da prova (sem login) ──
// A empresa vem do token (só o sha256 fica no banco), nunca da URL. Throttle 30/min/IP contra
// varredura de token; o `web` do RouteServiceProvider dá sessão + CSRF ao formulário.
Route::middleware(['throttle:30,1'])
    ->prefix('arte')
    ->name('producao.arte.')
    ->group(function () {
        Route::get('{token}', [AprovacaoArteController::class, 'mostrar'])->where('token', '[A-Za-z0-9]{48}')->name('mostrar');
        Route::post('{token}/aprovar', [AprovacaoArteController::class, 'aprovar'])->where('token', '[A-Za-z0-9]{48}')->name('aprovar');
        Route::post('{token}/recusar', [AprovacaoArteController::class, 'recusar'])->where('token', '[A-Za-z0-9]{48}')->name('recusar');
    });

// ── US-PCP-029 · UC-FLX-10 · alocador decide quem e quando começa (JSON, gestor) ──
// ── US-PCP-023 · UC-FLX-12 · romaneio de impressão peça a peça (JSON; a tela é follow-up) ──
// (os dois num bloco só desde o merge de 2026-10-20; mesmo middleware, rotas distintas)
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::put('etapas/{id}/alocacao', [AlocacaoController::class, 'alocar'])->whereNumber('id')->name('etapas.alocar');
        // UC-PCV-30 · thread 07 — reprogramar na Linha do tempo (horário e máquina do mesmo setor).
        Route::put('etapas/{id}/reprogramar', [AlocacaoController::class, 'reprogramar'])->whereNumber('id')->name('etapas.reprogramar');
        Route::get('postos/{postoId}/fila', [AlocacaoController::class, 'fila'])->whereNumber('postoId')->name('postos.fila');
        Route::post('os/{id}/romaneio', [RomaneioController::class, 'store'])->whereNumber('id')->name('os.romaneio.store');
        Route::get('romaneios/{id}', [RomaneioController::class, 'show'])->whereNumber('id')->name('romaneios.show');
    });

// ── US-PCP-024 · UC-FLX-13 · aproveitamento de mídia na bobina (JSON; a tela é follow-up) ──
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::put('bobinas/{material}', [AproveitamentoMidiaController::class, 'bobina'])->whereNumber('material')->name('bobinas.update');
        Route::post('aproveitamento/simular', [AproveitamentoMidiaController::class, 'simular'])->name('aproveitamento.simular');
        Route::post('aproveitamento', [AproveitamentoMidiaController::class, 'aceitar'])->name('aproveitamento.aceitar');
    });

// ── Thread 06 · Quadro (UC-PCV-05/06) — recebe o alocador (UC-FLX-10) e o simulador de bobina (UC-FLX-13) ──
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::get('quadro', [QuadroController::class, 'index'])->name('quadro.index');
        // UC-PCV-07: o PCP move a OS de coluna à mão (JSON; só troca a coluna e registra o evento).
        Route::post('quadro/mover', [QuadroController::class, 'mover'])->name('quadro.mover');
        // Painel (UC-PCV-01..04, 29): o que trava a produção hoje; os indicadores abrem o quadro filtrado.
        Route::get('painel', [PainelController::class, 'index'])->name('painel.index');
    });

// ── Thread 07 · Linha do tempo por máquina (UC-PCV-08, 09, 30) — reprogramar é PUT etapas/{id}/reprogramar ──
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('producao')
    ->name('producao.')
    ->group(function () {
        Route::get('linha', [LinhaController::class, 'index'])->name('linha.index');
    });
