---
page: /governance/operacao
component: resources/js/Pages/governance/Operacao.tsx
owner: wagner
status: draft
last_validated: "2026-07-31"
parent_module: governance
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
related_adrs: [94, 110, 143, 155, 256, 264, 275, 314, 336]
tier: A
charter_version: 1
---

# Page Charter — governance/Operacao (DRAFT · carimbado do PT-01)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança de padrão,
> NÃO bespoke). Golden: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
> Irmã de [`ModuleGrades/Index`](./ModuleGrades/Index.charter.md) no mesmo módulo — o `/governance`
> segue dono do tema; esta é o detalhe por **processo**, como a ModuleGrades é por **módulo**.

---

## Por que existe

[W] 2026-07-31, textual: *"eu não consigo garantir o funcionamento integral do sistema como um
todo e não enxergo como cobrar de cada parte do sistema a sua responsabilidade"*.

O sistema tem **117 máquinas** registradas e **34 bloqueando o merge** — a defesa mecânica é
forte. Falta a camada humana: nenhuma máquina declara dono, **4 das 11 etapas** do fluxo não têm
quem responda, e as fontes que sabem disso vivem em quatro arquivos que só um agente lê. Todo
alarme sobe direto pro [W] porque não existe degrau intermediário. Esta tela é esse degrau.

### Posição na pilha de governança

[W] 2026-07-31 introduziu o modelo em camadas (Estratégico / Tático / Operacional / Automático).
Esta tela é **a camada Operacional** — cadência diária, pergunta *"o que está quebrado agora?"*.
O nome `Operacao` é deliberado: deixa `Tatico` e `Estrategico` livres pra nascerem como telas
irmãs sem renomear nada.

**Tradução da premissa, não cópia:** o modelo original (Amazon/Toyota/Google) separa camadas
porque cada uma tem **gente diferente** — o nível operacional é *staffed*. Aqui são 5 pessoas e o
Tier 0 é uma só, então as camadas separam **momentos da mesma pessoa**, não públicos. E a inversão
que importa: a camada Automático — a mais fraca naquelas empresas — é a mais forte aqui (117
máquinas). O sistema está super-indexado no automático e sub-indexado no tático.

---

## Mission

Responder **cinco perguntas** sobre a operação inteira, numa tela só — pra que administrar dezenas
de módulos não dependa de conhecer o detalhe técnico de cada um. Formulação do [W], adotada
literalmente:

| Pergunta | Resposta |
|---|---|
| **O que está piorando?** | Ranking dos processos em queda. |
| **Quem responde?** | Dono do processo e das peças afetadas. |
| **Por que piorou?** | Gates, bugs, métricas, testes, dependências e eventos recentes. |
| **O que precisa ser feito?** | Plano de ação com prioridade, prazo e responsável. |
| **Está melhorando?** | Tendência, histórico e evolução da nota. |

---

## Goals — Features (faz)

Uma seção por pergunta. Nada entra que não sirva a uma delas.

### 1 · O que está piorando — ranking

- Lista das **etapas do fluxo** (pedido → contrato de tela → design → código → teste → segredo/PII
  → conhecimento → meta-gates → merge → deploy/smoke → registro), ordenada por **piora**.
- **Coluna `parado há`** — idade em dias do item mais velho não-resolvido. Ordenação default:
  gargalo é o que está parado há mais tempo, não o que tem mais itens.
- **Estado derivado**, nunca digitado: `defendida` · `avisando` · `vencida` (passou do
  `promote_by`) · `sem defesa` (métrica nunca medida) · `sem dono`.

### 2 · Quem responde

- Coluna **Dono** por etapa, herdada pelas peças (máquina, módulo, tela).
- **Bus factor** por peça — nº de autores distintos em 180d, do git. Peça com autor único é
  sinalizada. Medido em 2026-07-31: **18 de 34 módulos com 1 autor**; 96,7% dos commits do repo
  numa identidade só.
- `sem dono` em rose. Filtro por dono + toggle **"só o que exige decisão [W]"**.

### 3 · Por que piorou — causa

