---
date: "2026-07-18"
time: "00:24 BRT"
slug: dtcg-208-fieis-para-ponteiro
tldr: "PR #4490 MERGED: comentários do pipeline DTCG restateavam '208 fiéis' stale (o script reporta 296 vivo) — trocado por ponteiro nu pro dtcg-equivalence.mjs. Comment-only, gates verdes, aplica proibicoes §5 'oráculo errado' 2026-07-17."
prs: [4490]
decided_by: [W]
next_steps: ["Follow-up opcional: cockpit.css:3 ainda restateia '61 vars light + 45 dark' stale — mesma doença 'oráculo errado', deixado fora pra manter 1 PR = 1 intent"]
---

# Handoff — DTCG proof-count "208 fiéis" → ponteiro pro script vivo

## Estado MCP no momento do fechamento

⚠️ **MCP server indisponível no fechamento** (`cycles-active`/`my-work` retornaram "Server unavailable"). Fallback = snapshot do brief-fetch do SessionStart (Brief #372):
- **Cycle:** nenhum ativo (off-cycle).
- **HITL pendente Wagner:** 2 (FIN-004 cobrança ROTA LIVRE · runbook on-prem pós-Gold).
- **Brain B hoje:** 0% (0/50).
- **Incidentes 24h:** 0. **ADRs 24h:** 0340/0341/0342.

## O que aconteceu

Fix cirúrgico de drift de comentário no pipeline de tokens DTCG. Os comentários cravavam **`(208 fiéis)`** como prova da equivalência token↔CSS — número que **outro sistema sabe melhor**: `node scripts/governance/dtcg-equivalence.mjs` reporta **296** (light+dark) hoje, não 208.

Aplicando proibicoes §5 (2026-07-17 "doc canônico não repete número que outro sistema sabe melhor / oráculo errado"), **não** bumpei `208→296` (drifta de novo). Removi o parêntese e mantive o ponteiro nu `scripts/governance/dtcg-equivalence.mjs` — **o script É a contagem viva**. Fica idêntico à forma já usada em `inertia.css` / `foundations.css` / `_generated-*.css`.

## Artefatos gerados

- **PR #4490** (MERGED 2026-07-17 20:57 BRT · squash `f0dafb34cd`, ancestral de `main`):
  - `resources/css/cockpit.css:6` — `(208 fiéis)` → ponteiro nu (−1 token no comentário)
  - `resources/css/tokens/style-dictionary.config.mjs:24` — idem
  - Diff = **2 linhas, comment-only, zero surface change**.
- **Este handoff** + linha no índice `memory/08-handoff.md`.

## Persistência (canais)

- **git:** PR #4490 mergeado em `main`; fix confirmado vivo (`git show origin/main:...` sem "208 fiéis").
- **MCP:** indisponível no fechamento (webhook GitHub→MCP propaga o handoff em ~2min quando voltar).
- **BRIEFING:** n/a (mudança de comentário, não altera capacidade de módulo).

## Verificação (gates)

- `dtcg-equivalence` → **296 fiéis, 0 divergências** (exit 0), antes e depois.
- `tokens:version:check` → **OK, 296 tokens, fp `c079eef9c6d2` inalterado** (só lê `--token: value;` dos 6 `_generated-*.css`; comentário não entra na superfície).
- **CI #4490:** 82 required verdes (DS gate incluído); único vermelho = `module-grades-gate` **advisory** (ADR 0314 D-1, "1 módulo regrediu vs baseline" — alheio a comentário CSS), **não** silenciado.
- **Sem UI smoke:** comentário CSS é stripado no build → zero rendered output (marcador `no-ui-smoke` no PR body).

## Próximos passos pra retomar

Follow-up **opcional** (mesma doença, mesma sessão-alvo): `cockpit.css:3` ainda diz `"61 vars light + 45 dark"` — stale (surface cockpit cresceu). Aplicar o mesmo tratamento ponteiro/recibo se Wagner quiser. Deixado fora do #4490 pra manter 1 PR = 1 intent.

## Lições catalogadas

- Nenhuma violação de protocolo. Aplicação **positiva** da lápide §5 "oráculo errado" (2026-07-17): a correção certa é **subtrair** o número e apontar pro dono, não atualizar o número.
- Confirmado que `ds-token-version` é imune a comentário (hasheia só `--token: value;` dos generated) — comment-only em `cockpit.css`/config nunca move o fingerprint.

## Pointers detalhados

- Lápide fonte: `memory/proibicoes.md` §5 entrada 2026-07-17 "oráculo errado".
- Sibling: PR #4487 (build_consumes_this truth-fix).
