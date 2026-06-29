---
slug: 0313-financeiro-subnav-unificada-fidelidade-prototipo
number: 313
title: "Financeiro — barra de abas (subnav) UNIFICADA fiel ao protótipo Cowork (supersede parcial da 0180: desacopla subnav dos ghosts da entry ativa)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-29"
module: Financeiro
quarter: 2026-Q2
tags: [ux, navigation, subnav, pageheader, financeiro, prototipo-cowork, fidelidade, persona-eliana, ADR-0180-tabs, ADR-0299-fonte-design]
supersedes: []
supersedes_partially: ["0180-sidebar-v3-5-grupos-ghosts-header"]
amends: []
superseded_by: []
related:
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0299-figma-nao-e-fonte-de-design"
pii: false
review_triggers:
  - "Eliana/Larissa não achar Contas a Pagar/Receber (foram pro overflow ⋯) → reavaliar maxVisible ou promover de volta"
  - "Barra de 8 abas + lentes + primary estourar largura em 1280px (persona Larissa) → reduzir maxVisible ou reordenar"
  - "Permissão granular: usuário com financeiro.access mas sem acesso a Cobrança/Assinaturas ver a aba → adicionar filtro por permissão na barra unificada (hoje só gate no nível da entry)"
---

# ADR 0313 — Financeiro: subnav unificada fiel ao protótipo Cowork

## Contexto

A [ADR 0180](0180-sidebar-v3-5-grupos-ghosts-header.md) dividiu o grupo FINANÇAS em **4 entries flat** no sidebar (Caixa · Cobrança · Financeiro · Cobrança Recorrente), cada uma com seus próprios `ghosts`, e fez a **barra de abas da tela (subnav)** renderizar os ghosts **da entry ativa**. Resultado: cada tela do Financeiro mostrava só um subconjunto de abas (a tela `/financeiro/unificado` mostrava `Lançamentos · Contas a Pagar · Fluxo · DRE · Relatórios · ⋯`).

O **protótipo Cowork** da Visão Unificada (fonte de design canônica — [ADR 0299](0299-figma-nao-e-fonte-de-design.md)), aprovado por [W] via screenshot em 2026-06-29, mostra uma **barra de abas UNIFICADA**: `Financeiro · Cobrança · Assinaturas · Fluxo de caixa · Conciliação · DRE / Relatórios · Plano de contas · Impostos e obrigações` — itens que, pós-0180, vivem em entries distintas. [W] 2026-06-29: *"SubNav abas pode trocar sim"* + *"sim eu quero"* (fidelidade ao protótipo, no page header).

## Decisão

A barra de abas do Financeiro passa a ser **uma só, unificada e fiel ao protótipo**, renderizada em TODA tela do módulo — desacoplada dos ghosts da entry ativa do sidebar.

- Fonte única: `FINANCEIRO_SUBNAV_GHOSTS` em [`resources/js/Pages/Financeiro/_shared/financeiroMenu.ts`](../../resources/js/Pages/Financeiro/_shared/financeiroMenu.ts). 8 abas do protótipo visíveis (`maxVisible={8}`); destinos legacy (Contas a Pagar/Receber, Categorias, Caixa, Extrato, Contas Bancárias, Contador, Gateway) vão pro overflow `⋯` — **nada se perde**.
- O **sidebar de 4 entries flat da 0180 é PRESERVADO** (não revertido). `pickFinanceiroEntry` continua resolvendo a entry ativa, agora só pra (a) escolher o **PRIMARY contextual** por página (Novo título / Nova cobrança / Abrir caixa) e (b) servir de **gate de permissão** (sem entry FINANÇAS → subnav renderiza `null`).
- Implementação **frontend-only**: nenhuma mudança no menu do backend (`DataController`); o teste de regressão da 0180 (`tests/financeiroSubNav.spec.ts` · `pickFinanceiroEntry`) segue intacto e verde.

## Escopo do supersede

Parcial. Substitui APENAS o acoplamento "subnav = ghosts da entry ativa" da 0180, **para o módulo Financeiro**. Mantém intactos: os 5 grupos canônicos, o split de 4 entries FINANÇAS no sidebar, ghosts ARIA tablist no header, Cmd+K e Pinned da 0180.

## Consequências

- **+** Fidelidade ao protótipo aprovado; navegação consistente entre todas as telas do Financeiro.
- **+** Risco baixo (frontend puro, sem tocar menu backend/Pest); coberto por vitest (`test:fin-subnav`).
- **−** A barra unificada ainda não filtra abas por permissão granular (Cobrança/Assinaturas aparecem a qualquer user com `financeiro.access`; clique passa pelo auth de rota). Catalogado em `review_triggers` pra evoluir se necessário.
- **−** Contas a Pagar/Receber saíram da faixa visível (foram pro overflow) — alinhado ao protótipo, que consolida AR/AP nas lentes (Caixa · A receber · A pagar) da própria Unificada.
