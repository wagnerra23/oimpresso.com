<?php

use Modules\Fisio\Http\Controllers\AgendaController;
use Modules\Fisio\Http\Controllers\BibliotecaController;
use Modules\Fisio\Http\Controllers\ConfigAgendamentoController;
use Modules\Fisio\Http\Controllers\BibliotecaMidiaController;
use Modules\Fisio\Http\Controllers\BibliotecaFavoritosController;
use Modules\Fisio\Http\Controllers\ConfiguracoesController;
use Modules\Fisio\Http\Controllers\DocumentosPacienteController;
use Modules\Fisio\Http\Controllers\EnvioWhatsappController;
use Modules\Fisio\Http\Controllers\InstallController;
use Modules\Fisio\Http\Controllers\LaudoBiopsicossocialController;
use Modules\Fisio\Http\Controllers\MedicamentosPacienteController;
use Modules\Fisio\Http\Controllers\OnboardingController;
use Modules\Fisio\Http\Controllers\PacientesController;
use Modules\Fisio\Http\Controllers\FiscalController;
use Modules\Fisio\Http\Controllers\PainelController;
use Modules\Fisio\Http\Controllers\PesquisaController;
use Modules\Fisio\Http\Controllers\PacotesController;
use Modules\Fisio\Http\Controllers\PortalAgendaController;
use Modules\Fisio\Http\Controllers\PortalController;
use Modules\Fisio\Http\Controllers\PortalLinkController;
use Modules\Fisio\Http\Controllers\ProgramasController;
use Modules\Fisio\Http\Controllers\FolhaProgramaController;
use Modules\Fisio\Http\Controllers\ProntuarioPdfController;
use Modules\Fisio\Http\Controllers\ProtocolosController;
use Modules\Fisio\Http\Controllers\QuestionariosController;
use Modules\Fisio\Http\Controllers\RegistrosController;
use Modules\Fisio\Http\Controllers\ResponsavelController;
use Modules\Fisio\Http\Controllers\TranscricaoDocumentoController;
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
 *    sem auth (bloco 3, abaixo).
 */
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', FisioLiberado::class])
    ->prefix('fisio')
    ->name('fisio.')
    ->group(function () {
        // Painel do profissional (US-FISIO-010): KPIs, fila de atenção e tabela. Só leitura.
        Route::get('/', [PainelController::class, 'index'])->name('painel');

        // Área do estudo (etapa 07 P-04). Exige `fisio.pesquisa` (no controller), além de fisio.access.
        Route::get('pesquisa', [PesquisaController::class, 'index'])->name('pesquisa.index');
        Route::post('pesquisa/estudos', [PesquisaController::class, 'store'])->name('pesquisa.estudos.store');
        Route::post('pesquisa/estudos/{study}/participantes', [PesquisaController::class, 'incluir'])->whereNumber('study')->name('pesquisa.participantes.store');
        Route::post('pesquisa/estudos/{study}/participantes/{participant}/saida', [PesquisaController::class, 'saida'])
            ->whereNumber('study')->whereNumber('participant')->name('pesquisa.participantes.saida');
        Route::post('pesquisa/estudos/{study}/eventos/{evento}/revisar', [PesquisaController::class, 'revisarEvento'])
            ->whereNumber('study')->whereNumber('evento')->name('pesquisa.eventos.revisar');
        Route::get('pesquisa/estudos/{study}/exportar/{arquivo}', [PesquisaController::class, 'exportar'])
            ->whereNumber('study')->whereIn('arquivo', ['momentos', 'diario', 'participantes', 'dicionario'])->name('pesquisa.exportar');
        Route::post('pesquisa/estudos/{study}/travar', [PesquisaController::class, 'travar'])->whereNumber('study')->name('pesquisa.travar');
        // Assistente de IA da pesquisadora (etapa 07 P-08): desligado por padrão, só na área do estudo.
        Route::post('pesquisa/assistente', [PesquisaController::class, 'assistente'])->name('pesquisa.assistente');
        Route::post('pesquisa/estudos/{study}/sugestoes', [PesquisaController::class, 'sugerir'])->whereNumber('study')->name('pesquisa.sugestoes.store');
        Route::post('pesquisa/estudos/{study}/sugestoes/{sugestao}/{decisao}', [PesquisaController::class, 'decidirSugestao'])
            ->whereNumber('study')->whereNumber('sugestao')->whereIn('decisao', ['aceitar', 'descartar'])->name('pesquisa.sugestoes.decidir');

        // Configurações › WhatsApp: a clínica escolhe o canal; número e driver são do módulo Whatsapp.
        Route::get('configuracoes/whatsapp', [ConfiguracoesController::class, 'whatsapp'])->name('configuracoes.whatsapp');
        Route::put('configuracoes/whatsapp', [ConfiguracoesController::class, 'salvarWhatsapp'])->name('configuracoes.whatsapp.update');
        // Agenda da clínica (US-FISIO-014/015). Leitura com fisio.access; escrita exige `fisio.editar` (no controller).
        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('agenda/atendimentos', [AgendaController::class, 'store'])->name('agenda.atendimentos.store');
        Route::post('agenda/atendimentos/{id}/status', [AgendaController::class, 'status'])->whereNumber('id')->name('agenda.atendimentos.status');
        Route::post('agenda/atendimentos/{id}/cancelar', [AgendaController::class, 'cancelar'])->whereNumber('id')->name('agenda.atendimentos.cancelar');
        Route::post('agenda/bloqueios', [AgendaController::class, 'bloquear'])->name('agenda.bloqueios.store');
        Route::delete('agenda/bloqueios/{id}', [AgendaController::class, 'desbloquearHorario'])->whereNumber('id')->name('agenda.bloqueios.destroy');
        Route::post('agenda/espera', [AgendaController::class, 'entrarNaEspera'])->name('agenda.espera.store');
        Route::delete('agenda/espera/{id}', [AgendaController::class, 'sairDaEspera'])->whereNumber('id')->name('agenda.espera.destroy');
        // Configurações › Agendamento pelo app (US-FISIO-015). Salvar exige `fisio.editar` (no controller).
        Route::get('configuracoes/agendamento', [ConfigAgendamentoController::class, 'index'])->name('configuracoes.agendamento');
        Route::put('configuracoes/agendamento', [ConfigAgendamentoController::class, 'update'])->name('configuracoes.agendamento.update');
        Route::post('pacientes/{id}/liberar-agendamento', [AgendaController::class, 'liberarApp'])->whereNumber('id')->name('pacientes.liberar-agendamento');

        // Primeira configuração da clínica (Onboarding, 5 passos). Cada passo grava o dele; nada obrigatório.
        Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding');
        Route::put('onboarding/{passo}', [OnboardingController::class, 'salvar'])->where('passo', 'perfil|atendimento|canal|protocolos|concluir')->name('onboarding.salvar');

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

        // Laudo biopsicossocial no modelo SES-SC (US-FISIO-060). Escrita exige `fisio.editar` (no controller).
        Route::post('pacientes/{id}/laudos-bps', [LaudoBiopsicossocialController::class, 'store'])->whereNumber('id')->name('pacientes.laudos-bps.store');
        Route::put('laudos-bps/{laudo}', [LaudoBiopsicossocialController::class, 'update'])->whereNumber('laudo')->name('laudos-bps.update');
        Route::post('laudos-bps/{laudo}/equipe', [LaudoBiopsicossocialController::class, 'incluirMembro'])->whereNumber('laudo')->name('laudos-bps.equipe.store');
        Route::delete('laudos-bps/{laudo}/equipe/{membro}', [LaudoBiopsicossocialController::class, 'removerMembro'])->whereNumber(['laudo', 'membro'])->name('laudos-bps.equipe.destroy');
        Route::post('laudos-bps/{laudo}/finalizar', [LaudoBiopsicossocialController::class, 'finalizar'])->whereNumber('laudo')->name('laudos-bps.finalizar');
        Route::get('laudos-bps/{laudo}/apoio-fisio', [LaudoBiopsicossocialController::class, 'apoioFisio'])->whereNumber('laudo')->name('laudos-bps.apoio-fisio');
        Route::get('laudos-bps/{laudo}/pdf', [LaudoBiopsicossocialController::class, 'pdf'])->whereNumber('laudo')->name('laudos-bps.pdf');
        Route::post('laudos-bps/{laudo}/nova-versao', [LaudoBiopsicossocialController::class, 'novaVersao'])->whereNumber('laudo')->name('laudos-bps.nova-versao');

        // Exames e receitas do paciente (US-FISIO-063). Anexar e tirar da lista exigem fisio.editar (no controller);
        // o arquivo sai só pela rota que confere a empresa. Documento do prontuário não se apaga.
        Route::post('pacientes/{id}/documentos', [DocumentosPacienteController::class, 'store'])->whereNumber('id')->name('pacientes.documentos.store');
        Route::post('documentos/{documento}/tirar', [DocumentosPacienteController::class, 'tirar'])->whereNumber('documento')->name('documentos.tirar');
        Route::get('documentos/{documento}/arquivo', [DocumentosPacienteController::class, 'arquivo'])->whereNumber('documento')->name('documentos.arquivo');
        // Transcrição pela IA, conferida pelo profissional (US-FISIO-063 parte 3). Tudo exige fisio.editar; desligada por empresa.
        Route::post('documentos/transcricao-ia', [TranscricaoDocumentoController::class, 'configurar'])->name('documentos.transcricao-ia');
        Route::post('documentos/{documento}/transcricao', [TranscricaoDocumentoController::class, 'sugerir'])->whereNumber('documento')->name('documentos.transcricao.store');
        Route::post('transcricoes/{transcricao}/confirmar', [TranscricaoDocumentoController::class, 'confirmar'])->whereNumber('transcricao')->name('transcricoes.confirmar');
        Route::post('transcricoes/{transcricao}/descartar', [TranscricaoDocumentoController::class, 'descartar'])->whereNumber('transcricao')->name('transcricoes.descartar');
        // Medicamentos em uso, estruturados (US-FISIO-063 parte 2). Escrita exige fisio.editar; sai da lista com motivo.
        Route::post('pacientes/{id}/medicamentos', [MedicamentosPacienteController::class, 'store'])->whereNumber('id')->name('pacientes.medicamentos.store');
        Route::put('medicamentos/{medicamento}', [MedicamentosPacienteController::class, 'update'])->whereNumber('medicamento')->name('medicamentos.update');
        Route::post('medicamentos/{medicamento}/tirar', [MedicamentosPacienteController::class, 'tirar'])->whereNumber('medicamento')->name('medicamentos.tirar');

        // Link do portal do paciente (US-FISIO-007a): gerar devolve link + PIN UMA vez; revogar derruba.
        Route::post('pacientes/{id}/portal', [PortalLinkController::class, 'store'])->whereNumber('id')->name('pacientes.portal.store');
        Route::delete('pacientes/{id}/portal', [PortalLinkController::class, 'destroy'])->whereNumber('id')->name('pacientes.portal.destroy');

        // Aba "Pacote e cobrança" (US-FISIO-021): escreve só sessões; dinheiro é leitura do núcleo.
        Route::get('pacientes/{id}/pacote', [PacotesController::class, 'show'])->whereNumber('id')->name('pacientes.pacote');
        Route::post('pacientes/{id}/pacotes', [PacotesController::class, 'store'])->whereNumber('id')->name('pacientes.pacotes.store');
        Route::post('pacotes/{pacote}/consumir', [PacotesController::class, 'consumir'])->whereNumber('pacote')->name('pacotes.consumir');
        Route::post('pacotes/{pacote}/venda', [PacotesController::class, 'ligarVenda'])->whereNumber('pacote')->name('pacotes.venda');
        Route::post('pacotes/lancamentos/{lancamento}/estornar', [PacotesController::class, 'estornar'])->whereNumber('lancamento')->name('pacotes.estornar');
        // Documentos fiscais do dia (US-FISIO-023): NFS-e lida do NFSe; Receita Saúde só marca o envio.
        Route::get('fiscal', [FiscalController::class, 'index'])->name('fiscal.index');
        Route::post('fiscal/recibos/{payment}/enviado', [FiscalController::class, 'marcarEnviado'])->whereNumber('payment')->name('fiscal.recibos.enviado');

        // Exportação do prontuário em PDF (UC-FPS-19..20). Leitura: basta `fisio.access`; a exportação fica no activity_log.
        Route::get('pacientes/{id}/prontuario.pdf', [ProntuarioPdfController::class, 'show'])->whereNumber('id')->name('pacientes.prontuario.pdf');
        // Laudo de evolução (Lote 2): síntese para médico ou convênio, mesmo acesso e mesmo rastro do prontuário.
        Route::get('pacientes/{id}/laudo.pdf', [ProntuarioPdfController::class, 'laudo'])->whereNumber('id')->name('pacientes.laudo.pdf');

        // Questionários de uso livre (US-FISIO-011). Pontuação calculada no servidor; só criação.
        Route::post('pacientes/{id}/questionarios', [QuestionariosController::class, 'store'])->whereNumber('id')->name('pacientes.questionarios.store');

        // Editor de programa (US-FISIO-006). JSON; escrita exige `fisio.editar` (checada no controller).
        // `itens/ordem` vem antes de `itens/{item}` para não ser lido como id.
        Route::post('pacientes/{id}/programas', [ProgramasController::class, 'store'])->whereNumber('id')->name('pacientes.programas.store');
        Route::put('programas/{id}', [ProgramasController::class, 'update'])->whereNumber('id')->name('programas.update');
        Route::post('programas/{id}/itens', [ProgramasController::class, 'adicionarItem'])->whereNumber('id')->name('programas.itens.store');
        Route::put('programas/{id}/itens/ordem', [ProgramasController::class, 'reordenar'])->whereNumber('id')->name('programas.itens.ordem');
        Route::put('programas/{id}/itens/{item}', [ProgramasController::class, 'atualizarItem'])->whereNumber(['id', 'item'])->name('programas.itens.update');
        Route::delete('programas/{id}/itens/{item}', [ProgramasController::class, 'removerItem'])->whereNumber(['id', 'item'])->name('programas.itens.destroy');
        Route::post('programas/{id}/protocolo', [ProgramasController::class, 'aplicarProtocolo'])->whereNumber('id')->name('programas.protocolo');
        // US-FISIO-009: envio do link do portal pelo módulo Whatsapp (fila) ou wa.me manual sem canal ativo.
        Route::post('programas/{id}/enviar', [EnvioWhatsappController::class, 'enviar'])->whereNumber('id')->name('programas.enviar');
        // B-12: folha A4 do programa para imprimir. Leitura (fisio.access); não emite nem revoga link do portal.
        Route::get('programas/{id}/folha.pdf', [FolhaProgramaController::class, 'show'])->whereNumber('id')->name('programas.folha');
        Route::post('programas/{id}/salvar-como-protocolo', [ProtocolosController::class, 'salvarDoPrograma'])->whereNumber('id')->name('programas.salvar-protocolo');

        // Protocolos-modelo: copiar um modelo (global ou da clínica) e editar a cópia da clínica.
        // Tela dos protocolos (leitura: fisio.access). Lista os modelos do produto e os da clínica da sessão.
        Route::get('protocolos', [ProtocolosController::class, 'index'])->name('protocolos.index');
        Route::post('protocolos/{id}/copiar', [ProtocolosController::class, 'copiar'])->whereNumber('id')->name('protocolos.copiar');
        Route::put('protocolos/{id}', [ProtocolosController::class, 'update'])->whereNumber('id')->name('protocolos.update');
    });

