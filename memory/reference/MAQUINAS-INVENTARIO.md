---
name: MAQUINAS-INVENTARIO — inventário derivado das máquinas do oimpresso
description: Censo GERADO por scripts/governance/maquinas-inventario.mjs (workflows, hooks, skills, agents, scripts, baselines). NÃO editar à mão (regenera). Cada descrição vem do cabeçalho/frontmatter/_meta da própria máquina.
type: reference
authority: generated
lifecycle: ativo
---

# Máquinas do oimpresso — inventário consolidado (DERIVADO)

> ⚙️ **Auto-gerado** por `scripts/governance/maquinas-inventario.mjs` — cada descrição vem do
> cabeçalho/frontmatter/`_meta` do PRÓPRIO arquivo (medido, não escrito à mão · ADR 0256).
> Regerar: `node scripts/governance/maquinas-inventario.mjs --write` · drift de COBERTURA
> acusado por `--check` (advisory em `governance-script-tests.yml`): morde quando uma máquina
> é adicionada/removida sem regenerar. Bite-test: `maquinas-inventario.test.mjs`.
>
> **Donos canônicos** (esta página só CONSOLIDA — a fonte viva de cada eixo é):
> - Hooks → `.claude/hooks/_HOOKS-INDEX.md` · Skills → `.claude/skills/_SKILLS-INDEX.md`
> - Gates/Workflows → `scripts/governance/gates-registry.json` · Required → `governance/required-checks-baseline.json`

## 1. Workflows / Gates de CI — 123 (45 contexts required)

> `Invocador` = gatilho `on:` do YAML · `Documento` = doc canônico de maior precedência que o cita.
> **Evidência não é derivável aqui** (medido: 0 de 123): o `gate-selftest` prova que o SCRIPT morde,
> nunca o workflow. Marcar o workflow como provado por causa do script dele seria medir outra coisa.

