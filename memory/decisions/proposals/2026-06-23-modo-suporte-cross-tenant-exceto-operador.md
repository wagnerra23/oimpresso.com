---
title: "Modo Suporte: acesso cross-tenant da equipe de suporte, exceto a empresa operadora (biz=1)"
status: proposed
date: "2026-06-23"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
origem: "Sessão Wagner 2026-06-23 (controle de acesso da equipe). Discussão convergiu: o 'atender clientes' da equipe é, na prática, dar SUPORTE aos tenants-cliente (cross-tenant); a regra que o Wagner cravou foi 'ver tudo dos clientes, menos a empresa 1'."
prs: []
---

# Modo Suporte: acesso cross-tenant da equipe de suporte, exceto a empresa operadora (biz=1)

> Proposta de ADR. Pareada com a [SPEC Suporte](../../requisitos/Suporte/SPEC.md). Ratificar (Wagner) → vira ADR canon numerada.

## Contexto

- O oimpresso é multi-tenant: isolamento por `business_id` é a regra **Tier 0** ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)). A **única** exceção legítima hoje é o `superadmin` — cross-tenant total, Wagner-only (SPEC Superadmin, [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) Art. 6).
- A **equipe de suporte do operador** precisa entrar nas empresas-cliente pra dar suporte (ver e atuar, incluindo o financeiro **do cliente**). Hoje, o único jeito de alcançar outra empresa é ser `superadmin` → **tudo-ou-nada**.
- Dar `superadmin` à equipe **expõe o financeiro do operador** (biz=1) e o de todos os clientes, e abre **caminho de escalonamento cross-tenant**. Inaceitável.

## Decisão (proposta)

Criar a capability **"Modo Suporte"**: acesso cross-tenant a **todas as empresas-cliente, EXCETO a empresa operadora (biz=1)** — auditado e sem escalonamento.

- Regra formal: **`suporte ⊂ (todas as empresas \ operador)`**.
- Como o financeiro e os dados privados do operador vivem na biz=1, **excluir a biz=1 protege o operador automaticamente** — sem bloquear permissão-a-permissão (sem "vazamento" de custo/lucro/dashboard).
- O `business_id` do operador vem de **config** (`OPERATOR_BUSINESS_ID`, default `1`) — não chumbado no código.
- **Auditoria append-only** de cada acesso a tenant pelo agente de suporte.
- Distinta de `superadmin` (que segue Wagner-only, vendo tudo inclusive biz=1).

## Alternativas consideradas (e por que não)

1. **Dar `superadmin` à equipe** — expõe o financeiro do operador + escalonamento cross-tenant. ❌
2. **Cargo por-empresa ("Operação") na biz=1** — não resolve suporte: suporte é cross-tenant, e cargo é preso a uma empresa. ❌
3. **Tornar o financeiro opt-in no código (global)** — afeta todos os clientes e ainda não resolve o cross-tenant do suporte. ❌
4. **`if business_id == 1` chumbado** — magic number; o dinheiro vaza em vários pontos (dashboard/custo/lucro), teria que repetir o `if` em cada um; não escala. ❌
5. **Modo Suporte (escolhido)** — resolve o suporte (cross-tenant) **e** protege o operador (exclui biz=1) **e** audita. ✅

## Consequências

**Positivas:** equipe dá suporte completo aos clientes; financeiro/dados do operador protegidos **por design** (a equipe nem entra na biz=1); trilha de auditoria; o modelo escala pra N clientes/operadores; substitui o `superadmin` tudo-ou-nada como caminho de suporte.

**Custos / riscos:** é **código novo** (capability + resolução de tenants acessíveis + auditoria + UI de "entrar como suporte"); por ser **exceção ao multi-tenant**, exige blindagem por **testes Tier 0** (suporte NÃO alcança biz=1 · alcança cliente · auditoria grava · não escala pra superadmin); cuidado pra não virar caminho de escalonamento.

**Tier 0:** nova exceção **explícita e auditada** ao isolamento — porém **mais restrita** que o `superadmin` (exclui o operador). Não enfraquece o isolamento entre clientes.

## Próximos passos

- Ratificação (Wagner) → numerar como ADR canon.
- Implementação conforme o roadmap da [SPEC Suporte](../../requisitos/Suporte/SPEC.md).
