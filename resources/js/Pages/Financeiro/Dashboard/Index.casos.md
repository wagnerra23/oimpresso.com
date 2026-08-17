---
id: resources-js-pages-financeiro-dashboard-index-casos
casos: Dashboard (DORMENTE) · /financeiro → 301 /financeiro/unificado
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro (Dashboard, dormente)

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

**Tela dormente** (tier C): desde 2026-06-06 ([W] "não vou usar o dashboard") `/financeiro` e `/financeiro/dashboard` fazem **301 pra `/financeiro/unificado`**. O Controller e a Page seguem no repo, reversíveis, sem rota que os renderize.

## [BACKLOG] A landing do módulo é a Visão Unificada
Status: ⬜ sem prova — não há teste do 301 — é o caso que o próprio pedido aponta como candidato a 3 linhas. Vira `UC-DASH-01` quando existir teste citando o id (G-2).
Quando alguém acessa `/financeiro` ou `/financeiro/dashboard` · Então recebe 301 pra `/financeiro/unificado`, preservando query string.
**Pronto quando:** existe teste do 301 (hoje o comportamento é só comentário/rota).

## Backlog de casos (sem id — só se a tela voltar a ser viva)
- **[BACKLOG] 4 KPIs clicáveis aplicam filtro** · **[BACKLOG] Saldo em bancos por conta** · **[BACKLOG] Tabela paginada 25/pág com aging** · **[BACKLOG] Filtros bookmarkable via partial reload** · **[BACKLOG] Tier 0**.

## Decisão pendente [W]
Manter dormente (tier C) **ou** reativar. Enquanto dormente, o único caso que vale prova é o 301 — declarar os outros seria contrato de tela que ninguém abre.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork, deliberadamente magro (não investir contrato em tela dormente).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
