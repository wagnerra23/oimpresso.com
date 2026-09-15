# Consolidação — as 11 etapas do ciclo, medidas máquina a máquina

> **Frescura conferida por mim** (não herdada): `git -C .../ciclo-docs rev-list --count HEAD..origin/main` → **1** · `origin/main..HEAD` → **6** · `git diff --quiet origin/main HEAD` → **árvore IDÊNTICA**. Meu cwd (`blissful-bartik-5337ab`) está **283 atrás** — tudo abaixo vale contra `origin/main`.

---

## 1. SURPRESAS (o que contradisse o que foi afirmado)

**S0 · A branch ESTÁ mergeada — quem errou foi o agente 10, não você.**
O agente 10 abriu a surpresa dele com *"a branch NÃO está mergeada — 6 commits à FRENTE"*. Medi: é **assinatura de squash**, não de branch pendente.
```
$ gh pr view 5272 --json state,headRefName,mergeCommit
{"state":"MERGED","headRefName":"claude/ciclo-templates-docs","mergeCommit":{"oid":"69039c8cd45..."}}
$ git merge-base --is-ancestor a830036172e origin/main   → falso (os 6 SHAs não entram em main)
$ git log -1 --format="%h parents=%p" 69039c8cd45         → parents=1518c5cd62a  (1 pai = squash)
$ git diff --quiet origin/main HEAD                        → SIM (tree byte-idêntico)
```
`afrente=6` num repo que mergeia por squash é o estado **normal e esperado** de branch mergeada. Contar `origin/main..HEAD` como prova de "não mergeada" é ler ordinal como fato.

**S1 · A numeração das etapas que você usou não é a do doc canônico.**
```
$ git show origin/main:memory/decisions/proposals/2026-08-04-ciclo-completo-responsabilidade-por-maquina.md | sed -n '28,35p'
| **3 · contrato da feature** | ... | **4 · aprovação** | ... | **5 · execução** | ...
```
O doc tem **8 etapas**; sua lista tem **11**. `design` foi inserida na posição 3 ⇒ da 4 em diante há **offset +1** (sua 4 = doc 3; sua 7 = doc 6; sua 10 = doc 8). `deploy` (sua 9) e `aprendizado` (sua 11) **não têm linha** no doc. Achado do agente 4, confirmado por mim.

**S2 · "advisory" aqui não significa "fica vermelho" — significa que NÃO PODE ficar vermelho.**
`pt-conformance` e `design-coverage` engolem o exit do `--check` num `if/then/else` do próprio step:
```
$ node scripts/qa/design-coverage.mjs --check --baseline /tmp/dcbite.json   → exit_script=1
$ if <mesmo comando>; then …; else echo "::warning…"; fi                    → exit_STEP=0
```
Pior: `design-coverage.yml` tem **um único step**, e é esse ⇒ o job não tem **nenhum caminho** para reprovar. Nem selftest ele roda.

**S3 · `shipped-log-gate` é hard-fail que nunca teve como reprovar — laço fechado com o próprio cron.**
`--check` pula todo arquivo `status: parcial` (`shipped-log-generate.mjs:206`); o cron sempre carimba `parcial` (`--until=$(date -u +%F)` → `partial=true`).
```
$ for c in $(git log --format=%H --all -- memory/governance/shipped/); do git show $c:...CYCLE-08.md | sed -n 's/^status: //p'; done | sort | uniq -c
     18 parcial          ← 18 de 18 commits. Nunca existiu um 'ativo'.
```
Se o cron morrer, o registro apodrece e o gate — que existe pra detectar o cron morto — segue verde.

**S4 · Os dois índices de máquinas MENTEM sobre enforcement (medido por mim).**
```
$ git show origin/main:scripts/governance/gates-registry.json | node -e "…"
gate-selftest.yml -> {"nome":"Gate selftest (advisory · …)"}      ← required desde 2026-07-02
anchor-drift.yml  -> {"nome":"… ADVISORY F1 — SA-A2/A3"}          ← 4 dos 7 jobs são required
$ git show origin/main:governance/required-checks-baseline.json | node -e "…"
classic=34 rulesets=1 ; gate selftest presente? [ 'gate selftest (as catracas mordem · GT-G6)' ]
```
E o `_HOOKS-INDEX.md` carimba `exit-2` em 2 hooks do `UserPromptSubmit` — bite-test do agente 1: **0 de 6 bloqueiam nesse evento** (o `exit(2)` está no ramo `PreToolUse`; o gerador detecta por arquivo, não por ramo). Reincidência da lápide §5 2026-07-16, e agentes são mandados a inventariar por esses índices.

