<?php

use Modules\Fisio\Http\Controllers\BibliotecaController;
use Modules\Fisio\Http\Controllers\BibliotecaMidiaController;
use Modules\Fisio\Http\Controllers\BibliotecaFavoritosController;
use Modules\Fisio\Http\Controllers\InstallController;
use Modules\Fisio\Http\Controllers\PacientesController;
use Modules\Fisio\Http\Controllers\PainelController;
use Modules\Fisio\Http\Controllers\ProntuarioPdfController;
use Modules\Fisio\Http\Controllers\QuestionariosController;
use Modules\Fisio\Http\Controllers\RegistrosController;
use Modules\Fisio\Http\Controllers\ResponsavelController;
use Modules\Fisio\Http\Middleware\FisioLiberado;

/*
 * Esqueleto (ADR 0437, US-FISIO-002).
 *
 * 1) Rotas Install 1-clique (ADR 0024 / BaseModuleInstallController). Ficam FORA do
 *    gate do pacote: instalar acontece antes de qualquer empresa ter o pacote. Sem
 *    elas o helper action() em Install/ModulesController vira '#' e o botão "Install"
 *    de /manage-modules não faz nada.
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('fisio')
    ->group(function () {
        Route::post('install',           [InstallController::class, 'install']);
        Route::get('install',           [InstallController::class, 'index']);
        Route::match(['get', 'post'], 'install/uninstall', [InstallController::class, 'uninstall']);
        Route::match(['get', 'post'], 'install/update',    [InstallController::class, 'update']);
    });

/*
 * 2) Área da clínica: TODA rota autenticada do Fisio entra neste grupo. FisioLiberado
 *    devolve 403 para empresa sem o pacote `fisio_module` ou usuário sem `fisio.access`.
 *    As rotas de negócio (Painel, Pacientes, prontuário, programas, biblioteca) chegam
 *    com as US seguintes (US-FISIO-003 em diante), sempre dentro deste grupo.
 *
 *    O portal público do paciente (/fisio/p/{token}) NÃO entra aqui: tem grupo próprio,
 *    sem auth, na US do portal (US-FISIO-007).
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', FisioLiberado::class])
    ->prefix('fisio')
    ->name('fisio.')
    ->group(function () {
        // Painel do profissional (US-FISIO-010): KPIs, fila de atenção e tabela. Só leitura.
        Route::get('/', [PainelController::class, 'index'])->name('painel');

        // Biblioteca de exercícios (US-FISIO-004). Escrita exige `fisio.editar` (checada no controller).
        Route::get('biblioteca', [BibliotecaController::class, 'index'])->name('biblioteca.index');
        Route::post('biblioteca/exercicios', [BibliotecaController::class, 'store'])->name('biblioteca.exercicios.store');
        Route::put('biblioteca/exercicios/{id}', [BibliotecaController::class, 'update'])->whereNumber('id')->name('biblioteca.exercicios.update');
        // Foto ou vídeo do exercício PRÓPRIO (UC-FBI-05..07). Servida por rota com checagem de empresa, nunca por URL de disco.
        Route::post('biblioteca/exercicios/{id}/midia', [BibliotecaMidiaController::class, 'store'])->whereNumber('id')->name('biblioteca.exercicios.midia.store');
        Route::get('biblioteca/exercicios/{id}/midia', [BibliotecaMidiaController::class, 'show'])->whereNumber('id')->name('biblioteca.exercicios.midia.show');
        Route::delete('biblioteca/exercicios/{id}/midia', [BibliotecaMidiaController::class, 'destroy'])->whereNumber('id')->name('biblioteca.exercicios.midia.destroy');
        // Favoritos do profissional na empresa da sessão (exige só fisio.access).
        Route::post('biblioteca/exercicios/{id}/favorito', [BibliotecaFavoritosController::class, 'store'])->whereNumber('id')->name('biblioteca.exercicios.favorito.store');
        Route::delete('biblioteca/exercicios/{id}/favorito', [BibliotecaFavoritosController::class, 'destroy'])->whereNumber('id')->name('biblioteca.exercicios.favorito.destroy');

        // Pacientes e prontuário (US-FISIO-005). Escrita exige `fisio.editar` (checada no controller).
        Route::get('pacientes', [PacientesController::class, 'index'])->name('pacientes.index');
        Route::post('pacientes', [PacientesController::class, 'store'])->name('pacientes.store');
        // Busca de contato para o cadastro (UC-FPI-06) — JSON, exige `fisio.editar`.
        Route::get('pacientes/contatos', [PacientesController::class, 'contatos'])->name('pacientes.contatos');
        Route::get('pacientes/{id}', [PacientesController::class, 'show'])->whereNumber('id')->name('pacientes.show');
        Route::put('pacientes/{id}', [PacientesController::class, 'update'])->whereNumber('id')->name('pacientes.update');
        Route::delete('pacientes/{id}', [PacientesController::class, 'destroy'])->whereNumber('id')->name('pacientes.destroy');

        // Fisioterapeuta responsável: transferir o paciente (UC-FPS-31). Exige `fisio.editar`.
        Route::post('pacientes/{id}/responsavel', [ResponsavelController::class, 'store'])->whereNumber('id')->name('pacientes.responsavel.store');

        Route::post('pacientes/{id}/registros', [RegistrosController::class, 'store'])->whereNumber('id')->name('pacientes.registros.store');
        // Prontuário é append-only: estes verbos existem só para responder 405 com o motivo.
        Route::match(['put', 'patch', 'delete'], 'pacientes/{id}/registros/{registro}', [RegistrosController::class, 'imutavel'])
            ->whereNumber(['id', 'registro'])->name('pacientes.registros.imutavel');

        // Exportação do prontuário em PDF (UC-FPS-19..20). Leitura: basta `fisio.access`; a exportação fica no activity_log.
        Route::get('pacientes/{id}/prontuario.pdf', [ProntuarioPdfController::class, 'show'])->whereNumber('id')->name('pacientes.prontuario.pdf');

        // Questionários de uso livre (US-FISIO-011). Pontuação calculada no servidor; só criação.
        Route::post('pacientes/{id}/questionarios', [QuestionariosController::class, 'store'])->whereNumber('id')->name('pacientes.questionarios.store');
    });