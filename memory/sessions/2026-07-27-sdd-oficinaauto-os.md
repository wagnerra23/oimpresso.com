---
date: '2026-07-27'
topic: "Chip SDD OficinaAuto — SDD do zero + trio das 9 telas + lane nova (Onda 2, passo 5)"
authors: ['C']
id: sessions-2026-07-27-sdd-oficinaauto-os
modulo: OficinaAuto
related: memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md
outcomes:
  - "SDD criado do zero (§0–§10, 19 CU) — primeira corrida do ramo sem SDD pré-existente"
  - "UC declarados 12 → 42, todos com teste que os cita (0 órfão)"
  - "Lane de PR criada (advisory) — os 44 testes do módulo não rodavam em PR nenhum"
  - "anchor-lint: US implementada sem teste que a cobre 20 → 5"
us:
  - US-OFICINA-001
  - US-OFICINA-002
  - US-OFICINA-003
  - US-OFICINA-004
  - US-OFICINA-014
  - US-OFICINA-017
  - US-OFICINA-035
  - US-OFICINA-038
  - US-OFICINA-039
  - US-OFICINA-040
  - US-OFICINA-041
related_adrs:
  - '0351-sdd-from-source'
  - '0352-errata-0351-venue-distiller-citacao-taxonomia'
  - '0264-governanca-executavel-trio-dominio-e2e'
  - '0265-oficina-reparo-erradica-locacao'
  - '0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes'
---

# Chip SDD · OficinaAuto — Onda 2 do passo 5

> **Primeira corrida do ramo "SDD NÃO existe → cria §0–§10"** — o plano do passo 5 registra que
> os 3 runs anteriores do agent rodaram com o SDD do Produto **já pronto**, e que esse ramo tinha
> **zero corridas**. Esta é a primeira.
>
> ⚠️ **Módulo LIVE em produção** (piloto Martinho, ~91 veículos reais). **Zero mudança de
> comportamento**: só documento, contrato, nome de teste e uma lane nova.

## 1. Placar — antes → depois (porta viva `requisitos-status.mjs OficinaAuto`)

| Elo | Antes | Depois |
|---|---:|---:|
| US no SPEC | 48 | 48 |
| **CU no SDD** | **0** | **19** |
| Telas (.tsx) | 9 | 9 |
| **Telas com `casos.md`** | **4** | **10** (9 telas + 1 fluxo sem tela) |
| **UC declarados** | **12** | **42** |
| **UC com teste que os cita** | 12 | **42** (0 órfão) |
| **Lacunas na fila** | **18** | **6** |
| `anchor-lint` · US implementada SEM teste que a cobre | 20 | **5** |
| Lane de PR | **nenhuma** | `oficinaauto-pest.yml` (advisory) |

`casos:check` fechou **−13 de débito** vs baseline, exit 0. `anchor-lint --check` exit 0
(coverage 100%, 0 dead, 0 zombie, 0 teste-fantasma, **0 fora de lane**). `dominio:check` estável.
`deadlink-gate` OK. Nenhum veredito verde/vermelho é afirmado aqui — **status vem da lane** (G-7).

## 2. As 6 lacunas que sobraram — cada uma com causa nomeada

| Lacuna | Por que não fechei |
|---|---|
| `Board.casos.md` "sem UC" | **falso-negativo da porta**, não ausência de contrato — ver §4 achado A-1 |
| `CU-OFI-14` sem UC | mesma causa do Board |
| `CU-OFI-03` / `US-OFICINA-012` (consulta de placa) | a prova **existe** (`tests/Feature/Modules/OficinaAuto/ConsultaPlacaEndpointTest.php` + `PlacaLookupServiceTest.php`) mas **fora da área de escrita do chip**; declarar o UC sem poder citá-lo no teste o faria nascer órfão e **bloquearia** o `casos-gate` required |
| `CU-OFI-17` / `US-OFICINA-042` (painel fiscal) | **nenhum teste no repo cobre** o card (varrido: 0 arquivos). É decisão de produto: contratar ou virar Non-Goal |

## 3. Fontes — a triangulação real (Camada 1)

