---
doc: PRE-MERGE-UI
camada: meta
status: ativo
created: 2026-05-24
parent_adr: UI-0013
---

# PRE-MERGE UI · checklist por camada antes de PR

> **Quando aplicar:** todo PR que toca `resources/js/Pages/`, `resources/js/Components/`, `resources/css/`, `tailwind.config.*`, ou `memory/requisitos/_DesignSystem/`.
> **Tempo:** ~3 min de revisão mental.
> **Se algum item falhar:** comunique Wagner antes de mergear, **não corrija silenciosamente**.

Checklist deriva da **hierarquia de 4 camadas** ([ADR UI-0013](adr/ui/0013-constituicao-ui-v2-camadas.md)). Cada camada tem regras específicas — só roda a seção da camada que você tocou.

---

## Camada 1 · Fundações (`resources/css/inertia.css`, `cockpit.css`, tailwind config)

**Critério-gatilho:** tocou cor/fonte/espaço/sombra/radius em CSS canon.

- [ ] Existe **ADR UI aprovada por Wagner** pra essa mudança? Se não, **PARE** — abre ADR `06-decisoes/proposals/` antes de codar.
- [ ] Token novo segue **fórmula canônica**:
  - Cor: `oklch(L C H)` ([ADR 0043 v2](../../../.claude/worktrees/frosty-greider-83ab2f/_v2-tmp/project/06-decisoes/0043-oklch-espaco-cor.md) — quando incorporada como UI-0014)
  - Espaço: múltiplo de 4 (`4px`, `8px`, `12px`, `16px`, `20px`, `24px`, `32px`)
  - Tipografia: IBM Plex Sans/Mono ([UI-0001](adr/ui/0001-tailwind-4-como-fundacao-css.md))
- [ ] Não introduzi cor crua (`#hex`, `rgb()`, `hsl()` literal) fora de `#fff` e `#000`
- [ ] Token é **semântico**, não estético (ex: `--accent`, não `--azul-1`)
- [ ] Bumped versão do DS no `CHANGELOG.md` (Fundação muda = mínimo v0.X → v0.Y; breaking = v0 → v1)
- [ ] Não regrediu nenhuma ADR já aceita (UI-0001 a UI-0013) sem `supersedes:` explícito

## Camada 2 · Shell (`AppShellV2.tsx`, `PageHeader.tsx`, `ModuleTopNav.tsx`, `Sidebar.tsx`)

**Critério-gatilho:** mudança no chrome universal do app (não por tela).

- [ ] Mudança **não muda por tela** — é universal a todo módulo
- [ ] ADR existente (UI-0008, UI-0011, UI-0009) **lida antes** de modificar
- [ ] Se quebra contrato (ex: tira slot, adiciona slot, muda comportamento default), **abriu ADR nova**
- [ ] Sidebar não virou light se ADR-0009 ainda vigente (ou virou e proposal `sidebar-dark-vs-light` foi aceita)
- [ ] `localStorage` segue prefixo `oimpresso.cockpit.*` ([UI-0011](adr/ui/0011-sidebar-single-pane-cascata-user-menu.md))
- [ ] Charter `.charter.md` atualizado se mudou contrato visual
- [ ] CHANGELOG apendado

## Camada 3 · Padrão de Tela (`memory/requisitos/_DesignSystem/padroes-tela/PT-XX-*.md`)

**Critério-gatilho:** criou ou alterou doc canônico de PT-01..PT-XX.

- [ ] Doc segue formato canônico (`PT-01-Lista.md` como template — anatomia + DNA + regras + snippet + casos limite + refs)
- [ ] Lista "Aplicado em" reflete **estado real** dos módulos (rodar `grep` ou inventário se incerto)
- [ ] Mudança breaking? Lista módulos a refatorar + abre ADR
- [ ] Adicionou variante? Documentou em seção "Variantes" sem mudar anatomia base
- [ ] Frontmatter `versao:` bumpado (v1.0 → v1.1 minor; v1 → v2 breaking)
- [ ] CHANGELOG apendado com linha `ADD PT-XX vN.M`

## Camada 4 · Módulo (`Modules/<X>/`, `resources/js/Pages/<X>/*.tsx`)

**Critério-gatilho:** tocou Page Inertia ou Component específico de módulo.

### Anti-padrões (KB-9.75)

