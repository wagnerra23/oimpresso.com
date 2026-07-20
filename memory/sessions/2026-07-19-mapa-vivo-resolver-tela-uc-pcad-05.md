---
date: "2026-07-19"
topic: "Máquina de docs derivada (mapa vivo → resolver por-tela) + review/merge Produto da Maiara + UC-PCAD-05 Tier 0"
authors: [C]
prs: [4524, 4535, 4544, 4545, 4549, 4553, 4554, 4417, 4449, 4464, 4471]
related_adrs:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0093-multi-tenant-isolation-tier-0
---

# Sessão 2026-07-19 — o mapa de arquivos por tela vira máquina viva (e o resto)

> **TL;DR:** o "mapa de arquivos por tela" apodrecia (session logs datados) → virou máquina viva: `npm run screen:files <Mod/Tela>` (resolver `--screen`) que acha TODOS os arquivos + resolve por DECLARAÇÃO do charter (não por nome). No caminho: 6 briefings refrescados, `HISTORIA-LINHAGEM`, cobertura de briefing não-gameável, 4 PRs da Maiara revisadas+mergeadas, e o achado Tier 0 UC-PCAD-05 (cross-tenant no `store()`) virou fix CT100-verificado. **11 PRs MERGED.** Adversário-antes-de-construir matou ≥4 propostas ruins.

## Fio condutor

Wagner: *"onde fica o mapa de arquivos [por tela]?"* → *"acho que está apodrecendo, está fora das máquinas. deveria ser como?"*. A sessão inteira desenrolou desse fio: **derivado+enforçado sobrevive, escrito+lembrado apodrece** (lei-mãe [ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)).

Cada peça passou por **adversário antes de construir** (padrão do [W]) — que matou ou reshapeou várias propostas antes de virarem código.

## O arco (7 PRs meus, todos MERGED)

1. **[#4524] mapa = COMANDO, não arquivo** — matei o gerador-fóssil `gen-mapa-telas.py` (zero invocadores), nomeei a régua viva (`npm run screen-coverage:report`), resposta canônica em `how-trabalhar.md` (`@import` toda sessão). O adversário confirmou: subtração + porta nomeada, não teste novo.
2. **[#4535] refresh dos 6 BRIEFINGs stale** — 6 agentes paralelos, refresh HONESTO (cada um corrigiu um fato stale REAL vs o código, não carimbo vazio). Staleness 6→0. Frontmatter schema-válido.
3. **[#4544] `memory/HISTORIA-LINHAGEM.md`** — a linhagem convergida (Delphi WR/OfficeImpresso → fork UltimatePOS ADR 0001 → oimpresso modular) que estava órfã em 10+ nós. Timeline datada + ponteiros, **zero número vivo** (aponta, não restateia — lápide §5 2026-07-17). Doc estático porque história não muda.
4. **[#4545] `--strict-coverage`** no briefing-staleness — cobertura de EXISTÊNCIA (módulo backend sem BRIEFING = não-gameável), advisory-first. O adversário matou o gate de FRESCOR (gameável por data auto-escrita); só a cobertura é enforçável.
5. **[#4549] resolver por-tela (`--screen`)** — "achar TODOS os arquivos da tela". Refutei a máquina agregada (fragmentada + naming drift + presença≠linkagem). O resolver unifica + é honesto sobre ambiguidade (FLAG, não adivinha).
6. **[#4553] charter DECLARA artefatos** — `+related_runbook/visual_comparison/proto_baseline` (opcionais). O resolver agora resolve por DECLARAÇÃO (autoritativo) + pega `declared-missing` (linkagem quebrada). Demo real: Produto/Create flipou `[por nome]`→`[declarado]`.
7. **[#4554] UC-PCAD-05 Tier 0** — fix cross-tenant no `store()` (FKs de insumo business-scoped) + teste restaurado. Verificado no CT 100 (lane Estoque·MySQL verde) antes do merge.

## Review + merge das 4 PRs da Maiara ([M]/SupportWR)

- **[#4417]** trio Create (casos+teste) + fix Tier 0 (dup 500→404) · **[#4464]** corrige premissa FALSA do multiplicador (percentage JÁ é multiplicador — impede construir o que já existe) · **[#4471]** decisão [W] 3 campos $ na aba geral · **[#4449]** protocolo pedido-de-contrato + ordem de fonte (incl. Delphi).
- Trabalho **excelente e honesto** (auto-correção, recibo datado, anti-tautologia). Reconciliei o conflito do #4464 no `Produto/BRIEFING.md` (meu #4535 vs a correção dela) mantendo as duas.

## As três decisões da mesa (Wagner "as três")

1. **Preço-na-tela** — RESOLVIDO por decisão: [W] já decidiu (Opção A, #4471) → é o TODO da US-PROD-023, não bug nem Non-Goal.
2. **UC-PCAD-05** — #4554 (fix + teste, CT 100-verificado).
3. **Charter declara** — #4553.

## Lições / padrões reforçados

- **Adversário-antes-de-construir** salvou ≥4 vezes: matou o gate de frescor gameável, o guard-por-nome, o teste tautológico, a agregação-que-regride-ao-fóssil.
- **Merge-forcing de PRs**: `visual-regression` é required + path-filtered → PRs sem `.tsx` ficam "expected"/lentos; a suíte Browser roda ~15-20min mesmo pra scripts. `module-grades-gate` é advisory (ADR 0314) mas stale-required no branch protection → label `module-grades-allowed-regression`.
- **Tier 0 não-verificável local** (UC-PCAD-05): DRAFT PR → CI Estoque·MySQL (CT 100) prova → só então merge. Respeitou a REGRA MESTRE + o deferimento deliberado dos owners.
- **Incidente meu**: editei `D:\oimpresso.com\...` (repo principal, branch de outra sessão) em vez da worktree — revertido cirurgicamente. Sempre conferir cwd git vs path do Edit.

## Pointers

- Handoff do fechamento: `memory/handoffs/2026-07-19-*.md`
- Doutrina do mapa: `how-trabalhar.md §Mapa de arquivos por tela`
- Resolver: `scripts/qa/screen-coverage-map.mjs --screen <Mod/Tela>` (`npm run screen:files`)
