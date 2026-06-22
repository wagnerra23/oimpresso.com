# CLAUDE_DESIGN_BRIEFING.md — pra Claude Design (Cowork ou plugin local)

> ## ⚠️ CANON ATUAL (2026-05-30) — corrige §2/§4/§12 abaixo
> Onde divergir, vence isto:
> 1. **Entrada única:** **[`memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md`](../memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md)** — índice mestre + regra de ouro + goldens PT-01..PT-05.
> 2. **Cor = roxo** `primary` `oklch(0.55 0.15 295)` (ADR 0235). O §4 abaixo (shadcn genérico/stone-50, sem roxo) está defasado — **zero `blue-*` de marca**.
> 3. **Posicionamento:** ERP **multi-vertical** (ADR 0121); **Larissa = vestuário**, não gráfica/comunicação visual. O §2 abaixo ("setor de comunicação visual") está errado.
> 4. **Crie DENTRO do que existe:** copie o golden do arquétipo + rode o PRE-FLIGHT; nunca invente paleta/persona/componente (M-AP-6) nem repita anti-padrão F3.
> 5. **Fonte de design = este projeto, NÃO Figma** ([ADR 0299](../memory/decisions/0299-figma-nao-e-fonte-de-design.md)): protótipo Cowork + Design System + charter. Figma/Notion/screenshot/link **não** são fonte (só com Wagner explícito). No lado Cowork não há hook que bloqueie — é disciplina sua.

> Este arquivo te dá o contexto do oimpresso pra você produzir protótipo + crítica que **não invente paleta nem persona**.

## 1. Quem você é neste loop

`[CC]` (Cowork) ou `[CD]` (Claude Design plugin local). Sua função:

- **[CC]:** desenhar protótipo visual rápido em `<tela>/page.tsx` (React + Tailwind, mock de dados ok)
- **[CD]:** rodar `design:design-critique`, `design:design-system`, `design:ux-copy` sobre o protótipo

Wagner aprova. Claude Code (`[CL]`) traduz pra Inertia real. Você NÃO escreve Inertia.

## 2. O produto em 3 frases

oimpresso = **ERP brasileiro pra setor de comunicação visual** (gráficas rápidas, plotters, fachadas, brindes). Cliente piloto = **ROTA LIVRE** (Larissa, monitor 1280px, 99% do volume). Diferencial vs concorrentes (Iugu/Asaas/Vindi) = NFe automática + IA conversacional + governança formal.

Por que existe: [memory/why-oimpresso.md](../memory/why-oimpresso.md).

## 3. Personas

| Persona | Quem | Contexto de uso | Prioridade visual |
|---|---|---|---|
| **Larissa** | dona+operadora ROTA LIVRE | balcão da gráfica, monitor 1280×1024, intercala com cliente | densidade alta, KPIs gigantes legíveis a 1m, atalhos teclado |
| **Wagner** | dono oimpresso | escritório, monitor 1440×900, multitarefa | dashboards, governança, métricas de saúde |
| **Técnico Repair** | operador chão de fábrica | tablet/celular, mãos sujas | mobile-first, touch targets ≥44px, status visíveis a 2m |
| **Eliana[E]** | financeiro | escritório, foca extrato + boletos | tabelas densas, filtros poderosos, export CSV/Excel |
| **Iniciante [L]** | dev novo | dev | UI clara que ensina o domínio |

## 4. Tokens canônicos (não invente paleta)

### Cores semânticas Tailwind (shadcn)
- `bg-background` / `text-foreground` — base
- `bg-primary` / `text-primary-foreground` — ações principais
- `bg-secondary` — neutro de apoio
- `bg-muted` / `text-muted-foreground` — desfocado, secundário
- `bg-accent` / `text-accent-foreground` — destaque sutil
- `bg-destructive` / `text-destructive-foreground` — ações destrutivas
- `bg-card` / `text-card-foreground` — superfícies elevadas

### Cores warm (semântica de status)
- `bg-emerald-50` / `text-emerald-700` — sucesso, "pago", "autorizado"
- `bg-amber-50` / `text-amber-700` — warning, "pendente", "vencendo"
- `bg-rose-50` / `text-rose-700` — erro, "rejeitado", "atrasado"
- `bg-sky-50` / `text-sky-700` — info, "novo", "rascunho"

**NÃO USE** cores por opacity (`bg-amber-500/10`) — preferimos escala semântica warm (`bg-amber-50`).

### Tipografia
- `text-xs` (12px) labels, abas, badges
- `text-sm` (14px) corpo de tabela, formulário
- `text-base` (16px) corpo padrão
- `text-lg` (18px) — `text-2xl` (24px) títulos seção
- `text-3xl` (30px) — `text-4xl` (36px) KPI value (números grandes)
- `tracking-widest` em uppercase labels (`KPI LABEL`)

### Espaçamento
- `gap-3` (12px), `gap-4` (16px), `gap-6` (24px) — entre cards
- `p-4` (16px), `p-6` (24px) — interno de cards
- `space-y-4` / `space-y-6` — pilhas verticais

### Sombras
- `shadow-sm` em cards (default) — sutil mas presente
- `shadow-md` em drawers / dialogs
- Evitar `shadow-lg`+ — fica "tutorial-shadcn"

### Radius
- `rounded-md` (6px) — cards, buttons, inputs (default)
- `rounded-lg` (8px) — drawers, dialogs, popovers
- `rounded-full` — badges, avatars, status dots
- Evitar `rounded-xl+` — fica "tutorial-shadcn"

### Animação
- `transition-colors duration-150` em hovers
- `transition-transform duration-200 ease-out` em drawers
- `animate-pulse` apenas skeleton loading
- Sem animação > 300ms (exceto loading)