**S5 · Required verde ≠ contrato saudável.**
- `anchor entry/covers gate` só passa por grandfather: mesmo comando **sem** `--baseline` → **exit 1**; o baseline perdoa **655 entradas**; `req_sem_aceite=246`, `req_sem_covering_test=350`.
- `Casos-coverage · ratchet` verde com **122 de 206 telas sem `casos.md`** (`--json` → `missing_casos:122`, `exec_backed_pct:28`); `screen-coverage` baseline: **e2e 9/206 · a11y 3/206**.

**S6 · Hooks que mordem menos do que anunciam.**
- `post-merge-ui-smoke-required` morde (exit 2 provado), mas só vê `gh pr merge --admin`: **62 de 907** merges no corpus = **6,8%**.
- `block-ancora-no-olho` é registrado `matcher:"Read|Glob|Grep"` e o código faz `if (toolName !== 'Read') return null` — **2 dos 3 matchers são inertes**, e o teste de registro aceita qualquer matcher.

**S7 · Cron que falhou calado, e a superfície escondeu.**
`mcp:tasks:unassigned` rodou **6 de 8 dias** (faltam 29/07 e 04/08) sem disparar o `onFailure` — o comando não rodou, então o handler nunca dispara. O brief segue fresco porque chama `detectarNaoAtribuidas()` **direto**, sem o cron: a superfície mascara o defeito do medidor.

**S8 · O eixo temporal da âncora mede e ninguém consome.**
`--stale` → 42 stale · 99 frescos · **296 unknown (67,7%)**, 270 por `sha_fora_da_ancestralidade`. O required `SDD scorecard ratchet` invoca `anchor-lint --json` **sem `--stale`** ⇒ `governance/sdd-scorecard.json` grava `anchor_stale_on: false` e 4 campos `null`.

---

## 2. Tabela

| Etapa | Máquina | Veredito | Comando que decidiu |
|---|---|---|---|
| 1 · pedido | 6 hooks `UserPromptSubmit` | **ATIVA-SÓ-RELATA** | bite-test por stdin: 6/6 `exit=0` (4 injetam texto, 2 gravam flag) |
| 2 · requisito | `validate.mjs` (leg SPEC) + `anchor-lint --check` + `--check-entry/--check-covers` | **ATIVA-E-MORDE** | `gh api …/branches/main/protection` contém os 3 contexts; bite good/bad 1/0 nos 3 |
| 3 · design | `visual-regression` | **MORDE** | `grep -c "^        continue-on-error"` → 2 (ambos Lighthouse L5); context no baseline |
| 3 · design | `anchor-content-check --check` | **MORDE** | 0 `continue-on-error` no `anchor-content-required.yml`; context required |
| 3 · design | `pt-conformance` · `design-coverage` | **NÃO MORDE** | `if <cmd>; then…else…fi` → `exit_STEP=0` com fixture ruim |
| 3 · design | hook `block-ancora-no-olho` | **MORDE (parcial)** | `Read` audit-*.png → `exit=2`; `Grep` mesmo path → `exit=0` |
| 4 · contrato da feature | `feature-lint.mjs` | **ATIVA-SÓ-RELATA** | CI roda **sem `--check`**: `EXIT_SEM_CHECK=0` vs `EXIT_COM_CHECK=1` com 4 erros |
| 5 · aprovação | `plan-health.mjs --check` | **ATIVA-SÓ-RELATA** | `gh api …/jobs` → step `exit 1` conclusion=**success**; 121 runs, 0 failure |
| 5 · aprovação | `jana:plan-drift` | **NÃO INVOCADA** | `git grep plan-drift -- app/Console/Kernel.php` → vazio; `schedule:list` prod: ausente |
| 6 · execução | `mcp:tasks:unassigned` · `:health-check` | **ATIVA-SÓ-RELATA** | `schedule:list` prod mostra os 2; Kernel invoca sem `--strict`/`--auto-comment` |
| 7 · contrato da tela | `casos-guard` · `screen-coverage-map` · `charter.schema` · `charter status:live` | **ATIVA-E-MORDE** | `protection-drift.mjs` → 35 vivo = 35 baseline; os 4 contexts presentes |
| 8 · gates | `Governance Gate` (2 yml) + `gate-selftest` | **ATIVA-E-MORDE** | `gate-selftest.mjs` → 72/72; controle-negativo (`exit(1)`→`exit(0)`) → **exit=1** |
| 9 · deploy | `deploy.yml` (classmap/boot/OPcache/failsafe) | **MORDE (eixo deploy)** | 5 steps com `exit 1`; failsafe faz `artisan down` + exit 1 (503, nunca 500) |
| 9 · deploy | hook `post-merge-ui-smoke-required` | **MORDE (6,8%)** | bite: claim→`exit=2`, `git status`→0; 62/907 merges casam o gatilho |
| 9 · deploy | `shipped-log-generate --check` | **ATIVA-SÓ-RELATA (mudo)** | 18/18 commits com `status: parcial`; `--check` pula parcial → `exit=0` |
| 10 · âncora | `anchor-drift.yml` (4 de 7 jobs) | **MORDE** | cruzamento job×baseline: 4 REQUIRED / 3 advisory; 0 `continue-on-error` no arquivo |
| 10 · âncora | `anchor-lint --stale` | **NÃO MORDE** | `node anchor-lint.mjs --stale` → 42 ⏳ e `EXIT=0`; job fora dos 35 |
| 10 · âncora | `ancora-codigo-sync --check` / `--stamp` / `--sync` | **RELATA / 0 INVOCADORES** | `git grep -- "--stamp" \| grep -v ^memory/` → só docblock e selftest |
| 11 · aprendizado | `licoes-code-two-strikes.mjs` | **ATIVA-SÓ-RELATA** | `EXITCODE=0`; job `governance script tests (advisory)` fora dos 35 |