| # | Fonte | Estado |
|---|---|---|
| 1 canon | ✅ SPEC (48 US) · ROADMAP · BRIEFING · CAPTERRA · 9 charters · 6 RUNBOOKs |
| 2 código | ✅ 9 Controllers · 14 Services · 4 Entities · 1 Observer · 44 testes |
| 3 Blade | 🟡 **quase inexistente** — 1 arquivo (`resources/views/oficina_auto/print/service_order.blade.php`). O módulo nasceu React/Inertia; **não houve migração Blade→React** aqui, logo não há paridade a defender além da impressão |
| 4 Delphi | ❌ **NÃO EXISTE** — `find memory -iname "*ANTI-REGRESSAO*"` = 2 arquivos, ambos do Produto. **Gap declarado no §0.2 do SDD, não preenchido por invenção** |

## 4. Achados (varredura CONTADA)

**A-1 · Dois leitores do mesmo corpus discordam sobre o que é um UC.**
`Board.casos.md` declara `UC-01`…`UC-09` (sem prefixo de tela). O `casos-coverage-guard`
(**required**) aceita e os 9 estão provados por Playwright; o `requisitos-status.mjs` exige um
segmento no meio do id e por isso imprime *"não declara nenhum UC"*. Ids curtos também colidem
entre módulos (já causaram um bug conhecido). **Não consertei**: renomear exige editar
`e2e/oficina-uc06-gate-etapa.spec.ts`, fora da área; renomear só o `casos.md` deixaria os 9
órfãos e **quebraria o gate required**. Registrado como **D-5** no SDD §8.2.

**A-2 · O `casos.md` contradizia o código num eixo `[V0]` (estoque).**
`UC-OED-03` afirmava *"Quando o item peça **é adicionado** → o estoque é baixado"*. O código
(`ServiceOrderItemService::baixarEstoqueConclusao`) baixa **na conclusão da OS**, e o teste que o
UC citava prova exatamente isso (*"…ao concluir OS"*). O comentário-cabeçalho do próprio teste
repetia o erro. **Corrigi os dois artefatos, não o código** (precedência: teste verde > casos).

**A-3 · "Sem lane" era meia-verdade — e a metade errada importa.**
Os 44 testes **estão** em `phpunit.xml` (`<directory>./Modules/OficinaAuto/Tests/Feature</directory>`),
logo rodam na **suíte completa noturna**. O que não existia era lane de **PR**. Registrei as três
portas separadas no SDD §8.1 (`phpunit.xml` / workflow / `required-checks-baseline.json`) porque
confundi-las é a classe LC-08.

**A-4 · `@covers-us` sozinho não conta.** Medido em `anchor-lint::collectCoversIndex`: o índice
só lê arquivos **citados em `Testado em:`** no SPEC. Adicionar 27 marcadores nos testes moveu
o contador de 20→20 (nada); só depois de aplicar 15 linhas `**Testado em:**` o contador caiu pra
**5**. As duas metades são obrigatórias — meia mecanização parece feita e não é.

**A-5 · Drift factual em charter (reportado, não consertado).**
`AprovacaoPublica.charter.md` afirma em prosa que o schema da OS preserva o tipo de ordem
erradicado pela [ADR 0265](../decisions/0265-oficina-reparo-erradica-locacao.md). É factualmente
verdade sobre o schema (o dicionário confirma o resíduo) mas contradiz a doutrina. **Não editei**:
é prosa de contexto num charter, e `.md` **não** é varrido pelo `dominio:check` (extensões medidas:
`.php/.ts/.tsx/.js/.jsx`), então não há risco de gate — é decisão de [W].

**A-6 · Um id proposto e nunca ratificado (o quarto da série `UC-OED-`) não foi reusado.**
Ao documentar isso, soletrar o id no corpo do `casos.md` o transformaria num UC **declarado e
órfão** — porque a porta extrai UC do corpo inteiro. Reescrevi a nota sem soletrá-lo.

## 5. Orçamento da corrida

