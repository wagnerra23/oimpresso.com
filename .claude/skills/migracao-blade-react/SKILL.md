---
name: migracao-blade-react
description: ATIVAR quando user pedir "migrar tela X", "migrar Blade pra React", "migração massiva", "/migracao-blade-react <modulo>/<tela>", OU em Edit/Write em `resources/views/**/*.blade.php` que ainda tem rota ativa apontando pra ela. Skill ORQUESTRADORA do pipeline Cowork→Inertia preservando paridade Blade legacy. Carrega 6-step workflow (SNAPSHOT → TRADUÇÃO VISUAL → ADAPTAÇÃO CONTROLLER → GATE VISUAL → PEST + SMOKE → PR) + 5 templates de runbook por tipo (LIST/DETAIL/FORM/DASHBOARD/REPORT). Usa output do Claude Design plugin (`prototipo-ui/prototipos/<modulo>/`) como fonte visual canônica. Não substitui Controller (Tier 0 IRREVOGÁVEL ADR 0093). Chama skills existentes (`mwart-process`, `mwart-comparative`, `mwart-quality`, `cockpit-runbook`, `multi-tenant-patterns`, `module-completeness-audit`, `commit-discipline`) em sequência. Sequencial obrigatório (worktree filha mata subagents). Piloto: Crm/Compras (16 views Blade + Compras.html 38KB Cowork). ADR 0141.
tier: B
status: active
version: 0.1.0
authority: canonical
---

# Skill: migracao-blade-react — Orquestrador Cowork→Inertia (Tier B)

> **Documento mãe:** [ADR 0141](../../memory/decisions/0141-skill-migracao-blade-react.md).
> Esta skill orquestra migração massiva Blade→Inertia preservando paridade legacy + aplicando "novo estilo" (shared components canônicos) automaticamente.

## Quando ativar

- User pede *"migrar tela X pra Inertia"*, *"migrar Blade pra React"*, *"migração massiva <módulo>"*
- User invoca `/migracao-blade-react <modulo>/<tela>`
- Edit/Write em `resources/views/**/*.blade.php` OU `Modules/*/Resources/views/**/*.blade.php` que **ainda tem rota web ativa** apontando pra ela
- User pede *"migrar todas as telas do <módulo>"* (loop sequencial 1 por vez)

## Quando NÃO ativar

- Tela já tem `resources/js/Pages/<Mod>/<Tela>.tsx` (já migrada) — usar `mwart-process` direto
- View Blade interna (partials, components, emails, PDF) — não migrar
- `vendor/` views — pacotes externos
- Tela sem mockup Cowork em `prototipo-ui/prototipos/<modulo>/` E sem rota ativa — pode ser morta, perguntar antes

## Pipeline 6-step (sequencial obrigatório)

