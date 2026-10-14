---
slug: restaurante-runbook-cozinha
title: "Restaurante — Runbook da tela Cozinha"
type: runbook
module: Restaurante
tela: Restaurante/Cozinha/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Cozinha (`/modules/kitchen`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `restaurante/playbook/04`.
> **Fonte de design:** alvo `governance/design/targets/restaurante--cozinha--index.alvo.json` (thread A1), medido sobre `prototipo-ui/cowork/Wagner/restaurante-operacao.jsx` → `RestauranteOperacaoPage view="cfg-cozinha"`.
> **Decisão de origem:** [W] 07/10 no formulário do SINCRONIZAR: *"Usamos — migrar para React"*.

## Estado final esperado

`GET /modules/kitchen` (`KitchenController::index`) responde Inertia `Restaurante/Cozinha/Index` **quando a flag
`useV2RestauranteCozinha` está ligada** para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade
`restaurant.kitchen.index`. "Marcar como preparado" continua chamando o mesmo endpoint
(`GET /modules/kitchen/mark-as-cooked/{id}`), sem mudar o que ele grava.

## 1. Objetivo

Mostrar a fila da cozinha (pedidos com item por preparar) no desenho do alvo da A1 e marcar o pedido como preparado
pelo endpoint que já existe.

## 2. Pré-condições

- Permissão `sell.view` — gate reativado na US-GOV-059; o item do menu usa o mesmo predicado.
- Módulo `kitchen` ligado no negócio (`enabled_modules`) para o item aparecer no menu.
- Lane `acessos-pest.yml` (MySQL) roda `tests/Feature/Restaurante/`.

## 3. Paridade com a Blade (o que existe hoje)

| Blade `restaurant.kitchen.index` + `partials/show_orders` | Fonte | Na tela React |
|---|---|---|
| Cartão por pedido: #nota · feito às · situação · cliente · mesa · local | `RestaurantUtil::getAllOrders($biz, ['line_order_status' => 'received', 'is_kitchen_order' => 1])` | cartão por pedido, mais antigo primeiro |
| Situação derivada dos itens (recebido · preparado em parte · preparado · servido em parte · servido) | `show_orders.blade.php` | a mesma regra, calculada no controller |
| "Marcar como preparado" (confirmação, GET ajax, remove o cartão) | `markAsCooked($id)` | mesmo endpoint, recarrega a fila |
| "Detalhes do pedido" (modal da venda) | `SellController::show` | link para o detalhe da venda |
| Botão Atualizar | `refreshOrdersList` (HTML parcial) | recarrega a prop da fila (partial reload) |

## 4. Passo-a-passo

1. F2 — `CozinhaBaselineTest` trava a Blade e o endpoint hoje: fila só com pedidos de cozinha do negócio (98 × 99) que
   têm item por preparar, 403 sem `sell.view`, e `markAsCooked` só altera itens do próprio negócio.
2. F3 — `KitchenController::index` ganha o ramo Inertia atrás da flag; a fila sai de uma prop deferida.
3. F3 — `Restaurante/Cozinha/Index.tsx` + charter + casos + `CozinhaContratoTest`.
4. F4 — smoke em biz=1 com a flag ligada (`flag:set useV2RestauranteCozinha --biz=1`).
5. F5 — cutover (flag default ligada) é decisão [W].

## 5–8. Tokens · estados · responsividade · atalhos

Herdados do `AppShellV2`. Estados: carregando (prop deferida), fila vazia, marcando (botão desabilitado).

## 9. DoD checklist

- [ ] `CozinhaBaselineTest` e `CozinhaContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] `secao-check --tela restaurante--cozinha--index --servir-espelho` conforme.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas

- **Estado de pedido, não valor nem estoque.** `markAsCooked` muda só `transaction_sell_lines.res_line_order_status`
  de `received`/nulo para `cooked`. Não toca preço, total, pagamento nem estoque: a regra mestre de valor/estoque não
  se aplica. A tela React não muda esse endpoint.
- **Resíduo da US-GOV-059, mantido.** `markAsCooked`, `refreshOrdersList` e `refreshLineOrdersList` seguem sem gate de
  permissão (só filtro por negócio). Fechar é outra thread; a tela React só chama `markAsCooked`.
- **Locais permitidos.** `getAllOrders` filtra por `permitted_locations()` do usuário — a React herda.
- **Ordem.** A Blade mostra o mais recente primeiro (`orderBy created_at desc`); o protótipo, o mais antigo primeiro.
  A React segue o protótipo (forma); a fila é a mesma.
- **Atualização ao vivo** não existe na Blade (botão Atualizar) e o protótipo manteve o botão (`_saida-P1`).

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
