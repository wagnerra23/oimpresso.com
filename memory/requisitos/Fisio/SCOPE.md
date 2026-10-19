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
  - "Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) com pontuação calculada no servidor (US-FISIO-011)"
not_contains:
  - "Envio de WhatsApp → Modules/Whatsapp (driver whatsmeow via ChannelDriverFactory)"
  - "Cobrança, pacotes e títulos → Financeiro / RecurringBilling (F3)"
  - "Emissão de nota → NFSe (F3)"
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
  - /fisio/p/{token}/* (portal público do paciente — a criar)
---

# Modules/Fisio

## Por que existe

[W] decidiu em 2026-10-17 que o produto de fisioterapia nasce como módulo do oimpresso, não como produto separado; a ADR 0437 foi aceita em 2026-10-18.

## Fronteira

O Fisio é dono do domínio clínico (paciente, prontuário, programa, check-in, jogo). Mensagem, cobrança e nota pertencem aos módulos do núcleo e são chamados, nunca duplicados.

## Referências

- [SPEC](SPEC.md) · [HANDOFF da F1](HANDOFF-F1.md) · [etapas](etapas/)
- [ADR 0437](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md) · [ADR 0093 — multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
