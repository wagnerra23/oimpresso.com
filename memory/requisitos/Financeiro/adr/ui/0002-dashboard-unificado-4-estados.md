# ADR UI-0002 (Financeiro) · Dashboard unificado com 4 estados (KPI grid + tabela única)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner (pediu explicitamente "contas pagas, a pagar, recebida, a receber na mesma tela")
- **Categoria**: ui
- **Relacionado**: US-FIN-013, ARQ-0005, `_Ideias/Financeiro/README.md` (ideia original)

## Contexto

A ideia original (`_Ideias/Financeiro/README.md` linhas 38-50) propôs **4 telas separadas** baseadas na combinação `(tipo, status)`:

```
| tipo    | status         | Tela visível |
|---------|----------------|--------------|
| pagar   | aberto/parcial | A Pagar      |
| pagar   | quitado        | Pagas        |
| receber | aberto/parcial | A Receber    |
| receber | quitado        | Recebidas    |
```

Concorrentes BR (Conta Azul, Tiny, Bling) seguem esse pattern: 4 menus separados na sidebar. Larissa precisa **clicar 4× e perder contexto** pra responder "quanto entra essa semana / quanto sai / como tá o mês".

Wagner pediu explicitamente em 2026-04-24: **"contas pagas, a pagar, recebida, a receber na mesma tela"** — sinalizou desejo de tela única.

## Decisão

**Dashboard único como entry-point do módulo (`/financeiro`) com:**

1. **KPI Grid** (4 cards clicáveis, top da tela)
2. **Filtros consolidados** (tipo, status, período, cliente, aging, conta bancária)
3. **Tabela única** mostrando todos os 4 estados, filtráveis por click no KPI

Layout (desktop ≥ 1024px):

```
╔═══════════════════════════════════════════════════════════════════════╗
║  FINANCEIRO  ·  abril 2026  ·  ROTA LIVRE                            ║
║                                                  [+ Novo título] [⚙] ║
╠═══════════════════════════════════════════════════════════════════════╣
║                                                                       ║
║  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐║
║  │📥 A RECEBER  │ │📤 A PAGAR    │ │✓ RECEBIDOS   │ │✓ PAGOS       │║
║  │              │ │              │ │              │ │              │║
║  │  R$ 12.450   │ │  R$ 8.230    │ │  R$ 45.300   │ │  R$ 28.100   │║
║  │  14 títulos  │ │  9 títulos   │ │  32 no mês   │ │  21 no mês   │║
║  │              │ │              │ │              │ │              │║
║  │ ⚠ 3 vencidos │ │ ⚠ 2 vencidos │ │ ↑ +12% vs    │ │ ↑ +5% vs     │║
║  │   R$ 2.340   │ │   R$ 1.180   │ │   março      │ │   março      │║
║  └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘║
║   ↑ click filtra ↑ click filtra   ↑ click filtra   ↑ click filtra   ║
║                                                                       ║
║  Filtros: [▼ Tipo: Todos]  [▼ Status: Todos]  [▼ Período]  [▼ ...]  ║
║                                                                       ║
║  ┌─────────────────────────────────────────────────────────────────┐ ║
║  │ # ▾ │ Cliente/Forn.    │ Tipo │ Status   │ Venc.  │ Valor  │ … │ ║
║  ├─────┼──────────────────┼──────┼──────────┼────────┼────────┼───┤ ║
║  │1234 │ João Silva       │ 📥 R │ ● aberto │ 28/04  │ R$1.500│ ⋯ │ ║
║  │1238 │ Petrobras        │ 📤 P │ ● aberto │ 30/04  │ R$  850│ ⋯ │ ║
║  │1230 │ Maria S.         │ 📥 R │ ✓ quita. │ 22/04  │ R$  500│ ⋯ │ ║
║  │1242 │ Conta de Luz     │ 📤 P │ ◐ parcial│ 05/05  │ R$  340│ ⋯ │ ║
║  │ ... │ ...              │  ... │  ...     │  ...   │   ...  │ ⋯ │ ║
║  └─────────────────────────────────────────────────────────────────┘ ║
║  Mostrando 1-25 de 87 · [<] 1 2 3 4 [>]                              ║
║                                                                       ║
╚═══════════════════════════════════════════════════════════════════════╝
```

Mobile (< 1024px):

- KPI grid em **2 colunas × 2 linhas** (preserva 4 cards)
- Filtros em accordion colapsado
- Tabela vira **lista de cards** (1 por título)
- FAB `[+]` no canto inferior direito

## Princípios de UX

1. **Drill-down por click no KPI** — não exige usuário entender filtro
2. **URL state** — filtros refletem em querystring (`?tipo=receber&status=aberto`); bookmarkable
3. **Server-side aggregation** — KPIs vêm calculados do backend, não somados no front
4. **Cache invalidado por evento** — após `TituloBaixado`, KPIs atualizam (5 min TTL ou broadcast)
5. **Tabela única > 4 tabelas** — Larissa não precisa saber qual menu clicar
6. **Badges visuais distinguem tipo/status** — sem coluna escondida; relance reconhece

## Pattern obrigatório

