---
slug: 0314-poda-gates-onda-2-lei-fusoes
number: 314
title: "Poda de gates onda 2 — LEI definitiva (required que mordem), fusões F1-F5 e deletes verificados (executa a D-4 da 0271)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-30"
accepted_at: ""
accepted_via: "PROPOSTA — Wagner greenlight 'recomendo poda de gates sim' (2026-06-30 chat). Aguarda ratificação por item (ADR 0271 D-4). Redação [CC]."
module: governance
quarter: 2026-Q2
tags: [governance, gates, ci, required, enforcement, subtracao, fusao, anti-elefante-branco, poda]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0261-enforcement-faseado-gates-ci", "0263-identidade-cor-gate-bloqueante", "0264-governanca-executavel-trio-dominio-e2e", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0216-governance-drift-framework-driftchecker-plugavel"]
pii: false
---

# ADR 0314 — Poda de gates onda 2: LEI, fusões, deletes (executa a D-4 da 0271)

> **STATUS: PROPOSTO.** Aguarda ratificação do Wagner item-a-item (a 0271 D-4 reservou esta camada à palavra dele). Cada bloco abaixo tem um checkbox `[ ]` — ratificar = marcar + virar `status: aceito`. Nada é executado antes da ratificação.

## Contexto

A [ADR 0271](../0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) (2026-06-11) fez só a **1ª onda** (64→58: deletes de morto/debug/teatro) e deixou a **D-4 — a poda grande — explicitamente pendente da ratificação do Wagner**, item a item. Ela nunca foi executada.

Desde então o inventário **cresceu 58 → 91 workflows** (+33). Boa parte é legítima (gates SDD/casos/domínio/floor que armamos em junho), mas o sprawl voltou: clusters de gates checando a mesma coisa, "advisory" misturado no required, fusões nunca feitas. Re-auditoria 2026-06-30 (sessão da poda):

- **91 workflows** · **27 required** (branch protection, `enforce_admins: true`).
- **Conflito papel≠máquina vivo:** 2 checks **nomeados "advisory" estão DENTRO do required** — `Tier-0 guards (advisory…)` e `anchor entry/covers gate (advisory)`. Required+advisory na mesma linha é a doença que a 0271 nomeou.
- **Clusters de fusão confirmados** (cor/UI 7 · memória 8 · RAGAS/Jana 5 · drift 5 · trio-tela 6).
- **`governance-drift` (ADR 0216) já "orquestra TODOS os DriftCheckers"** — os outros drift-gates podem foldar nele como plugins (fusão sem perda).

Regra-mestra desta onda (igual à 0271): **nenhuma proteção real sai** — só morto, teatro, redundância e bomba armada. Fundir = o gate fundido roda TODOS os sub-checks + o required-checks do branch protection é atualizado no mesmo PR (sem janela descoberta).

## D-1 — LEI: o required que DEVE morder (proposta)

Critério LEI: **só fica required o que evita catástrofe Tier-0 ou quebra de correção do núcleo.** O resto continua rodando e mostrando vermelho — mas advisory (fora do required), promovível por calendário (ADR 0275). Demover ≠ apagar: o gate segue lá, só não bloqueia.

**[ ] LEI proposta (15 — fica required):**

| Gate (check name) | Por que LEI |
|---|---|
| No hardcode business_id (Tier 0) | multi-tenant — pior bug possível |
| PHP / Pest (Unit) | correção do núcleo |
| PHP / Pest (Financeiro · MySQL) | dinheiro |
| Frontend / Vite build | app não compila = prod quebrada |
| PII scan (CPF/CNPJ literal) | LGPD |
| Secret scan (gitleaks · linhas novas) | credencial vazada |
| Append-only canon | integridade da governança |
| No-mock-in-prod · ratchet | mock servido a cliente |
| Dominio-dict · ratchet | enum ⇔ dicionário (ADR 0264, flip [W]) |
| Casos-coverage · ratchet | trio-de-tela (ADR 0264, flip [W]) |
| PHPStan / Larastan · ratchet | regressão de tipo |
| ADR frontmatter | canon válido (barato) |
| ADR 0216 PR scan | drift de governança no diff |
| memory-health (ADR 0256) | base de conhecimento podre |
| SDD scorecard ratchet (GT-G3) | governador do floor (no-op até armar, mas é a catraca-mãe) |