---

## 3. Placar

Unidade = **artefato-máquina** (script/hook/workflow), não "etapa" — várias etapas têm 2-3 máquinas com vereditos opostos.

| Balde | N | Quem |
|---|---|---|
| **MORDEM** | **18 pontos de mordida em 14 artefatos** | 15 contexts required de CI em 11 artefatos (`validate.mjs` ×2 legs · `anchor-lint` ×2 flags · `doneness-lint` · `charter-live-signal` · `visual-regression` · `anchor-content-check` · `casos-guard` · `screen-coverage-map` · `governance-gate.yml` ×2 · `umbrella.yml` ×2 · `gate-selftest`) + 2 hooks `exit 2` + `deploy.yml` |
| **SÓ RELATAM** | **16** | 6 hooks `UserPromptSubmit` · `feature-lint` · `plan-health` · `mcp:tasks:unassigned` · `mcp:tasks:health-check` · `pt-conformance` · `design-coverage` · `anchor-lint --stale` · `ancora-codigo-sync --check` · `licoes-code-two-strikes` · `shipped-log --check` |
| **NÃO INVOCADAS** | **6** | `jana:plan-drift` · `governance-audit.mjs` · `hook-bites.mjs` · `.claude/governance-eval/grade.mjs` · `ancora-codigo-sync --stamp/--sync` · fixture `protection-drift-mojibake` |

**Anti-inflação de censo (agente 2):** `SPEC` + `Charter` são o **mesmo** `validate.mjs`; `anchor-lint ADR 0273` + `anchor entry/covers gate` são o **mesmo** `anchor-lint.mjs`. Contar como 4 máquinas independentes infla o censo em 2.
**Sem bite-test nesta rodada:** `doneness-lint` (required por cruzamento de baseline, mordida não exercitada aqui).

---

## 4. Bugs de invocação (máquina presente e testada que ninguém chama)

Ordenados por **custo de ligar**, baixo → alto. Regra aplicada: `proibicoes.md §Sempre fazer` item 2 (órfão = bug) **e** item 3 (medidor → ligue · ferramenta sob demanda → órfã por design · a que escreve/decide → [W]).

| # | Máquina | Custo | Risco de deixar como está |
|---|---|---|---|
| 1 | fixture `tests/governance-fixtures/protection-drift-mojibake` — `git grep -c` → **1** (só ela mesma) | **mínimo** (1 catraca no `gate-selftest` ou remoção) | Fixture que ninguém consome dá cara de cobertura ao `protection-drift` — o gate que existe por causa do deadlock de 02/07. Teatro barato de matar. |
| 2 | `scripts/governance/hook-bites.mjs` — único hit em `.github/` é o `.test.mjs` | **baixo** (1 step advisory ou cron) | É o **dead-man's-switch dos hooks**. Sem ele, hook que para de morder só aparece por medição manual — foi assim que o `modulo-preflight-warning` ficou **silencioso em 116/116** por 2 meses. Medidor puro: ligar é seguro. |
| 3 | `.claude/governance-eval/grade.mjs` — único hit é a linha 11 dele mesmo | **baixo, mas classificar antes** | Não medi o que ele decide. Se for medidor → ligar; se escrever nota/estado → [W]. Ligar sem classificar é o anti-padrão do item 3. |
| 4 | `scripts/governance/governance-audit.mjs` — **0 de 6** arquivos não-`.md` com invocação (o `Kernel.php:465` é **comentário**; o `governance:audit --all --notify` das 06:35 é o **artisan** do `Modules/Governance`, outro programa) | **médio** (roda N slots, alguns PHP com DB) | Duplo: (a) o eixo agregado não existe; (b) ele é o **único registro** de máquinas mortas, e isso as faz parecer ligadas — foi exatamente o que aconteceu com o `plan-drift`. O próprio repo já documenta o caso em `selftest-registry-check.mjs:215` e não fechou. |
| 5 | `jana:plan-drift` — registrado no artisan (`php artisan list` confirma), **0 invocadores**, vivo desde 2026-06-20 | **alto** (schedule + `mcp_tasks` + ambiente live) | ~1,5 mês sem **nenhum** medidor do eixo "plano declarado × tasks reais". Ligar com `--check` seria promoção — ver §5. |

