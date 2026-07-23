---
status: proposal
title: "IDs estáveis doc↔doc que sobrevivem a move — design de construção (links nativos + auto-religador)"
proposed_by: Claude — decisão [W] 2026-07-23 (construir; override do veredito inicial)
proposed_at: 2026-07-23
relates_to:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio
  - 0130-handoff-append-only-mcp-first
  - 0273-anchor-lint-us-implementado-em
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
---

# PROPOSAL — IDs estáveis doc↔doc (design de construção)

> **Decisão [W] 2026-07-23:** *"vai precisar criar os ID. se necessário remover o hook das ADRs para conseguir fazer as alterações eu vou fazer."* [W] é dono e soberano — **construir**, override do veredito inicial ("over-engineering"). Este doc vira o **design**. Arquitetura escolhida por [W] = *"o melhor"* → **links nativos + auto-religador por ID**. Rollout escolhido por [W] = **backfill completo agora** — reconciliado com o muro §5 2026-07-12 abaixo. **Nada implementado até [W] ratificar o design; nenhuma branch protection tocada.**

## 0. Premissa corrigida (LC-08 — verificado no HEAD)

O enunciado da grade dizia *"deadlink-gate promovido a required, ADR 0347"*. **Errado nesta branch:** ADR **0347 não existe** (máx `0346`); o gate ancora na **[ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)** (#4700); é **advisory por lei**, não em `required-checks-baseline.json`; não há #4704. O gate detecta e cataraca **1105** links mortos em **571** arquivos vivos — mas **não** bloqueia merge hoje.

## 1. O que já existe (reusar, não recriar)

- **Número de ADR = ID estável, já dominante.** ~14,9k menções `ADR NNNN` vs ~3,8k links de path. `adr-index-generate.mjs` deriva a identidade do filename (`NNNN`), imune a rename. **Para ADR, o ID já existe — não se cria, se lê.**
- **Máquina de realocação** (`document-relocation-{classifier,adversary,executor}.mjs`): `git mv` + relink contexto-consciente de todos os referrers + **`move-with-tombstone`** (stub físico no path antigo pra referrers que não podem ser reescritos — append-only/gate-guarded). **Engine de relink já existe — o auto-religador reusa.**
- **`movementHistory()`**: mapa old→new durável via trailer `Document-Move`, validado no HEAD.
- **deadlink-gate**: detector por-arquivo com ratchet.

**O gap real (medido):** essas primitivas são **opt-in e não-praticadas** — 157 renames históricos de `memory/**` passaram por fora da máquina. Falta **identidade explícita + auto-cura**, não uma primitiva nova de zero.

## 2. Raio de explosão do "backfill completo" (MEDIDO 2026-07-23 — não estimado)

3205 `.md` em `memory/`. Stampar `id:` físico em cada um tem custo MUITO diferente por família:

| Família | Qtd | ID vem de | Editar o arquivo? |
|---|---|---|---|
| `decisions/` (ADR) | 352 | **número** (derivado do filename) | **NÃO** — append-only intacto |
| `sessions/` + `handoffs/` | 811 | **date-slug** (derivado do filename) | **NÃO** — append-only intacto |
| `reference` + `governance` + `research` + `audits` + `dominios` + raiz | ~670 | `id:` stamped | **SIM** — schemas `additionalProperties:true`, **backfill seguro** |
| `requisitos/**` | 1011 | `id:` stamped | ⚠️ **acorda anchor-lint + SDD scorecard (REQUIRED)** |
| `charter` (`Pages/**`) | 238 | `id:` stamped | ⚠️ **acorda schema charter (REQUIRED)** |

**Fato duro:** stampar `id:` nos 1249 gate-toxic (requisitos+charter) põe cada um no diff → `anchor-lint`/scorecard mordem **dívida pré-existente** (§5 2026-07-12; PR #4156 morreu fazendo isso em **52** SPECs). Stampar nos 811 append-only viola o hook. Ou seja, o "completo AGORA" na forma **físico-em-todos** = 811 violações de hook + pagar/waivar 1249 arquivos de dívida required no mesmo PR — o que a lápide já classificou como **teatro que o `enforce_admins` nem deixa passar**.

## 3. Como entregar "ID completo AGORA" sem bater no muro

**Insight-chave: "todo doc TEM um ID estável" ≠ "todo arquivo tem um campo `id:` stamped".** O ID pode ser **derivado** onde stampar é tóxico — igual o `adr-index-generate` já faz pra ADR.

- **ADR (352)** → id = número. Derivado. Zero edição. **Sua oferta de remover o hook das ADRs NÃO é necessária.**
- **sessions/handoffs (811)** → id = date-slug do filename (já único e estável). Derivado. Zero edição. Append-only intacto.
- **reference/governance/… (~670)** → `id:` stamped. Backfill físico **real e landável** (schema `true`).
- **requisitos/charter (1249, gate-toxic)** → `id:` **forward-only + oportunístico** (quando o arquivo já for tocado por trabalho real). **Meanwhile o move deles é coberto por git-rename-detection + tombstone** — o auto-religador **não precisa** de `id:` pra religar um rename puro; o `id:` é upgrade contra rename+edit-pesado.

Resultado: **o índice `id→path` cobre os 3205 HOJE** — 1163 por derivação (zero-edit, append-only-safe), ~670 por stamp seguro, 1249 por git-rename+tombstone (+ stamp quando tocados). É "completo agora" no sentido que importa (**todo doc addressable por ID já**), sem 811 hook-violations nem 1249 gate-awakenings.

> **Checkpoint [W] — RESOLVIDO 2026-07-23: "físico em todos agora".** [W] escolheu stampar `id:` físico. Refinamentos honestos que não contrariam a escolha:
>
> - **ADR (352) e session/handoff (811) já têm id físico: o filename** (`0094-…`, `2026-07-23-…`). Stampar `id:` neles duplica o filename editando 811 append-only por zero ganho → **trato o filename como o id físico deles** (o `adr-index` já faz isso). O hook append-only fica intacto e **não precisa ser afrouxado**, a menos que [W] queira o campo redundante mesmo assim.
> - **Os 1919 não-append-only (670 seguros + 1249 gate-toxic) recebem `id:` físico.** Os 670 landam direto (schema `true`). Os **1249** gate-toxic exigem a peça de aterrissagem abaixo — senão travam `anchor-lint`/scorecard (required, `enforce_admins`).
>
> **Peça de aterrissagem (§3-bis) — obrigatória pra "físico" não virar teatro travado:** uma **ADR** que (a) autoriza o big-bang de `id:` como exceção explícita e única ao §5 2026-07-12; (b) torna `anchor-lint` + charter-schema + `distiller_freshness` **cientes de diff `id-only`** — um diff cuja única mudança é adicionar `id: <slug>` no frontmatter é **no-op** pra esses gates (não cria dívida de âncora nem muda o significado de frescor), com **selftest que prova que só id-only é isento** (safe-subtraction ADR 0271, não afrouxamento cego). Sem essa peça, [W]-admin **não** mergeia os 1249 (a própria lápide §5 diz: `enforce_admins` bloqueia).

## 4. Componentes a construir (todos reusam o que existe)

1. **Schema:** adiciona `id` (opcional agora; required-forward via template) nos memory-schemas. Famílias `additionalProperties:true` aceitam sem reprovar retroativo.
2. **`scripts/governance/doc-id-index.mjs`** (gerador, modelo `adr-index-generate`): varre `memory/**`, **deriva** id de ADR (número) e session/handoff (date-slug), **lê** `id:` stamped onde houver → emite `memory/_id-index.generated.json` (`id → path atual`). Detecta colisão de id. Imune a move (identidade = id). Selftest hermético.
3. **Auto-religador** (estende `document-relocation-executor`): num move (git-rename **ou** id que aparece em path novo), reescreve os **links relativos nativos** que apontavam pro path antigo. Append-only/gate-guarded → **tombstone** (nunca edita). Roda em hook/CI. Reusa o engine de relink contexto-consciente + o adversário que já valida completude.
4. **deadlink-gate estende:** antes de marcar `[x](path-morto.md)` como morto, consulta o `id-index` — se o `id` daquele doc vive em outro path, **resolve e aponta o fix** em vez de só contar morto.
5. **Template:** docs novos nascem com `id:` (forward-only, custo zero).

## 5. Armadilhas §5 a NÃO cometer (auto-imposto)

- **Não** stampar em massa os gate-toxic (§5 2026-07-12 é máquina). Derivar/forward-only, não big-bang.
- **Não** editar referrer append-only pra "arrumar link" — o tombstone preserva o path, não toca o referrer.
- **Não** deixar o auto-religador virar alias-fantasma que só satisfaz o checker: ele reescreve o **link nativo real** OU deixa tombstone físico — o clique no GitHub sempre resolve, nunca 404 silencioso.
- **Purge é irredutível:** id cujo alvo foi deletado (auto-mem 2026-06-07) **não resolve** — fica flagged com razão "purgado", nunca "resolvido" por redirect fantasma.
- **Não** duplicar régua: estende `deadlink-gate` + a máquina de realocação; **não** abre 2º checker nem 2º move-record.
- **Não** promover nada a required sem mordida provada + flip [W] (ADR 0336/0314).

## 6. Plano de execução (incremental, cada PR revisável — [W] aprova cada merge)

**Construído e verde (2026-07-23, local, nada commitado):**
- ✅ **PR1** — [`scripts/governance/doc-id-index.mjs`](../../../scripts/governance/doc-id-index.mjs). Deriva id de ADR/session/handoff (= **stem completo**, único — o número puro tem 14 duplicatas), lê `id:` stamped, detecta colisão. Selftest **12/12**. Corpus real: **3179 docs · 1163 com id · 2016 sem id (worklist do backfill) · 0 colisões**. Zero mutação de conteúdo.
- ✅ **PR2** — [`scripts/governance/doc-auto-relink.mjs`](../../../scripts/governance/doc-auto-relink.mjs). Dado um move A→B, religa referrer mutável (link nativo) e deixa **tombstone** no append-only/gate-guarded. Detecta rename de **PASTA** por id stamped (Copiloto→Jana). Reusa `collectIncomingReferences`/`searchReplaceFor`/`renderTombstone` (zero reimplementação). Selftest **9/9**; irmãos (adversary/executor/id-index) intactos.
- ✅ **PR3** — modo aditivo `--triage` no `deadlink-gate.mjs` (teste do gate segue verde). Tria os mortos vivos: **1106 → 629 redirectable (auto-religáveis) + 477 purged (allowlist/remover)**. Heurística por basename (o move histórico não foi id-rastreado) — falso-positivo em basename comum, rotulado ambíguo.

**Pendente [W] (governança — não construo sem sign-off):**
- **PR4 (ADR + gate-aware)** — a peça de aterrissagem §3-bis: ADR autorizando o big-bang + `anchor-lint`/charter-schema/`distiller_freshness` cientes de diff `id-only`, com selftest. **Habilita** o stamp físico dos gate-toxic. Modifica gate required → [W] flip.
- **PR5** — stamp `id:` físico nas ~670 famílias grace/warn (landa direto).
- **PR6+** — stamp `id:` físico nos 1249 requisitos/charter, em bundles por módulo (agora landáveis via PR4).

> **Triagem separada (achado do PR1, oferecida a [W]):** 14 números de ADR duplicados (0170/0236 têm 3 cada). Não renumerо append-only sem sign-off por-item; posso gerar a tabela "qual mantém o número / qual renumera" quando [W] quiser.

## 7. Ratificação pendente [W]

1. **Design aprovado** (links nativos + auto-religador + `id:` físico nos não-append-only; filename = id físico dos append-only)?
2. **PR4 é o gargalo de governança** — a ADR que autoriza o big-bang + torna os gates id-only-aware. Ok abrir essa ADR quando chegar a hora?
3. **Começando PR1 agora** (gerador + índice, não-destrutivo, fork-agnóstico).

*Nada movido, nenhum arquivo de conteúdo mutado, branch protection intacta.*
