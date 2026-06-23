---
slug: 0304-alocacao-numero-ciente-trabalho-em-voo
number: 304
title: "Alocação de número (ADR/US) ciente de trabalho em voo + Check N (colisão de US-ID)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-22"
module: governance
quarter: 2026-Q2
tags: [governanca, adr, us, colisao, numeracao, alocacao, ratchet, memory-health]
supersedes: []
superseded_by: []
related: ["0028-adrs-numeracao-monotonica", "0180-drift-numero-adr-0178-conflito-paralelo", "0257-adr-status-lifecycle-kind-modelo-canonico", "0298-teto-de-governanca-anti-proliferacao-gates", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura", "0256-knowledge-survival-meia-vida-catraca-sentinela"]
pii: false
---

# ADR 0304 — Alocação de número (ADR/US) ciente de trabalho em voo + Check N

> Operacionaliza a [ADR 0028](0028-adrs-numeracao-monotonica.md) (numeração monotônica/única) que vinha sendo descumprida. Origem: proposta [`2026-06-22-alocacao-atomica-numero-adr-us`](proposals/2026-06-22-alocacao-atomica-numero-adr-us.md), aprovada por Wagner ("aprovado merge").

## Status

Aceito — Wagner 2026-06-22. Implementação provada antes da ratificação (doutrina Onda 0): `next-id.mjs` testado (ADR→0304, US-GOV→045, <1s), Check N grandfatherado na main (0 🔴) e mordendo no `gate-selftest` (catraca `memory-health-us`, 20/20).

## Contexto

Colisão de número é **crônica**: `_INDEX-LIFECYCLE.md` registra **14 colisões de ADR** (`0101,0102,0119,0126,0141,0170,0178,0180,0195,0216,0235,0236,0246,0294`) — a ADR 0028 "não cumprida em 14 casos". US-IDs não tinham detecção nenhuma.

Causa-raiz única: **alocação cega**. Quem cria ADR/US escolhe "próximo livre" lendo só a `main` canônica, sem ver branches/PRs não-mergeados que já reivindicaram o número. E como o append-only ([ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md)) torna a colisão **permanente** uma vez commitada, detectar tarde (no CI) não resolve — vira "registrar a colisão". A alavanca é a **alocação**, não a detecção.

Medição: a própria sessão que originou este ADR (re-montagem do tijolo anchor-fidelity, [PR #3240](https://github.com/wagnerra23/oimpresso.com/pull/3240)) gerou **3 colisões** — ADR 0297→0302→0303 (0297-excecao e doneness-lint #3239 tomaram 0297/0302) e US-GOV-043→044 (043 já era o charter_refs da onda-0). Só não viraram permanentes porque foram renumeradas à mão antes do CI.

## Decisão

### 1. `scripts/governance/next-id.mjs` — alocador ciente de trabalho em voo

CLI determinística (Node + git + gh):

```
node scripts/governance/next-id.mjs adr        # → ex: 0305
node scripts/governance/next-id.mjs us GOV     # → ex: US-GOV-045
```

Lê **duas fontes**, não uma: (1) **working tree** (canonical — `memory/decisions/` + os `SPEC.md`) e (2) **PRs abertos** (branches via `gh`, lidas do git local já fetchado). **Ignora de propósito** os milhares de branches stale do repo — incluí-los empurraria o contador à toa (um número abandonado não é "em voo"). Sem `gh`, degrada para canonical-only com aviso. Não elimina 100% a corrida (duas sessões no mesmo minuto); o resíduo é pego pela peça 2.

Wired no skill [`pre-adr-introspect`](../../.claude/skills/pre-adr-introspect/SKILL.md) (item 3, substitui o `ls + chute` manual). O `tasks-create` do MCP continua alocando US server-side; a peça 2 cobre o resíduo de ambos.

### 2. Check N (`memory-health.mjs`) — colisão de US-ID, sibling do Check A

`checkUsCollisions()` falha (🔴) em `### US-<MOD>-NNN` duplicado entre/dentro dos `SPEC.md`. Vive no **mesmo `memory-health`** — deliberadamente **sem gate novo**, respeitando o teto anti-proliferação ([ADR 0298](0298-teto-de-governanca-anti-proliferacao-gates.md)). É **ratchet-grandfathered** como os Checks C/L do mesmo arquivo: os dups legados ficam no baseline (`.checkN`) e só dup **NOVO** morde — assim não quebra a main (1 dup herdado, `US-NFSE-001`) mas **barra colisão nova**. Limpar/registrar o legado encolhe o baseline à vista. Provado pela catraca `memory-health-us` no `gate-selftest` (GT-G6, ADR 0256).

## Consequências

- ✅ Colisão deixa de nascer na fonte (alocador vê PRs/branches) → estanca o crescimento dos 14.
- ✅ Fecha a lacuna de detecção de US-ID, simétrica ao ADR, **sem proliferar gate** (ADR 0298).
- ✅ Determinístico, Node + git + gh, sem DB — encaixa no padrão das catracas/ratchets existentes.
- ⚠️ `next-id` depende de `gh` p/ ver PRs em voo (degrada a canonical-only com aviso se faltar).
- ⚠️ Janela de corrida residual permanece — coberta pelo Check A/N no CI.
- ⚠️ As 14 colisões de ADR existentes **não** são corrigidas aqui (append-only) — seguem registradas. Check N começa com `US-NFSE-001` grandfatherado; promovê-lo a 0 exige limpar esse dup (fora deste escopo).

## Alternativas consideradas

1. **Ledger de reserva** (reservar número num arquivo canônico antes de trabalhar; reservas paralelas = conflito git visível). Prevenção atômica de fato, mas **fricção alta**. Rejeitado por ora; evolução se alocador + Check N não bastarem.
2. **IDs não-sequenciais (ULID/timestamp/hash)** — elimina colisão por construção, mas quebra a convenção humana "ADR 0297" e toda referência existente. Rejeitado (disruptivo).
3. **Só detecção (sem alocador)** — não resolve: a detecção já existia pra ADR (Check A) e mesmo assim há 14, porque append-only torna a colisão permanente. O gargalo é a alocação.

## Referências

- [ADR 0028](0028-adrs-numeracao-monotonica.md) — numeração monotônica (operacionalizada aqui) · [ADR 0180](0180-drift-numero-adr-0178-conflito-paralelo.md) — registro de colisão · [ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md) — append-only (por que renumerar tarde é proibido)
- [ADR 0298](0298-teto-de-governanca-anti-proliferacao-gates.md) — teto anti-proliferação (Check N entra no memory-health, não em gate novo) · [ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) — gates · [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md) — memory-health/gate-selftest
- Proposta: [`proposals/2026-06-22-alocacao-atomica-numero-adr-us.md`](proposals/2026-06-22-alocacao-atomica-numero-adr-us.md)
- `scripts/governance/next-id.mjs` · `scripts/governance/memory-health.mjs` (Check N) · `tests/governance-fixtures/memory-health-us/`
