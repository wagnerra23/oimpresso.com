---
slug: 0365-trio-de-tela-fica-colocado-reverte-eixo-0364
number: 365
title: "O trio de tela (charter + casos) FICA colocado ao lado do .tsx; a doc espelha o fonte (Opção B) — reverte o eixo de localização da 0364"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W, F]
decided_at: "2026-08-02"
accepted_at: "2026-08-02"
accepted_via: "Wagner no chat 2026-08-02: 'Flip'. [F] patrocinou a direção em 2026-08-01 ('eu quero como no fonte' · 'colocar na estrutura do fonte e ter a documentação correlacionada'), registrada na proposal 2026-08-01-reverter-0364-trio-colocado-opcao-b (PR #5156). [W] ratifica porque reverte ADR canon aceita e o merge deste PR é o ato formal (R10) — o agente não ratifica."
module: governance
quarter: 2026-Q3
tags: [governance, memoria, rag, charter, casos, casos-gate, trio-de-tela, colocation, forward-only]
supersedes: []
supersedes_partially:
  - 0364-trio-de-tela-mora-em-memory-emenda-0264
superseded_by: []
related:
  - 0364-trio-de-tela-mora-em-memory-emenda-0264
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0053-mcp-server-governanca-como-produto
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
review_triggers:
  - "[W]/[F] concluírem que a casa única em memory/ volta a valer mais que a proximidade — re-flip para a Opção A da 0364, que permanece ativa"
  - "A proximidade colocada provar, com SINAL MEDIDO (não impressão), que não contém o apodrecimento da doc de tela"
  - "O RAG in-place provar inviável na prática — hoje já é fato entregue, então este gatilho só reabre se a indexação regredir"
---

# ADR 0365 — o trio de tela fica colado ao fonte

## Contexto

A [ADR 0364](0364-trio-de-tela-mora-em-memory-emenda-0264.md), aceita em **2026-08-01**, decidiu mover
o trio (`<Tela>.charter.md` + `<Tela>.casos.md`) de `resources/js/Pages/` para
`memory/requisitos/<Modulo>/_telas/`. O motivo declarado era legítimo e mensurável: o indexador do RAG
(`IndexarMemoryGitParaDb`) só varre `memory/**`, então **o trio colado estava invisível para a busca da
IA** — e mover era o caminho conhecido para resolver isso.

**No mesmo dia, [F] cortou o eixo de localização**: *"eu quero como no fonte"*, *"colocar na estrutura
do fonte e ter a documentação correlacionada"*. Quem mantém o trio é quem edita o `.tsx`, e a
proximidade física é o que faz a doc ser vista no momento em que o código muda.

