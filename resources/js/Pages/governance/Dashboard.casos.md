---
id: resources-js-pages-governance-dashboard-casos
casos: Dashboard · Painel consolidado de governança · /governance/dashboard
irmaos: Dashboard.charter.md (lei) · RUNBOOK-dashboard.md (receita + smoke) · governance-dashboard-gap.md (GAP-SPEC canon)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente das lanes `E2E Playwright · UCs críticos` (e2e) e `PHP / Pest (Unit)` (Pest, depois do wiring na .github/ci-sqlite-pest.list)"
---

# Casos de uso — /governance/dashboard

> **Status:** ✅ passa (provado por teste no manifesto) · 🧪 em teste (teste escrito que morde, aguarda o veredito chegar ao manifesto) · ⬜ não verificado · ❌ quebrou.

> **De onde os casos vêm.** Ordem de fonte do `how-trabalhar.md` §"Quando [W] pede o contrato da tela": a lei é o [`Dashboard.charter.md`](Dashboard.charter.md) (v3); o escopo é a **US-GOV-001** (`memory/requisitos/Governance/SPEC.md`); a receita e o smoke são o [`RUNBOOK-dashboard.md`](../../../../memory/requisitos/Governance/RUNBOOK-dashboard.md); a paridade com o protótipo já foi resolvida linha a linha no GAP-SPEC [`governance-dashboard-gap.md`](../../../../memory/requisitos/Governance/governance-dashboard-gap.md) (veredito VIVO-À-FRENTE). O `.tsx` e o `DashboardController` entraram **só para confirmar comportamento**, nunca para derivar caso — teste tautológico é lápide §5 de 2026-06-05.

> **Critério de parada.** Vira UC com id só o comportamento com contrato em **≥2 fontes** e com teste que o cite (G-2). O que tem contrato mas nenhum teste ficou como `[BACKLOG]` sem id no fim — UC sem teste é órfão e trava o PR de quem for atendê-lo.

> **Força do veredito, dita pelo dono e não deduzida.** Pela união `classic_protection ∪ rulesets` de [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json) (consultada em 2026-10-14): a lane **`E2E Playwright · UCs críticos`** (roda `e2e/governance-dashboard.spec.ts`) **não** está na lista — é **advisory**, reprova visível sem bloquear merge. A **`PHP / Pest (Unit)`** **está** na lista, mas só executa o que a `.github/ci-sqlite-pest.list` nomeia, e os dois Pest citados abaixo **ainda não estão lá** — até o wiring, eles não rodam em lane de PR nenhuma (o `DashboardExtensionTest` ainda pula no MySQL por desenho, `markTestSkipped` era-sqlite).

> **Nenhum UC nasce ✅.** O G-7 deriva o ✅ do manifesto `scripts/casos-test-results.json`; nenhum destes UCs foi colhido de JUnit ainda.

## UC-GDSH-01 — O painel mora em `/governance/dashboard`, e a raiz redireciona pro hub
Status: 🧪 (e2e `governance-dashboard.spec.ts` — "UC-GDSH-01 · a raiz /governance redireciona 302 pra /ia" + "UC-GDSH-01 · /governance/dashboard renderiza o componente governance/Dashboard")
**Persona:** Wagner (o charter: "painel único onde [W] opera governança em ~5min/dia").
O charter §Mission fixa a rota real — `/governance/dashboard`, nome `governance.admin.dashboard.legacy` —
e registra que a raiz `/governance` é `redirect('/ia', 302)` desde 2026-05-22. O RUNBOOK §2/§3 passo 1
repete os dois fatos. O SPEC US-GOV-001 ainda escreve a rota como `GET /governance` — divergência
registrada abaixo, não consertada aqui.
**Pronto quando:** `GET /governance` responde 302 com `Location` contendo `/ia`; `GET /governance/dashboard`
renderiza o h1 "Governança" e o payload Inertia servido traz `component: "governance/Dashboard"`.
**Regressão que defende:** refatoração de rota que mande a raiz pro `/login` (também 302) ou que troque o componente resolvido.

