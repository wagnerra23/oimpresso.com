---
slug: 0248-sells-show-delete-gate-paridade-authz-servidor
number: 248
title: "Gate de exclusão do Sells/Show espelha a autorização do servidor (sell.delete || direct_sell.delete || so.delete)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-03"
accepted_at: null
accepted_via: "Aguarda Wagner aprovar no PR #2175 (feat/sells-show-delete-btn)"
module: sells
quarter: 2026-Q2
tags: [sells, mwart, permissions, multi-tenant, inertia, ux, bugfix, phpstan-ratchet]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
pii: false
review_triggers:
  - "SellPosController::destroy mudar a regra de autorização (adicionar/remover permissão) → o gate de UI em SellController::show DEVE ser atualizado no mesmo PR para manter paridade"
  - "Surgir requisito de esconder o botão por tipo de venda (não só por permissão) → revisitar o OR plano e considerar gate type-aware (com cuidado pro ratchet PHPStan)"
---

# ADR 0248 — Gate de exclusão do Sells/Show espelha a autorização do servidor

## Contexto

Cliente reportou que o botão **"Excluir venda"** não aparecia na tela React `Sells/Show`, travando o fluxo operacional. O botão e o handler já existiam no `resources/js/Pages/Sells/Show.tsx` — só renderizam quando a prop `permissions.delete === true`.

O backend (`app/Http/Controllers/SellController.php::show`) computava esse flag como:

```php
'delete' => can('direct_sell.delete') || can('sell.delete')
```

Faltava `so.delete`. Para vendas do tipo **pedido** (`sales_order`), um perfil que só tinha `so.delete` ficava sem o botão — enquanto o Blade legado (`resources/views/sale_pos/show.blade.php:418-420`) o exibia via gate por tipo de venda.

A autorização **real** da exclusão vive em `app/Http/Controllers/SellPosController.php::destroy:1608`, que revalida server-side com um **OR plano** das três permissões:

```php
if (!can('sell.delete') && !can('direct_sell.delete') && !can('so.delete')) { abort(403); }
```

Alternativa avaliada: replicar o gate **type-aware** do Blade (`is_direct_sale == 0/1`, `type != 'sales_order'`). Rejeitada porque o Larastan marca essas comparações como `Result of && is always false`, regredindo o gate `PHPStan ratchet vs baseline` — e porque o Blade era mais restritivo no UI do que o próprio enforcement do servidor.

## Decisão

O gate de UI `permissions.delete` em `SellController::show` passa a **espelhar exatamente a autorização do servidor**:

```php
'delete' => can('sell.delete') || can('direct_sell.delete') || can('so.delete')
```

O botão de exclusão aparece precisamente quando `SellPosController::destroy` autorizaria a ação. O servidor permanece a fonte de verdade da autorização; o UI apenas reflete.

## Justificativa

- **Sem escalada de privilégio:** o endpoint `destroy` revalida as três permissões server-side; o gate de UI é cosmético sobre a regra autoritativa.
- **Paridade com a fonte de verdade:** mostrar o botão quando — e somente quando — o servidor autoriza evita tanto o falso-negativo (bug relatado) quanto o falso-positivo (botão que daria 403).
- **CI saudável:** o OR plano evita as comparações que disparavam regressão no ratchet PHPStan.
- **Multi-tenant preservado (ADR 0093):** `show()` filtra por `business_id` antes de montar as props.

Reabrir se a regra de autorização do `destroy` mudar ou se surgir necessidade de esconder o botão por tipo de venda (e não só por permissão).

## Consequências

**Positivas:** botão de exclusão volta a aparecer para perfis `so.delete` em pedidos; UI e servidor ficam acoplados pela mesma regra; PR verde no PHPStan.

**Negativas / Trade-offs:** o gate de UI fica mais permissivo que o Blade legado (que escondia por tipo). Aceitável porque o servidor é quem enforce — e o Blade já era inconsistente com seu próprio `destroy`.

**Riscos mitigados:** divergência silenciosa entre o que o UI oferece e o que o servidor permite.

## Referências

- ADR 0093 — Multi-tenant isolation (Tier 0)
- `app/Http/Controllers/SellPosController.php:1608` — autorização canônica do `destroy`
- `resources/views/sale_pos/show.blade.php:418-420` — gate legado por tipo (substituído no fluxo React)
- PR #2175 — implementação (botão + paridade de permissão)
