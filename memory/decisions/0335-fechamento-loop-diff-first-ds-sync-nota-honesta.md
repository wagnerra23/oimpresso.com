---
slug: 0335-fechamento-loop-diff-first-ds-sync-nota-honesta
number: 335
title: "Fechamento do loop diff-first DS-sync: nota honesta (B−/C+ vs SOTA A−), régua real (advisory-lint + Tier-0 humano), errata 0299 (Figma)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-11"
module: design-system
quarter: 2026-Q3
tags: [design-system, design-tokens, sync, governanca, grade, figma, adversarial, anti-inflacao]
supersedes: []
superseded_by: []
related:
  - 0299-figma-nao-e-fonte-de-design
  - 0328-ds-transicao-congelado-para-vivo-git-ssot
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0329-doutrina-documentacao-de-processo-executavel
  - 0239-governanca-design-system-git-ssot-regressao-ia
pii: false
review_triggers: []
---

> **Redação por [CC], sessão 2026-07-11. Ratificação formal = merge por [W]** (que dirigiu "Merge e fechar gap").
> Fecha o thread de teste + graduação do loop diff-first de sync de tokens (git ⇄ espelho claude.ai/design).

# ADR 0335 — Fechamento do loop diff-first DS-sync

## Contexto

Nesta sessão o loop diff-first (git = SSOT DTCG → Style Dictionary → `_generated-*.css`; espelho vivo no claude.ai/design; motor `ds-token-diff.mjs --companion` reporta `VALOR:0`) foi (1) **provado a frio** — sessão limpa, sem contexto prévio, fechou `VALOR:0` contra o git commitado E contra o espelho vivo; (2) **estendido** com 3 ferramentas (`ds-push`, `ds-token-version`, e o gate visual por delta de token) — PRs #4101/#4102/#4103; (3) **graduado** contra o SOTA 2026 de sync de tokens (DTCG + Style Dictionary v4 + Figma Variables API/Tokens Studio + Chromatic + npm semver).

A primeira nota foi dada **pelo próprio agente sobre o próprio design** e, submetida a um **avaliador adversarial** (a pedido do Wagner: "adversário para ter certeza"), revelou-se **inflada em ~1 grau inteiro**. Esta ADR canoniza a **nota honesta** e o **papel real** do loop — pra impedir que uma sessão futura re-infle (anti-padrão de degradação catalogado).

## Decisão

### 1. O papel HONESTO do loop (não inflar)

O loop diff-first é um **lint de sincronia git↔espelho, barato, determinístico, com governança humana Tier-0 forte** — bom para o contexto **1-app / 1-designer** do oimpresso. Ele **NÃO é**:
- estado-da-arte de sync de tokens (perde em workflow-do-designer, maturidade, distribuição, automação e regressão-visual);
- uma "régua da máquina" que enforça o loop inteiro. A máquina só enxerga a metade **git↔snapshot** (advisory); a metade **git↔espelho-vivo** roda **manual/cadência** (o CI não tem login claude.ai — limite irredutível).

### 2. A nota honesta (registro anti-inflação)

**oimpresso ≈ B−/C+ · SOTA 2026 ≈ A−.** A vitória **genuína e única** é **governança humana Tier-0 no gate de Fundação** (aprovação de screenshot + git-native audit). As outras "vitórias" da nota original eram dimensão desenhada pra ganhar (ex.: `--companion` "resolve" um falso-positivo que só existe porque o espelho **cura/omite** domínios — curativo de ferida auto-infligida, não capacidade) ou strawman do SOTA (Style Dictionary É determinístico; Chromatic pega layout/a11y/uso-errado que o diff de VALOR é cego por design — inclusive pula aliases). Detalhe: [session 2026-07-11](../sessions/2026-07-11-ds-sync-loop-grade-adversarial.md).

> ⛔ **Não re-propor a nota inflada** (A−/B+ · "outra função de custo em pé de igualdade" · "régua da máquina"). Regressão conhecida — antes de reafirmar superioridade do loop, reler esta seção.

### 3. A régua real = pilha 0256 (catraca+gate+sentinela+cadência), ADVISORY por 0314

Token não é Tier-0 (não é dinheiro/PII/multi-tenant/fiscal) → pela [0314](0314-poda-gates-onda-2-lei-fusoes.md) **required = só Tier-0**, o loop é **advisory-que-morde**, não required. Instanciação:

