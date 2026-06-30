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
- **Clusters de fusão confirmados** (cor/UI 7 · memória 8 · RAGAS/Jana 5 · drift 5 · trio-tela 6).

> ⚠️ **Correção pós-adversário (2026-06-30):** a v1 desta proposta afirmava que 2 checks "advisory" estavam no required por bug (papel≠máquina). **FALSO** — `Tier-0 guards` e `anchor entry/covers` foram **deliberadamente ARMADOS e promovidos a required nos últimos 6 dias** (PR #3438 / #3320); o "advisory" no nome é **label congelado** (context-string do binding do required — renomear quebra), NÃO estado de `continue-on-error`. Eles FICAM LEI. A v1 também assumia que o `governance-drift` (ADR 0216) acolhe os outros drift-gates como plugin "sem perda" — **FALSO**: ele orquestra **classes PHP `DriftChecker`**; os alvos são **scripts Node `.mjs`** → não é plug, é porte/reescrita (F4 rejeitada, ver D-2). Detalhe no §Adversário ao fim.

Regra-mestra desta onda (igual à 0271): **nenhuma proteção real sai** — só morto, teatro, redundância e bomba armada. Fundir = o gate fundido roda TODOS os sub-checks + o required-checks do branch protection é atualizado no mesmo PR (sem janela descoberta).

## D-1 — LEI: o required que DEVE morder (proposta)

Critério LEI: **só fica required o que evita catástrofe Tier-0 ou quebra de correção do núcleo.** O resto continua rodando e mostrando vermelho — mas advisory (fora do required), promovível por calendário (ADR 0275). Demover ≠ apagar: o gate segue lá, só não bloqueia.

**[ ] LEI proposta (fica required):** _(v2: +4 que o adversário resgatou — multi-tenant/fiscal recém-armados. Contagem exata fecha na execução; o que importa é que estes ficam bloqueantes)_

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
| **Tier-0 guards** (`tier0-guards-advisory.yml`) | **multi-tenant** — `WithoutGlobalScopes`+`BusinessId` guards (ADR 0093). ARMADO 30/jun #3438; "advisory" é label congelado, não estado |
| **anchor entry/covers** (`anchor-drift.yml`) | gate de entrada SDD (US sem aceite/teste-que-cobre). Promovido a required 30/jun #3320 (require-safe) |
| **visual-regression** (render-isolation) | carrega `Tier0RenderIsolationTest` BLOQUEANTE — prova que biz=1 não vaza biz=99 no render (ADR 0093 #1). NÃO confundir com o pixel-diff flaky (passo separado) |
| **PHP / Pest (NfeBrasil · MySQL)** | **fiscal/dinheiro** — nota errada ×150 clientes; lane MySQL que roda as regras tributárias que o sqlite pula. Armado (continue-on-error removido) |

**[ ] DEMOVER de required → advisory (9 — segue visível, não bloqueia):**

| Gate | Por que sai do required |
|---|---|
| module-grades-gate | check **mais caro do repo**, métrica composta, não-catástrofe (a própria 0271 D-4 já propôs rebaixar) |
| E2E Playwright · UCs críticos | lento; quality-gate, não catástrofe |
| A11y axe | quality, não catástrofe |
| Foundation ratchet | quality (quarentena/RefreshDatabase) |
| charter_refs_broken <= teto | já é "advisory na adoção" por design |
| Detectar bucket sem label (ADR 0160) | processo, não catástrofe |
| Jana recall-eval (mock) | mock — morde canon-integrity mas não é Tier-0 |

> **Conformance · cor-crua** e **UI Lint** NÃO demovem pra advisory — **movem pra dentro do `ds-gate` fundido (F1) seguindo bloqueantes** (required vira 1 contexto novo, trocado atomicamente). Não são perda de proteção.
>
> Resultado D-1: required **27 → ~18** (7 demoções reais acima + Conformance/UI-Lint que viram 1 contexto via F1). A contagem exata fecha no PR de execução; o invariante é: **nenhum gate multi-tenant/dinheiro/PII/fiscal sai do required**.

## D-2 — Fusões (5 clusters · preservam todos os sub-checks)

**[ ] F1 · DS/Cor (7→1):** `conformance-gate` + `css-size-gate` + `ds-canon-color-guard` + `ui-lint` + `design-index-gate` + `bundle-lint` + `scorer-sync-gate` → **1 workflow `ds-gate.yml`** com 1 job por sub-check (mesmos scripts, mesmos baselines, mesmo exit). Required: 1 entrada `DS gate` (roda cor-crua + ui-lint que eram LEI). Mata os "4 baselines de cor" que a 0271 nomeou como conflito.

**[ ] F2 · Memória/schema (8→3):** `memory-schema-gate` + `memory-schema-gate-extended` → **1** (mesma fonte schema). `component-registry` + `design-memory-gates` + `dtcg-equivalence` (todos advisory de design-memory) → **1 `design-memory-gate`**. `memory-health` + `knowledge-ghost-gate` + `anchor-drift` ficam separados (semânticas distintas). 8→3.

**[ ] F3 · RAGAS/Jana (5→3):** `jana-ragas-gate` (já desarmado) + `jana-ragas-canary` → **1** (PR-mock + cron-real no mesmo arquivo). `jana-pest` e `jana-logica-pura-pest` (este já fundiu 3) ficam. `jana-recall-eval` fica (vira advisory por D-1). 5→3.

**[ ] F4 · Drift — ❌ REJEITADA pelo adversário (não é fusão sem perda):** `governance-drift` orquestra **classes PHP** que implementam `Modules\Governance\Contracts\DriftChecker` (11 registradas no `GovernanceServiceProvider`). Os 3 alvos são **scripts Node `.mjs`** (`protection-drift.mjs`, `anchor-lint.mjs`, `outcome-metrics.mjs`) — foldar = **reescrever cada um como classe PHP** (porte caro, risco de perder cobertura na tradução), não plug. Agravantes: `anchor-drift.yml` **hospeda o required `anchor entry/covers`** (LEI — fundir dissolveria o host de um required); `protection-drift.mjs` é a **sentinela que lê o `required-checks-baseline.json`** (vigia o próprio sistema de required — natureza distinta de um DriftChecker de conteúdo). **Mantém os 5 separados nesta onda.** Se valer a pena depois, vira tarefa "porte .mjs→DriftChecker" própria, fora da poda.

**[ ] F5 · Trio-tela (6→4):** `casos-gate` + `dominio-gate` (LEI, ficam) + `screen-coverage-gate` ficam; `charter-refs-gate` + `charter-us-gate` + `contrato-de-tela` → avaliar fusão das 2 réguas de charter (`charter-refs` + `charter-us`) → **1**. 6→5 (conservador; contrato-de-tela é a perna visual nova, mantém separada).

## D-3 — Deletes verificados (one-shots de incidente fechado)

**[ ] DELETE (após confirmar zero reuso recente via `gh run list`):**

| Workflow | Veredito (pós-adversário) |
|---|---|
| `run-financeiro-resync.yml` | ✅ **SAFE delete** — dispatch-only, 0 runs recentes, comando `financeiro:resync-from-core` preservado |
| `create-test-business.yml` | ✅ **SAFE delete** — dispatch-only, idempotente, seeder subjacente preservado |
| `run-financeiro-demo-seeder.yml` | ⚠️ **delete COM coupling** — `Modules/Financeiro/Tests/Feature/DemoSeederProtectsRealBusinessTest.php` faz `file_get_contents` do .yml e asserta `default: '99'`. Deletar o .yml SÓ junto com a remoção do const + asserção **no mesmo PR**, senão a suite Financeiro fica vermelha |
| `force-clean-rebuild-trigger.yml` | ❌ **MANTER** — NÃO é one-shot (tem trigger `push` em branch dedicada), é escape-hatch de recovery citado em ≥8 runbooks/ADRs. A poda v1 de 22/jun foi invalidada por mexer em escape-hatch |

> Deletes só apagam dispatch-only utilities de incidente fechado — nenhum gate, nenhum trigger de PR/push normal. Reversível 100% (git history). O demo-seeder exige o PR bundlar a remoção do teste-acoplado.

## Consequências

- **91 → ~76 workflows** (F1 −6, F2 −5, F3 −2, F5 −1, D-3 −3; **F4 removida**) na 1ª execução; required **27 → ~18**.
- Papel volta a bater com máquina: zero "4 baselines de cor"; e o invariante DURO: **nenhum gate multi-tenant/dinheiro/PII/fiscal sai do required** (lição do adversário).
- Execução: **1 PR por bloco ratificado** (F1, F2, … isolados; cada um preserva sub-checks + atualiza branch protection no mesmo PR; smoke do gate fundido antes de mexer no required).
- Risco residual: fundir errado = perder um sub-check silenciosamente. Mitigação: cada PR de fusão roda o gate fundido contra um diff que SABE-se que deveria falhar (counterfactual por sub-check) antes de remover os antigos.

## Métricas

- Workflows: 91 → ~76 (onda 2) · required: 27 → ~18 · clusters redundantes fundidos: 4 (F4 fica) · deletes seguros: 2 + 1 acoplado.

## Adversário (review 2026-06-30 — antes da ratificação)

Wagner pediu um adversário antes de ratificar. Rodou (general-purpose, 31 tool-uses, evidência file:line) e pegou **7 CONFIRMED** que a v1 errou — 3 CRÍTICOS de multi-tenant Tier-0:

1. **CRÍTICO** — demover `Tier-0 guards` era regressão multi-tenant; premissa "advisory=bug" **invertida** (armado 30/jun #3438). → resgatado pra LEI.
2. **CRÍTICO** — `anchor entry/covers` promovido a required 30/jun #3320 (não-bug). → LEI.
3. **CRÍTICO** — `visual-regression` carrega o `Tier0RenderIsolationTest` BLOQUEANTE (ZZLEAK99); flakiness é de outro passo. → LEI (separar só o pixel-diff se doer).
4. **ALTO** — `NfeBrasil Pest MySQL` dropado por omissão (fiscal ×150). → LEI.
5. **ALTO** — F4 falsa (PHP DriftChecker ≠ scripts .mjs; anchor-drift hospeda um required). → F4 rejeitada.
6. **MÉDIO** — demo-seeder tem teste acoplado que lê o .yml. → delete bundlado.
7. **MÉDIO** — force-clean-rebuild é escape-hatch, não one-shot. → manter.

Esta v2 incorpora os 7. Veredito do adversário sobre o esqueleto: **correto** (subtração sem perder proteção + counterfactual por sub-check). A falha foi factual (gates recém-armados nos últimos 6 dias pelo próprio SDD), não de método.

## Ratificação (Wagner marca o que aprova)

- [ ] D-1 LEI (núcleo + os 4 resgatados: Tier-0 guards · anchor entry/covers · visual-regression · NfeBrasil) + 7 demoções reais (ou ajusta)
- [ ] F1 DS/Cor · [ ] F2 Memória · [ ] F3 RAGAS · [ ] F5 Trio-tela · [ ] ~~F4 Drift~~ (rejeitada — fica fora)
- [ ] D-3 deletes (resync + test-business SAFE; demo-seeder bundlado; force-clean = MANTER)

Ao ratificar: vira `status: aceito`, sai de `proposals/`, ganha número canon, e executo 1 PR por bloco (recomendo começar por F3 RAGAS — menor blast-radius — antes de F1 cor que é required).
