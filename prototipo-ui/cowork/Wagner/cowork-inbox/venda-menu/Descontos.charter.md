---
page: /discount
component: (F1) venda-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-22"
parent_module: Sells
related_prototype: prototipo-ui/cowork/venda-menu/
---

# Charter — Descontos

**Fonte legado:** `discount/index + create + edit` · **Permissão:** `discount.access (uma só permissão para ver, editar e excluir)`
**Frescor:** 🟠 catch-up real — medido no `main` em 2026-08-22 (tree `6a8e45998ee5`): nenhum `Inertia::render` no controller, a tela ainda é Blade.

## Missão
Regras de desconto que o PDV aplica sozinho enquanto valem.

## Regras
- R1 Prioridade menor ganha da maior quando duas regras batem no mesmo produto.
- R2 Escolher produtos **apaga** marca e categoria — o servidor guarda um ou outro, nunca os dois (`DiscountController@store`).
- R3 Massa só **desativa**; não existe exclusão em lote.
- R4 Desconto inativo some do PDV e ganha a ação "Reativar".
- R5 Local é obrigatório; tipo é fixo ou percentual.

## Achados (lidos no `main` 2026-08-22)
- A1 A view checa `brand.view`/`brand.create` nos botões enquanto o controller checa `discount.access` — quem tem marca e não tem desconto vê botão que dá 403.
- A2 `store()`/`update()` usam `Request` cru, **sem FormRequest**: nome vazio, prioridade não numérica e datas ausentes passam (viram `null`).
- A3 `discount.access` não separa ver de editar.
