---
slug: 0305-modo-suporte-cross-tenant-exceto-operador
number: 305
title: "Modo Suporte: acesso cross-tenant da equipe, exceto a empresa operadora (biz=1)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-23"
module: core
tags: [multi-tenant, suporte, cross-tenant, superadmin, auditoria, tier-0, acesso]
supersedes: []
related: ["0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
---

# ADR 0305 — Modo Suporte: acesso cross-tenant da equipe, exceto a empresa operadora (biz=1)

> **Status:** aceito — decisão do Wagner (sessão 2026-06-23: *"ver tudo dos clientes, menos a empresa 1"*). Promovido de [proposta](proposals/2026-06-23-modo-suporte-cross-tenant-exceto-operador.md) a ADR canon; a ratificação se concretiza com a aprovação/merge desta PR pelo Wagner. Pareada com a [SPEC Suporte](../requisitos/Suporte/SPEC.md) (roadmap). O PLAN de implementação Tier 0 (desenho detalhado) é commitado junto à 1ª PR de código — quando o módulo ganha "door" e deixa de ser ghost no scorecard SDD.

## Contexto

- O oimpresso é multi-tenant: o isolamento por `business_id` é a regra **Tier 0** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)). A **única** exceção legítima hoje é o `superadmin` — cross-tenant **total**, Wagner-only ([SPEC Superadmin](../requisitos/Superadmin/SPEC.md), [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) Art. 6).
- A **equipe de suporte do operador** (oimpresso / WR2 Sistemas) precisa entrar nas empresas-cliente pra dar suporte — ver e atuar, **incluindo o financeiro do cliente**. Hoje o único caminho pra alcançar outra empresa é ser `superadmin` → **tudo-ou-nada**.
- Dar `superadmin` à equipe **expõe o financeiro do operador** (que vive na biz=1) e o de todos os clientes, e abre **caminho de escalonamento cross-tenant** (auto-promoção, alcance da biz=1). Inaceitável.

## Decisão

Criar a capability **"Modo Suporte"** — acesso cross-tenant a **todas as empresas-cliente, EXCETO a empresa operadora (biz=1)** — auditado e sem escalonamento. Distinta de `superadmin`.

1. **Regra formal:** `suporte ⊂ (todas as empresas \ operador)`. Como o financeiro e os dados privados do operador vivem na biz=1, **excluir a biz=1 protege o operador automaticamente** — sem bloquear permissão-a-permissão (sem "vazamento" de custo/lucro/dashboard).
2. **O `business_id` do operador vem de config** — `OPERATOR_BUSINESS_ID` (default `1`), nunca chumbado no código. Prepara multi-operador no futuro.
3. **Resolução central de tenants acessíveis** (um único ponto) computa "todas as empresas ativas exceto o operador". A exclusão da biz=1 vive **só ali** — nunca como `if business_id == 1` espalhado.
4. **Capability concedida/revogada por conta** (RF4) — não é o `superadmin` global. Quem concede é o `superadmin` (Wagner).
5. **Auditoria append-only** de cada acesso a um tenant pelo agente de suporte (RF3) — quem · qual empresa · quando · rota/ação. Registro distinto do `mcp_audit_log` (Tier 0, intocável).
6. **Sem escalonamento (invariante dura):** o agente de suporte **não** está em `ADMINISTRATOR_USERNAMES`, logo **não** passa o `Gate::before` do `superadmin`/`backup`/`manage_modules`; **não** pode se auto-promover; **não** alcança a biz=1 nem adulterando o `business_id` da requisição.
7. **`superadmin` (Wagner) segue pleno** (vê tudo, inclusive a biz=1).

## Consequências

**Positivas:** a equipe dá suporte completo aos clientes; o financeiro/dados do operador ficam protegidos **por design** (a equipe nem entra na biz=1); trilha de auditoria; o modelo escala pra N clientes/operadores; substitui o `superadmin` tudo-ou-nada como caminho de suporte.

**Custos / riscos:** é **código novo** (capability + resolução de tenants acessíveis + auditoria + UI de "entrar como suporte" + concessão/revogação). Por ser **exceção ao multi-tenant**, exige blindagem por **testes Tier 0** (suporte NÃO alcança biz=1 · alcança cliente · auditoria grava · não escala pra `superadmin` · exclusão dirigida por config). Cuidado contínuo pra não virar caminho de escalonamento.

**Tier 0:** nova exceção **explícita e auditada** ao isolamento — porém **mais restrita** que o `superadmin` (exclui o operador). **Não enfraquece** o isolamento entre clientes (cada entrada num tenant é deliberada e auditada).

## Alternativas consideradas (e por que não)

1. **Dar `superadmin` à equipe** — expõe o financeiro do operador + abre escalonamento cross-tenant. ❌
2. **Cargo por-empresa ("Operação") na biz=1** — não resolve suporte: suporte é cross-tenant; cargo é preso a uma empresa. ❌
3. **Tornar o financeiro opt-in no código (global)** — afeta todos os clientes e ainda não resolve o cross-tenant do suporte. ❌
4. **`if ($business_id == 1)` chumbado** — magic number; o dinheiro vaza em vários pontos (dashboard/custo/lucro), repetiria o `if` em cada um; não escala. ❌
5. **Modo Suporte (escolhido)** — resolve o suporte (cross-tenant) **e** protege o operador (exclui biz=1) **e** audita, num único ponto de resolução. ✅

## Refs

- [SPEC Suporte](../requisitos/Suporte/SPEC.md) · [proposta original](proposals/2026-06-23-modo-suporte-cross-tenant-exceto-operador.md)
- [SPEC Superadmin](../requisitos/Superadmin/SPEC.md) — cross-tenant Wagner-only (o Modo Suporte é o "superadmin restrito, sem a biz=1")
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant isolation Tier 0 (e exceções `superadmin`) · [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Art. 6 multi-tenant)
