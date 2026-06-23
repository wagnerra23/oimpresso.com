---
slug: 2026-06-04-conflitos-memoria-plano
title: "Plano de reconciliação de conflitos de memória (4 camadas driftadas + índice da Jana recuperando ADR superseded)"
type: session
status: proposta
authority: pending
decided_by: pending-[W]
date: "2026-06-04"
authors: [claude-cowork]
related: [STATUS.md, MEMORY_INDEX.md, memory/LICOES_CC.md, PROCESSO_MEMORIA_CC.md, ADR 0031, ADR 0033, ADR 0238, ADR 0239]
---

# Plano — conflitos de memória ([W] "as memórias estão conflitantes, analise todas e crie um plano")

> **Aterramento:** li nesta sessão `STATUS.md` · `MEMORY_INDEX.md` · `memory/LICOES_CC.md` · `PROCESSO_MEMORIA_CC.md` · `CONSTITUICAO.md` · `memory/decisions/README.md` · `_PROPOSTA-0244/0245`.
> **NÃO li o `main` nesta sessão** → toda afirmação sobre estado do git está marcada `⚠ por STATUS, não re-verificado no git`. Internos do índice da Jana (mem0/Meilisearch) = `⚠ inferido de ADR 0031/0033`.
> Tier 0 (governança/memória/ADR/índice vetorial) = **soberania de [W]**; eu **proponho**. Não cunho número de ADR, não afirmo commit, não toco constituição.

---

## 1. Diagnóstico-raiz (1 frase)

A memória tem **4 camadas** que evoluíram em velocidades diferentes e **nenhuma carrega "estado de validade" mecânico** — então o índice vetorial da Jana recupera ADR **superseded** como se fosse vivo, e as camadas escritas se contradizem entre si.

## 2. As 4 camadas e o frescor de cada (a doença é o drift entre elas)

| # | Camada | Última att. | Cobertura | Papel real hoje |
|---|--------|-------------|-----------|-----------------|
| 1 | `STATUS.md` | 2026-06-03 | narrativa viva | **mais fresco** — mas **internamente contraditório** (quadro de telas ≠ texto de consolidação) |
| 2 | `MEMORY_INDEX.md` | 2026-05-30 | ADR 0001–0041 + altos esparsos | **stale**: pré-v5/v6, pré-resolução D-02; auto-declara gap 0042–0189 |
| 3 | `memory/decisions/README.md` | **2026-04-24** | só ADR 0001–0011 + 0023 | índice morto (40 dias atrás) |
| 4 | Índice vetorial Jana (MCP) | contínuo | todos os ADR | `⚠ inferido` — recupera por similaridade, **sem noção de supersession** |

> O índice da Jana é **bom** (indexa tudo, busca semântica) — o defeito não é ele, é que **as outras 3 camadas não dizem quais ADR estão mortos**, então ele não tem como filtrar.

## 3. Mapa de conflitos (grounded)

