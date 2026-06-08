---
slug: modelo-unico-identidade-2-camadas
number: PENDENTE
title: "Modelo único de identidade visual em 2 camadas — chrome (1 roxo) vs. semântica (N governada); reconcilia 0246 D-02 × 0235"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
quarter: 2026-Q2
decided_at: 2026-06-08
decided_by: [PENDENTE-WAGNER]
module: _DesignSystem
supersedes: []
amends:
  - "0246-sessao-2026-05-30-ds-harmonizacao"   # rebaixa D-02 (identidade via --accent escopado)
related:
  - "0235-ds-v4-accent-roxo-universal"          # chrome = roxo 295 universal (lei vigente)
  - "0249-ds-v6-naming-amends-0235"             # ds-v6/tokens.css = nome canônico da camada de tokens
  - "0190-primary-button-roxo-universal-295"    # origem do roxo (superseded por 0235)
  - "0261-enforcement-faseado-gates-ci"         # base do gate CI da Fase 4
  - "0246-sessao-2026-05-30-ds-harmonizacao"    # D-05 "cor crua = erro" (proposto, nunca adotado) → Fase 4
tags: [design-system, ds-v6, accent, roxo, identidade, duas-camadas, origin-tokens, stage-tokens, semantica, chrome, ratchet, ds-guard, governanca-ui]
pii: false
review_triggers:
  - Larissa biz=4 reportar perda de wayfinding (origem/etapa) após rebaixar hue-por-módulo
  - Surgir necessidade legítima de 2ª cor de chrome (ex: produto white-label / sub-marca)
---

# ADR — Modelo único de identidade visual em 2 camadas

## Status

**Proposto** — 2026-06-08. Aguardando aprovação Wagner ([W]). Quando aceito, [CL] atribui o
próximo número livre (≈ 0263) e move de `proposals/drafts/` para `memory/decisions/`.

Origem: artefato de decisão **"Mapa de Identidade ERP — Fonte Única vs Ilhas · [CC]"**
(handoff Claude Design, 2026-06-08). O mapa diagnosticou a identidade visual do ERP lendo os
arquivos canônicos `@main` e identificou **duas leis vigentes que se contradizem**. Este ADR é a
**Fase 0** desse mapa — a decisão-raiz que destrava todo o programa de consolidação. O próprio
mapa registra a Fase 0 como *"RATIFICADO [W] 06-08"*; este documento a formaliza no canon.

## Contexto — duas leis que se contradizem

| Lei | Fonte | Diz |
|---|---|---|
| **A — accent por módulo** | [ADR 0246](../../0246-sessao-2026-05-30-ds-harmonizacao.md) D-02 + `PROCESSO_MEMORIA_CC.md` | Cada tela expressa sua cor por `.<tela>-scope { --accent: … }`. Registry: Vendas 155 verde · Financeiro 295 roxo · Compras navy · Clientes 262 indigo · CRM 220 azul. |
| **B — chrome único roxo** | [ADR 0235](../../0235-ds-v4-accent-roxo-universal.md) / [0249](../../0249-ds-v6-naming-amends-0235.md) / 0260 | `--accent` = roxo `oklch(0.55 0.15 295)` **universal**, travado, igual em todo módulo. *"é o mesmo produto."* |

As duas usam o **mesmo** token (`--accent`) com significados opostos. Resultado: nada downstream
(port de módulos, lint de cor, harvest de componentes) é seguro até a contradição ser resolvida.
O mapa também separou **fato de artefato**: o censo inicial foi montado sobre **espelhos locais
stale** do Cowork; relido o **git real `@main`** (confirmado nesta sessão):

| Módulo | `@main` real | Veredito |
|---|---|---|
| **Sells/Vendas** (`sells-cowork.css`) | `--accent: oklch(0.55 0.15 295)` | ✅ já convergido (o "azul 220" era artefato do espelho) |
| **Financeiro** (`cowork-canon-financeiro-bundle.css`) | `--accent: …295`; `--bubble-me: var(--accent)` | ✅ já roxo + bubble já aliasado (o "azul 220 stale" não existe no git; bundle único, não 2 duplicados) |
| **Clientes/CRM** | sem `clientes-norte.css` em `resources/css` | só protótipo — não é ilha viva |
| **Compras** (`cowork-compras-bundle.css`) | `--cmp-accent:#1f3a5f` + paleta navy em **hex cru** | ❌ **única ilha viva confirmada** |

Ou seja: a maior parte do programa **já está mergeada**. O resíduo de código real é pequeno —
**Compras (hex→token)** + a blindagem que impede o drift de voltar.

## Decisão

### D-1 — A identidade vive em **2 camadas**, nunca fundidas

1. **Chrome · identidade — 1 cor.** Botão · foco · link · estado ativo · primary das Index.
   É `--accent` = roxo `oklch(0.55 0.15 295)`, **universal e travado** (mantém 0235/0249/0260).
   Diz *"é o mesmo produto"*. Aqui a cor única é **obrigatória**.

2. **Semântica · significado — N cores governadas.** Wayfinding (padrão Attio), não decoração:
   - **Origem** da tarefa → `--origin-*` (de onde veio: CRM, Financeiro, Oficina/OS, Pagamento…)
   - **Etapa** do pipeline kanban → `--stage-*` (recepção, diagnóstico, peças, execução…)
   - **Status** do dado → `--pos` / `--warn` / `--neg`.

   Multi-hue **de propósito**. Não dilui a identidade porque **não é chrome**. Regra dura:
   só destes tokens, **nunca hue inventado**, nunca redefinindo `--accent`.

> O âmbar da OS no Oficina é exceção **legítima**: é `--origin` (origem-OS, hue 30) — camada
> semântica —, não um `--accent` redefinido. Por isso não é drift.

