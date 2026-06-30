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

> 🔧 **Regra de sincronia de REGISTRO (descoberta na exploração de execução 2026-06-30):** todo `.github/workflows/*.yml` está registrado em DOIS lugares que o CI valida — `scripts/governance/gates-registry.json` (chave `workflows.<arquivo>`) e `scripts/governance/.memory-health-baseline.json` (`checkM.<n> = <arquivo>`). O **`memory-health` (LEI/required)** falha se o conjunto de workflows divergir do baseline. Logo: **qualquer DELETE ou FUSÃO de workflow DEVE atualizar os 2 registros no MESMO PR** (remover a chave em `gates-registry`, remover/reindexar a entrada em `checkM`). Sem isso, o próprio gate LEI fica vermelho. É o equivalente registry-level do "fundir atualiza branch protection no mesmo PR".

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

**[x] F2 · Memória/schema (8→3) — EXECUTADO** ([PR #3459](https://github.com/wagnerra23/oimpresso.com/pull/3459), merged 2026-06-30): `memory-schema-gate` + `memory-schema-gate-extended` → **1** (mesma fonte schema). `component-registry` + `design-memory-gates` + `dtcg-equivalence` (advisory de design-memory) → **1 `design-memory-gate`**. `memory-health` + `knowledge-ghost-gate` + `anchor-drift` ficam separados (semânticas distintas). 8→3 feito, com sync de registro no mesmo PR.

**[~~ ~~] F3 · RAGAS/Jana — ❌ RETIRADA (exploração de execução 2026-06-30 não achou alvo limpo):** a v2 propunha fundir `jana-ragas-gate` + `jana-ragas-canary`. Lendo os 2 a fundo: **não são redundantes** — `jana-ragas-gate` é PR-gate de threshold ABSOLUTO (faith≥0.80, comenta no PR, bloqueia em real); `jana-ragas-canary` é cron diário de regressão RELATIVA vs baseline (>5% drift, abre issue, atualiza baseline). Compartilham o comando `jana:ragas-ci-eval` mas fazem coisas diferentes e foram **intencionalmente separados** (cada header cross-referencia o outro). O workflow genuinamente redundante era o `ragas-gate.yml` (W22 MVP) — **já deletado**. Fundir os 2 = combinar 2 mecanismos distintos por −1 arquivo, com risco de emaranhar o issue-open/baseline-update do canary no comment-logic do gate. **Não vale.** `jana-recall-eval` segue (vira advisory por D-1). **F3 sai da onda.**

**[ ] F4 · Drift — ❌ REJEITADA pelo adversário (não é fusão sem perda):** `governance-drift` orquestra **classes PHP** que implementam `Modules\Governance\Contracts\DriftChecker` (11 registradas no `GovernanceServiceProvider`). Os 3 alvos são **scripts Node `.mjs`** (`protection-drift.mjs`, `anchor-lint.mjs`, `outcome-metrics.mjs`) — foldar = **reescrever cada um como classe PHP** (porte caro, risco de perder cobertura na tradução), não plug. Agravantes: `anchor-drift.yml` **hospeda o required `anchor entry/covers`** (LEI — fundir dissolveria o host de um required); `protection-drift.mjs` é a **sentinela que lê o `required-checks-baseline.json`** (vigia o próprio sistema de required — natureza distinta de um DriftChecker de conteúdo). **Mantém os 5 separados nesta onda.** Se valer a pena depois, vira tarefa "porte .mjs→DriftChecker" própria, fora da poda.

**[~~ ~~] F5 · Trio-tela — ❌ FUSÃO-CHARTER RETIRADA (avaliação de execução 2026-06-30 — réguas ortogonais):** o `casos-gate` + `dominio-gate` (LEI) + `screen-coverage-gate` + `contrato-de-tela` já ficavam separados; a ÚNICA fusão proposta era `charter-refs-gate` + `charter-us-gate` → 1. Lendo os 2 a fundo (YAML + scripts + baseline + branch protection): **não são a mesma régua.** `charter-refs` mede **integridade de PATHS** (os refs/links do charter resolvem on-disk?) — varre a árvore INTEIRA vs teto numérico (`charter-refs-baseline.json` ceiling=2, ratchet-down), espelha `CharterHealthChecker.php`, tem self-test HARD + `--fix`, e **É REQUIRED** (`charter_refs_broken <= teto` confirmado no branch protection). `charter-us` mede **rastreabilidade** (o charter declara `related_us`?) — diff-aware (só charters tocados mordem), **sem floor** (129 legados não avermelham), CI-only, **ADVISORY-de-nascença** (`promote_by 2026-09-30`, ADR 0275 §5). Compartilham SÓ o glob `*.charter.md` — como casos/domínio/screen-coverage/contrato, que a própria F5 mantém separados (mesmo arquivo-alvo ≠ mesma régua). Fundir = acoplar 1 required + 1 advisory: ou **arma o `related_us` lint como required antes do soak** (viola o calendário 0275 + footgun da ADR 0261), ou **rebaixa a catraca de integridade required pra advisory** (perde proteção real que espelha o health-check). Triggers incompatíveis (`charter-refs` sem path-filter por require-safe vs `charter-us` path-filtered+cron) e grandfather opostos (teto numérico vs git-diff) selam: **−1 arquivo por emaranhar 2 mecanismos distintos. Não vale.** F5 não funde nada — 6→6. **F5 sai da onda.** (É o caso F3 RAGAS repetido: mesmo glob/nome ≠ redundância.)

## D-3 — Deletes verificados (one-shots de incidente fechado)

**[x] DELETE — 2 de 3 EXECUTADOS ([PR #3455](https://github.com/wagnerra23/oimpresso.com/pull/3455), merged 2026-06-30; zero reuso confirmado, ambos 0 runs):**

| Workflow | Veredito (pós-adversário) |
|---|---|
| `run-financeiro-resync.yml` | ✅ **DELETADO (#3455)** — dispatch-only, 0 runs · sync de registro feito (`gates-registry.json` chave + `checkM`) |
| `create-test-business.yml` | ✅ **DELETADO (#3455)** — dispatch-only, idempotente · sync de registro feito (`gates-registry.json` chave + `checkM`) |
| `run-financeiro-demo-seeder.yml` | ⚠️ **delete COM coupling** — `Modules/Financeiro/Tests/Feature/DemoSeederProtectsRealBusinessTest.php` faz `file_get_contents` do .yml e asserta `default: '99'`. Deletar o .yml SÓ junto com a remoção do const + asserção **no mesmo PR** + sync registro, senão Financeiro + memory-health ficam vermelhos |
| `force-clean-rebuild-trigger.yml` | ❌ **MANTER** — NÃO é one-shot (tem trigger `push` em branch dedicada), é escape-hatch de recovery citado em ≥8 runbooks/ADRs. A poda v1 de 22/jun foi invalidada por mexer em escape-hatch |

> ⚠️ **Nenhum delete é "puro"** (exploração 2026-06-30): os 3 deletáveis estão em `gates-registry.json` + `.memory-health-baseline.json` (`checkM`). Cada PR de delete **obrigatoriamente** remove as 2 entradas de registro (ver Regra de sincronia de registro no §Contexto), senão o `memory-health` (LEI) fica vermelho. Reversível 100% (git history).

## Consequências

- **91 → ~80 workflows** (F1 −6, F2 −5, D-3 −3; **F3, F4 e a fusão-charter da F5 retiradas**) na 1ª execução; required **27 → ~18**.
- Papel volta a bater com máquina: zero "4 baselines de cor"; e o invariante DURO: **nenhum gate multi-tenant/dinheiro/PII/fiscal sai do required** (lição do adversário).
- Execução: **1 PR por bloco ratificado** (F1, F2, … isolados; cada um preserva sub-checks + atualiza branch protection **+ os 2 registros** no mesmo PR; smoke do gate fundido antes de mexer no required).
- Risco residual: fundir errado = perder um sub-check silenciosamente. Mitigação: cada PR de fusão roda o gate fundido contra um diff que SABE-se que deveria falhar (counterfactual por sub-check) antes de remover os antigos.

## Métricas

- Workflows: 91 → ~80 (onda 2) · required: 27 → ~18 · clusters redundantes fundidos: 2 (F1/F2; F3+F4+fusão-charter-F5 fora) · deletes: 2 limpos + 1 acoplado.

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

## Exploração de execução (v3 · 2026-06-30 — auto-adversário antes de codar)

Wagner deu "pode fazer" pra começar pela F3. Ao abrir os arquivos pra executar, 2 achados que retiram/refinam blocos (rigor do adversário aplicado a mim mesmo):

- **F3 RETIRADA.** Os 2 RAGAS workflows não são redundantes (gate de threshold absoluto vs canary de drift relativo — intencionalmente separados); o redundante de verdade (`ragas-gate.yml` W22 MVP) já foi deletado. Fundir = emaranhar 2 mecanismos por −1 arquivo. Não vale. (Detalhe no bloco F3.)
- **Nenhum delete/fusão é "puro": regra de sincronia de registro.** Todo workflow está em `gates-registry.json` + `.memory-health-baseline.json` (`checkM`), e o `memory-health` (LEI) falha se divergir. Cada PR de delete/fusão tem que sincronizar os 2 registros — senão eu mesmo deixo o gate LEI vermelho. (Regra no §Contexto.)
- **F5 fusão-charter RETIRADA (avaliação de execução 2026-06-30).** Wagner pré-aprovou executar a F5; ao abrir os 2 arquivos pra fundir, `charter-refs` (integridade de PATHS, **required**, ratchet numérico, espelha `CharterHealthChecker.php`) e `charter-us` (rastreabilidade `related_us`, **advisory**-de-nascença, diff-aware sem floor) **não são a mesma régua** — só compartilham o glob `*.charter.md`. Fundir acoplaria 1 required + 1 advisory (armaria o advisory antes do soak OU rebaixaria o required) e emaranharia triggers/grandfather opostos. F5 não funde nada (6→6). Caso F3 RAGAS repetido. (Detalhe no bloco F5.)

Lição perene: a poda parece "deletar arquivo", mas é cirurgia de registro — por isso 1 PR por bloco, com counterfactual, e nada executado no fim de sessão longa.

## Ratificação (Wagner marca o que aprova)

- [ ] D-1 LEI (núcleo + os 4 resgatados: Tier-0 guards · anchor entry/covers · visual-regression · NfeBrasil) + 7 demoções reais (ou ajusta)
- [ ] F1 DS/Cor _(em PR aberto [#3456](https://github.com/wagnerra23/oimpresso.com/pull/3456))_ · [x] **F2 Memória — EXECUTADO** ([#3459](https://github.com/wagnerra23/oimpresso.com/pull/3459)) · [ ] ~~F5 Trio-tela~~ (fusão-charter retirada — réguas ortogonais) · [ ] ~~F3 RAGAS~~ (retirada — sem alvo limpo) · [ ] ~~F4 Drift~~ (rejeitada)
- [x] **D-3 deletes — EXECUTADO (parcial)** ([#3455](https://github.com/wagnerra23/oimpresso.com/pull/3455)): resync + test-business deletados **com sync de registro**; force-clean = MANTER; **resta** demo-seeder (bundlado com o teste acoplado)

Ao ratificar o resto: vira `status: aceito`, sai de `proposals/`, ganha número canon, 1 PR por bloco. Pendente: **D-1 LEI** (mexe em required — branch protection no mesmo PR), e fechar **F1** (#3456) + o **demo-seeder** do D-3.

## Log de execução

| Data | Bloco | PR(s) | Estado |
|---|---|---|---|
| 2026-06-30 | **D-3 (parcial)** | [#3455](https://github.com/wagnerra23/oimpresso.com/pull/3455) deletes · [#3458](https://github.com/wagnerra23/oimpresso.com/pull/3458) roadmap | ✅ `resync` + `test-business` deletados c/ sync de registro (`gates-registry.json` + `checkM`); `memory-health` verde. Resta `demo-seeder` (teste acoplado). |
| 2026-06-30 | **F2 Memória/schema** | [#3459](https://github.com/wagnerra23/oimpresso.com/pull/3459) | ✅ 8→3: `memory-schema-gate-extended` foldado em `memory-schema-gate`; `component-registry` + `dtcg-equivalence` foldados em `design-memory-gate`; sync de registro no mesmo PR. |
| 2026-06-30 | reconciliação v2→v3 | _(este PR)_ | A cópia em `main` estava na v2 (merge do #3452 pegou v2; a v3 — F3 retirada, F4 rejeitada, regra de sincronia de registro, +4 LEI resgatados — ficou na branch não-remergeada). Este PR traz a v3 pra `main` + marca D-3 e F2 executados. |
| _em PR_ | **F1 DS/Cor** | [#3456](https://github.com/wagnerra23/oimpresso.com/pull/3456) aberto | 7→1 `ds-gate.yml` (mexe em required — branch protection no mesmo PR). |
| 2026-06-30 | **F5 Trio-tela** | _(este PR)_ | ❌ fusão-charter **RETIRADA**: `charter-refs` (required, integridade de paths, ratchet numérico) e `charter-us` (advisory, rastreabilidade `related_us`, diff-aware) não são a mesma régua — fundir acoplaria required+advisory. F5 não funde nada (6→6). Zero mudança em workflow/required/registro. |
| _pendente_ | D-1 LEI · demo-seeder | — | aguarda ratificação/execução por bloco ([W]). |
