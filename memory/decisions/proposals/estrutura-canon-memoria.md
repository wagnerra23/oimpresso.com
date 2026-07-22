---
status: proposal
title: "Conceito único de estrutura de memory/ — schema-dono por família + normalização mecânica sob append-only"
proposed_by: Wagner + Claude
proposed_at: 2026-07-12
relates_to:
  - 0130-handoff-append-only-mcp-first
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
---

# PROPOSAL — Conceito único de estrutura de `memory/`

> **Status:** `proposal` — Wagner promove a ADR aceita após revisão.
> **Origem (2026-07-12):** Wagner *"tem que estruturar os arquivos antigos e deixar
> todos com mesmo conceito. Não importa o custo. Em thread"* — pra a máquina-matriz
> (`system-map.mjs`) poder ler status/owner/related_adrs confiável em vez de só frescor-git.

## Contexto

O corpus `memory/` cresceu organicamente: **7 famílias (~1.537 arquivos), ~683 com passivo
divergente** (frontmatter ausente ou em 5 dialetos por família). Dois globs de família
(**BRIEFING**, **reference**) nunca tiveram schema-dono no `memory-schema-gate.yml`.
Auditoria: workflow `estrutura-canon-memoria` (8 agentes, 2026-07-12).

## Decisão

1. **1 família = 1 schema-dono** em `scripts/memory-schemas/`. Criar os 2 ausentes
   (`briefing.schema.json`, `reference.schema.json` — feito nesta Fase 0); estender os 6
   existentes só via PR. **Nunca schema paralelo** ("1 tema = 1 schema").
2. **Campos transversais têm grafia única:** data → `updated_at` (SPEC mantém `last_updated`;
   session/handoff `date`; ADR `decided_at`); referência a ADR → `related_adrs` (lista inline
   de slugs); módulo → `module`; autoria por enum `W/F/M/L/E` (+`C` onde há autoria de sessão).
3. **Enforcement = AJV (enum fechado + regex), jamais presence-gate** de campo auto-declarado
   (proibicoes.md §5 / L-24). Ex: o valor do BRIEFING é o `status` enum validável, não "tem status".
4. **Dois trilhos de normalização:**
   - **MECÂNICO** (codemod, 1 PR isolado ≤300 linhas por família) para as **mutáveis**:
     SPEC, session, reference, charter, BRIEFING.
   - **APPEND-ONLY** (ADR, handoff): o **corpo é imutável** ([ADR 0130](../0130-handoff-append-only-mcp-first.md) +
     Constituição Art. 3). Histórico **não é reescrito em massa** — normalização só via labels
     sancionados (0257/0297, corpo byte-idêntico) ou **forward-only** (arquivos novos nascem no padrão).
5. **`DistillerModuloVerdade.php`** passa a emitir `status`+`updated_at` no frontmatter que gera
   (fecha o **regen-loop**: senão a próxima `jana:distill-module-truth` desfaz a normalização).
   Ship junto da wave BRIEFING.

## Fronteira honesta (o asterisco do "todos com o mesmo conceito")

Vale **100% pras famílias mutáveis** (novos + backfill). Pro **histórico append-only**
(ADRs/handoffs antigos), vale **forward-only** — reescrevê-los em massa violaria a regra
Tier 0 que protege a trilha de decisão. Os `.schema.json` usam `additionalProperties: true`
pra **não reprovar retroativamente** arquivos válidos hoje.

## Enforcement (rollout, sem gate prematuro)

Os 2 schemas novos entram **primeiro como conceito-alvo** (esta Fase 0) — **não fiados** à
matrix bloqueante. Wiring = **grace** (`JANA_VALIDATE_MEMORY_STRICT=false`, warn-only,
diff-aware) → **required** por família **só depois do backfill zerar o falso-positivo**
([ADR 0314](../0314-poda-gates-onda-2-lei-fusoes.md): required = decisão deliberada, nunca merge no calado).

## Plano de migração (ordenado por impacto-na-matriz × segurança)

