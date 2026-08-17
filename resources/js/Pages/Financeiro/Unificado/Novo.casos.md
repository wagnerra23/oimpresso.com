---
id: resources-js-pages-financeiro-unificado-novo-casos
casos: Novo lançamento (stub/picker) · /financeiro/unificado/novo
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/unificado/novo (stub)

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

**Stub** (tier C): hub de 2 cards que linkam pra `/contas-receber/novo` e `/contas-pagar/novo`. Não tem formulário.

> ⚠️ Conflito de rumo registrado: o **contrato de intenção** da Visão Unificada (`prototipo-ui/contrato/financeiro-unificado.intent.json`, `nao_pode_conter: router.visit('/financeiro/unificado/novo')`) e o charter v21 mandam TODO ponto de entrada abrir o `TituloCreateSheet` — ou seja, **esta tela é rota legada em processo de morte**. Investir contrato aqui é dívida.

## [BACKLOG] A rota legada não é o caminho da criação
Status: ⬜ sem prova — a prova vive do outro lado (auditor de intenção da Unificada), não em teste que cite este UC. Vira `UC-NOV-01` quando existir teste citando o id (G-2).
Nenhum ponto de entrada da Visão Unificada (primary, ⌘K, empty state) navega pra esta rota; quem chega por URL direta escolhe receber/pagar e é encaminhado.

## Backlog de casos (sem id)
- **[BACKLOG] Nenhuma mutação nem redirect automático** — a tela espera o clique (Anti-hook declarado).
- **[BACKLOG] Prop `tipos` não usada** — o Controller manda, a tela ignora: **pendência [W]** (remover a prop ou usá-la).

## Decisão pendente [W]
Manter como picker (tier C) **ou** aposentar a rota agora que o sheet inline existe. Recomendação [CC]: aposentar com 301 pra `/financeiro/unificado`.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork, magro de propósito (tela em rota de saída).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
