---
distilled_at: "2026-07-17"
distilled_by: "manual [CC] — redistilação por releitura do módulo (rotas + comandos + config + baseline + gates rodados). Substitui o refresh de 2026-07-09, que ainda carregava a medição de 2026-05-16 como se fosse estado"
module: Governance
status: producao
updated_at: "2026-07-17"
---

# BRIEFING — Governance (verdade destilada)

## Estado atual

Enforcer + dashboard humano da **Constituição v2** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)): UI Inertia, middleware ActionGate e leitura consolidada das tabelas `mcp_*`. Cross-tenant **intencional** (exceção formal ao Tier 0, justificada pela Constituição Art. 6+8 — governança é L1 transversal, não dado de negócio). Em produção.

**Module grade: 88/100 (Excelente · rubrica v3).** Dono do número: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) (v3.6.0, lock 2026-07-16, medição do **CI** do PR [#4378](https://github.com/wagnerra23/oimpresso.com/pull/4378)) — recomputar com `php artisan module:grade Governance`. **Empatado no topo dos 36 módulos** (88, junto com ADS). Estável em 88 desde **2026-05-28**. O gate `module-grades` é **advisory** desde 2026-06-30 ([ADR 0314](../../decisions/0314-poda-gates-onda-2-lei-fusoes.md) D-1) — a nota não bloqueia merge.

> ⛔ **Errata do destilado anterior — não re-alegar.** Ele dizia *"na medição de 2026-05-16 a grade era 49/100 com meta 84 (Wave G)"*, e nunca registrou que **a meta foi batida e superada há ~2 meses**. O 49 é história de **rubrica anterior**: `git log -S '"Governance": 49'` no baseline volta **vazio** — ele nunca esteve na régua v3 ([ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md)). Fica aqui como história datada, jamais como estado.

**O centro de gravidade saiu do PHP.** Recibo: `git log --since=2026-07-09 -- Modules/Governance memory/requisitos/Governance scripts/governance`, rodado em 2026-07-17 → **73 commits** nos 8 dias desde o carimbo anterior, sendo **68 em `scripts/governance/`**, 4 em docs, e **1 único em `Modules/Governance/`** ([#4053](https://github.com/wagnerra23/oimpresso.com/pull/4053)). O módulo Laravel está praticamente congelado; a governança executável virou **Node + gates de CI**. Um 1-pager que descreva só o módulo PHP descreve a menor parte do que o Governance é hoje — por isso as capacidades abaixo cobrem os dois lados.

## Capacidades

Varridas em 2026-07-17 (contagens completas; testes = arquivos, **não** testes verdes — rodar Pest é CT 100, [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)):

- **Telas** — ⚠️ `/governance` **redireciona 302 pra `/ia`** desde 2026-05-22 (o entry-point canon é o hub IA/Jana, sidebar v3 [ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)); o dashboard original sobrevive em `/governance/dashboard`, com a rota nomeada `admin.dashboard.legacy`. Vivas: `policies` (listagem + toggle), `audit`, `drift`, `module-grades`, **`ds-rollout`** (ausente do destilado anterior).
- **16 comandos artisan** — família `module:grade` (v1, [ADR 0153](../../decisions/0153-module-grade-rubrica-v1.md)) → **`module:grade-v4`** (scoped scorecards por bucket) → `module:grade-snapshot` (cron 06:00); `governance:audit` (itera o registry de DriftCheckers); `governanca:ciclo-diario`; `charter:audit|health|metrics`; `governance:sdd-scorecard-snapshot` (GT-G7, [ADR 0275](../../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md)); `observability:aggregate-daily` (cron 02:00); `governanca:scorecard`; `governance:health`. O destilado anterior citava só `php artisan module:grade`.
- **Drift Framework com 11 DriftCheckers** registrados (`Config/config.php:73-85`, master switch `drift_framework_enabled=true`): Composer/Npm audit, **MultiTenantScope** (Tier 0), AdrLinks, ChartersFreshness, DesignDocsFreshness, RoutesZombie, MeilisearchSettings, DeployDrift, McpServedDrift, McpIndexFreshness. O destilado anterior resumia isso em 1 bullet ("detecção automática de modificações não registradas").
- **6 injetores do Daily Brief** (novo — cada um com kill-switch em `config.php:166-226`): linha SDD, saúde dos planos, shipped-log, revisão de ADR, ADR pendente, e a seção **outcome do agente** (DORA: aceitação / change-failure / time-to-merge + custo USD por PR).
- **Máquina de gates em `scripts/governance/`** — é onde a governança realmente roda hoje: `sdd-scorecard.mjs`, `anchor-lint.mjs`, `gate-selftest.mjs`, `knowledge-drift.mjs`, `protection-drift.mjs`, detector de papel do DS, visual-regression. Dono de "o que é required": [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) — **este BRIEFING não restateia enforcement** (lápide [proibicoes.md](../../proibicoes.md) §5 2026-07-16).
- **49 arquivos de teste** (`*Test.php`), com **2 dedicados a cross-tenant**: `CrossTenantPolicyTest` (10 cenários biz=1 vs biz=99, [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — valida que as `mcp_*` **não têm** `business_id` por design) + `MultiTenantGovernanceTest`.

## Gaps

| Gap | Evidência (verificada 2026-07-17) |
|---|---|
| **ActionGate é guard fantasma** — o alias `actiongate` é registrado (`GovernanceServiceProvider.php:114`) e **0 rotas do repo o usam** (grep em `**/routes*.php` = 0 matches). Modo default `warn` (`config.php:12`), e o próprio docblock admite *"Uso (futuro): adicionar no kernel ou em rotas L2+"* (`ActionGate.php:24`). O destilado anterior listava isto como **capacidade** ("controle de ações críticas em diferentes modos") — é a forma exata do *"chokepoint que o fluxo real não atravessa"* ([proibicoes.md](../../proibicoes.md) §5, 2026-07-09): zero cobertura com cara de defesa. | `ActionGate.php:24` · `GovernanceServiceProvider.php:114` |
| **`compliance_pct` do Dashboard é hardcoded 80** — `$compliancePct = (7 * 10) + (2 * 5) + 0; // = 80`, somado à mão num comentário (que inclusive já anota *"Pendente: 8 (ActionGate Fase 5 ainda em warn) = 0"*). Não vem de gate rodando. | `DashboardController.php:45` |
| **Cobertura de âncora do PRÓPRIO SPEC = 13%** — o módulo **dono** do `anchor-lint` é o menos ancorado: 46 US, sendo **40 `sem_campo`** e 4 ancoradas, contra **83,7%** de média do projeto. Dívida **grandfatherada**: rodei os 2 gates required contra o SPEC (`--check` e `--check-entry --check-covers --baseline`) e **os dois passam** (`dead:0`, `zombie:0`) — tocar o SPEC não acorda o gate. | `anchor-lint.mjs --json .modules[Governance]` |
| **Scorecard SDD: 9/13 métricas medidas** — 4 seguem `not_yet_measured` (`full_suite_pass_rate`, `coverage_pct`, `recall_eval_violations`, `ragas_real_uptime`), **todas** travadas na mesma coisa: o write-side do CT100 publicar o transporte ([ADR 0279](../../decisions/0279-sdd-medir-governar-floor-nightly.md) Opção A). | `sdd-scorecard.mjs --json` |
| **Drift SPEC↔mundo** — US-GOV-049/050/051 declaram `todo` com o **trabalho já feito**: ADR 0329 `aceito` (#4027), ADR 0299 `aceito` (#4039), ADR 0320 já movida pro top-level (#4034), PRs #4009/#4010 mergeados. O SPEC está atrás do mundo. | `memory/decisions/0329-*.md` · `0299-*.md` · `0320-*.md` |
| **Policies: CRUD + audit trail seguem planejados** (alegação antiga **mantida — está correta**). `mcp_governance_rule_history` tem 5 ocorrências no repo, **todas prosa**: zero migration, zero query. O `PoliciesController` tem só `index` + `toggle`. | `PolicyToggleService.php:17` · `PoliciesController.php:19` |

**Percentuais removidos de propósito** (não recarimbados): o destilado anterior trazia *"cobertura SPEC 20%"*, *"documentação 40%"* e *"Pest cross-tenant 40%"*, todos da medição de 2026-05-16. Nenhum tem fonte rastreável hoje, e re-afirmá-los seria fabricar número. O que é mensurável está acima (âncora 13% com dono; 49 arquivos de teste; 2 suítes cross-tenant dedicadas).

## Última mudança

Nos **73 commits** da janela 2026-07-09→07-17 (recibo acima), a maior massa — e a única que entrega capacidade nova de verdade — é a **leva DS/visual**:

- **Tripé componente-por-papel** ([ADR 0338](../../decisions/0338-ds-lint-eixo-valor-token-fecha-por-forma.md)): 4 papéis canônicos (tab-nav, status-badge, combobox, sub-nav contextual) + regras `ds/no-*` + assinatura `ROLE_SIGNATURES` + detector que reconhece consumo **transitivo** do canon — PRs [#4286](https://github.com/wagnerra23/oimpresso.com/pull/4286) [#4293](https://github.com/wagnerra23/oimpresso.com/pull/4293) [#4294](https://github.com/wagnerra23/oimpresso.com/pull/4294) [#4295](https://github.com/wagnerra23/oimpresso.com/pull/4295) [#4298](https://github.com/wagnerra23/oimpresso.com/pull/4298) [#4306](https://github.com/wagnerra23/oimpresso.com/pull/4306).
- **visual-regression fail-closed**: classificação de impacto visual, identidade runtime das telas, ledger completo de execução, rastreio dos consumidores reais — [#4335](https://github.com/wagnerra23/oimpresso.com/pull/4335) [#4339](https://github.com/wagnerra23/oimpresso.com/pull/4339) [#4341](https://github.com/wagnerra23/oimpresso.com/pull/4341) [#4342](https://github.com/wagnerra23/oimpresso.com/pull/4342) [#4349](https://github.com/wagnerra23/oimpresso.com/pull/4349) [#4391](https://github.com/wagnerra23/oimpresso.com/pull/4391).
- **Selftest permanente**: as 13 regras `ds/*` e as 3 catracas DS required ganharam fixture good/bad no `gate-selftest` — [#4317](https://github.com/wagnerra23/oimpresso.com/pull/4317) [#4318](https://github.com/wagnerra23/oimpresso.com/pull/4318).
- **Os 3 gates DS viraram required em 2026-07-15** (flip 24→27) **sem** o bite-log da DR-2 exigido pela [ADR 0336](../../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) — mantido por exceção soberana [W] e registrado honestamente como desvio consciente na [ADR 0339](../../decisions/0339-promocao-soberana-3-gates-ratchet-ds-required-emenda-0336.md). Follow-up = **US-GOV-054** (`todo`; `memory/governance/design-gate-bites.jsonl` ainda **não existe**).

Também no período: scorecard SDD tirou `drift_alarms` + `read_path_hops` de `not_yet_measured` ([#4196](https://github.com/wagnerra23/oimpresso.com/pull/4196)); `distiller_freshness` parou de **fabricar stale** em checkout shallow ([#4201](https://github.com/wagnerra23/oimpresso.com/pull/4201) — o 6 era artefato de medição, não dívida); manifesto por-UC levou o G-7 de 10%→17% ([#4377](https://github.com/wagnerra23/oimpresso.com/pull/4377)); e 4 hooks Tier-0 foram portados `.ps1`→`.mjs` antes do time MCP entrar em Mac/Linux ([#4004](https://github.com/wagnerra23/oimpresso.com/pull/4004) [#4025](https://github.com/wagnerra23/oimpresso.com/pull/4025) [#4028](https://github.com/wagnerra23/oimpresso.com/pull/4028) [#4035](https://github.com/wagnerra23/oimpresso.com/pull/4035)).

## Proveniência (destilado de)

Releitura direta em 2026-07-17 — não de sessions/handoffs (o destilado anterior citava 11 fontes, **nenhuma posterior a 2026-06-29**, e por isso não enxergava a janela que importava):

- código: `Modules/Governance/Http/routes.php` · `Http/Controllers/` · `Http/Middleware/ActionGate.php` · `Providers/GovernanceServiceProvider.php` · `Console/Commands/` (16) · `Config/config.php` · `Tests/` (49)
- contrato: [SPEC.md](SPEC.md) (817 linhas; 36 US com header — 4 done / 32 todo)
- números **rodados**, não lidos: `node scripts/governance/sdd-scorecard.mjs --json` (9/13 measured · `distiller_freshness` 0) · `node scripts/governance/anchor-lint.mjs --json` (Governance 13% · projeto 83,7%) · os 2 gates required contra o SPEC (passam)
- dono das notas: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) v3.6.0
- janela: `git log --since=2026-07-09 -- Modules/Governance memory/requisitos/Governance scripts/governance` → 73 commits em 2026-07-17
</content>
