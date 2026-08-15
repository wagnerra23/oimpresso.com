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

## 1. Workflows / Gates de CI — 123 (44 contexts required)

| Workflow | Descrição |
|---|---|
| `a11y-axe-gate.yml` | A11y axe runtime (jsdom · componentes canon) |
| `a11y-gate.yml` | A11y ratchet (acessibilidade = categoria protegida) |
| `adr-index-gate.yml` | ADR Index Gate — ADR 0258 |
| `adr-lint.yml` | ADR frontmatter lint |
| `agent-cost-per-pr.yml` | Agent cost per PR (advisory · custo USD estimado por PR do agente · unidade = SESSÃO do JSONL local atribuída por branch==headRefName ou citação /pull/N · cobertura de AL… |
| `agent-pr-outcomes.yml` | Agent PR outcomes (advisory · DORA dos PRs do agente · change-failure-rate + accept-rate + time-to-merge via gh pr list · weekly schedule + dispatch · card #0 grade-das-r… |
| `anchor-content-required.yml` | Ancora de design nao-shell — REQUIRED (F2/F6 revisão adversarial 2026-07-08: related_prototype do charter != shell/fantasma; anchor-content-check --check hard-fail; emend… |
| `anchor-drift.yml` | Anchor Drift — lint spec↔código ADR 0273 + entry/covers (0303) + doneness (0302) + charter status:live, diff-aware no PR e full-tree no cron semanal (SA-A2/A3). Enforceme… |
| `arquivos-pest.yml` | Arquivos · Pest (MySQL) — audit-log/download/enum rodam no MySQL real (skip no sqlite = verde mente); catraca allowlist verde |
| `baseline-tamper-guard.yml` | Baseline tamper-guard (anti-grandfather · afrouxar baseline + código no mesmo PR · ADR 0256/0258 · Gap-2 blueprint SDD) |
| `block-brl-values-selftest.yml` | block-brl-values selftest (meta-teste do hook Tier-0 dinheiro block-brl-values-in-memory.mjs — bite/release do detector via --selftest + registration test do settings.jso… |
| `briefing-code-staleness.yml` | Staleness reporters (advisory · 6 eixos: BRIEFING×código briefing-code-staleness.mjs · visual-comparison×tela visual-comparison-staleness.mjs · ADR pendente adr-proposto-… |
| `briefing-coverage-required.yml` | Cobertura BRIEFING (required) — modulo backend (Modules/<X>/ com dir requisitos/<X>/) sem BRIEFING.md falha o merge. Sinal = EXISTENCIA (isBriefingCoverageGap), nao data … |
| `brl-scan.yml` | BRL scan (advisory · valor monetário em linha NOVA do PR · diff-only · arquivos + PR body + commit subjects) |
| `casos-gate.yml` | Casos-coverage ratchet (trio-de-tela + caso↔teste) |
| `casos-results-publish.yml` | Casos results publish — colhe o JUnit das lanes (que já emitem --log-junit) e aterrissa o veredito por-UC em scripts/casos-test-results.json, fonte do G-7 do casos-gate; … |
| `catalog-graph.yml` | Catalog graph — prova que memory/governance/catalog.json é a derivada determinística dos SCOPE.md + SUPERFICIE.md Classe B, sem referências estruturais penduradas; exerci… |
| `charter-refs-gate.yml` | Charter refs (catraca charter_refs_broken ≤ teto · require-safe · US-GOV-043 · ADR 0256) |
| `charter-us-gate.yml` | Charter ↔ US join (advisory · related_us nos Page Charters · charter-us-lint.mjs --check diff-aware no PR + cobertura full-tree no cron) |
| `ci.yml` | CI |
| `ciclo-completo.yml` | ciclo-completo (advisory) — catraca do CICLO-DE-TELA por tela: quantas telas roteadas têm o conjunto obrigatório (charter + Padrão de Tela declarado + pt-conforme via pt-… |
| `components-tree-guard.yml` | Components tree guard (árvore canônica de Components/) |
| `composer-lock-sync.yml` | Composer lock sync |
| `compras-pest.yml` | Compras · Pest (MySQL) |
| `contrato-de-tela.yml` | Contrato de Tela — fidelidade visual do trio-de-tela (advisory na adoção · RUNBOOK-contrato-de-tela.md) |
| `deadlink-gate.yml` | deadlink-gate (ratchet · integridade referencial) — catraca de integridade referencial doc↔doc: links markdown internos mortos no corpo VIVO (memory/** menos história app… |
| `deploy.yml` | Deploy to Hostinger |
| `design-coverage.yml` | ds-design-coverage (advisory) — catraca da cobertura de DESIGN por tela: quantas telas DECLARAM a fonte de design (protótipo via related_prototype ou 'segue DS' explícito… |
| `design-identity-gate.yml` | Design Identity Gate (soft) |
| `design-memory-gate.yml` | Design-memory gate (advisory · FUNDIDO ADR 0314 F2 de 3 workflows: registry-check ex-component-registry O2 + gates ex-design-memory-gates §8/§15 + prove ex-dtcg-equivalen… |
| `design-return-gate.yml` | Design return gate (§10.2 pós-merge) |
| `design-spec-gate.yml` | Design-spec por-tela (contrato estrutural determinístico) |
| `detect-ui-drift.yml` | detect-ui-drift — M1 (advisory) — eixo de AUTORIZAÇÃO: quando uma .tsx de tela muda num PR, exige sinal FRESCO no mesmo PR (divergence_from_blueprint com razão real no ch… |
| `devcontainer-firewall.yml` | devcontainer firewall (egress default-deny · chip C7) — prova que o firewall do devcontainer do agente MORDE (corta host fora da allowlist) e SOLTA (deixa passar github/a… |
| `dominio-gate.yml` | Dominio-dict ratchet (coerência de domínio) |
| `ds-gate.yml` | DS gate (fusão F1 — cor/UI/css/index/bundle/scorer · ADR 0314) |
| `ds-mirror-drift.yml` | ds-mirror-drift (advisory) — sentinela de drift git↔espelho do Design System: compara os _generated-*.css do git contra o snapshot commitado do espelho claude.ai/design (… |
| `ds-token-version.yml` | ds-token-version — semver + changelog do pacote de tokens do DS: versiona a superfície dos _generated-*.css (296 tokens/4 escopos) por fingerprint sha256; `--check` sai ≠… |
| `ds-tokens-build-sync.yml` | ds-tokens-build-sync — ANCORA a premissa do loop diff-first (git = fonte verdadeira): `scripts/design-sync/ds-tokens-build-sync.mjs --check` builda os *.tokens.json num t… |
| `dup-detector-gate.yml` | Dup detector (advisory · L3 anti-duplicação de trabalho entre sessões paralelas · arquivo hot-path em PR aberto sem Dedup-ack · proposta anti-duplicacao-work-claim-gate) |
| `e2e-gate.yml` | E2E Playwright (UCs críticos) — G-3 |
| `eslint-gate.yml` | ESLint 9 (ADR 0209) |
| `essentials-pest.yml` | Essentials · Pest (MySQL) |
| `estoque-pest.yml` | Estoque · Pest (MySQL) — movimentação de saldo (venda/compra/devolução) roda no MySQL real; skip no sqlite = verde mente |
| `exposicao-tier0-sentinel.yml` | Exposição Tier-0 — sentinela de cadência (Onda 0c · débito Tier-0 por tela dinheiro/estoque/PII/fiscal × cobertura de comportamento · cron semanal + tendência + issue dur… |
| `fin-hero-gate.yml` | Fin Hero Gate — KPI hero claro (anti-regressão Onda 28) |
| `fin-subnav-gate.yml` | Fin SubNav Gate — abas seguem a entry do active (anti-regressão ADR 0180 split) |
| `financeiro-pest.yml` | Financeiro · Pest (MySQL) |
| `force-clean-rebuild-trigger.yml` | Force Clean Rebuild (one-shot) |
| `forja-pest.yml` | Forja · Pest (MySQL) — rotas /forja executam de verdade (em sqlite a stack UltimatePOS só SKIPa, e skip vira veredito `skip` no manifesto por-UC, nunca `pass`); catraca a… |
| `forja-shortcuts-gate.yml` | Forja Shortcuts — atalhos do Board (overlay `?` · Enter · J/K/E/A anti-regressão) |
| `foundation-ratchet.yml` | Foundation ratchet (advisory · catracas só-diminui da fundação de testes — SDD FV-Q1) |
| `gate-selftest.yml` | Gate selftest — quem vigia os vigias: cada catraca de governança contra fixtures boa/ruim versionadas (SDD GT-G6). Enforcement POR JOB: consultar governance/required-chec… |
| `gitleaks-history.yml` | Gitleaks histórico completo (4º portão four-gate · full-history detect · advisory · ADR 0215) |
| `governance-drift.yml` | Governance Drift Framework — ADR 0216 |
| `governance-gate-umbrella.yml` | Governance Gate (umbrella) |
| `governance-gate.yml` | Governance Gate (pre-merge) |
| `governance-script-tests.yml` | Governance script tests (advisory · scripts/governance/*.test.mjs — Onda 1; inclui agent-corpus-counterfactual.test.mjs, chip C1 da grade 2026-07-17: prova por Monte Carl… |
| `guards-meta-gate.yml` | Guards meta-gate (vitest · casos + domínio · funde casos-meta + dominio-meta) |
| `handoff-integrity.yml` | Handoff Integrity (advisory · fila ↔ prompts) |
| `handoff-scope-guard.yml` | Handoff Scope Guard (files_json · escopo duro do handoff de design, ADR 0283 Fase 0) |
| `handoff-sign-submit.yml` | Handoff Sign & Submit (on-push · assina HMAC + POST handoff-submit → pending; advisory · PR-6b ADR 0283) |
| `infra-contract-required.yml` | Infra Contract Required |
| `jana-conversas-gate.yml` | Jana Conversas — histórico do chat (filtro real · J/K · ⌘⇧H · aria-live) |
| `jana-logica-pura-pest.yml` | Jana lógica pura Pest (event-time + histórico + audit-chain · funde 3 lanes Unit · ADR 0294/0295) |
| `jana-pest.yml` | Jana · Pest (MySQL) |
| `jana-ragas-canary.yml` | Jana RAGAS Canary (daily 06:00 UTC) |
| `jana-ragas-gate.yml` | Jana RAGAS Eval Gate |
| `jana-recall-eval.yml` | Jana recall-eval (mock gate · golden set determinístico · advisory → required ADR 0275 · P12 roadmap SDD) |
| `jscpd-gate.yml` | jscpd ratchet (anti-duplicação de bloco copy-paste) |
| `kb-pest.yml` | KB · Pest (MySQL) |
| `knowledge-ghost-gate.yml` | Knowledge Ghost Gate (catraca anti-ghost · baseline por módulo · ADVISORY — KL-A2) |
| `layout-primitives-guard.yml` | Layout primitives guard (flex/grid solto) |
| `mcp-drift-sentinel.yml` | MCP Drift Sentinel — servido vs main (ADR 0256 + 0062) |
| `memory-health.yml` | Memory Health — ADR 0256 |
| `memory-schema-gate.yml` | Memory schema gate (ONDA 5 S1 · FUNDIDO ADR 0314 F2: matrix AJV/frontmatter + sub-checks do corpo via validate-memory-schema.sh, ex-memory-schema-gate-extended D6 #4) |
| `module-grades-gate.yml` | Module Grades Gate (anti-regressão) |
| `module-surface.yml` | Module surface — guarda o índice GERADO de arquivos por módulo (memory/requisitos/<Mod>/SUPERFICIE.md) contra a árvore: self-test HARD + `--all --check` (drift real; obri… |
| `modules-pest.yml` | Modules Pest |
| `multi-tenant-gate.yml` | Multi-tenant gate |
| `mutation-gate.yml` | Mutation Gate (advisory) |
| `mv-metabolismo.yml` | MV metabolismo (batimento nightly do Módulo Vivo · stream MV · sinais vitais + proposta de batch via auto-PR SEM auto-merge — merge Wagner = aprova batch) |
| `negocio-vs-governanca-ratio.yml` | Ratio negócio × governança (sentinela anti-atrofia) — mede o FLUXO de merges (first-parent, janela 4 semanas) classificado em NEGÓCIO (A+B) × GOVERNANÇA-META (C) × INFRA,… |
| `nfebrasil-pest.yml` | NfeBrasil · Pest (MySQL) — testes fiscais rodam no MySQL real (skip no sqlite = verde mente); JUnit alimenta o verde@ do gate de entrada G1b |
| `no-mock-gate.yml` | No-mock-in-prod ratchet (stub/mock em controller) |
| `officeimpresso-pest.yml` | Officeimpresso · Pest (MySQL) |
| `outcome-metrics.yml` | Outcome metrics (advisory · medidor de aceitação Cowork→code · rework/revert/first-pass via SYNC_LOG proxy + git Pages/*.tsx · Onda O1) |
| `pageheader-gate.yml` | PageHeader migration guard (F4 · congela header antigo) |
| `pageheader-tabs-fidelity-gate.yml` | PageHeaderTabs Fidelity — aba ativa fiel ao protótipo (radius 0 · accent · font 600) |
| `phpstan-baseline-regen.yml` | PHPStan baseline regen (manual) |
| `phpstan-gate.yml` | PHPStan / Larastan (ADR 0208) |
| `plan-health-gate.yml` | Plan Health Gate (advisory · planos órfãos/podres · sentinela plan-health.mjs --check · ADR 0294 Onda 1) |
| `ponto-pest.yml` | Ponto · Pest (MySQL) |
| `pr-critic-precisao.yml` | pr-critic precisão (advisory · mede a PRÓPRIA precisão do pr-critic · taxa-de-ação dos achados: o humano mexeu no arquivo apontado depois do comentário? + first-pass + po… |
| `pr-critic.yml` | pr-critic contrato (advisory) — critic adversarial de PR ancorado em contrato: em PRs tocando resources/js/Pages/** ou Modules/**, roteia o diff pros contratos (charter/c… |
| `prompt-injection-corpus.yml` | prompt-injection corpus (red-team do agente · OWASP LLM01) — invoca .claude/governance-eval/prompt-injection-corpus.mjs: alimenta aos hooks REAIS as ações induzidas por i… |
| `protection-drift.yml` | Protection Drift — baseline de required checks + watchdog de staleness (advisory PERENE · cron diário 10:10 UTC — GT-G4, ADR 0275 §5/§3) |
| `pt-conformance.yml` | ds-pt-conformance (advisory) — torna 'herda PT-0X' no charter FALSIFICÁVEL: verifica que a tela tem a assinatura estrutural do Padrão de Tela declarado (PT-02 exige <form… |
| `quick-sync.yml` | Quick Sync (manual escape — auto-deploy agora é deploy.yml) |
| `reconcile-triplet.yml` | Reconcile triplet (advisory · paridade por setor 3-way charter↔protótipo↔produção · 6 slots PT-01 · reconcile-triplet.mjs --all + charter-blueprint-pointers.mjs · self-te… |
| `repair-shared-vocab.yml` | Repair shared vocabulary guard |
| `required-always-run.yml` | Required always-run (advisory · todo context required nasce em todo PR · anti-deadlock de required-readiness) |
| `reuse-gate.yml` | Reuse duplicates ratchet (anti-duplicação de símbolo) |
| `scope-guard.yml` | Scope Guard (anti-drift) |
| `screen-coverage-gate.yml` | Screen Coverage Gate (catraca de cobertura) |
| `screen-grades-ratchet.yml` | Screen grades ratchet (nota 16-dim de tela não desce vs origin/main · cobre 2 vetores: baixar o valor e apagar o item) |
| `screen-smoke-after-merge.yml` | Screen Smoke After Merge (fase C do PDCA MWART — smoke visual REAL pós-deploy via Playwright headless + OpenAI vision, runner ubuntu; dispara por workflow_run após deploy… |
| `sdd-scorecard-publish.yml` | SDD floor commit-back — publica o floor vivo (branch órfã governance/nightly-floor) em governance/sdd-scorecard.json no main (P01/Gap-1a · ADR 0279) |
| `sdd-scorecard-ratchet.yml` | SDD Scorecard Ratchet (2º dente SDD · GT-G3 — métrica armada não regride; hard, candidato a required; ADR 0275 §3) |
| `sdd-scorecard.yml` | SDD Scorecard meta-catraca (advisory · determinismo + staleness + ratchet vs baseline — GT-G3, ADR 0275) |
| `sells-pest.yml` | Sells · Pest (MySQL) |
| `sells-v3-dominio-gate.yml` | Sells V3 Domínio — parcelas · fiscal · comissão · colunas (vitest · JUnit → manifesto G-7) |
| `shipped-log-cron.yml` | Shipped log cron (auto-PR + auto-merge · regenera registro de entrega do cycle · porta de saída ADR 0294) |
| `shipped-log-gate.yml` | Shipped log gate (advisory · freshness do registro de entrega via --check · porta de saída ADR 0294) |
| `status-badge-fidelity-gate.yml` | StatusBadge Fidelity — pílula de status fiel ao protótipo (rounded-full · token -soft/-fg dark-aware) |
| `stylelint-gate.yml` | Stylelint CSS anti-drift (G5 · ADR 0209) |
| `system-map.yml` | system-map (automação) — regenera memory/reference/PAINEL-SISTEMA.md, memory/requisitos/Jana/ARCHITECTURE.md e ONBOARDING-AGENTE-GERADO.md das fontes canônicas. Painel de… |
| `tier0-guards-advisory.yml` | Tier-0 guards (WithoutGlobalScopes + BusinessId) |
| `ui-architecture-gate.yml` | UI architecture gate |
| `verticais-pest.yml` | Verticais · Pest (MySQL) — ComunicacaoVisual/Repair/Vestuario rodam no MySQL real (skip no sqlite = verde mente); catraca allowlist verde |
| `visual-regression.yml` | Visual Regression (Pest 4 Browser) |
| `whatsapp-pest.yml` | Whatsapp · Pest (MySQL) |
| `xss-content-gate.yml` | XSS content ratchet (.tsx · dSIH + scheme · funde dsih-gate + scheme-gate · oráculo de conteúdo) |

## 2. Hooks (PreToolUse/PostToolUse/SessionStart) — 49 arquivos

> Fonte viva com evento×matcher×sinal-de-bloqueio: **`.claude/hooks/_HOOKS-INDEX.md`** (auto-gerado).

| Hook | Descrição (cabeçalho) |
|---|---|
| `audit-creates-tasks.mjs` | Hook PostToolUse(Write) — detecta tasks órfãs em audit doc + propõe tasks-create MCP. |
| `block-ancora-no-olho.mjs` | PreToolUse(Read): print de auditoria NÃO é âncora de design. |
| `block-askq-execution-menu.mjs` | enforcement POR MÁQUINA da regra |
| `block-automem.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-bom-encoding.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-brl-values-in-memory.mjs` | BLOQUEIA Write/Edit/MultiEdit que introduza valor BRL |
| `block-claim-without-evidence.mjs` | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `block-design-sync-without-optin.mjs` | claude.ai/design NÃO é fonte de design canônica. |
| `block-destructive.mjs` | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `block-edit-authority-generated.mjs` | PreToolUse:Write|Edit|MultiEdit. |
| `block-figma-without-optin.mjs` | Figma NÃO é fonte de design (block determinístico por tool_name). |
| `block-instrumento-sem-porta-viva.mjs` | PreToolUse:Glob|Grep. |
| `block-memory-drift.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-merge-markers.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-mwart-violation.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-routes-string-legacy.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |
| `block-skill-design-sync-without-optin.mjs` | gateia a INVOCAÇÃO da skill /design-sync |
| `block-test-fora-ct100.mjs` | PreToolUse:Bash|PowerShell (PORTE cross-plataforma do .ps1). |
| `block-test-without-red.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |
| `brief-fetch-curl.mjs` | SessionStart (PORTE cross-plataforma do brief-fetch-curl.ps1). |
| `charter-da-tela-que-o-controller-serve.mjs` | PreToolUse:Read. ADVISORY (nunca bloqueia). |
| `charter-validate.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1, advisory). |
| `check-skills-fresh.mjs` | SessionStart (PORTE cross-plataforma do .ps1, advisory). |
| `commit-discipline-check.mjs` | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `design-agente-ativa.mjs` | ATIVA no momento: "você É o designer-agente v2, NÃO espera insumo externo". |
| `design-compare-protocol.mjs` | Hook UserPromptSubmit — ATIVA o protocolo de comparação design×prod (LC-06, strike 2). |
| `design-handoff-reprocess.mjs` | Hook design-handoff-reprocess — detecta o bloco `## new_design_memories` num |
| `diag-pretooluse-trace.mjs` | INSTRUMENTO DE DIAGNÓSTICO (NÃO é um gate). |
| `doc-fora-do-rag.mjs` | PreToolUse:Write. ADVISORY (nunca bloqueia). |
| `force-r12-closing-signal.mjs` | Hook UserPromptSubmit — FORÇA R12 PROTOCOLO ao detectar sinal de fechamento. |
| `git-base-freshness-guard.mjs` | Hook SessionStart — GUARD de base fresca vs `origin/main`. |
| `handoff-inline.mjs` | SessionStart (PORTE cross-plataforma do comando PowerShell INLINE do settings.json). |
| `licoes-code-two-strikes.mjs` | SessionStart (PORTE cross-plataforma do .ps1, advisory). |
| `loop-fechar-check.mjs` | SessionStart (PORTE cross-plataforma do .ps1, advisory). |
| `memory-pending.mjs` | Stop (PORTE cross-plataforma do .ps1, advisory). |
| `memory-schema-guard.mjs` | PreToolUse:Write|Edit|MultiEdit em memory/** e charters. |
| `modulo-preflight-warning.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1, advisory). |
| `nudge-auditoria-resposta.mjs` | Stop (advisory). Checklist de auditoria ANTES de responder. |
| `nudge-diagnosis-without-evidence.mjs` | Stop (PORTE cross-plataforma do .ps1, advisory · estende R1). |
| `nudge-recommend-not-menu.mjs` | Stop (PORTE cross-plataforma do .ps1, advisory · R13/ADR 0233). |
| `nudge-test-contract-anchor.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1, advisory). |
| `php-syntax-after-write.mjs` | PostToolUse:Write|Edit|MultiEdit. |
| `pii-redactor.mjs` | PreToolUse:Bash (PORTE cross-plataforma do .ps1). |
| `post-merge-ui-smoke-required.mjs` | PostToolUse:Bash + PreToolUse:Bash|browser-MCP |
| `preflight-new-capability.mjs` | PreToolUse:Write (PORTE cross-plataforma do .ps1, advisory). |
| `tema-owner-advisory.mjs` | PreToolUse:Write (ADVISORY, allow · ADR 0224/0314). |
| `tier-a-banner.mjs` | SessionStart (PORTE cross-plataforma do tier-a-banner.ps1). |
| `vista-publicada-padrao.mjs` | PreToolUse:Artifact. ADVISORY (nunca bloqueia). |
| `warn-red-first.mjs` | PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1). |

## 3. Skills — 74

> Fonte viva com Tier/auto_trigger: **`.claude/skills/_SKILLS-INDEX.md`** (auto-gerado do frontmatter).

| Skill | Tier | Descrição (início) |
|---|---|---|
| `alinhar-tela` | B | Use quando Wagner pedir "alinhar a tela X", "ligar a máquina da tela Y", "o que já tem pronto e o que falta na tela Z", "fidelidade spec↔código de <Mo… |
| `aplicar-prototipo` | B | ATIVAR quando Wagner pedir "pega o que mudou no protótipo e aplica", "aplicar protótipo nas telas", "atualizar as telas com o protótipo", "o que mudou… |
| `audit-constituicao` | C | ATIVAR quando user pedir "audit pós-constituição", "/audit-constituicao", "consolidação geral", "revisão geral desde a constituição", OU trimestralmen… |
| `audit-to-backlog` | B | ATIVAR quando user pedir "transformar audit em tasks", "levar audit X pro backlog", "criar tasks do audit", "/audit-to-backlog <doc>", OU quando agent… |
| `automem-pending` | B | BLOQUEADOR — quando user mencionar tópico/módulo OU Edit/Read em path com auto-mem stale pendente migração (ADR 0061), esta skill carrega manifesto AU… |
| `avaliar-modulo` | B | ATIVAR quando user pedir "nota do módulo X", "avaliar Modules/X", "/avaliar-modulo X", "qual a nota de Y", "module grade de Z", "qual o bucket de W", … |
| `brief-first` | B | BLOQUEADOR — antes de qualquer outra tool MCP, Read, Glob, Grep ou ação no |
| `brief-update` | B | Use SEMPRE depois de commit/merge de PR que altere capacidades, diferenciais, score Capterra, UX visível, ou gaps de um módulo do oimpresso. |
| `charter-first` | B | BLOQUEADOR — ANTES de editar qualquer .tsx que tenha .charter.md ao lado (ex Index.tsx + Index.charter.md), chame tool MCP `charter-fetch <page-id>` p… |
| `charter-write` | B | ATIVAR quando user pedir "criar charter da tela X", "escrever charter pra /caminho", "gerar charter de Index.tsx Y", "novo charter Page", "/charter-wr… |
| `cliente-discovery` | B | ATIVAR quando Wagner pedir /cliente-discovery, "entrevistar cliente X", "fazer discovery do cliente Y", "criar persona pra <pessoa>", "vou visitar cli… |
| `cockpit-runbook` | C | Generates a detailed RUNBOOK.md or audits a screen against the Chat Cockpit pattern (ADR 0039) for the oimpresso ERP. |
| `commit-discipline` | A | Use ANTES de git commit ou git push em qualquer PR do oimpresso. |
| `comparar-design-prod` | B | BLOQUEADOR de eyeball — ATIVAR SEMPRE que a tarefa envolver COMPARAR design/protótipo com tela em produção ou declarar que estão iguais. |
| `comparativo-do-modulo` | B | ATIVAR quando user pedir "comparar módulo X com mercado", "auditar escopo do módulo Y", "o que falta no módulo Z vs estado da arte", "inventário aprov… |
| `constituicao-ui-aware` | B | Use SEMPRE antes de Edit/Write em qualquer `resources/js/Pages/<X>/*.tsx`, `resources/js/Components/shared/**/*.tsx`, `resources/css/cockpit.css`, `re… |
| `cowork-prototype-replication` | B | ATIVAR quando user pedir "fazer layout estado-da-arte", "replicar protótipo Cowork", "espelhar visual-source.html", "transformar prototipo-ui/* em Ine… |
| `criar-modulo` | B | Use ao criar novo módulo Laravel modular (nWidart) no oimpresso — qualquer pasta nova em `Modules/<Nome>/`, ou pedido explícito "criar módulo", "novo … |
| `criar-staging` | B | ATIVAR quando user pedir "criar staging", "ambiente de homologação/homolog", "replicar produção pra teste", "subir/recriar/atualizar staging.oimpresso… |
| `curador` | B | ATIVAR quando user pedir "ingerir conhecimento", "triar D:\\Conhecimento", "organizar arquivos do computador", "ler tudo e classificar", "/curador <su… |
| `design-deep-analysis` | B | ATIVAR quando Wagner pedir /design-deep <persona-slug>, "analisar visualmente tela X pra persona Y", "design profundo da tela <Z>", OU em refator visu… |
| `design-memoria-reprocess` | B | ATIVAR quando (a) o Claude Design enviar handoff com bloco `## new_design_memories`; |
| `encerrar-sessao` | B | BLOQUEADOR — ATIVAR SEMPRE que user disser "encerrar sessão", "fim de sessão", "vamos parar", "continua depois", "salvar tudo", "salve as memórias", "… |
| `feedback-capture` | B | ATIVAR quando Wagner colar feedback de cliente real OU disser "Daniela reclamou X", "Larissa pediu Y", "Kamila falou que Z", "Jair quer W", "via Whats… |
| `feedback-dashboard` | B | ATIVAR quando Wagner pedir "/feedback-dashboard", "mostra feedback", "como está o feedback", "que feedback tem aberto", "feedback do <cliente>", "feed… |
| `funcao-scorecard` | B | ATIVAR quando [W] pedir "o que você acha dessas funções", "concorda com essa função?", "avalie as funções do <arquivo>", "parecer do ProductUtil", "/f… |
| `governance-pr-summary` | B | Use ANTES de `gh pr create` em qualquer branch que toque Modules/<X>/. |
| `hostinger-dns-autonomy` | A | BLOQUEADOR Tier A — ATIVAR antes de pedir Wagner pra criar/editar DNS record, qualquer ação Hostinger painel/UI, OU sempre que agente cogitar "pode vo… |
| `incident-done-checklist` | A | BLOQUEADOR — ATIVAR antes de declarar "incident fechado" / "está pronto" / "feature funcionando" / encerrar sessão de fix em prod. |
| `inertia-defer-default` | B | Use SEMPRE antes de Edit em qualquer Controller que chama `Inertia::render(...)` no oimpresso (qualquer `Modules/<X>/Http/Controllers/**/*Controller.p… |
| `jana-arch` | B | Use ao trabalhar em Modules/Jana/ ou ao tocar memória/IA do projeto. |
| `jana-brief-concierge` | B | ATIVAR quando user (Wagner) colar/citar um JSON com chaves `version`, `business_id`, `sources` (vendas/inadimplencia/tickets/nfe/oportunidades) OU ped… |
| `jana-recall-flow` | B | Use ao tocar Modules/Jana/Services/Memoria/, ContextSnapshotService, recall hybrid (Meilisearch + HyDE + reranker), MCP memory sync (git→DB→Scout), ou… |
| `mcp-first` | B | ATIVAR antes de Read/Glob/Grep em memory/, ler ADR/session/spec do projeto, buscar conhecimento canônico do oimpresso, criar arquivo em ~/.claude/proj… |
| `memory-first-secret-search` | A | BLOQUEADOR Tier A — ATIVAR ANTES de qualquer busca por token / API key / password / SSH key / credential / secret. |
| `memory-schema-preflight` | B | ATIVAR ANTES de Write/Edit em `memory/requisitos/**/SPEC.md`, `memory/requisitos/**/RUNBOOK*.md`, `memory/requisitos/**/BRIEFING.md`, `memory/decision… |
| `memory-sync` | B | ATIVAR após criar/editar arquivo em memory/, atualizar SPEC.md/TEAM.md, salvar ADR/session log, ou usar trigger "salve no cofre"/"guarde"/"grave na me… |
| `meta-skill-roi-erp-autonomo` | C | ATIVAR ao criar skill nova, usar `skill:scaffold`, discutir se uma ideia merece virar skill, ou perguntar "isso vira skill?". |
| `migracao-blade-react` | B | ATIVAR quando user pedir "migrar tela X", "migrar Blade pra React", "migração massiva", "/migracao-blade-react <modulo>/<tela>", OU em Edit/Write em `… |
| `migrar-modulo` | B | Use ao mover, renomear, ou extrair controller/módulo Laravel modular existente em `Modules/<X>/` — qualquer `git mv Modules/X Modules/Y`, `git mv Modu… |
| `migration-status` | B | ATIVAR quando user pedir "status migração", "% migrado {módulo}", "tabelas Firebird", "status da migração por tabelas", "dependências da migração", "/… |
| `module-completeness-audit` | B | ATIVAR antes de marcar US como `done` (`tasks-update task_id:US-XXX-NNN status:done` ou `tasks-update from:review to:done`), OU quando user pedir "aud… |
| `module-grades-gate` | C | ATIVAR quando user pedir "checar grades antes de PR", "rodar gate de notas local", "atualizar baseline module-grade", "como override regressão grades"… |
| `multi-tenant-patterns` | A | Use ao criar ou alterar Eloquent Model, Controller, Service, Job, Command ou Migration que toca dados de negócio (qualquer entidade com `business_id`)… |
| `mwart-comparative` | B | Use SEMPRE antes de codar Page Inertia em migração MWART (Blade→React) no oimpresso. |
| `mwart-process` | B | Use SEMPRE que o trabalho envolva migrar tela Blade legacy → Inertia/React no oimpresso (MWART). |
| `mwart-quality` | B | Use ANTES de criar/editar tela MWART (Module Web App React Transition Blade→Inertia/React) no oimpresso. |
| `officeimpresso-financial-snapshot` | B | ATIVAR quando user pedir "analisar receita do cliente X", "snapshot financeiro de {cliente OfficeImpresso}", "comparar 2 clientes legacy", "/financial… |
| `officeimpresso-source-analysis` | B | ATIVAR quando precisar entender comportamento real de uma tela/feature do OfficeImpresso legacy (Delphi WR Comercial) — em vez de inferir via probes n… |
| `oimpresso-cc-watcher-setup` | C | Configura o watcher local do Claude Code que sincroniza ~/.claude/projects/*.jsonl com o MCP server do oimpresso (cc-search cross-dev). |
| `oimpresso-stack` | C | Use ao iniciar trabalho no oimpresso ou ao entrar num módulo novo. |
| `oimpresso-team-onboarding` | C | Configura ou valida acesso ao MCP server da empresa oimpresso (Wagner/Felipe/Maiara/Luiz/Eliana). |
| `pageheader-canon` | B | ATIVAR quando agente vai aplicar o PageHeader canon (ADR 0180/0182/0189/0190) em módulo novo — user pede "aplicar pageheader canon no módulo X", "padr… |
| `personas-resolve` | B | BLOQUEADOR Tier A — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em arquivos de `resources/js/Pages/**/*.tsx` ou criação de tela nova. |
| `pr-ui-judge-manual` | C | Use quando Wagner pedir "avaliar PR <número> contra Constituição UI v2", "rodar judge no PR X", "review semântico do PR Y", "/pr-ui-judge <PR#>", "sco… |
| `pre-adr-introspect` | B | ATIVAR ANTES de qualquer Write em `memory/decisions/NNNN-*.md` (ADR nova) OU antes de propor schema novo (`database/migrations/*.php` que adiciona col… |
| `pre-decisao-git-first` | B | ATIVAR ANTES de interromper o Wagner com uma dúvida durante o desenvolvimento — sempre que for usar AskUserQuestion, escrever "não sei se...", "qual v… |
| `precisao-literal` | B | ATIVAR quando user pedir "compare com o protótipo", "avalie precisão", "que % literal", "ficou idêntico?", "compare lado a lado", "nota da paridade", … |
| `preflight-modulo` | B | BLOQUEADOR — ATIVAR ANTES de qualquer Edit/Write/MultiEdit em Modules/<X>/. |
| `proxmox-docker-host` | C | Use ao mexer com infra Proxmox/CT 100/containers Docker do oimpresso. |
| `publication-policy` | B | Use ANTES de qualquer git push, abertura/merge de PR, deploy em produção, mudança em .env de produção, ou postagem externa (blog, rede social, email c… |
| `reguas-do-sistema` | B | ATIVAR quando Wagner pedir "grade de réguas", "onde sou fraco vs mercado", |
| `runtime-rules-hostinger-ct100` | B | Use ANTES de SSH no Hostinger, composer install/update em servidor, criar git worktree em servidor, ou qualquer comando que envolva laravel/mcp, larav… |
| `screen-grade` | B | ATIVAR quando user pedir "nota da tela X", "gradear tela Y", "/screen-grade Sells/Create", "qual a maturidade da tela Z", "pré-flight da tela W", "scr… |
| `sdd-avaliar` | C | Use ANTES de promover qualquer gate SDD a required (calendário ADR 0275), AO |
| `session-start-check` | B | ATIVAR depois do brief-first em toda sessão. |
| `sidebar-menu-arch` | B | Reconhecer, auditar e modificar a arquitetura do sidebar do AppShellV2 — DataController por módulo + agrupamento visual via SIDEBAR_GROUPS no frontend… |
| `smoke-prod-evidence` | B | ATIVAR antes de declarar "funcionando", "smoke OK", "deploy ok", "está rodando" no oimpresso. |
| `tela-smoke-pos-merge` | B | ATIVAR após PR mergeado que toca resources/js/Pages/**/*.tsx OU quando Wagner pedir "smoke a tela X", "validar tela X visualmente", "ver como ficou te… |
| `ticket-triage` | B | ATIVAR quando user pedir "analise esse ticket", "triage", "vale a pena atender X?", "qual a prioridade", "esse cliente é importante?", "score do ticke… |
| `ui-component-creator` | B | Use ao criar/modificar componentes React (Pages Inertia, sub-componentes em _components/, ou shareds em Components/shared/) seguindo Cockpit Pattern V… |
| `validador-modulo` | B | ATIVAR quando Wagner pedir "valida o módulo X inteiro", "confere a estrutura |
| `wagner-protocol-enforce` | B | BLOQUEADOR Tier A always-on — carrega memory/reference/PROTOCOLO-WAGNER-SEMPRE.md |
| `wagner-request-refiner` | B | ATIVAR quando Wagner manda múltiplos pedidos curtos não-estruturados num mesmo turno (ex: lista com 3+ items, "todo: a) b) c)", bullets numerados, scr… |

## 4. Agents (subagentes Task) — 27

| Agent | Descrição (início) |
|---|---|
| `audit-implement-expert` | Implementador universal de gap específico — recebe um GAP da auditoria (Fase 1 do `/audit-and-fix`), pesquisa best-of-class do gap, mini-comparativo % atual→target, e imp… |
| `audit-research-expert` | Auditor universal de maturidade — recebe um TEMA (ex "reranker", "knowledge-architecture", "session-handoff", "observability"), pesquisa estado-da-arte 2025-2026, compara… |
| `audit-senior-expert` | Auditor SÊNIOR — pesquisa profunda (5-7 WebSearch POR gap), comparativo rigoroso, dossier executável pra Onda inteira. |
| `capterra-senior` | Use quando Wagner pedir "Capterra do módulo X", "compare meu módulo Y com os melhores e dá nota", "estado-da-arte profundo do módulo Z", "/capterra-senior <Modulo>", "pes… |
| `ciclo-adversary` | Adversário read-only do CICLO DE APRENDIZADO (erro → conserta → lápide §5 → ledger LC → defesa mecânica). |
| `cliente-drawer-integrar` | Implementador especializado da integração legacy WR Comercial/Delphi → drawer Cliente 760px (ADR 0179). |
| `como-integrar` | Use ANTES de Wagner aprovar implementação de feature nova/refactor médio no oimpresso. |
| `comunicacao-visual-expert` | Especialista de domínio em Comunicação Visual industrial brasileira (CNAE 1813-0/01) — processos OS, PCP, instalação, tributação serviço vs mercadoria, NR-35 fachada, con… |
| `coordenador-paralelo` | Use quando Wagner pedir "coordene em paralelo X", "decompor em waves", "spawne N agents pra Y", "faça em paralelo sem invadir outras áreas", OU quando o problema admite d… |
| `cowork-to-inertia` | Use quando Wagner mandar design Cowork pra implementar como Inertia/React real — sinais típicos "implementa essa tela", "esse design tem nota 9,75", "vou te mandar a tela… |
| `deprecar-modulo` | Use quando Wagner decidir deprecar/aposentar um módulo Laravel modular do oimpresso (ex SRS, Officeimpresso legacy, Cms antigo, qualquer Modules/<X> em estado zumbi). |
| `design-arte` | Use quando Wagner pedir "estado da arte de design do oimpresso", "nota de design da tela X", "Capterra de design pro módulo Y", "compare meu design com Linear/Shopify/Not… |
| `document-relocation-adversary` | Adversario read-only de planos de classificacao, movimento e relink de documentacao. |
| `documentacao-sistema` | ATIVAR quando [W] pedir qualquer coisa sobre a DOCUMENTAÇÃO DO SISTEMA — "documenta o sistema", |
| `estado-da-arte` | Use quando o Wagner pedir "faça o estado da arte de X", "estado da arte de Y", "pesquise como os melhores fazem Z", "/estado-da-arte <problema>". |
| `financeiro-bridge-auditor` | Auditor especialista da bridge Sells/Compras (UltimatePOS core) → Modules/Financeiro (`fin_titulos`/`fin_titulo_baixas` via Observers). |
| `maturity-gap-expert` | Especialista em gap analysis maturidade oimpresso vs estado-da-arte 2026. |
| `memoria-senior` | Use quando Wagner pedir "auditoria de memória", "otimizar memory/", "estado-da-arte arquitetura de memória/knowledge architecture/RAG", "compare minha memória com Mem0/Le… |
| `migracao-firebird-versoes` | Use quando Wagner pedir "termine a migração", "migra os clientes legacy todos", "trate as versões diferentes Firebird", "/migrar-versoes <cliente>", "terminar Martinho", … |
| `migracao-officeimpresso` | Use quando Wagner pedir "migrar cliente legacy <hash>", "importar Firebird de <cliente>", "trazer dados Delphi pra oimpresso", "/migrar-officeimpresso <cliente>", OU quan… |
| `screen-qa-specialist` | ATIVAR quando Wagner pedir "garantir QA da tela X", "testar a tela Y de ponta a ponta", "cobrir a tela Z", "/screen-qa <Mod>/<Tela>", "especialista de teste na tela W", "… |
| `sdd-from-source` | ATIVAR quando [W] pedir "gera o SDD da tela X a partir do fonte", "documenta o fluxo real de <Mod>/<Tela>", "faz o SDD/casos de <Mod>/<Tela> analisando o código", "/sdd-f… |
| `tela-venda-arte` | Use quando Wagner pedir "estado da arte da tela de venda", "compare minha tela de venda com os concorrentes", "benchmark POS", "nota da minha tela de venda", "como o Blin… |
| `testador-de-maquinas` | ATIVAR quando [W] pedir "essa máquina morde?", "testa o gate X", "esse hook está funcionando mesmo?", "audita a máquina Y", "prova que o gate pega", "posso promover esse … |
| `wagner-understand` | ATIVAR ANTES de Claude começar a executar pedido do Wagner — especialmente quando o pedido vem cru/curto/ambíguo ("faz isso", "implementa X", "copia aquilo", screenshot c… |
| `whatsapp-arch-arte` | Use quando Wagner pedir "estado da arte de arquitetura WhatsApp/mensagens", "compare minha estrutura WhatsApp com os melhores e dá nota", "auditar arquitetura técnica do … |
| `whatsapp-doctor` | Use quando WhatsApp Baileys daemon der problema no CT 100 — "WhatsApp parou", "mensagem não saiu", "tá banido?", "loop de erro no daemon", "device_removed", "stream error… |

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

### 5.1 `scripts/governance/` — 111

| Script | Invocador | Descrição (cabeçalho) |
|---|---|---|
| `adr-index-generate.mjs` | agente, ci, script | GERADOR determinístico do índice de ADR (modelo Log4brains). |
| `adr-proposto-parado.mjs` | ci, script | sentinela: decisão PENDENTE que ninguém vê acaba não sendo feita. |
| `adr-supersede.mjs` | npm | supersessão ATÔMICA de ADR (modelo adr-tools/pyadr, ADR 0258). |
| `agent-corpus-counterfactual.mjs` | agente | QUANTO CUSTA descobrir se o corpus ajuda? |
| `agent-cost-per-pr.mjs` | agente, ci, script | CUSTO ESTIMADO POR PR do agente (USD/tokens · advisory). |
| `agent-pr-outcomes.mjs` | agente, ci, script | EVALS DE OUTCOME dos PRs do agente (DORA-style). |
| `agents-md-staleness.mjs` | ci, script | sentinela: o AGENTS.md ficou atrás do CLAUDE.md? |
| `anchor-content-check.mjs` | agente, ci, script | sentinela de CONTEÚDO da âncora de design. |
| `anchor-lint.mjs` | agente, ci, script | parser da gramática anchor spec↔código (ADR 0273 · passo SA-A2 |
| `ancora-codigo-sync.mjs` | ci, script | AUTO-SYNC da âncora doc→CÓDIGO (o mecanismo do Swimm, traduzido). |
| `baseline-tamper-guard.mjs` | ci, script | anti-grandfather (Gap 2 do blueprint SDD · ADR 0256/0258). |
| `blade-migration-census.mjs` | ci, script | o CONTRATO DE COMPLETUDE da ADR 0277, derivado da árvore. |
| `briefing-code-staleness.mjs` | agente, ci, npm, script | sentinela: a PORTA (BRIEFING.md) ficou atrás do CÓDIGO? |
| `brl-scan-diff.mjs` | ci, script | varre as LINHAS ADICIONADAS de um PR procurando valor BRL não-redigido. |
| `catalog-graph.mjs` | agente, ci, script | GERADOR determinístico do GRAFO TIPADO de módulos. |
| `charter-blueprint-pointers.mjs` | ci, script | auditoria de PONTEIROS DE PROTÓTIPO dos Page Charters. |
| `charter-live-signal.mjs` | ci, script | gate de SINAL pra charter `status: live` (proposta SDD 2026-06-24). |
| `charter-promote-signal.mjs` | script | passe REPETÍVEL de promoção draft→live guiado por SINAL de prod. |
| `charter-refs.mjs` | agente, ci, script | catraca de integridade de refs dos Page Charters (ADR 0256). |
| `charter-us-lint.mjs` | ci | lint do campo canônico `related_us` nos Page Charters |
| `ciclo-completo.mjs` | ci | GATE "a tela nasceu (e segue) COMPLETA?" (Constituição UI v2 · UI-0013). |
| `component-registry-check.mjs` | ci, script | sentinela de DRIFT do registro de componentes (Onda O2). |
| `cowork-mirror-freshness.mjs` | agente, ci, script | comparador de FRESCOR do espelho Cowork (v2, identidade canônica). |
| `cowork-ssot-guard.mjs` | ci | MÁQUINA de fonte única do protótipo de design. |
| `criar-tela.mjs` | agente, ci, npm, script | GERADOR de tela que NASCE do Padrão de Tela (Constituição UI v2 · UI-0013). |
| `cron-watchdog.mjs` | ci | G6: heartbeat dos crons de governança (generaliza o auto-canário |
| `deadlink-gate.mjs` | ci, script | catraca de integridade referencial doc↔doc (links markdown mortos). |
| `design-code-map-check.mjs` | ci | sentinela da ponte design↔código PERSISTENTE (<tela>.map.json). |
| `design-gate-bites.mjs` | agente, ci | o BITE-LOG dos gates de design (DR-2a da ADR 0336). |
| `detect-handoff.mjs` | ci, npm | DETECTOR-EM-LOTE do G4 ("paste zip → 1 tarefa por tela"). |
| `detect-ui-drift.mjs` | ci, npm, script | M1: detector de MUDANÇA DE UI NÃO-DECLARADA (eixo de AUTORIZAÇÃO). |
| `doc-auto-relink.mjs` | ci, npm | AUTO-RELIGADOR: dado um doc que MOVEU (A→B), religa os links. |
| `doc-freshness-score.mjs` | ci, script | RADAR de frescor POR DOC (score 0-100 · régua Dosu). |
| `doc-id-index.mjs` | ci, script | GERADOR determinístico do índice `id → path atual` do corpus memory/. |
| `doc-id-stamp.mjs` | ci, npm | STAMPER: adiciona `id:` no frontmatter dos docs SEM id. |
| `document-authority.mjs` | agente, ci, script | identidade documental compartilhada pelo hook e pelo CI. |
| `document-relocation-adversary.mjs` | agente, ci, npm, script | Validador read-only de planos de realocacao documental. |
| `document-relocation-classifier.mjs` | ci, npm | Classificador conservador de documentos. Produz plano v2 pinado ao HEAD; |
| `document-relocation-executor.mjs` | ci, npm, script | Executor transacional de planos documentais aprovados. Dry-run por padrao. |
| `documentation-loop.mjs` | agente, ci, npm, script | recibo determinístico do ciclo documental. |
| `doneness-lint.mjs` | ci, script | catraca de fonte-única do "done-ness" de US (ADR 0302). |
| `ds-lint-selftest.mjs` | ci | LINT SELFTEST — controle-negativo das regras ds/* (as `no-restricted-syntax` |
| `ds-mirror-drift.mjs` | agente, ci, script | SENTINELA de drift git ↔ espelho vivo (P3). |
| `dtcg-equivalence.mjs` | ci, npm | onda DTCG (ancora: ADR 0239 DS git SSOT + ADR 0249 DS v6 + |
| `dup-detector.mjs` | ci | L3 (keystone) da trava anti-duplicação de trabalho entre sessões |
| `fact-anchor.mjs` | script | lógica PURA do Check T de memory-health.mjs (fact-anchor). |
| `feature-lint.mjs` | ci, npm, script | valida o TRIO de feature (requirements.md + plan.md + tasks.md) em |
| `flip-required.mjs` | — | promove UM check advisory a required na branch protection de `main`. |
| `fluxo-morde.mjs` | ci, script | EXERCÍCIO DE FOGO DO FLUXO: o método detém um defeito, ou só o comenta? |
| `funcao-scorecard-calibracao.mjs` | script | calibração NÃO-CIRCULAR do juiz funcao-scorecard. |
| `funcao-scorecard-humano.mjs` | npm | template                 imprime o JSON cego que [W] preenche |
| `funcao-scorecard-outcome-probe.mjs` | ci, npm | PROTÓTIPO de validação-por-OUTCOME do funcao-scorecard. |
| `gate-selftest.mjs` | agente, ci, script | QUEM VIGIA OS VIGIAS (frente GT-G6, plano-mãe SDD 2026-06-12 §2 |
| `ghost-fix.mjs` | agente, script | codemod de ghost-names em memory/requisitos/** (Semana 0, frente KL). |
| `governance-audit.mjs` | script | DEPRECADO 2026-08-04: agregador SEM invocador e sem casa honesta |
| `governance-backlog-sync.mjs` | ci | fecha o loop memory-health → backlog MCP. |
| `hook-bites.mjs` | agente, script | DEAD MAN'S SWITCH dos hooks de runtime (advisory, exit 0 sempre). |
| `hook-replay.mjs` | npm | testa hook contra TELEMETRIA REAL (advisory, exit 0 sempre). |
| `hooks-manifest-generate.mjs` | agente, ci | GERADOR determinístico do manifesto de hooks (grade de réguas |
| `hue-canon-check.mjs` | agente, ci | verificador da fonte única do hue primário (US-GOV-052 P32). |
| `junit-lanes.mjs` | ci | fonte ÚNICA e DERIVADA das lanes de CI que alimentam o manifesto por-UC |
| `knowledge-drift.mjs` | agente, ci, script | primeira batida do "batimento" (ADR 0270 / sessão 2026-06-11). |
| `lapide-recheck.mjs` | agente, ci, script | re-verificação de FRESCOR das lápides §5 (memory/proibicoes.md, |
| `ledger-check.mjs` | agente, ci, script | enforcement do PROTOCOLO-REFUTADOR-BACKFILL (frente GT-G5, |
| `ledger-hash-chain.mjs` | ci | transparency-log (Rekor/Sigstore-style) sobre o |
| `maquinas-inventario.mjs` | agente, ci | DERIVA um índice único e legível de TODAS as "máquinas" |
| `mcp-drift-sentinel.mjs` | ci, script | sentinela EXTERNA de drift do MCP server (ADR 0256 + 0062). |
| `memory-health.mjs` | ci, script | sentinela de saúde da base de conhecimento (ADR 0256, Onda 1). |
| `module-group-resolve.mjs` | — (só `.test`) | resolve O GRUPO DE MEMÓRIA de um módulo a partir da ÁRVORE. |
| `module-surface.mjs` | agente, ci, npm, script | GERADOR determinístico da "Superfície de código" de um módulo. |
| `negocio-vs-governanca-ratio.mjs` | agente, ci | o alarme anti-atrofia da inteligência de negócio. |
| `next-id.mjs` | agente, script | aloca o próximo número de ADR/US **ciente de trabalho em voo** (ADR 0304). |
| `normalize-adr-frontmatter.mjs` | npm | normaliza status/lifecycle de ADR pro enum canônico. |
| `onboarding-paths-check.mjs` | agente, script | a CAMADA DETERMINÍSTICA do canário de onboarding. |
| `outcome-metrics.mjs` | agente, ci, script | MEDIDOR DE ACEITAÇÃO do transporte Cowork→code (Onda O1). |
| `pages-colisao.mjs` | agente, ci | barra DUAS fontes declarando a mesma página Inertia. |
| `palette-generate.mjs` | ci | GERADOR determinístico da página de paleta de cor. |
| `permissao-renomeada-lint.mjs` | ci | barra o nome VELHO de permissão renomeada em linha NOVA. |
| `permission-drift.mjs` | ci | mede o drift entre permissão DECLARADA e permissão APLICADA. |
| `plan-health.mjs` | ci, script | sentinela de PLANOS órfãos/podres (ADR 0294 Onda 1 · catraca da |
| `plans-index.mjs` | ci | GERADOR determinístico do Índice de Planos Vivos (ADR 0294 + 0256). |
| `protection-drift.mjs` | agente, ci, script | drift de branch protection + watchdog de staleness (GT-G4, |
| `pt-conformance.mjs` | ci, npm, script | VERIFICA que uma tela que DECLARA "herda PT-0X" tem de fato a |
| `rag-status-vocab-check.mjs` | ci | detecta documento que ENTRA no índice do RAG mas |
| `reconcile-triplet.mjs` | ci, script | gate de PARIDADE POR SETOR (3-way charter↔protótipo↔produção). |
| `ref-integrity.mjs` | ci, script | sentinela ADVISORY de integridade referencial rota↔código |
| `refuter-canary-check.mjs` | agente, script | anti-Goodhart do LAYER DE AGENTE (chip orq-anti-goodhart · |
| `reguas-cross-model.mjs` | agente, script | braço de verificação CROSS-MODEL (cross-VENDOR) da grade de réguas. |
| `reguas-indexar.mjs` | agente, ci, npm | Órgão 4 da máquina de réguas em looping (ADR proposta reguas-loop-maquina-evolucao). |
| `reguas-ledger-check.mjs` | ci | o ledger de réguas contradiz a si mesmo? |
| `required-always-run.mjs` | ci | todo context REQUIRED nasce em TODO PR? |
| `requisitos-status.mjs` | ci, npm, script | a CADEIA DE RASTREABILIDADE de um módulo, derivada e com STATUS. |
| `resolver-reclamacao.mjs` | ci, npm | resolvedor reclamação → cadeia de responsabilidade. |
| `sdd-flow.mjs` | npm | recibo estrutural da cadeia: |
| `sdd-output-lint.mjs` | ci, npm | mede a QUALIDADE do artefato que o agent `sdd-from-source` (ADR 0351) produz. |
| `sdd-scorecard.mjs` | agente, ci, script | agregador do scorecard SDD (GT-G2, Semana 0 do plano |
| `sec5-derive.mjs` | ci | o §5 do `memory/proibicoes.md` passa a ser DERIVADO. |
| `seed-tela.mjs` | script | EMPACOTADOR DE SEED (G1 do padrão "1 clique → sessão limpa por tela"). |
| `selftest-registry-check.mjs` | agente, ci, script | P15 entrega 3: teste .mjs órfão de workflow (advisory). |
| `service-scorecard.mjs` | ci | SCORECARD de SINAIS-VIVOS por serviço/módulo (estilo Cortex). |
| `shipped-log-generate.mjs` | ci | generate.mjs v2 — porta de saída do loop (estende ADR 0294). |
| `skills-index-generate.mjs` | agente, ci, script | GERADOR determinístico do índice de skills (US-GOV-052 P31). |
| `spec-lib-staleness.mjs` | ci | sentinela: o DOC que descreve uma lib externa ficou |
| `system-map.mjs` | agente, ci, script | a MATRIZ gerada do painel do sistema oimpresso. |
| `tasks-index-generate.mjs` | ci, script | GERADOR determinístico de BACKLOG + CHANGELOG indexados. |
| `tema-owner.mjs` | agente | detector ADVISORY de DONO-DE-TEMA por sobreposição de ENTIDADE. |
| `test-lane-coverage.mjs` | ci, script | quais testes EXISTEM × quais o CI realmente EXECUTA. |
| `uc-sem-lane.mjs` | ci | UC com o id no TÍTULO de um teste que LANE NENHUMA executa. |
| `ui-impact.mjs` | ci | Fonte única do skip-as-pass do visual-regression. |
| `visual-comparison-staleness.mjs` | ci, script | sentinela: o `<tela>-visual-comparison.md` ficou atrás da TELA? |
| `worktree-janitor.mjs` | ci, npm | Faxineiro de worktrees — classifica worktree MORTO vs VIVO por ORÁCULO, nunca por heurística. |

### 5.2 `scripts/tests/` — 9

| Script | Invocador | Descrição (cabeçalho) |
|---|---|---|
| `coverage-compute.mjs` | ci, script | write-side do coverage_pct (SDD P07 · ADR 0275 §2 fonte |
| `floor-compute.mjs` | ci, script | write-side do floor (ADR 0279 Opção A · PR-2 · US-GOV-018). |
| `foundation-ratchet.mjs` | agente, ci, script | marcadores `legacy-quarantine` (burn-down: subir = regressão) |
| `junit-summary.mjs` | agente, ci, script | sumário JSON por arquivo de teste a partir de JUnit XML (PHPUnit/Pest). |
| `nightly-diff.mjs` | ci | tripwire de regressão QUALITATIVA do nightly (ROADMAP-SDD P15). |
| `ragas-trend-compute.mjs` | ci, script | write-side do trend do RAGAS real (ADR 0318 + pattern |
| `shards-merge.mjs` | ci, script | funde os summaries junit POR SHARD numa medição da noite (SDD P04 |
| `shards-plan.mjs` | agente, ci, script | particiona a suíte Pest em N shards POR DIRETÓRIO (determinístico). |
| `visreg-clock-bite.mjs` | ci | TEST do congelamento do relógio do navegador (gate visual-regression). |

### 5.3 `scripts/` (raiz) — 35

| Script | Invocador | Descrição (cabeçalho) |
|---|---|---|
| `a11y-ratchet.mjs` | ci, npm | scripts/a11y-ratchet.mjs — acessibilidade como categoria DETERMINÍSTICA PROTEGIDA. |
| `adversario-intencao-fluxo.mjs` | ci | Adversário: procura contraprovas ao contrato, sem aceitar justificativa em prosa. |
| `auditar-intencao-fluxo.mjs` | ci, script | Catraca estática: a prosa declara a intenção, mas não mascara evidência ausente. |
| `bundle-lint.mjs` | ci, npm | esteira ≠ armazém (régua 6 da memória de proveniência). |
| `casos-coverage-guard.mjs` | agente, ci, npm, script | scripts/casos-coverage-guard.mjs — Gate G-1 (trio-de-tela) + G-2 (rastreabilidade caso↔teste) |
| `casos-results-collect.mjs` | ci, npm, script | scripts/casos-results-collect.mjs — Coletor de test-results → manifesto por-UC (Salto #2, |
| `components-tree-guard.mjs` | agente, ci, npm, script | scripts/components-tree-guard.mjs — árvore canônica de Components/ (allowlist + convenção _components) |
| `conformance-gate.mjs` | ci, npm, script | Determinístico, sem browser, sem dependência. Roda em CI (exit≠0 = bloqueia merge) E local. |
| `contrato-de-tela.mjs` | ci, npm, script | Gate "Contrato de Tela" (a perna de fidelidade visual do trio-de-tela). |
| `css-size-baseline.mjs` | ci, npm, script | scripts/css-size-baseline.mjs — ratchet de TAMANHO do CSS (anti-regrowth). |
| `design-identity-grade.mjs` | ci | GRADE de identidade visual DETERMINÍSTICO (ADR 0254). |
| `design-spec-gen.mjs` | ci, npm | tela (componentes/tokens/layout) é PURA e DERIVÁVEL, mas era julgada por LLM |
| `domain-dict-guard.mjs` | ci, npm, script | scripts/domain-dict-guard.mjs — Gate G-4 (dicionário de domínio) da Governança executável (ADR 0264). |
| `ds-canon-color-guard.mjs` | ci, npm | scripts/ds-canon-color-guard.mjs — catraca: a camada canônica NÃO usa paleta crua |
| `ds-ledger.mjs` | ci, npm | scripts/ds-ledger.mjs — Ledger de Conformidade DS (censo Onda 0, por tela). |
| `ds-report.mjs` | ci, npm, script | scripts/ds-report.mjs — placar de adoção do Design System (ds/* por regra × módulo) |
| `dsih-gate.mjs` | ci, script | porque NENHUM gate mordia CONTEUDO em .tsx (so canal: lint/build/conformance). |
| `eslint-baseline.mjs` | ci, npm, script | scripts/eslint-baseline.mjs — Onda 1.2 (ADR 0209) |
| `foundation-guard.mjs` | ci, npm, script | Determinístico, sem browser, sem dependência. Roda em CI (exit≠0 = bloqueia merge) E local. |
| `generate-dxt.js` | script | Gera arquivo .dxt (Claude Desktop Extension) para membros do time oimpresso. |
| `handoff-integrity-guard.mjs` | ci, npm, script | scripts/handoff-integrity-guard.mjs — catraca de Integridade do Handoff (PROCESSO_MEMORIA_CC.md §16 · IT8). |
| `layout-primitives-guard.mjs` | ci, npm, script | scripts/layout-primitives-guard.mjs — enforcement da ADR 0253 (primitivos de layout) |
| `no-mock-in-prod.mjs` | ci, npm, script | scripts/no-mock-in-prod.mjs — Frente 6 (plano anti-duplicacao 2026-06-06) |
| `pageheader-migration-guard.mjs` | ci, npm, script | scripts/pageheader-migration-guard.mjs — F4 do roadmap de convergência UI (MANUAL-CSS-JS.md §5) |
| `perf-static-guard.mjs` | script | scripts/perf-static-guard.mjs — catraca da Onda 4 lente 5b (AUDITORIA-PERFORMANCE-2026-07). |
| `reuse-index.mjs` | agente, ci, npm, script | índice escrito à mão APODRECE (ADR 0239: git=SSOT, derivado>escrito). Este índice é REGENERADO do código a cada |
| `scheme-gate.mjs` | ci | Por que existe: o red-team adversarial de 2026-06-17 mostrou que NENHUM gate mordia CONTEUDO |
| `scorer-sync-check.mjs` | ci, npm | scripts/scorer-sync-check.mjs — guarda a SINCRONIA dos regex entre as duas implementações. |
| `sells-cowork-dead-css.mjs` | — | gate conta a cor-crua DESSAS regras mortas como se fosse dívida viva |
| `smoke-veredito-ledger.mjs` | npm | Smoke / acceptance harness do programa veredito-ledger. |
| `stylelint-baseline.mjs` | ci, npm, script | scripts/stylelint-baseline.mjs — G5 anti-drift CSS (ADR 0209 pattern) |
| `uc-derive.mjs` | — | scripts/uc-derive.mjs — Auto-derivador de vínculo UC↔teste (PoC read-only, determinístico) |
| `visreg-flows-lint.mjs` | ci, npm, script | Catraca do contrato de fluxos visuais: cenário sem viewport, ação ou evidência não entra no CI. |
| `visreg-sells-lint.mjs` | ci, npm | Catraca do contrato de fluxos visuais de Sells/Create: cenário sem viewport, ação ou |
| `visreg-states-lint.mjs` | ci, npm, script | charter `states:` ⇄ manifesto do gate L2 (estados isolados do VRT). |

## 6. Baselines & JSON de estado

| Arquivo | `_meta` / propósito |
|---|---|
| `governance/adr-alias-map.json` | (baseline/estado) |
| `governance/adr-collisions-baseline.json` | Colisões de número de ADR — catraca anti-bifurcação (só encolhe). O detector já existia (adr-index-generate.mjs lista as colisões desde sempre); este … |
| `governance/adr-tombstones.json` | (baseline/estado) |
| `governance/anchor-entry-baseline.json` | anchor entry/covers GRANDFATHER — US legadas isentas (ratchet só-desce · ADR 0275 advisory→required por calendário) |
| `governance/blade-migration-baseline.json` | Censo de migração Blade→React — catraca só-desce por escopo (ADR 0277 contrato de completude) |
| `governance/charter-refs-baseline.json` | (baseline/estado) |
| `governance/cron-vermelho-esperado.json` | (baseline/estado) |
| `governance/deadlink-baseline.json` | (baseline/estado) |
| `governance/dependency-direction-baseline.json` | (baseline/estado) |
| `governance/doc-id-index.json` | (baseline/estado) |
| `governance/doneness-baseline.json` | doneness GRANDFATHER — conflitos status×âncora legados isentos (ratchet só-desce · ADR 0302/0275 advisory→required por calendário) |
| `governance/ds-ledger.json` | (baseline/estado) |
| `governance/dup-hot-paths.json` | (baseline/estado) |
| `governance/ghost-rename-map.json` | (baseline/estado) |
| `governance/hue-canon.json` | Fonte única do hue primário universal (US-GOV-052 P32). O hue vivia em 3 mapas divergentes — pageheader-canon chegou a ter check aprovando o 145 morto… |
| `governance/jana-ragas-baseline.json` | Baseline RAGAS canary Jana — recriado via workflow_dispatch jana-ragas-canary.yml (US-COPI-116). Não editar à mão; usar update_baseline=true no dispat… |
| `governance/jana-ragas-real-baseline.json` | (baseline/estado) |
| `governance/ledger-checkpoints.json` | (baseline/estado) |
| `governance/module-coupling-baseline.json` | (baseline/estado) |
| `governance/module-grades-baseline.json` | (baseline/estado) |
| `governance/module-group.json` | (baseline/estado) |
| `governance/module-table-coupling-baseline.json` | (baseline/estado) |
| `governance/multi-tenant-scope-baseline.json` | (baseline/estado) |
| `governance/prod-flags.json` | (baseline/estado) |
| `governance/required-checks-baseline.json` | Required checks de main CONGELADOS — GT-G4 (plano 2026-06-12 §2 GARANTIDA) |
| `governance/reseed-meilisearch-manifest.json` | (baseline/estado) |
| `governance/route-hits.json` | (baseline/estado) |
| `governance/sdd-scorecard-baseline.json` | SDD scorecard baseline v1 — meta-catraca GT-G3 (plano 2026-06-12 §2 GARANTIDA + §4 Semanas 1-2) |
| `governance/sdd-scorecard.json` | (baseline/estado) |
| `governance/sdd-verification-ledger.json` | (baseline/estado) |
| `config/a11y-baseline.json` | (baseline/estado) |
| `config/css-size-baseline.json` | (baseline/estado) |
| `config/design-identity-baseline.json` | (baseline/estado) |
| `config/ds-handoff-baseline.json` | (baseline/estado) |
| `config/eslint-baseline.json` | (baseline/estado) |
| `config/handoff-integrity-baseline.json` | (baseline/estado) |
| `config/pageheader-shared-baseline.json` | (baseline/estado) |
| `config/stylelint-baseline.json` | (baseline/estado) |
| `config/ui-lint-baseline.json` | (baseline/estado) |
| `scripts/casos-coverage-baseline.json` | casos:check (ADR 0264 G-1 trio + G-2 rastreabilidade + G-5 metadata + G-6 frescor + G-7 status derivado) |
| `scripts/casos-test-results.json` | casos status derivado (ADR 0264 G-7 — Status por UC vem do veredito real do teste) |
| `scripts/domain-dict-baseline.json` | dominio:check (ADR 0264 G-4 — dicionário de domínio ⇔ enum de migration + código, Salto #3) |
| `scripts/layout-primitives-baseline.json` | Contagem de flex/grid solto POR ARQUIVO. Gate falha se um arquivo AUMENTAR ou se arquivo novo nascer com flex/grid solto. |
| `scripts/no-mock-baseline.json` | Contagem por REGRA. Gate falha so se uma regra AUMENTAR vs este baseline. |
| `scripts/perf-static-baseline.json` | perf-static-guard (Onda 4 lente 5b — AUDITORIA-PERFORMANCE-2026-07, ratchet advisory) |
| `scripts/reuse-duplicates-baseline.json` | (baseline/estado) |

> Total baselines JSON em governance/+config/+scripts: 46 · (mais ~5 dot-baselines na raiz + fixtures em tests/).
