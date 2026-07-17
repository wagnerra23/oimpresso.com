---
slug: 0141-skill-migracao-blade-react
number: 141
title: "Skill `migracao-blade-react` — orquestrador Cowork→Inertia preservando paridade Blade legacy"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-11"
module: infra
tags: [skill, mwart, blade, react, inertia, migracao, cowork, escala-massiva]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0095-skills-tiers-convencao-interna, 0104-processo-mwart-canonico-unico-caminho, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0107-emendation-0104-visual-comparison-gate-f3, 0109-claude-design-plugin-integrado-processo-mwart, 0114-prototipo-ui-cowork-loop-formalizado]
pii: false
review_triggers:
  - "Pós-piloto Crm/Compras (16 views) — paridade real <95% medida em smoke biz=4 → reabrir STEP 1 snapshot"
  - "≥3 telas migradas via skill regredirem feature em prod → bloquear skill até refator STEP 1"
  - "≥30% das telas-alvo sem mockup Cowork → reavaliar dependência (fallback manual ou template-only)"
  - "Após 50 telas migradas — medir velocity real vs estimativa fator 10x (ADR 0106) → recalibrar"
  - "Se Wagner aprovar <50% dos screenshots no STEP 4 → revisar templates de runbook por tipo"
---

# ADR 0141 — Skill `migracao-blade-react`: orquestrador Cowork→Inertia preservando paridade Blade

## Status

**Aceito 2026-05-11.** Wagner aprovou criação de skill Tier B nova pra
orquestrar migração massiva Blade→Inertia, usando output do Claude Design
plugin (Cowork) como fonte visual canônica.

## Contexto

Em 2026-05-11 Wagner mapeou o universo real de telas no projeto e tomou
duas decisões estratégicas:

### Inventário descoberto

- **135 Pages Inertia** já migradas (em 25 módulos)
- **902 Blade modulares** em 21 módulos (`Modules/*/Resources/views/`)
- **406 Blade raiz UltimatePOS** em 50+ áreas (`resources/views/`)
- **~28 telas com mockup Cowork pronto** no ZIP do Claude Design plugin

UX híbrida (Blade legacy + Inertia novo) está em produção em todos os
módulos parciais. Wagner observou: *"fica muito feio ter dois tipos de
layouts"* — usuário troca de planeta visual a cada clique. Custo cognitivo
real, perda de produtividade ROTA LIVRE (biz=4, 99% volume).

### Tentativas anteriores

- **Manual (humano + IA-pair):** Wagner confirmou *"já fiz isso, não deu
  muito certo"* — escala não-linear, perda de paridade, inconsistência
  visual entre telas migradas em momentos diferentes
- **Batch automatizado 2026-05-09:** `PROMPT_PARA_CLAUDE_CODE — F3
  Financeiro completo (5 telas)` rejeitado pré-merge ([LICOES_F3](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)).
  4/5 controllers violaram Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)).
  Mas frontend `.tsx` aprovado como pinos F1 visuais.
- **Claude Design plugin (Cowork):** Wagner observou *"o design refez a
  tela com muito mais precisão, refez todo layout, colocou em destaque,
  aumentou a usabilidade"* — output visual superior ao manual.

### Skills existentes (peças soltas)

| Skill | O que faz | Gap |
|-------|-----------|-----|
| `cockpit-runbook` | Gera RUNBOOK.md base | Não cruza com Cowork output |
| `mwart-process` | 5 fases obrigatórias por tela | Não aplica em escala massiva |
| `mwart-comparative` | Gate visual screenshot | Por tela, não orquestra batch |
| `mwart-quality` | 9 pré-flight checks | Não detecta perda paridade |
| `multi-tenant-patterns` | Tier 0 obrigatório | Defensivo, não prescritivo |
| `module-completeness-audit` | 8 dims antes de fechar | Pós-fato, não previne |
| **(falta)** | **Orquestrador Cowork→Inertia em escala** | — |

Não existe skill que **integre Cowork output + Blade legacy paridade +
shared components canônicos + gate Tier 0 + workflow sequencial em escala**.
Migrar 800+ Blades sem ela exige 800× reaplicação manual do mesmo método.

## Decisão

Criar skill **`migracao-blade-react`** (Tier B + slash command
`/migracao-blade-react <modulo>/<tela>`) que orquestra pipeline 6-step:

```
STEP 1 — SNAPSHOT PARIDADE
  Extrai do Blade legacy:
    • Rotas + permissões
    • Campos do form + validações Laravel
    • Colunas DataTable + filtros
    • Botões de ação + modais
    • Events::dispatch + hooks DataController
    • Screenshot Blade atual (Chrome MCP)
  → memory/mwart-inventory/<modulo>/<tela>.snapshot.md

STEP 2 — TRADUÇÃO VISUAL
  Cowork HTML/JSX → Inertia/TSX draft:
    • AppShellV2 layout
    • Shared components obrigatórios (KpiCard, PageHeader, DataTable,
      StatusBadge, EmptyState, BulkActionBar)
    • Tokens canônicos (paleta/tipo/espaçamento)
    • Tipos TS espelhando snapshot
  → resources/js/Pages/<Mod>/<Tela>/Index.tsx (draft)

STEP 3 — ADAPTAÇÃO CONTROLLER (delta mínimo, NÃO substituir)
  • view() → Inertia::render()
  • Preserva business_id scope (Tier 0 IRREVOGÁVEL)
  • Preserva permissões existentes
  → Modules/<X>/Http/Controllers/<X>Controller.php (apenas mudanças)

STEP 4 — GATE VISUAL
  • visual-comparison.md (15 dimensões mwart-comparative)
  • Screenshot draft + Cowork lado-a-lado
  • Wagner aprova SCREENSHOT (não tabela) ANTES do PR
  → memory/requisitos/<Mod>/<tela>-visual-comparison.md

STEP 5 — PEST + SMOKE
  • tests/Feature/<Mod>/<Tela>Test.php (biz=1 + cross-tenant biz=99)
  • Smoke local (Herd) antes do PR

STEP 6 — PR ≤300 linhas (1 tela = 1 PR — commit-discipline Tier A)
```

