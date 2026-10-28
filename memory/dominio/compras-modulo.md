---
dominio: Compras (módulo Modules/Compras) — custo do insumo e proposta de preço
fonte_unica: este arquivo é a fonte canônica do vocabulário de domínio do módulo Compras (ADR 0264 G-4)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum de migration ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0093-multi-tenant-isolation-tier-0]
---

# Dicionário de domínio — Compras (módulo)

> **Fonte única do vocabulário de domínio** das tabelas próprias de `Modules/Compras` (ADR 0264 G-4).
> O lado core (pedido, recebimento, devolução em `transactions`) fica em [compras.md](compras.md).
> **Editar aqui é uma decisão de domínio.**

## Semântica

- **Método de custo do insumo** (`compras_insumo_metodo_custo.metodo`, US-COM-024 · UC-FLX-38):
  `medio` é o médio ponderado pela quantidade líquida das compras recebidas até a data; `ultimo`
  é o preço da compra recebida mais recente até a data. Só vale para o insumo quando a regra
  "Atualizar o custo dos insumos" da empresa é **Definido em cada insumo** (`por_insumo`, em
  `RegrasCompra`). Uma linha por empresa e insumo; insumo sem linha fica sem custo, com o motivo.
- **Proposta de preço** (`compras_propostas_preco.status`, US-COM-026 · UC-FLX-17): nasce `pendente`
  quando a compra é recebida e a regra "Atualizar o custo dos insumos" da empresa está em "Propõe novo
  preço". `aprovada` e `recusada` são a decisão de quem aprova (fila ainda é follow-up); o preço da
  variação só muda quando a proposta for aprovada.
- **`stock_reservations.status`** é do [estoque.md](estoque.md); vai repetido no `vocab` porque o MRP (US-COM-030) lê as reservas ativas pela coluna curta.
- **`transactions.status`** é da espinha e o dono é [vendas.md](vendas.md). Vai repetido no `vocab`
  abaixo só porque o gate lê o código do módulo pela coluna curta (`status`): sem ele, cada
  `status = received` do módulo vira divergência. Mudou lá, muda aqui.

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "Compras",
  "enums": {
    "compras_insumo_metodo_custo.metodo": ["medio", "ultimo"],
    "compras_propostas_preco.status": ["pendente", "aprovada", "recusada"],
    "compras_cotacoes.status": ["aberta", "fechada", "cancelada"],
    "compras_aprovacoes_pedido.nivel": ["gerente", "diretoria"],
    "compras_aprovacoes_pedido.status": ["pendente", "aprovado", "recusado"],
    "compras_inspecoes.resultado": ["aprovado", "reprovado", "parcial"]
  },
  "vocab": {
    "transactions.status": ["received", "pending", "ordered", "draft", "final", "partial", "completed"],
    "stock_reservations.status": ["active", "consumed", "released", "expired"]
  }
}
```
