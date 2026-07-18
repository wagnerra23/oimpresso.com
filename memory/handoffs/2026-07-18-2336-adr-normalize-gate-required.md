---
date: "2026-07-18"
time: "23:36 BRT"
slug: adr-normalize-gate-required
tldr: "Normalizou 143 ADRs (139→0 inválidos, ADR 0342 relax de slug), limpou 55 refs mortos, e LIGOU o gate ADR (memory/decisions/*.md) como required (ADR 0343, baseline 29→31, flip do vivo). Estado main: 349 ADRs, 0 inválidos, gate required, protection-drift 🟢. + fix do gate append-only pra _TEMPLATE.md (#4523) e 3 chips de follow-up."
prs: [4456, 4467, 4479, 4523]
decided_by: [W]
related_adrs: [0342-adr-slug-pattern-permite-legacy-filename-ponto-maiuscula, 0343-promove-adr-gate-required-emenda-0341]
next_steps: ["Mergear #4518 (chip 2 forward-only, destravado) — decisão do [W]/sessão do chip", "Chip 3: medir cobertura de UC (ADR 0264) ainda sem artefato no main", "Chip do visual-regression dual-fire (task_528df934) segue aberto"]
---

# Handoff 2026-07-18 23:36 — ADRs normalizados + gate `ADR` required

## Estado MCP no momento do fechamento

⚠️ **MCP indisponível** neste fechamento (`brief-fetch` curl exit 28 — timeout). Snapshot via git:
- `origin/main` HEAD inclui #4515 (chip 1) e #4523 (fix do gate); `git log` confirma os 5 PRs desta linha mergeados.
- Não consegui rodar `cycles-active`/`my-work`/`decisions-search` (servidor MCP fora). Retomar com `brief-fetch` quando voltar.

## O que aconteceu

Chip "normalizar 143 ADRs + ligar a máquina de verificação". Entregue em 3 fases + follow-ups:
- **#4456** Fase 1: 139→0 inválidos (codemod das 3 classes de sintaxe) + **ADR 0342** (relax do slug pros 3 legacy irrenomeáveis).
- **#4467** Fase 1b: −54 refs `related` mortos + 1 `superseded_by` corrigido.
- **#4479** Fase 3: **ADR 0343** promove o gate `ADR (memory/decisions/*.md)` a required — baseline 29→31 + flip do vivo (`gh api --input` add-only, ASCII, sem mojibake) + `screen-coverage-gate` reconciliado. `protection-drift` 🟢, `enforce_admins` intacto.
- **#4523** fix: gate append-only excluía mal o `_TEMPLATE.md` de handoff → corrigido pra `[0-9]{4}-.+`.

## Estado final medido (origin/main)
- **349 ADRs · 349 válidos · 0 inválidos** (AJV, gate-aligned).
- Gate `ADR` **required no vivo** (31 contexts) · `protection-drift` 🟢.
- Famílias memory-schema: charter/spec/adr = required (0); reference/briefing/runbook/session/handoff = advisory (dívida medida, muito é sem-frontmatter).

## Artefatos gerados
- ADR [0342](../decisions/0342-adr-slug-pattern-permite-legacy-filename-ponto-maiuscula.md) + [0343](../decisions/0343-promove-adr-gate-required-emenda-0341.md) (merged)
- `governance/required-checks-baseline.json` (29→31 + 2 entradas `promocoes`)
- `.github/workflows/governance-gate.yml` (fix regex handoff)
- Session log [2026-07-18](../sessions/2026-07-18-adr-normalize-gate-required.md)

## Persistência
- **git:** 5 PRs mergeados em `main` (canon).
- **MCP:** webhook GitHub→MCP propaga quando o servidor voltar (estava fora no fechamento).
- **BRIEFING:** N/A (trabalho de governança, não módulo de produto).

## Próximos passos pra retomar
- `brief-fetch` (quando MCP voltar) → estado consolidado.
- Chips abertos: **#4518** (chip 2 forward-only) destravado, aguarda merge; chip 3 (cobertura UC) sem artefato; chip `visual-regression` dual-fire (task_528df934).

## Lições catalogadas
- Relatar contagem de schema exige contar TODAS as categorias (o "0 inválidos" pulou 4 sem-frontmatter — [W] pegou).
- Sessão paralela não-pushada = superada, não duplicação: não pushar.
- `visual-regression` 2-runs+cancel trava merge — re-rodar o cancelado destrava (chip aberto pra fix de raiz).
- Mass-fix de legado é proibido (§5 2026-07-12) — advisory reduz forward-only, nunca backfill.

## Pointers
- Session log detalhado: [../sessions/2026-07-18-adr-normalize-gate-required.md](../sessions/2026-07-18-adr-normalize-gate-required.md)
- PRs: [#4456](https://github.com/wagnerra23/oimpresso.com/pull/4456) · [#4467](https://github.com/wagnerra23/oimpresso.com/pull/4467) · [#4479](https://github.com/wagnerra23/oimpresso.com/pull/4479) · [#4523](https://github.com/wagnerra23/oimpresso.com/pull/4523)