**Órfão legítimo, NÃO bug:** `ancora-codigo-sync --stamp` / `--sync` — é stamper e escritor de doc, o balde "ferramenta sob demanda" do item 3.

**Adjacente — invocada mas MUDA** (a saída não chega a humano nenhum; mesma família, não é bug de invocação):
- `mcp:tasks:health-check` → **392 flagged hoje** morrem no `laravel.log` (sem `--auto-comment`, sem superfície; o `TasksHealthTool` só existe sob demanda no CT 100).
- `anchor-lint --stale` → 42 stale só no log de um job advisory; o scorecard required grava `null`.
- `shipped-log-gate` → hard-fail estruturalmente impossível (S3).

---

## 5. O que NÃO dá pra ligar sozinho ([W])

| Item | Por que é [W] |
|---|---|
| Promover a **required**: `pt-conformance`, `design-coverage`, `feature-lint --check`, `plan-health`, `casos`/`design` advisory | ADR 0314 + 0327 + **0336** — flip é ato do dono, com **mordida provada** (`design-gate-bites` DR-2). Nenhuma delas tem série de mordida. |
| `mcp:tasks:unassigned --strict` | O próprio `Kernel.php:614-618` declara advisory **de propósito**: *"ligar o ratchet agora seria o anti-padrão foundation-ratchet"*. Com 659-672 pendências, nasceria vermelho permanente. |
| `mcp:tasks:health-check --auto-comment` | **ESCREVE** (posta comentário em task de produção). |
| `ancora-codigo-sync --sync` / `--stamp` | **ESCREVE** doc/carimbo. É o único caminho pra atacar os 270 `sha_fora_da_ancestralidade`, e por isso mesmo não é ato de agente. |
| Ligar `jana:plan-drift` no schedule | Roda contra prod; e o modo útil (`--check`) morde ⇒ é promoção disfarçada de "ligar". |
| Mexer no `governance/anchor-entry-baseline.json` (655 grandfathered) | §5 proíbe editar baseline pra passar; encolher a dívida é decisão de escopo. |
| Tirar o `if/else` de `pt-conformance`/`design-coverage` (deixar o job ficar **vermelho** sem virar required) | Tecnicamente alinhado ao canon (ADR 0271/0275 + §5 2026-07-09: *advisory = não bloqueia, nunca = não pode ficar vermelho*), mas **muda o sinal que o time vê em toda PR**. Proponho em PR; o merge é seu. |
| Consertar `shipped-log --check` (parar de isentar `parcial`) | Hoje não avermelharia (generated=03/08), mas muda quando o gate reprova — FP tem que ser medido antes (§5 2026-07-26). |

**Consertos que NÃO precisam de [W]** (bug de código, PR normal, com FP medido): gerador do `_HOOKS-INDEX` carimbando `exit-2` por arquivo em vez de por ramo de evento; `nome` do `gates-registry` para de restatear enforcement e aponta pro `required-checks-baseline.json` (§5 2026-07-16); matcher `Read|Glob|Grep` do `block-ancora-no-olho` reduzido ao que o código faz (ou o código estendido); crase de markdown que desliga `semGate()` no two-strikes (LC-20 hoje conta como coberto e não deveria); número `≥50` no comentário do `Kernel.php:614` × 659 real.

---

## 6. Discordâncias entre agentes (mostradas, não resolvidas por voto)

| Ponto | Lado A | Lado B | Quem mediu |
|---|---|---|---|
| Branch mergeada? | Agente 10: *"NÃO está mergeada"* — `git rev-list --count origin/main..HEAD` → **6** | Agentes 4/5/7 + eu: **mergeada por squash** — `gh pr view 5272 --json state` → `MERGED`; `git merge-base --is-ancestor` → falso ×6; `git diff --quiet origin/main HEAD` → **SIM** | **Eu** — B está certo; `afrente>0` é a assinatura normal de squash |
| `plan-health` tem quantos invocadores? | Doc canônico (proposal, L32): **3** | Agente 5: **2 reais** (`plan-health-gate.yml` + shell-out do brief 6×/dia) + 1 **decorativo** em agregador morto | Agente 5, com `git grep -l` = 38 arquivos contados |
| Numeração das etapas | Seu prompt: 11 etapas (contrato da feature = 4) | Doc canônico: 8 etapas (contrato da feature = **3**) | **Eu** — `sed -n '28,35p'` no proposal |