| Workflow | Invocador | Documento | Descrição |
|---|---|---|---|
| `a11y-axe-gate.yml` | pr+push | `memory/decisions/_PROPOSTA-protocolo-v2-colapso-W.md` +8 | A11y axe runtime (jsdom · componentes canon) |
| `a11y-gate.yml` | pr+push | `memory/requisitos/Jana/AUDITORIA-design-as-code-token-driven-2026-06-22.md` +1 | A11y ratchet (acessibilidade = categoria protegida) |
| `adr-index-gate.yml` | pr+manual | `memory/08-handoff.md` +3 | ADR Index Gate — ADR 0258 |
| `adr-lint.yml` | pr+push | `memory/decisions/0063-prevenir-composer-lock-drift.md` +16 | ADR frontmatter lint |
| `agent-cost-per-pr.yml` | cron+manual | `memory/proibicoes.md` +10 | Agent cost per PR (advisory · custo USD estimado por PR do agente · unidade = SESSÃO do JSONL local atribuída por branch==headRefName ou citação /pull/N · cobertura de AL… |
| `agent-pr-outcomes.yml` | cron+manual | `memory/08-handoff.md` +2 | Agent PR outcomes (advisory · DORA dos PRs do agente · change-failure-rate + accept-rate + time-to-merge via gh pr list · weekly schedule + dispatch · card #0 grade-das-r… |
| `anchor-content-required.yml` | pr+push | `memory/decisions/0327-anchor-content-required-emenda-0314.md` +1 | Ancora de design nao-shell — REQUIRED (F2/F6 revisão adversarial 2026-07-08: related_prototype do charter != shell/fantasma; anchor-content-check --check hard-fail; emend… |
| `anchor-drift.yml` | pr+cron+manual | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +21 | Anchor Drift — lint spec↔código ADR 0273 + entry/covers (0303) + doneness (0302) + charter status:live, diff-aware no PR e full-tree no cron semanal (SA-A2/A3). Enforceme… |
| `arquivos-pest.yml` | pr+push+manual | `memory/decisions/0354-teammcp-pest-required-emenda-0314.md` +5 | Arquivos · Pest (MySQL) — audit-log/download/enum rodam no MySQL real (skip no sqlite = verde mente); catraca allowlist verde |
| `baseline-tamper-guard.yml` | pr+manual | `memory/decisions/0331-anti-duplicacao-work-claim-gate.md` +39 | Baseline tamper-guard (anti-grandfather · afrouxar baseline + código no mesmo PR · ADR 0256/0258 · Gap-2 blueprint SDD) |
| `block-brl-values-selftest.yml` | pr+manual | — | block-brl-values selftest (meta-teste do hook Tier-0 dinheiro block-brl-values-in-memory.mjs — bite/release do detector via --selftest + registration test do settings.jso… |
| `briefing-code-staleness.yml` | pr+cron+manual | `memory/proibicoes.md` +20 | Staleness reporters (advisory · 6 eixos: BRIEFING×código briefing-code-staleness.mjs · visual-comparison×tela visual-comparison-staleness.mjs · ADR pendente adr-proposto-… |
| `briefing-coverage-required.yml` | pr+push | `memory/decisions/0348-briefing-coverage-required-emenda-0314.md` +1 | Cobertura BRIEFING (required) — modulo backend (Modules/<X>/ com dir requisitos/<X>/) sem BRIEFING.md falha o merge. Sinal = EXISTENCIA (isBriefingCoverageGap), nao data … |
| `brl-scan.yml` | pr+manual | `memory/08-handoff.md` +3 | BRL scan (advisory · valor monetário em linha NOVA do PR · diff-only · arquivos + PR body + commit subjects) |
| `casos-gate.yml` | pr+push | `memory/decisions/0365-trio-de-tela-fica-colocado-reverte-eixo-0364.md` +146 | Casos-coverage ratchet (trio-de-tela + caso↔teste) |
| `casos-results-publish.yml` | cron+manual | `memory/decisions/0354-teammcp-pest-required-emenda-0314.md` +10 | Casos results publish — colhe o JUnit das lanes (que já emitem --log-junit) e aterrissa o veredito por-UC em scripts/casos-test-results.json, fonte do G-7 do casos-gate; … |
| `catalog-graph.yml` | pr+manual | `memory/decisions/0370-module-surface-catalog-graph-required-emenda-0314.md` +24 | Catalog graph — prova que memory/governance/catalog.json é a derivada determinística dos SCOPE.md + SUPERFICIE.md Classe B, sem referências estruturais penduradas; exerci… |
| `charter-refs-gate.yml` | pr | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +6 | Charter refs (catraca charter_refs_broken ≤ teto · require-safe · US-GOV-043 · ADR 0256) |
| `charter-us-gate.yml` | pr+cron+manual | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +5 | Charter ↔ US join (advisory · related_us nos Page Charters · charter-us-lint.mjs --check diff-aware no PR + cobertura full-tree no cron) |
| `ci.yml` | pr+push+manual | `memory/decisions/0063-prevenir-composer-lock-drift.md` +39 | CI |
| `ciclo-completo.yml` | pr+manual | `memory/decisions/proposals/2026-07-11-maquina-nascimento-tela.md` | ciclo-completo (advisory) — catraca do CICLO-DE-TELA por tela: quantas telas roteadas têm o conjunto obrigatório (charter + Padrão de Tela declarado + pt-conforme via pt-… |
| `components-tree-guard.yml` | pr+push | `memory/requisitos/_DesignSystem/CHANGELOG.md` +6 | Components tree guard (árvore canônica de Components/) |
| `composer-lock-sync.yml` | manual | `memory/decisions/0063-prevenir-composer-lock-drift.md` +6 | Composer lock sync |
| `compras-pest.yml` | pr+push+manual | `memory/decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md` +12 | Compras · Pest (MySQL) |
| `contrato-de-tela.yml` | pr+push | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +9 | Contrato de Tela — fidelidade visual do trio-de-tela (advisory na adoção · RUNBOOK-contrato-de-tela.md) |
| `deadlink-gate.yml` | pr | `memory/decisions/0347-deadlink-gate-required-emenda-0314.md` +36 | deadlink-gate (ratchet · integridade referencial) — catraca de integridade referencial doc↔doc: links markdown internos mortos no corpo VIVO (memory/** menos história app… |
| `deploy.yml` | push+manual | `memory/decisions/0166-errata-0162-otel-require-dev-hostinger.md` +64 | Deploy to Hostinger |
| `design-coverage.yml` | pr+manual | `memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md` +8 | ds-design-coverage (advisory) — catraca da cobertura de DESIGN por tela: quantas telas DECLARAM a fonte de design (protótipo via related_prototype ou 'segue DS' explícito… |
| `design-identity-gate.yml` | pr | `memory/requisitos/Jana/AUDITORIA-design-as-code-token-driven-2026-06-22.md` +3 | Design Identity Gate (soft) |
| `design-memory-gate.yml` | pr+manual | `memory/decisions/0327-anchor-content-required-emenda-0314.md` +14 | Design-memory gate (advisory · FUNDIDO ADR 0314 F2 de 3 workflows: registry-check ex-component-registry O2 + gates ex-design-memory-gates §8/§15 + prove ex-dtcg-equivalen… |
| `design-return-gate.yml` | push | `memory/08-handoff.md` +3 | Design return gate (§10.2 pós-merge) |
| `design-spec-gate.yml` | pr+push | `memory/requisitos/Jana/AUDITORIA-design-as-code-token-driven-2026-06-22.md` +3 | Design-spec por-tela (contrato estrutural determinístico) |
| `detect-ui-drift.yml` | pr+manual | `memory/decisions/0348-briefing-coverage-required-emenda-0314.md` +4 | detect-ui-drift — M1 (advisory) — eixo de AUTORIZAÇÃO: quando uma .tsx de tela muda num PR, exige sinal FRESCO no mesmo PR (divergence_from_blueprint com razão real no ch… |
| `devcontainer-firewall.yml` | pr+cron+manual | (só sessão/handoff · 1) | devcontainer firewall (egress default-deny · chip C7) — prova que o firewall do devcontainer do agente MORDE (corta host fora da allowlist) e SOLTA (deixa passar github/a… |
| `dominio-gate.yml` | pr+push | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +24 | Dominio-dict ratchet (coerência de domínio) |
| `ds-gate.yml` | pr+push | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +9 | DS gate (fusão F1 — cor/UI/css/index/bundle/scorer · ADR 0314) |
| `ds-mirror-drift.yml` | pr+manual | `memory/decisions/0328-ds-transicao-congelado-para-vivo-git-ssot.md` +9 | ds-mirror-drift (advisory) — sentinela de drift git↔espelho do Design System: compara os _generated-*.css do git contra o snapshot commitado do espelho claude.ai/design (… |
| `ds-token-version.yml` | pr+manual | `memory/decisions/0335-fechamento-loop-diff-first-ds-sync-nota-honesta.md` +4 | ds-token-version — semver + changelog do pacote de tokens do DS: versiona a superfície dos _generated-*.css (296 tokens/4 escopos) por fingerprint sha256; `--check` sai ≠… |
| `ds-tokens-build-sync.yml` | pr+manual | `memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md` +6 | ds-tokens-build-sync — ANCORA a premissa do loop diff-first (git = fonte verdadeira): `scripts/design-sync/ds-tokens-build-sync.mjs --check` builda os *.tokens.json num t… |
| `dup-detector-gate.yml` | pr+manual | `memory/decisions/0331-anti-duplicacao-work-claim-gate.md` +2 | Dup detector (advisory · L3 anti-duplicação de trabalho entre sessões paralelas · arquivo hot-path em PR aberto sem Dedup-ack · proposta anti-duplicacao-work-claim-gate) |
| `e2e-gate.yml` | pr+manual | `memory/decisions/0339-promocao-soberana-3-gates-ratchet-ds-required-emenda-0336.md` +3 | E2E Playwright (UCs críticos) — G-3 |
| `eslint-gate.yml` | pr+push+manual | `memory/decisions/0338-ds-lint-eixo-valor-token-fecha-por-forma.md` +6 | ESLint 9 (ADR 0209) |
| `essentials-pest.yml` | pr+push+manual | `memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md` +2 | Essentials · Pest (MySQL) |
| `estoque-pest.yml` | pr+push+manual | `memory/decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md` +14 | Estoque · Pest (MySQL) — movimentação de saldo (venda/compra/devolução) roda no MySQL real; skip no sqlite = verde mente |
| `exposicao-tier0-sentinel.yml` | cron+manual | `memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.5-catraca-sentinela.md` +1 | Exposição Tier-0 — sentinela de cadência (Onda 0c · débito Tier-0 por tela dinheiro/estoque/PII/fiscal × cobertura de comportamento · cron semanal + tendência + issue dur… |
| `fin-hero-gate.yml` | pr+push | (só sessão/handoff · 1) | Fin Hero Gate — KPI hero claro (anti-regressão Onda 28) |
| `fin-subnav-gate.yml` | pr+push | `memory/08-handoff.md` +1 | Fin SubNav Gate — abas seguem a entry do active (anti-regressão ADR 0180 split) |
| `financeiro-pest.yml` | pr+push+manual | `memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md` +25 | Financeiro · Pest (MySQL) |
| `force-clean-rebuild-trigger.yml` | push+manual | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +3 | Force Clean Rebuild (one-shot) |
| `forja-pest.yml` | pr+push+manual | `memory/requisitos/Forja/SPEC.md` +1 | Forja · Pest (MySQL) — rotas /forja executam de verdade (em sqlite a stack UltimatePOS só SKIPa, e skip vira veredito `skip` no manifesto por-UC, nunca `pass`); catraca a… |
| `forja-shortcuts-gate.yml` | pr+push | `memory/decisions/0367-cockpit-unico-forja-project-mgmt-morre.md` +5 | Forja Shortcuts — atalhos do Board (overlay `?` · Enter · J/K/E/A anti-regressão) |
| `foundation-ratchet.yml` | pr+manual | `memory/decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md` +35 | Foundation ratchet (advisory · catracas só-diminui da fundação de testes — SDD FV-Q1) |
| `gate-selftest.yml` | pr+manual | `memory/decisions/0304-alocacao-numero-ciente-trabalho-em-voo.md` +45 | Gate selftest — quem vigia os vigias: cada catraca de governança contra fixtures boa/ruim versionadas (SDD GT-G6). Enforcement POR JOB: consultar governance/required-chec… |
| `gitleaks-history.yml` | cron+manual | `memory/LICOES_CODE.md` +4 | Gitleaks histórico completo (4º portão four-gate · full-history detect · advisory · ADR 0215) |
| `governance-drift.yml` | pr+cron+manual | `memory/decisions/0216-governance-drift-framework-driftchecker-plugavel.md` +15 | Governance Drift Framework — ADR 0216 |
| `governance-gate-umbrella.yml` | pr+manual | `memory/requisitos/_Governanca/roadmap/P10-sa-a5-a6-batches-ia-fila-wagner.md` +15 | Governance Gate (umbrella) |
| `governance-gate.yml` | pr | `memory/decisions/0147-cascade-review-defesa-drift-time-mcp.md` +54 | Governance Gate (pre-merge) |
| `governance-script-tests.yml` | pr+manual | `memory/decisions/0315-design-sync-claude-design-vs-cowork-charter.md` +47 | Governance script tests (advisory · scripts/governance/*.test.mjs — Onda 1; inclui agent-corpus-counterfactual.test.mjs, chip C1 da grade 2026-07-17: prova por Monte Carl… |
| `guards-meta-gate.yml` | pr+push | (só sessão/handoff · 1) | Guards meta-gate (vitest · casos + domínio · funde casos-meta + dominio-meta) |
| `handoff-integrity.yml` | pr+push+manual | `memory/requisitos/Governance/SPEC.md` +7 | Handoff Integrity (advisory · fila ↔ prompts) |
| `handoff-scope-guard.yml` | pr | (só sessão/handoff · 3) | Handoff Scope Guard (files_json · escopo duro do handoff de design, ADR 0283 Fase 0) |
| `handoff-sign-submit.yml` | pr+push+manual | `memory/decisions/0285-handoff-publisher-cowork-to-repo.md` +2 | Handoff Sign & Submit (on-push · assina HMAC + POST handoff-submit → pending; advisory · PR-6b ADR 0283) |
| `infra-contract-required.yml` | pr | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +11 | Infra Contract Required |
| `jana-conversas-gate.yml` | pr+push | `memory/licoes-rejeitadas.md` +3 | Jana Conversas — histórico do chat (filtro real · J/K · ⌘⇧H · aria-live) |
| `jana-logica-pura-pest.yml` | pr+manual | `memory/requisitos/Jana/AUDIT-GAPS-2026-08-10.md` +4 | Jana lógica pura Pest (event-time + histórico + audit-chain · funde 3 lanes Unit · ADR 0294/0295) |
| `jana-pest.yml` | pr+push+manual | `memory/requisitos/Jana/RUNBOOK-memoria.md` +19 | Jana · Pest (MySQL) |
| `jana-ragas-canary.yml` | cron+manual | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +10 | Jana RAGAS Canary (daily 06:00 UTC) |
| `jana-ragas-gate.yml` | pr+cron+manual | `memory/decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md` +22 | Jana RAGAS Eval Gate |
| `jana-recall-eval.yml` | pr+manual | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +6 | Jana recall-eval (mock gate · golden set determinístico · advisory → required ADR 0275 · P12 roadmap SDD) |
| `jscpd-gate.yml` | pr+push | — | jscpd ratchet (anti-duplicação de bloco copy-paste) |
| `kb-pest.yml` | pr+push+manual | `memory/requisitos/KB/SDD-tela-kb-unificado-v1.0.md` +15 | KB · Pest (MySQL) |
| `knowledge-ghost-gate.yml` | pr | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +3 | Knowledge Ghost Gate (catraca anti-ghost · baseline por módulo · ADVISORY — KL-A2) |
| `layout-primitives-guard.yml` | pr+push+manual | `.claude/skills/aplicar-prototipo/SKILL.md` +5 | Layout primitives guard (flex/grid solto) |
| `mcp-drift-sentinel.yml` | cron+manual | `memory/requisitos/Infra/AUDITORIA-OPS-DR-2026-07.md` +3 | MCP Drift Sentinel — servido vs main (ADR 0256 + 0062) |
| `memory-health.yml` | pr+cron+manual | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +78 | Memory Health — ADR 0256 |
| `memory-schema-gate.yml` | pr+push | `memory/decisions/0343-promove-adr-gate-required-emenda-0341.md` +42 | Memory schema gate (ONDA 5 S1 · FUNDIDO ADR 0314 F2: matrix AJV/frontmatter + sub-checks do corpo via validate-memory-schema.sh, ex-memory-schema-gate-extended D6 #4) |
| `module-grades-gate.yml` | pr | `memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md` +57 | Module Grades Gate (anti-regressão) |
| `module-surface.yml` | pr+manual | `memory/decisions/0370-module-surface-catalog-graph-required-emenda-0314.md` +33 | Module surface — guarda o índice GERADO de arquivos por módulo (memory/requisitos/<Mod>/SUPERFICIE.md) contra a árvore: self-test HARD + `--all --check` (drift real; obri… |
| `modules-pest.yml` | pr+push | `memory/decisions/0193-nfeservice-retransmitir-sem-forcedelete.md` +48 | Modules Pest |
| `multi-tenant-gate.yml` | pr+push | `memory/decisions/0283-handoff-loop-zero-paste.md` +14 | Multi-tenant gate |
| `mutation-gate.yml` | pr | `memory/requisitos/_Governanca/roadmap/P07-instrumentar-pcov-ci-coverage.md` +9 | Mutation Gate (advisory) |
| `mv-metabolismo.yml` | cron+manual | `memory/how-trabalhar.md` +18 | MV metabolismo (batimento nightly do Módulo Vivo · stream MV · sinais vitais + proposta de batch via auto-PR SEM auto-merge — merge Wagner = aprova batch) |
| `negocio-vs-governanca-ratio.yml` | pr+cron+manual | `memory/requisitos/_Governanca/roadmap/_ROADMAP.md` +4 | Ratio negócio × governança (sentinela anti-atrofia) — mede o FLUXO de merges (first-parent, janela 4 semanas) classificado em NEGÓCIO (A+B) × GOVERNANÇA-META (C) × INFRA,… |
| `nfebrasil-pest.yml` | pr+push+manual | `memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md` +19 | NfeBrasil · Pest (MySQL) — testes fiscais rodam no MySQL real (skip no sqlite = verde mente); JUnit alimenta o verde@ do gate de entrada G1b |
| `no-mock-gate.yml` | pr+push | (só sessão/handoff · 5) | No-mock-in-prod ratchet (stub/mock em controller) |
| `officeimpresso-pest.yml` | pr+push+manual | — | Officeimpresso · Pest (MySQL) |
| `outcome-metrics.yml` | pr+manual | (só sessão/handoff · 2) | Outcome metrics (advisory · medidor de aceitação Cowork→code · rework/revert/first-pass via SYNC_LOG proxy + git Pages/*.tsx · Onda O1) |
| `pageheader-gate.yml` | pr+push | `memory/decisions/0272-arvore-componentes-canonica.md` +11 | PageHeader migration guard (F4 · congela header antigo) |
| `pageheader-tabs-fidelity-gate.yml` | pr+push | — | PageHeaderTabs Fidelity — aba ativa fiel ao protótipo (radius 0 · accent · font 600) |
| `phpstan-baseline-regen.yml` | manual | — | PHPStan baseline regen (manual) |
| `phpstan-gate.yml` | pr+push | `memory/decisions/0208-larastan-baseline-ratchet.md` +10 | PHPStan / Larastan (ADR 0208) |
| `plan-health-gate.yml` | pr+manual | `memory/requisitos/Brief/BRIEFING.md` +1 | Plan Health Gate (advisory · planos órfãos/podres · sentinela plan-health.mjs --check · ADR 0294 Onda 1) |
| `ponto-pest.yml` | pr+push+manual | `memory/decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md` +10 | Ponto · Pest (MySQL) |
| `pr-critic-precisao.yml` | cron+manual | — | pr-critic precisão (advisory · mede a PRÓPRIA precisão do pr-critic · taxa-de-ação dos achados: o humano mexeu no arquivo apontado depois do comentário? + first-pass + po… |
| `pr-critic.yml` | pr | `memory/decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md` +10 | pr-critic contrato (advisory) — critic adversarial de PR ancorado em contrato: em PRs tocando resources/js/Pages/** ou Modules/**, roteia o diff pros contratos (charter/c… |
| `prompt-injection-corpus.yml` | pr+cron+manual | `memory/decisions/proposals/2026-07-28-guardrails-superficie-jana-cliente.md` +4 | prompt-injection corpus (red-team do agente · OWASP LLM01) — invoca .claude/governance-eval/prompt-injection-corpus.mjs: alimenta aos hooks REAIS as ações induzidas por i… |
| `protection-drift.yml` | pr+cron+manual | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +25 | Protection Drift — baseline de required checks + watchdog de staleness (advisory PERENE · cron diário 10:10 UTC — GT-G4, ADR 0275 §5/§3) |
| `pt-conformance.yml` | pr+manual | `memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md` +16 | ds-pt-conformance (advisory) — torna 'herda PT-0X' no charter FALSIFICÁVEL: verifica que a tela tem a assinatura estrutural do Padrão de Tela declarado (PT-02 exige <form… |
| `quick-sync.yml` | manual | `memory/decisions/0098-build-inertia-hostinger-pos-pull.md` +37 | Quick Sync (manual escape — auto-deploy agora é deploy.yml) |
| `reconcile-triplet.yml` | pr+manual | `memory/decisions/proposals/2026-06-24-eixos-de-orfao.md` +3 | Reconcile triplet (advisory · paridade por setor 3-way charter↔protótipo↔produção · 6 slots PT-01 · reconcile-triplet.mjs --all + charter-blueprint-pointers.mjs · self-te… |
| `repair-shared-vocab.yml` | pr+push | `memory/requisitos/OficinaAuto/kanban-producao-gap.md` +4 | Repair shared vocabulary guard |
| `required-always-run.yml` | pr+manual | `memory/08-handoff.md` +2 | Required always-run (advisory · todo context required nasce em todo PR · anti-deadlock de required-readiness) |
| `reuse-gate.yml` | pr+push | `memory/decisions/0255-contrato-view-deterministico-charter-design-spec.md` +1 | Reuse duplicates ratchet (anti-duplicação de símbolo) |
| `scope-guard.yml` | pr+push | `memory/decisions/0283-handoff-loop-zero-paste.md` +18 | Scope Guard (anti-drift) |
| `screen-coverage-gate.yml` | pr | `memory/decisions/0343-promove-adr-gate-required-emenda-0341.md` +14 | Screen Coverage Gate (catraca de cobertura) |
| `screen-grades-ratchet.yml` | pr | `memory/decisions/0373-screen-grades-ratchet-required-emenda-0314.md` +9 | Screen grades ratchet (nota 16-dim de tela não desce vs origin/main · cobre 2 vetores: baixar o valor e apagar o item) |
| `screen-smoke-after-merge.yml` | manual | `memory/decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md` +13 | Screen Smoke After Merge (fase C do PDCA MWART — smoke visual REAL pós-deploy via Playwright headless + OpenAI vision, runner ubuntu; dispara por workflow_run após deploy… |
| `sdd-scorecard-publish.yml` | push+cron+manual | `memory/requisitos/_Governanca/roadmap/P01-reconectar-read-side-floor.md` +11 | SDD floor commit-back — publica o floor vivo (branch órfã governance/nightly-floor) em governance/sdd-scorecard.json no main (P01/Gap-1a · ADR 0279) |
| `sdd-scorecard-ratchet.yml` | pr+manual | `memory/requisitos/_Governanca/roadmap/P14-catraca-floor-morde-no-required.md` +7 | SDD Scorecard Ratchet (2º dente SDD · GT-G3 — métrica armada não regride; hard, candidato a required; ADR 0275 §3) |
| `sdd-scorecard.yml` | pr+cron+manual | `memory/decisions/0331-anti-duplicacao-work-claim-gate.md` +29 | SDD Scorecard meta-catraca (advisory · determinismo + staleness + ratchet vs baseline — GT-G3, ADR 0275) |
| `sells-pest.yml` | pr+push+manual | `memory/requisitos/Sells/SDD-tela-venda-v1.0.md` +8 | Sells · Pest (MySQL) |
| `sells-v3-dominio-gate.yml` | pr+push | (só sessão/handoff · 1) | Sells V3 Domínio — parcelas · fiscal · comissão · colunas (vitest · JUnit → manifesto G-7) |
| `shipped-log-cron.yml` | cron+manual | `memory/08-handoff.md` +5 | Shipped log cron (auto-PR + auto-merge · regenera registro de entrega do cycle · porta de saída ADR 0294) |
| `shipped-log-gate.yml` | pr+cron+manual | `memory/proibicoes.md` +5 | Shipped log gate (advisory · freshness do registro de entrega via --check · porta de saída ADR 0294) |
| `status-badge-fidelity-gate.yml` | pr+push | (só sessão/handoff · 1) | StatusBadge Fidelity — pílula de status fiel ao protótipo (rounded-full · token -soft/-fg dark-aware) |
| `stylelint-gate.yml` | pr+push+manual | `memory/requisitos/Infra/SPEC.md` +1 | Stylelint CSS anti-drift (G5 · ADR 0209) |
| `system-map.yml` | pr+cron+manual | `memory/requisitos/Jana/ARCHITECTURE.md` +21 | system-map (automação) — regenera memory/reference/PAINEL-SISTEMA.md, memory/requisitos/Jana/ARCHITECTURE.md e ONBOARDING-AGENTE-GERADO.md das fontes canônicas. Painel de… |
| `tier0-guards-advisory.yml` | pr+push | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +3 | Tier-0 guards (WithoutGlobalScopes + BusinessId) |
| `ui-architecture-gate.yml` | pr+push | `memory/decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md` +9 | UI architecture gate |
| `verticais-pest.yml` | pr+push+manual | (só sessão/handoff · 1) | Verticais · Pest (MySQL) — ComunicacaoVisual/Repair/Vestuario rodam no MySQL real (skip no sqlite = verde mente); catraca allowlist verde |
| `visual-regression.yml` | pr+manual | `memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md` +86 | Visual Regression (Pest 4 Browser) |
| `whatsapp-pest.yml` | pr+push+manual | `memory/08-handoff.md` +1 | Whatsapp · Pest (MySQL) |
| `xss-content-gate.yml` | pr+push | — | XSS content ratchet (.tsx · dSIH + scheme · funde dsih-gate + scheme-gate · oráculo de conteúdo) |

## 2. Hooks (PreToolUse/PostToolUse/SessionStart) — 49 arquivos

> Fonte viva com evento×matcher×sinal-de-bloqueio: **`.claude/hooks/_HOOKS-INDEX.md`** (auto-gerado).
>
> `Invocador` = o par `evento(matcher)` que o `.claude/settings.json` registra — quem de fato
> dispara o hook. `—` aqui significa hook NO DISCO E FORA DO WIRING: existe e nunca roda.

| Hook | Invocador | Evidência | Documento | Descrição (cabeçalho) |
|---|---|---|---|---|
| `audit-creates-tasks.mjs` | PostToolUse(Write\|Edit) | — | `memory/decisions/0234-automation-registry-mcp.md` +8 | Hook PostToolUse(Write) — detecta tasks órfãs em audit doc + propõe tasks-create MCP. |
| `block-ancora-no-olho.mjs` | PreToolUse(Read\|Glob\|Grep) | test | `memory/decisions/0326-trava-ancora-compare-fingerprint.md` +12 | PreToolUse(Read): print de auditoria NÃO é âncora de design. |
| `block-askq-execution-menu.mjs` | PreToolUse(AskUserQuestion) | test + hook-bites | `memory/reference/feedback-recomendado-quando-tecnico.md` +11 | enforcement POR MÁQUINA da regra |
| `block-automem.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/decisions/0299-figma-nao-e-fonte-de-design.md` +12 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-bom-encoding.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +3 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-brl-values-in-memory.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | — | `memory/proibicoes.md` +6 | BLOQUEIA Write/Edit/MultiEdit que introduza valor BRL |
| `block-claim-without-evidence.mjs` | PreToolUse(Bash) | test | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +8 | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `block-design-sync-without-optin.mjs` | PreToolUse(DesignSync) UserPromptSubmit(*) | test | `memory/decisions/0315-design-sync-claude-design-vs-cowork-charter.md` +5 | claude.ai/design NÃO é fonte de design canônica. |
| `block-destructive.mjs` | PreToolUse(Bash) | test + hook-bites | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +48 | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `block-edit-authority-generated.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/licoes-rejeitadas.md` | PreToolUse:Write\|Edit\|MultiEdit. |
| `block-figma-without-optin.mjs` | PreToolUse(mcp__.*figma.*\|mcp__.*__(use_figma\|get_desig…) UserPromptSubmit(*) | test | `memory/decisions/0315-design-sync-claude-design-vs-cowork-charter.md` +8 | Figma NÃO é fonte de design (block determinístico por tool_name). |
| `block-instrumento-sem-porta-viva.mjs` | PreToolUse(Glob\|Grep\|Bash) | hook-bites | `memory/decisions/0353-maquina-evolucao-reguas-looping.md` +13 | PreToolUse:Glob\|Grep. |
| `block-memory-drift.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/decisions/0377-append-only-adr-excecao-por-label-emenda-0094.md` +24 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-merge-markers.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +6 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-mwart-violation.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test + hook-bites | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +43 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-routes-string-legacy.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +6 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-skill-design-sync-without-optin.mjs` | PreToolUse(Skill) | test | `.claude/runbooks/design-sync-push.md` | gateia a INVOCAÇÃO da skill /design-sync |
| `block-test-fora-ct100.mjs` | PreToolUse(Bash\|PowerShell) | test + hook-bites | `memory/proibicoes.md` +12 | PreToolUse:Bash\|PowerShell (PORTE cross-plataforma do .ps1). |
| `block-test-without-red.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | — | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |
| `brief-fetch-curl.mjs` | SessionStart(*) | test | `memory/decisions/proposals/2026-07-30-brief-se-divide-em-dois.md` +4 | SessionStart (PORTE cross-plataforma do brief-fetch-curl.ps1). |
| `charter-da-tela-que-o-controller-serve.mjs` | PreToolUse(Read) | test + hook-bites | `memory/decisions/proposals/documentacao-do-fonte-layout-canonico.md` | PreToolUse:Read. ADVISORY (nunca bloqueia). |
| `charter-validate.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test + hook-bites | `memory/decisions/0225-skills-tier-a-recalibracao-claude-4.8.md` +14 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1, advisory). |
| `check-skills-fresh.mjs` | SessionStart(*) | test | (só sessão/handoff · 1) | SessionStart (PORTE cross-plataforma do .ps1, advisory). |
| `commit-discipline-check.mjs` | PreToolUse(Bash) | test + hook-bites | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +3 | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `design-agente-ativa.mjs` | UserPromptSubmit(*) | — | `memory/proibicoes.md` +7 | ATIVA no momento: "você É o designer-agente v2, NÃO espera insumo externo". |
| `design-compare-protocol.mjs` | UserPromptSubmit(*) | — | `.claude/skills/comparar-design-prod/SKILL.md` +5 | Hook UserPromptSubmit — ATIVA o protocolo de comparação design×prod (LC-06, strike 2). |
| `design-handoff-reprocess.mjs` | UserPromptSubmit(*) | test | `.claude/skills/design-memoria-reprocess/SKILL.md` +1 | Hook design-handoff-reprocess — detecta o bloco `## new_design_memories` num |
| `diag-pretooluse-trace.mjs` | PreToolUse(Skill\|DesignSync\|design-login) | — | `memory/decisions/0315-design-sync-claude-design-vs-cowork-charter.md` +3 | INSTRUMENTO DE DIAGNÓSTICO (NÃO é um gate). |
| `doc-fora-do-rag.mjs` | PreToolUse(Write) | test | `memory/reference/como-escrever-doc-para-o-rag.md` +5 | PreToolUse:Write. ADVISORY (nunca bloqueia). |
| `force-r12-closing-signal.mjs` | UserPromptSubmit(*) | — | `memory/decisions/0234-automation-registry-mcp.md` +7 | Hook UserPromptSubmit — FORÇA R12 PROTOCOLO ao detectar sinal de fechamento. |
| `git-base-freshness-guard.mjs` | SessionStart(*) | test | `memory/08-handoff.md` +12 | Hook SessionStart — GUARD de base fresca vs `origin/main`. |
| `handoff-inline.mjs` | SessionStart(*) | test | `memory/08-handoff.md` +1 | SessionStart (PORTE cross-plataforma do comando PowerShell INLINE do settings.json). |
| `licoes-code-two-strikes.mjs` | SessionStart(*) | test | `memory/decisions/0344-two-strikes-cobre-processo.md` +16 | SessionStart (PORTE cross-plataforma do .ps1, advisory). |
| `loop-fechar-check.mjs` | SessionStart(*) | test | `memory/licoes-rejeitadas.md` +3 | SessionStart (PORTE cross-plataforma do .ps1, advisory). |
| `memory-pending.mjs` | Stop(*) | test | `.claude/commands/sync-mem.md` +2 | Stop (PORTE cross-plataforma do .ps1, advisory). |
| `memory-schema-guard.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | hook-bites | `memory/08-handoff.md` +3 | PreToolUse:Write\|Edit\|MultiEdit em memory/** e charters. |
| `modulo-preflight-warning.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test + hook-bites | `memory/decisions/0225-skills-tier-a-recalibracao-claude-4.8.md` +11 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1, advisory). |
| `nudge-auditoria-resposta.mjs` | Stop(*) | test | — | Stop (advisory). Checklist de auditoria ANTES de responder. |
| `nudge-diagnosis-without-evidence.mjs` | Stop(*) | test | `memory/LICOES_CODE.md` | Stop (PORTE cross-plataforma do .ps1, advisory · estende R1). |
| `nudge-recommend-not-menu.mjs` | Stop(*) | test | `memory/decisions/0262-governanca-escala-com-o-time.md` +1 | Stop (PORTE cross-plataforma do .ps1, advisory · R13/ADR 0233). |
| `nudge-test-contract-anchor.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` +1 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1, advisory). |
| `php-syntax-after-write.mjs` | PostToolUse(Write\|Edit\|MultiEdit) | test + hook-bites | `memory/LICOES_CODE.md` +4 | PostToolUse:Write\|Edit\|MultiEdit. |
| `pii-redactor.mjs` | PreToolUse(Bash) | test | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +11 | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `post-merge-ui-smoke-required.mjs` | PostToolUse(Bash) PreToolUse(Bash) PreToolUse(mcp__computer-use__screenshot\|mcp__[Cc]laude…) | test + hook-bites | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +5 | PostToolUse:Bash + PreToolUse:Bash\|browser-MCP |
| `preflight-new-capability.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test + hook-bites | (só sessão/handoff · 3) | PreToolUse:Write (PORTE cross-plataforma do .ps1, advisory). |
| `tema-owner-advisory.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test + hook-bites | `memory/licoes-rejeitadas.md` +2 | PreToolUse:Write (ADVISORY, allow · ADR 0224/0314). |
| `tier-a-banner.mjs` | SessionStart(*) | test | `memory/requisitos/ADS/BRIEFING.md` +5 | SessionStart (PORTE cross-plataforma do tier-a-banner.ps1). |
| `vista-publicada-padrao.mjs` | PreToolUse(Artifact) | test | `memory/reference/VISTAS-PUBLICADAS.md` +2 | PreToolUse:Artifact. ADVISORY (nunca bloqueia). |
| `warn-red-first.mjs` | PreToolUse(Write\|Edit\|MultiEdit) | test | `memory/LICOES_CODE.md` +1 | PreToolUse:Write\|Edit\|MultiEdit (PORTE cross-plataforma do .ps1). |

## 3. Skills — 74

> Fonte viva com Tier/auto_trigger: **`.claude/skills/_SKILLS-INDEX.md`** (auto-gerado do frontmatter).
>
> **Invocador não é derivável** aqui: skill dispara por casamento de `description` (semântico),
> e o `Tier` já é o eixo de ativação. As 40 de 74 que aparecem em hook/workflow/script são
> MENÇÃO (o hook nudga a skill), não invocação — chamar isso de invocador seria medir outra
> propriedade. **Evidência** idem: não existe fixture boa/ruim de skill (medido: 0 de 74).

| Skill | Tier | Documento | Descrição (início) |
|---|---|---|---|
| `alinhar-tela` | B | `memory/requisitos/Cliente/audits/ALINHAMENTO-cliente-2026-06-22.md` +2 | Use quando Wagner pedir "alinhar a tela X", "ligar a máquina da tela Y", "o que já tem pronto e o que falta na tela Z", "fidelidade spec↔código de <Mo… |
| `aplicar-prototipo` | B | `memory/decisions/0325-import-prototipo-designsync-pull-direto.md` +30 | ATIVAR quando Wagner pedir "pega o que mudou no protótipo e aplica", "aplicar protótipo nas telas", "atualizar as telas com o protótipo", "o que mudou… |
| `audit-constituicao` | C | `memory/decisions/0120-reverse-supersession-metadata-housekeeping.md` +4 | ATIVAR quando user pedir "audit pós-constituição", "/audit-constituicao", "consolidação geral", "revisão geral desde a constituição", OU trimestralmen… |
| `audit-to-backlog` | B | `memory/decisions/0213-audit-creates-tasks-loop-fechado.md` +7 | ATIVAR quando user pedir "transformar audit em tasks", "levar audit X pro backlog", "criar tasks do audit", "/audit-to-backlog <doc>", OU quando agent… |
| `automem-pending` | B | `memory/decisions/0131-tiering-memoria-canonico-local-segredo.md` +8 | BLOQUEADOR — quando user mencionar tópico/módulo OU Edit/Read em path com auto-mem stale pendente migração (ADR 0061), esta skill carrega manifesto AU… |
| `avaliar-modulo` | B | `memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md` +13 | ATIVAR quando user pedir "nota do módulo X", "avaliar Modules/X", "/avaliar-modulo X", "qual a nota de Y", "module grade de Z", "qual o bucket de W", … |
| `brief-first` | B | `memory/decisions/0091-daily-brief.md` +63 | BLOQUEADOR — antes de qualquer outra tool MCP, Read, Glob, Grep ou ação no |
| `brief-update` | B | `memory/decisions/0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md` +49 | Use SEMPRE depois de commit/merge de PR que altere capacidades, diferenciais, score Capterra, UX visível, ou gaps de um módulo do oimpresso. |
| `charter-first` | B | `memory/decisions/0102-s6-charter-capterra-postmortem-s7-backlog.md` +63 | BLOQUEADOR — ANTES de editar qualquer .tsx que tenha .charter.md ao lado (ex Index.tsx + Index.charter.md), chame tool MCP `charter-fetch <page-id>` p… |
| `charter-write` | B | `memory/decisions/0102-s6-charter-capterra-postmortem-s7-backlog.md` +41 | ATIVAR quando user pedir "criar charter da tela X", "escrever charter pra /caminho", "gerar charter de Index.tsx Y", "novo charter Page", "/charter-wr… |
| `cliente-discovery` | B | `memory/requisitos/_DesignSystem/RUNBOOK-design-deep.md` +5 | ATIVAR quando Wagner pedir /cliente-discovery, "entrevistar cliente X", "fazer discovery do cliente Y", "criar persona pra <pessoa>", "vou visitar cli… |
| `cockpit-runbook` | C | `memory/decisions/0104-processo-mwart-canonico-unico-caminho.md` +39 | Generates a detailed RUNBOOK.md or audits a screen against the Chat Cockpit pattern (ADR 0039) for the oimpresso ERP. |
| `commit-discipline` | A | `memory/decisions/0208-larastan-baseline-ratchet.md` +103 | Use ANTES de git commit ou git push em qualquer PR do oimpresso. |
| `comparar-design-prod` | B | `memory/requisitos/_DesignSystem/RESPEITAR-PROTOTIPO.md` +5 | BLOQUEADOR de eyeball — ATIVAR SEMPRE que a tarefa envolver COMPARAR design/protótipo com tela em produção ou declarar que estão iguais. |
| `comparativo-do-modulo` | B | `memory/decisions/0213-audit-creates-tasks-loop-fechado.md` +51 | ATIVAR quando user pedir "comparar módulo X com mercado", "auditar escopo do módulo Y", "o que falta no módulo Z vs estado da arte", "inventário aprov… |
| `constituicao-ui-aware` | B | `memory/decisions/0187-constituicao-ui-v2-ponteiro-canon.md` +8 | Use SEMPRE antes de Edit/Write em qualquer `resources/js/Pages/<X>/*.tsx`, `resources/js/Components/shared/**/*.tsx`, `resources/css/cockpit.css`, `re… |
| `cowork-prototype-replication` | B | `memory/decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md` +8 | ATIVAR quando user pedir "fazer layout estado-da-arte", "replicar protótipo Cowork", "espelhar visual-source.html", "transformar prototipo-ui/* em Ine… |
| `criar-modulo` | B | `memory/decisions/0137-modules-oficinaauto-qualificada.md` +54 | Use ao criar novo módulo Laravel modular (nWidart) no oimpresso — qualquer pasta nova em `Modules/<Nome>/`, ou pedido explícito "criar módulo", "novo … |
| `criar-staging` | B | `memory/decisions/0235-staging-ct100-clone-anonimizado.md` +1 | ATIVAR quando user pedir "criar staging", "ambiente de homologação/homolog", "replicar produção pra teste", "subir/recriar/atualizar staging.oimpresso… |
| `curador` | B | `memory/requisitos/Arquivos/BRIEFING.md` +6 | ATIVAR quando user pedir "ingerir conhecimento", "triar D:\\Conhecimento", "organizar arquivos do computador", "ler tudo e classificar", "/curador <su… |
| `design-deep-analysis` | B | `memory/requisitos/_DesignSystem/framework-15-dimensoes.md` +9 | ATIVAR quando Wagner pedir /design-deep <persona-slug>, "analisar visualmente tela X pra persona Y", "design profundo da tela <Z>", OU em refator visu… |
| `design-memoria-reprocess` | B | `memory/decisions/0236-governanca-evolucao-doc-design.md` +4 | ATIVAR quando (a) o Claude Design enviar handoff com bloco `## new_design_memories`; |
| `encerrar-sessao` | B | `memory/reference/PROTOCOLO-WAGNER-SEMPRE.md` +13 | BLOQUEADOR — ATIVAR SEMPRE que user disser "encerrar sessão", "fim de sessão", "vamos parar", "continua depois", "salvar tudo", "salve as memórias", "… |
| `feedback-capture` | B | `memory/requisitos/Jana/LICOES-OPERACAO.md` +6 | ATIVAR quando Wagner colar feedback de cliente real OU disser "Daniela reclamou X", "Larissa pediu Y", "Kamila falou que Z", "Jair quer W", "via Whats… |
| `feedback-dashboard` | B | `.claude/skills/feedback-capture/SKILL.md` +1 | ATIVAR quando Wagner pedir "/feedback-dashboard", "mostra feedback", "como está o feedback", "que feedback tem aberto", "feedback do <cliente>", "feed… |
| `funcao-scorecard` | B | `memory/decisions/proposals/2026-07-21-funcao-scorecard-opiniao-ancorada-rubrica.md` +6 | ATIVAR quando [W] pedir "o que você acha dessas funções", "concorda com essa função?", "avalie as funções do <arquivo>", "parecer do ProductUtil", "/f… |
| `governance-pr-summary` | B | `memory/requisitos/SRS/DEPRECATION-PLAN.md` +7 | Use ANTES de `gh pr create` em qualquer branch que toque Modules/<X>/. |
| `hostinger-dns-autonomy` | A | `memory/decisions/0225-skills-tier-a-recalibracao-claude-4.8.md` +10 | BLOQUEADOR Tier A — ATIVAR antes de pedir Wagner pra criar/editar DNS record, qualquer ação Hostinger painel/UI, OU sempre que agente cogitar "pode vo… |
| `incident-done-checklist` | A | `memory/decisions/0216-deploy-webhook-rodar-composer-dump-autoload.md` +11 | BLOQUEADOR — ATIVAR antes de declarar "incident fechado" / "está pronto" / "feature funcionando" / encerrar sessão de fix em prod. |
| `inertia-defer-default` | B | `memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md` +41 | Use SEMPRE antes de Edit em qualquer Controller que chama `Inertia::render(...)` no oimpresso (qualquer `Modules/<X>/Http/Controllers/**/*Controller.p… |
| `jana-arch` | B | `memory/requisitos/Jana/RUNBOOK-chat.md` +10 | Use ao trabalhar em Modules/Jana/ ou ao tocar memória/IA do projeto. |
| `jana-brief-concierge` | B | `memory/requisitos/Jana/RUNBOOK-jana-pro-concierge.md` +4 | ATIVAR quando user (Wagner) colar/citar um JSON com chaves `version`, `business_id`, `sources` (vendas/inadimplencia/tickets/nfe/oportunidades) OU ped… |
| `jana-recall-flow` | B | `memory/decisions/0148-cascade-review-onda-6-memoria-senior-98.md` +8 | Use ao tocar Modules/Jana/Services/Memoria/, ContextSnapshotService, recall hybrid (Meilisearch + HyDE + reranker), MCP memory sync (git→DB→Scout), ou… |
| `mcp-first` | B | `memory/decisions/0095-skills-tiers-convencao-interna.md` +36 | ATIVAR antes de Read/Glob/Grep em memory/, ler ADR/session/spec do projeto, buscar conhecimento canônico do oimpresso, criar arquivo em ~/.claude/proj… |
| `memory-first-secret-search` | A | `memory/decisions/0215-secrets-governance-5-camadas-automaticas.md` +11 | BLOQUEADOR Tier A — ATIVAR ANTES de qualquer busca por token / API key / password / SSH key / credential / secret. |
| `memory-schema-preflight` | B | `memory/proibicoes.md` +17 | ATIVAR ANTES de Write/Edit em `memory/requisitos/**/SPEC.md`, `memory/requisitos/**/RUNBOOK*.md`, `memory/requisitos/**/BRIEFING.md`, `memory/decision… |
| `memory-sync` | B | `memory/decisions/0130-handoff-append-only-mcp-first.md` +22 | ATIVAR após criar/editar arquivo em memory/, atualizar SPEC.md/TEAM.md, salvar ADR/session log, ou usar trigger "salve no cofre"/"guarde"/"grave na me… |
| `meta-skill-roi-erp-autonomo` | C | `memory/decisions/0078-constituicao-uma-frase-skill-unidade-evolucao.md` +12 | ATIVAR ao criar skill nova, usar `skill:scaffold`, discutir se uma ideia merece virar skill, ou perguntar "isso vira skill?". |
| `migracao-blade-react` | B | `memory/decisions/0141-skill-migracao-blade-react.md` +43 | ATIVAR quando user pedir "migrar tela X", "migrar Blade pra React", "migração massiva", "/migracao-blade-react <modulo>/<tela>", OU em Edit/Write em `… |
| `migrar-modulo` | B | `memory/decisions/0152-modules-pcp-feature-wish.md` +11 | Use ao mover, renomear, ou extrair controller/módulo Laravel modular existente em `Modules/<X>/` — qualquer `git mv Modules/X Modules/Y`, `git mv Modu… |
| `migration-status` | B | — | ATIVAR quando user pedir "status migração", "% migrado {módulo}", "tabelas Firebird", "status da migração por tabelas", "dependências da migração", "/… |
| `module-completeness-audit` | B | `memory/decisions/0141-skill-migracao-blade-react.md` +27 | ATIVAR antes de marcar US como `done` (`tasks-update task_id:US-XXX-NNN status:done` ou `tasks-update from:review to:done`), OU quando user pedir "aud… |
| `module-grades-gate` | C | `memory/decisions/0261-enforcement-faseado-gates-ci.md` +49 | ATIVAR quando user pedir "checar grades antes de PR", "rodar gate de notas local", "atualizar baseline module-grade", "como override regressão grades"… |
| `multi-tenant-patterns` | A | `memory/decisions/0093-multi-tenant-isolation-tier-0.md` +126 | Use ao criar ou alterar Eloquent Model, Controller, Service, Job, Command ou Migration que toca dados de negócio (qualquer entidade com `business_id`)… |
| `mwart-comparative` | B | `memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md` +64 | Use SEMPRE antes de codar Page Inertia em migração MWART (Blade→React) no oimpresso. |
| `mwart-process` | B | `memory/decisions/0104-processo-mwart-canonico-unico-caminho.md` +66 | Use SEMPRE que o trabalho envolva migrar tela Blade legacy → Inertia/React no oimpresso (MWART). |
| `mwart-quality` | B | `memory/decisions/0100-projectmgmt-ui-redesign.md` +32 | Use ANTES de criar/editar tela MWART (Module Web App React Transition Blade→Inertia/React) no oimpresso. |
| `officeimpresso-financial-snapshot` | B | `memory/decisions/0136-sells-grade-avancada-modo-toggle.md` +48 | ATIVAR quando user pedir "analisar receita do cliente X", "snapshot financeiro de {cliente OfficeImpresso}", "comparar 2 clientes legacy", "/financial… |
| `officeimpresso-source-analysis` | B | `memory/decisions/0137-modules-oficinaauto-qualificada.md` +19 | ATIVAR quando precisar entender comportamento real de uma tela/feature do OfficeImpresso legacy (Delphi WR Comercial) — em vez de inferir via probes n… |
| `oimpresso-cc-watcher-setup` | C | `memory/decisions/0122-admin-center-ct100.md` +10 | Configura o watcher local do Claude Code que sincroniza ~/.claude/projects/*.jsonl com o MCP server do oimpresso (cc-search cross-dev). |
| `oimpresso-stack` | C | `memory/decisions/0076-skills-db-primary-git-destino-drift-alert.md` +12 | Use ao iniciar trabalho no oimpresso ou ao entrar num módulo novo. |
| `oimpresso-team-onboarding` | C | `memory/decisions/0131-tiering-memoria-canonico-local-segredo.md` +23 | Configura ou valida acesso ao MCP server da empresa oimpresso (Wagner/Felipe/Maiara/Luiz/Eliana). |
| `pageheader-canon` | B | `memory/decisions/0185-drawer-760-canon-entidades-cadastrais.md` +18 | ATIVAR quando agente vai aplicar o PageHeader canon (ADR 0180/0182/0189/0190) em módulo novo — user pede "aplicar pageheader canon no módulo X", "padr… |
| `personas-resolve` | B | `.claude/skills/feedback-capture/SKILL.md` +1 | BLOQUEADOR Tier A — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em arquivos de `resources/js/Pages/**/*.tsx` ou criação de tela nova. |
| `pr-ui-judge-manual` | C | `memory/requisitos/_DesignSystem/CHANGELOG.md` +4 | Use quando Wagner pedir "avaliar PR <número> contra Constituição UI v2", "rodar judge no PR X", "review semântico do PR Y", "/pr-ui-judge <PR#>", "sco… |
| `pre-adr-introspect` | B | `memory/decisions/0304-alocacao-numero-ciente-trabalho-em-voo.md` +3 | ATIVAR ANTES de qualquer Write em `memory/decisions/NNNN-*.md` (ADR nova) OU antes de propor schema novo (`database/migrations/*.php` que adiciona col… |
| `pre-decisao-git-first` | B | — | ATIVAR ANTES de interromper o Wagner com uma dúvida durante o desenvolvimento — sempre que for usar AskUserQuestion, escrever "não sei se...", "qual v… |
| `precisao-literal` | B | `memory/reference/metodo-precisao-literal.md` +1 | ATIVAR quando user pedir "compare com o protótipo", "avalie precisão", "que % literal", "ficou idêntico?", "compare lado a lado", "nota da paridade", … |
| `preflight-modulo` | B | `memory/decisions/0225-skills-tier-a-recalibracao-claude-4.8.md` +36 | BLOQUEADOR — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em Modules/<X>/. |
| `proxmox-docker-host` | C | `memory/requisitos/Whatsapp/SPEC.md` +12 | Use ao mexer com infra Proxmox/CT 100/containers Docker do oimpresso. |
| `publication-policy` | B | `memory/decisions/0040-policy-publicacao-claude-supervisiona.md` +36 | Use ANTES de qualquer git push, abertura/merge de PR, deploy em produção, mudança em .env de produção, ou postagem externa (blog, rede social, email c… |
| `reguas-do-sistema` | B | `memory/decisions/0333-emenda-0330-eixo-rodar-e-observar-submedido.md` +22 | ATIVAR quando Wagner pedir "grade de réguas", "onde sou fraco vs mercado", |
| `runtime-rules-hostinger-ct100` | B | `memory/decisions/0216-deploy-webhook-rodar-composer-dump-autoload.md` +17 | Use ANTES de SSH no Hostinger, composer install/update em servidor, criar git worktree em servidor, ou qualquer comando que envolva laravel/mcp, larav… |
| `screen-grade` | B | `memory/decisions/0320-programa-ondas-regua-correcao.md` +30 | ATIVAR quando user pedir "nota da tela X", "gradear tela Y", "/screen-grade Sells/Create", "qual a maturidade da tela Z", "pré-flight da tela W", "scr… |
| `sdd-avaliar` | C | `memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md` +17 | Use ANTES de promover qualquer gate SDD a required (calendário ADR 0275), AO |
| `session-start-check` | B | `memory/decisions/0119-paralelismo-sessoes-whats-active-tier-1.md` +10 | ATIVAR depois do brief-first em toda sessão. |
| `sidebar-menu-arch` | B | `memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md` +20 | Reconhecer, auditar e modificar a arquitetura do sidebar do AppShellV2 — DataController por módulo + agrupamento visual via SIDEBAR_GROUPS no frontend… |
| `smoke-prod-evidence` | B | `memory/decisions/0224-hooks-block-vs-advisory-claude-4.8-aware.md` +20 | ATIVAR antes de declarar "funcionando", "smoke OK", "deploy ok", "está rodando" no oimpresso. |
| `tela-smoke-pos-merge` | B | `memory/decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md` +14 | ATIVAR após PR mergeado que toca resources/js/Pages/**/*.tsx OU quando Wagner pedir "smoke a tela X", "validar tela X visualmente", "ver como ficou te… |
| `ticket-triage` | B | `memory/decisions/0141-agents-tool-use-pattern-claude-code.md` +10 | ATIVAR quando user pedir "analise esse ticket", "triage", "vale a pena atender X?", "qual a prioridade", "esse cliente é importante?", "score do ticke… |
| `ui-component-creator` | B | `memory/requisitos/NfeBrasil/SPEC.md` +8 | Use ao criar/modificar componentes React (Pages Inertia, sub-componentes em _components/, ou shareds em Components/shared/) seguindo Cockpit Pattern V… |
| `validador-modulo` | B | (só sessão/handoff · 1) | ATIVAR quando Wagner pedir "valida o módulo X inteiro", "confere a estrutura |
| `wagner-protocol-enforce` | B | `memory/decisions/0169-errata-0168-runbook-onda-cowork-canon.md` +12 | BLOQUEADOR Tier A always-on — carrega memory/reference/PROTOCOLO-WAGNER-SEMPRE.md |
| `wagner-request-refiner` | B | `memory/decisions/0168-protocolo-wagner-sempre-tier-A-irrevogavel.md` +20 | ATIVAR quando Wagner manda múltiplos pedidos curtos não-estruturados num mesmo turno (ex: lista com 3+ items, "todo: a) b) c)", bullets numerados, scr… |

## 4. Agents (subagentes Task) — 27

> **Invocador/Evidência não são deriváveis** aqui (mesma razão das skills — agente é spawnado por
> intenção, e não há fixture de agente). Medido: 8 de 27 aparecem em script/workflow, todas como menção.

> **Coluna `Escreve?` — DERIVADA do frontmatter `tools:`** (eixo *risco*), não da prosa da
> `description`. Conta como escrita: `Write`, `Edit`, `NotebookEdit`, `Bash` — **`Bash` inclusive**, porque
> `sed -i`, `>` e `git commit` escrevem (se você discorda desse critério, ele está aqui pra ser
> discutido, não escondido). Medido agora: **27 de 27** podem escrever.
>
> ⚠️ Onde a prosa e a capacidade DISCORDAM, quem manda é a capacidade: agente que se descreve
> "read-only / nunca edita / nunca commita" e tem `Bash` está fazendo uma **promessa**, não
> operando sob **restrição**. Instrução de prompt não é mecanismo.

| Agent | Escreve? | Documento | Descrição (início) |
|---|---|---|---|
| `audit-implement-expert` | 🔴 Bash/Write/Edit | `memory/decisions/0231-processo-trabalho-canonico-especialista-por-area.md` +13 | Implementador universal de gap específico — recebe um GAP da auditoria (Fase 1 do `/audit-and-fix`), pesquisa best-of-class do gap, mini-comparativo % atual→target, e imp… |
| `audit-research-expert` | 🔴 Bash/Write | `memory/decisions/0236-scorecard-universal-entidade-arbitraria.md` +16 | Auditor universal de maturidade — recebe um TEMA (ex "reranker", "knowledge-architecture", "session-handoff", "observability"), pesquisa estado-da-arte 2025-2026, compara… |
| `audit-senior-expert` | 🔴 Bash/Write | `memory/decisions/0231-processo-trabalho-canonico-especialista-por-area.md` +15 | Auditor SÊNIOR — pesquisa profunda (5-7 WebSearch POR gap), comparativo rigoroso, dossier executável pra Onda inteira. |
| `capterra-senior` | 🔴 Write/Bash | `memory/decisions/0320-programa-ondas-regua-correcao.md` +53 | Use quando Wagner pedir "Capterra do módulo X", "compare meu módulo Y com os melhores e dá nota", "estado-da-arte profundo do módulo Z", "/capterra-senior <Modulo>", "pes… |
| `ciclo-adversary` | 🔴 Bash | `memory/LICOES_CODE.md` +1 | Adversário read-only do CICLO DE APRENDIZADO (erro → conserta → lápide §5 → ledger LC → defesa mecânica). |
| `cliente-drawer-integrar` | 🔴 Bash/Write/Edit | (só sessão/handoff · 1) | Implementador especializado da integração legacy WR Comercial/Delphi → drawer Cliente 760px (ADR 0179). |
| `como-integrar` | 🔴 Bash/Write | `memory/decisions/0200-contacts-sync-canon-amends-0197-0199.md` +29 | Use ANTES de Wagner aprovar implementação de feature nova/refactor médio no oimpresso. |
| `comunicacao-visual-expert` | 🔴 Write | — | Especialista de domínio em Comunicação Visual industrial brasileira (CNAE 1813-0/01) — processos OS, PCP, instalação, tributação serviço vs mercadoria, NR-35 fachada, con… |
| `coordenador-paralelo` | 🔴 Write/Bash | `memory/decisions/0180-drift-numero-adr-0178-conflito-paralelo.md` +22 | Use quando Wagner pedir "coordene em paralelo X", "decompor em waves", "spawne N agents pra Y", "faça em paralelo sem invadir outras áreas", OU quando o problema admite d… |
| `cowork-to-inertia` | 🔴 Bash/Write/Edit | `.claude/agents/cliente-drawer-integrar.md` +2 | Use quando Wagner mandar design Cowork pra implementar como Inertia/React real — sinais típicos "implementa essa tela", "esse design tem nota 9,75", "vou te mandar a tela… |
| `deprecar-modulo` | 🔴 Bash/Write | `memory/decisions/0301-separar-cliente-deprecar-crm-pipeline.md` +7 | Use quando Wagner decidir deprecar/aposentar um módulo Laravel modular do oimpresso (ex SRS, Officeimpresso legacy, Cms antigo, qualquer Modules/<X> em estado zumbi). |
| `design-arte` | 🔴 Write/Bash | `memory/requisitos/Sells/SPEC.md` +17 | Use quando Wagner pedir "estado da arte de design do oimpresso", "nota de design da tela X", "Capterra de design pro módulo Y", "compare meu design com Linear/Shopify/Not… |
| `document-relocation-adversary` | 🔴 Bash | `memory/proibicoes.md` +2 | Adversario read-only de planos de classificacao, movimento e relink de documentacao. |
| `documentacao-sistema` | 🔴 Bash/Write/Edit | `memory/08-handoff.md` +2 | ATIVAR quando [W] pedir qualquer coisa sobre a DOCUMENTAÇÃO DO SISTEMA — "documenta o sistema", |
| `estado-da-arte` | 🔴 Write/Bash | `memory/decisions/0319-product-truth-stream-adversario-modulo-analise.md` +43 | Use quando o Wagner pedir "faça o estado da arte de X", "estado da arte de Y", "pesquise como os melhores fazem Z", "/estado-da-arte <problema>". |
| `financeiro-bridge-auditor` | 🔴 Bash/Write | `memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md` +6 | Auditor especialista da bridge Sells/Compras (UltimatePOS core) → Modules/Financeiro (`fin_titulos`/`fin_titulo_baixas` via Observers). |
| `maturity-gap-expert` | 🔴 Bash/Write | `memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md` +4 | Especialista em gap analysis maturidade oimpresso vs estado-da-arte 2026. |
| `memoria-senior` | 🔴 Write/Bash | `memory/decisions/0148-cascade-review-onda-6-memoria-senior-98.md` +9 | Use quando Wagner pedir "auditoria de memória", "otimizar memory/", "estado-da-arte arquitetura de memória/knowledge architecture/RAG", "compare minha memória com Mem0/Le… |
| `migracao-firebird-versoes` | 🔴 Bash/Write/Edit | `memory/reference/matriz-conhecimento-clientes-legacy.md` +3 | Use quando Wagner pedir "termine a migração", "migra os clientes legacy todos", "trate as versões diferentes Firebird", "/migrar-versoes <cliente>", "terminar Martinho", … |
| `migracao-officeimpresso` | 🔴 Bash/Write/Edit | `memory/reference/migracao-officeimpresso-pattern.md` +5 | Use quando Wagner pedir "migrar cliente legacy <hash>", "importar Firebird de <cliente>", "trazer dados Delphi pra oimpresso", "/migrar-officeimpresso <cliente>", OU quan… |
| `screen-qa-specialist` | 🔴 Bash/Write/Edit | `memory/decisions/0250-screen-qa-specialist-sustentavel.md` +2 | ATIVAR quando Wagner pedir "garantir QA da tela X", "testar a tela Y de ponta a ponta", "cobrir a tela Z", "/screen-qa <Mod>/<Tela>", "especialista de teste na tela W", "… |
| `sdd-from-source` | 🔴 Bash/Write/Edit | `memory/decisions/0351-sdd-from-source.md` +56 | ATIVAR quando [W] pedir "gera o SDD da tela X a partir do fonte", "documenta o fluxo real de <Mod>/<Tela>", "faz o SDD/casos de <Mod>/<Tela> analisando o código", "/sdd-f… |
| `tela-venda-arte` | 🔴 Write/Bash | `memory/requisitos/Sells/SPEC.md` +7 | Use quando Wagner pedir "estado da arte da tela de venda", "compare minha tela de venda com os concorrentes", "benchmark POS", "nota da minha tela de venda", "como o Blin… |
| `testador-de-maquinas` | 🔴 Bash/Write | `memory/LICOES_CODE.md` +2 | ATIVAR quando [W] pedir "essa máquina morde?", "testa o gate X", "esse hook está funcionando mesmo?", "audita a máquina Y", "prova que o gate pega", "posso promover esse … |
| `wagner-understand` | 🔴 Bash/Write | `memory/decisions/0319-product-truth-stream-adversario-modulo-analise.md` +26 | ATIVAR ANTES de Claude começar a executar pedido do Wagner — especialmente quando o pedido vem cru/curto/ambíguo ("faz isso", "implementa X", "copia aquilo", screenshot c… |
| `whatsapp-arch-arte` | 🔴 Write/Bash | `memory/requisitos/Whatsapp/SPEC.md` +2 | Use quando Wagner pedir "estado da arte de arquitetura WhatsApp/mensagens", "compare minha estrutura WhatsApp com os melhores e dá nota", "auditar arquitetura técnica do … |
| `whatsapp-doctor` | 🔴 Bash/Write | `memory/how-trabalhar.md` +14 | Use quando WhatsApp Baileys daemon der problema no CT 100 — "WhatsApp parou", "mensagem não saiu", "tá banido?", "loop de erro no daemon", "device_removed", "stream error… |

## 5. Scripts (`scripts/**`) — o gap sem índice-dono

> **Coluna `Invocador` — DERIVADA** (Trilha D · D0). Quem de fato executa o script:
> `ci` (workflow) · `npm` (package.json) · `agente` (`.claude/**`) · `script` (outro script) ·
> `php` (comando artisan/serviço). `—` = nenhum invocador encontrado; `— (só \`.test\`)` = o
> CI roda o **teste** dele, mas o script em si nunca é apontado para o repo; `?` = a varredura
> falhou (não medido — nunca leia como ausência).
>
> ⚠️ `—` **não** significa "apagar": one-shot (codemod, probe, PoC de migração) é órfão **por
> design**. O que é dívida é **medidor** órfão — a máquina existe, o teste prova que ela morde,
> e nada a executa. A matriz reporta o fato; a triagem é humana.
>
> **Coluna `Evidência` — DERIVADA** (Trilha D · D0). Prova de que a máquina MORDE, lida dos donos
> que já existem: `selftest` (par de fixtures boa/ruim rodado por `gate-selftest.mjs`) · `bite-log`
> (array do `design-gate-bites.mjs`, DR-2a da ADR 0336) · `test` (`*.test.mjs` irmão, invocado por
> algum workflow) · `test (fora do CI)` (o irmão existe mas nenhum YAML o roda — verde na máquina do
> dev, nunca no CI) · `—` (nenhuma prova).
>
> **Coluna `Documento` — DERIVADA.** É o **citador de maior precedência**, e não "o doc dono" —
> citação não prova posse. `+N` = quantos OUTROS docs também citam (é o aviso de que a escolha
> foi por regra, não por autoridade: com `+143` o dono provavelmente é outro, vá olhar).
> Precedência: `memory/decisions/` → canon raiz que o `CLAUDE.md` importa (`proibicoes.md` etc.) →
> `memory/requisitos/` → `memory/reference/` → `decisions/proposals/` → `.claude/**` → resto.
> Empate resolvido por: nome do arquivo carrega o nome da máquina → densidade de citação. Censo gerado
> (este arquivo, `_HOOKS-INDEX`, `_SKILLS-INDEX`, `AUTOMATIONS`, `PAINEL-SISTEMA`) **não conta** —
> ser listado por outro inventário não é estar documentado. `(só sessão/handoff · N)` = existe
> rastro histórico, mas **nenhum doc vivo** governa a máquina. `—` = nenhum doc a cita.

### 5.1 `scripts/governance/` — 111

| Script | Invocador | Evidência | Documento | Descrição (cabeçalho) |
|---|---|---|---|---|
| `adr-index-generate.mjs` | agente, ci, script | test | `memory/decisions/0317-maquina-revisao-adr-quando-rever-gatilhos.md` +30 | GERADOR determinístico do índice de ADR (modelo Log4brains). |
| `adr-proposto-parado.mjs` | ci, script | — | `memory/decisions/0378-execucao-mcp-jana-para-forja-ondas.md` +7 | sentinela: decisão PENDENTE que ninguém vê acaba não sendo feita. |
| `adr-supersede.mjs` | npm | — | `memory/decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md` +6 | supersessão ATÔMICA de ADR (modelo adr-tools/pyadr, ADR 0258). |
| `agent-corpus-counterfactual.mjs` | agente | test | `memory/requisitos/Governance/SPEC.md` +8 | QUANTO CUSTA descobrir se o corpus ajuda? |
| `agent-cost-per-pr.mjs` | agente, ci, script | test | `memory/proibicoes.md` +18 | CUSTO ESTIMADO POR PR do agente (USD/tokens · advisory). |
| `agent-pr-outcomes.mjs` | agente, ci, script | test | `memory/requisitos/Brief/BRIEFING.md` +6 | EVALS DE OUTCOME dos PRs do agente (DORA-style). |
| `agents-md-staleness.mjs` | ci, script | — | `memory/decisions/proposals/2026-07-23-sentinelas-staleness-prontidao-required.md` +3 | sentinela: o AGENTS.md ficou atrás do CLAUDE.md? |
| `anchor-content-check.mjs` | agente, ci, script | test | `memory/decisions/0327-anchor-content-required-emenda-0314.md` +24 | sentinela de CONTEÚDO da âncora de design. |
| `anchor-lint.mjs` | agente, ci, script | selftest | `memory/decisions/0303-anchor-lint-wired-testado-sa-a2-bis.md` +124 | parser da gramática anchor spec↔código (ADR 0273 · passo SA-A2 |
| `ancora-codigo-sync.mjs` | ci, script | — | `memory/requisitos/_DesignSystem/SDD-TEMPLATE.md` +5 | AUTO-SYNC da âncora doc→CÓDIGO (o mecanismo do Swimm, traduzido). |
| `baseline-tamper-guard.mjs` | ci, script | selftest | `memory/decisions/0331-anti-duplicacao-work-claim-gate.md` +43 | anti-grandfather (Gap 2 do blueprint SDD · ADR 0256/0258). |
| `blade-migration-census.mjs` | ci, script | — | `memory/proibicoes.md` +39 | o CONTRATO DE COMPLETUDE da ADR 0277, derivado da árvore. |
| `briefing-code-staleness.mjs` | agente, ci, npm, script | test | `memory/decisions/0348-briefing-coverage-required-emenda-0314.md` +26 | sentinela: a PORTA (BRIEFING.md) ficou atrás do CÓDIGO? |
| `brl-scan-diff.mjs` | ci, script | — | `memory/08-handoff.md` +3 | varre as LINHAS ADICIONADAS de um PR procurando valor BRL não-redigido. |
| `catalog-graph.mjs` | agente, ci, script | test | `memory/decisions/0370-module-surface-catalog-graph-required-emenda-0314.md` +29 | GERADOR determinístico do GRAFO TIPADO de módulos. |
| `charter-blueprint-pointers.mjs` | ci, script | — | `memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md` +5 | auditoria de PONTEIROS DE PROTÓTIPO dos Page Charters. |
| `charter-live-signal.mjs` | ci, script | selftest | `memory/decisions/0330-mapa-dos-niveis-estado-real-2026-07-constituicao.md` +18 | gate de SINAL pra charter `status: live` (proposta SDD 2026-06-24). |
| `charter-promote-signal.mjs` | script | — | `memory/proibicoes.md` +6 | passe REPETÍVEL de promoção draft→live guiado por SINAL de prod. |
| `charter-refs.mjs` | agente, ci, script | test | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +17 | catraca de integridade de refs dos Page Charters (ADR 0256). |
| `charter-us-lint.mjs` | ci | — | `memory/requisitos/Financeiro/SPEC.md` +22 | lint do campo canônico `related_us` nos Page Charters |
| `ciclo-completo.mjs` | ci | — | `memory/decisions/proposals/2026-07-11-maquina-nascimento-tela.md` +1 | GATE "a tela nasceu (e segue) COMPLETA?" (Constituição UI v2 · UI-0013). |
| `component-registry-check.mjs` | ci, script | bite-log + test | `memory/decisions/0373-screen-grades-ratchet-required-emenda-0314.md` +15 | sentinela de DRIFT do registro de componentes (Onda O2). |
| `cowork-mirror-freshness.mjs` | agente, ci, script | selftest + test | `memory/decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md` +17 | comparador de FRESCOR do espelho Cowork (v2, identidade canônica). |
| `cowork-ssot-guard.mjs` | ci | — | `memory/proibicoes.md` +8 | MÁQUINA de fonte única do protótipo de design. |
| `criar-tela.mjs` | agente, ci, npm, script | — | `memory/decisions/0351-sdd-from-source.md` +21 | GERADOR de tela que NASCE do Padrão de Tela (Constituição UI v2 · UI-0013). |
| `cron-watchdog.mjs` | ci | — | `memory/proibicoes.md` +17 | G6: heartbeat dos crons de governança (generaliza o auto-canário |
| `deadlink-gate.mjs` | ci, script | test | `memory/decisions/0347-deadlink-gate-required-emenda-0314.md` +41 | catraca de integridade referencial doc↔doc (links markdown mortos). |
| `design-code-map-check.mjs` | ci | test | `memory/requisitos/_Governanca/GRADE-MAPAS-VINCULOS-trava-frescor.md` +4 | sentinela da ponte design↔código PERSISTENTE (<tela>.map.json). |
| `design-gate-bites.mjs` | agente, ci | — | `memory/proibicoes.md` +9 | o BITE-LOG dos gates de design (DR-2a da ADR 0336). |
| `detect-handoff.mjs` | ci, npm | — | `memory/08-handoff.md` +1 | DETECTOR-EM-LOTE do G4 ("paste zip → 1 tarefa por tela"). |
| `detect-ui-drift.mjs` | ci, npm, script | test | `memory/decisions/0348-briefing-coverage-required-emenda-0314.md` +4 | M1: detector de MUDANÇA DE UI NÃO-DECLARADA (eixo de AUTORIZAÇÃO). |
| `doc-auto-relink.mjs` | ci, npm | — | `memory/requisitos/Infra/RUNBOOK-doc-auto-relink-orfaos.md` +4 | AUTO-RELIGADOR: dado um doc que MOVEU (A→B), religa os links. |
| `doc-freshness-score.mjs` | ci, script | — | `memory/requisitos/Governance/SPEC.md` +10 | RADAR de frescor POR DOC (score 0-100 · régua Dosu). |
| `doc-id-index.mjs` | ci, script | — | `memory/decisions/0351-sdd-from-source.md` +32 | GERADOR determinístico do índice `id → path atual` do corpus memory/. |
| `doc-id-stamp.mjs` | ci, npm | — | `memory/decisions/0353-maquina-evolucao-reguas-looping.md` +3 | STAMPER: adiciona `id:` no frontmatter dos docs SEM id. |
| `document-authority.mjs` | agente, ci, script | selftest | (só sessão/handoff · 1) | identidade documental compartilhada pelo hook e pelo CI. |
| `document-relocation-adversary.mjs` | agente, ci, npm, script | — | `memory/proibicoes.md` +4 | Validador read-only de planos de realocacao documental. |
| `document-relocation-classifier.mjs` | ci, npm | — | `memory/proibicoes.md` +2 | Classificador conservador de documentos. Produz plano v2 pinado ao HEAD; |
| `document-relocation-executor.mjs` | ci, npm, script | — | `memory/decisions/proposals/2026-07-23-referencia-id-estavel-doc-links.md` +3 | Executor transacional de planos documentais aprovados. Dry-run por padrao. |
| `documentation-loop.mjs` | agente, ci, npm, script | test | `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` +12 | recibo determinístico do ciclo documental. |
| `doneness-lint.mjs` | ci, script | selftest + test | `memory/decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md` +21 | catraca de fonte-única do "done-ness" de US (ADR 0302). |
| `ds-lint-selftest.mjs` | ci | — | — | LINT SELFTEST — controle-negativo das regras ds/* (as `no-restricted-syntax` |
| `ds-mirror-drift.mjs` | agente, ci, script | bite-log + test | `memory/decisions/0328-ds-transicao-congelado-para-vivo-git-ssot.md` +9 | SENTINELA de drift git ↔ espelho vivo (P3). |
| `dtcg-equivalence.mjs` | ci, npm | test | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +10 | onda DTCG (ancora: ADR 0239 DS git SSOT + ADR 0249 DS v6 + |
| `dup-detector.mjs` | ci | test | `memory/decisions/0331-anti-duplicacao-work-claim-gate.md` +28 | L3 (keystone) da trava anti-duplicação de trabalho entre sessões |
| `fact-anchor.mjs` | script | test | `memory/decisions/0349-fact-anchor-fail-emenda-0314.md` +15 | lógica PURA do Check T de memory-health.mjs (fact-anchor). |
| `feature-lint.mjs` | ci, npm, script | test | `memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md` +24 | valida o TRIO de feature (requirements.md + plan.md + tasks.md) em |
| `flip-required.mjs` | — | — | — | promove UM check advisory a required na branch protection de `main`. |
| `fluxo-morde.mjs` | ci, script | — | `memory/licoes-rejeitadas.md` | EXERCÍCIO DE FOGO DO FLUXO: o método detém um defeito, ou só o comenta? |
| `funcao-scorecard-calibracao.mjs` | script | test | `memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md` +5 | calibração NÃO-CIRCULAR do juiz funcao-scorecard. |
| `funcao-scorecard-humano.mjs` | npm | test | `memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md` +4 | template                 imprime o JSON cego que [W] preenche |
| `funcao-scorecard-outcome-probe.mjs` | ci, npm | test | `memory/decisions/proposals/2026-07-21-funcao-scorecard-validacao-por-outcome.md` +3 | PROTÓTIPO de validação-por-OUTCOME do funcao-scorecard. |
| `gate-selftest.mjs` | agente, ci, script | — | `memory/decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md` +66 | QUEM VIGIA OS VIGIAS (frente GT-G6, plano-mãe SDD 2026-06-12 §2 |
| `ghost-fix.mjs` | agente, script | test | `memory/requisitos/_Governanca/roadmap/P11-kl-e2-renames-reseed-distiller.md` +7 | codemod de ghost-names em memory/requisitos/** (Semana 0, frente KL). |
| `governance-audit.mjs` | script | — | `memory/requisitos/Jana/SPEC.md` +7 | DEPRECADO 2026-08-04: agregador SEM invocador e sem casa honesta |
| `governance-backlog-sync.mjs` | ci | — | `memory/decisions/0323-governanca-conhecimento-checks-s-w-gov-sync-story-dod.md` +3 | fecha o loop memory-health → backlog MCP. |
| `hook-bites.mjs` | agente, script | test | `.claude/agents/testador-de-maquinas.md` +6 | DEAD MAN'S SWITCH dos hooks de runtime (advisory, exit 0 sempre). |
| `hook-replay.mjs` | npm | test | `memory/08-handoff.md` +4 | testa hook contra TELEMETRIA REAL (advisory, exit 0 sempre). |
| `hooks-manifest-generate.mjs` | agente, ci | test | `memory/requisitos/Infra/RUNBOOK-branch-protection.md` +5 | GERADOR determinístico do manifesto de hooks (grade de réguas |
| `hue-canon-check.mjs` | agente, ci | test | `.claude/skills/pageheader-canon/SKILL.md` +1 | verificador da fonte única do hue primário (US-GOV-052 P32). |
| `junit-lanes.mjs` | ci, script | — | `memory/08-handoff.md` +2 | fonte ÚNICA e DERIVADA das lanes de CI que alimentam o manifesto por-UC |
| `knowledge-drift.mjs` | agente, ci, script | selftest | `memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md` +44 | primeira batida do "batimento" (ADR 0270 / sessão 2026-06-11). |
| `lapide-recheck.mjs` | agente, ci, script | test | `memory/decisions/0376-sec5-derivado-limite-no-contexto-arqueologia-na-fonte.md` +10 | re-verificação de FRESCOR das lápides §5 (memory/proibicoes.md, |
| `ledger-check.mjs` | agente, ci, script | selftest | `memory/decisions/0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md` +29 | enforcement do PROTOCOLO-REFUTADOR-BACKFILL (frente GT-G5, |
| `ledger-hash-chain.mjs` | ci | test | (só sessão/handoff · 2) | transparency-log (Rekor/Sigstore-style) sobre o |
| `maquinas-inventario.mjs` | agente, ci | test | `memory/requisitos/Infra/SPEC.md` +15 | DERIVA um índice único e legível de TODAS as "máquinas" |
| `mcp-drift-sentinel.mjs` | ci, script | — | `memory/decisions/proposals/2026-07-23-sentinelas-staleness-prontidao-required.md` +5 | sentinela EXTERNA de drift do MCP server (ADR 0256 + 0062). |
| `memory-health.mjs` | ci, script | selftest | `memory/decisions/0317-maquina-revisao-adr-quando-rever-gatilhos.md` +109 | sentinela de saúde da base de conhecimento (ADR 0256, Onda 1). |
| `module-group-resolve.mjs` | — (só `.test`) | test | `memory/decisions/proposals/2026-08-11-o-que-pode-existir-em-memory-requisitos.md` +1 | resolve O GRUPO DE MEMÓRIA de um módulo a partir da ÁRVORE. |
| `module-surface.mjs` | agente, ci, npm, script | test | `memory/decisions/0370-module-surface-catalog-graph-required-emenda-0314.md` +103 | GERADOR determinístico da "Superfície de código" de um módulo. |
| `negocio-vs-governanca-ratio.mjs` | agente, ci | test | `memory/decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md` +12 | o alarme anti-atrofia da inteligência de negócio. |
| `next-id.mjs` | agente, script | — | `memory/decisions/0304-alocacao-numero-ciente-trabalho-em-voo.md` +6 | aloca o próximo número de ADR/US **ciente de trabalho em voo** (ADR 0304). |
| `normalize-adr-frontmatter.mjs` | npm | — | `memory/decisions/0257-adr-status-lifecycle-kind-modelo-canonico.md` +1 | normaliza status/lifecycle de ADR pro enum canônico. |
| `onboarding-paths-check.mjs` | agente, script | — | `memory/governance/REALOCACAO-DOCUMENTAL.md` +5 | a CAMADA DETERMINÍSTICA do canário de onboarding. |
| `outcome-metrics.mjs` | agente, ci, script | test | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +6 | MEDIDOR DE ACEITAÇÃO do transporte Cowork→code (Onda O1). |
| `pages-colisao.mjs` | agente, ci | — | `memory/requisitos/_DesignSystem/RUNBOOK-migrar-pages-para-modulo.md` +4 | barra DUAS fontes declarando a mesma página Inertia. |
| `palette-generate.mjs` | ci | — | `memory/requisitos/_DesignSystem/PIPELINE-TOKENS.md` +2 | GERADOR determinístico da página de paleta de cor. |
| `permissao-renomeada-lint.mjs` | ci | — | — | barra o nome VELHO de permissão renomeada em linha NOVA. |
| `permission-drift.mjs` | ci | test | `memory/requisitos/Governance/SPEC.md` +9 | mede o drift entre permissão DECLARADA e permissão APLICADA. |
| `plan-health.mjs` | ci, script | — | `memory/decisions/0294-metodo-dual-track-shapeup-catraca.md` +19 | sentinela de PLANOS órfãos/podres (ADR 0294 Onda 1 · catraca da |
| `plans-index.mjs` | ci | test | `memory/decisions/0294-metodo-dual-track-shapeup-catraca.md` +11 | GERADOR determinístico do Índice de Planos Vivos (ADR 0294 + 0256). |
| `protection-drift.mjs` | agente, ci, script | selftest | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +44 | drift de branch protection + watchdog de staleness (GT-G4, |
| `pt-conformance.mjs` | ci, npm, script | bite-log | `memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md` +19 | VERIFICA que uma tela que DECLARA "herda PT-0X" tem de fato a |
| `rag-status-vocab-check.mjs` | ci | — | `memory/licoes-rejeitadas.md` +1 | detecta documento que ENTRA no índice do RAG mas |
| `reconcile-triplet.mjs` | ci, script | test | `memory/requisitos/Produto/_telas/produto-index-setor-matrix.md` +6 | gate de PARIDADE POR SETOR (3-way charter↔protótipo↔produção). |
| `ref-integrity.mjs` | ci, script | — | `memory/requisitos/Governance/SPEC.md` +2 | sentinela ADVISORY de integridade referencial rota↔código |
| `refuter-canary-check.mjs` | agente, script | selftest + test | `.claude/skills/reguas-do-sistema/SKILL.md` +1 | anti-Goodhart do LAYER DE AGENTE (chip orq-anti-goodhart · |
| `reguas-cross-model.mjs` | agente, script | test | `memory/reguas/README.md` +2 | braço de verificação CROSS-MODEL (cross-VENDOR) da grade de réguas. |
| `reguas-indexar.mjs` | agente, ci, npm | — | `memory/decisions/0353-maquina-evolucao-reguas-looping.md` +8 | Órgão 4 da máquina de réguas em looping (ADR proposta reguas-loop-maquina-evolucao). |
| `reguas-ledger-check.mjs` | ci | — | `memory/licoes-rejeitadas.md` +3 | o ledger de réguas contradiz a si mesmo? |
| `required-always-run.mjs` | ci | — | `memory/decisions/0373-screen-grades-ratchet-required-emenda-0314.md` +10 | todo context REQUIRED nasce em TODO PR? |
| `requisitos-status.mjs` | ci, npm, script | — | `memory/decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md` +62 | a CADEIA DE RASTREABILIDADE de um módulo, derivada e com STATUS. |
| `resolver-reclamacao.mjs` | ci, npm | — | `memory/decisions/proposals/2026-07-21-resolvedor-reclamacao-cadeia.md` +4 | resolvedor reclamação → cadeia de responsabilidade. |
| `sdd-flow.mjs` | npm | test | (só sessão/handoff · 1) | recibo estrutural da cadeia: |
| `sdd-output-lint.mjs` | ci, npm | — | `memory/decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md` +2 | mede a QUALIDADE do artefato que o agent `sdd-from-source` (ADR 0351) produz. |
| `sdd-scorecard.mjs` | agente, ci, script | selftest | `memory/decisions/0279-sdd-medir-governar-floor-nightly.md` +65 | agregador do scorecard SDD (GT-G2, Semana 0 do plano |
| `sec5-derive.mjs` | ci | — | `memory/decisions/0376-sec5-derivado-limite-no-contexto-arqueologia-na-fonte.md` +4 | o §5 do `memory/proibicoes.md` passa a ser DERIVADO. |
| `seed-tela.mjs` | script | — | (só sessão/handoff · 2) | EMPACOTADOR DE SEED (G1 do padrão "1 clique → sessão limpa por tela"). |
| `selftest-registry-check.mjs` | agente, ci, script | — | `memory/requisitos/_Governanca/roadmap/P15-done-comportamento-evidencia-alvo.md` +13 | P15 entrega 3: teste .mjs órfão de workflow (advisory). |
| `service-scorecard.mjs` | ci | test | `memory/LICOES_CODE.md` +5 | SCORECARD de SINAIS-VIVOS por serviço/módulo (estilo Cortex). |
| `shipped-log-generate.mjs` | ci | test | `memory/requisitos/Brief/BRIEFING.md` +6 | generate.mjs v2 — porta de saída do loop (estende ADR 0294). |
| `skills-index-generate.mjs` | agente, ci, script | test | `memory/decisions/proposals/2026-08-03-incorporar-boost-guidelines-skills.md` +5 | GERADOR determinístico do índice de skills (US-GOV-052 P31). |
| `spec-lib-staleness.mjs` | ci | test | `memory/decisions/proposals/2026-07-23-sentinelas-staleness-prontidao-required.md` | sentinela: o DOC que descreve uma lib externa ficou |
| `system-map.mjs` | agente, ci, script | — | `memory/proibicoes.md` +50 | a MATRIZ gerada do painel do sistema oimpresso. |
| `tasks-index-generate.mjs` | ci, script | — | `memory/requisitos/_BACKLOG-GENERATED.md` +8 | GERADOR determinístico de BACKLOG + CHANGELOG indexados. |
| `tema-owner.mjs` | agente | test | `memory/proibicoes.md` +5 | detector ADVISORY de DONO-DE-TEMA por sobreposição de ENTIDADE. |
| `test-lane-coverage.mjs` | ci, script | — | `memory/requisitos/Jana/AUDIT-GAPS-2026-08-10.md` +11 | quais testes EXISTEM × quais o CI realmente EXECUTA. |
| `uc-sem-lane.mjs` | ci | — | (só sessão/handoff · 2) | UC com o id no TÍTULO de um teste que LANE NENHUMA executa. |
| `ui-impact.mjs` | ci | — | `memory/decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md` +5 | Fonte única do skip-as-pass do visual-regression. |
| `visual-comparison-staleness.mjs` | ci, script | — | `memory/decisions/0329-doutrina-documentacao-de-processo-executavel.md` +8 | sentinela: o `<tela>-visual-comparison.md` ficou atrás da TELA? |
| `worktree-janitor.mjs` | ci, npm | — | — | Faxineiro de worktrees — classifica worktree MORTO vs VIVO por ORÁCULO, nunca por heurística. |

### 5.2 `scripts/tests/` — 11

| Script | Invocador | Evidência | Documento | Descrição (cabeçalho) |
|---|---|---|---|---|
| `coverage-compute.mjs` | ci, script | test | `memory/requisitos/_Governanca/roadmap/P07-instrumentar-pcov-ci-coverage.md` +3 | write-side do coverage_pct (SDD P07 · ADR 0275 §2 fonte |
| `floor-compute.mjs` | ci, script | test | `memory/requisitos/_Governanca/roadmap/P04-burn-down-ate-nightly-verde.md` +9 | write-side do floor (ADR 0279 Opção A · PR-2 · US-GOV-018). |
| `foundation-ratchet.mjs` | agente, ci, script | selftest + test | `memory/decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md` +38 | marcadores `legacy-quarantine` (burn-down: subir = regressão) |
| `junit-corpus.mjs` | ci | test | `memory/LICOES_CODE.md` | agrega N summaries `junit-summary/v1` na DISTRIBUIÇÃO de assertions |
| `junit-summary.mjs` | agente, ci, script | test | `memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md` +12 | sumário JSON por arquivo de teste a partir de JUnit XML (PHPUnit/Pest). |
| `nightly-diff.mjs` | ci | test | `memory/requisitos/Governance/SPEC.md` | tripwire de regressão QUALITATIVA do nightly (ROADMAP-SDD P15). |
| `ragas-trend-compute.mjs` | ci, script | test | `memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md` +1 | write-side do trend do RAGAS real (ADR 0318 + pattern |
| `shards-merge.mjs` | ci, script | test | `memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md` +2 | funde os summaries junit POR SHARD numa medição da noite (SDD P04 |
| `shards-plan.mjs` | agente, ci, script | selftest + test | `memory/requisitos/Infra/RUNBOOK-ct100-fullsuite.md` +17 | particiona a suíte Pest em N shards POR DIRETÓRIO (determinístico). |
| `snap-diff.mjs` | — (só `.test`) | test | `memory/licoes-rejeitadas.md` +1 | LÊ o que mudou entre duas baselines de pixel (`.snap` do Pest Browser). |
| `visreg-clock-bite.mjs` | ci | — | — | TEST do congelamento do relógio do navegador (gate visual-regression). |

### 5.3 `scripts/` (raiz) — 35

| Script | Invocador | Evidência | Documento | Descrição (cabeçalho) |
|---|---|---|---|---|
| `a11y-ratchet.mjs` | ci, npm | — | (só sessão/handoff · 2) | scripts/a11y-ratchet.mjs — acessibilidade como categoria DETERMINÍSTICA PROTEGIDA. |
| `adversario-intencao-fluxo.mjs` | ci | test | — | Adversário: procura contraprovas ao contrato, sem aceitar justificativa em prosa. |
| `auditar-intencao-fluxo.mjs` | ci, script | test | — | Catraca estática: a prosa declara a intenção, mas não mascara evidência ausente. |
| `bundle-lint.mjs` | ci, npm | test | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +5 | esteira ≠ armazém (régua 6 da memória de proveniência). |
| `casos-coverage-guard.mjs` | agente, ci, npm, script | — | `memory/decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md` +65 | scripts/casos-coverage-guard.mjs — Gate G-1 (trio-de-tela) + G-2 (rastreabilidade caso↔teste) |
| `casos-results-collect.mjs` | ci, npm, script | — | `memory/08-handoff.md` +4 | scripts/casos-results-collect.mjs — Coletor de test-results → manifesto por-UC (Salto #2, |
| `components-tree-guard.mjs` | agente, ci, npm, script | — | `memory/decisions/0272-arvore-componentes-canonica.md` +9 | scripts/components-tree-guard.mjs — árvore canônica de Components/ (allowlist + convenção _components) |
| `conformance-gate.mjs` | ci, npm, script | — | `memory/decisions/0263-identidade-cor-gate-bloqueante.md` +16 | Determinístico, sem browser, sem dependência. Roda em CI (exit≠0 = bloqueia merge) E local. |
| `contrato-de-tela.mjs` | ci, npm, script | test | `memory/decisions/0290-fidelity-lock-v0-recusado.md` +14 | Gate "Contrato de Tela" (a perna de fidelidade visual do trio-de-tela). |
| `css-size-baseline.mjs` | ci, npm, script | — | `memory/decisions/0311-frescor-consolidado-em-sla-escala-temporal-unica.md` +2 | scripts/css-size-baseline.mjs — ratchet de TAMANHO do CSS (anti-regrowth). |
| `design-identity-grade.mjs` | ci | — | `memory/decisions/0254-design-identity-grade-deterministico.md` +7 | GRADE de identidade visual DETERMINÍSTICO (ADR 0254). |
| `design-spec-gen.mjs` | ci, npm | — | `memory/decisions/0255-contrato-view-deterministico-charter-design-spec.md` +4 | tela (componentes/tokens/layout) é PURA e DERIVÁVEL, mas era julgada por LLM |
| `domain-dict-guard.mjs` | ci, npm, script | — | `memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md` +16 | scripts/domain-dict-guard.mjs — Gate G-4 (dicionário de domínio) da Governança executável (ADR 0264). |
| `ds-canon-color-guard.mjs` | ci, npm | — | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +6 | scripts/ds-canon-color-guard.mjs — catraca: a camada canônica NÃO usa paleta crua |
| `ds-ledger.mjs` | ci, npm | — | `memory/requisitos/_DesignSystem/CHANGELOG.md` +8 | scripts/ds-ledger.mjs — Ledger de Conformidade DS (censo Onda 0, por tela). |
| `ds-report.mjs` | ci, npm, script | — | `memory/decisions/0240-task-ledger-git-native-cowork-code.md` +6 | scripts/ds-report.mjs — placar de adoção do Design System (ds/* por regra × módulo) |
| `dsih-gate.mjs` | ci, script | — | `memory/decisions/0283-handoff-loop-zero-paste.md` | porque NENHUM gate mordia CONTEUDO em .tsx (so canal: lint/build/conformance). |
| `eslint-baseline.mjs` | ci, npm, script | selftest | `memory/decisions/0254-design-identity-grade-deterministico.md` +8 | scripts/eslint-baseline.mjs — Onda 1.2 (ADR 0209) |
| `foundation-guard.mjs` | ci, npm, script | — | `memory/decisions/0255-contrato-view-deterministico-charter-design-spec.md` +11 | Determinístico, sem browser, sem dependência. Roda em CI (exit≠0 = bloqueia merge) E local. |
| `generate-dxt.js` | script | — | `memory/08-handoff.md` | Gera arquivo .dxt (Claude Desktop Extension) para membros do time oimpresso. |
| `handoff-integrity-guard.mjs` | ci, npm, script | test | `memory/requisitos/Governance/SPEC.md` +6 | scripts/handoff-integrity-guard.mjs — catraca de Integridade do Handoff (PROCESSO_MEMORIA_CC.md §16 · IT8). |
| `layout-primitives-guard.mjs` | ci, npm, script | selftest | `memory/requisitos/Forja/RUNBOOK-gantt.md` +6 | scripts/layout-primitives-guard.mjs — enforcement da ADR 0253 (primitivos de layout) |
| `no-mock-in-prod.mjs` | ci, npm, script | — | `memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md` +5 | scripts/no-mock-in-prod.mjs — Frente 6 (plano anti-duplicacao 2026-06-06) |
| `pageheader-migration-guard.mjs` | ci, npm, script | — | `memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md` +1 | scripts/pageheader-migration-guard.mjs — F4 do roadmap de convergência UI (MANUAL-CSS-JS.md §5) |
| `perf-static-guard.mjs` | script | — | `memory/requisitos/KB/SPEC.md` +7 | scripts/perf-static-guard.mjs — catraca da Onda 4 lente 5b (AUDITORIA-PERFORMANCE-2026-07). |
| `reuse-index.mjs` | agente, ci, npm, script | — | `memory/decisions/0255-contrato-view-deterministico-charter-design-spec.md` +10 | índice escrito à mão APODRECE (ADR 0239: git=SSOT, derivado>escrito). Este índice é REGENERADO do código a cada |
| `scheme-gate.mjs` | ci | — | — | Por que existe: o red-team adversarial de 2026-06-17 mostrou que NENHUM gate mordia CONTEUDO |
| `scorer-sync-check.mjs` | ci, npm | — | (só sessão/handoff · 2) | scripts/scorer-sync-check.mjs — guarda a SINCRONIA dos regex entre as duas implementações. |
| `sells-cowork-dead-css.mjs` | — | — | `memory/requisitos/Sells/DEAD-CSS-sells-cowork.md` | gate conta a cor-crua DESSAS regras mortas como se fosse dívida viva |
| `smoke-veredito-ledger.mjs` | npm | — | — | Smoke / acceptance harness do programa veredito-ledger. |
| `stylelint-baseline.mjs` | ci, npm, script | selftest | `memory/decisions/0310-tokens-semanticos-dominio-frescor-sla-kind-canal.md` +1 | scripts/stylelint-baseline.mjs — G5 anti-drift CSS (ADR 0209 pattern) |
| `uc-derive.mjs` | — | — | `memory/decisions/proposals/2026-06-24-eixos-de-orfao.md` +4 | scripts/uc-derive.mjs — Auto-derivador de vínculo UC↔teste (PoC read-only, determinístico) |
| `visreg-flows-lint.mjs` | ci, npm, script | — | — | Catraca do contrato de fluxos visuais: cenário sem viewport, ação ou evidência não entra no CI. |
| `visreg-sells-lint.mjs` | ci, npm | — | — | Catraca do contrato de fluxos visuais de Sells/Create: cenário sem viewport, ação ou |
| `visreg-states-lint.mjs` | ci, npm, script | — | `memory/decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md` +2 | charter `states:` ⇄ manifesto do gate L2 (estados isolados do VRT). |

## 6. Baselines & JSON de estado

> **Coluna `Leitor` — DERIVADA** (o eixo "invocador" desta tabela): baseline não é executado, é
> LIDO — então quem o executa é quem o consome. `ci` (workflow) · `script` · `agente` (`.claude/**`).
> `—` = **nenhum consumidor no versionado**: o arquivo é mantido e ninguém o lê. Evidência não é
> derivável aqui (medido: 0 de 46 — baseline não tem fixture própria; quem tem é a catraca que o usa).

| Arquivo | Leitor | Documento | `_meta` / propósito |
|---|---|---|---|
| `governance/adr-alias-map.json` | agente, ci, script | `memory/08-handoff.md` +4 | (baseline/estado) |
| `governance/adr-collisions-baseline.json` | script | — | Colisões de número de ADR — catraca anti-bifurcação (só encolhe). O detector já existia (adr-index-generate.mjs lista as colisões desde sempre); este … |
| `governance/adr-tombstones.json` | ci, script | `memory/licoes-rejeitadas.md` +2 | (baseline/estado) |
| `governance/anchor-entry-baseline.json` | ci, script | `memory/decisions/proposals/2026-07-02-baseline-tamper-guard-required.md` +3 | anchor entry/covers GRANDFATHER — US legadas isentas (ratchet só-desce · ADR 0275 advisory→required por calendário) |
| `governance/blade-migration-baseline.json` | script | — | Censo de migração Blade→React — catraca só-desce por escopo (ADR 0277 contrato de completude) |
| `governance/charter-refs-baseline.json` | ci, script | `memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md` +2 | (baseline/estado) |
| `governance/cron-vermelho-esperado.json` | script | (só sessão/handoff · 1) | (baseline/estado) |
| `governance/deadlink-baseline.json` | ci, script | `memory/decisions/0347-deadlink-gate-required-emenda-0314.md` +3 | (baseline/estado) |
| `governance/dependency-direction-baseline.json` | ci, script | — | (baseline/estado) |
| `governance/doc-id-index.json` | ci, script | `memory/decisions/0351-sdd-from-source.md` +29 | (baseline/estado) |
| `governance/doneness-baseline.json` | ci, script | — | doneness GRANDFATHER — conflitos status×âncora legados isentos (ratchet só-desce · ADR 0302/0275 advisory→required por calendário) |
| `governance/ds-ledger.json` | ci, script | `memory/decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md` +2 | (baseline/estado) |
| `governance/dup-hot-paths.json` | ci, script | — | (baseline/estado) |
| `governance/ghost-rename-map.json` | agente, script | `memory/requisitos/_Governanca/roadmap/P11-kl-e2-renames-reseed-distiller.md` +8 | (baseline/estado) |
| `governance/hue-canon.json` | agente, ci, script | — | Fonte única do hue primário universal (US-GOV-052 P32). O hue vivia em 3 mapas divergentes — pageheader-canon chegou a ter check aprovando o 145 morto… |
| `governance/jana-ragas-baseline.json` | ci | `memory/requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md` | Baseline RAGAS canary Jana — recriado via workflow_dispatch jana-ragas-canary.yml (US-COPI-116). Não editar à mão; usar update_baseline=true no dispat… |
| `governance/jana-ragas-real-baseline.json` | agente, script | `memory/decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md` +1 | (baseline/estado) |
| `governance/ledger-checkpoints.json` | script | — | (baseline/estado) |
| `governance/module-coupling-baseline.json` | ci, script | — | (baseline/estado) |
| `governance/module-grades-baseline.json` | agente, ci, script | `memory/decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md` +8 | (baseline/estado) |
| `governance/module-group.json` | script | `memory/08-handoff.md` +1 | (baseline/estado) |
| `governance/module-table-coupling-baseline.json` | script | — | (baseline/estado) |
| `governance/multi-tenant-scope-baseline.json` | — | (só sessão/handoff · 2) | (baseline/estado) |
| `governance/prod-flags.json` | ci, script | `memory/requisitos/_DesignSystem/SPEC.md` +3 | (baseline/estado) |
| `governance/required-checks-baseline.json` | agente, ci, script | `memory/decisions/0361-errata-0354-teammcp-pest-required-nunca-executado.md` +72 | Required checks de main CONGELADOS — GT-G4 (plano 2026-06-12 §2 GARANTIDA) |
| `governance/reseed-meilisearch-manifest.json` | — | `memory/requisitos/_Governanca/roadmap/P11-kl-e2-renames-reseed-distiller.md` | (baseline/estado) |
| `governance/route-hits.json` | script | `memory/requisitos/_DesignSystem/SPEC.md` +9 | (baseline/estado) |
| `governance/sdd-scorecard-baseline.json` | agente, ci, script | `memory/requisitos/_Governanca/roadmap/P13-promover-gt-g3-required.md` +3 | SDD scorecard baseline v1 — meta-catraca GT-G3 (plano 2026-06-12 §2 GARANTIDA + §4 Semanas 1-2) |
| `governance/sdd-scorecard.json` | agente, ci, script | `memory/decisions/0303-anchor-lint-wired-testado-sa-a2-bis.md` +20 | (baseline/estado) |
| `governance/sdd-verification-ledger.json` | agente, ci, script | `memory/decisions/0319-product-truth-stream-adversario-modulo-analise.md` +3 | (baseline/estado) |
| `config/a11y-baseline.json` | ci, script | — | (baseline/estado) |
| `config/css-size-baseline.json` | ci, script | (só sessão/handoff · 1) | (baseline/estado) |
| `config/design-identity-baseline.json` | ci, script | — | (baseline/estado) |
| `config/ds-handoff-baseline.json` | — | — | (baseline/estado) |
| `config/eslint-baseline.json` | ci, script | `memory/decisions/0254-design-identity-grade-deterministico.md` +5 | (baseline/estado) |
| `config/handoff-integrity-baseline.json` | ci, script | — | (baseline/estado) |
| `config/pageheader-shared-baseline.json` | agente, ci, script | `memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md` | (baseline/estado) |
| `config/stylelint-baseline.json` | ci, script | — | (baseline/estado) |
| `config/ui-lint-baseline.json` | ci | `memory/decisions/0209-eslint-9-flat-config.md` +3 | (baseline/estado) |
| `scripts/casos-coverage-baseline.json` | ci, script | `memory/decisions/proposals/2026-08-04-templates-8-artefatos-ANEXO.md` +11 | casos:check (ADR 0264 G-1 trio + G-2 rastreabilidade + G-5 metadata + G-6 frescor + G-7 status derivado) |
| `scripts/casos-test-results.json` | agente, ci, script | `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` +4 | casos status derivado (ADR 0264 G-7 — Status por UC vem do veredito real do teste) |
| `scripts/domain-dict-baseline.json` | ci, script | (só sessão/handoff · 1) | dominio:check (ADR 0264 G-4 — dicionário de domínio ⇔ enum de migration + código, Salto #3) |
| `scripts/layout-primitives-baseline.json` | ci, script | (só sessão/handoff · 1) | Contagem de flex/grid solto POR ARQUIVO. Gate falha se um arquivo AUMENTAR ou se arquivo novo nascer com flex/grid solto. |
| `scripts/no-mock-baseline.json` | ci, script | (só sessão/handoff · 2) | Contagem por REGRA. Gate falha so se uma regra AUMENTAR vs este baseline. |
| `scripts/perf-static-baseline.json` | script | `memory/governance/AUDITORIA-PERFORMANCE-2026-07.md` +2 | perf-static-guard (Onda 4 lente 5b — AUDITORIA-PERFORMANCE-2026-07, ratchet advisory) |
| `scripts/reuse-duplicates-baseline.json` | agente, ci, script | `memory/decisions/0272-arvore-componentes-canonica.md` +2 | (baseline/estado) |

> Total baselines JSON em governance/+config/+scripts: 46 · (mais ~5 dot-baselines na raiz + fixtures em tests/).
