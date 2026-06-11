---
dominio: Vendas (core UltimatePOS) — balcão, a prazo, devolução; espinha de transações
fonte_unica: este arquivo é a fonte canônica do vocabulário de VENDAS (ADR 0264 G-4, Onda Q3)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum core ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0265-oficina-reparo-erradica-locacao]
---

# Dicionário de domínio — Vendas (core)

> **Fonte única do vocabulário de vendas** (Onda Q3 do mandato ONDAS-QUALIDADE — a defesa
> anti-alucinação tem que existir ANTES das telas de estoque/faturamento que [W] vai construir).
> Grounded no schema REAL (`database/migrations`, last-write-wins). Editar aqui é decisão de
> domínio — não se acrescenta valor de enum sem registrar a semântica.

## Conceitos-chave (PT-BR canônico)

- **Venda balcão** = `transactions` com `type=sell`, `status=final`. Sem pagamento integral →
  `payment_status=due|partial` (**venda a prazo / fiado** — permitido por decisão [W] 2026-05-27;
  o backend deriva o status do pagamento, NUNCA a UI inventa estado).
- **A espinha é compartilhada**: a tabela `transactions` carrega TODOS os movimentos do ERP
  (`sell`, `purchase`, `expense`, ajustes e transferências de estoque, devoluções, saldos de
  abertura). Este dicionário é o DONO da declaração; compras.md e estoque.md REFERENCIAM
  estes valores, não os redeclaram.
- **Origem da venda** = `transactions.source ∈ {balcao, oficina, online}` — oficina deriva venda
  (Vendas×Oficina), nunca o contrário.
- **Devolução** = `sell_return` (venda) / `purchase_return` (compra) — termo canônico "devolução",
  nunca "estorno" (estorno é de PAGAMENTO, domínio financeiro).
- **Cliente** = `contacts` (cadastro compartilhado cliente/fornecedor/funcionário via
  `primary_role`). Walk-In Customer é o cliente-default do balcão (`is_default`).
- **Sinônimos proibidos**: "pedido de venda" pra `status=final` (pedido é `ordered`); "orçamento"
  pra venda final (orçamento/quotation é `draft`); "nota" pra venda (nota é documento FISCAL —
  ver fiscal-faturamento.md).

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "VendasCore",
  "migrations_paths": ["database/migrations"],
  "tables_scope": ["transactions", "transaction_sell_lines", "transaction_payments", "cash_registers", "cash_register_transactions", "types_of_services", "sale_processes", "contacts"],
  "code_paths": ["app/Http/Controllers/SellController.php", "app/Http/Controllers/SellPosController.php"],
  "vocab": {
    "transactions.type": ["purchase", "sell", "expense", "stock_adjustment", "sell_transfer", "purchase_transfer", "opening_stock", "sell_return", "opening_balance", "purchase_return", "production_purchase", "production_sell", "purchase_order"],
    "transactions.status": ["received", "pending", "ordered", "draft", "final"],
    "transactions.source": ["balcao", "oficina", "online"]
  },
  "enums": {
    "transactions.payment_status": ["paid", "due", "partial"],
    "transactions.discount_type": ["fixed", "percentage"],
    "transactions.pay_term_type": ["days", "months"],
    "transactions.recur_interval_type": ["days", "months", "years"],
    "transactions.adjustment_type": ["normal", "abnormal"],
    "transactions.packing_charge_type": ["fixed", "percent"],
    "transactions.res_order_status": ["received", "cooked", "served"],
    "transaction_sell_lines.line_discount_type": ["fixed", "percentage"],
    "transaction_payments.method": ["cash", "card", "cheque", "bank_transfer", "custom_pay_1", "custom_pay_2", "custom_pay_3", "other"],
    "transaction_payments.card_type": ["visa", "master"],
    "cash_registers.status": ["close", "open"],
    "cash_register_transactions.pay_method": ["cash", "card", "cheque", "bank_transfer", "custom_pay_1", "custom_pay_2", "custom_pay_3", "other"],
    "cash_register_transactions.transaction_type": ["initial", "sell", "transfer", "refund"],
    "cash_register_transactions.type": ["debit", "credit"],
    "types_of_services.packing_charge_type": ["fixed", "percent"],
    "sale_processes.default_for_contact_type": ["cf", "pf", "pj", "any"],
    "contacts.tipo": ["PF", "PJ"],
    "contacts.pay_term_type": ["days", "months"],
    "contacts.pgto_padrao": ["pix", "boleto", "cartao", "dinheiro", "transferencia"],
    "contacts.canal_preferido": ["whatsapp", "email", "telefone", "presencial"],
    "contacts.segmento": ["varejo", "atacado", "agencia", "corporativo", "evento", "governo"],
    "contacts.tabela_preco_padrao": ["padrao", "varejo", "atacado", "parceiro"],
    "contacts.primary_role": ["customer", "supplier", "employee", "representative"],
    "contacts.legacy_source": ["outro"]
  }
}
```

### Vestigial (declarado pra ficar VISÍVEL, não escondido)

- `transactions.res_order_status` (`received/cooked/served`) — herança do UltimatePOS
  restaurante (cozinha). Nenhuma gráfica/oficina usa; candidato a erradicação futura
  (mesma classe do `locacao` da Oficina, ADR 0265). Declarado porque o schema o carrega.
- `transaction_payments.card_type` (`visa/master`) — incompleto pro Brasil (sem elo/hiper);
  o fluxo real registra `method=card` e ignora a bandeira. Não inventar bandeira nova sem
  migration + decisão [W].

## Cobertura de código (Salto #3)

`code_paths` ESTREITOS de propósito (SellController + SellPosController): colunas genéricas
(`status`/`type`) super-casam em `app/` largo. Ampliar caminho-a-caminho conforme telas novas.
