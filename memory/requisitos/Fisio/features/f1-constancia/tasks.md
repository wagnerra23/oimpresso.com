---
id: requisitos-fisio-features-f1-constancia-tasks
feature: f1-constancia
module: Fisio
---

# Tasks — F1 Constância do módulo Fisio

> **Estado de workflow (todo/doing/done) vive no MCP** (`tasks-create ... parent_plan:"fisio-f1-constancia"`,
> ADR 0070) — este arquivo é o **plano versionado**: ordem, dependências e DoD. Por isso NÃO há
> `status:` aqui (ADR 0302 — done-ness se lê da âncora da US no SPEC, nunca de checkbox).
> Executar em ordem topológica de `blocked_by:`. Task atômica = 1 sessão consegue fechar.
> Detalhe de cada task: [HANDOFF-F1.md](../../HANDOFF-F1.md) e [etapas/](../../etapas/).

### T-01 · Criar o esqueleto `Modules/Fisio` e trazer a suíte TDD
> blocked_by: — · covers: AC-2 · us: US-FISIO-002 · estimate: 4h

Molde do `Modules/Producao` (DataController, InstallController, pacote `fisio_module`, permissões `fisio.*`, menu condicionado), `ModuleUtil` pelo container. Registrar `Modules/Fisio/Tests/{Feature,Unit}` no `phpunit.xml`.

**DoD:** `FisioModuloVisibilidadeTest` e `FisioModuloContratoTest` verdes no CI MySQL; Unit tests rodando (vermelhos até as tasks seguintes, sem erro de sintaxe).

### T-02 · Migrations, models e append-only
> blocked_by: T-01 · covers: AC-1, AC-3 · us: US-FISIO-003 · estimate: 6h

As 11 tabelas do HANDOFF; `Exercise` com escopo `business_id = sessão OU nulo`; `fisio_records` e `fisio_game_ledger` sem UPDATE/DELETE.

**DoD:** `FisioMultiTenantTest` verde; UPDATE em registro assinado falha no teste.

### T-03 · Seed da biblioteca revisada
> blocked_by: T-02 · covers: AC-4 · us: US-FISIO-004 · estimate: 4h

38 exercícios com `times_per_day`, avisos e `contraindication_flags`, seguindo `etapas/01-biblioteca.md`.

**DoD:** `FisioBibliotecaTest` verde; revisão da Dra. Dejeane anotada no PR.

### T-04 · Pacientes e prontuário
> blocked_by: T-02 · covers: AC-3 · us: US-FISIO-005 · estimate: 8h

Paciente a partir de `contact`, marcadores de condição, avaliação/evolução/adendo com carimbo.

**DoD:** `FisioPacienteCrudTest` e `FisioProntuarioTest` verdes.

### T-05 · Editor de programa, protocolos-modelo e aviso de contraindicação
> blocked_by: T-03, T-04 · covers: AC-4 · us: US-FISIO-006 · estimate: 8h

**DoD:** `FisioProgramaEditorTest` verde (409 → confirmação com justificativa gravada no prontuário).

### T-06 · Portal do paciente e check-in
> blocked_by: T-05 · covers: AC-5, AC-6, AC-7 · us: US-FISIO-007 · estimate: 12h

**DoD:** `FisioPortalAcessoTest`, `FisioCheckinTest`, `FisioPortalSegurancaClinicaTest` verdes.

### T-07 · Jogo de constância
> blocked_by: T-06 · covers: AC-8 · us: US-FISIO-008 · estimate: 8h

**DoD:** `FisioJogoTest`, `NivelCalculatorTest`, `SequenciaCalculatorTest` verdes; `config/fisio.php` com os números de `etapas/02-f1-constancia.md`.

### T-08 · Lembrete e envio por WhatsApp
> blocked_by: T-06 · covers: AC-9 · us: US-FISIO-009 · estimate: 6h

**DoD:** `FisioWhatsappJobTest` verde; teto por hora e intervalo aleatório configuráveis; alerta de canal caído.

### T-09 · Painel e fila de atenção
> blocked_by: T-07 · covers: AC-10 · us: US-FISIO-010 · estimate: 8h

**DoD:** `FisioPainelTest`, `AdesaoCalculatorTest`, `AtencaoDetectorTest` verdes; telas no AppShellV2 com charter e `casos.md`.

### T-10 · Questionários de uso livre
> blocked_by: T-04 · covers: AC-11 · us: US-FISIO-011 · estimate: 6h

**DoD:** pontuação de cada instrumento conferida contra a publicação em teste Pest.

### T-11 · Fechar o loop — âncora da US + smoke real
> blocked_by: T-08, T-09, T-10 · covers: AC-1 · us: US-FISIO-012 · estimate: 30min

Smoke real (R1 — evidência literal) + atualizar `**Implementado em:**` das US no SPEC (ADR 0273).

**DoD:** âncoras vivas (`node scripts/governance/anchor-lint.mjs memory/requisitos/Fisio/SPEC.md` → `anchored_ok`) + evidência de smoke colada no PR.
