---
date: "2026-07-03"
time: "16:46 BRT"
slug: oficinaauto-casos-contrato-show-edit-create
tldr: "Contrato casos.md das 3 telas de OS (Show/Edit/Create): 12 UCs 🧪, cada um citando um Pest real (G-2 via anotação nos testes). casos-gate verde (débito trio −16). Número segue 0% honesto (🧪≠✅) — subir pra ✅ = dente CT100. PR #3773."
prs: [3773]
decided_by: [W]
related_adrs: [0320-programa-ondas-regua-correcao, 0264-governanca-executavel-trio-dominio-e2e, 0230-metodo-governance-scorecard]
next_steps: ["DENTE Show (CT100, EM ANDAMENTO — [W] topou): ShowCasosTest.php com UC-OSH-01..04 no TÍTULO → run --log-junit no CT100 → npm run casos:results regenera manifesto → flip Show pra ✅ (sai do 0% de verdade)", "Depois: mesmo dente pra Edit/Create", "Board UI gaps quando priorizar"]
---

# Handoff — Contrato casos.md das telas de OS (Show/Edit/Create)

> Continuação da régua OficinaAuto. [W] escolheu "Contrato 🧪" e depois "eu topo" o dente CT100
> pra tirar a Show do 0% de verdade (em andamento — handoff próprio).

## Estado MCP no momento
- **cycles-active:** nenhum cycle ATIVO (programa-ondas off-cycle).
- **Sessões paralelas:** worktrees `casos-guard-status` + `fin-regua-cr-cp` (outras sessões) — não tocados.

## O que aconteceu
As 3 telas de OS estavam sem `.casos.md` (só o Board tinha) → mostravam "—". Estabeleci o **contrato**:
- **3 `.casos.md`** (Show/Edit/Create), **4 UCs 🧪 cada** (12 total) + backlog.
- Cada UC **cita um Pest real** (G-2) via anotação `// casos: UC-...` em **6 testes** (comentários inertes): ServiceOrderCrudTest, FsmTransitionTest, VehicleMultiTenantTest, ServiceOrderItemTest, ServiceOrderItemStockBaixaTest, OficinaFioUsavelAdr0265Test.
- **3 scorecards** `casos_coverage` atualizados (fonte→`.casos.md`, ucs 🧪, cob 0%).

## O teto honesto (por que 🧪 e não ✅)
Pra ✅ o G-7 exige `verdict:pass` no manifesto `casos-test-results.json`, gerado por `casos-results-collect.mjs` lendo **JUnit** de `test-results/*.xml` (extrai o UC-id do **TÍTULO** do testcase; o coletor aceita o reporter JUnit dos runners). Os testes destas telas não carregam o UC-id no título nem rodaram com reporter JUnit pra alimentar o manifesto → **🧪**, `cobertura_uc` **0% honesto** (paridade com Sells/Create golden). Ganho = contrato + rastreabilidade + fecha 3 trios (G-1), não o número.

## Verificação
- `casos-coverage-guard` → **"Sem violações novas (débito caiu −16)"**.
- CI: **`Casos-coverage · ratchet` PASS** + lanes PHP/Pest PASS.
- `screen-grade-report` → as 3 telas "0% · UC 4 (🧪4)" (antes "—"), 0 drift nas minhas.
- Mergeado via `safe-merge.sh` — 12 arquivos confirmados em `origin/main` (`ls-tree` MSYS-safe).

## O dente (próximo, [W] topou)
`Modules/OficinaAuto/Tests/Feature/ShowCasosTest.php` com 1 caso por UC-OSH, **UC-OSH-01..04 no TÍTULO** → rodar a suíte no CT100 com reporter JUnit apontando pra `test-results/oficina-show.xml` → `npm run casos:results` regenera manifesto → commit manifesto → flip Show pra ✅. Aí sai do 0% de verdade. Depois idem Edit/Create.

## Lições catalogadas
- **Manifesto aceita qualquer runner com reporter JUnit** (não só e2e) — o UC-id vem do **TÍTULO** do testcase, não de comentário. Comentário satisfaz G-2 mas NÃO alimenta o manifesto G-7 (✅).
- **🧪 é gate-safe sem rodar teste** (G-7 só cobra ✅).
- Tooling/classifier instável a sessão toda — várias chamadas falharam e foram reexecutadas; nada quebrou.

## Pointers
- PR: https://github.com/wagnerra23/oimpresso.com/pull/3773
- casos.md: `resources/js/Pages/OficinaAuto/ServiceOrders/{Show,Edit,Create}.casos.md`
- Manifesto: `scripts/casos-test-results.json` · collector: `scripts/casos-results-collect.mjs`
- Handoffs irmãos: `2026-07-03-1521-*` (régua+Board), `2026-07-03-1603-*` (safe-merge)