- **Fase 0** (esta): criar `briefing`+`reference` schema + README + este ADR. Zero dado tocado.
- **Fase 1** SPEC (`owner→owners`, `related_adrs` inline, `anchor_format`, quoting) — alto ROI.
- **Fase 2** Reference (sinônimos → `updated_at`/`related_adrs`/`authority`) — pula os gerados.
- **Fase 3** Sessions (rename `title→topic`/`data→date`, quotar date, backfill mínimo dos 149).
- **Fase 4** Charters (dedup de chaves-variante nos 103 com `related_us`).
- **Fase 5** ADR/Handoff — **forward-only** + labels sancionados; **sem backfill retroativo** sem OK Wagner.

MECÂNICO e CURADORIA (status/nomes) são **PRs distintos** por família.

## Riscos Tier 0

- **Append-only** (o risco-mestre): não reescrever ADR/handoff antigos → `governance-gate` bloqueia.
- **memory-schema-gate**: valida só changed files; wiring em grace evita reddear PRs do time.
- **Regen-loop**: patchar o distiller junto da wave BRIEFING (senão drift de volta).
- **Links/anchors**: renomear filename quebra inbound links (handoff não-editável p/ consertar) →
  preferir **não** renomear; renumeração de ADR duplicado = ADR-errata, não frontmatter.
- **Windows**: CRLF/BOM inflam diff/quebram parse — usar Edit tool / newline correto.

---

# PARTE II — Dimensão espacial: árvore-alvo, owners de realocação e o gap do classificador (2026-07-22)

> A Parte I trata do **frontmatter** (schema por família). Falta a dimensão **espacial**:
> em qual pasta cada família mora e se a máquina de realocação (#4675–#4678 +
> [runbook](../../governance/REALOCACAO-DOCUMENTAL.md)) roteia certo. Origem: proposta de
> árvore-alvo do Wagner + review adversarial do pipeline + **medição** desta sessão. Nada aqui
> é canon ainda — é o que a promoção desta proposta a ADR precisa cravar.

## II.1 O que a máquina faz hoje (MEDIDO, não afirmado)

Reproduzível: `classifyDocument` (a função real do classificador) aplicada a cada `.md` de
`memory/{dominios,dominio,clientes}/` — **434 docs de negócio**:

| owner atribuído | nº | layer |
|---|---|---|
| `reference` | 374 | `ia-os` |
| `governance` | 12 | `ia-os` |
| `audit` | 1 | `ia-os` |
| `module:Financeiro` | 47 | `product-erp` |

**410 de 434 (94%) do corpus de NEGÓCIO são classificados como PROCESSO** (`reference`/
`governance`/`audit`) com confiança **≥0,90**. Escopo honesto: isto é a *classificação*
(pré-adversário); o adversário barra os que têm backlink de ADR/session/handoff, mas essa
proteção é **acidental** (depende do backlink existir), não semântica. **Consequência:** rodar
a convergência com o classificador de hoje **automatiza a atrofia diagnosticada pela
[ADR 0334](../0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)** — puxa a
inteligência de negócio pra dentro da governança em escala.

## II.2 Gap classificador ↔ alvo (P1/P2 — cada um com como foi verificado)

| # | Achado | Verificação |
|---|---|---|
| P1 | **Sem owner `domain`/`client`** — negócio cai em `reference`/`governance` | leitura de `expectedPrefix` (adversário) + `classifyDocument` (classificador): prefixos param em reference/governance/research/audit/module |
| P1 | **`confidence` olha metadado CRU** — qualquer `type:`/`module:` (mesmo módulo inexistente) eleva a 0,97 | leitura: `classifier` linha 85 `meta.type \|\| meta.module ? 0.97 : …`; owner cai em `reference` porque `inferModule` não resolve |
| P1 | **Adversário não cruza `owner×layer×door×target`** — valida cada um isolado | leitura: `adversary` valida `LAYER_INVALID` (enum) e `CANONICAL_DOOR_MISSING` (existe) separadamente; nenhuma linha cruza a combinação |
| P1 | **Revisão humana é declarativa** — `reviewed_by` não é lido por script; só `confidence` manual libera o executor | leitura: `reviewed_by` ausente do código; executor só aceita `safe_to_apply` (APPROVE) |
| P2 | **Lifecycle só reconhece `arquivado`** — `historical`/`archived` viram `active` | leitura: `classifier` linha 87 `meta.lifecycle === 'arquivado' ? 'archived' : 'active'` |
| P2 | **`replaceExact` troca a string globalmente** (`split/join`) — pode tocar prosa | leitura: `executor` `replaceExact`; baixa probabilidade (o `from` carrega o path inteiro) mas real |