/*
 * 3) Portal PÚBLICO do paciente (US-FISIO-007a): sem auth, sem SetSessionData, sem FisioLiberado.
 *    O paciente não é usuário do sistema; o business viaja ASSINADO no token e cada leitura
 *    filtra por ele (PortalTokenService). Throttle por IP contra força bruta, além do lockout
 *    de 5 PINs errados por 30 min que mora na linha do token.
 */
Route::middleware(['web', 'throttle:60,1'])
    ->prefix('fisio/p/{token}')
    ->name('fisio.portal.')
    ->where(['token' => '[A-Za-z0-9_\-\.]{1,512}'])
    ->group(function () {
        Route::get('/', [PortalController::class, 'show'])->name('show');
        Route::get('manifest.webmanifest', [PortalController::class, 'manifest'])->name('manifest');
        Route::post('pin', [PortalController::class, 'pin'])->name('pin');
        Route::post('consentimentos', [PortalController::class, 'consentir'])->name('consentimentos.store');
        Route::delete('consentimentos/{purpose}', [PortalController::class, 'revogar'])->name('consentimentos.destroy');
        // Pesquisa é por estudo (etapa 07 P-02): revogar tira o paciente daquele estudo.
        Route::delete('consentimentos/pesquisa/{study}', [PortalController::class, 'revogarPesquisa'])
            ->whereNumber('study')->name('consentimentos.pesquisa.destroy');
        Route::post('checkin', [PortalController::class, 'checkin'])->name('checkin');
        Route::post('pre-anamnese', [PortalController::class, 'preAnamnese'])->name('pre-anamnese');
        Route::post('desafio', [PortalController::class, 'desafio'])->name('desafio');
        // Agenda do paciente (US-FISIO-016): mesmo portão do portal (PIN + teleatendimento), business do token.
        Route::get('agenda', [PortalAgendaController::class, 'index'])->name('agenda.index');
        Route::get('agenda/horarios', [PortalAgendaController::class, 'horarios'])->name('agenda.horarios');
        Route::post('agenda', [PortalAgendaController::class, 'store'])->name('agenda.store');
        Route::delete('agenda/espera', [PortalAgendaController::class, 'sairDaEspera'])->name('agenda.espera.destroy');
        Route::post('agenda/{id}/{acao}', [PortalAgendaController::class, 'acao'])
            ->whereNumber('id')->whereIn('acao', ['cancelar', 'remarcar', 'confirmar', 'cheguei', 'nota'])->name('agenda.acao');
    });

/*
 * 4) Entrada do app do paciente na Google Play (etapa 02 §3.6 · ADR 0437 §13.7). O app instalado
 *    pela loja abre aqui quando o paciente toca no ícone: sem token, a tela só pede para abrir o
 *    link que a clínica mandou. Não lê banco e não mostra dado nenhum (UC-FPP-29).
 */
Route::middleware(['web', 'throttle:60,1'])
    ->get('fisio/app', [PortalController::class, 'app'])
    ->name('fisio.portal.app');