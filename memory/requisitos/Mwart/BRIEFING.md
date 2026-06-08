---
module: Mwart
status: meta-processo enforcement migração Blade→Inertia/React (ADR 0104 IRREVOGÁVEL)
piloto: governance interna (todos `Modules/*` migrando passam pelo gate)
last_review: 2026-05-16
owner: wagner
parent_adr: 0104
related_adrs: [0104, 0106, 0107, 0109, 0114, 0153, 0154, 0155, 0156]
nota_atual_v2: "~45/100 (injusto — D5+D4.b+D6.a penalizados)"
nota_esperada_v3: "~75-80/100 pós-PR3 na_justified declarado"
---

# BRIEFING — MWART (Module Web App React Transition)

> **1-pager executivo** · Atualizado: 2026-05-16 (pós-PR3 governance-v3-docs `na_justified` declarado)
> Canon: [SPEC.md](SPEC.md) · ADR mãe: [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · Skill mãe: [mwart-process](../../../.claude/skills/mwart-process/SKILL.md) Tier A always-on · Rubrica v3: [0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) + [0156](../../decisions/0156-rubrica-v3-pesos-redistribuidos.md)

## TL;DR

**Meta-processo de enforcement** do caminho canônico Blade → Inertia/React. Wagner 2026-05-08 cravou: "único caminho, falhas inaceitáveis". 3 camadas: (1) skill Tier A `mwart-process`, (2) hook PreToolUse `block-mwart-violation.ps1`, (3) CI workflow `mwart-gate.yml`. Override autorizado via comentário PR `/mwart-override <razão>` → ADR per-tela `lifecycle: historical`. NÃO é módulo de features cliente — é gate canônico que toda migração de tela passa.

## Capacidade core

- **5 fases obrigatórias** (skill `mwart-process`): F1 RUNBOOK → F2 BACKEND BASELINE → F3 ESTADO-DA-ARTE FRONTEND → F4 QA → F5 CUTOVER
- **Camada 1 enforcement (ativa):** skill Tier A always-on bloqueia agent que pula fase
- **Camada 2 enforcement (US-MWART-001 p0, todo):** hook PreToolUse `.claude/hooks/block-mwart-violation.ps1` bloqueia Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` sem RUNBOOK correspondente
- **Camada 3 enforcement (US-MWART-001 p0, todo):** CI workflow `.github/workflows/mwart-gate.yml` falha PR sem RUNBOOK + score audit ≥70
- **Gate visual F1.5 + F3 estado-da-arte** ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) + [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)) — Cowork ↔ Claude Code loop formalizado em `prototipo-ui/PROTOCOL.md`
- **Claude Design plugin Anthropic integrado** ([ADR 0109](../../decisions/0109-claude-design-plugin-integrado-processo-mwart.md)) — design-critique + design-system + design-handoff + ux-copy + accessibility-review + research-synthesis

## Cliente piloto

**N/A por design.** Consumidores são `Modules/*` que migram telas: Repair Wave B6 (5 telas Index/Show/Create/Edit/AddParts), Vestuario, ComunicacaoVisual, etc. Cliente externo biz=4 ROTA LIVRE só vê resultado (telas migradas), não consome processo.

## Score module-grade

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR3) | ~45/100 | Penalizava D5 (sem cliente direto), D4.b (sem FSM próprio), D6.a (sem Controllers Inertia próprios) — injusto pra meta-processo |
| **v3 (pós-PR3)** | **~75-80/100** (esperado) | `na_justified` D5+D4.b+D6.a declarado no SPEC → rubrica v3 redistribui peso (ADR 0156) |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** meta-processo de governança — biz=4 ROTA LIVRE não consome features Mwart; consumidores são `Modules/*` migrando.
- **D4.b (FSM canônica):** processo administrativo de gating (skill + hook + CI), não fluxo de negócio com transições Eloquent.
- **D6.a (Inertia::defer):** meta-processo sem Controllers Inertia próprios — telas alvo vivem nos módulos consumidores.

## Gaps remanescentes

- 🔴 **US-MWART-001 p0** Camadas 2+3 enforcement (hook + CI) — único bloqueio real do "único caminho" canônico
- 🟡 **US-MWART-002 p1** Backfill audit ~78 telas Inertia existentes (migradas antes do processo formalizar)
- 🟡 **F2 BACKEND BASELINE Pest 5+** sem ele = regressão silenciosa garantida
- 🟢 **Loop Cowork ↔ Claude Code** formalizado em `prototipo-ui/PROTOCOL.md` (ADR 0114)

## Próximo passo sugerido

1. Wagner desbloquear US-MWART-001 (1.5h IA-pair) → hook + CI ficam ativos
2. Após camadas 2+3 ativas → US-MWART-002 backfill 78 telas existentes
3. Telas com score <50 viram tasks p2 (refactor backlog explícito Wagner decide ordem)

## ADRs centrais

- [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) Processo MWART canônico único caminho IRREVOGÁVEL (mãe)
- [0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) Recalibração estimates IA-pair
- [0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) Visual comparison gate F3
- [0109](../../decisions/0109-claude-design-plugin-integrado-processo-mwart.md) Claude Design plugin integrado
- [0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) Cowork loop formalizado
- [0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) Rubrica v3 anti-injustiça
