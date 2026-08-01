---
status: proposal
title: "Documentação do fonte — consolidação física do trio em memory/ (Opção A, decisão organizacional [F])"
proposed_by: Felipe [F] + Claude
proposed_at: 2026-07-31
decided_at: 2026-08-01
relates_to:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0053-mcp-server-governanca-como-produto
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0094-constituicao-v2-7-camadas-8-principios
---

# PROPOSAL — Documentação do fonte: consolidação física do trio em `memory/` (Opção A)

> **Status:** `proposal` — **[F] decidiu o move físico** (2026-08-01: *"prefiro mudar para memória… mover os arquivos é escolha minha e eu quero"*). Ratificação = **[W]** (supera ADR canon + reescrita de gates required + toca `Modules/Jana` — CODEOWNERS `@wagnerra23`). [F] patrocina; [W] ratifica; eu executo sob aprovação.
>
> **Decisão registrada com honestidade (o ponto que o adversário provou):** o move **NÃO é requisito de RAG** — o trio pode ser indexado **in-place** (glob no `IndexarMemoryGitParaDb`) sem mover nada. [F] escolheu mover **mesmo assim, como decisão organizacional** ("raiz é memory, nada fora"), ciente do trade-off. É soberania [F]/[W], não consequência técnica.

## Histórico honesto (append-only)

- **v1 → B** (trio fica no `resources/js/`, achabilidade por índice derivado). Refutada.
- **v2 → A por causa do RAG.** **Refutada pelo adversário do plano** (2026-08-01): o RAG é alcançável in-place; a tese "só A põe no RAG" era falsa.
- **v3 (esta) → A por escolha organizacional [F]**, com o plano **corrigido** pelos achados do adversário (abaixo). O RAG deixa de ser justificativa; a justificativa é "quero tudo em `memory/`".

## Alternativa registrada e DECLINADA por [F]: RAG in-place

Adicionar `resources/js/Pages` (filtrado a `.charter/.casos.md`) como raiz do `coletarRecursivo` em `IndexarMemoryGitParaDb` → o trio entra no RAG **sem mover, sem tocar gate**. Entrega a Jana-searchability. **[F] declinou** em favor do move físico (organizacional). Fica registrado como o caminho de menor custo, caso [W] reavalie.

## Decisão (A)

1. **Casa canônica ÚNICA = `memory/requisitos/<Modulo>/`.** O trio migra pra `memory/requisitos/<Modulo>/_telas/<Tela>.charter.md` + `.casos.md` — **mesma estrutura, raiz `memory/`**. Nada fora de `memory/`.
2. **O `.tsx` fica em `resources/js/Pages/`** (é código). O vínculo trio↔tela vira **explícito no frontmatter** (`tela: resources/js/Pages/<Mod>/<Tela>.tsx`) — mas isto é **dado inerte até o gate ler** (ver plano passo 2).
3. **Supera a [ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)** — ADR nova `supersedes: [264]` (append-only).

## Plano de migração CORRIGIDO (adversário 2026-08-01) — [W] ratifica, eu executo

> Não é turn-key. É projeto separado. Os defeitos da v2 estão corrigidos:

1. **Reescrever o `casos-coverage-guard.mjs` para DUAL-RESOLVER** — o G-1/G-6/G-7 hoje resolvem o `.tsx` por **path-irmão computado** (`file.replace('.casos.md','.tsx')`, `existsSync(dir+base+'.charter.md')`), **não leem frontmatter**. Passa a resolver o trio nas **DUAS árvores ao mesmo tempo** (irmão em `Pages/` OU vinculado em `_telas/` via `tela:`), mantido até o último módulo mover. ⚠️ **É o passo caro** — o `tela:` do frontmatter é inerte sem esta reescrita (correção: v2 chamava isto de "re-path").
2. **Inventário COMPLETO dos consumidores** (a v2 dizia "≥12" — incompleto). Os REQUIRED omitidos, achados pelo adversário:
   - **`anchor-content-check.mjs`** (`Ancora de design nao-shell F2/F6`) — **required**
   - **`charter-live-signal.mjs`** (`charter status:live precisa de sinal de prod`) — **required**
   - **`visreg-states-lint.mjs` + manifesto `tests/Browser/visreg-states.json`** (dentro de `visual-regression`) — **required**
   - advisory: `charter-refs`, `charter-us-lint`, `charter-promote-signal`, `reconcile-triplet`, `design-code-map-check`, subsistema `prototipo-ui/ancora.mjs` + `_lib-charter.mjs` + `block-ancora-no-olho`, `charter-da-tela-que-o-controller-serve`
   - **`requisitos-status.mjs` REGRIDE** — lê `_telas/` como fluxo "(blade)", geraria falsa lacuna + contagem dobrada; precisa aprender o trio React movido.
3. **RAG (Jana = CODEOWNERS [W]):** glob `memory/requisitos/*/_telas/*.{charter,casos}.md` → `type=charter`/`type=casos` (⚠️ glob PHP não recursa, `_telas` é 2 níveis, `{}` exige `GLOB_BRACE`); casos entra bem **sem** schema (o RAG degrada gracioso — `casos.schema.json` é necessidade do memory-schema-gate, não do RAG); atualizar o glob hardcoded do memory-schema-gate.
4. **NÃO usar `move-with-tombstone`** — é a ferramenta ERRADA (correção da v2): é `memory/`-scoped, só relinka referências **literais** (as do trio são **resoluções path-irmão computadas** que o scanner não acha), e o stub que ele deixa em `Pages/` **quebra o Charter schema REQUIRED + finge o G-1 verde** (LC-11). Mover = `git mv` real + a reescrita dual-resolver (passo 1) segurando a transição.
5. **Mover forward-only, por módulo** — mas **só depois** do dual-resolver (passo 1), senão o `.tsx` órfão em `Pages/` falha o G-1 **full-tree** (o gate varre `Pages/**` inteiro, não diff).
6. **Adversarial do PLANO (feito) + smoke por módulo** antes de cada flip.

## Riscos Tier 0

- **Append-only:** não edita a 0264; ADR nova `supersedes`.
- **Gates required (≥5) + Jana:** reescrita = **[W]** + adversarial + bite-test antes do flip ("a IA não altera a máquina que a fiscaliza").
- **Diff-aware por-arquivo:** cada charter movido é "novo" no diff → re-validado contra o `charter.schema.json` **strict** → dívida latente grandfathered **falha** (EMENDA §5 2026-07-27, 3 eixos). Mover um por vez, verde a verde.
- **Ordem:** passo 1 (dual-resolver) **antes** de qualquer move; passo 5 por último.

## Ratificação

PR + aprovação **[W]** (CODEOWNERS). O plano corrigido já passou por revisão adversarial (2026-08-01, veredito no PR #5136). Ratificada → executo módulo-a-módulo, forward-only, dual-resolver segurando a transição.
