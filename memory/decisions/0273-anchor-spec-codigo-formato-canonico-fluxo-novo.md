---
slug: 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
number: 273
title: "Anchor spec↔código — formato canônico do campo 'Implementado em', sentinela _pendente_ de 1ª classe, e regra do fluxo novo"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-12"
module: governance
quarter: 2026-Q2
tags: [sdd, spec-anchored, traceability, anchor, implementado-em, pendente, ratchet, fluxo-novo, governanca]
supersedes: []
superseded_by: []
related: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
---

# ADR 0273 — Anchor spec↔código: formato canônico, sentinela `_pendente_`, fluxo novo

> Passo **SA-A1** da Semana 0 do [plano de reestruturação SDD](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md). Origem: [auditoria SDD 2026-06-12](../sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md) — achado nº1: *"a spec mente"* (campo `Implementado em` sem preenchimento confiável → sistema spec-first operando como se fosse spec-anchored). Complementa [ADR 0270] (a verdade atual em 1 pulo): o anchor é o pulo spec→código.

## Status

Proposto — aguarda aprovação Wagner (caminho "ADR canon" do CLAUDE.md).

## Contexto

### Números reais (re-derivados em `origin/main@afecf98f`, 2026-06-12 — regra anti-stale)

Medição determinística sobre `memory/requisitos/*/SPEC.md` (linhas `**Implementado em:**`):

| Métrica | Valor |
|---|---|
| SPECs totais | **57** |
| SPECs SEM nenhum campo `Implementado em` | **42** |
| Campos existentes (nos 15 SPECs restantes) | **84** |
| Placeholders (ver taxonomia abaixo) | **49** |
| Preenchidos com path real | **35** |

Taxonomia de placeholder observada no repo (insumo do backfill mecânico SA-A4):
`_[TODO — ...]_` (42×: Financeiro 13 · Accounting 9 · PontoWr2 12 · NfeBrasil 8) · `_[path]_` (3×) · `(a criar ...)` (2×) · pseudo-path `2026_xx_*.php` (1×). Parte dos placeholders está **correta** — a tela nunca foi construída; inventariar um path ali seria anchor falso. Por isso a sentinela `_pendente_` é estado de 1ª classe.

### O problema

Sem formato máquina-parseável: (a) nenhum lint consegue dizer se um anchor é verdadeiro; (b) "sem campo", "placeholder" e "não construído ainda" são indistinguíveis; (c) anchor não tem proveniência — quando o código move, ninguém sabe quando o link era válido pela última vez.

## Decisão

### 1. Gramática canônica do campo (1 linha por US, no corpo do SPEC)

```
linha       := "**Implementado em:** " (preenchido | parcial | pendente)
preenchido  := âncoras " · " verificação
parcial     := "_parcial_ · " âncoras " · " verificação [" — " o-que-falta]
pendente    := "_pendente_" [" — " justificativa]
âncoras     := segmento (" · " segmento)*        (≥1 segmento-path)
segmento    := "`" texto-sem-crase "`"
verificação := "verificado@" sha7 " (" YYYY-MM-DD ")"
```

Regex canônica (referência única pro `anchor-lint.mjs`, passo SA seguinte):

```
^\*\*Implementado em:\*\* (?:_pendente_(?: — .+)?|(?:_parcial_ · )?(?:`[^`]+`)(?: · `[^`]+`)* · verificado@[0-9a-f]{7} \(\d{4}-\d{2}-\d{2}\)(?: — .+)?)$
```

Desambiguação dos segmentos (determinística, sem IA):
- Segmento contendo `/` = **path** relativo à raiz do repo → verificável com `existsSync`. Estado preenchido/parcial exige **≥1 path**.
- Segmento sem `/` = **símbolo** (`Controller@method`, `Classe::metodo`, componente) ligado ao path imediatamente anterior. Verificação de símbolo é advisory (grep), nunca bloqueia sozinha.
- `verificado@<sha7> (<data>)` = **proveniência**: sha curto do commit de `origin/main` em que a existência do path foi verificada + dia da verificação. Backfill mecânico promove placeholder→preenchido **só** com `existsSync` true, carimbando o sha7 da verificação (plano-mãe §1 camada 1).

Exemplos válidos:

```
**Implementado em:** `Modules/Fiscal/Http/Controllers/NfeCockpitController.php` · `resources/js/Pages/Fiscal/Nfe.tsx` · verificado@afecf98 (2026-06-12)
**Implementado em:** `Modules/ProjectMgmt/Http/Controllers/BoardController.php` · `BoardController@updateStatus` · verificado@afecf98 (2026-06-12)
**Implementado em:** _parcial_ · `resources/js/Pages/NfeBrasil/Emissoes/Show.tsx` · verificado@afecf98 (2026-06-12) — falta botão CCe
**Implementado em:** _pendente_ — tela Sped não construída (US planejada)
```

### 2. Estados e semântica de cobertura (métrica `anchor_coverage` do scorecard)

