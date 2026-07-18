---
date: "2026-07-18"
topic: "Normalização de frontmatter dos ADRs + promoção do gate adr a required + limpeza de refs mortos + fix do gate append-only pra templates + 3 chips de follow-up"
authors: [C]
prs: [4456, 4467, 4479, 4523]
related_adrs: [0342-adr-slug-pattern-permite-legacy-filename-ponto-maiuscula, 0343-promove-adr-gate-required-emenda-0341]
---

# Sessão 2026-07-18 — Normalizar ADRs + ligar o gate `ADR` required

## TL;DR

O chip pedia: normalizar o frontmatter dos 143 ADRs que violavam o schema e **ligar** o gate `ADR (memory/decisions/*.md)` como required (a família mais dura das 6 que a ADR 0341 deixou advisory). Feito em 3 fases + follow-ups. Estado final em `main`: **349 ADRs, 349 válidos, 0 inválidos, gate required no vivo, protection-drift 🟢**.

## O que aconteceu (arco)

1. **Fase 1 — [#4456](https://github.com/wagnerra23/oimpresso.com/pull/4456):** codemod cirúrgico corrigiu as 3 classes de sintaxe (title `!!binary`→literal, `decided_at` sem aspas→string, refs bare-number→slug), **139 → 0** inválidos (AJV), corpo byte-idêntico 140/140. Como 3 filenames legacy têm maiúscula/ponto (`0168-tier-A`, `0224/0225-claude-4.8`) e **não podem ser renomeados** (append-only), criei a **ADR 0342** relaxando o slug pattern pra `[A-Za-z0-9.-]`. Reconciliei com o **#4439** (o "0341" real, que o [W] mergeou no meio da sessão e deferiu o adr citando append-only — sem ver a exceção 0297).

2. **Fase 1b — [#4467](https://github.com/wagnerra23/oimpresso.com/pull/4467):** removi 54 refs `related` a arquivo inexistente + corrigi 1 `superseded_by` (0032 → o slug real `0048-framework-agentes-...`, com dedup). Corpo byte-idêntico 39/39.

3. **Fase 3 — [#4479](https://github.com/wagnerra23/oimpresso.com/pull/4479):** **ADR 0343** (emenda à 0341, rebate o medo de "143 decisões intocáveis": gate é diff-aware, corpo segue imutável, etiqueta migra via 0297) + baseline 29→31 (`+ADR (...)` `+screen-coverage-gate` reconciliado) + flip do vivo via `gh api --input` add-only (ASCII, sem mojibake). `protection-drift` 🟢, `enforce_admins` intacto.

4. **Medição das 8 famílias** (fresco, gate-aligned): charter/spec/adr = 0 (required); reference 123/125, briefing 65/77, runbook 124/153, session 283/462, handoff 121/275 = advisory (muito é literalmente sem-frontmatter).

5. **3 chips** spawneados: (1) 4 ADRs sem-FM, (2) forward-only das 5 advisory (SEM big-bang), (3) medir cobertura de UC (ADR 0264). Chip 1 landou (**#4515**, adr 4→0). Chip 2 abriu **#4518**, travado por falso-positivo do gate append-only.

6. **Fix do gate — [#4523](https://github.com/wagnerra23/oimpresso.com/pull/4523):** o step "Block handoff edits" casava `memory/handoffs/.+\.md` e pegava `_TEMPLATE.md`. Corrigi pra `[0-9]{4}-.+` (só handoff datado real), espelhando o check de ADR. Destravou o #4518.

## Lições

- **"0 inválidos" foi impreciso 1×:** olhei válido/inválido e pulei a categoria "no frontmatter (legacy warn): 4". [W] perguntou "quais estão corretos?" e ao re-medir com harness que conta tudo, apareceram 4 ADRs sem frontmatter (0126/0128/0246/0247, dormentes). Corrigido pelo chip 1. Lição: relatar contagem de esquema exige contar TODAS as categorias, não só valid/invalid.
- **Sessão paralela não-pushada é superada, não duplicação:** a outra sessão tinha "5 residual, nada pushado" — forkou pré-0342; o `main` já estava no destino. Não pushar.
- **`visual-regression` dispara 2 runs e a concurrency cancela um** → a branch protection lê o cancelado e trava merge. Contornado re-rodando o cancelado; chip aberto pra corrigir os gatilhos.
- **Mass-fix de legado é proibido** (§5 2026-07-12): as 5 advisory reduzem forward-only + oportunístico, nunca backfill.

## Pointers
- Handoff: [2026-07-18-2336](../handoffs/2026-07-18-2336-adr-normalize-gate-required.md)
- ADRs: [0342](../decisions/0342-adr-slug-pattern-permite-legacy-filename-ponto-maiuscula.md) · [0343](../decisions/0343-promove-adr-gate-required-emenda-0341.md)
