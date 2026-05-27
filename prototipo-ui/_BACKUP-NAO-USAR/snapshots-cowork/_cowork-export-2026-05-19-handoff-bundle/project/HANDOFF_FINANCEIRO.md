# Financeiro · Handoff F1 → F3 (Claude Code)

**Estado:** F1 visual aprovado pelo Wagner. Pronto pra Claude Code traduzir pra Inertia/React real no `wagnerra23/oimpresso.com@main`.

## Arquivos do protótipo (neste projeto Cowork)

```
Financeiro Unificado.html      ← shell + carrega todos os módulos
financeiro-icons.jsx           ← ícones lucide-style (28 ícones)
financeiro-data.jsx            ← mock data ROTA LIVRE (14 receivables + 12 payables)
financeiro-app.jsx             ← Sidebar/Header/Tela unificada/Drawer/CmdK
financeiro-telas-extras.jsx    ← Fluxo, Conciliação, DRE, Plano de contas
tweaks-panel.jsx               ← starter (não traduzir — só ferramenta de proto)
```

## Telas entregues (5)

| Slug      | Rota Inertia sugerida                      | Componente Page              |
|-----------|--------------------------------------------|------------------------------|
| unified   | `/financeiro` (default)                    | `Pages/Financeiro/Index.tsx` |
| fluxo     | `/financeiro/fluxo-caixa`                  | `Pages/Financeiro/Fluxo.tsx` |
| concil    | `/financeiro/conciliacao`                  | `Pages/Financeiro/Conciliacao.tsx` |
| dre       | `/financeiro/dre`                          | `Pages/Financeiro/DRE.tsx`   |
| pcontas   | `/financeiro/plano-de-contas`              | `Pages/Financeiro/PlanoContas.tsx` |

## Tokens (BRIEFING §4 — não inventar)

- Background: `#fafaf9` (stone-50). Cards: `#ffffff` + `border border-stone-200 shadow-sm`
- Radius: `rounded-md` (6px) padrão · `rounded-full` em badges/dots · evitar `rounded-xl+`
- Status: emerald-50/700 (recebido/pago) · amber-50/700 (vencendo) · rose-50/700 (atrasado/saída) · stone-100/600 (pendente)
- Tipografia: Inter Tight + JetBrains Mono (já no `<head>`). KPI = `text-[28px] font-semibold tracking-tight num` · labels = `text-[10px] uppercase tracking-widest text-stone-500 font-medium`
- Densidade tabela (rowH px): compacta 32 / confortável 44 / espaçosa 56 — controlada via tweak persistido
- `.num` = `font-variant-numeric: tabular-nums` — usar em todo dinheiro/contador
- Foco: `focus-visible:ring-2 focus-visible:ring-stone-700 focus-visible:ring-offset-2` (já styled global)

## Padrão Cockpit V2 (ADR 0110)

Sidebar 220px sticky · Header sticky com breadcrumb + busca ⌘K + ações · KPI strip card único com `divide-x` · Body cards `bg-white rounded-md shadow-sm` · Footer sticky com summary/batch · **Drawer lateral 440px** pra detalhe (NUNCA modal full-screen).

## Backend — modelos sugeridos (Eloquent)

```php
// Models a criar no módulo Financeiro/
FinancialEntry              // tabela unificada — kind: receivable|payable
  - kind, party_type, party_id (poly), description, category_id
  - amount (decimal 12,2), due_date, paid_at (nullable)
  - channel (enum), invoice_ref, account_id, sale_id (nullable)
  - status (computed: pendente/vencendo/atrasado/recebido/pago)

ChartOfAccount              // plano de contas hierárquico
  - code (1.1.01), name, type (rec|exp), parent_id, level

BankAccount                 // contas bancárias
  - bank, branch, account, label, current_balance

BankStatementLine           // linha do extrato OFX
  - bank_account_id, ofx_id, date, description, amount
  - matched_entry_id (nullable), confidence (0-1)
```

## Comportamentos críticos pra preservar

1. **Marcar pago/recebido em 1 clique** — botão "✓ Recebi/Paguei" inline na linha, sem confirmação. Emite evento + atualiza status + tenta auto-conciliar com BankStatementLine ±R$5 ±2 dias.
2. **⌘K / `/`** — abre command palette. Busca em `description`, `party.name`, `invoice_ref`, `id`, `category.name`. Resultado abre drawer.
3. **Filtros syncados na URL** — tab, period, category, query → query string. F5 preserva estado.
4. **Tabela unificada agrupada por data** — header `<tr class="bg-stone-50/60">` com `colSpan` em `<td>` (NÃO usar `display: flex` no `<td>` — wrap content em `<div>`).
5. **Conciliação fuzzy** — sugestões com confidence ≥ 0.85 já vêm pré-marcadas como "Sugerido" (amber). Botão "✓ Aceitar" persiste o match.
6. **DRE com linha "Resultado operacional"** em destaque (`bg-stone-900 text-white`) — KPI mais importante da página.

## Permissões (RBAC)

- Eliana [E] (financeiro): full CRUD em FinancialEntry + Conciliacao + DRE
- Larissa: read-only em DRE/Plano de contas, full em Visão unificada/Fluxo
- Wagner: full em tudo

## Acessibilidade (pra F3.5 com Claude A11y)

- Contraste verificado: stone-700 sobre stone-50 = 8.2:1 ✓ · emerald-700 sobre emerald-50 = 7.1:1 ✓
- Foco visível em todo botão/input/linha clicável (`focus-visible:ring`)
- Atalhos teclado documentados no FooterBar (J/K/␣//)
- Tabela: `<thead>` com `scope="col"`, datas em `<time datetime>`
- Drawer abre como `<aside role="dialog" aria-modal="true">`, fecha com Esc + clique no backdrop

## Próximos passos protocolares

1. **F1.5 [CD]:** rodar `design:design-critique` + `critique-score.json` (alvo ≥80)
2. **F2 [W2]:** Wagner aprova screenshot
3. **F3 [CL]:** Claude Code traduz pra Inertia em `resources/js/Pages/Financeiro/*.tsx`
4. **F3.5 [CA]:** `accessibility-review` WCAG 2.1 AA
5. **F4 [W2]:** PR merge

## Decisões abertas pra Wagner confirmar

- [ ] Banco padrão em ROTA LIVRE: confirmou Itaú PJ (mock usa)?
- [ ] Plano de contas — seguir modelo do mock (2 níveis Rec/Desp) ou importar de outro template?
- [ ] DRE: incluir linha "Lucro líquido" (depois de IR/CSLL) ou parar em "Resultado operacional" (Simples Nacional não separa)?
- [ ] Limite mínimo de caixa (R$ 5.000 hardcoded no Fluxo) — virar config do tenant?