- Expansão mostra as **peças** que degradaram: gate que ficou vermelho, métrica que caiu, teste em
  quarentena, promoção vencida, dependência que sumiu — cada uma com **evento e data** (run, PR, commit).
- **Não reimplementa** o detalhe: linka pra `/governance/module-grades/{nome}`, `/governance/drift`,
  `/governance/audit` e pro run do Actions.

### 4 · O que precisa ser feito — plano

- Ação pendente em **imperativo**, com prioridade, prazo e responsável — ex.:
  `ds-mirror-drift · advisory vencido há 8d · [W] decide: promover ou renovar prazo`.
- Ação **derivada do estado**: vencido → decidir promoção; sem dono → atribuir; métrica nunca
  medida → ligar a fonte; required vermelho → corrigir.
- **Não executa.** Diz o que fazer e pra quem; quem faz é gente, no lugar canônico.

### 5 · Está melhorando — tendência

- **Sparkline por etapa** e seta de direção, das séries que já existem em git
  (`sdd-scorecard.json` versionado por PR, `module-grades-baseline.json`, runs do Actions).
- Sinal de **estagnação**: item sem mudança de estado há N dias aparece como parado, ainda que
  verde. Verde antigo ≠ verde saudável.
- **Projeção só por aritmética verificável** — "no ritmo atual, cruza o piso em N dias". Nunca
  probabilidade calibrada: ver Non-Goals.

### Transversal

- Multi-tenant Tier 0 (default biz=1) · degradação graciosa com a razão · PT-BR em tudo.

---

## Fontes de dados — todas derivadas, nenhuma digitada

Esta tela **não tem cadastro próprio de estado**. Se a fonte apodrecer, ela mostra a fonte podre —
não uma cópia bonita dela.

| Coluna | Fonte única |
|---|---|
| Máquinas por etapa | [`scripts/governance/gates-registry.json`](../../../../scripts/governance/gates-registry.json) |
| O que bloqueia merge | [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json) |
| Métricas do processo | [`governance/sdd-scorecard-baseline.json`](../../../../governance/sdd-scorecard-baseline.json) |
| Nota por módulo | [`governance/module-grades-baseline.json`](../../../../governance/module-grades-baseline.json) |
| Bus factor | `git log --since=180.days --format=%an -- Modules/<X>` (autores distintos) |
| Dono por etapa | **[`TEAM.md` §3](../../../../TEAM.md)** — não existe por processo; ver Dependência |
| Vermelho vivo | GitHub Actions API (último run por workflow) |
| Item sem dono | tool MCP `triage` / cron `mcp:tasks:unassigned` |

**Regra dura:** o número vem com a data da medição ao lado. Incomodou? Re-roda a fonte — nunca
edita o número ([proibicoes.md §oráculo errado, 2026-07-17](../../../../memory/proibicoes.md)).

---

## Dependência bloqueante — decisão [W]

A coluna **Dono** não tem fonte. O `gates-registry.json` guarda `nome`, `classe`, `terminal`,
`anchor`, `promote_by` — **não guarda quem responde**. E o `TEAM.md` §3 é matriz por *tipo de
task*: só 5 das 24 linhas nomeiam módulo.

Opções pra [W]:

- **(a)** Segunda tabela `processo → dono` no `TEAM.md` §3 — 11 linhas, estende o dono do tema, e
  o registry já mapeia máquina→etapa, então o dono propaga de graça. **Recomendada.**
- **(b)** Campo `dono` no `gates-registry.json` — 117 linhas, granular demais.
- **(c)** Só etapa, sem pessoa — a tela mostra estado e o [W] segue destinatário único.

Sem essa decisão a tela nasce com a coluna principal vazia.

---

## Non-Goals — Features (NÃO faz)

> ⛔ **Preenchimento é do [W].** Cada item vira Pest GUARD; a skill `charter-write` é proibida de
> inferir Non-Goal. Abaixo são **propostas** — sujeitas a confirmar, trocar ou cortar no review.

