---
date: "2026-08-11"
time: "17:51 BRT"
slug: linhagem-adr-no-grafo-e-os-residuos-que-viraram-fix
tldr: "F6 da grade memoria-conhecimento fechada: o catalog.json passou de 0 para 82 arestas ADR→ADR. A medição mostrou que resolver por NÚMERO publicaria fato falso em 6 arestas (13 números têm 2 ADRs; 1 slug é tombado), então a identidade virou slug-addressed. Os 3 residuais declarados viraram 2 PRs — e um deles, que eu tinha chamado de inócuo, derruba gate DURO."
prs: [5614, 5629, 5632]
decided_by: [W]
related_adrs:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
  - 0370-module-surface-catalog-graph-required-emenda-0314
next_steps:
  - "Merge [W] dos PRs #5629 e #5632 (os dois BLOCKED só por DS gate na fila)"
  - "Decidir se o eixo módulo→adr também vira slug-addressed (hoje adr:NNNN colapsa os 13 números colididos)"
---

_(20:51 UTC — o `time` do frontmatter aceita um fuso só.)_

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **8 tasks, todas em REVIEW** (US-TR-309/310/311, US-PROD-027, US-INFRA-023/048, US-TR-305/306). **Nenhuma tocada nesta sessão** — o trabalho foi de governança, fora do backlog de produto.
- `decisions-search` → nenhuma ADR nova no intervalo; as citadas (0256/0274/0316/0370) são pré-existentes.
- Handoffs irmãos de hoje: `1345`, `1514`, `1537`, `1745`, `1750`, `1810`.

## O que aconteceu

**Pedido:** publicar as arestas ADR→ADR no `catalog-graph.mjs` (fraqueza **F6** da grade `memoria-conhecimento`, id `mem-provenance-prosa`). Premissa do brief reproduzida e confirmada: **569 nós · 834 arestas · ADR→ADR = 0**.

O trabalho foi **decidido pela medição, não pelo brief**:

- **`related` (1602 pares) ficou de fora** — seria **+192%** de arestas num grafo de 834, semântica vaga, 11 alvos pendurados. **`amends` (39) também**: não está no `adr.schema.json`, então emitir seria inventar tipo de aresta a partir de campo não-canônico.
- **100% dos 1.684 itens são declarados por SLUG inteiro** (0 números crus). Isso virou a decisão de arquitetura: **resolver por número publicaria fato falso em 6 arestas**. `0189→0180-drift-numero` e `0313→0180-sidebar-v3-5` apontam pra ADRs **diferentes** que cairiam no MESMO `adr:0180`; e `0358 supersedes 0101-tests-…` é ADR **tombada**, que por número resolveria pra `0101-sistema-charter-…`, viva e de outro assunto.
- Regra adotada: `adr:NNNN` **só quando o número é inequívoco**; senão o id é qualificado pelo slug (ADR 0274). Tombada é morte legítima, não pendurada — espelha o `adr-index-generate.mjs`.

**Depois [W] disse "pode ajustar"** — entendi como os residuais que eu tinha declarado, e virou 2 PRs.

⚠️ **O residual (c) era pior do que eu tinha reportado.** Eu o chamei de "latente, inócuo hoje". Medido: o `rawItemsFrom` tira as **aspas antes** do `#`, então `- "0101-slug"  # nota` vira `0101-slug"` → a isenção de tombstone não casa → `--check` acusa **alarme FALSO** e sai `exit 1` **dentro do required Governance Gate**. Latente só porque há uma 2ª isenção (por número) que mascara — **exceto** quando o número da tombada segue vivo em outra ADR, que é o caso 0101 real.

## Artefatos gerados

| PR | escopo | estado |
|---|---|---|
| [#5614](https://github.com/wagnerra23/oimpresso.com/pull/5614) | 3 tipos de aresta novos; **0 → 82** ADR→ADR; guarda de slug; `$enforcement` corrigido | **MERGED** 19:08 UTC · 101 checks pass |
| [#5629](https://github.com/wagnerra23/oimpresso.com/pull/5629) | 5 refs de ADR sem lastro em `Jana/SCOPE.md` (4) + `Fiscal/SCOPE.md` (1, tombada→sucessora) | OPEN · **102 pass, 0 falha** |
| [#5632](https://github.com/wagnerra23/oimpresso.com/pull/5632) | ordem do `clean` no `rawItemsFrom` + 3 testes (1 BITE + 2 CN) | OPEN · **97 pass, 0 falha** |

`supersedes` 15 · `supersedesPartially` 48 · `supersededBy` 19. Diagnóstico novo `adr_supersession_of_tombstoned` (perdoar ≠ esconder). Nós 569→650→649; **nenhum tipo de aresta preexistente mudou de contagem** (aditivo).

## Persistência

- **git**: 3 PRs (1 mergeado em `main`, 2 abertos). Handoff + índice neste PR.
- **MCP**: webhook GitHub→MCP propaga `memory/**` em ~2min após merge.
- **BRIEFING**: **não atualizado, de propósito** — nenhuma capacidade de módulo mudou; o trabalho foi em `scripts/governance/` + 2 `SCOPE.md`.

## Próximos passos pra retomar

```
gh pr view 5629 --json state,mergeStateStatus && gh pr view 5632 --json state,mergeStateStatus
```

Os dois estão `BLOCKED` **só** pelo `DS gate` na fila (pool com 200+ runs enfileiradas repo-wide). Nenhum toca `.css`/`.tsx` ⇒ passagem trivial quando rodar. Merge é [W].

## Lições catalogadas

**Nenhuma lápide nova no §5, deliberadamente.** Os deslizes desta sessão são instâncias de classe já catalogada (**LC-08**) e foram **pegos por controle antes de virar afirmação publicada** — inflar o contador com recibo repetido é o vício que o próprio ledger alerta (mesmo critério do handoff `1537` de hoje).

O que vale carregar, e já está **no código, não na memória**:

1. **Reusar o parser do dono verbatim é o certo — mas o parser do dono pode ter defeito.** Copiei o `rawItemsFrom` para medir e ele me devolveu 2 "slugs fantasma" que eram **artefato da ordem de limpeza dele**, não dado. Quem denunciou foi ler o **byte cru** (`cat -A`) antes de acreditar no achado. Encodado no docblock do `catalog-graph.mjs` + teste BITE.
2. **`grep -c` que devolve 0 sai com rc=1 e quebra cadeia `&&`** — perdi uma execução de gate por isso (mesma família do `cmd || echo` já lapidado em §5 2026-07-17).
3. **O caminho de tombstone não tinha teste nenhum** — é por isso que o defeito sobreviveu. Agora tem 3, com mordida provada por mutação (15 passed / 1 failed, e o que falha é o BITE).

## Pointers detalhados

- Corpo dos 3 PRs (medições completas, tabelas antes→depois, provas de mordida): #5614 · #5629 · #5632
- Dono da validação de supersessão: [`scripts/governance/adr-index-generate.mjs`](../../scripts/governance/adr-index-generate.mjs) — este handoff **não restateia** as regras dele
- Dono do enforcement: [`governance/required-checks-baseline.json`](../../governance/required-checks-baseline.json)
- Resíduo **não** fechado: o eixo **módulo→adr** segue number-addressed, então `adr:NNNN` ainda colapsa os 13 números colididos (pré-existente; não toquei pra não churnar o catálogo inteiro)
