---
id: resources-js-pages-ads-admin-project-show-charter
page: /ads/admin/projects/{id}
component: Modules/Forja/Resources/js/Pages/ads/Admin/ProjectShow.tsx
related_us: [US-ADS-004]
related_prototype: n/a (detalhe bespoke — banda de KPIs + decomposição em <ol>; sem FsmActionPanel/<dl>/Timeline, logo sem a assinatura do padrão de Detalhe)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Forja
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/projects/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Forja/Http/Controllers/Admin/ProjectsController@show` (rota `ads.admin.projects.show`, `whereNumber('id')`) + `@decompose` (POST). Detalhe do Project: KPIs estratégicos, parts decompostas e métricas de sucesso.
>
> **Acesso:** login **e** a permissão do módulo Forja (`jana.mcp.usage.all`), nas 4 ações do controller — decisão [W] D10 (2026-10-07). Sem ela, 403.
>
> **Casos de uso:** [`ProjectShow.casos.md`](ProjectShow.casos.md) (UC-ADPS-01..05; testes `Modules/Forja/Tests/Feature/AdsAdminProjectShowContratoTest.php`, `ProjectDecomposeTenantTest.php` e `ForjaProjectsAcessoTest.php`).

---

## Mission
Mostrar o Project por dentro: viability/custo/prazo, a decomposição em Parts (ordem, dependências, viability/risco, estimativas, arquivos previstos) e as métricas de sucesso. Quando ainda em draft e sem parts, oferecer o disparo do Project Decomposer Agent (Claude Sonnet) pra gerar a decomposição estratégica. É a tela onde a estratégia vira plano executável.

---

## Goals — Features (faz)
- KPIs: viability score (com tom por faixa), custo estimado, prazo estimado, contagem de parts (e concluídas).
- Decomposição em `<ol>`: por part — código/ordem, nome, status, viability/risco, horas, valor, dependências, arquivos estimados (details).
- Métricas de sucesso (quando houver).
- Botão "Decompor com IA" (só quando draft e sem parts) → `POST /ads/admin/projects/{id}/decompose`, com `confirm()` avisando custo (~30s, ~5k tokens).
- EmptyState quando ainda não decomposto.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita as parts nem o project manualmente aqui — decomposição vem do agente.
- ❌ Não executa as parts — só exibe.
- ❌ Não lista decisões ligadas ao project. A fonte morreu com a ADR 0363 (a tabela de decisões do ADS foi dropada) e a seção saiu deste charter por decisão [W] D11 (2026-10-07).
- ❌ Não abre para quem só está logado — exige a permissão do módulo Forja (D10).
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
