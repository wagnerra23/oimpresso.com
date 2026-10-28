<?php

use Inertia\Inertia;
use Modules\ComunicacaoVisual\Http\Controllers\AgendaController;
use Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController;
use Modules\ComunicacaoVisual\Http\Controllers\InstalacaoController;
use Modules\ComunicacaoVisual\Http\Controllers\InstallController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoAceiteController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoArquivoController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Http\Controllers\OsPosCalculoController;
use Modules\ComunicacaoVisual\Http\Controllers\Public\OrcamentoAceitePublicoController;
use Modules\ComunicacaoVisual\Http\Controllers\RoteiroPadraoController;

// Rota raiz do módulo: renderiza Index.tsx — calculadora de orçamento por m²
// (US-COMVIS-001) + cartões "em breve" das outras áreas. Nasceu em 2026-05-26
// como hub stub no lugar do dropdown legacy do DataController, que apontava
// pra URLs /comunicacao-visual/admin/* inexistentes (404).
Route::middleware(['web', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])
    ->prefix('comunicacao-visual')
    ->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            // Permission gate canon — qualquer perm de visualização CV libera o hub.
            if (! auth()->user()->can('superadmin')
                && ! auth()->user()->can('comvis.orcamento.view')
                && ! auth()->user()->can('comvis.os.view')) {
                abort(403, 'Sem permissão Comunicação Visual (comvis.orcamento.view ou comvis.os.view).');
            }

            // Catálogo do business pra calculadora (UC-CV-07 / CU-CV-09): escolher o material
            // preenche o preço/m². Até 2026-09-23 esta rota passava só `bizName` e o seletor
            // ficava sempre em "Sem catálogo", mesmo com o MaterialSeeder rodado.
            //
            // EAGER, não Inertia::defer, de propósito: (1) é um catálogo curto, 1 query por
            // business; (2) deferido, o 1º paint receberia `materiais` vazio e mostraria o aviso
            // "você ainda não tem materiais" — falso — até o 2º request; (3) o contrato
            // ContratoTelaOrcamentoTest lê o payload inicial da página.
            //
            // business_id EXPLÍCITO além do global scope do Material: aquele scope não filtra
            // nada quando a sessão não tem business (`if ($businessId !== null)`), e aqui a
            // falha segura é catálogo vazio, nunca o de todos (ADR 0093).
            $businessId = session('user.business_id') ?? session('business.id');
            $materiais = $businessId === null
                ? collect()
                : \Modules\ComunicacaoVisual\Entities\Material::ativos()
                    ->where('comvis_materiais.business_id', $businessId)
                    ->orderBy('categoria')
                    ->orderBy('nome')
                    ->get(['id', 'nome', 'categoria', 'unidade', 'preco_venda_m2'])
                    ->map(fn ($m) => [
                        'id'             => $m->id,
                        'nome'           => $m->nome,
                        'categoria'      => $m->categoria,
                        'unidade'        => $m->unidade,
                        // A tela faz BRL.format(preco_venda_m2): número, não string decimal.
                        'preco_venda_m2' => (float) $m->preco_venda_m2,
                    ])
                    ->values();

            // Reabrir (`?orcamento={id}`): EAGER, a calculadora monta as peças no 1º paint.
            // A lista dos salvos é DEFERIDA (Inertia::defer) — query de lista, não do 1º paint.
            // Só quem vê orçamento (comvis.os.view sozinho abre o hub, mas não os orçamentos).
            $podeVerOrc = auth()->user()->can('superadmin')
                || auth()->user()->can('comvis.orcamento.view')
                || auth()->user()->can('comvis.orcamento.create');
            $reabrirId = $podeVerOrc ? (int) $request->query('orcamento', 0) : 0;
            $orcamento = ($businessId !== null && $reabrirId > 0)
                ? OrcamentoController::paraTela((int) $businessId, $reabrirId)
                : null;

            return Inertia::render('ComunicacaoVisual/Index', [
                'bizName'   => session('business.name', 'oimpresso'),
                'materiais' => $materiais,
                'podeCriar' => auth()->user()->can('superadmin')
                    || auth()->user()->can('comvis.orcamento.create'),
                // UC-FLX-03: a tela avisa que a margem aparece ao conferir, só para quem vê custo.
                'podeVerCusto'      => OrcamentoController::podeVerCusto(),
                'orcamento'         => $orcamento,
                'orcamentoAusente'  => $reabrirId > 0 && $orcamento === null,
                'recentes'  => Inertia::defer(fn () => ($businessId === null || ! $podeVerOrc)
                    ? []
                    : OrcamentoController::recentes((int) $businessId)),
            ]);
        })->name('comunicacao-visual.index');

        // US-COMVIS-007 · UC-FLX-21: agenda de instalação em semana × equipe. Só lê; agendar é o POST da API.
        Route::get('agenda', [AgendaController::class, 'index'])->name('comunicacao-visual.agenda');
    });

// Rotas Install 1-click (ADR 0024 / BaseModuleInstallController).
// Sem essas rotas o action() helper em Install/ModulesController vira '#'
// e o botão "Install" da tela /manage-modules fica sem ação.
// Incidente documentado: ConsultaOs 2026-05-04 (RUNBOOK §troubleshooting).
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('comunicacao-visual')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });

