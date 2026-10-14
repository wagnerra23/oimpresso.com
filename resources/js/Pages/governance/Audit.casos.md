---
id: resources-js-pages-governance-audit-casos
casos: Audit · Drill-down forense do mcp_audit_log · /governance/audit
irmaos: Audit.charter.md (lei) · governance-audit-gap.md (GAP-SPEC canon) · RUNBOOK-audit.md
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente das lanes governance-filtros-gate (vitest) e ci-sqlite-pest (Pest)"
---

# Casos de uso — /governance/audit

> **Status:** ✅ passa (provado por teste no manifesto) · 🧪 em teste (teste escrito que morde, aguarda o veredito chegar ao manifesto) · ⬜ não verificado · ❌ quebrou.

> **De onde os casos vêm.** A lei é o [`Audit.charter.md`](Audit.charter.md); o escopo é a **US-GOV-003** (`memory/requisitos/Governance/SPEC.md`); o passo-a-passo e as pegadinhas são do [`RUNBOOK-audit.md`](../../../../memory/requisitos/Governance/RUNBOOK-audit.md); a paridade com o protótipo e as decisões de 2026-09-09 estão no GAP-SPEC [`governance-audit-gap.md`](../../../../memory/requisitos/Governance/governance-audit-gap.md); as rotas e o throttle estão no `SCOPE.md`. Cada UC tem contrato em ≥2 dessas fontes. O `.tsx` e o `AuditController` entraram **só para confirmar comportamento**, nunca para derivar caso (lápide §5 2026-06-05).

> **Nenhum UC nasce ✅.** O G-7 deriva o `✅` do manifesto `scripts/casos-test-results.json`. A lane vitest desta tela (`governance-filtros-gate.yml`) não emite JUnit, e o Pest novo (`tests/Feature/Governance/AuditContratoTest.php`) ainda precisa entrar na lane `ci-sqlite-pest`. O teto honesto é 🧪.

> **Força do veredito.** Nenhuma das duas lanes acima está em `governance/required-checks-baseline.json` (o dono de "é required"): reprovação é visível e **não bloqueia merge**.

## UC-GAUD-01 — Os 4 filtros combinam e se aplicam por partial reload
Status: 🧪 (vitest `tests/js/governance-filtros.test.tsx` — "UC-GAUD-01 · trocar um filtro…" + "UC-GAUD-01 · escolher Todos…")
O operador recorta o `mcp_audit_log` por período, actor (slug de `mcp_actors`), endpoint e status
(charter §Goals; SPEC US-GOV-003). O filtro novo soma aos já aplicados, a URL guarda o estado, e só
`entries`, `kpis` e `filters` são re-buscados — as listas de opção não mudam com filtro (RUNBOOK §3.7,
D-14). Escolher "Todos" tira o filtro da consulta, em vez de mandar valor vazio.
**Pronto quando:** trocar um filtro chama `GET /governance/audit` com o filtro novo **e** os anteriores,
com `only: ['entries','kpis','filters']`, `preserveState`, `preserveScroll` e `replace`.

## UC-GAUD-02 — A janela de período nunca passa de 30 dias
Status: 🧪 (vitest — "UC-GAUD-02 · a janela de período oferece só 1h, 24h, 7d e 30d")
Charter §Goals fixa o período em 1h/24h/7d/30d, e §UX Anti-patterns proíbe "Filtro de período > 30d
(custo MySQL alto sem índice composto)". O GAP-SPEC confirma o teto de 30d nos dois lados.
**Pronto quando:** o seletor de período oferece exatamente `1h`, `24h`, `7d` e `30d`.

## UC-GAUD-03 — Os 3 KPIs são contados sobre a amostra
Status: 🧪 (Pest `tests/Feature/Governance/AuditContratoTest.php` — "UC-GAUD-03 · …")
Charter §Goals: KpiGrid de 3 — total de entries, errors e users distintos. O GAP-SPEC registra que os
KPIs são calculados sobre a amostra (teto 200), igual ao protótipo — não sobre o período inteiro.
**Pronto quando:** para uma amostra de 3 linhas com 2 usuários e 1 erro, os KPIs são `total=3`,
`errors=1`, `unique_users=2`; amostra vazia dá zero nos três.

