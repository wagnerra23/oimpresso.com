---
proposal_id: sdd-from-source
status: accepted
created: 2026-07-24
decided_at: 2026-07-24
proposed_by: claude-code
decided_by: wagner
realized_by: 0351-sdd-from-source
parent_adr: 0291 (distiller-modulo-verdade — a peça de análise a religar/aposentar)
related_adrs: [0291, 0292, 0273, 0264, 0345, 0256, 0275, 0104]
related_proposals: [2026-07-23-fatos-derivaveis-anti-apodrecimento, 2026-07-23-referencia-id-estavel-doc-links]
type: mecanismo-de-processo
---

# `sdd-from-source` — as 3 camadas (analisa o fonte → documenta no padrão → confere)

- **Status:** ✅ `accepted` — ratificada por [W] em 2026-07-24, realizada na [ADR 0351](../0351-sdd-from-source.md). Venue confirmado = **agent in-session + `--dry-run` + PR** (cron `clone + auto-PR bot` = follow-up).
- **Origem ([W] 2026-07-24):** *"sinto falta de runbook/skill que faça igual as três camadas do melhor do ramo: análise do fluxo correto do fonte, documentar no padrão, e poder ser conferido se fez corretamente."* + a dor de escala: *"módulos gigantes onde os arquivos ficam grandes — a máquina que obriga preencher tem que se adaptar."*
- **Evidência que ancora:** o [scorecard adversarial SDD de 2026-07-23](../../sessions/2026-07-23-sdd-avaliacao-adversarial.md) (76/100) achou o **distiller kill-switched (0/76 portas)** e o **piloto Produto empacado** (charter 7/7, casos 3/7, anchor do SPEC 11,1% — 8 de 9 US `sem_campo`). Escrever SDD à mão não escala; foi por isso que o piloto travou.

## O problema (medido, não suposto)

O oimpresso já tem **2 das 3 camadas**, fragmentadas — falta a **1 (análise automática do fluxo)** e a **costura das três num fluxo repetível**:

| Camada | O que JÁ existe | O que falta |
|---|---|---|
| **1. Análise do fonte** | [`DistillerModuloVerdade.php`](../../../Modules/Jana/Services/Memoria/DistillerModuloVerdade.php) (kill-switched · [ADR 0291](../0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md)) · `module-surface` (SUPERFICIE = arquivos, não fluxo) · `catalog-graph` · agent `como-integrar` (analisa, não gera doc) | o **fluxo detalhado** Controller→Service→Model (o §5 do SDD) — nenhum extrai |
| **2. Documentar no padrão** | formato **SDD já existe** ([Produto](../../requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md)) · [`criar-tela.mjs`](../../../scripts/governance/criar-tela.mjs) · skill `charter-write` | preencher a partir da análise, não à mão |
| **3. Conferir** | `anchor-lint` ([ADR 0273](../0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)) · `casos-gate` ([ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)) · `charter-live-signal` · `fact-anchor` | — (a mais madura) |

## Decisão proposta

Criar um **agent** (padrão do projeto pra análise — como `capterra-senior`, `wagner-understand`), `sdd-from-source`, que **orquestra** as 3 camadas e **não cria máquina de análise nem tipo de doc novos**:

1. **ANÁLISE** — aponta pro `<Mod>/<Tela>` e lê **as 3 fontes na ordem-de-fonte canônica** ([how-trabalhar §ordem de fonte](../../how-trabalhar.md)), não só o presente. **Religa o distiller** como motor (ou o aposenta e nasce aqui — ver Fronteira).
2. **DOCUMENTAÇÃO** — gera/preenche o `SDD-tela-<x>.md` (§5 fluxo + §6 CU), o `casos.md` (via `criar-tela`) **e os docs de migração** `ANTI-REGRESSAO-<tela>-legacy.md` (paridade Delphi→React) + `PARIDADE-charter-vs-legado.md` (gaps do cutover); propõe as linhas `**Implementado em:**` pro SPEC.
3. **CONFERÊNCIA** — roda `casos-gate` + `anchor-lint` e devolve o veredito ✓/🧪/❌ **por US**. Humano confere e corrige — não escreve do zero.

### Camada 1 tem 3 fontes — Blade + Delphi, não só o atual (requisito [W] 2026-07-24 · FUNDAMENTAL)