## UC-GDSH-02 — As props deferidas chegam sem derrubar a tela
Status: 🧪 (e2e `governance-dashboard.spec.ts` — "UC-GDSH-02 · o painel resolve as props deferidas sem exceção de runtime")
`sdd` e `mcp` são `Inertia::defer` (charter §UX Targets; RUNBOOK §3 passo 2). O charter tem um Automation
Anti-hook literal para isso — "⛔ Consumir a prop `mcp` fora do `<Deferred>`" — porque é o incidente de
2026-05-25 na tela de origem (`TypeError undefined.find` **em prod**); o RUNBOOK §7 repete a pegadinha.
A prova é pela **consequência** (nenhuma exceção não-tratada depois do deferido chegar), não pela presença
do wrapper no fonte.
**Pronto quando:** após `networkidle` em `/governance/dashboard`, `pageerror` não registrou nada e o h1 segue visível.
**Regressão que defende:** desestruturar `mcp`/`sdd` direto — reabre o incidente de 2026-05-25.

## UC-GDSH-03 — O KPI de conformidade da Constituição aparece com a data da próxima revisão
Status: 🧪 (e2e `governance-dashboard.spec.ts` — "UC-GDSH-03 · o KPI de conformidade da Constituição aparece com a régua que a tela declara")
O SPEC US-GOV-001 lista o "Compliance score heurístico Constituição v2" entre os 6 KPIs que [W] quer ver
"em <5s sem clique adicional"; o charter §Goals põe o grid `Constituição` (cols=6) com `<KpiCard>` shared.
O valor é **literal** no controller (soma fixa, sem fonte apurada — GAP-SPEC, bloco de 2026-09-09), então o
caso trava o **contrato da tela** (o card existe, com a próxima revisão), **não** o número: pinar o
percentual transformaria uma decisão de régua do [W] em teste vermelho.
**Pronto quando:** o rótulo "Conformidade" e o texto "próx revisão" estão visíveis no painel.
**Regressão que defende:** o card sumir do grid da Constituição, ou perder a data de revisão.

## UC-GDSH-04 — Saúde do ecossistema conta só as últimas 24 h
Status: 🧪 (Pest `tests/Feature/Modules/Governance/DashboardExtensionTest.php` — os 4 casos "UC-GDSH-04 …": failed jobs, custo de IA, última narrativa, narrativas recentes. ⚠️ **Não roda em lane de PR**: sqlite-only por desenho e fora da `.github/ci-sqlite-pest.list` — ver "Linha de lane" no relatório do PR)
O charter §Goals põe o grid `Saúde do ecossistema` (cols=3) e a lista de narrativas "24h"; o RUNBOOK §1
nomeia as três perguntas e as fontes (`failed_jobs`, `jana_mensagens`, `jana_health_narratives`). O
docblock do Pest ancora na extensão US-COPI-098 (charter v2).
**Pronto quando:** registros mais antigos que 24 h não entram na contagem de failed jobs nem no custo de IA;
a última narrativa traz `severity`/`message`/`generated_at`; a lista de narrativas recentes tem no máximo 5,
a mais nova primeiro.
**Regressão que defende:** janela que vaze histórico antigo pro KPI de topo (o painel deixa de responder "o que aconteceu hoje").

## UC-GDSH-05 — Fonte ausente vira "—", nunca erro
Status: 🧪 (mesmo Pest — os 4 casos "UC-GDSH-05 … retorna null/array vazio quando … ausente". ⚠️ Mesma ressalva de lane do UC-GDSH-04)
Charter §Goals, literal: "Degradação graciosa: `failed_jobs` / `jana_mensagens` / `jana_health_narratives` /
`mcp_sdd_scorecard_history` / `mcp_audit_log` ausentes → KPI '—' ou `<EmptyState>`, nunca erro". O RUNBOOK §4
lista o estado `degraded`. O GAP-SPEC registra a degradação por fonte como paridade com o protótipo.
**Pronto quando:** sem a tabela, cada fonte da saúde devolve `null` (KPI) ou `[]` (lista) em vez de lançar.
**Regressão que defende:** query direta sem checar a tabela — derruba o painel inteiro em dev/test e em base sem o módulo Jana completo.
⚠️ **Cobertura parcial, declarada:** o Pest cobre as 3 fontes da saúde; `mcp_sdd_scorecard_history` e
`mcp_audit_log` ausentes **não** têm teste — ficam no BACKLOG abaixo.