| Camada 0256 | Artefato | Terminal |
|---|---|---|
| Catraca | `version.json` fingerprint (ds-token-version) + `ds-mirror-drift-baseline.json` | advisory |
| Gate CI | delta de token dispara o `visual-regression` **required** (#4101) + selftests em `governance-script-tests` | required (visreg) / advisory (selftests) |
| Sentinela | `ds-token-diff --companion` (git↔snapshot no CI; git↔vivo manual) | advisory |
| Cadência | `ds-push` (1 comando + `VALOR:0`) + runbooks | — |
| Anti-fantasma | selftests de `ds-token-diff` (motor), `ds-push`, `ds-token-version` | required-ready |

### 4. Gaps FECHADOS nesta sessão

- **Gate visual no delta de token** (#4101): antes um PR só-de-token caía em skip-as-pass (verde sem renderizar). Agora dispara o `visual-regression` required. Fecha também a cegueira do diff-de-VALOR a layout/a11y (VALOR:0 + pixel juntos).
- **Semver + changelog do contrato de tokens** (#4103): a versão era implícita (só o sha do README). Agora `version.json` + CHANGELOG (MAJOR=remoção · MINOR=add/valor). Um blocker (fingir MINOR numa remoção) foi pego pelo adversário e corrigido no mesmo PR.
- **`ds-push`** (#4102): push git→espelho de 4 passos manuais → 1 comando determinístico + `VALOR:0` + manifesto DesignSync.
- **Motor central sem teste** (esta ADR): `ds-token-diff.mjs` — o motor que todo o loop reusa — ganhou selftest (diverge/designOnly/gitOnly/**alias-skip**/companion), fechando o gap de maturidade mais afiado do adversário.

### 5. Não-goals CONSCIENTES (não são TODO — não abrir de novo)

- **Publicar pacote npm versionado / distribuição multi-consumidor** — over-engineering pro contexto 1-app.
- **Workflow do designer no Figma / round-trip Figma Variables** — doctrinal (ver §6) + o MCP Figma foi desligado.
- **Ecossistema/comunidade** — inerente ao stack bespoke; aceito.
- **`VALOR:0` contra o espelho VIVO no CI** — **irredutível** (GitHub Actions não tem login claude.ai). Fica como cadência (run manual/cron autenticado), nunca gate de PR. Fingir que o CI valida o vivo seria teatro (contra a [0329](0329-doutrina-documentacao-de-processo-executavel.md)).

### 6. Errata da [ADR 0299](0299-figma-nao-e-fonte-de-design.md) (Figma) — parcial

Wagner 2026-07-11: a doutrina "Figma **não é** fonte de design" da 0299 estava atrapalhando e o MCP do Figma foi **desligado**. Errata:
- **Doutrina revertida:** Figma volta a ser uma **fonte legítima** de design quando o Wagner quiser de propósito — não mais categoricamente "NÃO-fonte".
- **Enforcement MANTIDO (opt-in leve):** o hook `block-figma-without-optin` **permanece** como gate opt-in (exige "figma" explícito / `.figma-allow`). Não é bloqueio-doutrina; é guarda anti-atrator/anti-fonte-acidental. Camadas L4/L5 (catraca + eval) da 0299 seguem válidas.
- **Estado atual:** MCP Figma desconectado (Wagner), então o atrator da 0299 nem está presente hoje.
- Relação: **supersede PARCIAL** da 0299 (§1 doutrina) — o mecanismo (L1 hook) e as catracas seguem canon. 0299 NÃO vira morta.

## Consequências

✅ **Boas:** o loop tem papel honesto e registrado; o motor central tem teste; a nota inflada não volta (registro anti-regressão); Figma deixa de ser proibido-por-doutrina sem perder a guarda opt-in.
⚠️ **Tradeoffs:** o loop continua advisory (não bloqueia merge de token) — aceito pela 0314; a metade viva continua manual — aceito como irredutível.

## Validação

- ✅ Prova a frio (sessão limpa): `VALOR:0` git commitado + git vivo, `--companion` fecha o falso git-only (68/76 → 10/16).
- ✅ PRs #4101/#4102/#4103 mergeados (required verde; adversário de código pegou 4 achados, todos corrigidos + provados).
- ✅ `node scripts/design-sync/ds-token-diff.test.mjs` — 5 comportamentos do motor (incl. alias-skip + companion).
- ✅ Avaliador adversarial da NOTA (não só do código) deflacionou ~1 grau; concessões registradas em §2.
