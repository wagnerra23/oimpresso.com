---
casos: Saúde do MCP · Scorecard Facts+Checks · /team-mcp/scorecard
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-06-16"
---

# Casos de uso — /team-mcp/scorecard

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Forja PR-3. A rota existia mas o componente nunca existiu (route quebrada) — este trio nasce com a Page. Padrão Facts+Checks (ADR 0091): Facts = números sem juízo, Checks = semáforo ok/fail. Persona: Wagner [W] (superadmin). Tela read-only.

## UC-SC-01 — A rota deixa de quebrar (a Page existe)
Status: ⬜ (smoke pós-merge — abrir `/team-mcp/scorecard` em prod)
Antes deste PR, `ScorecardController@index` renderizava `team-mcp/Scorecard/Index` sem componente → Inertia 500.
**Pronto quando:** abrir `/team-mcp/scorecard` renderiza a tela (sem tela branca / 500).

## UC-SC-02 — Semáforo geral reflete os checks
Status: ⬜ (manual/visual)
Banner no topo: verde "Tudo verde — N/N checks OK" quando todos passam; amarelo "N de M falhando" caso contrário.
**Pronto quando:** com todos os checks `ok=true`, o banner é verde com a contagem certa; com ≥1 `ok=false`, fica amarelo.

## UC-SC-03 — Facts são números reais do builder
Status: 🧪 (cobertura futura — assert sobre `ScorecardBuilderService::buildFacts()`)
KpiCards: tokens ativos · calls 7d · custo 7d (BRL) · devs ativos 7d, + Top tools (7d). Sem juízo, só contagem.
**Pronto quando:** os valores batem 1:1 com o retorno de `buildFacts()` (`tokens_ativos`, `calls_7d`, `cost_7d_brl`, `users_ativos_7d`, `top_tools_7d`).

## UC-SC-04 — Checks listam ok/fail com detalhe
Status: 🧪 (cobertura futura — assert sobre `buildChecks()`)
Lista cada dimensão (schema mcp_tokens/audit_log, brief recente, tokens sem orphan, custo médio sanity) com ícone ok/fail + nome + detail + pill.
**Pronto quando:** cada item de `buildChecks()` aparece com `CheckCircle2` (ok) ou `AlertCircle` (fail) e o `detail` do backend.

## UC-SC-05 — Sem sparkline (sem dado fantasma · §3)
Status: 🧪 (cobertura: o payload não tem série temporal)
O builder só expõe pontos atuais (sem série). A tela NÃO renderiza sparkline fabricado — só Facts+Checks reais.
**Pronto quando:** não há nenhum gráfico de série na tela; nenhum dado derivado é apresentado como medido.

## UC-SC-06 — DS v6 (sem cor crua)
Status: 🧪 (cobertura: eslint `ds/*` = 0 + conformance ratchet)
Tokens semânticos (success/warning/destructive), `tabular-nums`, layout via `inline-flex`/`KpiGrid` — zero paleta crua, zero `rounded-xl+`.
**Pronto quando:** `eslint resources/js/Pages/team-mcp/Scorecard/Index.tsx` = 0 `ds/*` e `conformance-gate` verde.

## UC-SC-07 — Read-only (a tela não muta nada)
Status: ⬜ (manual — só `router.reload` de facts/checks)
Nenhuma ação edita estado; o único efeito é recarregar os dados deferidos (botão Atualizar / atalho R).
**Pronto quando:** não há nenhuma ação na tela que escreva no banco.

## UC-SC-08 — Acesso (auth + permissão)
Status: ⬜ (manual — rota sob `auth` + `copiloto.mcp.usage.all`)
`/team-mcp/scorecard` exige login + `copiloto.mcp.usage.all` (mesma do TeamController). Repo-wide cross-business intencional (ADR 0093) pro superadmin ver saúde global.
**Pronto quando:** usuário sem `copiloto.mcp.usage.all` recebe 403.