### D-2 — Rebaixa a Lei A (amends [ADR 0246](../../0246-sessao-2026-05-30-ds-harmonizacao.md) D-02)

O hue-por-módulo (Vendas-verde, CRM-indigo, Sells-azul) **deixa de ser chrome** (`--accent`) e
é **rebaixado para a camada semântica** (`--origin-*`). Vendas-verde vira "origem Vendas", não
"chrome verde". Cessa o padrão `.<tela>-scope { --accent: … }` para fins de identidade.
`PROCESSO_MEMORIA_CC.md` e os charters que ainda mandam "cor por `.scope{--accent}`" são atualizados.

### D-3 — Fonte única de token

Eleger **UM** arquivo canon de token — `ds-v6/tokens.css` ([ADR 0249](../../0249-ds-v6-naming-amends-0235.md)).
Os demais sites de `--accent` (`styles.css`, `cockpit.css`, `mockup-pages.css`, bundles de módulo)
viram `@import`/alias, **nunca redefinição**. Bloco de token de módulo (ex. Financeiro) deve
**aliasar** o canon, não redeclarar valores.

### D-4 — Blindagem (adota a D-05 de 0246, "cor crua = erro")

Promove a [ADR 0246](../../0246-sessao-2026-05-30-ds-harmonizacao.md) D-05 (proposta desde
2026-05-30, nunca adotada) a **lint obrigatório**, no trilho faseado de
[ADR 0261](../../0261-enforcement-faseado-gates-ci.md): hex/oklch fora de `tokens.css` **falha CI**;
`DS-GUARD` passa a **barrar redefinição de `--accent`** fora do canon. A partir daí, o drift não volta.

## O que isto decide pro port do Oficina

O port do `cowork-oficina-bundle.css` é **seguro** — com UMA regra: ele **não pode carregar nenhum
bloco de definição de token** (não declarar `--accent`, `--pos`, etc.). Ele já é assim (só `var(--…)`),
então **converge** ao herdar a fonte única — o oposto do que o Compras fez. O Oficina é o **aluno
modelo** (zero hex, herda tudo, e suas cores de etapa já foram promovidas a `--stage-*`).

## Programa de consolidação (gated — vários PRs)

Não é uma tarefa, é um **programa**: cada fase trava na anterior; [CC] propõe e faz a ponte, [CL]
executa, [W] aprova o gate (screenshot + CI verde). Tasks no MCP (módulo `_DesignSystem`).

| Onda | Fase | Conteúdo | Dep | Tam |
|---|---|---|---|---|
| — | **F0** | **Esta ADR** + atualizar `PROCESSO_MEMORIA_CC.md`/charters (parar de mandar `.scope{--accent}`) | — | S |
| 2 | **F1** | Fonte única: eleger `ds-v6/tokens.css`; colapsar os 3+ sites de `--accent` em `@import`/alias | F0 | M |
| 3 | **F2** | Reconciliar **Compras** hex→token (alias ao canon) — **único item de código vivo** + verificar/encerrar Sells/Financeiro (já roxo `@main`) | F1 | M |
| 4 | **F3** | Camada semântica governada: tokenizar `--origin-*`/`--stage-*` (funil CRM pendente) + escrever a regra das 2 camadas no DS | F2 | M |
| 5 | **F4** | Anti-regressão: lint "cor crua = erro" (D-05) + DS-GUARD barra redefinição de `--accent` | F3 | S |
| 6 | **F5** | Colher do Financeiro pro DS comum: 123 `.fin-*` + 138 `.vd-*` + paleta **dark completa** | F4 | L |

> **Trilho B (paralelo, não bloqueia):** "deixar lindo" — diagnóstico de *craft* por módulo contra
> o Oficina (régua 9.5: hierarquia, acabamento, cor semântica, controles, microcópia). Eixo de
> beleza, separado do eixo de token.

## Consequências

- **Positivas:** uma só lei de identidade; wayfinding (origem/etapa/status) preservado **por
  design**, não por acidente; port do Oficina destravado; drift de cor vira erro de CI, não revisão
  manual; o Financeiro vira fonte de componentes (não retrabalho).
- **Custos/risco:** Compras muda de chrome navy → roxo (mudança **visível** — exige gate de
  screenshot [W]); reescrever charters/`PROCESSO_MEMORIA_CC.md`; F5 é L (harvest grande).
- **Reversão:** travar 2ª cor de chrome exigiria nova ADR (`review_trigger`: white-label/sub-marca).

## Decisões que são do [W] (do mapa)

1. **Confirmar o roxo 295 como a ÚNICA cor de chrome** — não "qual hue", e sim comprometer com uma só
   (já é lei via 0235; re-explorar tem custo alto). → *recomendado: confirmar.*
2. **Cravar a regra das 2 camadas como texto no DS** (vs. deixar implícito). → *recomendado: cravar.*
3. **Reconciliar Financeiro e Compras na ordem certa** — Financeiro (aliasar + colher componentes)
   antes, Compras (hex→token, pendência [ADR 0200]) depois. → *recomendado: Financeiro 1º, Compras 2º.*

## Refs

`Mapa de Identidade ERP - CC.html` (handoff Claude Design 2026-06-08) · `ds-v6/tokens.css` ·
`styles.css` · `cowork-compras-bundle.css` L7 (`--cmp-accent:#1f3a5f`) ·
`cowork-canon-financeiro-bundle.css` L24/L29 · `sells-cowork.css` L42 ·
`_PROPOSTA-modelo-unico-identidade-2-camadas.md` (lápide Cowork) · ADRs 0235 · 0249 · 0246 · 0261 · 0190/0200.