A reversão foi registrada como proposal em [PR #5156](https://github.com/wagnerra23/oimpresso.com/pull/5156),
acionando explicitamente o **gate-de-reversão cláusula (c)** que a própria 0364 previu (*"reabrir se
[W]/[F] concluírem que a proximidade física valia mais que a casa única"*). Esta ADR é o flip.

### O fato que torna a reversão barata: ela não é migração, é NÃO-mover

Medido em `origin/main` (2026-08-01, recibos reproduzíveis na proposal):

- O resolver canônico do trio — `scripts/casos-coverage-guard.mjs`, que sustenta o gate **required**
  `casos-gate` — resolve **100% por path-irmão colocado** (`dirname + basename + '.charter.md'`), e o
  frescor G-6 compara a data-git do `.tsx` irmão. **Não existe walk de `_telas/` para o trio React.**
- O **dual-resolver da 0364 nunca chegou ao `main`** — a branch de migração tem só a proposta, **zero
  charter movido**.
- Logo **nenhum gate required precisa de reescrita**. Reverter *antes* de qualquer arquivo se mexer é o
  momento mais barato que vai existir; o custo cresce a cada tela migrada.

### O único gap da Opção B já foi fechado

A 0364 estava certa sobre o problema: trio colado não entrava no RAG. O que ela errou foi supor que a
**única** saída era mover.

O **B3** — indexação in-place por glob aditivo — foi entregue e está **vivo em produção**
([PR #5167](https://github.com/wagnerra23/oimpresso.com/pull/5167), deploy 2026-08-02):

```
enum type ......... 'charter','casos' presentes em prod
indexados ......... 210 charters + 74 casos = 284
mcp_index_sync_gap  0 — "todos os 2303 docs canônicos do git estão no índice"
```

Ou seja: a cláusula (c) do gate-de-reversão desta ADR (*"se o RAG in-place provar inviável"*) **já foi
testada contra produção, não estimada**. O trio é buscável pela IA sem sair do lugar.

## Decisão

1. **A casa canônica do trio permanece COLOCADA** ao lado do `<Tela>.tsx`, em
   `resources/js/Pages/<Mod>/`. Não migra para `memory/requisitos/<Mod>/_telas/`.
2. **A doc espelha o fonte** por proximidade física + frontmatter de correlação (`component:` → o
   `.tsx` irmão, `parent_module:`, `related_us:`).
3. **A doc de MÓDULO** (SPEC / BRIEFING / SDD / RUNBOOK) continua em `memory/requisitos/<Mod>/`,
   espelhando `Modules/<Mod>` + `resources/js/Pages/<Mod>`.
4. **Reverte PARCIALMENTE a 0364** — `supersedes_partially`, nunca supersessão total. A distinção é
   deliberada: supersedes total rebaixaria a 0364 para fora do canon vivo e mataria junto o raciocínio
   *"o RAG não exige o move"*, que é justamente o que sustenta a Opção B. A 0364 permanece
   `lifecycle: ativo`, com o plano de move válido caso o eixo seja re-flipado.

### O que NÃO muda

Nenhum gate, nenhum resolver, nenhum arquivo de tela. O `casos-gate` (required), o `screen-coverage`,
o `anchor-content-check`, a família `charter-*`, o pipeline `prototipo-ui` e o subsistema PHP de
charter-health continuam operando sobre o trio colado — **como já operavam**. Esta ADR formaliza *de
jure* um estado que é *de facto*.

## Justificativa

**Por que colado, e não a casa única.** Colocation é o padrão de fato do ecossistema (o Storybook
documenta `Button.stories.tsx` ao lado de `Button.tsx` como prática padrão), e a pesquisa de docs-as-code
de 2026 converge para **híbrido**: colado para o que é por-arquivo, pasta central para o que é
transversal. É exatamente o desenho que fica: trio colado, doc de módulo em `memory/`.

**Por que agora.** O custo da reversão é zero **hoje** e cresce monotonicamente. Nenhum charter foi
movido; nenhum gate reescrito.

**O que a 0364 acertou e esta ADR preserva.** O diagnóstico do RAG estava correto — e ficou resolvido
por indexação aditiva, sem mover nada. Esse é o ganho que as duas ADRs compartilham.

## Consequências

**Positivas.** O trio segue onde quem o mantém trabalha. Nenhum gate required precisa de reescrita, e
nenhuma janela de migração precisa ser aberta num repositório que recebe dezenas de merges por dia. O
RAG cobre o trio in-place, com 284 docs indexados e gap zero.

**Negativas e trade-offs assumidos.** O trio fica **fora** de `memory/`, que é a casa mental de "onde
mora o conhecimento" — quem procurar doc de tela em `memory/requisitos/` não a encontra, e depende do
`component:`/`parent_module:` para correlacionar. E a doc de tela passa a viver sob um diretório de
build (`resources/js/`), o que é semanticamente torto pelo mesmo motivo que `/ads/admin/*` servido pelo
Governance é torto — feiura aceita em troca de custo zero.

**O que segue aberto, por desenho e não por esquecimento.** O reforço da correlação por frontmatter
(B2), a lente de descasamento doc↔fonte (B4) e a cobertura forward-only das telas com charter sem casos
(B7) são advisory/ratchet e não dependem desta ADR. A reversão não os antecipa nem os bloqueia.

## Relação com o canon

- **[0364](0364-trio-de-tela-mora-em-memory-emenda-0264.md)** — revertida **só no eixo de localização**,
  via `supersedes_partially`. Permanece `aceito`/`ativo`, **não é editada** (append-only Tier 0), e o
  plano de move continua válido se o eixo for re-flipado.
- **[0264](0264-governanca-executavel-trio-dominio-e2e.md)** — intacta. O trio, o G-2 (UC citado por
  teste) e o `casos-gate` required não mudam; muda apenas onde os arquivos moram, e eles não mudam.
- **[0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md)** — a proximidade é aposta em
  *derivado+enforçado*: o G-6 compara a data-git do `.tsx` irmão, então a doc colada tem sentinela de
  frescor por construção.

## Gate de reversão

Reabrir por **nova ADR append-only** se: **(a)** [W]/[F] concluírem que a casa única em `memory/` volta
a valer mais que a proximidade — re-flip para a Opção A, cujo plano segue válido na 0364; **(b)** a
proximidade colocada provar, **com sinal medido** e não impressão, que não contém o apodrecimento da
doc de tela; **(c)** o RAG in-place regredir a ponto de inviabilizar a busca — hoje é fato entregue e
verificado em produção, então este gatilho só vale para regressão futura.

## Referências

- [Proposal — reverter o eixo da 0364 (Opção B)](proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md) · [PR #5156](https://github.com/wagnerra23/oimpresso.com/pull/5156)
- [ADR 0364](0364-trio-de-tela-mora-em-memory-emenda-0264.md) — revertida parcialmente por esta
- [PR #5167](https://github.com/wagnerra23/oimpresso.com/pull/5167) — B3, RAG in-place (o gap da Opção B, fechado e em produção)
