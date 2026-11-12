---
module: Fisio
purpose: "Vertical de fisioterapia: programa de exercícios domiciliares, portal do paciente por link + PIN, constância (check-in, jogo, lembrete WhatsApp), prontuário conforme COFFITO e painel de risco de abandono. Decidido pela ADR 0437 em 2026-10-18."
migracao_ui: "n/a — módulo novo, nasce Inertia (sem Blade)"
contains:
  - "Cadastro de pacientes (perfil clínico 1:1 sobre contacts) e prontuário append-only (Res. COFFITO 414/2012)"
  - "Busca de contato para o cadastro e criação do contato na hora pelo ContactUtil do core (UC-FPI-06/07)"
  - "Biblioteca de exercícios, protocolos-modelo e programa por paciente"
  - "Portal público do paciente (token HMAC + PIN, consentimentos, check-in, telemonitoramento Res. 619/2025)"
  - "Jogo de constância (ledger de XP append-only, nível e sequência derivados)"
  - "Painel e fila de atenção do profissional"
  - "BibliotecaController"
  - "BibliotecaMidiaController"
  - "Foto ou vídeo curto do exercício próprio da clínica (UC-FBI-05..07) — servida por rota com checagem de empresa"
  - "BibliotecaFavoritosController — favoritos do profissional na empresa (fisio_exercise_favorites)"
  - "DataController"
  - "PainelController"
  - "InstallController"
  - "PacientesController"
  - "RegistrosController"
  - "ResponsavelController"
  - "Fisioterapeuta responsável pelo paciente e transferência registrada no activity_log"
  - "QuestionariosController"
  - "ProgramasController"
  - "ProtocolosController"
  - "Editor de programa por paciente, protocolos-modelo (aplicar e copiar para a clínica) e aviso de contraindicação que não bloqueia (US-FISIO-006)"
  - "ProntuarioPdfController"
  - "Exportação do prontuário em PDF (dompdf, registrada no activity_log) — UC-FPS-19..20"
  - "FolhaProgramaController — folha A4 do programa para imprimir, sem QR do portal (B-12)"
  - "Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) com pontuação calculada no servidor (US-FISIO-011)"
  - "PortalController — portal público do paciente: PIN com trava, consentimentos separados, lista do dia (US-FISIO-007a)"
  - "PortalLinkController — link do portal gerado e revogado na ficha (US-FISIO-007a)"
  - "EnvioWhatsappController — envio do link do portal pelo módulo Whatsapp (fila) ou wa.me manual sem canal ativo (US-FISIO-009)"
  - "Pacote de sessões: saldo derivado do ledger append-only fisio_package_ledger, sem valor em dinheiro (SaldoDoPacote, US-FISIO-020)"
  - "PacotesController — aba Pacote e cobrança: abre pacote e registra sessão; títulos e NFS-e só lidos do Financeiro e do NFSe (US-FISIO-021)"
  - "VendaDoPacoteObserver — liga a venda do núcleo ao pacote pelo id; venda cancelada tira a sobra do saldo, sem tocar no Financeiro (US-FISIO-022)"
  - "FiscalController — documentos fiscais do dia: NFS-e lida do NFSe e fila do Receita Saúde sobre transaction_payments; grava só a marca de envio em fisio_health_receipts (US-FISIO-023)"
  - "ConfiguracoesController — Configurações › WhatsApp: a clínica escolhe o canal (manual, oficial, QR com aceite de risco), teto e silêncio, em fisio_settings (Lote 5)"
  - "OnboardingController — primeira configuração da clínica em 5 passos (perfil, PF/PJ, canal, protocolos, conclusão) em fisio_settings (US-FISIO-050)"
  - "Agenda: tipos de atendimento, atendimentos, bloqueios, lista de espera e regras do agendamento pelo app (fisio_appointment_types, fisio_appointments, fisio_schedule_blocks, fisio_waitlist, fisio_booking_settings — US-FISIO-013)"
  - "AgendaController — agenda da clínica: semana, marcar, status, desmarcar, bloqueio de horário, lista de espera e liberar o app (US-FISIO-014/015)"
  - "ConfigAgendamentoController — Configurações › Agendamento pelo app: ligar, quem marca, tipos liberados, horários e regras (US-FISIO-015)"
  - "PortalAgendaController — agenda do paciente no portal: horários livres, marcar, remarcar, cancelar, confirmar, Cheguei, nota e lista de espera (US-FISIO-016)"
  - "Laudo de avaliação biopsicossocial no modelo SES-SC (Deliberação 500/CIB/2026): formulário, matriz IFBr-M (MatrizIfbrm) e equipe; finalizado não muda (fisio_laudos_bps, fisio_laudos_bps_equipe — US-FISIO-060)"
  - "LaudoBiopsicossocialController — cria, preenche, monta a equipe, finaliza com as regras do modelo SES-SC e abre nova versão; apoio de dor e adesão (ApoioFisioterapia) e PDF no layout oficial (LaudoBiopsicossocialDocumento) (US-FISIO-060)"
  - "Estudo de pesquisa (fisio_studies) e consentimento `pesquisa` por estudo, com a versão do TCLE aprovada pelo CEP; `treino_ia` separado (etapa 07 P-02)"
  - "PesquisaController — estudo, participantes (código sem nome nem CPF), momentos de avaliação e pendências, com a permissão fisio.pesquisa (etapa 07 P-04)"
  - "Exames e receitas do paciente (fisio_patient_documents): foto ou PDF guardado pelo Modules/Arquivos, servido por rota que confere a empresa; não se apaga, sai da lista com motivo (US-FISIO-063)"
  - "DocumentosPacienteController — anexar, abrir e tirar da lista os exames e receitas na ficha (US-FISIO-063)"
  - "DadosSociaisController — dados sociais do paciente, todos opcionais, em fisio_patient_social; nascimento, município e bairro no contato (US-FISIO-061)"
  - "RelatorioAssociacaoController — relatório agregado da associação por período, tela e PDF, só contagem e média com grupo < 5 como \"<5\" (RelatorioAssociacao, permissão fisio.relatorio — US-FISIO-062)"