```
╔══════════════════════════════════════════════════════════════════════╗
║ STEP 1 — SNAPSHOT PARIDADE                                           ║
║                                                                      ║
║ Input:  resources/views/<area>/<tela>.blade.php (Blade legacy)       ║
║         Modules/<X>/Http/Controllers/<X>Controller.php@<acao>        ║
║         Modules/<X>/Routes/web.php (rotas + middleware)              ║
║                                                                      ║
║ Output: memory/mwart-inventory/<modulo>/<tela>.snapshot.md           ║
║         contendo:                                                    ║
║         • Rotas (URL + verbos + middleware UPOS)                     ║
║         • Permissões (@can, $user->can(), middleware)                ║
║         • Campos do form (inputs + types + accept)                   ║
║         • Validações Laravel (FormRequest rules)                     ║
║         • Colunas DataTable (Yajra definitions)                      ║
║         • Filtros (selects + date ranges)                            ║
║         • Botões de ação (<a>, <button>, modais)                     ║
║         • Events::dispatch (eventos disparados)                      ║
║         • Hooks DataController (multi-tenant)                        ║
║         • Screenshot Blade atual (Chrome MCP — biz=1)                ║
║                                                                      ║
║ Checklist STEP 1 (skill recusa STEP 2 se algum FALTAR):              ║
║   ☐ Snapshot existe                                                  ║
║   ☐ Screenshot Blade capturado                                       ║
║   ☐ Rotas + permissões enumeradas                                    ║
║   ☐ Campos + validações enumerados                                   ║
║   ☐ Tipo de tela classificado (LIST/DETAIL/FORM/DASHBOARD/REPORT)    ║
╠══════════════════════════════════════════════════════════════════════╣
║ STEP 2 — TRADUÇÃO VISUAL (Cowork → Inertia/TSX draft)                ║
║                                                                      ║
║ Input:  prototipo-ui/prototipos/<modulo>/visual-source.html          ║
║         prototipo-ui/prototipos/<modulo>/cowork-*.jsx                ║
║         memory/mwart-inventory/<modulo>/<tela>.snapshot.md           ║
║         runbook-{tipo}.template.md (este diretório)                  ║
║                                                                      ║
║ Output: resources/js/Pages/<Mod>/<Tela>/Index.tsx (DRAFT)            ║
║         contendo:                                                    ║
║         • Persistent Layout AppShellV2 (ADR 0094)                    ║
║         • Shared components OBRIGATÓRIOS por tipo (ver tabela)       ║
║         • Tokens canônicos (paleta stone/emerald/rose/amber)         ║
║         • Tipos TS espelhando snapshot (campos + colunas)            ║
║         • Props matching Controller atual (NÃO recria Controller)    ║
║                                                                      ║
║ Shared components OBRIGATÓRIOS por tipo:                             ║
║   LIST       → DataTable, PageHeader, PageFilters, BulkActionBar,    ║
║                EmptyState                                            ║
║   DETAIL     → PageHeader, Sheet, StatusBadge, Tabs                  ║
║   FORM       → PageHeader, Card, Input, Select, Button               ║
║   DASHBOARD  → KpiGrid, KpiCard, PageHeader (sticky filters)         ║
║   REPORT     → DataTable, date range picker, export CSV/PDF          ║
╠══════════════════════════════════════════════════════════════════════╣
║ STEP 3 — ADAPTAÇÃO CONTROLLER (DELTA MÍNIMO — NÃO substituir)        ║
║                                                                      ║
║ Permitido:                                                           ║
║   ✓ return view('<x>::<tela>', $data) → Inertia::render('<X>/<Tela>', $data)
║   ✓ Adicionar props derivadas se draft TSX precisar                  ║
║   ✓ Adicionar route nova `<x>/<tela>-v2` mantendo route legada       ║
║                                                                      ║
║ PROIBIDO (skill recusa, sem exceção):                                ║
║   ✗ Remover business_id scope (Tier 0 IRREVOGÁVEL — ADR 0093)        ║
║   ✗ Remover permission checks                                        ║
║   ✗ Reescrever lógica de negócio                                     ║
║   ✗ Substituir Models por novos                                      ║
║   ✗ Inventar Services não existentes (ver LICOES_F3 2026-05-09)      ║
║                                                                      ║
║ Output: Modules/<X>/Http/Controllers/<X>Controller.php (delta)       ║
╠══════════════════════════════════════════════════════════════════════╣
║ STEP 4 — GATE VISUAL (Wagner aprova SCREENSHOT antes do PR)          ║
║                                                                      ║
║ Chama skill: mwart-comparative                                       ║
║                                                                      ║
║ Output: memory/requisitos/<Mod>/<tela>-visual-comparison.md          ║
║         (15 dimensões + framework Anthropic Claude Design)           ║
║         + screenshot draft (Chrome MCP)                              ║
║         + screenshot Cowork source                                   ║
║         + screenshot Blade legacy (do STEP 1)                        ║
║                                                                      ║
║ Skill PARA aqui aguardando aprovação Wagner. Sem aprovação:          ║
║   • Não roda STEP 5                                                  ║
║   • Não abre PR                                                      ║
║   • Não toca em main                                                 ║
╠══════════════════════════════════════════════════════════════════════╣
║ STEP 5 — PEST + SMOKE                                                ║
║                                                                      ║
║ Output: tests/Feature/<Mod>/<Tela>Test.php (Pest)                    ║
║         contendo:                                                    ║
║         • biz=1 happy path ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)) ║
║         • biz=99 cross-tenant guard (não vê dados de outro tenant)   ║
║         • Cada campo/validação do snapshot tem teste                 ║
║         • Cada permissão do snapshot tem teste                       ║
║                                                                      ║
║ Smoke local (Herd) ANTES do PR:                                      ║
║   1. php artisan migrate (se aplicável)                              ║
║   2. npm run build                                                   ║
║   3. Acessar http://oimpresso.test/<rota> com user biz=1             ║
║   4. Validar paridade visual com STEP 4 approved                     ║
╠══════════════════════════════════════════════════════════════════════╣
║ STEP 6 — PR ≤300 LOC (commit-discipline Tier A)                      ║
║                                                                      ║
║ Chama skills:                                                        ║
║   • commit-discipline (Tier A — 1 PR = 1 intent, conventional)       ║
║   • module-completeness-audit (8 dims antes de pedir review)         ║
║                                                                      ║
║ PR title: feat(<modulo>): MWART <tela> Blade → Inertia [W]           ║
║ PR body:                                                             ║
║   • Refs: ADR 0141 + snapshot + visual-comparison                    ║
║   • Paridade: link snapshot + checklist preserved                    ║
║   • Smoke: print do http://oimpresso.test/<rota> rodando             ║
║   • Tier 0 check: business_id scope verificado                       ║
║                                                                      ║
║ Loop: próxima tela só DEPOIS deste PR mergeado.                      ║
╚══════════════════════════════════════════════════════════════════════╝
```

