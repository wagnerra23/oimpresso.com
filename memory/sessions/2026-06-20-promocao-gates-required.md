---
date: "2026-06-20"
topic: "PROPOSTA [W] — promover a required os gates que mordem mas ficam fora do baseline + o pixel-diff advisory. Scorecard por gate vs protection vivo, baseline e o calendário (draft) da ADR 0275/0261/0271."
authors: [C]
related_adrs: ["0261-enforcement-faseado-gates-ci", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"]
prs: []
---

# Promoção de gates a required — PROPOSTA, não execução

> **Natureza:** proposta para decisão **[W]**. NADA foi aplicado — branch protection e
> `governance/required-checks-baseline.json` **não** foram tocados (regra do baseline + ADR 0271 §Autoridade: branch protection é admin-only, Tier 0, só Wagner aprova/reverte).
> **Origem:** auditoria de sentinelas 2026-06-20 (PRs #3098/#3100) achou gates que fazem `exit 1` de verdade mas cujo *context* não está no required → ficam 🔴 e ainda assim o PR mergeia.
> **Fonte da verdade usada:** `gh api .../branches/main/protection` (vivo, lido 2026-06-20) + `governance/required-checks-baseline.json` (congelado 2026-06-12) + `gh run list` (vivo) + `.yml`/`git log` **re-lidos de `origin/main @ e765b4a7f3`** (a árvore de trabalho local estava 509 commits atrás — reads iniciais corrigidos contra origin/main).
> **Coordenação:** há sessão paralela ativa armando gates (worktree `arm-gates`, branch `docs/fix-sdd-ghost-rule`, com incidente de sessão-duplicada já registrado em #3092). Esta proposta **não abriu PR nem tocou protection** — execução precisa coordenar com essa sessão pra não duplicar.

---

## 0. TL;DR — o que mudou desde a premissa da auditoria

A lista da auditoria tinha 8 gates. Cruzando com o **protection vivo** (não só o baseline congelado), a foto real é diferente em 2 pontos que mudam a ação:

1. **`a11y-axe-gate` JÁ É REQUIRED ao vivo** — o context `A11y axe · runtime nos componentes canon` está na lista required do `main` **hoje**, somado *depois* do congelamento do baseline (2026-06-12). Ele **não** está no `required-checks-baseline.json` → é 🟡 drift. Ação aqui **não é promover** — é **reconciliar o baseline**. **Sem deadlock** porque na `origin/main` o a11y-axe **já foi convertido pra always-run** (gatilho `pull_request` sem `paths:`, short-circuit por `git diff` no job — ADR 0282 / #2885); reporta `success` em todo PR (ex #3103 toca 0 paths e mesmo assim é verde; 15/15 PRs não-UF mergearam). Incorporar ao baseline é seguro.
2. **`dSIH ratchet vs baseline`** está required ao vivo e **não casa com nenhum job de workflow existente**. **Verificado 2026-06-20 = ZUMBI confirmado:** não existe em lugar nenhum do repo (`grep` em workflows/scripts/governance/Modules = 0), `git log -S "dSIH"` = vazio, e **não reporta check-run** no #3103 (onde `A11y axe` e `Conformance` reportam). É um required órfão (provável resíduo de workflow deletado na onda de subtração da ADR 0271 D-3). Ação: **remover do protection vivo** (mutação Tier-0 = clique [W]); **não** incorporar ao baseline.

Os **outros 7** gates da lista estão confirmados **fora** do required vivo (premissa da auditoria correta).

**Pré-requisito que trava TODA promoção nova:** dos 8, **6 ainda são path-scoped** na `origin/main` (`on.pull_request.paths`): components-tree-guard, ds-canon-color-guard, design-spec-gate, design-index-gate, screen-coverage-gate, scope-guard. (Os outros 2 já são always-run: a11y-axe via ADR 0282 #2885, e module-grades nativo.) No GitHub, required-check path-scoped que não roda fica preso em **"Expected — waiting"** e trava o PR — a doença que a ADR 0261 (Alavanca 2) e a 0271 documentaram (incidente "Expected" 08/jun). **Atenção:** o a11y-axe **não** é evidência de que path-scoped-required é seguro — ele só não trava porque foi convertido pra always-run. Logo, pros 6 que continuam path-scoped a conversão **always-run + skip-as-pass** (padrão de `visual-regression.yml`/`financeiro-pest.yml`, ou o short-circuit-por-git-diff do a11y) **continua obrigatória antes de qualquer flip**. Promover qualquer um dos 6 como está = brickar PRs não-relacionados.

**Status do calendário:** a ADR 0275 (que deveria ratificar o *calendário de promoções*) **ainda é draft** — não existe arquivo `memory/decisions/0275-*.md` (as decisions pulam 0272 → 0294); o baseline e o `protection-drift.mjs` já apontam pra ela como se estivesse aceita. Até a 0275 landar, cada promoção precisa de **palavra [W] explícita item-a-item**, sob o precedente da ADR 0271 onda-2 (foi assim que `visual-regression` e `financeiro-pest` viraram required-ready).

---

## 1. Regras que esta proposta obedece (o "calendário")

Codificadas hoje (independentemente da 0275 estar em draft):

- **Baseline `_meta.regra`:** promoção a required **atualiza `required-checks-baseline.json` no MESMO PR do flip**; demoção **só** via PR editando o baseline + ADR; required novo no vivo fora do baseline = 🟡 (entrar é permitido, abrir PR incorporando).
- **ADR 0261 (calendário de soak):** gate novo **nasce advisory → sequência de verdes estável → enforcing**. Alavancas faseadas por risco×esforço. *"Só promover gates com histórico estável; gate flaky promovido a required trava TODOS os merges."* `enforce_admins:true` já está ligado ao vivo → **não há válvula de admin-bypass** se um required piscar.
- **ADR 0261 — nunca exigir não-determinístico** (LLM-judge/RAGAS). Os 8 candidatos são **todos determinísticos** (regra/ratchet/Pest/axe), então passam nesse critério.
- **`protection-drift.mjs` §3 (watchdog):** métrica/canário desarma até **nova sequência de 3 verdes**. Uso isto como piso de "verde estável" pra recomendar promoção.

**Critério que apliquei por gate:** (a) morde de verdade? (b) determinístico? (c) idade ≥ ~14d de soak advisory? (d) histórico de verde estável / taxa de falso-positivo? (e) já é always-run (ou exige conversão skip-as-pass)? (f) conflita com ADR existente?

---

## 2. Scorecard

| Gate (context/job) | Morde? (onde) | Required vivo? | Idade | Estabilidade (gh run) | Always-run? | Conflito | **Recomendação** |
|---|---|---|---|---|---|---|---|
| **a11y-axe-gate** · `A11y axe · runtime nos componentes canon` | ✅ `npm run a11y:axe` (vitest exit≠0); sem continue-on-error | **JÁ É** (fora do baseline) | ~14d (06-06) | 0 falhas / 80 runs | ✅ always-run (ADR 0282 #2885, short-circuit git-diff) | — (sem deadlock) | **Reconciliar baseline** (já é required, não é promoção) |
| **design-index-gate** · `Design index (fonte única)` | ✅ Pest `DesignIndexSingleSourceTest` (exit≠0) | não | ~3sem (05-30) | 4 falhas = 1 PR (true positive, doc órfão), resolvido | ❌ path-scoped | — | **pronto-pra-required** (após conversão skip-as-pass) |
| **visual-regression pixel-diff** (step dentro do check já required) | 🟡 `PixelBaselineTest` **com `continue-on-error:true`** (linha 233) | check pai sim; step **não** | step Q4 (~06-04), 6 `.snap` commitados | smoke browser verde; pixel advisory | check pai always-run ✅ | — | **pronto-pra-required**: remover `continue-on-error` após 2 verdes (sem mexer no protection) |
| **components-tree-guard** · `Components · árvore canônica` | ✅ `components-tree-guard.mjs:142 process.exit(1)` | não | **~9d** (06-11) | 0 falhas | ❌ path-scoped `resources/js/**` | — | **precisa-mais-soak** (jovem; converter + soak p/ ~14d) |
| **design-spec-gate** · `Design-spec · contrato estrutural por-tela` | ✅ `design-spec-gen.mjs:122 process.exit(1)` | não | ~14d (06-06) | 1 falha (true positive, spec stale) | ❌ path-scoped `Pages/**` | — | **precisa-mais-soak** (converter + +1 sequência de verdes) |
| **screen-coverage-gate** · `screen-coverage-gate` | ✅ `screen-coverage-map.mjs:155 process.exit(1)` | não | ~16d (06-04) | 8 falhas = remoções/refactor de página tropeçando na catraca | ❌ path-scoped `Pages/**` | — | **precisa-mais-soak** (fricção em refactor legítimo; alisar fluxo de bump do baseline antes) |
| **scope-guard** · `check-scope` | ✅ `check-scope.php:247 exit(1)` (`--strict`) | não | ~1mês | ~25 falhas/mês = drift real (controller fora do SCOPE.md) rotineiramente mergeado vermelho | ❌ path-scoped `Modules/**/Controllers` | — | **precisa-mais-soak** (converter + zerar backlog de drift + soak) |
| **ds-canon-color-guard** · `DS canon · cor crua` | ✅ `ds-canon-color-guard.mjs:111 process.exit(1)` | não | **~7d** (06-13), 25 runs totais | 0 falhas (gatilho estreito) | ❌ path-scoped `Components/ui|shared` | — | **manter-advisory** (novo demais; menos evidência de todos) |
| **module-grades-gate** · `module-grades-gate` | ✅ `.yml` `exit 1` (linhas 348/354/487) | não | maduro (mai) | **mais red-prone de todos** — ~25 falhas em PRs **não-relacionados** (composto/ruidoso) | ✅ always-run | **ADR 0271 D-4 propõe DEMOTIR** ("caro, composto, não é catástrofe") | **NÃO promover / manter-advisory** (resolver a questão da 0271 primeiro) |

> Notas de método: "estabilidade" vem de `gh run list --workflow=<f> --status failure`. Como nenhum dos 7 é required, **todo** vermelho deles foi não-bloqueante por definição (mergeou vermelho = comportamento esperado, não prova de falso-positivo). Onde distingo true-positive (catch real) de flaky, é pela natureza do PR; onde não dá pra cravar, marco como sinal a verificar, não como veredito.

---

## 3. Detalhe por gate

### a11y-axe-gate — JÁ required, reconciliar (não promover)
- Context `A11y axe · runtime nos componentes canon` está no required **vivo** mas **ausente** do baseline congelado → 🟡 drift que o `protection-drift` deveria sinalizar e ninguém incorporou.
- Determinístico (axe-core, 0 serious/critical), 2 semanas, 0 falhas. Como *qualidade de gate* está ótimo.
- **Deadlock inexistente — mecanismo confirmado (2026-06-20):** na `origin/main` o `pull_request` do a11y-axe **não tem `paths:`** → roda em **todo** PR, com short-circuit por `git diff` dentro do job (ADR 0282 / #2885). Por isso sempre reporta status (ex #3103 toca 0 paths e é verde; 15/15 PRs não-UF mergearam). É o padrão always-run correto pra required — já está certo. **Ação:** apenas **incorporar o context ao baseline JSON** (Wave 0). Nenhuma conversão pendente.

### dSIH ratchet vs baseline — required ZUMBI (remover do vivo)
- **Verificado 2026-06-20:** sem produtor em lugar nenhum (`grep -rni dSIH .github scripts governance Modules` = 0), `git log -S "dSIH" -- .github/workflows` = vazio, e **não reporta check-run** no #3103. Required que não é produzido por nada → resíduo (provável workflow deletado/renomeado na ADR 0271 D-3). Na prática os PRs mergeiam (não observei trava); o porquê exato de um required-nunca-reportado não bloquear aqui fica como pergunta aberta — mas, reportando ou não, é um required errado e deve sair.
- **Ação [W] (Tier-0):** remover o context do protection vivo (`gh api -X PATCH .../required_status_checks`). **Não** entra no baseline — deixá-lo fora faz o `protection-drift` continuar sinalizando 🟡 até a remoção, o que é o comportamento correto. Fora do escopo dos 8, mas é a mesma classe de drift da auditoria #3098/#3100.

### design-index-gate — candidato mais maduro entre os novos
- Pest file-based (sem DB/rede), 3 semanas (o mais velho dos gates de design), determinístico. As 4 falhas históricas são **um** PR (`docs/manual-identidade-consolida`) iterando até consertar um doc-canon órfão = **true positive resolvido**. Sem ruído.
- **Promover:** converter pra always-run+skip-as-pass (gatilho hoje é `_DesignSystem/**` + `decisions/**` + `prototipo-ui/**`) → flip. Bom **primeiro** flip entre os gates novos.

### visual-regression pixel-diff — a promoção mais limpa e barata
- O check `visual-regression` **já é required** (browser smoke morde de verdade). Só o **step** pixel-diff é advisory via `continue-on-error: true` (linha 233). O próprio comentário do workflow diz: *promover = remover o `continue-on-error`* — **sem clique de branch protection** (o context já é required).
- Baseline armado: 6 `.snap` commitados (`tests/.pest/snapshots/.../PixelBaselineTest/`).
- **Risco:** pixel-diff é sujeito a flaky de rendering/anti-aliasing. **Promover:** confirmar **2 runs verdes consecutivos** do pixel-diff em PRs de UF de verdade, então remover o `continue-on-error`. Manter o escape [W] via `npm run visreg:update` + aprovação visual (gate F1.5).

### components-tree-guard — promissor, só jovem
- Node puro determinístico, allowlist revisável no diff, **0 falsos-positivos**. Mas **~9 dias** e path-scoped. Converter + deixar chegar a ~14d de soak; depois é candidato forte (Wave 2).

### design-spec-gate — quase lá
- Determinístico (freshness do `.design-spec.json` derivado da `.tsx`), 2 semanas, 1 true-positive. Converter + +1 sequência de verdes e promove (Wave 2).

### screen-coverage-gate — fricção a domar antes
- Catraca de cobertura. As 8 falhas são **remoções/refactor de página** derrubando a cobertura legitimamente → exigem bump consciente do baseline. Promover sem alisar esse fluxo gera fricção em refactor honesto. **precisa-mais-soak** + garantir que o "subir o piso" (`screen-coverage-map.mjs --json` + commit do baseline) é trivial no PR.

### scope-guard — valor alto, backlog de drift no caminho
- Constituição Art. 7 (controller fora do SCOPE.md). Determinístico, maduro (~1 mês). Mas ~25 vermelhos/mês = drift **real** rotineiramente mergeado. Promover **hoje** trava esses PRs. **Pré:** (1) always-run+skip-as-pass; (2) zerar/declarar o backlog de drift (cada controller órfão entra no `SCOPE.md.contains[]` ou em `drift_alerts[]`); (3) soak. Não é catástrofe Tier-0 → não precisa furar fila.

### module-grades-gate — NÃO promover (conflito com ADR 0271)
- Único already-always-run, mas o **mais red-prone**: ~25 falhas em PRs sem relação com grades (handoff, forja, caixa) — sintoma de métrica **composta/ruidosa** (qualquer módulo baixar 1 ponto reprova o PR inteiro). **ADR 0271 D-4 propõe explicitamente REBAIXAR/manter advisory** ("check mais caro do repo, métrica composta, não catástrofe"). Promover contradiz a 0271 e bloquearia muitos merges. **Resolver a questão de demoção da 0271 primeiro**; não é candidato a required.

---

## 4. Plano faseado proposto (cada flip = 1 PR, [W] aprova)

**Wave 0 — reparo de drift (baixo risco, fazer primeiro; NÃO é "promoção", é o baseline alcançar a realidade):**
- Incorporar ao `required-checks-baseline.json` os 2 contexts já-vivos: `A11y axe · runtime nos componentes canon` e `dSIH ratchet vs baseline` (ou remover o dSIH se for zumbi).
- **Verificar o deadlock do a11y-axe** (PR que não toca `Components/ui/**`) e converter pra skip-as-pass se preciso.
- **Investigar o dSIH** (produtor real ou remoção).

**Wave 1 — promoções mais limpas:**
- `visual-regression` pixel-diff → remover `continue-on-error` após 2 verdes (sem mexer no protection).
- `design-index-gate` → converter skip-as-pass + flip + baseline no mesmo PR.

**Wave 2 — após conversão + soak ~14d e sequência de verdes:**
- `components-tree-guard`, `design-spec-gate`.

**Wave 3 — após domar fricção/backlog:**
- `screen-coverage-gate` (fluxo de bump), `scope-guard` (backlog de drift).

**Segurar / fora de fila:**
- `module-grades-gate` (resolver demoção 0271), `ds-canon-color-guard` (novo demais — revisitar após ~14d advisory).

**Mecânica de cada flip (baseline `_meta.regra`):** no MESMO PR — (a) conversão always-run+skip-as-pass se path-scoped; (b) adicionar o context a `governance/required-checks-baseline.json`; (c) `gh api -X PATCH .../required_status_checks` adicionando o context ao vivo; (d) [W] aprova. Reversível em 1 comando.

---

## 5. Edit proposto do baseline (NÃO aplicado — para [W] aprovar)

Apenas a reconciliação Wave-0 (o que já está vivo). Promoções Wave 1-3 entram cada uma no seu PR de flip.

`governance/required-checks-baseline.json` → `classic_protection.contexts` ganha **só** o a11y (o dSIH é zumbi → sai do vivo, não entra no baseline):
```diff
       "Append-only canon (ADRs, handoffs, Constituição)",
+      "A11y axe · runtime nos componentes canon",
       "Casos-coverage · ratchet (trio + rastreabilidade)",
       "Conformance · cor-crua ratchet vs baseline",
       "Dominio-dict · ratchet (enum ⇔ dicionário)",
```
> `dSIH ratchet vs baseline` **não** entra (investigação confirmou zumbi — remover do vivo via PATCH [W]). Deixar fora do baseline mantém o `protection-drift` 🟡 até a remoção = correto.
> Atualizar também `_meta.capturado_em` / `_meta.capturado_de_sha` para o SHA do PR de reconciliação.

---

## 6. O que **não** fiz (de propósito)
- Não editei branch protection nem o baseline.
- Não promovi nada.
- Não abri PR (a tarefa pede a proposta; o flip é decisão [W] e depende da 0275 ratificar o calendário OU de aprovação item-a-item sob a 0271).

## 7. Próximos passos para [W]
1. Decidir Wave 0 (reconciliação + investigar a11y-deadlock e dSIH-zumbi) — é dívida de drift, independe da 0275.
2. Aprovar (ou não) Wave 1 item-a-item.
3. Landar a **ADR 0275** (calendário) pra parar de referenciar ADR fantasma em `baseline._meta` e `protection-drift.mjs`.
4. Resolver a demoção do `module-grades-gate` (ADR 0271 D-4).