not_contains:
  - "Envio de WhatsApp → Modules/Whatsapp (canal da clínica — meta_cloud ou whatsmeow — via ChannelDriverFactory)"
  - "Preço do pacote, cobrança e títulos → Modules/Financeiro (via venda do núcleo); o Fisio só lê fin_titulos pelo model Titulo (F3, US-FISIO-021/022)"
  - "Mensalidade de paciente → Modules/RecurringBilling (F3)"
  - "Emissão de nota → Modules/NFSe (F3); o Fisio só lê nfse_emissoes pelo model NfseEmissao"
  - "Armazenamento de arquivo (foto/vídeo do exercício próprio) → Modules/Arquivos (backbone HasArquivos, ADR 0123/0214)"
excludes:
  - "Prescrição sugerida por IA e câmera com ângulo/correção de forma (SaMD classe II, ADR 0437 §5) até decisão de notificação"
  - "Questionários com licença paga (ODI, NDI, KOOS, QuickDASH, WOMAC) até licença"
depends_on: [Whatsapp]
owner: wagner
status: em construção
url_prefixes:
  - /fisio (painel do profissional — KPIs, fila de atenção e tabela; US-FISIO-010)
  - /fisio/pacientes/* (área do profissional — pacientes, prontuário e questionários; demais telas a criar)
  - /fisio/programas/* (editor de programa — itens, dose, ordem, protocolo)
  - /fisio/protocolos/* (protocolos-modelo — copiar e editar a cópia da clínica)
  - /fisio/p/{token}/* (portal público do paciente — link + PIN e consentimentos; US-FISIO-007a)
---

# Modules/Fisio

## Por que existe

[W] decidiu em 2026-10-17 que o produto de fisioterapia nasce como módulo do oimpresso, não como produto separado; a ADR 0437 foi aceita em 2026-10-18.

## Fronteira

O Fisio é dono do domínio clínico (paciente, prontuário, programa, check-in, jogo). Mensagem, cobrança e nota pertencem aos módulos do núcleo e são chamados, nunca duplicados.

## Referências

- [SPEC](SPEC.md) · [HANDOFF da F1](HANDOFF-F1.md) · [etapas](etapas/)
- [ADR 0437](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md) · [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
