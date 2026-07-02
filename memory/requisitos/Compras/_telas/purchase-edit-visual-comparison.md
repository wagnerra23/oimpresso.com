---
tela: purchase/edit
modulo: Purchase
tipo: FORM EDIT
generated_at: 2026-05-15
generated_by: Agent W2-D
status: aguardando-screenshot-wagner
runbook: memory/requisitos/Compras/_telas/RUNBOOK-purchase-edit.md
draft_tsx: resources/js/Pages/Purchase/Edit.tsx
controller_delta: app/Http/Controllers/PurchaseController.php@editInertia
cowork_source: prototipo-ui/prototipos/compras/visual-source.html
---

# Visual Comparison — `purchase/edit` (FORM EDIT)

## Smoke local

```bash
# Acesso Blade: /purchases/{id}/edit
# Acesso Inertia: /purchases/{id}/edit?v=2
```

## 15 dimensões (resumido)

Idêntico a `purchase-create-visual-comparison.md` salvo:
- **Pré-população**: `purchase` prop popula `useForm` initial state.
- **Método HTTP**: `_method: 'PUT'` em useForm + `form.post(/purchases/{id})` (Laravel method spoofing).
- **Breadcrumb**: "Compras > Editar".
- **Linhas existentes preservadas**: `purchase.purchase_lines[]` populadas com `id` real (não null).

## Diferenças vs Create

| Aspecto | Create.tsx | Edit.tsx |
|---|---|---|
| URL submit | POST /purchases | POST /purchases/{id} + _method=PUT |
| Initial state | vazio | from purchase prop |
| Linhas | [] | purchase.purchase_lines |
| Permission gate | `purchase.create` | `purchase.update` + `canBeEdited` |
| isReturnExist bloqueio | n/a | sim (Controller) |

## Decisão Wagner — aprova screenshot?

- [ ] Renderiza com purchase real biz=1 sem erro
- [ ] Linhas existentes aparecem populadas
- [ ] PUT update funciona (form salva)
- [ ] Tier 0 preservado (não acessa compra de outra business)
