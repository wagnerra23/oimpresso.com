---
id: resources-js-pages-governance-drift-alerts-casos
casos: Drift Alerts · SCOPE.md × filesystem · /governance/drift
irmaos: DriftAlerts.charter.md (lei) · RUNBOOK-drift-alerts.md · governance-drift-alerts-gap.md (GAP-SPEC canon)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Unit) (sqlite) e de uma lane vitest ainda não wirada"
---

# Casos de uso — /governance/drift

> **Status:** ✅ passa (provado por teste no manifesto) · 🧪 em teste (teste escrito que morde, aguarda o veredito chegar ao manifesto) · ⬜ não verificado · ❌ quebrou.

> **De onde os casos vêm.** A lei é o [`DriftAlerts.charter.md`](DriftAlerts.charter.md) (Mission · Goals · Non-Goals · UX Anti-patterns); o escopo é a **US-GOV-004** (`memory/requisitos/Governance/SPEC.md`); o passo-a-passo e os estados são do [`RUNBOOK-drift-alerts.md`](../../../../memory/requisitos/Governance/RUNBOOK-drift-alerts.md) e do GAP-SPEC [`governance-drift-alerts-gap.md`](../../../../memory/requisitos/Governance/governance-drift-alerts-gap.md). O `.tsx` e o `DriftAlertService` entraram **só para confirmar comportamento**, nunca para derivar caso (lápide §5 2026-06-05).

> **Persona:** Wagner [W] — quem lê a tela e resolve o drift no IDE. A tela é só leitura (charter §UX Anti-patterns).

> **Multi-tenant.** O scan é de **filesystem** (`Modules/` + `memory/requisitos/*/SCOPE.md`), cross-tenant por construção: não há `business_id` envolvido e nenhum caso toca banco. Os testes montam árvore fixture num diretório temporário e apontam o `base_path` para ela — nunca o repo real, cujo drift muda a cada PR. Nenhum tenant aparece, biz=4 menos ainda.

> **A promessa do charter que não existia.** O §"Tests anti-regressão" do charter prometia `tests/Feature/Governance/DriftDetectionTest.php — módulo fixture com controller undeclared`. Medido em 2026-10-14: o arquivo **não existe** (`ls tests/Feature/Governance/` = só `ProdFlagsCommandTest.php`). O contrato que ele prometia é o UC-GDRF-01 abaixo, provado por `tests/Feature/Governance/DriftAlertsContratoTest.php`. O ponteiro do charter não foi tocado aqui (fora do escopo deste PR); corrigi-lo é reconciliação factual da Fase 2.6.

> **Força do veredito.** Pest: lane `PHP / Pest (Unit)` (sqlite) — o arquivo precisa entrar em `.github/ci-sqlite-pest.list`. Vitest: nenhuma lane roda `tests/js/governance-drift-alerts.test.tsx` ainda. Se a lane bloqueia merge é do [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json), não desta prosa. Até o veredito chegar ao manifesto, o teto honesto é 🧪.

## UC-GDRF-01 — Controller que existe e não está declarado vira drift do módulo
Status: 🧪 (Pest `DriftAlertsContratoTest` — "UC-GDRF-01 — controller que existe e não está no contains[] vira drift do módulo" + controle negativo "tudo declarado não gera drift")
Mission do charter: drift = controller em `Modules/<X>/Http/Controllers/` que não está no `contains[]` do `SCOPE.md` do módulo — sintoma de mudança não registrada (REGRA PRIMÁRIA "mexeu, registra"). Cada módulo em drift lista os controllers não declarados e o total de controllers reais.
**Pronto quando:** num módulo com `Foo` e `Bar` e `contains[]` só com `Foo`, a linha do módulo traz `undeclared = [Bar]`, `undeclared_count = 1`, `total_actual = 2`; com tudo declarado o módulo não aparece e `total_drift = 0`.

## UC-GDRF-02 — Boilerplate nunca é drift
Status: 🧪 (Pest — "UC-GDRF-02 — boilerplate (Data/Install/Superadmin/Controller) nunca é drift")
Charter §Goals: "Filtra boilerplate (DataController/InstallController/SuperadminController/Controller) antes de comparar". RUNBOOK §10.5: tirar um nome do filtro faz ~todos os módulos aparecerem em drift de uma vez.
**Pronto quando:** os 4 boilerplates presentes e não declarados não geram linha de drift.