| Estado | Forma | Conta como coberta? |
|---|---|---|
| Preenchido | âncoras + verificação | ✅ (se todos os paths existem) |
| `_parcial_` | sentinela + âncoras + verificação + pendência | ✅ (pendência rastreável) |
| `_pendente_` | sentinela + justificativa opcional | ✅ — **tela não construída é estado legítimo**, não dívida de anchor |
| Placeholder legado | `_[TODO...]_`, `_[path]_`, `(a criar...)`, pseudo-path | ❌ |
| Campo ausente | — | ❌ |
| Path inexistente / sem `verificado@` | — | ❌ (anchor quebrado = mentira detectável) |

Baseline da métrica é capturado na **1ª medição real do lint**, nunca deste documento (anti-stale, plano-mãe §2).

### 3. Regra do FLUXO NOVO (vigente a partir do aceite)

1. **SPEC novo** (arquivo novo em `memory/requisitos/<Mod>/SPEC.md`) nasce com:
   - frontmatter `anchor_format: "v1"` (key OPCIONAL no [spec.schema.json](../../scripts/memory-schemas/spec.schema.json) — grace-period: os 57 SPECs legados sem a key continuam válidos, conforme regra "campo novo opcional até backfill" do [README memory-schemas](../../scripts/memory-schemas/README.md));
   - campo `**Implementado em:**` em **toda US**, no mínimo `_pendente_`.
2. **US nova em SPEC legado** ganha o campo na criação (lint diff-only cobre, fase 1 abaixo).
3. O template canônico [`_TEMPLATE_SPEC.md`](../requisitos/_TEMPLATE_SPEC.md) já traz o campo (atualizado neste mesmo PR).
4. Frontmatter `anchor_format: "v1"` declara: "todo campo `Implementado em` deste arquivo segue a gramática §1" — habilita lint estrito por arquivo sem esperar o backfill global.

### 4. Ratchet advisory→required (3 fases — gates nascem ADVISORY, ADR 0271)

| Fase | Gate | Critério de promoção |
|---|---|---|
| **F1 ADVISORY** | `anchor-lint.mjs` em CI, diff-only (só SPECs tocados no PR), reporta sem bloquear; scorecard registra `anchor_coverage` | nasce junto com o lint (passo SA seguinte) |
| **F2 CATRACA diff-only** | lint **required** nos SPECs tocados + baseline versionado de coverage (só sobe; piorar = vermelho; exceção só via override visível no diff) | backfill mecânico (SA-A4) + IA com refutador G5 (SA-A5/A6) concluídos; 14d advisory com falso-positivo <5% |
| **F3 REQUIRED full-tree** | lint required na árvore inteira (SA-A10) | `anchor_coverage` = 100% (incl. sentinelas); máx 1 promoção required/semana (calendário do plano-mãe §3.6); entry em `gates-registry.json` + `required-checks-baseline.json`; aprovação Wagner |

## Consequências

- ✅ Spec-first vira **spec-anchored** (sweet spot brownfield — arXiv 2602.00180, citado na auditoria): drift spec↔código vira detectável por script determinístico, sem IA no runtime.
- ✅ Anchor falso fica impossível de entrar calado: preenchido exige path existente + proveniência `verificado@sha7`.
- ✅ US de tela não construída **não força** anchor inventado (`_pendente_` conta como coberta) — corrige o erro do claim "0/43" do audit.
- ⚠️ Custo: 1 linha a mais por US no fluxo novo; sha7/data ficam stale quando código move — mitigação: re-verificação automática pelo lint (`--fix` re-carimba) é responsabilidade do passo `anchor-lint.mjs`, não deste ADR.
- ⚠️ Os 42 SPECs sem campo + 49 placeholders NÃO são corrigidos aqui (1 PR = 1 intent) — são os passos SA-A4/A5/A6 do plano-mãe.

## Alternativas consideradas

1. **Anchor no frontmatter YAML** — rejeitado: US vive no corpo do SPEC; duplicar lista de US no YAML drifta (mesma doença dos 4 índices de ADR, ADR 0256).
2. **Âncora AST estilo Fiberplane Drift** — mais robusta a renames, custo alto de tooling PHP+TSX; fica como evolução possível (`anchor_format: "v2"`) — o campo de versão no frontmatter existe exatamente pra isso.
3. **Nascer required** — violaria "gates novos nascem advisory" (ADR 0271) e quebraria 42 SPECs legados no dia 1.

## Referências

- [Plano-mãe SDD 2026-06-12](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) — Semana 0, frente SA (SA-A1) · [Auditoria SDD 2026-06-12](../sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md) — P1 item 4 (spec↔código anchoring)
- [ADR 0270] ciclo de vida da informação · [ADR 0271] gates nascem advisory/required-ready · [ADR 0256] catraca + fonte única gerada
- arXiv 2602.00180 (níveis spec-anchored) — via auditoria
