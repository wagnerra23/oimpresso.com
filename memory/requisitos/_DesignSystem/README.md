---
module: _DesignSystem
alias: design-system
status: ativo
migration_target: N/A
migration_priority: alta
risk: baixo
areas: [ui, tokens, componentes, acessibilidade]
last_generated: 2026-05-24
version: 0.6
parent_adr: UI-0013
---

# Design System (cross-cutting)

Decisões de UI que atravessam todos os módulos — tokens Tailwind 4, componentes shadcn/ui, iconografia lucide, dark mode, acessibilidade e convenções visuais.

**NÃO é um módulo Laravel** — não tem controller, rota nem migration. É pasta de documentação cross-cutting.

---

## Hierarquia de 4 camadas (ADR UI-0013 · Constituição UI v2)

> **Princípio único:** uma camada superior **herda** das inferiores e **nunca contradiz**. Módulo não cria token, padrão não muda shell, shell não toca fundações sem ADR.

```
┌─────────────────────────────────────────────────────┐
│  4 · MÓDULO          Crm · Fiscal · Financeiro...   │  ← varia
├─────────────────────────────────────────────────────┤
│  3 · PADRÃO DE TELA  PT-01 Lista · PT-02 Form...    │  ← 5-7 templates fixos
├─────────────────────────────────────────────────────┤
│  2 · SHELL           AppShellV2 · PageHeader...     │  ← 1× pro app inteiro
├─────────────────────────────────────────────────────┤
│  1 · FUNDAÇÕES       Tokens (cor · tipo · espaço)   │  ← imutável (ADR)
└─────────────────────────────────────────────────────┘
```

| Camada | Quem pode mudar | Não pode mudar |
|---|---|---|
| **4 · Módulo** | colunas, regras, dados, copy de negócio | nada da camada 1-3 |
| **3 · Padrão de Tela** | slots internos, estados, variantes documentadas | tokens, shell |
| **2 · Shell** | item ativo no sidebar, breadcrumb no header | tokens |
| **1 · Fundações** | adicionar token via **ADR explícita** | nada sem ADR |

**Hierarquia de override (conflito):** Fundações > Shell > PT > Módulo (a de baixo vence).

---

## Onde está cada coisa

### Camada 1 · Fundações (tokens canônicos)

- **Tokens reais** → [`resources/css/inertia.css`](../../../resources/css/inertia.css) (light/dark, slate+blue) + [`cockpit.css`](../../../resources/css/cockpit.css) (oklch hues sidebar)
- **Tailwind v4 setup** → [UI-0001](adr/ui/0001-tailwind-4-como-fundacao-css.md)
- **shadcn/ui** → [UI-0002](adr/ui/0002-shadcn-ui-copy-paste-em-vez-de-npm.md)
- **lucide-react** (icones únicos) → [UI-0003](adr/ui/0003-lucide-react-como-unica-iconografia.md)
- **Dark mode user-toggle** → [UI-0004](adr/ui/0004-dark-mode-por-usuario-via-classe-html.md)

### Camada 2 · Shell (chrome universal)

- **AppShellV2** → [`resources/js/Layouts/AppShellV2.tsx`](../../../resources/js/Layouts/AppShellV2.tsx)
- **PageHeader canon** → [`Components/shared/PageHeader.tsx`](../../../resources/js/Components/shared/PageHeader.tsx) + [ADR 0182](../../decisions/) + skill [`pageheader-canon`](../../../.claude/skills/pageheader-canon/SKILL.md)
- **Cockpit layout-mãe** → [UI-0008](adr/ui/0008-cockpit-layout-mae-do-erp.md)
- **Sidebar light padrão** → [UI-0009](adr/ui/0009-cockpit-sidebar-light-padrao.md) (Wagner-explícito 2026-05-04) · **proposal de desempate v2 dark** → [proposals/2026-05-24-sidebar-dark-vs-light.md](../../decisions/proposals/2026-05-24-sidebar-dark-vs-light.md)
- **Sidebar single-pane + user menu cascata** → [UI-0011](adr/ui/0011-sidebar-single-pane-cascata-user-menu.md)
- **Skill correlata** → [`sidebar-menu-arch`](../../../.claude/skills/sidebar-menu-arch/SKILL.md)

### Camada 3 · Padrão de Tela (templates)

- **PT-01 Lista** → [`padroes-tela/PT-01-Lista.md`](padroes-tela/PT-01-Lista.md) — 6 slots, 12+ telas aplicam
- **PT-02 Form/Drawer** → ainda não documentado · candidato pra próximo (drawer 760px já implementado em [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md), [ADR 0185](../../decisions/0185-drawer-760-canon-entidades-cadastrais.md))
- **PT-03 Detalhe** → não documentado
- **PT-04 Dashboard** → não documentado
- **PT-05 Configuração** → não documentado
- **Pattern operacional original** (vira PT-01) → [UI-0006](adr/ui/0006-padrao-tela-operacional.md)

