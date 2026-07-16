---
page: /memcofre
component: resources/js/Pages/MemCofre/Dashboard.tsx
related_prototype: n/a (herda PT-01 Lista; painel de cobertura com tabela por módulo)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: MemCofre
related_us: [US-DOCVAULT-001]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /memcofre (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/SRS/Http/Controllers/DashboardController@index` (rota `memcofre.dashboard`, prefixo `/memcofre`, stack admin UltimatePOS + `throttle:60,1`). O módulo é `Modules/SRS` (ex-SRS renomeado pra "Cofre de Memórias") — ferramenta interna Wagner de uso raro, em deprecação segundo o BRIEFING do módulo (substituída na prática pelo MCP server). Tela implementada de verdade, não é stub.

---

## Mission
Dar a visão consolidada da documentação viva do projeto: quantos módulos estão documentados, quantas user stories e regras existem, o progresso de DoD, e a maturidade de cada módulo (README/Arquitetura/Spec/Changelog/ADRs) numa tabela navegável. É a porta de entrada do Cofre — leva pra Memória, Chat, Inbox e ingestão de evidências.

---

## Goals — Features (faz)
- Mostra KPIs globais: módulos documentados, user stories, regras Gherkin, % de DoD completo (lidos dos `.md` de `memory/requisitos/` via `RequirementsFileReader`).
- Painel de evidências por status (total, pendentes, triadas, aplicadas) — vindo de `DocSource`/`DocEvidence` escopados por `business_id`.
- Painel de maturidade: contagem formato pasta vs plano, score médio, ADRs totais, telas rastreadas (`DocPage`).
- Tabela "Cobertura por módulo" (`<table>`): formato, dots de cobertura, stories, regras, ADRs, telas, trace score, audit score, DoD — cada linha linka pra `/memcofre/modulos/{module}`.
- Lista de fontes recentes (últimas 5 `DocSource`).
- Atalhos no header pra Memória, Chat, Inbox e "Nova evidência".

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nem cria documentação/módulo por aqui — é painel read-only (a mutação vive em Ingest/Inbox). _Inferência pendente de Wagner._
- ❌ Não roda auditoria/trace on-demand — consome os últimos `DocValidationRun` já gravados. _Inferência pendente de Wagner._
- ❌ Não cruza dados entre businesses nos KPIs de evidências (escopo `business_id`); o inventário de módulos vem de arquivos de git, igual pra todos.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb "Cofre de Memórias").

---

## Automation hooks (faz)
- Stats de módulos derivadas de leitura de arquivos `.md` do repo (`RequirementsFileReader::listModules`) a cada request.
- Contagens de evidências/fontes via agregação SQL escopada por `business_id`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling/refresh automático — recarrega só no page load.
- ❌ Não dispara re-scan de trace/audit nem regenera `.md` ao abrir.
- ❌ Nenhuma mutação em GET.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar posição do módulo no ciclo de deprecação (manter charter live vs marcar `historical`)
