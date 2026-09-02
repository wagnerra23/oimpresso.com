---
date: "2026-09-02"
time: "2140 BRT"
slug: "jana-abas-alertas-acoes-plataforma"
tldr: "As 3 abas que faltavam no Painel da Jana estão em 3 PRs stacked (#6607 → #6608 → #6609), mais o espelho jana-merge.jsx re-baixado (#6600). CI na FILA no fechamento (60 runs queued). Nada mergeado; smoke R1 depende do merge [W]. Cheques segue sem fonte — decisão [W]."
decided_by: [W]
cycle: null
prs: [6600, 6607, 6608, 6609]
us: ["US-COPI-060", "US-COPI-148"]
next_steps:
  - "Ler `gh pr checks 6607` (a base) — verde ⇒ merge [W] na ordem 6600 → 6607 → 6608 → 6609 (stacked; o GitHub retarget sozinho)"
  - "Smoke R1 pós-merge com screenshot: /ia/alertas (biz=1), /ia/acoes (aprovar UMA ação de leitura e ver o recibo), /ia/superadmin/metas como [W] — receitas em RUNBOOK-alertas/acoes/plataforma §2"
  - "Se a lane `PHP / Pest (Jana · MySQL)` reprovar: a prova é o CONTADOR (3 arquivos novos: Alertas 5 it · Acoes 4 · Plataforma 3); UC-ALERTA-02 / UC-ACAO-02 / UC-PLAT-01 skipam se o seed tiver 1 business só"
  - "Decisão [W]: card Cheques (migrar FINANCEIRO_CHEQUE?) e drawer de config de alertas (US-COPI-061) — os dois ficaram FORA com medição"
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0264-governanca-executavel-trio-dominio-e2e", "0374-emenda-0315-espelho-cowork-e-rota-prevista"]
---

# Handoff 2026-09-02 21:40 BRT — Jana: as 3 abas do Painel em 3 PRs (Alertas · Ações · Plataforma)

## TL;DR

Fecha *"abas: protótipo 6 × prod 3"* do handoff 2026-08-31. Ordem final da barra = a da âncora `JmTabs`: Painel · Conversa · **Alertas** · **Ações** · Memória · **[Plataforma]** (só `jana.superadmin` real). Quatro PRs abertos, nenhum mergeado; CI **na fila** ao fechar. Detalhe do que foi feito e medido no [session log](../sessions/2026-09-02-jana-abas-paridade-3-prs.md).

## O que entrou (por PR)

| PR | conteúdo | atenção no review |
|---|---|---|
| [#6600](https://github.com/wagnerra23/oimpresso.com/pull/6600) | espelho `jana-merge.jsx` STALE → máquina (`--export-from`), ledger registrado | só build/design; `no-ui-smoke` declarado |
| [#6607](https://github.com/wagnerra23/oimpresso.com/pull/6607) | Alertas: `AlertaService::calcular()` extraído de `avaliar()` + `listar()`; Inertia `Jana/Alertas`; Blade stub apagado | a fórmula NÃO mudou (UC-ALERTA-04 pina a régua 1×/1,5×/3×); silenciar/perguntar/config ficaram fora — charter §Anti-hooks |
| [#6608](https://github.com/wagnerra23/oimpresso.com/pull/6608) | Ações: rota `jana.acoes.index`, `AcaoHitlService::TITULOS` + `fila()`, reusa `JanaAcaoModal` | prévia/recibo 100% do servidor; CTA segue "Revisar" |
| [#6609](https://github.com/wagnerra23/oimpresso.com/pull/6609) | Plataforma: `podeVerPlataforma()` (gate real no menu = gate da rota), `JanaSubNav maxVisible 6`, Inertia `Jana/Plataforma` | **corrige** o dropdown legado que usava `can('jana.superadmin')` (bypass do `Gate::before`); `topnav.php` legado ficou como estava |

## Fora, com medição (não é esquecimento)

- **Cheques × `metodos`** — sem fonte (medido 2026-08-31, `Index.casos.md` §UC-JPAIN-18); [W]: *"se a fonte não existir, NÃO invente"*.
- **Config de alertas** (drawer + aviso de topo) — US-COPI-061; `updateConfig` valida e descarta.
- **Contador `n` nas abas** — backend, afeta as 4 telas (R2 do visual-comparison).
- Tamanho dos PRs (986/736/735 linhas) > 300: trio/contrato/teste exigidos no mesmo PR pelos gates.

## Estado MCP no momento do fechamento

⚠️ **Não consultado.** `brief-fetch` caiu em fallback por timeout no início da sessão e não foi retentado; `cycles-active`/`my-work`/`sessions-recent` não responderam. Este handoff é derivado de medição em git/gh. Declarado porque snapshot ausente ≠ snapshot vazio.

## Recibos do fechamento

- `gh run list` nas 4 branches às 21:35 BRT: **todos `queued`** (60 de 60 na branch base) — fila de runners, não falha.
- Gates locais rodados por PR (contrato-de-tela · casos-gate · pt-conformance · anchor-content · charter-refs · charter-us · memory-schemas · eslint · tsc): todos limpos no que é novo; `tsc` acusa só arquivos pré-existentes (`Cliente/Index`, `Sells/Create`, `AssistantUiChat:405`).
- Pest **não** rodou local (ADR 0062) — o veredito é da lane `PHP / Pest (Jana · MySQL)`.