// Sprint 1 — US-COMVIS-001: API de cálculo m² + persistência de orçamentos.
// Middleware padrão UltimatePOS pra rotas admin com autenticação completa.
// Wave 10 D8 Security: throttle:60,1 — proteção contra abuso de cálculo m² authoritative
// (endpoint stateless mas faz DB-write em /orcamentos e /apontamentos).
Route::middleware(['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', 'throttle:60,1'])
    ->prefix('comunicacao-visual/api')
    ->group(function () {
        // Preview authoritative server-side (sem persistência)
        Route::post('calcular', [OrcamentoController::class, 'calcular'])
            ->name('comvis.api.calcular');

        // Persistência de orçamentos — salvar, listar e reabrir (UC-CV-13/14)
        Route::get('orcamentos', [OrcamentoController::class, 'index'])
            ->name('comvis.api.orcamentos.index');

        Route::post('orcamentos', [OrcamentoController::class, 'store'])
            ->name('comvis.api.orcamentos.store');

        // Consulta de orçamento por ID (multi-tenant: global scope filtra automaticamente)
        Route::get('orcamentos/{id}', [OrcamentoController::class, 'show'])
            ->whereNumber('id')
            ->name('comvis.api.orcamentos.show');

        Route::put('orcamentos/{id}', [OrcamentoController::class, 'update'])
            ->whereNumber('id')
            ->name('comvis.api.orcamentos.update');

        // UC-FLX-01 · US-COMVIS-020: aprovar o orçamento cria a OS sem redigitar (idempotente)
        Route::post('orcamentos/{id}/aprovar', [OrcamentoController::class, 'aprovar'])
            ->whereNumber('id')
            ->name('comvis.api.orcamentos.aprovar');

        // US-COMVIS-023: link para o cliente aceitar o orçamento (token só nesta resposta)
        Route::post('orcamentos/{id}/link', [OrcamentoAceiteController::class, 'gerar'])
            ->whereNumber('id')
            ->name('comvis.api.orcamentos.link');
        // US-COMVIS-003 (decisão [W] 2026-10-26): roteiro padrão com que a OS nasce no quadro do PCP
        Route::get('roteiro-padrao', [RoteiroPadraoController::class, 'show'])->name('comvis.api.roteiro-padrao.show');
        Route::put('roteiro-padrao', [RoteiroPadraoController::class, 'update'])->name('comvis.api.roteiro-padrao.update');

        // Arquivos do orçamento (arte, foto do local) — copiados para a OS ao aprovar (UC-FLX-01)
        Route::post('orcamentos/{id}/arquivos', [OrcamentoArquivoController::class, 'store'])
            ->whereNumber('id')
            ->name('comvis.api.orcamentos.arquivos.store');

        Route::delete('orcamentos/{id}/arquivos/{media}', [OrcamentoArquivoController::class, 'destroy'])
            ->whereNumber('id')
            ->whereNumber('media')
            ->name('comvis.api.orcamentos.arquivos.destroy');

        // Sprint 1 — US-COMVIS-004: Spool Plotter / Apontamento de Produção
        // ATENÇÃO: em-andamento registrado antes de {apontamento} pra evitar conflito de rota
        Route::get('apontamentos/em-andamento', [ApontamentoController::class, 'emAndamento'])
            ->name('comvis.api.apontamentos.em_andamento');

        Route::get('apontamentos', [ApontamentoController::class, 'index'])
            ->name('comvis.api.apontamentos.index');

        Route::post('apontamentos/iniciar', [ApontamentoController::class, 'iniciar'])
            ->name('comvis.api.apontamentos.iniciar');

        Route::post('apontamentos/{apontamento}/finalizar', [ApontamentoController::class, 'finalizar'])
            ->name('comvis.api.apontamentos.finalizar');

        Route::post('apontamentos/{apontamento}/cancelar', [ApontamentoController::class, 'cancelar'])
            ->name('comvis.api.apontamentos.cancelar');

        // US-COMVIS-007 · UC-FLX-21: agenda da equipe de instalação (equipe + veículo; recusa sem janela).
        Route::get('instalacoes', [InstalacaoController::class, 'index'])
            ->name('comvis.api.instalacoes.index');

        Route::post('os/{os}/instalacao', [InstalacaoController::class, 'agendar'])
            ->whereNumber('os')
            ->name('comvis.api.instalacoes.agendar');

        // US-COMVIS-005: custo orçado × real e margem da OS (flag COMVIS_POS_CALCULO_ENABLED, desligada).
        Route::get('os/{os}/pos-calculo', [OsPosCalculoController::class, 'show'])
            ->whereNumber('os')
            ->name('comvis.api.os.pos-calculo');
    });

// ── US-COMVIS-023 · página pública do aceite do orçamento (sem login) ──
// A empresa vem do token (só o sha256 fica no banco), nunca da URL. Throttle 30/min/IP contra
// varredura de token; `web` dá sessão + CSRF ao formulário.
Route::middleware(['web', 'throttle:30,1'])
    ->prefix('orcamento-cv')
    ->name('comvis.aceite.')
    ->group(function () {
        Route::get('{token}', [OrcamentoAceitePublicoController::class, 'mostrar'])->where('token', '[A-Za-z0-9]{48}')->name('mostrar');
        Route::post('{token}/aceitar', [OrcamentoAceitePublicoController::class, 'aceitar'])->where('token', '[A-Za-z0-9]{48}')->name('aceitar');
        Route::post('{token}/ajuste', [OrcamentoAceitePublicoController::class, 'ajuste'])->where('token', '[A-Za-z0-9]{48}')->name('ajuste');
    });