## UC-GDRF-03 — Declarar em `drift_alerts[]` conta como declarado (transitório)
Status: 🧪 (Pest — "UC-GDRF-03 — controller declarado em drift_alerts[] conta como declarado (transitório)")
Charter §Goals: "Aceita declared em `contains[]` OU em `drift_alerts[]` (transitório) — flexibilidade pra migração".
**Pronto quando:** controller ausente do `contains[]` mas presente em `drift_alerts[]` (`controller: "BarController"`) não aparece como drift.

## UC-GDRF-04 — Módulo sem SCOPE.md fica em lista própria, não é drift
Status: 🧪 (Pest — "UC-GDRF-04 — módulo sem SCOPE.md vai pra lista própria e não conta como drift")
Charter §Goals: card "Módulos sem SCOPE.md" separado; RUNBOOK §3.4: "entram em `modules_without_scope` (lista separada, não é 'drift')".
**Pronto quando:** o módulo sem SCOPE vai para `modules_without_scope`, não para o report, e ainda conta em `modules_total`.

## UC-GDRF-05 — SCOPE.md com YAML inválido não derruba a tela
Status: 🧪 (Pest — "UC-GDRF-05 — SCOPE.md com YAML inválido não derruba o scan e vai pro log estruturado")
Charter §UX Targets: "Mensagem clara quando YAML parse falha (log estruturado via `Log::error`, UI não quebra)". O charter decide o log, não decide a UI — ver Perguntas.
**Pronto quando:** com um SCOPE.md de frontmatter inválido, o scan termina, responde pelos outros módulos e registra `Log::error` com "YAML parse falhou".

## UC-GDRF-06 — Os 4 KPIs derivam do mesmo scan que a lista
Status: 🧪 (Pest — "UC-GDRF-06 — os 4 KPIs derivam do MESMO scan que a lista")
Charter §Goals: KpiGrid com controllers em drift, módulos com drift, sem SCOPE.md, total módulos. RUNBOOK §3.5: "KPIs derivam do mesmo payload".
**Pronto quando:** numa fixture com 2 módulos em drift (2 + 1 controllers), 1 limpo e 1 sem SCOPE, os KPIs são `3 · 2 · 1 · 4`.

## UC-GDRF-07 — A tela é só leitura
Status: 🧪 (Pest — "UC-GDRF-07 — a tela é só leitura: uma rota GET, throttle 20/min e gate da sidebar")
Non-Goals do charter: sem auto-fix, sem suprimir; UX Anti-patterns: sem botão "ignorar", sem edit inline de SCOPE.md. RUNBOOK §2: `throttle:20,1` porque o scan de filesystem é caro. Lido do registry de rotas, não do texto do `routes.php`.
**Pronto quando:** `governance.drift.index` é só `GET|HEAD`, tem `throttle:20,1` e `can:governance.dashboard.view`, e não existe nenhuma outra rota sob `/governance/drift`.
⚠️ **Ressalva D-GATE** (a mesma do UC-GPOL-06): o `Gate::before` devolve `true` para `Admin#{business_id}`, então `can:` barra o não-admin e não barra o admin de um business. Fechar é o passo 1 da ADR 0392, decisão [W].

## UC-GDRF-08 — Enquanto o cron não persiste, o histórico fica vazio e diz por quê
Status: 🧪 (Pest — "UC-GDRF-08 — enquanto o cron não persiste, o histórico chega vazio" · vitest `governance-drift-alerts.test.tsx` — "UC-GDRF-08 — histórico vazio mostra o empty state explicativo")
Charter §Goals: card "Histórico (mcp_alertas — últimos 30d)" com empty state explicativo enquanto o cron Enforcement #5 está pendente; Non-Goal: esta tela não persiste em `mcp_alertas`. RUNBOOK §10.4: vazio por construção, não bug a caçar.
**Pronto quando:** `persistedAlerts()` devolve `[]` e a tela mostra "Sem alertas persistidos". Destrava (e este caso muda) no dia em que `module_drift` entrar no enum de `mcp_alertas`.

