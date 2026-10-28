---
last_validated: "2026-10-27"
slug: runbook-financeiro-pagamento-publico
title: "RUNBOOK — /pagar/{token} (cliente paga o título a receber)"
type: runbook
module: Financeiro
page: /pagar/{token}
component: resources/js/Pages/Financeiro/PagamentoPublico.tsx
status: rascunho
updated_at: 2026-10-27
version: 0.1
owner: W
---

# RUNBOOK — /pagar/{token} (cliente paga o título a receber)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para tela
> **nova** e **pública**, sem Blade anterior. Charter:
> [`PagamentoPublico.charter.md`](../../../resources/js/Pages/Financeiro/PagamentoPublico.charter.md) · casos:
> [`PagamentoPublico.casos.md`](../../../resources/js/Pages/Financeiro/PagamentoPublico.casos.md) · SPEC: US-FIN-081.
> Segue o desenho do aceite público do orçamento ([RUNBOOK-aceite-publico](../ComunicacaoVisual/RUNBOOK-aceite-publico.md)).

## 1. Estrutura

- `POST /financeiro/unificado/{tituloId}/link-pagamento` (logado) → `UnificadoController@gerarLinkPagamento` →
  `LinkDePagamentoDoTitulo::gerar`. Devolve `{url, expira_em}`; o token sai só aí.
- `GET /pagar/{token}` → `Public\PagamentoPublicoController@mostrar` → `Inertia::render('Financeiro/PagamentoPublico')`.
  Sem login, fora do AppShellV2.
- `POST /pagar/{token}/{pix|boleto}` → `LinkDePagamentoDoTitulo::pagar`. Se o título já tem cobrança pagável,
  devolve essa; senão emite pelo `PaymentGatewayContract` com `origem_type = fin_titulo`.
- Baixa: o webhook do banco chega em `ReconciliarCobrancaService::marcarPaga` → `CobrancaPaga` →
  `OnCobrancaPagaCreateFinanceiroTitulo`, que fora da empresa dona do SaaS só baixa título com link.

## 2. Segurança (Tier 0)

- Token de 48 caracteres aleatórios; no banco só o sha256. Desconhecido = 404 sem dizer por quê.
- A empresa vem do token; toda consulta filtra pelo `business_id` do link.
- A página não mostra documento nem e-mail do cliente; a URL não carrega dado pessoal.
- `throttle:30,1` por IP; `X-Robots-Tag: noindex, nofollow`; `Referrer-Policy: no-referrer`.
- Flag `FINANCEIRO_LINK_PAGAMENTO_ENABLED` desligada = 404 nas três rotas e listener como antes.

## 3. Valor (regra mestre)

A página e a cobrança usam o `valor_aberto` gravado no título, sem recalcular. Juros e multa de
atraso ficam fora desta versão. A baixa usa `valor_pago_centavos` que o banco informou.

## 4. Fora desta versão

Cartão (o contrato do gateway exige token de cartão gerado no navegador, que não existe aqui),
botão no drawer do Unificado e o portal com todos os títulos do cliente (US-FIN-082).