**[ ] DEMOVER de required → advisory (12 — segue visível, não bloqueia):**

| Gate | Por que sai do required |
|---|---|
| module-grades-gate | check **mais caro do repo**, métrica composta, não-catástrofe (a própria 0271 D-4 já propôs rebaixar) |
| visual-regression | flaky — mergeou vermelho 2× em 24h (#2544/#2548); advisory até estabilizar |
| E2E Playwright · UCs críticos | lento; quality-gate, não catástrofe |
| A11y axe | quality, não catástrofe |
| Foundation ratchet | quality (quarentena/RefreshDatabase) |
| charter_refs_broken <= teto | já é "advisory na adoção" por design |
| Conformance · cor-crua | vai pra fusão F1 (vira 1 gate DS) |
| UI Lint · ratchet | vai pra fusão F1 |
| Detectar bucket sem label (ADR 0160) | processo, não catástrofe |
| Jana recall-eval (mock) | mock — teatro até RAGAS real (P12) |
| **Tier-0 guards (advisory)** | **JÁ nomeado advisory — required era bug** |
| **anchor entry/covers (advisory)** | **JÁ nomeado advisory — required era bug** |

> Resultado D-1: required **27 → 15**. (Os 2 "advisory-no-required" saem por correção, não por decisão de risco.)

## D-2 — Fusões (5 clusters · preservam todos os sub-checks)

**[ ] F1 · DS/Cor (7→1):** `conformance-gate` + `css-size-gate` + `ds-canon-color-guard` + `ui-lint` + `design-index-gate` + `bundle-lint` + `scorer-sync-gate` → **1 workflow `ds-gate.yml`** com 1 job por sub-check (mesmos scripts, mesmos baselines, mesmo exit). Required: 1 entrada `DS gate` (roda cor-crua + ui-lint que eram LEI). Mata os "4 baselines de cor" que a 0271 nomeou como conflito.

**[ ] F2 · Memória/schema (8→3):** `memory-schema-gate` + `memory-schema-gate-extended` → **1** (mesma fonte schema). `component-registry` + `design-memory-gates` + `dtcg-equivalence` (todos advisory de design-memory) → **1 `design-memory-gate`**. `memory-health` + `knowledge-ghost-gate` + `anchor-drift` ficam separados (semânticas distintas). 8→3.

**[ ] F3 · RAGAS/Jana (5→3):** `jana-ragas-gate` (já desarmado) + `jana-ragas-canary` → **1** (PR-mock + cron-real no mesmo arquivo). `jana-pest` e `jana-logica-pura-pest` (este já fundiu 3) ficam. `jana-recall-eval` fica (vira advisory por D-1). 5→3.

**[ ] F4 · Drift→plugin (5→2):** `governance-drift` (ADR 0216) JÁ é o orquestrador de DriftCheckers → foldar `protection-drift` + `anchor-drift` + `outcome-metrics` como checkers plugáveis dentro dele. `mcp-drift-sentinel` fica (sentinela EXTERNA do CT 100, cron — natureza diferente). 5→2.

**[ ] F5 · Trio-tela (6→4):** `casos-gate` + `dominio-gate` (LEI, ficam) + `screen-coverage-gate` ficam; `charter-refs-gate` + `charter-us-gate` + `contrato-de-tela` → avaliar fusão das 2 réguas de charter (`charter-refs` + `charter-us`) → **1**. 6→5 (conservador; contrato-de-tela é a perna visual nova, mantém separada).

## D-3 — Deletes verificados (one-shots de incidente fechado)

**[x] DELETE (verificado zero reuso via `gh run list` — ambos 0 runs):**

| Workflow | Por quê | Estado |
|---|---|---|
| `run-financeiro-resync.yml` | one-shot do incidente num_uf (#2279/#2280) — **fechado** | ✅ **DELETADO** ([PR #3455](https://github.com/wagnerra23/oimpresso.com/pull/3455), 2026-06-30) — + sync de registro (`gates-registry.json` + `checkM`) |
| `create-test-business.yml` | one-shot biz=99 (idempotente, já criado) | ✅ **DELETADO** ([PR #3455](https://github.com/wagnerra23/oimpresso.com/pull/3455), 2026-06-30) — + sync de registro |
| `run-financeiro-demo-seeder.yml` | one-shot seed mock biz=1 (2026-05-20) | ⏸️ **NÃO deletado** — bloco à parte: tem teste acoplado (`DemoSeederProtectsRealBusinessTest` lê o `.yml`), exige remover const+asserção no mesmo PR |
| `force-clean-rebuild-trigger.yml` | nuclear rebuild; dispara só em push a branch dedicada | ❌ **MANTER** (decidido [W]) — NÃO é one-shot, é escape-hatch de recovery citado em ≥8 runbooks/ADRs |

> Deletes só apagam dispatch-only utilities de incidente fechado — nenhum gate, nenhum trigger de PR/push normal. Reversível 100% (git history).
>
> **Trava de sincronia de registro (cumprida no #3455):** todo workflow vive em `gates-registry.json` (chave `workflows.<arquivo>`) + `.memory-health-baseline.json` (`checkM` — array). O gate `memory-health` (LEI/required) falha se divergir. O #3455 removeu as 2 chaves + as 2 entradas do `checkM` no mesmo PR; `node scripts/governance/memory-health.mjs` → exit 0 provado antes do PR. CI verde (48 checks).

## Consequências

- **91 → ~70 workflows** (F1 −6, F2 −5, F3 −2, F4 −3, F5 −1, D-3 −3/−4) na 1ª execução; required **27 → 15**.
- Papel volta a bater com máquina: zero "advisory-no-required", zero "4 baselines de cor".
- Execução: **1 PR por bloco ratificado** (F1, F2, … isolados; cada um preserva sub-checks + atualiza branch protection no mesmo PR; smoke do gate fundido antes de mexer no required).
- Risco residual: fundir errado = perder um sub-check silenciosamente. Mitigação: cada PR de fusão roda o gate fundido contra um diff que SABE-se que deveria falhar (counterfactual por sub-check) antes de remover os antigos.

## Métricas

- Workflows: 91 → ~70 (onda 2) · required: 27 → 15 · conflitos papel≠máquina: 2 → 0 · clusters redundantes: 5 → 0.

## Ratificação (Wagner marca o que aprova)

- [ ] D-1 LEI-15 + demoção dos 12 (ou ajusta a lista)
- [ ] F1 DS/Cor · [ ] F2 Memória · [ ] F3 RAGAS · [ ] F4 Drift-plugin · [ ] F5 Trio-tela
- [x] **D-3 deletes — EXECUTADO** ([PR #3455](https://github.com/wagnerra23/oimpresso.com/pull/3455), merged 2026-06-30): `run-financeiro-resync` + `create-test-business` deletados com sync de registro; `force-clean-rebuild` = MANTER (decidido [W]); `run-financeiro-demo-seeder` = bloco à parte (teste acoplado)

Ao ratificar: vira `status: aceito`, sai de `proposals/`, ganha número canon, e executo 1 PR por bloco.

> **Log de execução:**
> - **2026-06-30 — D-3 (parcial):** 2 dos 3 deletáveis executados via [PR #3455](https://github.com/wagnerra23/oimpresso.com/pull/3455) (Wagner pré-aprovou em sessão nova). Restam desta onda: `demo-seeder` (bloco à parte com teste acoplado) + os blocos D-1/F1/F2/F5 (F3/F4 retiradas na v3 da exploração de execução). Esta cópia em `main` estava na v2 — os blocos de fusão/LEI seguem como na v2 até ratificação por bloco.