## Templates de runbook por tipo (este diretório)

| Arquivo | Tipo de tela | Quando usar |
|---------|--------------|-------------|
| `runbook-LIST.template.md` | Listagem com tabela | `index.blade.php` com DataTable |
| `runbook-DETAIL.template.md` | Detalhe de 1 entidade | `show.blade.php` |
| `runbook-FORM.template.md` | Cadastro/edição | `create.blade.php`, `edit.blade.php` |
| `runbook-DASHBOARD.template.md` | KPIs + drill-down | `dashboard.blade.php`, cockpits |
| `runbook-REPORT.template.md` | Relatório com filtros + export | `report/*.blade.php` |

Cada template tem checklist específico do tipo + shared components obrigatórios + paridade-by-default.

## Skills associadas (chamadas em sequência pelo pipeline)

| Step | Skill chamada | Tier |
|------|---------------|------|
| STEP 1 | `brief-first` (always-on), `mcp-first` (always-on) | A |
| STEP 1 | `cockpit-runbook` (Tier C — slash) | C |
| STEP 2 | `mwart-quality` (auto-trigger) | B |
| STEP 2 | `ui-component-creator` (auto-trigger) | B |
| STEP 3 | `multi-tenant-patterns` (always-on) | A |
| STEP 4 | `mwart-comparative` (always-on) | A |
| STEP 4 | `charter-write` (auto-trigger) | B |
| STEP 5 | `mwart-process` (always-on F4 QA) | A |
| STEP 6 | `commit-discipline` (always-on) | A |
| STEP 6 | `module-completeness-audit` (auto-trigger) | B |

## Não-Goals

- ❌ Não substitui Controller (Tier 0)
- ❌ Não toca `vendor/`
- ❌ Não migra sem Cowork output disponível (ou fallback explícito)
- ❌ Não paraleliza em worktree filha (subagents mortos)
- ❌ Não pula STEP 4 (gate visual screenshot Wagner)

## Piloto

**Módulo:** Crm/Compras
**Views Blade legacy:** 16
**Mockup Cowork disponível:** `prototipo-ui/prototipos/compras/visual-source.html` (37,9 KB)
**Cadência piloto:** 1 tela por dia (sequencial). 16 telas ≈ 16 dias úteis com fator 10x IA-pair.
**Critério liberação geral:** ≥4/5 primeiras telas aprovadas por Wagner em screenshot + zero regressão funcional em smoke biz=4 ROTA LIVRE.

## Versionamento

- **v0.1.0 (2026-05-11):** spec inicial, 6 steps, 5 templates, piloto Crm/Compras
- **v0.2 (planejado pós-piloto):** refinamentos templates baseados em ≥5 telas reais
- **v1.0 (planejado pós-50 telas):** publicar como guia oficial do projeto
