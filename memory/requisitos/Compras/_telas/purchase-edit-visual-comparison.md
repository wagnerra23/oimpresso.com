---
id: requisitos-compras-telas-purchase-edit-visual-comparison
tela: purchase/edit
modulo: Purchase
tipo: FORM EDIT
generated_at: 2026-05-15
generated_by: Agent W2-D
status: medido
runbook: memory/requisitos/Compras/_telas/RUNBOOK-purchase-edit.md
draft_tsx: resources/js/Pages/Purchase/Edit.tsx
controller_delta: app/Http/Controllers/PurchaseController.php@editInertia
cowork_source: prototipo-ui/cowork/Wagner/compras-page.jsx
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

---

## Medição de 2026-11-09 (lote 6 da auditoria de Compras)

> Substitui como estado atual o "aguardando screenshot" acima, que fica como registro de 2026-05-15.

Run [42621542024](https://github.com/wagnerra23/oimpresso.com/actions/runs/42621542024), artifact `paridade-lote-42621542024`, branch descartável `claude/compras-lote6-medir-nao-mergear` (não mergeada). App efêmero do `design-smoke-ci` sobre o `main` em `d1826f8357`, biz=1 fictício, 1280×900, tema escuro real nos dois lados. Em produção não deu: o Chrome não tinha sessão e o staging está 26 dias atrás.

**Veredito contra protótipo: NÃO MEDIDO (2026-11-09), por decisão declarada.** O charter tem `related_prototype: n/a (herda PT-02 Form-Drawer)`, então não há âncora. O `blueprint_cowork` do charter não vira âncora sem decisão [W] (§5 2026-08-28).

**Comparação entre irmãs, medida.** O charter diz que a tela é idêntica ao Create. Mesma sonda em `/purchases/{id}/edit?v=2` e `/purchases/create`:

| Dim. | Edit × Create | Veredito | Data |
|---|---|---|---|
| D2 layout | igual | IGUAL | 2026-11-09 |
| D4 tipografia | igual | IGUAL | 2026-11-09 |
| D6 cor | igual | IGUAL | 2026-11-09 |
| D8 alinhamento | igual | IGUAL | 2026-11-09 |
| D9 âncoras `data-contract` | as 5 do Create não existem no Edit | achado | 2026-11-09 |
| Copy da 3ª seção | Create "Descontos / Impostos / Frete", Edit "Desconto / Impostos / Frete" | achado | 2026-11-09 |

A rota exige `?v=2`, e compra além do prazo `transaction_edit_days` é devolvida para o detalhe. A primeira medida caiu nisso; a que vale usou data recente no dado de teste.
