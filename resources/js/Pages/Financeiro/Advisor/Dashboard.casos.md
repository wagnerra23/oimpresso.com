---
id: resources-js-pages-financeiro-advisor-dashboard-casos
casos: Portal do contador · /advisor/dashboard
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /advisor/dashboard

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft** (Onda 31 · US-FIN-037). Portal isolado, **read-only**, guard `web-advisor`.

## UC-ADVD-01 — Ver só os clientes que concederam acesso
Status: 🧪 (`Advisor/Onda31AdvisorPortalTest`)
Quando o contador entra · Então vê no grid apenas os businesses com grant ATIVO (`revoked_at` nulo), com data da concessão e escopo.

## [BACKLOG] Falta de consentimento LGPD é inequívoca
Status: ⬜ sem prova — consented_at só aparece como fixture em scope_json; nada assere badge nem KPI de pendência. Vira `UC-ADVD-02` quando existir teste citando o id (G-2).
Cliente sem consentimento aparece com badge de pendência e os KPIs de topo contam quantos estão pendentes.

## UC-ADVD-03 — Entrar no cliente é read-only forçado
Status: 🧪 (`Onda31AdvisorPortalTest` + middleware `AdvisorViewScope`)
O link leva a `/financeiro/{unificado,relatorios}?advisor_view=1&business_id=X` e qualquer tentativa de mutação é barrada pelo middleware.

## UC-ADVD-04 — Grant revogado desaparece na hora
Status: 🧪 (`Onda31AdvisorPortalTest`)
Revogado no lado do cliente · Então o card sai do grid e a URL do cliente deixa de responder.

## [BACKLOG] Sem números cross-client
Status: ⬜ sem prova — Non-Goal declarado, sem asserção. Vira `UC-ADVD-05` quando existir teste citando o id (G-2).
O portal nunca soma receita/saldo de clientes diferentes num mesmo número.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork (leva 4).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