| Item | Medida |
|---|---|
| Tool calls | ~52 (orçamento: ~95) |
| Arquivos lidos/varridos | 9 controllers+services por `grep` dirigido · 4 `casos.md` · 3 charters · SPEC (mapa por `grep`, não leitura integral de 1.496 linhas) · 4 scripts de gate (pra **medir** o que eles cobram, não supor) |
| Varreduras contadas | ids CU no módulo (**0** existentes) · ids UC (**12**) · testes citando placa (**2**, fora da área) · testes cobrindo fiscal (**0**) · `ANTI-REGRESSAO` no repo (**2**, ambos do Produto) · Blade da oficina (**1** arquivo) |
| Artefatos escritos | 1 SDD (§0–§10, 19 CU) · 5 `casos.md` novos · 1 `casos.md` de fluxo sem tela · 4 `casos.md` atualizados · 1 lane · 15 `Testado em:` no SPEC · 27 `@covers-us` · 30 nomes de teste com UC-id · 1 `_STATUS-GENERATED` · este log |
| UC gerados | **30 novos ancorados** (0 órfãos) + **10 `[BACKLOG]`** sem id |
| Gargalo | **descobrir o que cada gate realmente cobra** (~14 calls). Os dois falso-caminhos custaram: o `@covers-us` sem `Testado em:` (A-4) e o id soletrado que viraria órfão (A-6). Ambos só apareceram **rodando** a porta, não lendo. |
| Reuso entre telas irmãs (Fase 1.4) | **alto** — a Camada 1 foi paga **1×** pro módulo: o mapa Controller→Service→Entity, o dicionário de domínio e o inventário de testes serviram às 9 telas. O que **não** se reusou: a resolução de fonte por tela e a checagem de qual teste prova qual UC (feita 30×, uma por UC) |

## 6. Lições de mecanismo (o que na definição do agent atrapalhou)

1. **"Aplique as âncoras" não diz que são DUAS metades.** A definição manda propor
   `Implementado em:`; o que estava vermelho aqui era o eixo `Testado em:` + `@covers-us`,
   e **só o par funciona** (A-4). Vale explicitar: *medir com `--check` antes e depois, porque
   marcador sem declaração no SPEC é invisível.*
2. **A área do chip pode tornar uma lacuna infechável — e isso precisa de vocabulário.**
   `CU-OFI-03` tem prova, mas ela mora em `tests/Feature/Modules/<Mod>/`, fora da área declarada.
   A definição do agent lista `tests/Feature/<Mod>/*Test.php` como área; o chip listou
   `Modules/<Mod>/Tests/**`. Módulo nWidart tem **as duas casas** — o contrato do chip deveria
   dizer qual vale, ou incluir as duas.
3. **"Sem lane" precisa ser dito com a porta junto** (A-3): o próprio agent já ensina isso na
   §1.2, mas o enunciado do chip repetiu "SEM LANE" sem a ressalva do `phpunit.xml`. Segui a
   §1.2 e reportei as três portas separadas.
4. **Falta um passo "não soletre id reservado"** (A-6): a regra de alocação de id manda declarar
   qual id foi pulado — mas declarar soletrando cria um UC órfão. A forma segura é descrever sem
   escrever o id.
5. **O guard de schema de `memory/sessions/` bloqueou 2×** (faltavam `date`/`topic`; depois
   `authors` fora do enum). Barrou cedo e barato — é o comportamento correto; anotado só como
   custo real da corrida (2 calls).

## 7. O que precisa do [W]

1. **D-5** — renomear os UC do Board (`UC-01`→`UC-OBD-01`) num PR que toque `e2e/` junto.
2. **CU-OFI-17 / US-OFICINA-042** — o painel fiscal vira contrato ou vira Non-Goal? É produto.
3. **CU-OFI-03 / US-OFICINA-012** — anexar o UC da consulta de placa aos testes que já existem
   em `tests/Feature/Modules/OficinaAuto/`.
4. **A-5** — o charter da aprovação pública fala do vocabulário erradicado; corrigir ou manter?
5. **D-1** — `ServiceOrder` não usa a trait de guarda de FSM (o portão é só o Controller).
6. **D-4** — ratificar (ou reverter) o fail-open do gate de etapa sem regra cadastrada.
7. **Merge do PR** (R10) e, se a lane vier verde, decidir promoção — que é flip [W] com mordida
   provada ([ADR 0336](../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)),
   não deste PR.

## 8. Arquivos globais NÃO tocados (regra de isolamento do passo 5)

`scripts/**` · `governance/*.json` · `governance-gate-umbrella.yml` · `.github/ci-sqlite-pest.list` ·
`memory/dominio/**` · `proibicoes.md` · `LICOES_CODE.md` · `08-handoff.md` — nenhum foi escrito.
O `casos-coverage-baseline.json` **não** foi regravado (o débito caiu −13; a consolidação é do parent).