| ID | Conflito | Onde vive | Verdade vigente | Classe |
|----|----------|-----------|-----------------|--------|
| **C1** | Vendas **verde 155** × **roxo 295** | STATUS "Quadro de telas" diz verde; STATUS (q)/(p) + L-29/L-32 dizem **roxo** | **roxo** (gabarito v6, ADR 0190/0235) | local (eu) |
| **C2** | **D-02** = "PROPOSTA F0 aberta" × "RESOLVIDO" | "Decisões vigentes" tabela diz aberta; STATUS (q) item 4 diz resolvido | **resolvido = roxo universal** | local (eu) |
| **C3** | DS canon = **v4.2/v3/v4** × **v5/v6** | `MEMORY_INDEX` T2 lista v4.2 "canon" e v3/v4 "archive"; STATUS diz **v5 único ativo + v6 régua aditiva** | **v5 ativo · v6 régua · v4.x histórico** | local (eu) |
| **C4** | §10.4 "tokens em `app.css`" | `LARAVEL_REPO_CONTEXT §10.4` | **errado** — `app.css` é manifesto vazio; tokens em `cockpit.css`/`inertia.css` (L-26) | local (eu) + mirror [CL] |
| **C5** | **Oficina dupla**: "Oficina/OS · verde 155" × "Oficina · âmbar 60" | duas linhas no Quadro de telas | **âmbar 60 escopado** (STATUS (i)); a linha "verde 155" é stale | local (eu) |
| **C6** | **ADR nº local ≠ git**: `_PROPOSTA-0244` (teste) **e** `_PROPOSTA-0245` (ds-v5) | `memory/decisions/` | `⚠ por STATUS (e)`: 0245→**0244** no git (#2123). Os dois locais **colidem em 0244** | git/[W] — eu **não renumero** (L-09) |
| **C7** | **3 índices de ADR** divergentes + gap 0042–0189 | README (04-24) · INDEX (05-30) · git | nenhum é fonte única confiável | local (eu) + [CL] |
| **C8** | Índice Jana recupera **ADR superseded** como vivo | retrieval MCP | **causa-raiz do pedido** (ver §4) | [CL] (Tier 0) |
| **C9** | **3 conjuntos de "regra ativa"** sobrepostos | COMO PENSAR (5) · Regra de Ouro (6 gates) · Bateria §9 (14 testes) | precisam de hierarquia única | [W] decide |
| C10 | `CONSTITUICAO.md` "ativo?" | raiz | **não é conflito** — confirmado tombstone limpo (SUPERSEDED→CARTA, ADR 0201) | ✓ ok |

## 4. Causa-raiz do problema da Jana (C8) — e por que é o coração do pedido

ADRs seguem Nygard, cujo **Status** (`Proposta | Aceita | Substituída por NNNN | Obsoleta`) já está **descrito** em `memory/decisions/README.md` — mas:

1. **Não é mecânico:** o Status mora no corpo do `.md` em prosa, não em campo estruturado que o indexador leia.
2. **Não há grafo de supersessão:** quando 0235/0236 colidem (documentado, PR #1997) ou quando uma decisão é trocada, nada **linka** o morto → o vivo.
3. **O retrieval não filtra:** o índice vetorial busca por similaridade semântica e devolve o ADR **mais parecido**, que muitas vezes é o **antigo/superseded** (texto mais longo, mais "denso").

**Fix arquitetural (padrão maduro, não invento):** dar a todo ADR um **frontmatter de ciclo de vida** + ensinar o retrieval a respeitá-lo. Isso é trabalho de [CL] (mexe em mem0/Meilisearch — ADR 0031/0033 — e nos `.md` do git = Tier 0).

## 5. Plano em fases (sequência + dono + reversibilidade)

### Fase 0 — Reconciliar o que está vivo no Cowork *(eu, agora sob "vai", reversível, Cowork-local)*
Resolve **C1, C2, C3, C5** editando in-place (são arquivos de estado Tier-1, não registros):
- `STATUS.md` "Quadro de telas": Vendas **roxo**, Oficina linha única **âmbar escopado**, remover a linha "Oficina/OS verde 155" stale (com nota de trilha-do-tempo, L-22).
- `STATUS.md` "Decisões vigentes": **D-02 = RESOLVIDO (roxo)**, sai de "PROPOSTA aberta".
- `MEMORY_INDEX.md` T2: substituir v4.2/v3/v4 pelo estado **v5 ativo · v6 régua · v4.x histórico**; carimbar att. 2026-06-04.
- **Não toco** o que já está certo (L-28 alvo-mínimo).

### Fase 1 — Índice único derivado, com coluna Status *(eu, Cowork-local)*
Resolve **C7**. Colapsar README (morto) + INDEX (stale) num **só** índice temático **derivado**, cada linha com `Status: aceita | superseded-by NNNN | proposta | obsoleta`. README local vira tombstone apontando o índice único. O gap 0042–0189 fica explicitamente marcado "só git — [CL] completa" (não invento conteúdo).

### Fase 2 — Ciclo de vida de ADR mecânico *(ponte → [CL], Tier 0)*
Resolve a base de **C6, C8**. Proposta pro [CL] (valida contra `main`):
- Frontmatter obrigatório em todo `memory/decisions/NNNN-*.md`: `status`, `superseded_by`, `supersedes`, `decided_at`.
- `jana:health-check` novo: ADR sem `status` / superseded sem link / número duplicado = 🔴 (estende o `AdrNumberCollisionTest` que já existe).
- **Grafo de supersessão** gerado (`superseded_by` → aresta), publicado num índice mestre regenerado a cada ADR.
- **Eu não renumero** 0244/0245 — colisão se **documenta** (L-09); [CL] decide o número real contra o `main`.

### Fase 3 — Retrieval da Jana respeita validade *(ponte → [CL], Tier 0)*
Resolve **C8** de vez (mem0/Meilisearch, ADR 0031/0033):
- Ingestão carrega o campo `status` como metadado filtrável.
- Retrieval **exclui `superseded`/`obsoleta`** por padrão; se um morto bater, **segue o `superseded_by`** e devolve o vivo + nota "substitui ADR X".
- Reindex após o backfill da Fase 2.

### Fase 4 — Uma hierarquia de regra só *(decisão [W], Tier 0)*
Resolve **C9**. Hoje há 3 conjuntos sobrepostos (COMO PENSAR 5 · Regra de Ouro 6 · Bateria 14). Proposta: **COMO PENSAR (5) = topo sempre-lido**; Regra de Ouro vira o **pré-flight operacional dela**; Bateria §9 vira o **check mecânico** (não 3ª lista de leitura). Só [W] ratifica a hierarquia.

## 6. Ordem de ataque
**Fase 0 → 1** (eu, hoje, sob "vai") destrava a contradição que mais confunde. **Fase 2 → 3** (ponte [CL]) é o conserto durável do índice da Jana — é o que o pedido realmente pede. **Fase 4** é decisão sua quando quiser.

## 7. O que eu NÃO faço (limites)
- Não cunho/renumero ADR (0244/0245 = git/[W] · L-09).
- Não afirmo commit — só gero a ponte; [CL] resolve (L-06).
- Não toco constituição/PROTOCOL/BRIEFING (ADR 0238).
- Não reescrevo o que já está certo/curado (L-28).

## Trilha do tempo
- 2026-06-04 · [CC] mapeou 10 conflitos (C1–C10) a partir das 4 camadas de memória; plano em 5 fases. Aguarda [W]: "vai" pra Fase 0–1 (local) + transporte da ponte Fase 2–3 pro [CL]. Supersede nada; primeira consolidação de conflitos formal pós-06-03.