- [ ] **AP1** Sem cor hardcoded · `rg -E "(#[0-9a-fA-F]{3,8}|oklch\(0\.\d+ \d+\.\d+ \d+)" resources/js/Pages/<X>/` retorna 0 ocorrências (excetuando comments e `#fff`/`#000`)
- [ ] **AP2** Componentes vêm do shared · sem reinventar `<Table>`, `<Drawer>`, `<PageHeader>`, `<DataTable>`, `<BulkActionBar>`, `<EmptyState>`, `<StatusBadge>`
- [ ] **AP3** `localStorage` sempre prefixado `oimpresso.<modulo>.*` ([ADR 0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- [ ] **AP4** Sem ícone fora do `lucide-react` ([UI-0003](adr/ui/0003-lucide-react-como-unica-iconografia.md))
- [ ] **AP5** Sem gradient decorativo 135deg (bluish-purple) — anti-trope DS
- [ ] **AP6** Sem emoji em UI de produto (só ícone SVG do lucide)
- [ ] **AP7** Sem `bg-fill` em status badges — usa dot + texto colorido (Stripe-style)
- [ ] **AP8** PT-BR em todo label, copy, erro, mensagem

### Estrutural

- [ ] Charter `.charter.md` ao lado do `.tsx` existe e está `status: live` (skill `charter-first` Tier A)
- [ ] Inertia::defer aplicado em props pesadas (skill `inertia-defer-default`)
- [ ] Multi-tenant Tier 0 — query usa `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- [ ] PT-01 (ou PT-XX aplicável) segue 6 slots — documentado em `04-modulos/<x>/OWNERS.md` (futuro) ou na charter

### Métricas

- [ ] Score Module Grade v4 **não baixou** vs `governance/module-grades-baseline.json` — se baixou, **NÃO** sobrescreve baseline, alerta Wagner
- [ ] Pest tests cobrem mudança visual crítica
- [ ] Smoke test em https://oimpresso.com (biz=4 ROTA LIVRE Larissa) se mudança visível ao cliente

## Camada 5 · Protocolo (KB-9.75, governance, rubric)

**Critério-gatilho:** alterou rubric, AUDIT_PROTOCOL, governance, ADR template.

- [ ] Mudança no rubric tem ADR? (rubric é régua — mudar régua exige justificativa)
- [ ] Versão da rubric incrementada se mudou peso/feature/anti-padrão
- [ ] CHANGELOG apendado

## Camada 6 · Decisões (`memory/requisitos/_DesignSystem/adr/ui/NNNN-*.md`)

**Critério-gatilho:** criou ADR UI nova ou alterou existente.

- [ ] Template seguido (formato Nygard — Status/Data/Categoria/Refs + Contexto/Decisão/Consequências)
- [ ] Numeração sequencial (próximo número livre — `ls adr/ui/ | tail`)
- [ ] Status correto: `proposed` (aguardando Wagner) | `accepted` | `superseded` | `deprecated` | `historical`
- [ ] Append-only — se substituindo ADR anterior, marca `supersedes: [N]` e a antiga ganha `superseded_by: [M]`, **nunca apaga**
- [ ] CHANGELOG apendado com linha `ADR UI-NNNN <título>`

---

## Universal · sempre verificar

- [ ] **Commit-discipline** ([skill](../../../.claude/skills/commit-discipline/SKILL.md)) — 1 PR = 1 intent · ≤300 linhas · conventional commits · sem PII em código/log/commit msg
- [ ] CHANGELOG tem linha(s) do trabalho desta sessão
- [ ] Nenhum arquivo deletado sem ADR (deleção = mudança estrutural)
- [ ] Nenhuma renomeação de path sem `MOVE` no CHANGELOG
- [ ] Se mudou UI visível em produção, screenshot anexado ao PR (smoke evidence — [skill `smoke-prod-evidence`](../../../.claude/skills/smoke-prod-evidence/))

---

## Sinais de regressão · alertar Wagner (não corrige silenciosamente)

- Score KB-9.75 / Module Grade v4 de um módulo baixou
- Token sendo usado fora da camada permitida (ex: `--sb-bg` em arquivo de módulo)
- Hardcoded color/font/spacing em arquivo de módulo
- Componente novo reinventando algo do shared
- ADR sendo contradita silenciosamente
- Charter `.charter.md` ficou desatualizado vs `.tsx` (drift)

**Pare. Reporte. Não corrija silenciosamente.**

---

## Por que esse checklist existe

Sem ele, qualquer agente (Claude DS, Claude Code, agente externo) pode regredir o sistema sem perceber. Wagner é único humano + único aprovador — **disciplina é código**.

Inspirado em:
- Constituição UI v2 (handoff Claude Design 2026-05-24)
- KB-9.75 rubric (5 categorias + 8 anti-padrões)
- Loop Design↔Code [PROTOCOL.md](../../../prototipo-ui/PROTOCOL.md) 7 fases

## Refs

- **ADR-mãe**: [UI-0013 Constituição UI v2](adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Template ADR**: [`adr/ui/`](adr/ui/) (próximo número: 0014)
- **PT-01 canon**: [`padroes-tela/PT-01-Lista.md`](padroes-tela/PT-01-Lista.md)
- **Skills correlatas**: `commit-discipline` · `mwart-quality` · `multi-tenant-patterns` · `inertia-defer-default` · `charter-first` · `module-completeness-audit`

---

**Última revisão:** 2026-05-24
