---
id: resources-js-pages-financeiro-plano-contas-index-casos
casos: Plano de contas · /financeiro/plano-contas
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/plano-contas

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft**. Tela de CONSULTA de cadastro contábil (~47 entries DCASP por business). **Zero teste próprio hoje** — todos os casos nascem em backlog sem id (G-2).

## Backlog de casos (sem id — entram quando tiverem teste)
- **[BACKLOG] Lista hierárquica ordenada por código** — Dado plano seedado · Quando Eliana abre · Então as contas saem ordenadas por código e indentadas por `nivel`.
- **[BACKLOG] Badge de tipo e natureza D/C** — cada conta mostra tipo (receita/despesa/ativo/passivo+patrim.) e natureza débito/crédito.
- **[BACKLOG] Conta protegida sinaliza cadeado e não é editável aqui** — index é read-only (o Controller só tem `index`).
- **[BACKLOG] KPI strip conta o plano** — total de contas + contagem por tipo bate com as linhas listadas.
- **[BACKLOG] Filtro por tipo + busca client-side** — sem round-trip ao servidor (`useMemo`).
- **[BACKLOG] Empty state instrui o seed** — business sem plano vê a instrução, não uma tabela vazia muda.
- **[BACKLOG] Tier 0** — plano é seedado por tenant e a query filtra `business_id` ([ADR 0093]).
- **[BACKLOG] Primary "Nova conta"** — o charter registra que a rota `create` não foi encontrada no Controller: **confirmar existência antes de virar UC** (senão é botão que mente).

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork. Achado: a tela é o vocabulário que classifica TODO lançamento (o filtro de plano da Unificada depende dela) e está sem uma única prova.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
