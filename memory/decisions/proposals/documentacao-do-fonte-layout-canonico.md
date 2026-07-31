---
status: proposal
title: "Documentação do fonte — consolidação física do trio em memory/ (Opção A, RAG-indexável)"
proposed_by: Felipe [F] + Claude
proposed_at: 2026-07-31
relates_to:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0053-mcp-server-governanca-como-produto
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0094-constituicao-v2-7-camadas-8-principios
---

# PROPOSAL — Documentação do fonte: consolidação física do trio em `memory/` (Opção A)

> **Status:** `proposal` — [F] escolheu **Opção A** (2026-07-31: *"eu gostaria do A, não gostei de
> onde ficou"*). Ratificação = **[W]** (supera ADR canon + re-path de gate required + toca
> `Modules/Jana` — CODEOWNERS `@wagnerra23`). [F] patrocina; [W] ratifica; eu executo sob aprovação.
>
> **Histórico honesto (append-only):** a v1 recomendava **Opção B** (trio fica no `resources/js/`,
> achabilidade por índice derivado). **Refutada por um fato medido que a v1 não pesou — o RAG.**

## Por que virou A — o fato do RAG (medido 2026-07-31)

O indexador do RAG/Jana — [`IndexarMemoryGitParaDb`](../../../Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php) (ADR 0053, **auto-sync via webhook GitHub** — zero "colocar no RAG" manual) — **só varre `memory/`**. **Não indexa `resources/js/`.** Logo o trio (`charter`+`casos` = contrato de design + casos de uso) **está fora do RAG**: invisível ao `memoria-search`/`decisions-search`/Jana. A Opção B (índice derivado) resolve achabilidade **por humano**; **não** põe o conteúdo no RAG. Só A faz o contrato de tela virar conhecimento pesquisável pela IA — e de graça (sync automático).

## Requisitos pra "entrar no RAG bem" (medidos — pergunta [F])

Não basta jogar em `memory/`: o RAG exige **classificação + schema**.

1. **Classificação (`type`):** o indexer atribui `type` por **glob→path** (`spec`/`briefing`/`adr`/`audit`…). **Não existe `type=charter` nem `type=casos` hoje** → precisam ser adicionados.
2. **Schema (bem definida):** o **charter JÁ tem** `charter.schema.json` (memory-schema-gate valida) ✅. O **casos NÃO tem** schema de memory (governado pelo casos-gate G-5) → ganha `casos.schema.json` OU o indexer trata `type=casos` sem frontmatter estrito.

## Decisão proposta (A)

1. **Casa canônica ÚNICA da doc do módulo = `memory/requisitos/<Modulo>/`.** O trio migra pra `memory/requisitos/<Modulo>/_telas/<Tela>.charter.md` + `.casos.md` (o `_telas/` já é a casa por-tela; `requisitos-status.mjs` já lê lá). **Nada fora de `memory/`.**
2. **O `.tsx` fica em `resources/js/Pages/`** (é código) — mas o trio deixa de depender de adjacência: o vínculo trio↔tela vira **explícito no frontmatter** (`tela: resources/js/Pages/<Mod>/<Tela>.tsx`).
3. **Supera a [ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)** — vira ADR nova `supersedes: [264]` (append-only).

## Plano de migração (turn-key — [W] aprova, eu executo end-to-end)

Ordenado por dependência; cada passo endereça um risco já medido (adversário 2026-07-31):

1. **Vínculo `tela:` no frontmatter do trio** — **conserta o G-6 que quebraria em silêncio** (o gate acha o `.tsx` pelo link, não pelo path-irmão `casos.replace('.casos.md','.tsx')`). É o passo-chave; nada move antes dele.
2. **Re-path do `casos-gate`** ([`casos-coverage-guard.mjs`](../../../scripts/casos-coverage-guard.mjs)) — varre `memory/requisitos/*/_telas/` e lê o `.tsx` do frontmatter (item 1). G-1 completude + G-6 frescor (`casos` vs git-date do `.tsx`) via link.
3. **Classificação + schema pro RAG** (⚠️ `Modules/Jana` = CODEOWNERS [W]): estender `IndexarMemoryGitParaDb` com glob `memory/requisitos/*/_telas/*.{charter,casos}.md` → `type=charter`/`type=casos`; criar `casos.schema.json` (ou tratar sem); **atualizar o glob do memory-schema-gate** (hoje hardcoded `resources/js/Pages/**/*.charter.md` → +`memory/requisitos/*/_telas/*.charter.md`).
4. **Atualizar os consumidores do path-irmão** — medido: **29 workflows** + **≥12 scripts/hooks** (`block-mwart-violation`, `charter-validate`, `detect-ui-drift`, `pt-conformance`, `screen-coverage-map`, `vital-signs`, `criar-tela.mjs`, `charter-first` hook…) → ler o trio do novo path / do frontmatter.
5. **Mover os arquivos — forward-only, por módulo** (nunca lote, §5 2026-07-12); `move-with-tombstone` (`estrutura-canon` §II.5b) pra não quebrar inbound links.
6. **Revisão adversarial do PLANO + smoke** antes de cada flip de gate required ("a IA não altera a máquina que a fiscaliza").

## Consequências

- **Positivo:** tudo num lugar (`memory/`); o trio entra no RAG/Jana **automático + classificado** (searchable por `type`); anti-rot **preservado por enforcement** (o gate re-pathado exige o frescor trio↔`.tsx` pelo link + git-date, não por adjacência).
- **Custo:** migração de 6 passos, 29+12 consumidores + Jana (Tier-0) — mitigado por forward-only + turn-key + adversarial antes do flip.
- **Trade-off honesto:** perde-se "o dev vê o contrato ao lado do `.tsx`"; ganha-se "o contrato está no RAG + o gate garante frescor por link".

## Riscos Tier 0

- **Append-only:** não edita a 0264; cria nova `supersedes`.
- **Gate required + Jana:** re-path `casos-gate` + glob/schema do RAG = **[W]** (CODEOWNERS). Nenhum flip sem [W] + adversarial.
- **G-6 silencioso:** endereçado pelo `tela:` (passo 1) — sem ele, mover = anti-rot desligado sem sinal.
- **Legado:** forward-only; `move-with-tombstone`; enumerar globs de gate por arquivo antes de tocar (EMENDA §5 2026-07-27).

## Ratificação

PR + aprovação **[W]** (CODEOWNERS). Revisão adversarial do **plano de migração** (não só do texto) antes do 1º flip. Ratificada → executo módulo-a-módulo, forward-only.
