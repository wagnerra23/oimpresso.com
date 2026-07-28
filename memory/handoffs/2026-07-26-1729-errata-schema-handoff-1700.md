---
date: "2026-07-26"
time: "17:29 BRT"
slug: "errata-schema-handoff-1700"
tldr: "Errata append-only do handoff das 17:00, que foi escrito com frontmatter fora do handoff.schema.json (title/type em vez de slug/tldr) e mergeado no #4798. O gate required Append-only recusou a correção in-place — corretamente. O conteúdo daquele handoff continua válido; o que muda é a CAUSA identificada: o validador de schema não existia como comando invocável, só dentro do heredoc do CI."
decided_by: [W]
prs: [4798, 4802]
next_steps:
  - "Hook PreToolUse chamando validate.mjs antes de gravar em memory/ (backstop determinístico da skill)"
  - "Decidir se Session log/Handoff sobem de advisory a required (colide com ADR 0314 — required = só Tier-0)"
  - "As 6 decisões do handoff das 17:00 seguem abertas"
related_adrs:
  - 0130-handoff-append-only-mcp-first
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# Errata 2026-07-26 17:29 — schema do handoff das 17:00

> Complementa (não substitui) [`2026-07-26-1700-obra-parada-sentinela-entrega.md`](2026-07-26-1700-obra-parada-sentinela-entrega.md). O conteúdo daquele handoff **continua válido** — as 6 decisões pendentes, o estado do CI e os achados seguem valendo. Esta errata corrige o registro de **metadado** e adiciona a causa-raiz que só foi diagnosticada depois.

## O defeito

O handoff das 17:00 e o session log da mesma sessão foram escritos com frontmatter genérico em vez do schema canônico:

| Arquivo | `required` do schema | O que foi escrito |
|---|---|---|
| session log | `[date, topic]` | `title` (não `topic`) |
| handoff 17:00 | `[date, slug, tldr]` | nem `slug` nem `tldr` |

Foram mergeados no [#4798](https://github.com/wagnerra23/oimpresso.com/pull/4798) porque os jobs `Session log` e `Handoff` do `memory-schema-gate` são **advisory**, e o auto-merge só espera os required.

## O que foi corrigido, e o que não foi

**Session log:** corrigido in-place ([#4802](https://github.com/wagnerra23/oimpresso.com/pull/4802)) — session log não é append-only.

**Handoff das 17:00:** **NÃO corrigido.** Tentei normalizar só o frontmatter, preservando o corpo. O gate **required** `Append-only canon` recusou:

> *"Handoffs são append-only (ADR 0130). Justificativa: handoff é snapshot temporal. Reescrever apaga o que outro Claude/humano leu — quebra continuidade de sessão."*

O gate está certo e eu estava tentando contornar. O frontmatter daquele arquivo **fica inválido**, como dívida honesta — o `memory-schema-gate` é diff-aware, então arquivo não-tocado não é reavaliado. Append-only significa exatamente isto: o histórico fica como foi escrito, erro incluído, e a correção vive num arquivo novo.

## A causa-raiz (o achado que vale mais que a errata)

A cadeia de defesa do schema tinha 4 peças e **uma não existia**:

| Peça | Estado |
|---|---|
| schema (`.json`) | ✅ 9 arquivos |
| **validador** | ❌ **só dentro do CI** — heredoc `node <<'NODE'` de ~100 linhas no `memory-schema-gate.yml` |
| skill `memory-schema-preflight` | ✅ existe, e promete *"roda validator local antes de commit pra zerar o loop CI fail"* |
| gate no CI | ✅ (advisory para estas 2 famílias) |

**A skill mandava rodar uma ferramenta que não era invocável.** Mesmo tendo disparado, não havia comando para cumprir a promessa — o "chokepoint fantasma" do `§5` das proibições.

Consertado no #4802: `scripts/memory-schemas/validate.mjs` vira comando real (local + CI + selftest), e o workflow passa a chamá-lo (fonte única). Ao rodá-lo nos arquivos reais ele achou **um erro que o CI ainda não tinha acusado** (`authors: [W, CC]` — `CC` fora do enum), que é a prova de que resolve a causa.

## Defeito colateral registrado

Ao tornar a ferramenta local, apareceu um **falso verde**: no Windows o `globSync` devolve `memory\sessions\x.md` e o `git diff --name-only` devolve `memory/sessions/x.md` — a interseção dava vazio e o validador reportava *"[OK] nenhum arquivo"* sobre arquivos que estavam ali. Não aparecia antes porque o heredoc só rodava no CI (Linux). Normalizado para posix nos dois lados.

Mesma família do `execSync` não importado que virou *"88 de 88 órfãos"* mais cedo na mesma sessão: **instrumento reportando ausência quando não chegou a olhar**.

## Estado MCP no momento do fechamento

- `cycles-active` → nenhum cycle ativo em COPI
- `my-work` → 6 tasks em REVIEW: US-TR-309, US-TR-310, US-PG-008, US-PROD-027, US-TR-305, US-TR-306