Documentar só o React atual **reproduz a regressão**: a migração Blade→React já perdeu features em silêncio (o menu de Ações da lista de vendas sumiu no rewrite #1032). O agente TEM que **triangular 3 fontes** — a ordem que o projeto já define:

| Fonte | Onde | Pra quê |
|---|---|---|
| **React/Laravel atual** | `.tsx` + Controller + Service + Model | o fluxo vivo (o que existe hoje) |
| **Blade AdminLTE legada** | `resources/views/<x>/**` | migração MWART ([ADR 0104](../0104-processo-mwart-canonico-unico-caminho.md)) — o que a tela antiga fazia que o React precisa manter |
| **Delphi / Office Comercial** | `ANTI-REGRESSAO-*.md` (destilado do manual/tela WR Comercial) | **contrato de paridade** — o cliente usou por anos; feature não some sem Non-Goal explícito |

Sem as fontes 2 e 3, o agente documenta um React que **pode já ter perdido features** — e carimba a perda como se fosse o correto. Isso NÃO é opcional; é o que fecha a migração. **Já validado no Produto:** o `ANTI-REGRESSAO-cadastro-produto-legacy.md` é o Office Comercial 2026.1.1.38 (8 abas) destilado à mão — o `sdd-from-source` **derivaria/atualizaria** esse doc, em vez de alguém re-transcrever o manual a cada tela.

**Regra dura anti-duplicação:** o output é SEMPRE um arquivo que já tem dono e gate. Se em qualquer momento o agent for gerar um `ANALISE-*.md`/`FLUXO-*.md` novo, é bug — o fluxo mora no §5 do SDD, não num arquivo paralelo.

## Respostas às 4 perguntas de [W] (as travas do desenho)

### (a) Arquivos/estrutura gerados — ZERO tipos novos
Só preenche o que a [taxonomia ADR 0345](../0345-topicos-vivos-aprendizado-por-critica-revisada.md) já define: `SDD-tela-<x>.md`, `<Tela>.casos.md`, linhas `**Implementado em:**` no `SPEC.md`. Nenhum tipo novo nasce.

### (b) Linkagem de todas as conversas sobre o tema — usa o que existe
Via `id:` estável (o `doc-id-index.json` já tem ~2.100 ids) + `related_adrs`/`related_us` + o rastro append-only de `sessions/`+`handoffs/`. O agent **carimba** o `id` no doc gerado; toda ADR/session/handoff que cite o id fica rastreável. **Não inventa "mapa de conversas" à mão** — segue a proposal irmã [`referencia-id-estavel-doc-links`](2026-07-23-referencia-id-estavel-doc-links.md).

### (c) O que garante que não apodreça — camada 3 + separar derivado/curado
| Parte do SDD | Apodrece? | Defesa |
|---|---|---|
| §5 fluxo/arquitetura (aponta paths) | não* | `anchor-lint` confere path existente; código muda → gate morde |
| §6 casos de uso | não* | `casos-gate` exige teste que cite o UC |
| §0 benchmark, §2 personas (prosa curada) | **sim** | honesto — como o GUIA-DO-SISTEMA; o SDD **marca** com badge derivado/curado |

*enquanto o gate rodar. O agent é **re-rodável** (deriva do fonte — lei [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)). A honestidade: torna ~70% do SDD auto-conferível; os ~30% curados seguem foto que envelhece — defesa detalhada na proposal irmã [`fatos-derivaveis-anti-apodrecimento`](2026-07-23-fatos-derivaveis-anti-apodrecimento.md).

### (d) Escala (módulos gigantes) — o mecanismo É a resposta
A "máquina que obriga preencher" já se adapta por design (diff-aware · grace/grandfather · `_pendente_` de 1ª classe · granularidade por-tela/US/`topicos/`). Mas **preencher à mão escala com o tamanho** — foi o que travou o Produto em 11%. A **camada 1 (análise automática)** é o que torna sustentável: a máquina gera o rascunho lendo o código, o humano só **confere**. Sem ela, gigante = fardo manual.

## Exemplo — `Produto/Edit` (tem charter, medido SEM casos)

- **Camada 1** lê `Edit.tsx` + `ProductController@update` + Service → mapeia `Edit.tsx → PUT /products/{id} → @update → ProductUtil::updateProduct()`.
- **Camada 2** gera `Produto/Edit.casos.md`:
  ```
  ## UC-PROD-E1 · Editar produto sem perder variações existentes
  - Aceite: Dado produto com 3 variações · Quando salvo alterando o nome · Então as 3 persistem
  - Teste: tests/Feature/Produto/ProdutoEditContratoTest.php  (stub via criar-tela)
  - Status: ⬜ não verificado
  ```
  e adiciona no SPEC: `US-PROD-0NN · **Implementado em:** app/Utils/ProductUtil.php::updateProduct · verificado@<sha>`.
- **Camada 3** roda os gates: `casos-gate → 🧪 sem prova (stub)` · `anchor-lint → ✓ path existe (sai de sem_campo)`.

> O exemplo é ilustrativo do formato — o fluxo real do `@update` seria lido pelo agent, não afirmado aqui.

## Fronteira honesta (a decisão real de [W])

Não é "criar do zero" — é **religar o distiller** ([ADR 0291](../0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md), 40% da visão) dando-lhe **venue git-backed** (o motivo do kill-switch: escrevia na árvore deployada, perdido a cada redeploy), **OU** aposentá-lo formalmente e o `sdd-from-source` nascer no lugar. O scorecard de 2026-07-23 deixou isso em aberto como decisão sua. Esta proposal recomenda **religar** (reusa código real + a métrica `distiller_freshness` já existe no scorecard).

## Consequências

- **Positivo:** o SDD deixa de ser artesanato de sessão; módulo gigante vira viável; o piloto Produto destrava (casos das 4 telas + âncoras das 8 US via análise, não digitação).
- **Custo:** implementar o agent + religar o distiller com venue git-backed (peça média, Tier 0 — mexe no coração do SDD). Nenhuma tecnologia/dependência nova.
- **Não muda** os gates required nem cria tipo de doc; usa `criar-tela` + os gates que já mordem.

## Rollback
Proposal em `proposals/` — se [W] recusar, `status: rejected` (append-only). Se ratificada e o agent gerar SDD ruim, o veredito da camada 3 (gates) reprova antes do merge — o dano é contido pelo próprio mecanismo que ele orquestra.

## O que esta proposal NÃO decide
- O formato exato do `SDD-tela` (já é o do Produto — não reabrir).
- A automação por cron do distiller (só a religação sob-demanda pelo agent; cron é follow-up).
- Promoção de qualquer gate a required (segue o calendário [ADR 0275](../0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md)).
