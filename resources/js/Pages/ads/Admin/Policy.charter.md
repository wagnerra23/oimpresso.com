---
page: /ads/admin/policy
component: resources/js/Pages/ads/Admin/Policy.tsx
related_prototype: n/a (display read-only de firewall bespoke — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/policy (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/PolicyController@index` (rota `ads.admin.policy.index`, middleware `auth` — V1 superadmin). Lê `PolicyEngine::getAllRules()` (regras hardcoded em `Modules/ADS/Services/PolicyEngine.php`), anota labels via `DecisionPresenter`. Sem business_id — o firewall é global e imutável.
>
> Silêncio de PT é honesto: é um display read-only do firewall em cards por categoria. O único token estrutural é `grid-cols` (layout 2 colunas), sem `<table>`/`DataTable`/`KpiCard` — declarar PT-01 por causa do grid seria count-pump.

---

## Mission
Dar transparência às regras imutáveis do firewall do ADS (ARQ-0006 Policy Engine): quais event_types são bloqueados sempre, exigem revisão humana, exigem Brain B (Claude API) ou podem rodar autônomos no Brain A. É a garantia visível de que nenhuma LLM pode ler, sugerir ou contornar essas regras — mudança só via PR git aprovado por Wagner.

---

## Goals — Features (faz)
- Aviso de topo "Firewall imutável": explica que as regras são hardcoded em PolicyEngine.php e só mudam por PR no GitHub.
- Grid de 4 cards por categoria (BLOCK_ALWAYS, REQUIRE_HUMAN_REVIEW, REQUIRE_BRAIN_B, ALLOW_BRAIN_A) com ícone, descrição, contagem e a lista de event_types (label humano + código).

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO edita, adiciona ou remove regras pela UI — read-only por design (mudança = PR git aprovado por Wagner).
- ❌ NÃO expõe as regras a nenhuma LLM pra sugestão/alteração (princípio do firewall imutável).
- ❌ NÃO escopa por business — é o firewall global do ADS, não configuração per-tenant.
- ❌ NÃO tem estado (sem KPIs, sem filtros, sem paginação) — é referência estática renderizada do PolicyEngine.

---

## UX targets
- p95 < 800ms (leitura de config em memória, sem query pesada) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; grid 2 colunas no desktop.

---

## Automation hooks (faz)
- Nenhum — a tela só renderiza `PolicyEngine::getAllRules()` (config em código), sem defer, job ou endpoint disparado.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh.
- ❌ Sem qualquer escrita — impossível mutar a policy por aqui (nem GET nem POST).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — validar as 4 categorias renderizadas