### Camada 4 · Módulo (instâncias)

- Cada módulo vive em `Modules/<X>/` + `resources/js/Pages/<X>/`
- Charter `.charter.md` ao lado de cada `.tsx` (skill `charter-first` Tier A)
- Listagem das 12 telas-lista que seguem PT-01 → ver [`PT-01-Lista.md` seção "Aplicado em"](padroes-tela/PT-01-Lista.md#aplicado-em-estado-real)

### Camada 5 · Protocolo (avaliação e governança)

- **KB-9.75** método de avaliação → [`memory/requisitos/_DesignSystem/audits/`](audits/) + skill [`module-completeness-audit`](../../../.claude/skills/module-completeness-audit/SKILL.md)
- **Module Grade v4** (CI gate) → `governance/module-grades-baseline.json` + skill [`module-grades-gate`](../../../.claude/skills/module-grades-gate/SKILL.md)
- **PRE-MERGE-UI checklist** → [`PRE-MERGE-UI.md`](PRE-MERGE-UI.md) ← anti-regressão por camada
- **Loop Design↔Code formal** → [`prototipo-ui/PROTOCOL.md`](../../../prototipo-ui/PROTOCOL.md) (7 fases)

### Camada 6 · Decisões (ADRs)

- **ADRs UI** → [`adr/ui/`](adr/ui/) (UI-0001 a UI-0013 hoje)
- **ADRs raiz cross-cutting** → [`memory/decisions/`](../../decisions/)
- **Propostas em discussão** → [`memory/decisions/proposals/`](../../decisions/proposals/)

---

## Como ler / como pedir (vocabulário canônico)

| Pedido vago ❌ | Pedido determinístico ✅ |
|---|---|
| "faz uma tela de cobranças" | "aplica **PT-01 Lista** no módulo Recorrente, colunas X/Y/Z" |
| "muda a cor pra ficar igual fiscal" | "usa **token Fundações** `--origin-FIN-bg`" |
| "o sidebar tá diferente" | "**replicar Shell-Sidebar** (AppShellV2) sem modificar" |
| "deixa bonito" | "**aplica KB-9.75** e me mostra o score + top 3 gaps" |

Agente segue a regra-mestre da [UI-0013](adr/ui/0013-constituicao-ui-v2-camadas.md): **se pedido não aponta camada+artefato+mudança, pergunta antes de implementar.**

---

## Ordem de leitura pra novo agente (humano ou IA)

1. **[adr/ui/0013-constituicao-ui-v2-camadas.md](adr/ui/0013-constituicao-ui-v2-camadas.md)** — ADR-mãe (10 min)
2. **[padroes-tela/PT-01-Lista.md](padroes-tela/PT-01-Lista.md)** — template canônico (10 min)
3. **[PRE-MERGE-UI.md](PRE-MERGE-UI.md)** — checklist antes de mergear (3 min)
4. **[ARCHITECTURE.md](ARCHITECTURE.md)** — stack visual (5 min)
5. **[SPEC.md](SPEC.md)** — regras R-DS-001..R-DS-016 (10 min)
6. **[CHANGELOG.md](CHANGELOG.md)** — últimas 3 entradas (5 min)
7. **[adr/ui/](adr/ui/)** UI-0001..UI-0012 — se precisar de contexto histórico

---

## Índice de docs nesta pasta

- **[adr/ui/0013-constituicao-ui-v2-camadas.md](adr/ui/0013-constituicao-ui-v2-camadas.md)** ← ADR-mãe (Constituição v2)
- **[padroes-tela/PT-01-Lista.md](padroes-tela/PT-01-Lista.md)** ← camada 3 primeiro template
- **[PRE-MERGE-UI.md](PRE-MERGE-UI.md)** ← checklist anti-regressão
- **[ARCHITECTURE.md](ARCHITECTURE.md)** — stack visual (Tailwind 4, shadcn/ui, lucide, Inertia)
- **[SPEC.md](SPEC.md)** — regras R-DS-001..R-DS-016
- **[CHANGELOG.md](CHANGELOG.md)** — evolução do DS (v0.1 → v0.6)
- **[GLOSSARY.md](GLOSSARY.md)** — termos (token, variant, utility, primitive)
- **[adr/ui/](adr/ui/)** — 13 ADRs UI numeradas
- **[audits/](audits/)** — auditorias módulo por módulo (KB-9.75)
- **[from-claude-design/](from-claude-design/)** — handoffs externos do Claude Design

## Módulos impactados

Todos que usam Inertia+React (atualmente 100%). Quando adicionar nova tela, consultar antes ADRs aqui + ler PT-01 + rodar PRE-MERGE.