## UC-GDRF-09 — Os KPIs de drift mudam de tom conforme o dado
Status: 🧪 (vitest — "UC-GDRF-09 — KPIs de drift ficam warning com drift e success sem drift")
Charter §Goals: "Tone semântico dinâmico: `warning` se drift > 0, `success` se zero"; UX Anti-patterns: tom semântico, nunca cor crua. Vale para "Controllers em drift" e "Módulos com drift". O KPI "Sem SCOPE.md" fica fora deste caso — divergência aberta (ver Perguntas).
**Pronto quando:** com drift os dois KPIs vêm `tone="warning"`; sem drift, `tone="success"`.

## UC-GDRF-10 — Zero drift diz "Sem drift"; com drift, cada módulo mostra o que falta e as 3 saídas
Status: 🧪 (vitest — "UC-GDRF-10 — zero drift mostra \"Sem drift\"; com drift lista os não declarados e as 3 saídas")
Charter §Goals: card "Drift detectado em runtime" com módulo + controllers não declarados + total, e hint "Adicione em SCOPE.md.contains[] OU mova OU declare em drift_alerts[]". RUNBOOK §5: estado "Sem drift".
**Pronto quando:** sem drift aparece "Sem drift"; com drift aparece `Modules/<X>`, cada controller não declarado, "N de M controllers" e as 3 saídas do hint.

## UC-GDRF-11 — Nenhuma ação de ignorar, suprimir, auto-corrigir ou editar
Status: 🧪 (vitest — "UC-GDRF-11 — nenhuma ação de ignorar, suprimir, auto-corrigir ou editar SCOPE na tela")
Non-Goals: "❌ Auto-fix", "❌ Suprimir alertas (sem snooze)"; UX Anti-patterns: "❌ Botão 'ignorar drift'", "❌ Edit inline de SCOPE.md". O humano resolve no IDE e commita.
**Pronto quando:** com drift na tela, nenhum botão ou link tem texto de ignorar/suprimir/silenciar/corrigir/editar, e não há formulário nem campo editável.

## UC-GDRF-12 — O card "Módulos sem SCOPE.md" só aparece com lista não vazia
Status: 🧪 (vitest — "UC-GDRF-12 — card \"Módulos sem SCOPE.md\" só aparece quando a lista não está vazia")
Charter §Goals: card "Módulos sem SCOPE.md" com os módulos; RUNBOOK §5: "card inteiro só aparece se a lista não estiver vazia".
**Pronto quando:** com `['Orfao']` o card aparece com o módulo; com `[]` o card não existe.

## Backlog (sem id — 1 fonte só, ou sem teste ainda)

- [BACKLOG] Paridade com `bin/check-scope.php` — o charter a chama de "obrigatória" e o RUNBOOK §10.3 manda consertar os dois juntos; nenhum teste compara os dois vereditos sobre a mesma fixture.
- [BACKLOG] p95 de first-paint < 1,2s (charter §UX Targets) — sem medição; o scan roda 2× por render (RUNBOOK §10.6).
- [BACKLOG] Loading/skeleton — scan síncrono, sem skeleton (RUNBOOK §5, ⬜ aberto; charter registra como dívida).
- [BACKLOG] Lista de alertas persistidos com severidade por token — o front já desenha (`severityVariant`), mas só existe quando o cron persistir; sem dado para testar.

## Perguntas sem fonte (decisão [W])

1. **"Escopo ilegível" na tela.** O GAP-SPEC deixa em aberto expor na UI o módulo cujo SCOPE.md não parseou (hoje só loga). Construir ou rejeitar por escrito?
2. **Tom do KPI "Sem SCOPE.md".** O protótipo usa `tone="info"` ("não é drift — é ausência de contrato"); o vivo usa warning/success. O GAP-SPEC marca "Alinhar"; o charter diz só "tone dinâmico" sem nomear qual KPI. Qual vale?
3. **Escopo da US-GOV-004.** O SPEC fala em "SCOPE.md violado, ADR ausente, charter stale"; a tela só detecta controller fora do SCOPE. "ADR ausente" e "charter stale" são desta tela ou de outro dono?
4. **Permission.** O SPEC §Permissões atribui `/governance/drift` a `governance.drift.view`; a rota usa `can:governance.dashboard.view`. Pela precedência a rota ganha; alinhar o SPEC é decisão [W], porque trocar a permission da rota revogaria acesso em silêncio.
