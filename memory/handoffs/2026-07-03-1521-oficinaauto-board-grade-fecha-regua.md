---
date: "2026-07-03"
time: "15:21 BRT"
slug: oficinaauto-board-grade-fecha-regua
tldr: "Adendo do handoff da régua OficinaAuto: screen-grade do Board (86 Leader, casos 89%) fecha a 4ª foto. Régua completa das 4 telas do canary em main. 2 PRs mergeados na sessão (#3763 régua + #3766 Board)."
prs: [3766, 3763]
decided_by: [W]
related_adrs: [0320-programa-ondas-regua-correcao, 0230-metodo-governance-scorecard, 0264-governanca-executavel-trio-dominio-e2e, 0171-oficinaauto-ativacao-piloto-martinho-faseada]
next_steps: ["UI do Board quando priorizar: bg-white cru → bg-card · densidade 6 KPIs no 1280px · confirmar KeyboardSensor dnd-kit (gaps já no scorecard)", "PR de contrato .casos.md pra Show/Edit/Create citando o Pest já existente (G-2: UC+teste no mesmo PR) tira 0%→>0%", "Aplicar a mesma régua-por-tela a outro módulo do canary quando [W] pedir"]
---

# Handoff — OficinaAuto: régua por tela completa (4 telas fotografadas)

> **Self-contained (cobre a sessão inteira).** O handoff `2026-07-03-1501-oficinaauto-regua-por-tela.md`
> foi escrito e commitado (`a1782b7d7c`) mas **PERDIDO por desync do GitHub** — o squash-merge do
> #3763 usou o `headRefOid` stale (só o 1º commit, os scorecards), então o 2º commit (handoff) nunca
> landou em main. **Mesmo padrão do incidente #3732** (catalogado no handoff da onda Cliente, 17:30).
> Por isso este registro consolida TUDO (régua #3763 + Board #3766), sem depender do arquivo perdido.

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ATIVO (programa-ondas é off-cycle transversal).
- **my-work (@wagner):** 30 tasks — nenhuma deste chip (programa-ondas, `parent_plan=programa-ondas`).
- **Sessões paralelas ativas hoje:** worktrees `fin-regua-cr-cp` (Financeiro régua CR/CP) + `handoff-dente-oficina` (dente cálculo Oficina) — **NÃO tocados** (áreas isoladas).

## O que aconteceu (2 PRs, 1 sessão)
1. **#3763 (régua)** — plugou `casos_coverage`+`d1_calculo` em Show/Edit/Create (as que já tinham nota) + seção da régua na Fase 3 do ROADMAP. Aditivo, canary intocado.
2. **#3766 (Board)** — [W] pediu "pode fazer quero ver": rodei o método `screen-grade` (16-dim LLM-as-judge sobre o código real, ADR 0230) no `Board.tsx`. Novo scorecard `oficinaauto-serviceorders-board.yaml` = **seed do ratchet** (tela pós-baseline 2026-05-30). Atualizei a seção do ROADMAP (Board deixou de ser "não fotografado").

**Foto completa do módulo (em main):**

| Tela | UX | casos_coverage | d1 | leitura |
|---|---|---|---|---|
| Board | 86 Leader | 89% (UC 9 · ✅8 🧪1) | 🟡 | **COERENTE** (premium E defendido) |
| Show | 76 Advanced | 0% (— contrato) | 🔴 | contradição |
| Edit | 73 Advanced | 0% | 🔴 | contradição |
| Create | 66 Developing | 0% | n/a | contradição |

A régua prova o ponto do programa **nos dois sentidos**: onde há indefesa (Show/Edit premium + valor 🔴) e onde há coerência (Board Leader + 89%).

## Por que Board = 86 (do código, não à mão)
Zero cor crua (100% tokens DS + primary roxo — fecha o gap que segurava o antigo `ProducaoOficina/Index` em 80) · a11y (aria/atalhos/2ª-porta por botão pro FSM) · 4 views · no-mock · useMemo/useCallback. Gaps residuais no scorecard: `bg-white` cru em superfícies, densidade 6 KPIs+abas no 1280px, sensor de teclado dnd-kit a confirmar. `d1 🟡`: KPI "Valor em curso" exibe agregado que herda `final_total=0` (US-OFICINA-027).

## Artefatos gerados
- `memory/governance/scorecards/screens/oficinaauto-serviceorders-{show,edit,create}.yaml` (#3763, +64)
- `memory/governance/scorecards/screens/oficinaauto-serviceorders-board.yaml` (#3766, novo +69)
- `memory/requisitos/OficinaAuto/ROADMAP.md` — seção Régua na Fase 3 (#3763 criou, #3766 atualizou)
- `memory/handoffs/2026-07-03-1501-...md` (handoff #1) + este adendo

## Persistência
- **git:** ambos PRs MERGED em main (#3763 `42dcb79aea`, #3766 `470ee27dfb`); este handoff via PR próprio.
- **MCP:** webhook GitHub→MCP propaga pós-merge (~2min).
- **BRIEFING:** não atualizado (foto de governança, não muda capacidade do módulo).

## Verificação
- `screen-grade-report --all` → 4 telas OficinaAuto, **0 drift** (Board 89% gravado==vivo)
- `screen-grades-ratchet` → ✨1 nova (Board) / 0 regrediram
- `casos-coverage-guard` → sem violação nova
- Módulo Grades Gate CI (#3763) ✅ OficinaAuto 79→80

## Lições catalogadas
- **Tela pós-baseline nasce com nota via `screen-grade` (LLM-as-judge sobre o código), não à mão** — e vira o seed do ratchet (`baseline_anterior = nota`). Foi assim que o Board entrou legítimo sem violar o "NÃO editar à mão".
- **A régua tem 2 mecanismos distintos:** o scorecard YAML é a foto durável (guardo `casos_coverage`+`d1`); o `screen-grade-report` deriva o comportamento **ao vivo do `.casos.md`** e checa drift contra o gravado. Manter o gravado == vivo (Board 89%) = 0 drift.
- **Continuação de sessão = handoff-adendo append-only** apontando pro handoff-mãe (nunca editar o anterior).
- **⚠️ Desync GitHub headRefOid engole commits:** o handoff #1 (`a1782b7d7c`) foi pushado mas o squash-merge do #3763 usou o head stale (só o 1º commit) → handoff perdido em main, scorecards sobreviveram. Mesmo padrão do #3732 (onda Cliente). **Mitigação:** conferir `gh pr view --json headRefOid` == `git rev-parse HEAD` ANTES de mergear; se um merge disser "already merged" com HEAD diferente, verificar o que realmente landou (foi o que pegou este caso).

## Pointers detalhados (on-demand)
- PRs: https://github.com/wagnerra23/oimpresso.com/pull/3763 · https://github.com/wagnerra23/oimpresso.com/pull/3766
- Método: [Onda 0b](../requisitos/_Governanca/programa-ondas/onda-0-fundacao/0b-extensao-regua.md) · [screen-grade skill/ADR 0230](../decisions/0230-metodo-governance-scorecard.md)
- Scorecards: `memory/governance/scorecards/screens/oficinaauto-serviceorders-*.yaml`
- ROADMAP: [OficinaAuto/ROADMAP.md §Fase 3 · Régua por tela](../requisitos/OficinaAuto/ROADMAP.md)
