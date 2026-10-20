---
dominio: Compras (módulo Modules/Compras) — custo do insumo
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

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "Compras",
  "enums": {
    "compras_insumo_metodo_custo.metodo": ["medio", "ultimo"]
  }
}
```