```tsx
// resources/js/Pages/Financeiro/Dashboard/Index.tsx
function FinanceiroDashboard({ kpis, titulos, filters }: Props) {
  return (
    <div>
      <KpiGrid kpis={kpis} onKpiClick={(filter) => router.get(route('financeiro.index', filter))} />
      <FilterBar filters={filters} />
      <TitulosTable
        rows={titulos.data}
        pagination={titulos.meta}
        onRowClick={(t) => openDrawer(t)}
      />
    </div>
  );
}

FinanceiroDashboard.layout = (page) => <AppShell children={page} />;
// preference_persistent_layouts.md — não envolver em <AppShell> manualmente
```

Endpoint shape:

```json
{
  "kpis": {
    "receber_aberto": {"valor": 12450.00, "qtd": 14, "vencidos_qtd": 3, "vencidos_valor": 2340.00},
    "pagar_aberto":   {"valor":  8230.00, "qtd":  9, "vencidos_qtd": 2, "vencidos_valor": 1180.00},
    "recebido_mes":   {"valor": 45300.00, "qtd": 32, "delta_pct": 12.0},
    "pago_mes":       {"valor": 28100.00, "qtd": 21, "delta_pct":  5.0}
  },
  "titulos": {
    "data": [
      {"id": 1234, "numero": "1234", "cliente_nome": "João Silva", "tipo": "receber",
       "status": "aberto", "vencimento": "2026-04-28", "valor_total": 1500.00, "valor_aberto": 1500.00,
       "aging_bucket": "<30", "origem_label": "Venda #5023"}
    ],
    "meta": {"current_page": 1, "per_page": 25, "total": 87}
  },
  "filters": {"tipo": "all", "status": "all", "periodo": null, "cliente_id": null, "aging": null}
}
```

## Componentes shadcn/ui requeridos

- `<Card>` — cada KPI
- `<Badge>` — tipo (📥/📤) e status (●/◐/✓)
- `<Select>` / `<DateRangePicker>` — filtros
- `<Combobox>` — autocomplete de cliente
- `<Table>` ou TanStack Table — listagem
- `<Drawer>` ou `<Sheet>` — detalhe do título
- `<Tooltip>` — em badges de aging vencido

## Tests obrigatórios

- **Backend Feature** `DashboardKpiTest` — KPIs corretos com 20 títulos misturados em 4 estados
- **Backend Feature** `DashboardFilterTest` — query string filtra (tipo/status/aging)
- **Backend Feature** `DashboardIsolationTest` — KPIs business B não vazam pra business A (R-FIN-001)
- **Component test (Vitest)** — `<KpiGrid>` renderiza 4 cards com cores e ícones corretos
- **E2E (Playwright)** — abrir → click "A RECEBER" → URL muda → tabela filtra → click row → drawer abre

## Performance

| Métrica | Meta |
|---|---|
| Endpoint dashboard p95 | < 500ms (5k títulos) |
| KPIs cache TTL | 5 min |
| Cache invalidação | event-based (`TituloBaixado`/`Criado`/`Cancelado`) |
| Pagination tamanho | 25 default, max 100 |
| Search debounce | 300ms |

## Métricas a observar (post-launch)

- Tempo médio "abrir financeiro → executar primeira ação" — meta < 10s
- Taxa de click em KPI (vs filtro manual) — meta > 70%
- Mobile vs desktop usage — informa investimento em mobile UX
- Larissa volta às 4 telas separadas se mantermos rotas legadas? Decidir após 30d

## Decisões em aberto

- [ ] Manter rotas legadas `/financeiro/contas-receber` e `/financeiro/contas-pagar` (US-FIN-001/004) ou redirect 301 pro dashboard com filtro? Recomendo **redirect** (uma única tela, evita confusão)
- [ ] KPIs configuráveis por user (esconder "Pago Mês" se não interessa)? Onda 4
- [ ] Dashboard exporta PDF/Excel? Onda 4
- [ ] Comparação de mês anterior (`+12% vs março`) usa baixa.data_baixa ou titulo.competencia? Provável `data_baixa` (regime caixa)

## Alternativas consideradas

- **4 rotas separadas** (proposta original `_Ideias/`) — rejeitado: Larissa pediu unificada
- **Tabs** ([Receber] [Pagar] [Histórico]) — rejeitado: perde overview do "todos os 4 estados"
- **Kanban quadrantes 2×2** (cada quadrante uma lista) — rejeitado: lista pequena demais em volume real (50+ títulos), perde sort/filter
- **Cards visuais sem tabela** (só KPI grid) — rejeitado: precisa drill-down pra detalhe; tabela é necessária

## Referências

- US-FIN-013 (SPEC)
- ARQ-0005 (paralelo a Accounting)
- `_Ideias/Financeiro/README.md` linhas 38-50 (ideia original 4 telas separadas)
- `_DesignSystem/adr/ui/0006-padrao-tela-operacional.md` (KpiGrid + DataTable + EmptyState)
- `auto-memória: cliente_rotalivre.md` — Larissa monitor 1280px (validar layout cabe)
- `auto-memória: preference_persistent_layouts.md` — Inertia layout pattern
- `auto-memória: reference_datatables_locale.md` — locale pt-BR
- Concorrentes BR (Conta Azul, Tiny, Bling) — todos usam 4 telas separadas (oportunidade de diferenciar)
