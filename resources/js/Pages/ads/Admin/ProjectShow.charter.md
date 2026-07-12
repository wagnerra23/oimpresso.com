---
page: /ads/admin/projects/{id}
component: resources/js/Pages/ads/Admin/ProjectShow.tsx
related_prototype: n/a (detalhe bespoke — banda de KPIs + decomposição em <ol>; sem FsmActionPanel/<dl>/Timeline, assinatura PT-03 ausente)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ProjectMgmt
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/projects/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ProjectMgmt/Http/Controllers/Admin/ProjectsController@show` (rota `ads.admin.projects.show`, `whereNumber('id')`) + `@decompose` (POST). Detalhe do Project: KPIs estratégicos, parts decompostas, métricas de sucesso e decisões geradas.

---

## Mission
Mostrar o Project por dentro: viability/custo/prazo, a decomposição em Parts (ordem, dependências, viability/risco, estimativas, arquivos previstos), as métricas de sucesso e as decisões geradas. Quando ainda em draft e sem parts, oferecer o disparo do Project Decomposer Agent (Claude Sonnet) pra gerar a decomposição estratégica. É a tela onde a estratégia vira plano executável.

---

## Goals — Features (faz)
- KPIs: viability score (com tom por faixa), custo estimado, prazo estimado, contagem de parts (e concluídas).
- Decomposição em `<ol>`: por part — código/ordem, nome, status, viability/risco, horas, valor, dependências, arquivos estimados (details).
- Métricas de sucesso (quando houver) e decisões linkadas ao project (linka pra `/ads/admin/decisoes/{id}`).
- Botão "Decompor com IA" (só quando draft e sem parts) → `POST /ads/admin/projects/{id}/decompose`, com `confirm()` avisando custo (~30s, ~5k tokens).
- EmptyState quando ainda não decomposto.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita as parts nem o project manualmente aqui — decomposição vem do agente.
- ❌ Não executa as parts/decisões — só exibe e linka.
- ❌ Não re-decompõe automaticamente um project já decomposto — botão some quando há parts.
- ❌ Não mostra project de outro business — scopado por `businessId` da sessão (Tier 0). [inferência confirmada no controller]

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 com breadcrumb ADS › Projects › Detalhe.

---

## Automation hooks (faz)
- `decompose` chama `ProjectDecomposerService` (Claude Sonnet) → gera 5–8 parts com viability/dependências/estimativas e registra audit LGPD (EVENT_PROJECT_DECOMPOSED).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ A decomposição por IA (custosa) NUNCA dispara sozinha — só por clique + `confirm()`.
- ❌ Não faz mutação em GET; a página é read-only, decompose é POST explícito.
- ❌ Não faz polling do status das parts.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Definir UX de re-decompor / editar parts manualmente (hoje inexistente)
