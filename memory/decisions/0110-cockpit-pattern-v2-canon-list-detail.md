---
slug: 0110-cockpit-pattern-v2-canon-list-detail
number: 110
title: "Cockpit Pattern V2 — list+detail canônico para todas as migrações MWART (header + KPIs + pills + drawer)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-08'
quarter: 2026-Q2
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0108-regressao-visual-pest-browser-tier-2
  - 0109-claude-design-plugin-integrado-processo-mwart
emends:
  - '0107'
pii: false
---

# ADR 0110 — Cockpit Pattern V2 (list+detail) canônico para MWART

**Status:** ✅ Aceita
**Data:** 2026-05-08
**Decisão por:** Wagner Rocha
**Emenda:** ADR 0107 (visual gate F1.5) — adiciona pattern UI obrigatório
**Não supersede:** ADRs 0094, 0104, 0109

---

## Contexto

Sequência de 5 PRs (#252, #254, #255, #257, #258, #259, #260, #261) na Sells/create + Sells/index gerou o **Cockpit Pattern V2** após Wagner aprovar o gold-standard "Officeimpresso/Ordens de Serviço" do plugin Claude Design Anthropic (claude.ai/design).

PRs intermediários falharam em alcançar paridade visual porque tentaram patchear Blade legacy (PR #260) — Wagner reportou: *"parece ter divergência do exemplo prático"*. Solução só veio com migração completa Inertia + drawer (PR #261 + hotfix `0b5a09d5`).

## Decisão

**Cockpit Pattern V2** é o **pattern canon obrigatório** pra todas migrações MWART de páginas do tipo "lista de entidades transacionais" (vendas, compras, OSes, despesas, contas, clientes, produtos, etc).

Tentar paridade visual via patch em Blade legacy é **proibido** — só replicar dentro de AppShellV2/Inertia.

### Anatomia canônica (5 partes)

```
┌─────────────────────────────────────────────────┐
│ 1. AppShellV2 (sidebar + topnav inline)         │
├─────────────────────────────────────────────────┤
│ 2. Header                                       │
│    h1 text-2xl + subtitle + Nova X (1 botão)    │
│    3 KPIs cards (Abertas / Atrasadas / Total)   │
│    5 filter pills rounded-full + counter        │
├─────────────────────────────────────────────────┤
│ 3. Tabela limpa                          ┌────┐ │
│    data | nº | cliente 2L | $ | $ | bdg  │ 4. │ │
│    [select row → bg-blue-50]             │ Sh │ │
│                                          │ ee │ │
│                                          │ t  │ │
│                                          │drw │ │
│                                          └────┘ │
├─────────────────────────────────────────────────┤
│ 5. Footer sticky (form pages — opcional)        │
└─────────────────────────────────────────────────┘
```

### Tipografia canônica

| Elemento | Classe | Px |
|---|---|---|
| h1 página | `text-2xl font-semibold tracking-tight` | 24 |
| Subtitle | `text-sm text-muted-foreground leading-relaxed` | 14 |
| KPI label | `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground` | 11 |
| KPI value | `text-4xl font-semibold tabular-nums` | 36 |
| Filter pill | `text-xs font-medium` | 12 |
| Pill counter | `text-[10px] tabular-nums` | 10 |
| Tabela th | `text-[11px] font-semibold uppercase tracking-wider text-muted-foreground` | 11 |
| Tabela td | `text-sm` | 14 |
| Badge | `text-[11px] font-medium` | 11 |
| Drawer mini-KPI label | `text-[10px] font-semibold uppercase tracking-wider` | 10 |
| Drawer section title | `text-[10px] font-semibold uppercase tracking-widest` | 10 |

### Cores semânticas (paleta canon)

| Tom | Light | Dark | Uso |
|---|---|---|---|
| **Rose** (danger) | `bg-rose-50 text-rose-700 border-rose-200` | `bg-rose-950/40 text-rose-300` | Atrasos, urgentes, KPI Atrasadas card destaque |
| **Emerald** (success) | `bg-emerald-50 text-emerald-700 border-emerald-200` | `bg-emerald-950/40 text-emerald-300` | Pago, concluído, ✓ pagamentos |
| **Amber** (warning) | `bg-amber-50 text-amber-700 border-amber-200` | `bg-amber-950/40 text-amber-300` | Saldo devedor, frete pendente, mini-KPI tone warning |
| **Blue** (info/active) | `bg-blue-50 text-blue-700 border-blue-200` | `bg-blue-950/50 text-blue-300` | Pill ativo, parcial, linha selecionada |

Red dot bullet pra urgência: `<span class="h-2 w-2 rounded-full bg-rose-500" />`.

### Filter pills canon (NÃO tabs border-bottom)

```tsx
<button
  className={
    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
    (isActive
      ? danger
        ? 'bg-rose-100 text-rose-800'
        : 'bg-blue-50 text-blue-700'
      : danger
        ? 'bg-rose-50/60 text-rose-700 hover:bg-rose-100'
        : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
  }
>
  <Icon size={13} />
  {label}
  {count > 0 && (
    <span className="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums bg-background">
      {count}
    </span>
  )}
</button>
```

### Drawer SaleSheet pattern

- `<Sheet>` shadcn `side="right"` `w-full sm:max-w-xl`
- Conteúdo `flex flex-col p-0 overflow-hidden` (não tem padding default)
- Estrutura: SheetHeader (badges + título + customer line) → scroll body (4 mini KPIs grid + sections) → footer sticky ações
- Mini KPIs `grid grid-cols-4 gap-3` com tone neutral/success/warning
- Sections com `text-[10px] uppercase` heading + ícone lucide size 11

### Endpoints REST pattern (Inertia + drawer)

Pra cada migração list+detail, criar 2 endpoints:

```php
// 1. Lista JSON minimalista (alimenta tabela Inertia)
Route::get('/{entity}-list-json', [EntityController::class, 'inertiaList']);
// 8 campos por linha + booleanos derivados (is_overdue, is_urgent)

// 2. Detalhe JSON (alimenta drawer)
Route::get('/{entity}/{id}/sheet-data', [EntityController::class, 'sheetData']);
// Eager load relacionamentos minimalistas, return arrays serializados
```

Permission scope **explícito** dentro do método (defesa em profundidade — não confia só no global scope tenant).

### Pattern campos UltimatePOS — cuidado com colunas virtuais

`total_paid` em `transactions` **não existe como coluna**. UltimatePOS calcula via subquery:

```php
\DB::raw('(SELECT COALESCE(SUM(IF(tp.is_return = 0, tp.amount, tp.amount * -1)), 0) FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id) as total_paid')
```

Mesmo padrão pra: `total_purchase_paid`, `total_sell_return_paid`, etc. Sempre conferir antes de queryar coluna direto. Ref `TransactionUtil.php:2400` e `:2983`.

## Aprendizados de processo (5 lições MWART V2)

1. **Patch em Blade legacy NÃO alcança paridade visual.** Tentei header Cockpit no `sell/index.blade.php` (PR #260) — Wagner viu screenshot e reportou divergência ("topnav purple competindo, 2 botões duplicados, containers múltiplos"). Lição: shell legacy + componente moderno = dissonância garantida. **Migrar a página inteira pra Inertia ou não tentar.**

2. **Comparar com gold-standard ANTES de implementar.** Skill `mwart-comparative` V3 (ADR 0109) deve invocar `design:design-critique` com **screenshot lado-a-lado** antes de codar. PRs #254-#258 falharam porque inferi pattern em vez de comparar.

3. **Pills > tabs border-bottom** pra filtros com contagem. Pattern Linear/Stripe/Notion. Tabs servem pra trocar conteúdo (Board DetailSheet); pills servem pra filtrar lista.

4. **Filtros DataTables legacy não migram em massa.** Pra MVP migra **apenas pills primárias** (5 status mais usados). Filtros avançados (date range, customer, location, source, payment method) ficam pra próximo PR ou expansíveis em accordion. Tentar migrar 8+ filtros num PR = scope creep e regressão.

5. **Tabela DataTables AJAX legacy → fetch JSON minimalista**. Não embed iframe. Não dangerouslySetInnerHTML. Endpoint dedicado retorna 8 campos limpos + booleanos derivados. Limita 50-200 inicialmente; paginação fica pra fase 2.

## Consequências

### Boas

- **Reduz "ficou feio ainda"** — pattern formalizado, screenshot lado-a-lado obrigatório no F1.5
- **Onboarding novo dev** — anatomia canônica em 5 partes + tipografia/cores em tabelas, copy-paste do snippet pills
- **Reduz round-trips** — pattern endpoint REST documentado evita re-descoberta
- **Coerência multi-página** — Sells/Repair/Compras/Officeimpresso compartilham mesma cara

### Ruins / mitigações

- **Migrações futuras vão custar mais** que patch Blade — só Inertia paga preço total. **Mitigação:** ADR 0106 já recalibrou estimativas (10x IA-pair), Cockpit Pattern V2 é cabível em ~7h/página
- **Pattern V2 pode ficar obsoleto se exemplo Anthropic mudar.** **Mitigação:** ADR re-emendar com nova versão (V3) sem supersede — append-only

## Plano de aplicação

1. **Hoje (já feito — PR #261):** /sells + /sells/create aplicam V2 100% — referência viva
2. **Próximas migrações MWART** (Compras, Repair JobSheet, Despesas, etc):
   - F1.5 obrigatório: gerar `<tela>-visual-comparison.md` + screenshot proposta side-by-side
   - F3 obrigatório: implementar V2 anatomia
   - Skill `mwart-comparative` V3 deve verificar V2 conformity score
3. **Backfill retroativo** (próximo trimestre): aplicar V2 nas Pages existentes em ordem de tráfego (sigam Sells)

## Refs

- ADR 0094 — Constituição V2
- ADR 0104 — Processo MWART canônico
- ADR 0107 — Visual comparison gate F1.5 (emendado aqui)
- ADR 0109 — Claude Design plugin integrado
- [Pages/Sells/Index.tsx](../../resources/js/Pages/Sells/Index.tsx) — referência viva V2
- [Pages/Sells/_components/SaleSheet.tsx](../../resources/js/Pages/Sells/_components/SaleSheet.tsx) — drawer canon
- [Pages/Sells/Create.tsx](../../resources/js/Pages/Sells/Create.tsx) — form pattern com pills + footer sticky
- [Pages/ProjectMgmt/Board/DetailSheet.tsx](../../resources/js/Pages/ProjectMgmt/Board/DetailSheet.tsx) — pattern fonte interno
- PRs #257, #258, #259, #260 (lessons learned), #261 + commit `0b5a09d5` (V2 vivo)
- Exemplo Anthropic claude.ai/design Officeimpresso/Ordens de Serviço — gold-standard

---

**Última atualização:** 2026-05-08