## UC-GDSH-06 — O período da seção MCP é whitelist no servidor e degrada pra 30 dias
Status: 🧪 (Pest `tests/Feature/Modules/Governance/DashboardFiltrosMcpTest.php` — 4 casos "UC-GDSH-06 …", nascidos neste PR, sem banco. ⚠️ Não roda em lane até entrar na `.github/ci-sqlite-pest.list`)
Charter §Goals: "filtro de período por query string `mcp_preset` / `mcp_de` / `mcp_ate` (whitelist
server-side)". RUNBOOK §3 passo 3: "Whitelist server-side (`hoje|ontem|7d|30d|mes_anterior|custom`); valor
fora da lista, ou `custom` sem as duas pontas em `YYYY-MM-DD`, degrada pra `30d` — nunca 500."
**Pronto quando:** cada preset da lista é respeitado; preset lixo, ausente ou em formato de array volta `30d`;
`custom` com as duas datas `YYYY-MM-DD` passa; `custom` sem uma das pontas, ou com data em outro formato,
volta `30d` com as datas zeradas.
**Regressão que defende:** `Carbon::parse()` de lixo vindo da URL (500), ou o preset da URL atravessando sem validação.

## Divergência registrada, não consertada

- **SPEC US-GOV-001 × charter/RUNBOOK sobre a rota.** O SPEC diz `GET /governance`; o charter v3 e o RUNBOOK
  dizem `/governance/dashboard` e que a raiz redireciona pra `/ia`. Pela precedência (teste > casos > charter >
  SPEC) o UC-GDSH-01 segue o charter. Alinhar o SPEC é tocar SPEC legado (acorda o `anchor-lint` diff-aware) —
  fica proposto, não aplicado.
- **SPEC US-GOV-001 diz "8/10 plenos = 80%"; o comentário do controller diz 7 plenos + 2 parciais = 80.** A
  conta difere, o resultado não. Não é UC (o número não é contrato da tela — UC-GDSH-03), e a régua por
  artigo foi rejeitada em 2026-09-09.

## Backlog (contrato em ≥2 fontes, sem teste que o cite — sem id de propósito)

- [BACKLOG] Seção "Governança MCP" visível **só** com `jana.mcp.usage.all`; sem ela some inteira, título incluso, e a prop `mcp` nem é enviada (charter §Goals + Anti-hook "não somar `governance.dashboard.view`"; RUNBOOK §3 passo 5 e §5). O charter já declara a lacuna: "não há teste cobrindo o gate da seção MCP".
- [BACKLOG] Trocar o período recarrega só `mcp` + `mcp_filters` (partial reload) — Constituição e SDD não re-rodam (charter §Goals + §UX Targets; RUNBOOK §3 passo 4).
- [BACKLOG] Ocorrências em 24 h mostram no máximo 10 itens — visão de topo, histórico longo é `governance/Audit` (charter §Non-Goals; GAP-SPEC linha "Lista Ocorrências em 24 h").
- [BACKLOG] `mcp_audit_log` ausente → `mcp` volta `null` e a seção mostra `<EmptyState>` "Trilha do MCP indisponível" (charter §Goals; RUNBOOK §4 `degraded`; GAP-SPEC).
- [BACKLOG] Sem snapshot SDD → estado "Sem snapshot SDD ainda" em vez de erro (charter §Goals degradação; GAP-SPEC linha "Card Scorecard SDD").
- [BACKLOG] `<GovernancaSubNav active="dashboard" />` como primeiro filho, lista lida de `shell.menu` e nunca duplicada na tela (charter §Goals + Anti-hook; RUNBOOK §3 passo 6).

## Perguntas sem fonte (para o [W])

- **Gatear a ROTA `/governance/dashboard` por `can:governance.dashboard.view`?** Charter e RUNBOOK registram que hoje a rota é só `auth` e chamam a mudança de "decisão [W] à parte". Sem decisão, não há UC de acesso à rota.
- **O "Compliance score" é contrato de valor ou só de presença?** O SPEC lista o KPI; nenhuma fonte define a régua que o produz (literal no controller). Hoje o UC-GDSH-03 trava só a presença.