## UC-GAUD-04 — "Limpar filtros" aparece só quando há filtro, e volta ao padrão de 24h
Status: 🧪 (vitest — os 5 casos "UC-GAUD-04 · …", com controle positivo de render)
Decisão registrada no GAP-SPEC em 2026-09-09 (construído no #7089), com a forma do protótipo
`governance-telas.jsx:102`. Período diferente de 24h também conta como filtro, porque 24h é o padrão do
controller; limpar volta a ele, não a "sem período".
**Pronto quando:** no padrão não há botão; com qualquer filtro há; clicar pede `GET /governance/audit`
sem parâmetro nenhum, pelo mesmo partial reload do UC-GAUD-01.

## UC-GAUD-05 — O vazio explica por que está vazio
Status: 🧪 (vitest — "UC-GAUD-05 · sem entries E com filtro…" + "UC-GAUD-05 · sem entries e SEM filtro…")
Charter §Goals: `EmptyState` quando os filtros não retornam nada. O GAP-SPEC (2026-09-09) registra o
vazio com filtro mostrando o resumo da combinação e a saída "Limpar filtros", como no protótipo
`governance-telas.jsx:113-116`.
**Pronto quando:** sem linhas e com filtro, o vazio cita a combinação (período, status…) e oferece
limpar; sem linhas e sem filtro, mostra "Sem entries" e não oferece limpar.

## UC-GAUD-06 — O teto de 200 linhas está declarado na tela
Status: 🧪 (vitest — "UC-GAUD-06 · o teto de 200 por consulta está declarado na tela")
Charter §UX Targets: "Limit hint visível no footer"; §UX Anti-patterns: "200 hard cap obrigatório".
RUNBOOK §0 confere pelo rodapé literal. Este UC prova o **aviso**; o corte de 200 no SQL depende de
banco e ficou no [BACKLOG] abaixo.
**Pronto quando:** o rodapé "Limit 200 entries por query" está visível.

## UC-GAUD-07 — Ler o audit exige login e a permission `governance.audit.view`
Status: 🧪 (Pest — "UC-GAUD-07 · a rota exige login, a permission… e o throttle de 30/min")
Charter §Goals: acesso gateado por `can:governance.audit.view` na rota (ADR 0392 §D-C). SCOPE.md
§routes declara a rota; RUNBOOK §2 lista o `throttle:30,1` do grupo.
⚠️ **Ressalva D-GATE:** o `Gate::before` do `AuthServiceProvider` devolve `true` para a role
`Admin#{business_id}`, então o `can:` barra o não-admin sem a permission e **não** barra o admin de um
business. Fechar isso é o passo 1 da ADR 0392, decisão [W] em aberto. O teste prova que o gate está
**armado**, não que o acesso está fechado.
**Pronto quando:** o registry de rotas mostra `auth`, `can:governance.audit.view` e `throttle:30,1` em
`governance.audit.index`.

## UC-GAUD-08 — A tela é só leitura: nada edita ou apaga uma entry
Status: 🧪 (Pest — "UC-GAUD-08 · nenhuma rota de /governance/audit escreve" + "UC-GAUD-08 · o controller… não expõe ação além da leitura")
Charter §Non-Goals: "❌ Edit/delete/UPDATE em audit entry (append-only IRREVOGÁVEL — trigger MySQL
bloqueia, ADR 0084)"; §UX Anti-patterns: "❌ Edit inline em entry". RUNBOOK §1: UPDATE/DELETE numa
entry é incidente P0.
**Pronto quando:** toda rota sob `governance/audit` aceita só GET/HEAD, e o `AuditController` não tem
ação pública além de `index`.

## [BACKLOG] — sem teste que os cite, por isso sem id

- [BACKLOG] O corte de 200 linhas no SQL (`getRecentEntries(200, …)`), o recorte por período e a ordem
  `ts` decrescente — precisam de banco com `mcp_audit_log` populado no tenant 98; não cabem na lane sqlite.
- [BACKLOG] Actor inexistente devolve zero linhas, nunca "todos" (RUNBOOK §10 pegadinha 5). Só o RUNBOOK
  sustenta; precisa de teste com banco.
- [BACKLOG] A leitura é de plataforma, cross-tenant por desenho (charter errata 2026-09-08, ADR 0392
  §D-C, SPEC "Por que Governance é cross-tenant") — prova exige banco com 2 tenants.
- [BACKLOG] Contagem do período além do teto de 200 — item "Decidir" do GAP-SPEC, ainda aberto (exige
  `count()` extra no controller).
- [BACKLOG] Export LGPD Art. 18 — Non-Goal do charter "fica pra próxima iteração"; SPEC US-GOV-003
  marca como pendente.
- [BACKLOG] Teste do trigger append-only: o charter §Tests cita `tests/Feature/Governance/AuditAppendOnlyTest.php`,
  que **não existe** no repo (medido 2026-10-14).
