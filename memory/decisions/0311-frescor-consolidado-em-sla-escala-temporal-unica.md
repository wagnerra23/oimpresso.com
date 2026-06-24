---
slug: 0311-frescor-consolidado-em-sla-escala-temporal-unica
number: 311
title: "frescor consolidado em --sla-* (escala temporal única) — emenda ao D4 da ADR 0310: a recência do Clientes deixa de ter família própria e usa a rampa de SLA"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-24"
module: design-system
tags: [design-system, ds-v6, tokens, dtcg, cockpit, sla, frescor, consolidacao, emenda, anti-drift]
supersedes: []
superseded_by: []
related:
  - 0310-tokens-semanticos-dominio-frescor-sla-kind-canal
  - 0249-ds-v6-naming-amends-0235
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

> **Status: aceito** — [W] decidiu na sessão 2026-06-24 ao entregar o DTCG final rotulado *"sem frescor, escala --sla-* única"*. Emenda ao **D4** da [ADR 0310](0310-tokens-semanticos-dominio-frescor-sla-kind-canal.md) (que dizia "`--frescor-*` NÃO consolida com `--sla-*`"). Redigida por [CL] (Claude Code); a ratificação se concretiza com o merge desta PR.

# ADR 0311 — frescor consolidado em --sla-* (escala temporal única)

## Natureza desta ADR

**Emenda append-only.** Não edita o corpo da [ADR 0310](0310-tokens-semanticos-dominio-frescor-sla-kind-canal.md) (canon é append-only — [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)). Reverte **só o D4**: a pergunta "consolidar frescor também?" estava marcada como **"em aberto p/ [W]"** na proposta original; o D4 do 0310 a fechou no default conservador (*não consolidar*). [W] agora **respondeu: consolidar**. O resto da 0310 (D1/D2/D3 + as famílias kind/kpi-feature/vip/sla/canal) segue **intacto**.

## Contexto

A 0310 promoveu 6 famílias semânticas. Uma delas — `--frescor-*` (recência de contato/compra no Clientes, 4 níveis) — ficou **separada** de `--sla-*` (tempo de resposta/vencimento, 4 passos) sob o argumento "recência ≠ tempo de resposta" (D4).

Na prática as duas escalas são **a mesma rampa temporal** (verde→amber→laranja→vermelho conforme o tempo passa). Manter duas famílias = a exata duplicação que a 0310 veio matar. [W] decidiu (2026-06-24, DTCG final): **frescor não vira família própria — a recência do Clientes usa `--sla-*`.**

## Decisão

### E1 · `--frescor-*` NÃO existe como família de token

As 12 vars `--frescor-{recente,fresc,distante,frio}[-soft|-line]` são **removidas** do `semantic.tokens.json` (e, por regeneração, dos `_generated-cockpit-*.css`). `--sla-*` é a **escala temporal única** do sistema.

### E2 · Mapa de consolidação (recência → SLA)

As classes do Clientes apontam pra rampa de SLA:

| Clientes (frescor) | → token SLA |
|---|---|
| `.cli-frescor-recente` | `--sla-fresh` |
| `.cli-frescor-fresc` | `--sla-aging` |
| `.cli-frescor-distante` | `--sla-late` |
| `.cli-frescor-frio` | `--sla-expired` |

(4 níveis ↔ 4 passos, 1:1. `-soft`/`-line`/`-dot` análogos.)

### E3 · O que NÃO muda

D1 (promover ao DTCG `.cockpit`), D2 (camada `var()`, não `@theme`), D3 (`--sla-*` 4 passos canônico) seguem. As famílias `kind`, `kpi-feature`, `vip`, `sla`, `canal` ficam **idênticas** (mesmos valores light+dark da 0310). Roxo 295 intocado.

## Não-goals

- ❌ **Não edito a 0310** (append-only — esta emenda é o registro da reversão do D4).
- ❌ **Não migro os consumidores** do Clientes (`.cli-frescor-*` → `var(--sla-*)`) nesta PR — é follow-up por bundle com smoke visual (e o Clientes ainda nem tem CSS no git, só no protótipo Cowork). Esta PR só **remove os tokens órfãos** + documenta o mapa.
- ❌ **Não toco no protótipo Cowork** — reconciliação do espelho é do [CC].

## Consequências

✅ **Boas:**
- Uma só rampa temporal no DS inteiro (SLA = recência = vencimento). Zero ambiguidade "qual escala uso pra tempo?".
- −24 linhas de CSS gerado (12 tokens × light+dark); o `dtcg-equivalence` cai de 320 → 296 fiéis, 0 divergência.

⚠️ **Tradeoffs:**
- A nuance "recência tem 2 verdes (recente+fresc)" some — recência passa a usar fresh/aging (verde/âmbar) como tudo mais. Aceito por [W]: consistência > nuance local.
- Nenhum consumo em git é quebrado (não havia `--frescor-*` consumido no app; Clientes vive no protótipo).

## Validação

- ✅ `node resources/css/tokens/style-dictionary.config.mjs` — regenera sem as 12 vars frescor.
- ✅ `node scripts/governance/dtcg-equivalence.mjs` — **296 fiéis, 0 divergências**.
- ✅ `node scripts/foundation-guard.mjs` — fundação íntegra (remover token é livre).
- ✅ `node scripts/conformance-gate.mjs --all` — conforme.
- ✅ `node scripts/css-size-baseline.mjs` — **−24 linhas** (baseline re-cravado pra baixo).

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-24 | [W] decide + [CL] redige | emenda ao D4 da 0310 — frescor consolidado em `--sla-*`; 12 tokens removidos; mapa recência→SLA registrado |
