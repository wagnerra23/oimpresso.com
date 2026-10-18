---
id: requisitos-fisio-spec
module: Fisio
version: "1.0"
last_updated: "2026-10-18"
owner: wagner
status: rascunho
status_nota: "ativo desde 2026-10-18 (ADR 0437 aceita); esqueleto de Modules/Fisio no PR #9536 (US-FISIO-002), sem schema nem telas ainda"
type: spec
scope: vertical (fisioterapia, autônomo e consultório pequeno)
related_adrs:
  - 0437-modulo-fisio-vertical-fisioterapia
  - 0093-multi-tenant-isolation-tier-0
  - 0306-strangler-spec-anchored-reconstrucao-sdd
  - 0368-funil-admissao-feature-pesquisa-propoe-w-admite
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0264-governanca-executavel-trio-dominio-e2e
---

# SPEC — Fisio (fisioterapia: programa domiciliar, constância e prontuário)

> **Decisão:** [ADR 0437](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md) — aceita por [W] em 2026-10-18.
> **Responsável técnica do conteúdo clínico:** Dra. Dejeane (fisioterapeuta).
> **Como executar:** [`HANDOFF-F1.md`](HANDOFF-F1.md) (ordem, regras duras, modelo de dados) e as especificações por etapa em [`etapas/`](etapas/).
> **Convenção do ID:** `US-FISIO-NNN`. As US abaixo são a F1 "Constância"; F2 a F4 entram depois, cada uma admitida pelo funil da ADR 0368.

## User Stories — F1 Constância

### US-FISIO-001 · Trio SDD da F1 e US no SPEC — **P0**

**Implementado em:** _pendente_ — trio entregue em features/f1-constancia/, âncora fecha com a US-FISIO-002

> owner: wagner · priority: p0 · estimate: 2h · status: todo · type: story
> `npm run sdd:init -- Fisio/f1-constancia --us US-FISIO-001 ...` e `feature-lint --check` verde. Concilia os 4 ajustes do HANDOFF (XP por config, justificativa de contraindicação, busca na F1 condicionada, WhatsApp pelo `ChannelDriverFactory`).

### US-FISIO-002 · Esqueleto `Modules/Fisio` (ativação por empresa, permissões, menu condicionado) — **P0**

**Implementado em:** _pendente_ — esqueleto no PR #9536 (rotas de negócio, schema e telas ainda não existem); âncora fecha com o CI verde

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: US-FISIO-001. DoD: empresa sem o pacote não vê menu nem rota (`FisioModuloVisibilidadeTest`, `FisioModuloContratoTest`). `ModuleUtil` pelo container.

### US-FISIO-003 · Schema, models e append-only de prontuário e ledger do jogo — **P0**

**Implementado em:** `Modules/Fisio/Database/Migrations/2026_10_18_000001_create_fisio_tables.php` · `Modules/Fisio/Entities/` · verificado@b9a7a58 (2026-10-18, #9537)

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> blocked_by: US-FISIO-002. DoD: isolamento 98×99 (`FisioMultiTenantTest`); UPDATE/DELETE em registro assinado falha (`FisioProntuarioTest`); nenhum `forceDelete` em dado clínico.

**DoD:** as 11 tabelas `fisio_*` com `business_id` e escopo entre 98 e 99; trigger recusa UPDATE/DELETE em `fisio_records` assinado e em `fisio_game_ledger`; nenhum `forceDelete` em dado clínico — `FisioEsquemaContratoTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioEsquemaContratoTest.php`

### US-FISIO-004 · Biblioteca semeada e revisada pela responsável técnica — **P0**

**Implementado em:** _pendente_ — sem seed

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: US-FISIO-003. 38 exercícios com `times_per_day`, avisos de respiração/apoio e `contraindication_flags`. DoD: revisão da Dra. Dejeane anotada no PR. Detalhe: `etapas/01-biblioteca.md`.

### US-FISIO-005 · Pacientes e prontuário conforme Res. COFFITO 414/2012 — **P0**

**Implementado em:** `Modules/Fisio/Http/Controllers/PacientesController.php` · `Modules/Fisio/Http/Controllers/RegistrosController.php` · rotas `fisio.pacientes.*` (`Modules/Fisio/Routes/web.php`) · telas `Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx` e `Show.tsx` (+ charters) — veredito pela lane Verticais (`FisioPacienteCrudTest`, `FisioProntuarioTest`)

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: US-FISIO-003. Paciente é `contact`; avaliação, evolução e adendo carimbados com nome e CREFITO; marcadores de condição. DoD: `FisioPacienteCrudTest`, `FisioProntuarioTest`.

**DoD:** paciente criado sobre um `contact` da empresa, um por contato, meta 1–7 e marcadores da lista fechada; excluir só bloqueia; avaliação/evolução/adendo carimbados com usuário, CREFITO e hora do servidor; avaliação exige os campos mínimos da Res. 414; registro assinado responde 405 a PUT/DELETE — `FisioPacienteCrudTest`, `FisioProntuarioTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioPacienteCrudTest.php` · `Modules/Fisio/Tests/Feature/FisioProntuarioTest.php`

### US-FISIO-006 · Editor de programa, protocolos-modelo e aviso de contraindicação — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: US-FISIO-004, US-FISIO-005. Aviso nunca bloqueia; exige justificativa que vai ao prontuário (ADR 0437 §13.2). DoD: `FisioProgramaEditorTest`.

### US-FISIO-007 · Portal do paciente (link + PIN, consentimentos, check-in, telemonitoramento) — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 12h · status: todo · type: story
> blocked_by: US-FISIO-006. Token HMAC + PIN com lockout (padrão OficinaAuto); consentimentos versionados; check-in idempotente com sinais de alerta; cada check-in vira registro de telemonitoramento (Res. COFFITO 619/2025). DoD: `FisioPortalAcessoTest`, `FisioCheckinTest`, `FisioPortalSegurancaClinicaTest`.

### US-FISIO-008 · Jogo de constância (XP, nível, sequência, missões, pausa por dor) — **P1**

**Implementado em:** _pendente_

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: US-FISIO-007. Regras da ADR 0437 §6 e números de `etapas/02-f1-constancia.md`. DoD: `FisioJogoTest`, `NivelCalculatorTest`, `SequenciaCalculatorTest`.

### US-FISIO-009 · Lembrete diário e envio do programa por WhatsApp (driver whatsmeow) — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> blocked_by: US-FISIO-007. Pelo módulo `Whatsapp` via `ChannelDriverFactory` (nunca whatsmeow direto); mitigações de banimento da ADR 0437 §13.1. DoD: `FisioWhatsappJobTest`; canal caído gera alerta ao profissional.

### US-FISIO-010 · Painel, evolução e fila de atenção — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: US-FISIO-008. Adesão 7d, dor, nível; fila de abandono separada da fila de alerta clínico. DoD: `FisioPainelTest`, `AdesaoCalculatorTest`, `AtencaoDetectorTest`.

### US-FISIO-011 · Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) — **P1**

**Implementado em:** _pendente_

> owner: — · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: US-FISIO-005. Texto da versão brasileira validada, referência na tela; pontuação conferida com a publicação. ODI, NDI, KOOS, QuickDASH e WOMAC bloqueados até licença.

### US-FISIO-012 · Fecha o loop da F1 — **P1**

**Implementado em:** _pendente_

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: US-FISIO-001..011. Âncoras `Implementado em:` com `verificado@sha7` (ADR 0273).
