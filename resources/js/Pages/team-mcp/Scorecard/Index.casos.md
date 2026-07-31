---
id: resources-js-pages-team-mcp-scorecard-index-casos
casos: Saúde do MCP · Scorecard Facts+Checks · /team-mcp/scorecard
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-07-30"
---

# Casos de uso — /team-mcp/scorecard

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Forja PR-3. A rota existia mas o componente nunca existiu (route quebrada) — este trio nasce com a Page. Padrão Facts+Checks (ADR 0091): Facts = números sem juízo, Checks = semáforo ok/fail. Persona: Wagner [W] (superadmin). Tela read-only.

## UC-SC-01 — A rota deixa de quebrar (a Page existe)
Status: 🧪 (2 testes de `ScorecardContratoTest` citam este UC — rota registrada apontando pro `ScorecardController`, e o `Inertia::render('team-mcp/Scorecard/Index')` cruzado com a existência do `.tsx` em disco. Segue 🧪 e não ✅ porque o ✅ vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit do CI — não se escreve à mão.)
Antes deste PR, `ScorecardController@index` renderizava `team-mcp/Scorecard/Index` sem componente → Inertia 500.
**Pronto quando:** abrir `/team-mcp/scorecard` renderiza a tela (sem tela branca / 500).

## UC-SC-02 — Semáforo geral reflete os checks
Status: ⬜ (manual/visual)
Banner no topo: verde "Tudo verde — N/N checks OK" quando todos passam; amarelo "N de M falhando" caso contrário.
**Pronto quando:** com todos os checks `ok=true`, o banner é verde com a contagem certa; com ≥1 `ok=false`, fica amarelo.

## UC-SC-03 — Facts são números reais do builder
Status: 🧪 (2 testes de `ScorecardContratoTest` citam este UC — as 7 chaves canônicas com tipos + o caminho de degradação sem schema MCP. Rodam **em sqlite**: o builder guarda todo acesso com `Schema::hasTable`, então a forma é provável sem MySQL. ⚠️ O `Wave23ScorecardRotateTest` já assertava esta estrutura desde a Wave 23, mas atrás de `requiresMcpSchema()` — que **pula** em sqlite — e sem citar UC nenhum; por isso este UC constava órfão apesar do comportamento estar coberto.)
KpiCards: tokens ativos · calls 7d · custo 7d (BRL) · devs ativos 7d, + Top tools (7d). Sem juízo, só contagem.
**Pronto quando:** os valores batem 1:1 com o retorno de `buildFacts()` (`tokens_ativos`, `calls_7d`, `cost_7d_brl`, `users_ativos_7d`, `top_tools_7d`).

## UC-SC-04 — Checks listam ok/fail com detalhe
Status: 🧪 (2 testes de `ScorecardContratoTest` citam este UC — forma `{name, ok:bool, detail}` de cada check + **controle negativo**: `checkSchema` de tabela inexistente tem que reprovar com "AUSENTE". Sem o controle negativo, um builder que devolvesse `ok=true` sempre passaria e o semáforo viraria decoração.)
Lista cada dimensão (schema mcp_tokens/audit_log, brief recente, tokens sem orphan, custo médio sanity) com ícone ok/fail + nome + detail + pill.
**Pronto quando:** cada item de `buildChecks()` aparece com `CheckCircle2` (ok) ou `AlertCircle` (fail) e o `detail` do backend.

## UC-SC-05 — Sem sparkline (sem dado fantasma · §3)
Status: 🧪 (1 teste de `ScorecardContratoTest` cita este UC — varre os Facts procurando marca de tempo nos itens de lista. Ancorado no **comportamento** ("não existe série no payload"), não numa chave literal: renomear o campo não pode fazer o vazamento passar.)
O builder só expõe pontos atuais (sem série). A tela NÃO renderiza sparkline fabricado — só Facts+Checks reais.
**Pronto quando:** não há nenhum gráfico de série na tela; nenhum dado derivado é apresentado como medido.

## UC-SC-06 — DS v6 (sem cor crua)
Status: 🧪 (cobertura: eslint `ds/*` = 0 + conformance ratchet)
Tokens semânticos (success/warning/destructive), `tabular-nums`, layout via `inline-flex`/`KpiGrid` — zero paleta crua, zero `rounded-xl+`.
**Pronto quando:** `eslint resources/js/Pages/team-mcp/Scorecard/Index.tsx` = 0 `ds/*` e `conformance-gate` verde.

## UC-SC-07 — Read-only (a tela não muta nada)
Status: 🧪 (1 teste de `ScorecardContratoTest` cita este UC — toda rota `team-mcp.scorecard.*` é GET-only, lido do registro de rotas. Roda em qualquer driver.)
Nenhuma ação edita estado; o único efeito é recarregar os dados deferidos (botão Atualizar / atalho R).
**Pronto quando:** não há nenhuma ação na tela que escreva no banco.

## UC-SC-08 — Acesso (auth + permissão)
Status: 🧪 (2 testes de `ScorecardContratoTest` citam este UC, em duas forças. **(a)** a stack de middleware do registro de rotas exige `auth` + a permissão — roda em qualquer driver, prova que a trava está **declarada**. **(b)** o 403 REAL com usuário autenticado sem a permissão — só produz veredito na lane MySQL `teammcp-pest.yml`; em sqlite pula, e skip **não é** cobertura. O usuário do caso (b) é filtrado por **não-admin**: o `Gate::before` do `AuthServiceProvider` libera qualquer ability pra quem tem `Admin#{business_id}`, e o 403 seria falso-verde.)
`/team-mcp/scorecard` exige login + `jana.mcp.usage.all` (mesma do TeamController). Repo-wide cross-business intencional (ADR 0093) pro superadmin ver saúde global.
**Pronto quando:** usuário sem `jana.mcp.usage.all` recebe 403.

> ⚠️ **Este arquivo de teste ainda não tem lane** (2026-07-28). `ScorecardContratoTest.php` **não está** em `.github/ci-sqlite-pest.list` nem no `teammcp-pest.yml` — as duas são allowlists explícitas, e o chip que escreveu estes testes é proibido de editar arquivo global. Enquanto a entrada não for consolidada pelo parent, estes UC são **"verde impossível"**: o teste existe e não roda. Linhas reportadas na devolutiva do chip.
