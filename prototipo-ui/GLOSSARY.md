# GLOSSARY.md — termos do loop Cowork ↔ Code

> Termos curtos referenciados em `PROTOCOL.md`, `COWORK_NOTES.md`, ADRs.

## Pessoas / sistemas

| Sigla | Significado |
|---|---|
| **[W]** | Wagner Junio Andrade da Silva — dono oimpresso, decide |
| **[CC]** | Claude Cowork — Anthropic Cowork web app, gera protótipo visual |
| **[CD]** | Claude Design — plugin local OU Cowork rodando design skills |
| **[CL]** | Claude Code — instância no terminal local rodando no repo Laravel |
| **[CA]** | Claude Accessibility — Claude Code rodando `design:accessibility-review` |
| **[W2]** | Wagner aprovador síncrono — quando Wagner precisa olhar screenshot real |
| **[F]** | Felipe — colaborador (presente na sigla histórica MWART, não-ativo no loop UI atual) |
| **[M]** | Manus — IA externa de alta capacidade, usada raramente |
| **[L]** | Iniciante / Estagiário — persona pra quem código tem que se explicar sozinho |
| **[E]** | Eliana — financeiro |

## Fases do loop

| Sigla | Significado |
|---|---|
| **F0 BRIEF** | Wagner escreve pedido em `COWORK_NOTES.md` |
| **F1 DESIGN** | [CC] gera protótipo Cowork → exporta zip → [CL] unzipa em `prototipos/<tela>/` |
| **F1.5 CRITIQUE** | [CD] roda `design-critique` → `critique-score.json` (score 0-100) |
| **F2 SCREENSHOT** | [W2] aprova screenshot real (não tabela) |
| **F3 CODE** | [CL] traduz protótipo aprovado pra Inertia/React real |
| **F3.5 A11Y** | [CA] roda `accessibility-review` → `a11y-report.md` (WCAG 2.1 AA) |
| **F4 MERGE** | [W2] mergeia PR (após F3.5 passou) |

## Conceitos

### Design Critique Score
Número 0-100 produzido por `design:design-critique`:
- **80-100:** ok, segue pra F2
- **70-79:** 1 round refator obrigatório
- **<70:** discussão obrigatória, possível redirect

Limites recalibram após 10 telas (p50/p90 reais).

### Critique Override
Wagner autoriza pular F1.5 com `/design-override <razão>` em `COWORK_NOTES.md`. Gera ADR `lifecycle: historical` per-tela.

### Screenshot Override
Wagner autoriza pular F2 com `/screenshot-override <razão>` quando já aprovou no Cowork direto.

### A11y Override
Wagner autoriza pular F3.5 com `/a11y-override <razão>` em telas internas superadmin não-cliente-facing.

### Backpressure
Limite de 2 telas simultâneas em F3 (`[CL]` perde foco se >2 PRs abertos no mesmo módulo).

### Cockpit V2
Padrão visual canônico ([ADR 0110](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)) — sidebar + header sticky + body cards + footer sticky + drawer lateral pra detalhe.

### MWART Process
Processo canônico ([ADR 0104](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) — F1 a F4. Loop UI atual é refinamento dele.

### Cowork Export
Zip que [CC] gera com `prototipo-ui/` — [CL] consome via PR `label: cowork-export` (sem build, sem testes).

### Charter
`<Tela>.charter.md` — documento de design: contexto + decisões + 15 dimensões. Vive ao lado do `.tsx` em `resources/js/Pages/`.

### 15 Dimensões
Skill `mwart-comparative` V4 — checklist de qualidade visual:
- A. Estrutura: layout, hierarquia, densidade, iconografia, estados, atalhos, persistência, componentes shared
- B. Estado da arte: tipografia numérica, espaçamento numérico, cores semânticas, microinterações, ref Wagner, benchmark externo, persona priorização

## Comparáveis canônicos (benchmark visual)

Referências válidas em `COMPARISON.md > dimensão 14`:

| Comparável | Tipo de tela | Por que |
|---|---|---|
| **Linear** | list+detail (Issues) | densidade alta, atalhos teclado, tipografia numérica |
| **Stripe** | dashboards, forms (Checkout) | hierarquia limpa, KPIs grandes, microinterações sutis |
| **Vercel** | dashboards, deploy logs | dark mode forte, cards `shadow-sm`, espaçamento numérico |
| **Mercury** | financial (transactions, balance) | warm palette, KPI gigante, densidade ROTA LIVRE-friendly |
| **Front** | inbox / conversation list | 3-col layout, threading, density labels — direto pra Tasks/Repair-inbox |
| **Pylon** | B2B support | tickets+SLA+queue — direto pra Repair (chão de fábrica → técnico operativo) |
| **Attio** | CRM list+detail | enxuto, custom views, filtros sync URL — direto pra Cliente/Index |
| **Cron** | densidade temporal | calendar/scheduling com hierarquia hora — direto pra Ponto/Marcacoes |

**Excluir** `Notion` pra telas Larissa (densidade insuficiente). Notion serve só pra docs/wiki interno.

## Onde NÃO buscar comparável

- ❌ Bootstrap/Material default — too generic
- ❌ Tutorial shadcn (rounded-xl+, gradient-pink) — too "demo"
- ❌ ERPs concorrentes brasileiros (Bling/Tiny) — too crowded, sem hierarquia
- ❌ Iugu/Asaas/Vindi (financial competitors) — Wagner explicitamente NÃO quer espelhar
- ❌ Notion — densidade incompatível com Larissa
