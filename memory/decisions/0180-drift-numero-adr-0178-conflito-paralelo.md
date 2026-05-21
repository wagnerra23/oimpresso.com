---
slug: 0180-drift-numero-adr-0178-conflito-paralelo
number: 180
title: "Drift de número ADR 0178 — conflito paralelo PR #1323 (Sells) × PR #1324 (Cliente BR)"
type: adr
status: aceito
authority: reference
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
module: governance
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
---

# ADR 0180 — Drift de número ADR 0178 (conflito paralelo)

## Contexto

Em **2026-05-21** dois PRs criados em paralelo, sem visibilidade um do outro, escolheram o mesmo número ADR `0178`:

| Arquivo | PR | Mergeado (UTC) | Authority | Conteúdo |
|---|---|---|---|---|
| [`0178-sells-unified-tabs-visao-supersede-0136.md`](0178-sells-unified-tabs-visao-supersede-0136.md) | [#1323](https://github.com/wagnerra23/oimpresso.com/pull/1323) | 14:36 | **canonical** (supersede 0136) | Sells: unificar Lista + Grade Avançada com tabs de Visão |
| [`0178-restauracao-campos-fiscais-br-canon.md`](0178-restauracao-campos-fiscais-br-canon.md) | [#1324](https://github.com/wagnerra23/oimpresso.com/pull/1324) | 16:48 | reference | Restauração dos campos fiscais BR em `contacts` (regressão UPOS 6.7) |

**Resultado:** dois arquivos diferentes com prefixo `0178-*` em `memory/decisions/`. Internamente cada um tem `slug` único e `number: 178`.

## Decisão

**Aceitar o drift permanente.** Ambos ADRs mantêm número 0178.

### Por que não renumerar via rename?

A regra **"Append-only canon"** ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §Art. 3 + [ADR 0095](0095-skills-tiers-convencao-interna.md)) bloqueia rename de ADR ratificada (`git mv` é detectado pelo CI como rename R092+ no workflow `append-only-canon.yml`).

Tentativa de renomear via [PR #1330](https://github.com/wagnerra23/oimpresso.com/pull/1330) foi abandonada — check `Append-only canon` falhou exatamente nessa proteção. **A regra está correta** — preserva trail histórico das decisões.

### Por que não criar ADR "supersedente"?

A opção canônica `supersedes: [0178]` + `lifecycle: superseded` na antiga não se aplica aqui porque:
- Os ADRs 0178 conflitantes **não decidem sobre o mesmo assunto**
- Ambas decisões continuam **ativas e válidas**
- Marcar uma como "superseded" seria semanticamente errado

### Solução pragmática

Esta meta-ADR (`0180`) documenta o drift pra arqueologia futura. **decisions-search** MCP retorna ambos quando filtra por '0178' — o trecho `slug` distingue:

- `0178-sells-unified-tabs-visao-supersede-0136` → Sells tabs
- `0178-restauracao-campos-fiscais-br-canon` → Cliente BR

## Como resolver conflitos futuros

Pra evitar repetir o drift:

1. **`decisions-search` antes de criar ADR**: chamar tool MCP `decisions-search status:proposed` (ou similar) imediatamente antes de escolher número, especialmente quando paralelizando agents
2. **Worktree paralelo `git fetch origin main`**: re-fetch DENTRO do worktree antes de escolher número, captura ADRs mergeados durante a sessão paralela
3. **Coordenador-paralelo**: aplicar [skill `coordenador-paralelo`](../../.claude/skills/coordenador-paralelo) quando spawn-ar >2 agents que possam criar ADR — orquestra reserva de números

## Convenção pra ler estes 2 ADRs 0178

Quando outras docs do projeto referenciam **"ADR 0178"** sem qualificador, contexto define:

- Mencionado em PRs/commits da área **Sells** ou unificação de tabs → `0178-sells-unified-tabs-visao-supersede-0136`
- Mencionado em contexto de **Cliente / campos BR / fiscais / contacts** → `0178-restauracao-campos-fiscais-br-canon`

Pra documentos futuros, **prefira sempre referenciar pelo slug completo** (ex: `[ADR 0178-sells-unified-tabs-visao-supersede-0136](memory/decisions/0178-sells-unified-tabs-visao-supersede-0136.md)`) em vez de só "ADR 0178".

## Consequências

### Positivas

- ✅ Append-only canon mantido íntegro (trail histórico preservado)
- ✅ Zero edição em ADRs ratificadas
- ✅ `decisions-search` MCP continua funcional (busca por slug é exata)
- ✅ Documenta o processo pra time MCP (Felipe/Maiara/Eliana/Luiz) aprender

### Negativas

- ⚠ Humanos lendo `ls memory/decisions/ | head` veem 2 prefixos `0178-*` lado a lado (estético, não funcional)
- ⚠ Referências "ADR 0178" sem slug em PRs futuros podem ser ambíguas — convenção acima mitiga

## Refs

- [PR #1323 — Sells unified tabs](https://github.com/wagnerra23/oimpresso.com/pull/1323)
- [PR #1324 — Cliente canon BR + RiscoCliente](https://github.com/wagnerra23/oimpresso.com/pull/1324)
- [PR #1330 — tentativa rename abandonada](https://github.com/wagnerra23/oimpresso.com/pull/1330)
- [ADR 0094 — Constituição v2 §Art. 3 append-only](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0095 — Skills tiers + convenção lifecycle](0095-skills-tiers-convencao-interna.md)
- [memory/sessions/2026-05-21-investigar-campos-br-cliente.md](../sessions/2026-05-21-investigar-campos-br-cliente.md)
