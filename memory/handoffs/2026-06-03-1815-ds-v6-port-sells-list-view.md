---
date: 2026-06-03
hour: "18:15 BRT"
topic: "DS v6 — fundação + port /sells list view (gabarito) + higiene token"
duration: "~4h (sessão épica, 10 PRs)"
authors: [CL, W]
---

# Handoff — DS v6: fundação + port /sells (gabarito) + higiene token

> Origem: handoff Claude Design `claude.ai/design` ("Oimpresso ERP Comunicação Visual" · `ds-v6/gabarito-vendas.html`). Wagner aprovou DS v6 ("tudo do gabarito") e dirigiu o pouso em slices via AskUserQuestion.

## Estado MCP no momento
- **Cycle:** CYCLE-08 "Receita — Onda A" (11% decorrido, 25d restantes). DS v6 **não** é goal do cycle — é infra de design (pré-req pra telas).
- **my-work @wagner:** 30 tasks ativas (6 review, 6 blocked dormentes Gold, 18 todo). Nada do DS v6 estava em task MCP (veio do design handoff).

## O que aconteceu
Fetch do bundle handoff (tar.gz 35MB) → li README + `ds-v6/{gabarito-vendas,showcase,receita}.html` + `PROMPT_PARA_CODE_DS-V6*`. O handoff sequencia: **PR1 tokens → PR2 kit → PR3 backfill (Vendas 1º)**. Executei em slices gated, Wagner aprovou cada fork.

**Achado-chave:** `gabarito-vendas.html` é um mock do **list view** e a tela `/sells` (charter v6, 1805 LOC) já tinha **~95% paridade estrutural** — o delta era **re-skin por token** (a tela usava `.vd-*`/`.os-*` com paleta própria + 559 `oklch` crus). Não foi rebuild.

## Artefatos gerados — 10 PRs MERGED em `main`
| PR | tipo | o que |
|---|---|---|
| #2170 | feat | tokens `--stage-*` (cockpit.css light+dark) |
| #2181 | docs | reuse-map kit DS v6 (8/11 c-* já reusam) |
| #2184 | feat | tokens semânticas `--pos/--neg/--warn(+soft)` + **gate /sells aprovado** (`sells-index-dsv6-visual-comparison.md`) |
| #2186 | feat | PR3 slice 1 — status pills (PILL_STYLE → tokens; cancelada cinza→vermelho) |
| #2187 | feat | slice 2 — camada `--vd-ok/warn/bad/neutral` → tokens canônicos (cascata ampla + flip dark) |
| #2190 | feat | slice 3 — origem `--vd-src-*` → `--origin-*` (balcão azul/oficina âmbar/online verde) |
| #2191 | feat | slice 4 — pipeline FSM dots → `--stage-emerald/green` (fecha loop PR1) |
| #2192 | docs | log slices 1-4 (SYNC_LOG + CODE_NOTES) |
| #2193 | feat | slice 5 — fiscal badges fg → `--pos/warn/neg` |
| #2194 | refactor | higiene token: 16 danger-red `oklch(0.55 0.18 25)` → `var(--neg)` |

**Resultado:** **list view do `/sells` 100% no modelo de cor do gabarito** (status·semântica·origem·pipeline·fiscal), flip claro/escuro de fábrica. **Foundation DS v6 completa** no `cockpit.css`: `--accent` 295 · `--origin-*` · `--stage-*` · `--pos/neg/warn(+soft)`.

## Persistência
- **git:** 10 PRs squash-merged `main` (último `8307ad246`). Branches deletadas, worktrees removidos.
- **MCP:** webhook GitHub→MCP propaga docs em ~2min.
- **Logs:** SYNC_LOG + CODE_NOTES (#2192 slices 1-4; slices 5/6 + este handoff neste commit).

## Próximos passos pra retomar
- **Higiene token restante (523 oklch):** ramp cinza hue-250 (174×) + verdes 145 + azuis 240 — **NÃO casam exato** com canon (regridem). Zerar exige **decisão Tier-0 de [W]**: aceitar cinza-quente canônico OU cunhar tokens cinza-frio. **Parado por design, não por falta de execução.**
- **Verificação real (gate F3):** subir staging + Chrome MCP pra [W] ver as 5 slices renderizadas (claro+escuro). Não feito (app não roda headless aqui).
- **PR4:** mesmo padrão DS v6 em `Sells/Edit·Show·Caixa` (cada um com gate).
- **3 componentes Tier-0 do kit** (`c-id` ficha360 · `c-tl` unificada · `c-nba`): nascem na 1ª tela que os consome (ver REUSE_MAPPING.md).

## Lições catalogadas
- **`*/` dentro de comentário CSS** (ex: `.vd-*/.os-*`) fecha o comentário → `CssSyntaxError`. Usar ` e ` ou espaço. (Pegou no #2184, corrigido.)
- **Medir o CSS alvo antes de prometer escopo:** "single-intent re-skin" virou campanha ao ver 7530 linhas / 559 oklch. Re-skin de tela madura = redirecionar a camada semântica local na DEFINIÇÃO (cobertura ampla, diff mínimo), não caçar cada oklch.
- **Gabarito ≠ tela inteira:** mock cobre só o que mostra (list view). Interior (drawer/stepper/IA) não tem alvo no gabarito → não inventar design no piloto automático.
- **Agente background pode travar** (PR2 travou ~1h15 pós-análise sem commitar) — retomei manualmente. Não confiar merge cego em background no arquivo crítico.

## Pointers detalhados
- Gate: [`memory/requisitos/Sells/sells-index-dsv6-visual-comparison.md`](../requisitos/Sells/sells-index-dsv6-visual-comparison.md) (15 dim, approved)
- Reuse-map: [`prototipo-ui/ds-v6/REUSE_MAPPING.md`](../../prototipo-ui/ds-v6/REUSE_MAPPING.md)
- Referência: `prototipo-ui/ds-v6/{gabarito-vendas,showcase,receita}.html` (#2165)
- Charter tela: [`resources/js/Pages/Sells/Index.charter.md`](../../resources/js/Pages/Sells/Index.charter.md) (v6)
