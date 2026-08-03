---
page: /governance/test-lanes
component: resources/js/Pages/governance/TestLanes.tsx
owner: wagner
status: draft
last_validated: "2026-08-02"
parent_module: governance
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
tier: B
charter_version: 1
---

# Page Charter — governance/TestLanes (DRAFT · carimbado do PT-01)

> Nascida do Padrão de Tela **PT-01 Lista** via `criar-tela.mjs` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
> `status: draft` até [W] aprovar o screenshot.

## Mission

Mostrar, por módulo, **quais testes têm feedback no PR e quais só na nightly** — para
que teste de código Tier 0 não seja alterado sem que seu teste de regressão fale
antes do merge.

## Origem (o incidente que a tela existe pra tornar visível)

2026-08-02: `PiiRedactor.php` (LGPD) foi alterado em **+98 linhas** num PR que ligou
apenas o teste do caso novo. O `PiiRedactorTest` — 19 casos de regressão da mesma
classe — estava fora da lane, embora `PiiRedactor.php` **já constasse no `paths:`**
do trigger. A lane disparava e não rodava o teste principal. Descobrir isso exigiu
varrer 117 workflows à mão; nenhuma tela do ERP mostrava esse estado.

## Fonte do dado — DERIVADA, nunca digitada

`node scripts/governance/test-lane-coverage.mjs --json` (advisory, Node puro, bite-test
12/12). O Controller **não** consulta a API do GitHub em runtime: lê o JSON commitado,
como os demais `governance/*.json`. Sem isso a tela vira painel que envelhece calado —
exatamente o oposto do que ela existe pra combater.

## Vocabulário — a distinção que a tela NÃO pode borrar

| Termo na tela | Significa | NÃO significa |
|---|---|---|
| **No PR** | alguma lane de PR invoca o arquivo | — |
| **Só na nightly** | fora das lanes de PR; roda no `ct100-fullsuite` (`--roots tests,Modules`) | ❌ "não tem cobertura" |
| **Quarentena** | em `.github/*-quarantine.list` — decisão consciente, a lane imprime | ❌ órfão |

⚠️ **"Fora do PR" ≠ "nunca roda".** A nightly roda a árvore inteira (menos
`tests/Browser` e `governance-fixtures`). O que a tela mede é **latência de feedback**:
minutos no PR × horas na nightly. Rotular como "sem cobertura" seria alarmismo e
tornaria a tela mentirosa — o defeito que ela denuncia.

## Goals — Features (faz)

- KPIs no topo: total de testes · quantos só na nightly · % · lanes lidas · alvos extraídos
- Tabela por módulo (PT-01 `DataTable`): módulo · no PR · só nightly · quarentena · total
- Drill-down: expandir módulo lista os arquivos que não têm lane de PR
- Filtro por módulo e ordenação por "só nightly" (maior primeiro)
- **Honestidade de medição:** se `lanes_lidas === 0` ou `alvos_totais === 0`, a tela
  exibe **aviso de ausência de medição** — nunca "100% fora do PR". Parsing é textual;
  falha de parsing tem que parecer falha, não achado
- Carimbo de frescor do JSON (data de geração) visível — dado velho se declara velho
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

> ⚠️ Seção reservada a **[W]** (`charter-write` é proibida de inferir Non-Goal —
> anti-padrão inventado vira lei e a próxima sessão obedece). Abaixo, apenas os que
> decorrem de decisão **já registrada**; o resto fica para [W].

- ❌ Não chama a API do GitHub em runtime (o dado é o JSON commitado — ver Fonte)
- ❌ Não mede cobertura de **linha** (dono: `scripts/tests/coverage-compute.mjs`)
- ❌ Não mede se o teste **passou** (dono: `junit-summary.mjs` + `--check-assertions`)
- ❌ Não bloqueia merge — a tela é **relato**; enforcement é decisão [W] por flip
- TODO [W]: outros Non-Goals

## Automation Anti-hooks

> ⚠️ Seção reservada a **[W]** — cada item vira Pest GUARD no CI.

- TODO [W]

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)
- Tabela com ~26 linhas (1 por módulo) sem paginação
- Sidebar: 6º item do Governance, após "Module Grades"
- Permissão: `governance.dashboard.view` (mesma dos irmãos) — sem permission nova

## Refs

- Padrão de Tela: PT-01 Lista (DataTable + PageHeader + filtros)
- Constituição UI v2: UI-0013
- Casos: [TestLanes.casos.md](TestLanes.casos.md)
- Fonte do dado: `scripts/governance/test-lane-coverage.mjs`
- Classe de defeito: `memory/LICOES_CODE.md` LC-13 (verde por não-execução)
