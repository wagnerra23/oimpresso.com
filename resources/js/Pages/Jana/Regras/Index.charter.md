---
page: /ia/regras
component: resources/js/Pages/Jana/Regras/Index.tsx
related_prototype: n/a (tela stub read-only — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Jana
related_adrs: [114, 101, 93, 182]
tier: C
charter_version: 1
---

# Page Charter — /ia/regras (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. É um **STUB/placeholder** — não é capacidade viva. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Jana/Http/Controllers/RegrasController@index` (rota `jana.regras.index`, prefixo `/ia`). Ghost canon ADR 0182 + GUIA-SIDEBAR-V3 — mantém o item "Regras" clicável no header do hub IA enquanto a UI dedicada não chega. O controller só passa `businessId` da sessão (a lista real de políticas vem em onda futura).

---

## Mission
Explicar em uma tela informativa os 4 resultados possíveis do PolicyEngine (Dual-Brain) que governam cada decisão da Jana — ALLOW_BRAIN_A, REQUIRE_BRAIN_B, REQUIRE_HUMAN_REVIEW, BLOCK_ALWAYS. Serve de destino canônico ("ghost") do hub IA e ponte para a Governança enquanto a listagem real das políticas ativas não é implementada. Não é ainda uma tela operacional — é placeholder honesto.

---

## Goals — Features (faz)
- Descreve os 4 outcomes do PolicyEngine em cards estáticos (label + descrição + badge por severidade).
- Mostra opcionalmente a contagem de políticas ativas (`count`) quando o controller a passar — hoje o controller ainda não passa `count`.
- Link para a Governança (`/governanca`).
- Usa `PageHeader` canônico dentro do `AppShellV2`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não lista as políticas reais por outcome (TODO explícito no código — depende do controller passar a lista).
- ❌ Não cria/edita/ativa/desativa política — é read-only informativo.
- ❌ Não filtra por business — `businessId` está disponível mas não é usado ainda (`void 0`).
- ❌ Não decide nada em runtime — a governança de decisão vive no PolicyEngine backend, não nesta tela.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; conteúdo em coluna `max-w-3xl`.

---

## Automation hooks (faz)
- Nenhum. Tela puramente estática/informativa (os 4 outcomes são constante no frontend).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não busca dados do PolicyEngine — os outcomes são hardcoded no frontend, sem chamada ao backend além do `businessId` da sessão.
- ❌ Não muta dados em GET — read-only.
- ❌ Não escopa por `business_id` hoje (stub); quando a listagem real chegar, a lista de políticas deve ser scopada ao business da sessão (Tier 0 multi-tenant).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Backend passar a lista real de políticas ativas por outcome (hoje é stub) — sem isso a tela não vira capacidade viva
- [ ] Definir permissão/escopo (business_id) da listagem real antes de sair do estado placeholder
- [ ] Smoke visual 1280/1440 (screenshot)
