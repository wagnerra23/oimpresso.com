---
dominio: Estoque (core UltimatePOS) — produto, movimento, reserva, inventário
fonte_unica: este arquivo é a fonte canônica do vocabulário de ESTOQUE (ADR 0264 G-4, Onda Q3)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum core ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e]
---

# Dicionário de domínio — Estoque (core)

> **Fonte única do vocabulário de estoque** (Onda Q3 — existe ANTES das telas de estoque que
> [W] vai construir). Grounded no schema REAL (`database/migrations`).

## Conceitos-chave (PT-BR canônico) — conforme o que o schema REALMENTE tem

- **Movimento de estoque** NÃO é tabela própria: vive na espinha `transactions.type`
  (`stock_adjustment` = ajuste/inventário · `opening_stock` = saldo inicial ·
  `sell_transfer`/`purchase_transfer` = transferência entre locais). A declaração desses
  valores é do [vendas.md](vendas.md) (dono da espinha) — aqui só a semântica.
- **Saldo por local** = `variation_location_details.qty_available` (decimal, sem enum) —
  termo canônico "saldo disponível", nunca "estoque atual" solto sem local.
- **Reserva** = `stock_reservations.status ∈ {active, consumed, released, expired}` —
  reserva CONSOME ao virar venda (`consumed`), LIBERA ao cancelar (`released`).
- **Baixa de estoque** = efeito da venda final sobre `qty_available` (via sell lines);
  produto `enable_stock=0` NÃO movimenta estoque (serviço/sob-demanda).
- **Inventário** = ajuste (`stock_adjustment`) com `adjustment_type ∈ {normal, abnormal}`.
- **Produto** = `products.type ∈ {single, variable, modifier}` — `variable` tem variações
  reais (P/M/G); `single` tem a variação DUMMY interna (idioma UltimatePOS); `modifier` é
  complemento (herança restaurante, vestigial pra nós).
- **Sinônimos proibidos**: "kit" pra `combo` (combo não existe no enum atual — não inventar);
  "depósito" pra `business_location` (canônico: "local"/"loja").

## Enums canônicos (machine-checked por `dominio:check`)

> `business.*` (accounting_method fifo/lifo/avco etc.) é tabela de CONFIGURAÇÃO mista —
> fica de fora do claim até ter dono de domínio claro (não esconder: é decisão registrada).

```json
{
  "module": "EstoqueCore",
  "migrations_paths": ["database/migrations"],
  "tables_scope": ["products", "stock_reservations", "warranties"],
  "code_paths": [],
  "enums": {
    "products.type": ["single", "variable", "modifier"],
    "products.barcode_type": ["C39", "C128"],
    "products.tax_type": ["inclusive", "exclusive"],
    "products.expiry_period_type": ["days", "months"],
    "stock_reservations.status": ["active", "consumed", "released", "expired"],
    "warranties.duration_type": ["days", "months", "years"]
  }
}
```

### Vestigial (visível, não escondido)

- `products.type=modifier` — herança UltimatePOS restaurante (complementos de prato).
  Gráfica/oficina não usa; manter até decisão [W] de erradicação.

### Por que `code_paths: []` (Salto #3 adiado pro estoque)

`ProductUtil.php` mistura `products.type` (single/variable) e `transactions.type`
(sell/purchase/production_*) — o matching do guard é por NOME CURTO de coluna, então o
índice do estoque colidia com a espinha de vendas (14 falsos-positivos no run local).
Salto #3 do estoque entra quando o guard ganhar matching por tabela.