### Foco (obrigatório — Larissa usa teclado pesado)
- `focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2`
- Em todo interativo (button, input, link, custom)
- Não usar `outline-none` sem substituir

## 5. Padrão Cockpit V2 ([ADR 0110](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md))

```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Sidebar    │  Header sticky                          │
│  - Dashboard      │   Título · Breadcrumb · Ações primárias│
│  - Sells          │  ─────────────────────────────────────  │
│  - Repair         │   Abas (text-xs) · Filtros sync URL    │
│  - ...            │  ─────────────────────────────────────  │
│                   │                                          │
│                   │   Body — Cards bg-background +          │
│                   │   shadow-sm + p-6                       │
│                   │                                          │
│                   │   ─────────────────────────────────────  │
│                   │   Footer sticky · ações secundárias    │
└───────────────────┴──────────────────────────────────────────┘
                            ⬑ Drawer (Sheet) lateral abre detalhe
```

Referências canon:
- `os-page.jsx` — list+detail master-detail
- `tasks.jsx` — Kanban
- `chat.jsx` — chat conversacional
- `viewers.jsx` — leitura passiva (KPI grids)

(Esses arquivos vivem em `memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/`.)

## 6. 15 dimensões obrigatórias (compatível com `mwart-comparative` V4)

### A. Estrutura
1. Layout (header/sidebar/topnav/body/footer)
2. Hierarquia visual (1 ação primária, 2-3 secundárias)
3. Densidade (espaço vs informação)
4. Iconografia (lucide-react, sem emoji)
5. Estados (default/hover/focus/active/disabled/loading/empty/error)
6. Atalhos teclado (J/K, /, Esc, ⌘+Enter)
7. Persistência (localStorage `oimpresso.<mod>.<tela>.*`)
8. Componentes shared reusados

### B. Estado da arte
9. Tipografia numérica (KPI px exatos, label tracking-widest)
10. Espaçamento numérico (p-4 ≠ p-6 ≠ p-8)
11. Cores semânticas warm
12. Microinterações (hover transition, backdrop-blur, shadow-sm)
13. Referência visual Wagner aprovou
14. Benchmark externo (form: Stripe Checkout · list: Linear · dashboard: Vercel · inbox: Front)
15. Persona priorização (top 3 decisões mudadas pela persona)

## 7. Proibições visuais

- ❌ **CTA "Fale no WhatsApp"** em landing pública — Wagner explicitou ([memory/comparativos](../memory/comparativos/) referência concorrentes Com. Visual)
- ❌ **Modal full-screen** pra detalhe de item — usar `<Sheet>` (drawer lateral)
- ❌ **Inglês em UI cliente-facing** — PT-BR sempre
- ❌ **Cores fora dos tokens** acima — não invente paleta
- ❌ **Densidade Bootstrap-default** (muito espaço entre coisas) — Larissa precisa ver muito de uma vez
- ❌ **Emoji em UI productiva** — só lucide-react icons
- ❌ **Texto explicativo verbose** ("Bem-vindo! Aqui você pode...") — direto ao ponto

### 7.1 Estrutura & evolução (artefato de design)

> Espelha pra **design** as duas regras canônicas de [`memory/proibicoes.md`](../memory/proibicoes.md) — referencia, **não duplica**: *não-duplicação* ("NUNCA criar arquivo … sem `Glob`/`Grep` … edita o existente") e *append-only* ("ADRs CANON são append-only"). Origem: 2 HTMLs de governança duplicados no Cowork (2026-06-01).

- ❌ **Criar `.html`/artefato de design novo sem checar duplicação antes** (`Glob`/grep do tema). Tela/módulo/**variação** de ERP = rota ou Tweak (`useTweaks`) no **layout único do shell**, nunca arquivo novo. Relatório/avaliação *meta* = **1 tema = 1 doc**; se já existe irmão, **edita o existente** — **nunca `vN.html`**. _(L-21.)_
- ❌ **Mover/consolidar artefato sem deixar trilha.** Todo artefato vivo (HTML de app/relatório, doc canônico) carrega **no fim** um bloco `Trilha do tempo` **append-only** (`data · o que mudou · o que supersedeu · → pra onde o anterior foi arquivado`); ao mover, deixa **lápide** na origem ou em `_arquivo/<pasta>/INDEX.md` (origem→destino + substituto). Nada some sem rastro legível. _(Concretiza L-07 · L-22.)_

## 8. Output esperado por fase

### F1 (você produz protótipo)
- `prototipos/<tela>/page.tsx` — React + Tailwind, mock data ok, **inglês ou PT-BR ok** (CL traduz)
- `prototipos/<tela>/COMPARISON.md` — 15 dimensões preenchidas, ≥6 obrigatórias

### F1.5 (você roda critique)
- `prototipos/<tela>/critique-score.json`:

```json
{
  "score": 85,
  "first_impression": "limpo, hierarquia clara",
  "strengths": ["KPIs gigantes", "abas text-xs corretas", "shadow-sm sutil"],
  "weaknesses_priority": [
    {"severity": "high", "issue": "...", "fix": "..."},
    {"severity": "med", "issue": "...", "fix": "..."}
  ],
  "benchmark_comparable": "Linear",
  "linear_vercel_stripe_test": "parece feita pelo time da Vercel: SIM"
}
```

### F3.5 (você roda accessibility)
- `prototipos/<tela>/a11y-report.md` — WCAG 2.1 AA com checklist

## 9. Refs

- [PROTOCOL.md](PROTOCOL.md) — regras formais
- [memory/why-oimpresso.md](../memory/why-oimpresso.md) — produto
- [memory/what-oimpresso.md](../memory/what-oimpresso.md) — stack
- [ADR 0110 — Cockpit V2](../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0107 — Visual gate F1.5](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
