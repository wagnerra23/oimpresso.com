---
dominio: Financeiro — títulos, baixas, caixa, conciliação (Modules/Financeiro + contas core)
fonte_unica: este arquivo é a fonte canônica do vocabulário FINANCEIRO (ADR 0264 G-4, Onda Q3)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0175-conta-bancaria-opcional]
---

# Dicionário de domínio — Financeiro

> **Fonte única do vocabulário financeiro** (Onda Q3). Grounded nas migrations REAIS de
> `Modules/Financeiro` + tabelas de conta do core. O fio canônico (provado por
> `RetencaoLoopE2ETest`, UC-F01..03): **venda → título a receber → baixa → caixa**.

## Conceitos-chave (PT-BR canônico)

- **Título** = `fin_titulos` (`tipo ∈ {receber, pagar}`) — TODA obrigação tem título; venda a
  prazo GERA título (`origem=venda`) via Observer, nunca por digitação manual duplicada.
- **Baixa** = `fin_titulo_baixas` — o "recebi/paguei". Baixa total quita
  (`status=quitado`, `valor_aberto=0`); parcial deixa `status=parcial`.
- **Caixa** = `fin_caixa_movimentos` (`tipo ∈ {entrada, saida, ajuste, transferencia}`) —
  a baixa REGISTRA o movimento (`origem_tipo=titulo_baixa`); caixa não se edita direto.
- **Conciliação** = casar `fin_extrato_lancamentos`/`fin_bank_statement_lines`
  (`pendente → sugerido → conciliado`, ou `ignorado`) com títulos/baixas.
- **Estorno** é de PAGAMENTO/baixa (financeiro); **devolução** é de mercadoria (vendas.md).
- **Sinônimos proibidos**: "conta a receber/pagar" como TABELA (canônico: título; "contas a
  receber" só como NOME DE TELA/visão); "duplicata" (não existe no schema); "quitação
  parcial" (canônico: baixa parcial).

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "Financeiro",
  "migrations_paths": ["Modules/Financeiro/Database/Migrations", "database/migrations"],
  "tables_scope": ["fin_bank_statement_lines", "fin_boleto_remessas", "fin_caixa_movimentos", "fin_categorias", "fin_extrato_lancamentos", "fin_planos_conta", "fin_titulo_baixas", "fin_titulos", "accounts", "account_transactions"],
  "code_paths": ["Modules/Financeiro/Http", "Modules/Financeiro/Services", "Modules/Financeiro/Models", "Modules/Financeiro/Observers", "Modules/Financeiro/Jobs"],
  "vocab": {
    "fin_cobrancas.tipo": ["boleto", "pix_cob", "pix_cobv", "pix_recv", "card"]
  },
  "enums": {
    "fin_titulos.tipo": ["receber", "pagar"],
    "fin_titulos.status": ["aberto", "parcial", "quitado", "cancelado"],
    "fin_titulos.origem": ["manual", "venda", "compra", "despesa", "recurring", "folha", "caixa"],
    "fin_titulos.forma_pagamento": ["dinheiro", "pix", "boleto", "cartao_credito", "cartao_debito", "transferencia", "cheque", "compensacao", "outro"],
    "fin_titulos.aprovacao_status": ["pendente", "aprovado", "rejeitado"],
    "fin_titulo_baixas.meio_pagamento": ["dinheiro", "pix", "boleto", "cartao_credito", "cartao_debito", "transferencia", "cheque", "compensacao", "outro"],
    "fin_caixa_movimentos.tipo": ["entrada", "saida", "ajuste", "transferencia"],
    "fin_categorias.tipo": ["receita", "despesa", "ambos"],
    "fin_planos_conta.tipo": ["ativo", "passivo", "patrimonio", "receita", "despesa", "custo"],
    "fin_planos_conta.natureza": ["debito", "credito"],
    "fin_extrato_lancamentos.origem": ["api", "ofx", "manual"],
    "fin_extrato_lancamentos.status": ["pendente", "sugerido", "conciliado", "ignorado"],
    "fin_bank_statement_lines.tipo": ["credit", "debit", "fee", "transfer", "unknown"],
    "fin_bank_statement_lines.status": ["pendente", "sugerido", "conciliado", "ignorado"],
    "fin_boleto_remessas.status": ["gerado_mock", "gerado", "enviado", "registrado", "pago", "vencido", "cancelado"],
    "accounts.account_type": ["saving_current", "capital"],
    "account_transactions.type": ["debit", "credit"],
    "account_transactions.sub_type": ["opening_balance", "fund_transfer", "deposit"]
  }
}
```

### Nota de fronteira

`transactions.payment_status` (paid/due/partial) é da espinha de VENDAS ([vendas.md](vendas.md));
o Financeiro deriva o estado do TÍTULO a partir dele via Observer — drift entre os dois é bug
(classe auditada pelo `financeiro-bridge-auditor`).
