---
slug: 0307-onda-0-rede-seguranca-enforcement
number: 307
title: "Onda 0 — rede de segurança / enforcement: travar o caminho do dinheiro + promover o 1º gate SDD a required antes de qualquer onda estrutural"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-24"
module: governance
tags: [enforcement, sdd, gates, required, rede-seguranca, ratchet, multi-tenant, tier-0, armamento]
supersedes: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0093-multi-tenant-isolation-tier-0
  - 0066-format-date-shift-3h-preservado-legacy-clientes
  - 0175-fix-observer-conta-bancaria-opcional
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-sdd-medir-governar-floor-nightly
  - 0306-strangler-spec-anchored-reconstrucao-sdd
pii: false
---

> **Ratificada por [W] em 2026-06-24.** Promove a canon a proposta [`onda-0-rede-seguranca-enforcement`](proposals/onda-0-rede-seguranca-enforcement.md) (PROPOSED 2026-06-21). É a **1ª onda** da estratégia [ADR 0306](0306-strangler-spec-anchored-reconstrucao-sdd.md). Estado real verificado contra o código vivo em [verificação 2026-06-21](../sessions/2026-06-21-verificacao-rede-onda0-estado-real.md) e re-confirmado contra `origin/main` + branch protection viva em 2026-06-24 (esta ratificação).

# ADR 0307 — Onda 0: a rede de segurança (parar de quebrar antes de mudar)

## Contexto

Wagner, ao ver a deriva spec↔módulo: *"o sistema fica quebrando; primeiro garantir que não quebre de novo com uma estrutura melhor."* O sistema "quebrava de novo" (`num_uf` ×100k, regressões entrando no `main` sem barrar — #2761/#2848, specs derivando em pastas-fantasma) por **uma causa só**: as proteções existiam mas eram todas *advisory* — **mediam e não governavam**. Mexer na estrutura antes de armar a rede é trocar o telhado com o detector de fumaça desligado. **Primeiro a rede.**

## Decisão

Executar a Onda 0 como **4 bricks**, cada um um PR pequeno que *prova* (counterfactual) que armou uma peça, e **promover o 1º gate SDD a `required`** — a peça que converte "mede" em "governa". **Nenhuma onda estrutural** (seam pricing/stock, expand fin_titulos, cutover) nem a vertical viva começa antes da rede verde. O **design (F1) é paralelo** — não fica atrás das ondas 1–5.

Garantia oferecida (honesta): não é 100%, é **monotônica** — tudo que for travado por teste+catraca não regride mais em silêncio, e a área travada só cresce (regra de armamento, [ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §3: 3 medições reais antes de punir; §5: máx 1 promoção/semana, demoção só via decisão).

| # | Brick | O que arma |
|---|---|---|
| **A** | Oráculo do caminho do dinheiro | characterization de `num_uf` (`app/Utils/Util.php`) + `+3h` ([ADR 0066](0066-format-date-shift-3h-preservado-legacy-clientes.md)); reintroduzir o legado → teste falha |
| **B** | Coverage real (pcov) | tira `coverage_pct` de `not_yet_measured`; arma após 3 medições |
| **C** | Migration scorecard em prod | ≥1 row em `mcp_sdd_scorecard_history`; destrava G7/G8 |
| **D** ⭐ | 1º gate SDD `required` + counterfactual | promove gate determinístico a required; PR-quebrado passa a ser **barrado** (não só medido) |

## Estado real no fechamento (verificado 2026-06-24)

A rede de medição já estava feita (floor 274 transportado CT100→main, [ADR 0279](0279-sdd-medir-governar-floor-nightly.md); JUnit wired; `baseline-tamper-guard` #3128; `gate-selftest` 5 catracas). Execução real dos bricks, reconciliada contra `origin/main` e a branch protection viva:

| Brick | Resultado real |
|---|---|
| **A** | ✅ merged [#3178](https://github.com/wagnerra23/oimpresso.com/pull/3178) (oráculo `format_date` +3h / num_uf) |
| **B** | ✅ coberto por [#3150](https://github.com/wagnerra23/oimpresso.com/pull/3150) (pcov CT100 + `measureCoverage` full-suite, fonte única); o duplicado [#3184](https://github.com/wagnerra23/oimpresso.com/pull/3184) foi **revertido** |
| **C** | ✅ tabela `mcp_sdd_scorecard_history` aplicada em prod (1 row, snapshot 2026-06-21, composta 50) |
| **D** ⭐ | ✅ **FEITO E VIVO** — a branch protection de `main` já exige enforced **`Foundation ratchet (quarentena/RefreshDatabase/Business::first)`** + **`SDD scorecard ratchet (métrica armada não regride · GT-G3)`** (PRs [#3143](https://github.com/wagnerra23/oimpresso.com/pull/3143) + [#3181](https://github.com/wagnerra23/oimpresso.com/pull/3181)), ao lado de Conformance/UI-Lint/Casos-coverage/Domínio-dict/No-mock-in-prod/PHPStan ratchets. **0 dos required eram SDD → agora são vários.** |

**Promoção soberana §5:** o flip do brick D foi consolidado **agora** (decisão Wagner "flip já hoje"), exercendo o direito de promoção da [ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5 em vez de esperar a janela natural de armamento. A reconciliação preexistente do `required-checks-baseline.json` no MESMO PR do flip mantém a regra do baseline (required novo ⇒ atualiza o arquivo no flip).

**Lição estrutural (registrada):** esta Onda sobrepôs um programa "armamento SDD" paralelo e mais adiantado; a checagem "tem outro fazendo isto?" tem de rodar **antes** de construir, mecanicamente — ver proposta [`anti-duplicacao-work-claim-gate`](proposals/anti-duplicacao-work-claim-gate.md). A própria ratificação re-verificou contra `origin/main` por causa disso.

## Kill-criteria

1. Counterfactual de D não dá exit 1 (gate não morde) → não promover; consertar o gate. *(N/A — já vivo e enforced.)*
2. pcov estourar o tempo de CI → rodar coverage só no nightly.
3. Promoção de D quebrar PR legítimo por falso-positivo de regra → demoção-via-decisão ([ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5), ajustar a regra, re-tentar.
4. Qualquer brick exigir tocar Tier 0 (`business_id` scope no ORM) → **pare** (Tier 0 vence).

## Reversibilidade

Alta. A/B são aditivos (testes + config CI). D é um flip de branch protection — reversível por decisão/ADR ([ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5). C é leitura. Nada cria schema irreversível.

## Consequências

✅ O pior tipo de quebra deixa de ser mergeável em silêncio; a rede vale para o time MCP (Felipe/Maiara/Eliana/Luiz). Destrava as ondas estruturais da [ADR 0306](0306-strangler-spec-anchored-reconstrucao-sdd.md) e o design (F1) em paralelo.
⚠️ Mais gates required = mais superfície de falso-positivo; mitigado pela regra de armamento (3 medições) e demoção-via-decisão. A promoção soberana antecipada (§5) assume esse risco conscientemente.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-21 | [CL] redige | proposta + verificação (5 verificadores read-only vs git) |
| 2026-06-24 | [W] decide + [CL] redige | ratificação a canon; registra brick D já vivo/enforced (promoção §5) |
