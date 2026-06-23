# OS — Refino F1 (Cowork) · COMPARISON 15 dimensões

> **Tela:** Ordens de Serviço · `Officeimpresso/Os/Index`
> **Estado anterior:** sem critique formal (Fase 2 piloto entregue em 2026-04-27)
> **Refino entregue:** 2026-05-27 · Cowork
> **Alvo F1.5 [CD]:** ≥ 90 (A+)
> **Princípio guia:** **fonte de verdade única** — usar o mesmo `cli-pageheader` + `cli-moduletopnav` do canon PT-01 que `Clientes` já consome. Zero invenção visual de paleta/radius/animação.

---

## Resumo das mudanças

| # | Mudança | Onde | Por quê |
|---|---|---|---|
| 1 | **PageHeader canon** (`cli-pageheader`) — icon box + título + subtítulo dinâmico com stats inline + ações | `os-page.jsx` | Mesmo header de `Clientes` (PT-01). Sub-tabs saem do toolbar e viram nav. |
| 2 | **ModuleTopNav canon** (`cli-moduletopnav`) com 9 fases-filtro + contador | `os-page.jsx` | Padrão de sub-nav ghost. Igual `Clientes`. Toolbar fica só com search+select. |
| 3 | **Mini-stepper FSM inline na linha** (`<FsmStepper domain="os" variant="dots-inline">`) substituindo badge de etapa | `os-page.jsx` `OsRow` | Gargalo declarado pelo Wagner — scan visual instantâneo do pipeline em cada OS. Padrão Linear Issues / ServiceTitan. |
| 4 | **FSM full-stepper no drawer detalhe** substituindo `.os-stages-flow` ad-hoc | `os-page.jsx` `OsDetailDrawer` | Reusa o stepper canônico cross-módulo. Remove 18 linhas de markup custom. |
| 5 | **Domain `os` no FSM canônico** estendido pra 6 fases reais + terminal `cancelado` | `fsm-stepper.jsx` | Antes tinha 5 fases genéricas (Aberta/Produção/Acab/Exped/Entregue) que não batiam com `OS_STAGES`. Agora 6 fases reais: Orçado → Aprovação → Produção → Acabamento → Expedição → Entregue. |
| 6 | **Helper `osFsmStage(stageId)`** mapeando `OS_STAGES` (8 ids) → índice no FSM (6 fases) + terminal | `fsm-stepper.jsx` | Padrão `finFsmStage` / `boletoFsmStage`. Mantém data layer separado do componente visual. |
| 7 | **Toolbar enxuto** — só search + select + total | `os-page.jsx` + `styles.css` | Sub-tabs migraram pro header canônico. Toolbar deixa de ser zona ambígua. |
| 8 | **Hue FSM os = 220** (alinhado com `--accent` do shell) | `fsm-stepper.jsx` | Wagner pediu tema único. Antes era hue 60 (warm yellow) — agora cool blue do shell. |

---

## 15 dimensões (CLAUDE_DESIGN_BRIEFING §5)

| Dimensão | Antes | Depois | Δ |
|---|---|---|---|
| **1. Identidade visual** | Genérica (cinza shell, sem assinatura) | **Padrão único cross-módulo** — cli-pageheader (icon box accent-soft + título 22px + subtítulo tabular) | +2 |
| **2. Hierarquia tipográfica** | h1 24px solto, subtítulo descritivo genérico | h1 22px canônico + subtítulo com `<strong>` tonalizado (default/danger/warn) — KPIs no respiro do título | +1.5 |
| **3. Densidade** | 4 cards de stats chapados + toolbar com 9 tabs + table | Stats absorvidos no subtítulo + sub-nav separada → +1 linha de scan, −1 zona morta | +1 |
| **4. Pipeline visual (FSM)** | Badge texto único ("Aprovação arte") | **6 bolinhas inline** mostrando posição no pipeline + label da fase atual em mono | **+3** (gargalo principal) |
| **5. Domínio (FSM canônica)** | Stages locais em `OS_STAGES` + render custom em 2 lugares | FSM domain `os` canônico (cross-módulo) + helper `osFsmStage` derivando do stageId | +2 |
| **6. Drawer detalhe** | Stages-flow custom (18 linhas markup ad-hoc) | `<FsmStepper variant="full-stepper">` reusado | +1.5 (consistência) |
| **7. ⌘K / atalhos** | Sem ⌘K rico | Não implementado nesta rodada (declarado por Wagner como gargalo secundário) | 0 |
| **8. Saved views / bulk** | Bulk básico (mudar etapa, atribuir, exportar) | Mantido como estava — não foi gargalo declarado | 0 |
| **9. Interação (cliques scan)** | Click linha → drawer | Mantido + leitura do pipeline sem abrir drawer (mini-stepper inline) | +1 |
| **10. Empty states** | 4 estados contextuais (atrasadas / arte / produção / filtros) | Mantidos — já estavam fortes | 0 |
| **11. Color tokens** | Hue 60 inventado pro stepper OS | Hue 220 = `--accent` do shell · zero hue inventado | +1 |
| **12. Radius/shadow** | rounded-md padrão shell | Idem, sem mudança | 0 |
| **13. Animação** | `slidein .2s cubic-bezier` no drawer | Idem · sem mudança | 0 |
| **14. Foco/A11y** | `aria-current` faltava nas sub-tabs | `aria-current="page"` na sub-nav · `aria-label="Etapa"` na nav | +1 |
| **15. Print** | `os-page-h-r` escondido | `cli-pageheader` segue regra de print do Clientes (já no clientes-page.css) | +0.5 |

---

## Projeção de nota

| Critério (peso) | Antes | Depois |
|---|---|---|
| **Estrutura** (25%) | 8.5 | **9.5** — header canônico + sub-nav canônica + toolbar enxuto |
| **Visual** (25%) | 8.0 | **9.5** — padrão único cross-módulo (Clientes ↔ OS) + mini-stepper |
| **Domínio** (25%) | 8.5 | **9.5** — FSM canônica `os` (6 fases reais) + helper + terminal cancelado |
| **Interação** (25%) | 8.5 | **9.5** — scan pipeline inline + drawer com stepper canônico |
| **Média ponderada** | **8.4** | **9.5** |

**Alvo F1.5:** ≥ 90 (A+) · **Esperado:** 92-94.

---

## O que foi **conscientemente excluído** desta rodada

- **⌘K rico** (gargalo secundário) — escopo separado, vale uma rodada própria
- **Avatar + comissão do vendedor** — é gargalo de Vendas, não OS
- **Tweaks (Vista = Balcão/PCP/Arte)** — Wagner pediu tema único, não variantes
- **Saved views ▾** — bulk atual cobre 80% do uso; ▾ entra na próxima quando aparecer fricção real
- **Aprovar arte com pin de comentário** — gargalo secundário, fluxo atual (versões + decisão A/J/R) já funciona

---

## Arquivos no snapshot

```
prototipo-ui-patch/prototipos/os/
├── os-page.jsx        — fonte de verdade do componente (refinado)
├── data-os.jsx        — mock + OS_STAGES (inalterado)
├── fsm-stepper.jsx    — FSM canônica + os domain estendido + osFsmStage helper
├── COMPARISON.md      — este arquivo
└── PROMPT_PARA_CODE.md — prompt zero-touch pro Claude Code
```

Mudanças em `styles.css` documentadas no `PROMPT_PARA_CODE.md` (12 linhas, escopo `.os-table` + `.os-toolbar-l` + `.os-toolbar-total`).
