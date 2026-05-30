---
date: '2026-05-30'
topic: "Screen-grade método + Pré-Flight de Tela — estado-da-arte de governança de design, foco Wagner fraco em design"
type: session
authors: [W, C]
prs: [1991]
related_adrs:
  - 0230-metodo-governance-scorecard
  - 0231-processo-trabalho-canonico-especialista-por-area
  - 0232-modelo-peso-real-classificacao-por-meta
  - 0235-ds-v4-accent-roxo-universal
  - 0155-rubrica-module-grade-v3
status: in-progress
owner: W
---

# Screen-grade + Pré-Flight de Tela — estado-da-arte (2026-05-30)

## Contexto

Wagner: *"foco em design, estou muito fraco nessa área; o Claude Design não entende as regras e eu não sei instruir ele"*. Evoluiu pra: criar método de **maturidade de tela** comparativo aos melhores, com **pré-flight** que impede a IA de inventar/repetir erro, rodado por **agentes em paralelo**. Branch `claude/design-governance-progress-NRXE3`, PR #1991.

## Decisões tomadas

1. **Tela-ouro** = `Sells/Create` (A+ 9,75, charter live, 39 testes) → `prototipo-ui/GOLDEN-REFERENCE.md` + 10 regras binárias.
2. **Não inventar método** — operacionalizar o que já existe: `framework-15-dimensoes` + ADR 0230 (scorecard) + 0231 (especialista/área) + 0232 (Peso Real). Vira `SCREEN-GRADE-METODO.md` (espelho do `module-grade`).
3. **Pré-Flight de Tela** (`prototipo-ui/PRE-FLIGHT-TELA.md`) — resolvedor de pré-requisitos por tela (4 blocos: identidade/não-inventar/não-repetir/validar). Princípio: o agente nunca monta o próprio contexto, roda o resolvedor.
4. **Piloto** (escolha Wagner): 10-12 telas top-Peso-Real (Sells+Cliente+Financeiro), validar método antes de escalar p/272.
5. **Agentes** (escolha Wagner): reusar `coordenador-paralelo` + `design-arte` (anti-dup), criar só `ScreenGradeCommand` p/ persistir.

## Estado-da-arte pesquisado (alguém já fez "isso"? SIM — 4 convergentes)

| SOTA | Resolve | Mecanismo |
|---|---|---|
| GitHub **Spec-Kit** / **Kiro** (spec-driven) | pré-flight | context-grounding hooks read-only probing ancorados em evidência do repo |
| Anthropic **context engineering** | pré-flight + não-repetir | JIT retrieval + "erro no contexto previne repetir" + memory fleet |
| Figma **Dev Mode MCP + Code Connect** | não-inventar | liga componente↔código → reusa real; hex→token |
| **USWDS/VA.gov/Figma DesignOps** maturity scale | a grade | níveis Beginner→Champion; Champion = scoring em todas as live apps |

**Achado:** o oimpresso reinventou a convergência dos 4, **code-first** (sem Figma), e está **à frente** em 2: Peso Real (ROI→R$5M) + persona-weighting. Nenhum dos 4 SOTA pondera por receita.

## Nota geral do método (vs estado-da-arte)

- Conceito/desenho: **85/100** (Advanced→Leader)
- Execução/wiring atual: **40/100** (Developing — ainda é doc, não tool/gate)
- **Ponderada: ~68/100** — todos os gaps são wiring (doc→tool), nenhum conceitual.

## Pontos de quebra identificados + fix

B1 1-golden≠272-tipos → golden por arquétipo · B2 v3 azul vs v4 roxo → dim "DS v4" + migrar âncora · B3 sem enforcement → ratchet+GovernanceV4 · B4 não-escala → agentes paralelos · B5 golden envelhece → Invariante A · B6 151/272 sem charter → dim 16 Pré-Flight.

## Artefatos criados (PR #1991)

- `prototipo-ui/GOLDEN-REFERENCE.md` — tela-ouro + 10 regras binárias + validação SOTA
- `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisitos
- `memory/requisitos/_DesignSystem/SCREEN-GRADE-METODO.md` — método 16-dim + níveis + score-as-code
- este session log

## Próximos passos

1. Migrar âncora `Sells/Create` pra DS v4 (roxo).
2. `ScreenGradeCommand` (espelho `ModuleGradeCommand`) — persiste `memory/governance/scorecards/screens/*.yaml`.
3. Disparar piloto (coordenador-paralelo → design-arte × Sells/Cliente/Financeiro).
4. Goldens dos arquétipos faltantes (dashboard/kanban/detalhe/relatório/drawer).
5. Wiring: pré-flight vira hook/skill; REGISTRY vira índice consultável; dimensão Design Maturity no GovernanceV4.
6. Candidata a ADR canon (método screen-grade).