### 5 templates de runbook por tipo de tela

| Tipo | Trigger | Shared components obrigatórios | Checklist paridade |
|------|---------|-------------------------------|---------------------|
| **LIST** | `index.blade.php` | `DataTable`, `PageHeader`, `PageFilters`, `BulkActionBar`, `EmptyState` | Colunas + filtros + paginação + busca + export + bulk |
| **DETAIL** | `show.blade.php` | `PageHeader`, `Sheet`, `StatusBadge`, `Tabs` | Campos + relações + ações + histórico/audit |
| **FORM** | `create/edit.blade.php` | `PageHeader`, `Card`, `Input`, `Select`, `Button` | Campos + validação + uploads + dependent dropdowns |
| **DASHBOARD** | cockpit/dashboard | `KpiGrid`, `KpiCard`, `PageHeader`, sticky filters | KPIs + drill-down + período + ações |
| **REPORT** | `report/*.blade.php` | `DataTable`, date range, export CSV/PDF | Período + filtros + agregação + export |

Templates ficam em `.claude/skills/migracao-blade-react/runbook-{tipo}.template.md`.

### Por que Tier B (não A)

Não é always-on. Ativa por description match quando user pede *"migrar tela X"*,
*"migrar Blade pra React"*, *"/migracao-blade-react"*, OU em Edit/Write em
`resources/views/**/*.blade.php` que ainda tem rota ativa.

### Por que orquestradora (não substituidora)

Skill NÃO reimplementa lógica das skills existentes. CHAMA:
- `cockpit-runbook` no STEP 2 (gera RUNBOOK base)
- `mwart-process` no STEP 1-6 (5 fases canônicas)
- `mwart-comparative` no STEP 4 (gate visual)
- `mwart-quality` no STEP 2 (9 pré-flight)
- `multi-tenant-patterns` no STEP 3 (Tier 0)
- `module-completeness-audit` no STEP 6 (8 dims)
- `commit-discipline` no STEP 6 (1 PR ≤300 linhas)

Tira o atrito de aplicar 7 skills manualmente por tela × 800+ telas.

## Não-Goals (escopo intencionalmente fora)

- ❌ **Não substitui Controller existente.** Tier 0 IRREVOGÁVEL ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)).
  Skill só faz delta mínimo (`view()` → `Inertia::render()`).
- ❌ **Não toca `vendor/`** (133 views externas — pacotes npm/composer).
- ❌ **Não gera código backend novo** — só adapta Inertia render.
- ❌ **Não substitui `mwart-process`** — orquestra ele.
- ❌ **Não cria Cowork output** — depende dele já existir em `prototipo-ui/prototipos/<modulo>/`.
- ❌ **Não paraleliza em worktree filha** ([handoff 2026-05-11 18:30](../handoffs/2026-05-11-1830-paralelizacao-omnichannel-frustrada.md)) — sequencial.

## Consequências

### Positivas

- **Escala viável:** 800+ Blade legacy migram em ~6-12 meses com fator 10x
  IA-pair ([ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md))
- **Paridade garantida:** STEP 1 snapshot impede *"esqueci o filtro X"*
- **Tier 0 preservado:** STEP 3 delta mínimo + STEP 6 gate bloqueia regressão
- **Consistência visual:** shared components forçados garantem identidade
  única automaticamente
- **Auditoria barata:** cada tela tem snapshot + visual-comparison + PR ≤300

### Negativas / riscos

- **Dependência Cowork:** se 30%+ telas-alvo não têm mockup, skill perde
  vantagem (review trigger ativa fallback manual)
- **Curva de aprendizado:** 5 templates exigem refino após primeiros usos
  reais (review trigger STEP 4 aprovação <50%)
- **Tempo upfront:** ~1 ciclo pra spec + piloto antes de qualquer ganho
- **Sequencial obrigatório:** subagents mortos em worktree filha — não
  acelera por paralelismo

## Piloto

**Crm/Compras** — 16 views Blade legacy + `Compras.html` (38 KB) do
Cowork. Pequeno o suficiente pra validar método em 1 ciclo, grande o
suficiente pra estressar templates (tem LIST + FORM + DETAIL).

**Aprovação piloto:** Wagner valida 5 primeiras telas migradas via skill
em prod (smoke biz=4 ROTA LIVRE). Se ≥4 passam sem regressão e Wagner
aprova screenshots → skill libera escala (Vendas, Produto, Repair, etc).

## Related

- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0095 — Skills tiers convenção interna](0095-skills-tiers-convencao-interna.md)
- [ADR 0104 — Processo MWART canônico único caminho](0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0106 — Recalibração velocidade fator 10x IA-pair](0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0107 — Visual comparison gate F3](0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0109 — Claude Design plugin integrado processo MWART](0109-claude-design-plugin-integrado-processo-mwart.md)
- [ADR 0114 — Cowork loop formalizado](0114-prototipo-ui-cowork-loop-formalizado.md)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — anti-padrões catalogados
