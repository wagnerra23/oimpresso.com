---
slug: relatorios-runbook-contatos
title: "Relatórios — Runbook do relatório Clientes e fornecedores"
type: runbook
module: Relatorios
tela: Relatorios/Contatos/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Clientes e fornecedores

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`contact`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/customer-supplier?tela=nova` responde Inertia `Relatorios/Contatos/Index`: o extrato por contato da
mesma consulta do DataTable da Blade (compras, devoluções, vendas, saldo inicial devido e total devido), 25 por
página, e o rodapé por coluna somando **só a página**. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `contacts_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/ContatosReportPageTest.php`: colunas e devido por três caminhos (o JSON do
   DataTable da Blade, as props da Page e a soma direta em `transactions`/`transaction_payments`), paginação e o
   isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaContatos()` e a conta do devido sai do `addColumn('due')`
   para `devidoDoContato()`, os dois usados pela Blade e pela tela nova. O ramo `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando a 1ª página com a Blade.

## 4. Achado de valor (decisão [W], não corrigido)

A conta do devido **ignora o desconto de razão de venda**: o código original lia uma variável local inexistente
(`$total_ledger_discount_sell ?? 0`) em vez de `$row->total_ledger_discount_sell`. Está em produção. A migração
**preserva** o comportamento (os dois números seguem iguais); corrigir muda o devido de quem tem desconto de venda e
é decisão [W] com tabela antes→depois. A correção, quando decidida, é só em `devidoDoContato()`.

## 5. Divergências declaradas

- Período padrão = ano fiscal da sessão, como a Blade (o daterangepicker dela nasce assim).
- O nome do contato não é link para a ficha (a Blade abre em nova aba) — fica para depois.

## 6. Falta para o cutover (F5 — decisão [W])

O link do contato, a decisão sobre o desconto de venda, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