## II.3 Árvore-alvo (PROPOSTA — não canon até promoção)

As 3 camadas da 0334 no espaço. **Não** é "3 pastas gigantes" — é classificação arquitetural
das famílias já existentes, mais os owners de negócio que faltam:

- **(A) Produto ERP** → `memory/requisitos/<Modulo>/` (porta `BRIEFING.md`)
- **(B) Produto IA** → `memory/requisitos/Jana/` (porta `BRIEFING.md`)
- **Conhecimento de negócio** (serve A+B, é o corpus (a) da 0334) → `memory/dominios/<dominio>/`
  e `memory/clientes/<cliente>/` (porta do cliente = `PERFIL.md`)
- **(C) IA-OS/governança** → `memory/governance/` (porta ADR 0094), `memory/reference/`,
  `memory/research/`, `memory/audits/`
- **Append-only** → `memory/decisions/`, `sessions/`, `handoffs/` (intocáveis)
- **Ciclo de vida** → `memory/archive/{legacy,superseded,historical}` (decaimento — **não** é owner)

> ⚠️ **`memory/dominio/` (SINGULAR) NÃO entra em "Conhecimento de negócio" — é contrato de path.**
> Os 6 arquivos de `memory/dominio/` (singular) são **dicionários de enum G-4** ([ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)),
> fonte-única do gate REQUIRED `dominio-gate` lida por **path FIXO** (`domain-dict-guard.mjs:74`) — dono
> **distinto** do plural `memory/dominios/` (Migration Factory legacy, [ADR 0118](../0118-segregacao-dominios-externos-clientes-legacy.md)/[0119](../0119-migration-factory-capacidade-institucional.md)), não duplicata a consolidar.
> A convergência (passo 9) **exclui `memory/dominio/`**; a máquina já protege por path nas 2 camadas
> (classifier + adversário `protectedReason`, #4681). Detalhe + incidente: [proibicoes §5 2026-07-22](../../proibicoes.md).

## II.4 Owners canônicos de realocação (REGISTRO a criar — pré-requisito dos gates)

O passo que destrava todo o resto: um registro único (owner → prefixo → layer → porta) que o
classificador consulta e o adversário valida como **matriz**, não campo a campo.

**Decisão tomada (delegada por [W] 2026-07-22 "escolha as melhores opções"; merge = ratificação):**
layer nova **`business-knowledge`** para `domain`/`client` — é o corpus (a) da 0334, que serve
A **e** B; mapear pra `product-ai` mentiria (fiscal/comercial servem o ERP também) e pra `ia-os`
seria a atrofia automatizada. Portas: `domain` → `memory/dominios/_overview.md`; `client` →
`memory/clientes/<cliente>/PERFIL.md` (sem PERFIL o plano não aprova — porta antes do move).

## II.5 Ordem de correção (endossada — pré-requisitos primeiro)

1. Ratificar esta proposta (árvore + owners).
2. Criar o registro canônico de owners + combinações permitidas (`module`/`domain`/`client`/
   `governance`/`reference`/`research`+`comparison`/`audit`).
3. `archive` = lifecycle/política de armazenamento (0270), separado da classificação por dono.
4. Adversário valida a matriz `owner×layer×door×target`.
5. Classificador valida metadado contra o registro **antes** de elevar confiança (mata o "meta cru").
6. Aprovação humana **ligada ao `planDigest`** (assinatura + identidade + data), **não** um
   `reviewed_by:` auto-escrito (seria a família `last_validated`/`verificado_em` já rejeitada em
   [proibicoes §5](../../proibicoes.md)).
7. `move-with-tombstone` para reorganizar legado sem editar histórico append-only — **provando
   antes** que não acorda gate diff-aware (lápide 2026-07-12: tocar legado acorda anchor-lint/scorecard).
8. Testes: domínio, cliente, lifecycle, metadado inválido, matriz incoerente.
9. **Só então** convergência do corpus em lotes pequenos e coesos.

## II.5b Implementado (2026-07-22, mesma branch — recibo, não promessa)

Passos 2–9 da ordem acima **codados e provados** (o passo 1 é este merge). O passo **9**
(convergência dos 11 comparativos) exigiu antes destravar um limite pré-existente do relink,
não do tombstone: os docs se cruzam densamente usando o mesmo literal de caminho em dois contextos
(`[x](arq.md)` markdown-link → `./rel` **e** `` `arq.md` `` code-span → `root/path`). O replace
textual global não distinguia contexto → colisão (`CONFLICTING_REWRITE`, primeiro exposto como
falso APPROVE, depois barrado honestamente). O **relink contexto-consciente** (`searchReplaceFor`,
fonte única: executor aplica, classificador conta, adversário detecta — busca/substituição carrega
o delimitador do contexto) resolveu: markdown-link e code-span do mesmo literal recebem destinos
diferentes sem se pisar. `CONFLICTING_REWRITE` foi **estreitado** ao residual insanável (um
`literal-path` cru junto de estruturada). Com isso o lote `--tombstone` dos 11 comparativos passou
a **APROVAR** no adversário (0 erros) — aplicado no commit de convergência desta branch:

| Fix | Prova (vetor de selftest que morde) |
|---|---|
| Owners `domain`/`client` + layer `business-knowledge` + portas | `SOLTA: owner domain coerente` · `MORDE: negocio desviado pra processo` |
| Matriz `owner×layer×door×target` no adversário (`ownerRules`, fonte única — classificador importa) | `MORDE: matriz owner x layer x door incoerente` (o plano sintético do review que APROVAVA agora REJEITA) |
| Metadado validado antes do boost (`module: Financeirro` não dá mais 0.97) | `modulo-inexistente-nao-boost` |
| Lifecycle normalizado (`historical` não volta como `active`; desconhecido derruba confiança) | `lifecycle-historical-preservado` · `lifecycle-desconhecido-derruba-confianca` |
| Aprovação humana amarrada ao hash (`approvals[]` + `approvalDigest`, reviewer enum W/F/M/L/E; CLI `--digest`) | `SOLTA: baixa confianca COM aprovacao assinada` · `MORDE: hash que nao corresponde` |
| `count` por rewrite no plano; executor aborta+rollback se divergir (P2 replaceExact) | `contagem divergente aborta e reverte` |
| `already_canonical`: doc já no prefixo do owner não gera move achatado | `dominio-e-business-knowledge` |
| **`move-with-tombstone` (passo 7)**: legado com referrer não-relinkável (append-only **ou** sob gate diff-aware — `memory/requisitos/**`/charter, lápide 2026-07-12) migra deixando stub no path antigo; o não-relinkável resolve pelo stub, o livre é relinkado; injustificado sem não-relinkável | adversário `SOLTA: move-with-tombstone isenta referrer append-only e relinka o mutavel` · `SOLTA: tombstone justificado so por referrer sob gate diff-aware` · `MORDE: tombstone sem referrer append-only e injustificado` · `MORDE: tombstone NAO isenta referrer mutavel` · `MORDE: tombstone NAO autoriza editar append-only` · `MORDE: relink de referrer sob gate diff-aware e barrado (GATE_GUARDED_REFERRER)` · classificador `tombstone-particao-separa-nao-relinkavel` · executor `tombstone: stub no path antigo + alvo movido + ADR intacto + mutavel relinkado` |

Selftests: adversário **22/22** · classificador **9/9** · executor **10/10**. **Re-medição
pós-fix (mesmo script da §II.1): 434/434 docs de negócio → owner `domain`(431)/`client`(3),
layer `business-knowledge`; docs de negócio classificados como processo: 410 → 0.**

## II.6 Fronteira honesta

- Passos 4–6 **não nascem required nem auto-apply** — só depois de morderem
  ([ADR 0336](../0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)).
- **Owners de negócio antes da convergência** (passo 2 antes do 9): rodar a máquina sem eles
  automatiza a atrofia da 0334 (§II.1 — 94% do negócio vira processo).
- Isto **estende** esta proposta (mesma família "estrutura de `memory/`"); não abre paralelo
  ([proibicoes §5](../../proibicoes.md) anti-duplicação).
