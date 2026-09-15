---
date: "2026-09-15"
time: "1521 BRT"
slug: "gitignore-ds-legado-contador-derivado-worktrees"
tldr: "4 PRs mergeados em cascata (cada um nasceu do erro do anterior): regra do _ds/ legado no .gitignore, registro da LC-08 que cometi ao reporta-la, contador do ledger virou DERIVADO porque a colisao me travou 2x, e lapide nova deixa de escrever n. Depois, 9 worktrees stale >30d preservados (WIP no remoto) e removidos, 3,14 GB liberados. Sobram 76 worktrees stale (<30d) intocados."
decided_by: [W]
cycle: null
prs: [7289, 7294, 7316, 7319]
us: []
next_steps:
  - "Decidir se os 76 worktrees stale restantes (<30d) valem remocao — 2 tem sessao viva e 1 (codex-sdd-hardening) tem junction vendor: esses 3 ficam de fora por construcao"
  - "Repo principal D:/oimpresso.com esta stale, parado em codex/prototipo-ssot-cleanup — decisao [W] se atualiza"
related_adrs: ["0344-two-strikes-cobre-processo", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0374-emenda-0315-espelho-cowork-e-rota-prevista"]
---

# Handoff 2026-09-15 15:21 BRT — `_ds/` legado, contador derivado do ledger e limpeza de worktrees

## TL;DR

Quatro PRs mergeados, **cada um nasceu do erro do anterior**. O pedido inicial era uma linha de `.gitignore`; ao reportá-la cometi um LC-08 que virou o PR seguinte; registrar esse LC-08 colidiu 2× com outras sessões, o que expôs o contador escrito à mão e virou o terceiro PR; e o quarto tirou o `nº N` das lápides pela mesma razão. Depois, 9 worktrees stale foram preservados e removidos.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 11:00 | [#7289](https://github.com/wagnerra23/oimpresso.com/pull/7289) — `prototipo-ui/cowork/_ds/` no `.gitignore` raiz + guard do painel estendido aos 2 destinos |
| 12:22 | Ao reportar o #7289, li `mergeable_state: unknown` como "computando" com `state: closed` ao lado — o [W] já tinha mergeado |
| 12:22 | [#7294](https://github.com/wagnerra23/oimpresso.com/pull/7294) — registro da LC-08. **2 conflitos de merge em ~20min**, numeração colidindo 156 → 157 → 158 |
| 14:26 | [#7316](https://github.com/wagnerra23/oimpresso.com/pull/7316) — contador do ledger vira DERIVADO (`base:<N>` + 1 por `- **rec**`) |
| 14:46 | [#7319](https://github.com/wagnerra23/oimpresso.com/pull/7319) — lápide nova não escreve `nº N` + errata da colisão que afirmei sem medir |
| 15:00–15:20 | 9 worktrees stale >30d: WIP preservado no remoto, depois removidos |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Notas |
|---|---|---|
| `.gitignore` | ✅ | regra `prototipo-ui/cowork/_ds/` (path legado pré-#7224) |
| `scripts/design/protocolo.config.mjs` | ✅ | guard cobre os 2 destinos no mesmo `ls-files` |
| `.claude/hooks/licoes-code-two-strikes.mjs` | ✅ | parser deriva contador; fail-open fechado; banner instrui `- **rec**` |
| `.claude/hooks/licoes-code-two-strikes.test.mjs` | ✅ | 60 OK / 0 FAIL (+13 asserts novos) |
| `memory/LICOES_CODE.md` | ✅ | 32 linhas de campo migradas, **zero números alterados** |
| `memory/licoes-rejeitadas.md` | ✅ | lápide LC-08 + regra "lápide nova não escreve `nº N`" |
| `memory/proibicoes.md` | ✅ | §5 **derivado** (`sec5-derive --write`), nunca editado à mão |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#7289](https://github.com/wagnerra23/oimpresso.com/pull/7289) | ✅ MERGED `3e699a6c84` | regra `_ds/` legado + guard |
| [#7294](https://github.com/wagnerra23/oimpresso.com/pull/7294) | ✅ MERGED `39736fa813` | LC-08 (`mergeable_state` lido sem `state`) |
| [#7316](https://github.com/wagnerra23/oimpresso.com/pull/7316) | ✅ MERGED `f4c253357b` | contador DERIVADO |
| [#7319](https://github.com/wagnerra23/oimpresso.com/pull/7319) | ✅ MERGED `31a0a7a1d1` | lápide sem `nº N` + errata |

## Worktrees — o que foi feito e o que sobrou

**Medido:** 114 registrados · **86 stale** (não contêm o #7224, logo rodam o hook antigo) · 31 com `cowork/_ds/` materializado.

**Removidos: 9** (>30d, sem sessão viva, sem junction). **8 tinham modificações rastreadas não-commitadas** (~350 arquivos) — todas commitadas e pushadas ANTES da remoção, com `local == remoto` provado item a item. Os 2 `detached HEAD` ganharam branch `wip/preserva-detached-<sha>`.

| branch preservada no remoto | commit |
|---|---|
| `claude/financeiro-unificado-fidelidade-dark` | `a4ee507112` (66 arquivos) |
| `claude/ciclo-templates-docs` | `dc66fa8e4b` (84) |
| `worktree-agent-a82827cc64a99c4a3` | `2db3b78844` (98) |
| `claude/epic-bhaskara-676293` | `00ac49ebaf` (94) |
| `claude/beautiful-chandrasekhar-8b3251` | `4191dbfcfc` (1) |
| `claude/gracious-rhodes-89f409` | `ba5120ef48` (2) |
| `wip/preserva-detached-0b058f8a5cb` | `eca0743d9a` (3) |
| `wip/preserva-detached-f876ce0650f` | `05a8a26c98` (3) |

**Disco liberado — 3.216 MB (3,14 GB)**, e a decomposição é o ponto: a diferença bruta das duas varreduras foi **7.062 MB**, mas o repo principal encolheu **3.536 MB sozinho** (outra sessão) e **1 worktree foi removido por terceiro** (`intelligent-mclean-91e16a`, ~310 MB). Reportar a diferença bruta teria dobrado o número. Confere com a estimativa prévia (3.138 MB) em 2,4%.

**Ficaram de fora por construção:** repo principal `D:/oimpresso.com` (35 GB, e também stale, em `codex/prototipo-ssot-cleanup`) · `codex-sdd-hardening` (**único com junction `vendor`** — é o vetor da lápide §5 que já esvaziou `vendor`/`node_modules` reais 2×) · 2 worktrees com sessão viva.

**Sobram 76 stale** (<30d). `vendor` 113 e `node_modules` 703 checados a **cada uma** das 9 remoções, com abort automático se mudassem.

## Caveats / o que NÃO foi feito

- **O `.gitignore` blinda o repo, não os worktrees stale** — eles seguem rodando o hook antigo e escrevendo em `cowork/_ds/`; só que agora invisível ao git em qualquer branch de `main`.
- **O `nº N` das lápides antigas fica** (append-only Tier 0) — a regra é forward-only, 17 auto-declarações + 1 citação preservadas.
- **Sem gate novo em nenhum dos 4 PRs.** ADR 0344 two-strikes: o candidato P6 (campo derivado de PR lido sem `state`) ficou **medido e não armado** no campo `Gate:` da LC-08 — barrado pelo custo (rede dentro de `PreToolUse` em 2.032 de 146.910 comandos), não pelo FP.
- **`ciclo-adversary` rodado 2× antes do canon**, deu REJECT nas duas e achou o que eu não tinha visto: o recibo `08-11 (n+1)` (que fez a lápide virar 2ª ocorrência) e o fail-open do contador (`base: 1` com espaço fazia 158 virar 1 em silêncio).

## Estado MCP no momento do fechamento

- `list_sessions` (limit 100): **4 sessões `isRunning: true`**; 2 delas em worktree stale (`ponto-anchor-verification-34a901`, `oimpresso-erp-lista-853e70`) — excluídas da remoção por isso.
- Colisão observada ao vivo: sessão `mystifying-bouman-58cf5b` com PR #7322 aberto intitulado **"Registrar LC-08 nº 159"** — o mesmo número que o #7319 mergeou. É exatamente o problema que o #7316/#7319 consertaram, acontecendo em paralelo.
- PRs abertos no fechamento: 11 no total; nenhum meu.
- `origin/main` no fechamento: `b1b4f32bf7d` (#7327 — outra sessão resolveu o recibo pendurado `LC-08 ↔ 08-31` que este handoff teria herdado).
- Gates rodados e verdes: `sec5-derive --check/--selftest` · `licoes-code-two-strikes.test.mjs` (60/0) · `--reconcile` (`pos_frontier_SEM_marcador=0`) · `loop-fechar-check` E7 · `lapide-recheck` · `gate-selftest` (82/82, via adversário).

## Próxima ação

Decidir os **76 stale restantes** (<30d): a maioria tem trabalho não-commitado, e 3 estão fora de alcance por construção. E decidir se o repo principal sai de `codex/prototipo-ssot-cleanup`.
