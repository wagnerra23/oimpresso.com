---
date: "2026-08-07"
time: "18:40 BRT"
slug: "lanes-required-vermelhas-e-quarentena"
tldr: "A lane de Estoque virou árvore-menos-quarentena e ficou verde no main (#5387). Medir as 5 lanes Pest required revelou Compras e Ponto em failure 5/5, da MESMA ADR 0369 — viraram US-COM-022 e US-PONTO-014 (#5395). Adversário deu MORDE com ressalva e achou 3 defeitos, consertados no #5399: o reporter não enxergava as 45 linhas de quarentena (0/45 → 45/45), push ≠ paths-filter, e uma frase excedia o medido. Aberto: nada limita o crescimento da quarentena."
cycle: null
prs: [5387, 5395, 5399, 5378]
decided_by: [W]
related_adrs:
  - "0369-tres-lanes-pest-required-emenda-0314"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
  - "0344-two-strikes-cobre-processo"
---

# Handoff — lanes Pest required: o vermelho que escondia

## Estado MCP no momento do fechamento

Consultado agora:

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI"*. Nada a fechar nem a rolar.
- **`my-work`** (@wagner) → **8 tasks**, todas em `REVIEW` e `p1`: US-TR-309, US-TR-310, US-PG-008,
  US-PROD-027, US-INFRA-023, US-TR-305, US-TR-306, US-INFRA-048. **Nenhuma tocada nesta sessão** —
  o trabalho de hoje nasceu de fora do backlog (pergunta do [W] sobre um PR) e gerou 2 US novas.
- **`decisions-search`** (lane/pest/required/quarentena, desde o último handoff) → nada novo nesta
  frente. Os hits são o padrão de emenda à 0314 (0339, 0343, 0347, 0354) — a família a que a
  **ADR 0369** pertence. Nenhuma ADR nova foi criada nesta sessão.
- **Paralelismo:** há **outra worktree ocupando `main`** (`hopeful-matsumoto-6ab4a7`) — descoberto
  ao tentar `git checkout main` aqui. Sessão irmã ativa; não toquei em nada fora dos paths abaixo.

## O que foi feito

| PR | commit | o quê |
|---|---|---|
| [#5387](https://github.com/wagnerra23/oimpresso.com/pull/5387) | `888db02a6c4` | lane de Estoque: allowlist inline → árvore-menos-quarentena (21 entradas) |
| [#5395](https://github.com/wagnerra23/oimpresso.com/pull/5395) | `f8794b9d3c4` | US-COM-022 + US-PONTO-014 no radar; ressalva presença≠execução no BRIEFING de Ponto |
| [#5399](https://github.com/wagnerra23/oimpresso.com/pull/5399) | `df303aa7346` | 3 achados do adversário: reporter cego, `push`≠filtro, errata na lista |

O [#5378](https://github.com/wagnerra23/oimpresso.com/pull/5378) (que estava travado pela lane)
também mergeou, em `0c3146e0941`.

Detalhe narrativo completo em
[`memory/sessions/2026-08-07-lanes-required-vermelhas-e-quarentena.md`](../sessions/2026-08-07-lanes-required-vermelhas-e-quarentena.md).

## Recibos (não afirmação)

```
LANE ESTOQUE NO MAIN: success · 888db02a6c4 → 0c3146e0941 → df303aa7346   (3 failure antes)
reporter de quarentena:  0/45 → 45/45 entradas casando path real
órfãos:                  956 → 936
selftest:                19 → 26
push × paths-filter:     0 só-no-filtro · 0 só-no-push
run-set pós-quarentena:  19 arquivos · 51 tests · 91 assertions · 0 skipped
```

## Onde pegar (o próximo passo, em ordem de custo)

1. **`US-COM-022`** (Compras, p0, ~3h) — a mais barata: 10 na árvore, 7 na allowlist, 3 fora.
   A receita está pronta em 2 lugares (`financeiro-pest.yml`, `estoque-pest.yml`); é copiar.
2. **`US-PONTO-014`** (Ponto, p0, ~5h) — 38 na árvore, 11 na allowlist, **27 fora**.
   ⛔ Tier 0: teste de imutabilidade/cross-tenant/LGPD que está fora **não entra na quarentena só
   pra fechar a lane** — se falhar, a falha é o achado, e a correção é decisão [W].
3. **Os 4 contratos Tier 0 / regra-mestre** do bloco A da quarentena do Estoque
   (`QuickAddProduto` e `ProdutoBom` = cross-tenant; `ProdutoEditPayload` = eixo ESTOQUE;
   `ProdutoBulkEdit` = eixo VALOR). Já vermelhos hoje; enumerados em
   `.github/estoque-pest-quarantine.list`.
4. **Os 14 `Wave2*`** — status desconhecido, rodar um a um no CT 100 antes de tirar da quarentena.

## Decisões pendentes de [W]

- **Achado 1 do adversário:** nada limita o crescimento da quarentena
  (`git grep -l 'estoque-pest-quarantine' origin/main` → **n=2**, sem baseline nem catraca).
  Não armei: catraca sobre tamanho de quarentena exige **FP medido antes**, e o §5 tem 4 lápides de
  guard sintático que reprovava o legítimo. Se virar máquina, o caminho é **estender** o
  `foundation-ratchet` (que já faz burn-down da outra quarentena, a de `@group legacy-quarantine`),
  nunca abrir régua paralela.
- **A ressalva do mecanismo novo:** sob árvore-menos-quarentena há poucos runs, todos verdes —
  população insuficiente pra afirmar que **ele** morde. O que morde hoje é o run-set herdado.
  Não há ação a tomar; é calibração pra quem for julgar a lane daqui a algumas semanas.

## Armadilhas que custaram tempo nesta sessão

- `gh run watch <id>` com **id não medido** → espera zero, em silêncio. Meça com
  `gh run list --json databaseId` antes. (LC-08, ocorrência 58.)
- `git show "origin/main:<path>"` no Git Bash **sem** `MSYS_NO_PATHCONV=1` → stdout vazio, e o
  loop devolve "0 arquivos / não existe" como se fosse medição.
- **Agente read-only e parent não devem dividir worktree**: troquei de branch com o adversário
  lendo. Ele sobreviveu medindo contra `origin/main`; foi disciplina dele, não desenho meu.
- Tocar SPEC acorda gate diff-aware (§5 2026-07-12 + emenda 07-27). Aqui foram 2:
  `_BACKLOG-GENERATED.md` (regenerar) e `distiller_freshness` (redestilar o BRIEFING — **não**
  bumpar a data no vazio).