- _proposta_ ❌ Não dá nota a pessoa. Mostra *item parado + responsável*, nunca score por gente.
  Razão medida: dos últimos 123 pontos de nota de módulo, **85 (69%) não foram trabalho** — foi o
  avaliador enxergando qualidade que já existia
  ([baseline v3.6.0](../../../../governance/module-grades-baseline.json)). Nota de artefato como
  avaliação de pessoa premia e pune ruído.
- _proposta_ ❌ **Não prevê com probabilidade.** Projeção só como aritmética verificável ("N dias
  no ritmo atual"). Um "82% em 12 dias" sem calibração é número inventado com cara de precisão —
  a classe que este projeto já matou por escrito.
- _proposta_ ❌ Não agrega tudo num índice único de saúde. Somar coisas incomensuráveis produz
  número que aponta pro lado errado ([lápide C9](../../../../memory/proibicoes.md)). Agregado só
  como **navegação** (clica e desce), nunca como meta.
- _proposta_ ❌ Não edita nem promove gate pela UI. Promover advisory → required é ato [W] na
  branch protection ([ADR 0275 §5](../../../../memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) ·
  [0336](../../../../memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)).
- _proposta_ ❌ Não guarda estado próprio. Zero tabela nova — estado digitado apodrece
  ([ADR 0256](../../../../memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)).
- _proposta_ ❌ Não duplica detalhe existente. Nota de módulo é da `ModuleGrades`; drift é do
  `/governance/drift`; log é do `/governance/audit`. Esta **linka**, não recalcula.
- _proposta_ ❌ Não é dashboard executivo. KPIs de topo seguem no `/governance`; esta é a lista operável.

---

## Automation Anti-hooks

> ⛔ **Preenchimento é do [W].** Propostas abaixo.

- _proposta_ ❌ Nenhuma automação altera `promote_by`, baseline ou branch protection a partir
  desta tela. Ela lê e mostra; escrita em fonte de gate é PR com diff visível.
- _proposta_ ❌ Nenhum alerta automático pra pessoa (e-mail/WhatsApp) na v1. Alarme que ninguém
  pediu vira ruído que se aprende a ignorar.

---

## UX Targets

- Cabe em **1280px** sem scroll horizontal (monitor da ROTA LIVRE).
- p95 first-paint < 800ms; payload caro (runs do Actions) via `Inertia::defer` + skeleton
  ([RUNBOOK](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)).
- **Teste dos 10 segundos:** ao abrir, sem clicar, dá pra dizer em voz alta o que está parado e
  quem tem que agir. Se não der, a tela falhou — mesmo com tudo verde.
- Ordenação default = `parado há` decrescente. O topo é sempre o gargalo.
- Cores Cockpit V2: rose=parado/vencido · amber=avisando · emerald=defendida · slate=sem dono
  (nunca verde — ausência de dono não é sucesso).

---

## UX Anti-patterns

- ❌ Número sem data de medição ao lado.
- ❌ Etapa sem dono em verde ou cinza neutro — tem que ler como pendência.
- ❌ Percentual único de "saúde geral" como meta.
- ❌ Probabilidade de falha sem calibração declarada.
- ❌ `sessionStorage` · ❌ cor crua `bg-red-100` (canon = rose/emerald semântico).

---

## Tests anti-regressão

- `e2e/governance-operacao.spec.ts` — stub carimbado citando o UC.
- Contrato de payload: nenhuma prop de estado é editável (prova o Non-Goal "não guarda estado").
- Multi-tenant: `business_id` scopado, cross-tenant biz=1 × biz=99.
- Ver [`Operacao.casos.md`](./Operacao.casos.md) — os UC são o contrato.

---

## Refs

- Padrão de Tela: [PT-01 Lista](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
- Constituição UI v2: [UI-0013](../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- Gêmea (camada IA-OS): [`Jana/Operacao`](../Jana/Operacao.charter.md)
- Irmã no módulo: [`ModuleGrades/Index`](./ModuleGrades/Index.charter.md) · dono do topo: [`Dashboard`](./Dashboard.charter.md)
- [ADR 0264 — trio de tela](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md)
- [ADR 0143 — FSM pipeline](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — a jornada, futura camada irmã
