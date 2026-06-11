---
dominio: Compras (core UltimatePOS) — pedido, recebimento, devolução ao fornecedor
fonte_unica: este arquivo é a fonte canônica do vocabulário de COMPRAS (ADR 0264 G-4, Onda Q3)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e]
---

# Dicionário de domínio — Compras (core)

> **Fonte única do vocabulário de compras** (Onda Q3). Compras roda na MESMA espinha
> `transactions` de vendas — este dicionário fixa a SEMÂNTICA do lado-compra; a declaração
> machine-checked dos enums compartilhados é do [vendas.md](vendas.md) (dono da espinha),
> não redeclarada aqui (duas declarações da mesma coluna = drift garantido).

## Conceitos-chave (PT-BR canônico) — conforme o que o schema REALMENTE tem

- **Compra** = `transactions` com `type=purchase`. Ciclo: `ordered` (**pedido emitido**) →
  `pending` (aguardando) → `received` (**recebida** — só aqui entra estoque).
- **Recebimento** = flip pra `status=received`; é o recebimento que MOVIMENTA estoque
  (`purchase_lines` → `qty_available`), nunca o pedido.
- **Devolução ao fornecedor** = `type=purchase_return` (espinha) — canônico "devolução",
  nunca "estorno" (estorno é de pagamento, financeiro.md).
- **Fornecedor** = `contacts` com `primary_role=supplier` (cadastro compartilhado — enums
  declarados em vendas.md).
- **Pagamento da compra** = título `tipo=pagar` com `origem=compra` (financeiro.md) — a
  compra a prazo gera obrigação igual a venda gera direito.
- **Custo** entra por `purchase_lines.purchase_price*` (decimal, sem enum) e alimenta o
  método de custeio (`business.accounting_method` fifo/lifo/avco — tabela de config, sem
  dono de domínio claimado; ver estoque.md).
- **Sinônimos proibidos**: "entrada de nota" pra recebimento de compra (canônico:
  recebimento; "nota" é documento fiscal — fiscal-faturamento.md); "ordem de compra" (o
  schema chama `ordered`/pedido; "OS/ordem" é vocabulário da Oficina).

## Enums canônicos (machine-checked por `dominio:check`)

> Compras não possui enum EXCLUSIVO no schema atual (purchase_lines não tem enum; a espinha
> é declarada em vendas.md). O bloco existe pro gate validar a INTENÇÃO (zero divergência)
> e pro dicionário contar como fonte-única no censo — declaração vazia é honesta, não burlа.

```json
{
  "module": "ComprasCore",
  "migrations_paths": ["database/migrations"],
  "tables_scope": ["purchase_lines"],
  "code_paths": ["app/Http/Controllers/PurchaseController.php", "app/Http/Controllers/PurchaseReturnController.php"],
  "enums": {}
}
```
