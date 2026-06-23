---
page: /suporte/empresas
component: resources/js/Pages/Suporte/Empresas.tsx
page_id: suporte-empresas
owner: wagner
status: draft
parent_module: Suporte
related_adrs:
  - 0305-modo-suporte-cross-tenant-exceto-operador
  - 0093-multi-tenant-isolation-tier-0
mission: "Dar ao agente de suporte um ponto de entrada read-only para escolher qual empresa-cliente atender, sem nunca alcançar a operadora."
---

# Charter — Suporte / Empresas

> Contrato vivo da tela. Lei sobre o [casos](Empresas.casos.md). Backend + RUNBOOK no `main`; aval visual do screenshot dado pelo Wagner (2026-06-23). Pareado com [SPEC](../../../../memory/requisitos/Suporte/SPEC.md) + [RUNBOOK](../../../../memory/requisitos/Suporte/RUNBOOK-empresas.md).

## Mission

O agente de suporte precisa escolher **qual cliente** atender. Esta tela lista, **somente leitura**, as empresas-cliente acessíveis (**todas exceto a operadora biz=1**, via `SupportAccessService`) e leva à visão do cliente. A autorização e a auditoria vivem no middleware `EnsureSupportAccess`.

## Goals

- G1. Em ≤2 cliques, o agente chega na visão de um cliente.
- G2. A lista deixa claro que a **operadora não aparece** (subtítulo explícito).
- G3. Busca local rápida por nome/ID.

## Non-Goals

- ❌ Editar/criar/excluir qualquer coisa (read-only; "atuar" é fase A futura).
- ❌ Mostrar a empresa operadora (biz=1) — bloqueado por design.
- ❌ Conceder/revogar capability (é outra tela).
- ❌ Paginação/virtualização agora (lista de clientes do operador é pequena).

## UX targets

- AppShellV2 + sidebar light (UI-0009). PT-BR. `tabular-nums` no ID.
- Tokens shadcn semânticos (sem cor crua). Dark mode ok (contraste ≥ 4.5:1).
- Estados: cheia · busca-sem-resultado · vazia ("Nenhuma empresa-cliente acessível").
- Botão primário "Entrar (suporte)" por linha.

## Anti-hooks

- O agente NÃO pode confundir esta tela com a operação normal — o destino (visão do cliente) carrega banner de Modo Suporte.
- Nunca depender da sessão pra escopar dados do cliente — toda leitura é por `business_id` explícito (ver SPEC §Desenho seguro; o switch de sessão foi descartado por risco de vazamento).
- Agente de suporte NÃO é superadmin (os global scopes confinam ele à empresa).
