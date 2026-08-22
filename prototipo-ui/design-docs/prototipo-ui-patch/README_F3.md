# F3 — Financeiro Visão Unificada (PR proposto)

**Origem:** protótipo Cowork aprovado por [W] em 2026-05-09 (Financeiro.html, tela "Visão unificada").
**Padrão:** Cockpit V2, persona Eliana [E], densidade configurável (compact/comfortable/spacious).
**Tokens:** seguem `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md §4` (emerald entrada, rose saída, amber vencendo, stone neutros, rounded-md).

## Arquivos canônicos a aplicar

| Path no repo | Ação | Origem neste patch |
|---|---|---|
| `resources/js/Pages/Financeiro/Unificado/Index.tsx` | **CREATE** | `resources/js/Pages/Financeiro/Unificado/Index.tsx` |
| `Modules/Financeiro/Http/Controllers/UnificadoController.php` | **CREATE** | `Modules/Financeiro/Http/Controllers/UnificadoController.php` |
| `Modules/Financeiro/Routes/web.php` | **EDIT** (adicionar rota) | ver `web.php.patch.md` |

## Comportamentos preservados do protótipo (DEVEM funcionar)

1. **5 KPIs no topo:** Saldo previsto (destaque), Recebido, A Receber, Pago, A Pagar.
2. **Tabela única intermixada:** entrada (↑ emerald) e saída (↓ stone) na mesma view, agrupadas por data de vencimento.
3. **Filtros sticky:** tabs (Todas/Aberto/Receber/Pagar/Recebidas/Pagas/Atraso) + chips (Conta, Categoria, Período, Conciliação) + busca.
4. **1-clique baixa:** botão "Recebi"/"Paguei" inline na linha não-quitada → POST `/financeiro/unificado/{id}/baixar` → otimistic update.
5. **Drawer lateral:** clique numa linha abre `<Sheet>` com detalhe + ações, NÃO modal full-screen.
6. **Densidade:** Tweak persona Eliana → 3 níveis (compact 32px / comfortable 44px / spacious 56px de altura de linha).
7. **Atalhos:** ⌘K (palette de busca global), J/K (navegar linha), Espaço (selecionar), `/` (foco busca).
8. **URL sync:** filtros + tab + densidade refletem em querystring (`router.get` com `preserveState`).
9. **Status badges:** `aberto`, `recebido`, `pago`, `atrasado`, `vencendo` → cores tokens (emerald/stone/amber/rose).
10. **Num tabular** em todos os valores BRL.

## Decisões abertas pra [W] confirmar antes de F4

1. Banco real pra "Saldo previsto" — somar `Modules\Financeiro\Models\BankAccount.saldo_cached` ou expor seletor?
2. Plano de contas: usar `Categorias` existente ou criar tree hierárquica nova?
3. "Conciliação rápida" no header → linka pra `/financeiro/extrato/{id}` ou abre modal inline?
4. Limite mínimo configurável para "vencendo em 7 dias" → settings tenant?

## Próximos passos protocolares

- F1.5 já está aprovado por consenso prático (W aprovou visualmente em 2026-05-09).
- F2 já está aprovado (W: "f3" = aprovou e quer tradução).
- **F3 = ESTE PATCH** (Claude Code traduz pra Inertia/React real).
- F3.5: Claude Accessibility roda `accessibility-review` no PR aberto.
- F4: W mergeia.
