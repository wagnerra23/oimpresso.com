---
module: Suporte
version: "1.0"
last_updated: "2026-06-23"
status: rascunho
owners:
  - W
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
---

<!-- schema-allowlist: SPEC rascunho de feature nova ("Modo Suporte") — captura requisitos e desenho ANTES da implementação. Sem seção US formal ainda (roadmap abaixo); sem âncoras "Implementado em:" porque nada foi implementado. Origem: sessão Wagner 2026-06-23. -->

# SPEC — Modo Suporte (acesso de suporte cross-tenant, exceto a empresa operadora)

## Visão

Capacidade para a **equipe de suporte do operador** (oimpresso / WR2 Sistemas) acessar e atuar dentro das **empresas-cliente** (tenants) para dar suporte — vendo tudo do cliente, **inclusive o financeiro DELE** — **sem nunca acessar a empresa do próprio operador** (biz=1), onde está o financeiro e os dados privados do dono. Todo acesso é **auditado**.

## Problema / origem

- Hoje, o **único** jeito de alcançar a empresa de um cliente é ser `superadmin` (cross-tenant total), que vê **tudo** — inclusive o financeiro da empresa do operador (biz=1) e de todos os clientes. É **tudo-ou-nada**.
- O operador precisa que a equipe **dê suporte aos clientes** (acesso amplo), mas que **nunca** veja a empresa dele (biz=1, onde está o dinheiro do operador).
- Dar `superadmin` à equipe resolve o suporte mas expõe o financeiro do operador **e** vira caminho de escalonamento cross-tenant — falha de segurança.
- Origem: sessão Wagner 2026-06-23 (controle de acesso da equipe; o "atender clientes" da equipe é, na prática, suporte aos tenants-cliente).

## Regra-mestre

> **O Modo Suporte enxerga e atua em TODAS as empresas-cliente — EXCETO a empresa operadora (biz=1).**

Como o financeiro e os dados privados do operador vivem na biz=1, **excluir a biz=1 protege o operador automaticamente** — sem precisar bloquear permissão por permissão (sem "vazamento" de custo/lucro/dashboard).

## Requisitos funcionais

- **RF1.** Agente de suporte acessa qualquer empresa-cliente com acesso operacional **+ financeiro do cliente** (pra suportar de verdade).
- **RF2.** Agente de suporte **não** acessa a empresa operadora (biz=1) — nem dados, nem financeiro.
- **RF3.** Toda entrada do agente numa empresa-cliente é **auditada** (quem · qual empresa · quando · idealmente o que fez), em registro append-only.
- **RF4.** O acesso de suporte é **concedido/revogado por conta** — não é o `superadmin` global tudo-ou-nada.
- **RF5.** O operador (Wagner) continua `superadmin` pleno (vê tudo, inclusive biz=1).

## Invariantes (Tier 0)

- O isolamento multi-tenant continua sendo a regra. O Modo Suporte é uma **exceção explícita e auditada** — como o `superadmin` já é ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) §exceções, [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) Art. 6) — porém **mais restrita**: exclui a biz=1.
- O Modo Suporte **não pode** virar caminho de escalonamento: um agente de suporte não pode se auto-promover a `superadmin` nem alcançar a biz=1.
- A exclusão da biz=1 é uma **regra central** (resolução de tenants acessíveis), nunca um `if business_id == 1` espalhado pelo código.

## Desenho proposto (alto nível)

- Uma **capability "suporte"** (papel/flag) distinta de `superadmin`.
- No ponto onde o `superadmin` resolve cross-tenant (lista de empresas acessíveis), o Modo Suporte resolve **"todas as empresas EXCETO a operadora"**.
- O id da empresa operadora vem de **config** (ex. `OPERATOR_BUSINESS_ID`, default `1`) — não chumbado no código (multi-operador no futuro).
- Camada de **auditoria** registra cada acesso a tenant pelo agente (append-only).
- Bloqueio duro: agente de suporte sem `superadmin` e sem acesso à biz=1.

## Fora de escopo (v1)

- "Login as / impersonar" usuário específico do cliente (futuro).
- Acesso de suporte com **expiração** temporal (futuro).
- Granularidade por módulo dentro do cliente (v1 = acesso amplo ao tenant do cliente).

## Roadmap

1. **ADR** da decisão arquitetural: exceção controlada ao multi-tenant — `suporte ⊂ (todas as empresas \ operador)`, auditada, sem escalonamento.
2. Capability/flag de suporte + resolução de tenants acessíveis (exceto operador, via config).
3. Auditoria append-only de acesso + UI "entrar como suporte na empresa X".
4. Tela de concessão/revogação do acesso de suporte por conta.
5. Testes Tier 0: suporte NÃO alcança biz=1 · suporte alcança cliente · auditoria grava · não escala pra `superadmin`.

## Relacionado

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant isolation Tier 0 (e exceções `superadmin`).
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Art. 6 multi-tenant).
- [SPEC Superadmin](../Superadmin/SPEC.md) — cross-tenant Wagner-only (o Modo Suporte é o "superadmin restrito, sem a biz=1").
