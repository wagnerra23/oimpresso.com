---
page: /memcofre/modulos/{module}
component: resources/js/Pages/MemCofre/Modulo.tsx
related_prototype: n/a (tela de detalhe de módulo bespoke, com abas — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: MemCofre
related_us: [US-DOCVAULT-004]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /memcofre/modulos/{module} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/SRS/Http/Controllers/ModuloController@show` (rota `memcofre.modulo`, prefixo `/memcofre`, stack admin UltimatePOS + `throttle:60,1`). Usa `RequirementsFileReader::readModule` — 404 se o módulo não tem arquivo em `memory/requisitos/`. Módulo `Modules/SRS` ("Cofre de Memórias") — ferramenta interna Wagner de uso raro, em deprecação segundo o BRIEFING.
>
> Classificação: **SILENCIOSO** — é uma tela de detalhe com abas (overview/arquitetura/stories/regras/ADRs/glossário/runbook/diagramas/contratos/auditorias/changelog/raw), mas sem a assinatura estrutural do PT-03 (não tem `FsmActionPanel`/`Timeline`/`<dl`/`StatCard`); layout bespoke.

---

## Mission
Ver os requisitos consolidados de um módulo num só lugar: KPIs (stories/regras/DoD/tela React), frontmatter, e abas que expõem cada artefato do `.md`/pasta do módulo — arquitetura, user stories (com DoD e onde foi implementado), regras Gherkin (com onde foi testado), ADRs (master-detail filtrável por categoria), glossário, runbook, diagramas, contratos, auditorias, changelog e markdown cru.

---

## Goals — Features (faz)
- KPIs do módulo: nº de stories (e implementadas), regras (e testadas), % de DoD, se tem tela React.
- Abas primárias inline + excedente num menu "Mais" (overflow) pra não estourar em mobile.
- Stories e regras como listas com estado (implementado/testado) e barra de progresso de DoD.
- ADRs em master-detail, com filtro por categoria e badge de status (accepted/proposed/superseded riscado).
- Panes markdown (`SimpleMarkdown`) pra arquitetura/glossário/runbook/changelog; sub-arquivos (diagramas/contratos/auditorias) em master-detail; aba "Markdown" com raw.
- Suporta formato "pasta" (README/ARCHITECTURE/SPEC/CHANGELOG separados) vs "plano" (arquivo único), com dica de migração no formato plano.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita o `.md` do módulo por aqui — é viewer read-only. _Inferência pendente de Wagner._
- ❌ Não migra o formato plano→pasta automaticamente — só sugere o caminho manual. _Inferência pendente de Wagner._
- ❌ Não é dado multi-tenant: lê arquivos de `memory/requisitos/` do servidor, igual pra todos os businesses (ferramenta interna). _Confirmar intenção com Wagner._

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb "Cofre › Módulo").

---

## Automation hooks (faz)
- `RequirementsFileReader::readModule` faz o parse do `.md`/pasta (frontmatter, stories, rules, ADRs, sub-arquivos) a cada request.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não regenera nem reprocessa o `.md` ao abrir — só lê o estado atual.
- ❌ Não faz polling; troca de aba é client-side sobre os dados já carregados.
- ❌ Nenhuma mutação — GET puro.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — abas primárias + overflow "Mais"
- [ ] Confirmar comportamento 404 (módulo sem arquivo) no smoke
