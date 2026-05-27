# ADR UI-0013 · Constituição UI v2 — hierarquia de 4 camadas (Fundações → Shell → Padrão de Tela → Módulo)

- **Status**: accepted
- **Data**: 2026-05-24
- **Aprovado em**: 2026-05-24 — Wagner explícito "eu aporvo"
- **Decisores**: Wagner, Claude Code (autor)
- **Categoria**: ui · estruturante · governança
- **Substitui**: nada (consolida + formaliza)
- **Substituído por**: —
- **Refs**:
  - Handoff Claude Design 2026-05-24 (Constituição UI v2)
  - [ADR UI-0008](0008-cockpit-layout-mae-do-erp.md) — Cockpit layout-mãe
  - [ADR UI-0006](0006-padrao-tela-operacional.md) — pattern operacional (vira PT-01)
  - [ADR UI-0011](0011-sidebar-single-pane-cascata-user-menu.md) — Shell sidebar
  - [ADR 0114](../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop Design↔Code
  - [ADR 0149](../../../decisions/0149-mwart-screen-pattern-reuse-cowork.md) — pattern reuse
  - [prototipo-ui/PROTOCOL.md](../../../../prototipo-ui/PROTOCOL.md)

## Contexto

12 ADRs UI (UI-0001 a UI-0012) decidiram peças individuais (Tailwind v4, shadcn, lucide, dark mode, Cockpit, sidebar, pageheader, etc) **sem uma camada-mãe explícita** declarando como elas se relacionam. Resultado: cada módulo novo precisa redescobrir o mapa lendo 12 ADRs + 6 RUNBOOKs + 4 skills.

Em 2026-05-24, Claude Design produziu a "Constituição UI v2" (sessão chat8, projeto Cowork) — handoff externo que **formaliza hierarquia de 4 camadas** com regra única: *uma camada superior herda e nunca contradiz a de baixo*. Inspiração: Atomic Design (Brad Frost), Design Tokens (W3C), Stripe/Linear/Carbon DS.

A v2 trouxe também:
- **Regra-mestre "não-gastar-tokens-com-pedido-vago"** — agente pergunta antes de codar se pedido não aponta camada+artefato+mudança
- **Vocabulário canônico de pedido** — tabela "vago → determinístico"
- **PT-01 Lista** documentado como template canônico de 6 slots
- **PRE-MERGE checklist por camada**
- **CHANGELOG raiz append-only**
- **ADRs retroativas curtas** (estilo Nygard de 4 seções)

O oimpresso JÁ tem peças mais maduras em algumas dimensões (loop Design↔Code formalizado em [PROTOCOL.md](../../../../prototipo-ui/PROTOCOL.md), CI Module Grades v4, MCP tools), mas **falta a hierarquia formal das 4 camadas** que torna pedido determinístico sem leitura de 12 ADRs.

Não há conflito com nenhuma ADR aceita exceto **sidebar dark vs light** ([ADR UI-0009](0009-cockpit-sidebar-light-padrao.md) light vs v2 dark) — registrado em [proposta separada](../../../../memory/decisions/proposals/2026-05-24-sidebar-dark-vs-light.md), Wagner desempata.

## Decisão

Adotar a **hierarquia de 4 camadas** como mental model canônico do DS:

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

**Princípio único:** uma camada superior **herda** das inferiores e **nunca contradiz**. Módulo não cria token, padrão não muda shell, shell não toca fundações sem ADR.

### Mapeamento das camadas pra estrutura atual do repo

| Camada | Local canônico no oimpresso | Status |
|---|---|---|
| **1 · Fundações** | `resources/css/inertia.css` + `cockpit.css` + tokens documentados em [ADR UI-0001](0001-tailwind-4-como-fundacao-css.md), [UI-0003](0003-lucide-react-como-unica-iconografia.md), [UI-0004](0004-dark-mode-por-usuario-via-classe-html.md) | maduro |
| **2 · Shell** | `resources/js/Layouts/AppShellV2.tsx` + `Components/shared/PageHeader.tsx` + `ModuleTopNav.tsx` + [UI-0008](0008-cockpit-layout-mae-do-erp.md), [UI-0011](0011-sidebar-single-pane-cascata-user-menu.md) | maduro |
| **3 · Padrão de Tela** | `memory/requisitos/_DesignSystem/padroes-tela/` (NOVO — esta ADR cria) + [UI-0006](0006-padrao-tela-operacional.md) vira PT-01 | a documentar |
| **4 · Módulo** | `Modules/<X>/` + `resources/js/Pages/<X>/` + `.charter.md` por tela | maduro |

### Regra-mestre · Pedido vago

**Antes de tocar código de qualquer camada, agente verifica se o pedido aponta:**

- ✅ **Camada** (Fundações / Shell / PT / Módulo)
- ✅ **Artefato** canônico aplicar
- ✅ **Mudança específica** (adicionar X · trocar Y por Z)

Se vago, agente **pergunta** antes de implementar. Já operacionalizado no oimpresso via skill `wagner-request-refiner` e agente `wagner-understand`. Esta ADR **eleva a tier-A do `CLAUDE.md`** raiz.

### Vocabulário canônico de pedido

| Pedido vago ❌ | Pedido determinístico ✅ |
|---|---|
| "faz uma tela de cobranças" | "aplica **PT-01 Lista** no módulo Recorrente, colunas: cliente, valor, status" |
| "muda a cor pra ficar igual fiscal" | "usa **token Fundações** `--origin-FIN-bg`" |
| "o sidebar tá diferente" | "**replicar Shell-Sidebar** (AppShellV2) sem modificar" |
| "deixa bonito" | "**aplica KB-9.75** e me mostra o score" |
| "moderniza" | (sem analogia — aponte camada+mudança) |

### Hierarquia de override (quando há conflito)

```
Fundações  >  Shell  >  Padrão de Tela  >  Módulo
   (cor)      (sidebar)     (PT-01)         (Fiscal)
   vence!     vence         vence           obedece
```

**Exemplo:** se `Modules/Fiscal/page.tsx` hardcoded `#3b82f6`, Fundações vence — refator obrigatório, ADR-0043 (OKLCH) é canônica.

### O que esta ADR NÃO decide (lacunas explícitas)

- ❌ PT-02 Form/Drawer · PT-03 Detalhe · PT-04 Dashboard · PT-05 Config — abrem ADR cada um quando ≥2 módulos precisarem
- ❌ Migração de 5 origin badges (OS/CRM/FIN/PNT/MFG) → 11 hues semânticos da v2 — abrir ADR específica se decidido
- ❌ Sidebar dark vs light — Wagner desempata via [proposta](../../../../memory/decisions/proposals/2026-05-24-sidebar-dark-vs-light.md)
- ❌ Voice & tone formalizado · iconografia stroke sizes · animação tokens — preencher só quando dor justificar

## Consequências

### Positivas

- **Pedido vira determinístico** — Wagner aponta camada+artefato, agente não inventa
- **Onboarding novo agente cai pra 1 ADR-mãe** + ler PT-01 — em vez de 12 ADRs UI + 6 RUNBOOKs
- **Conflito tem hierarquia explícita** de quem vence (Fundações > Shell > PT > Módulo)
- **CI Module Grades v4 ganha eixo de "respeita camada"** — gate futuro pode validar hardcoded color em arquivo de módulo
- **Compatível com loop Design↔Code já formal** (PROTOCOL.md 7 fases) — esta ADR é o "DNA estrutural" que o loop opera sobre
- **Append-only** — futura PT-02..PT-05 adicionam sem regredir esta

### Negativas

- **Custo de leitura inicial** — agente precisa ler PT-01 + esta ADR antes de tela nova (~10min). Mitigação: skill `mwart-process` referencia.
- **Risco de virar burocracia** se Wagner não usar o vocabulário canônico. Mitigação: regra-mestre pergunta antes de codar.
- **Conflito sidebar pendente** — esta ADR não resolve UI-0009 vs v2 dark. Wagner desempata em proposal separada.

### Neutras / a observar

- PT-01 documenta o que **já existe** (paridade Sells/Index, Cliente/Index, Compras/Index, Financeiro). Não introduz mudança visual.
- ADRs UI-0001 a UI-0012 permanecem aceitas — esta ADR é **aditiva**, não substitui nenhuma. Cada uma vira "implementação concreta" de uma camada.

## Estrutura criada por esta ADR

```
memory/requisitos/_DesignSystem/
├── README.md                    ← ATUALIZADO · índice 4 camadas
├── PRE-MERGE-UI.md              ← NOVO · checklist por camada antes de PR UI
├── padroes-tela/                ← NOVA PASTA · camada 3 docs
│   └── PT-01-Lista.md           ← NOVO · primeiro template canônico
├── CHANGELOG.md                 ← apendado · entrada v0.6.0
└── adr/ui/
    └── 0013-constituicao-ui-v2-camadas.md  ← este arquivo

memory/decisions/proposals/
└── 2026-05-24-sidebar-dark-vs-light.md     ← NOVO · Wagner desempata
```

## Aprovação · 2026-05-24

Wagner aprovou explicitamente ("eu aporvo") em 2026-05-24 + escolheu opção A no proposal de sidebar (manter UI-0009 light). Conflito formalizado em [ADR UI-0014](0014-sidebar-light-mantida-v2-parcial.md).

### Próximos passos pós-aprovação (não obrigatórios neste PR)

1. `CLAUDE.md` raiz ganha linha apontando esta ADR como **Tier A obrigatória** ao tocar UI
2. Skill `mwart-process` apenda referência à PT-01
3. Próximos PT (PT-02 Form, PT-03 Detalhe, etc) usam mesmo template de doc da PT-01

## Pegadinhas conhecidas

- **Não tocar tokens reais** (`cockpit.css`, `inertia.css`) só pra "alinhar com v2" sem ADR de migração específica. Esta ADR é estrutural, não troca cor.
- **Não criar PT-02..PT-05** especulativamente. Só quando ≥2 módulos pedirem o mesmo template (princípio da v2, alinha com Anthropic skill-creator).
- **Não conflitar com UI-0009** unilateralmente. Sidebar light é decisão Wagner-explícita 2026-05-05 — sidebar dark da v2 é alternativa registrada em proposal, não fait accompli.
