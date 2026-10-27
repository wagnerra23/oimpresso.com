---
last_validated: "2026-10-27"
slug: runbook-financeiro-portal-cliente
title: "RUNBOOK — /portal-cliente/{token} (cliente vê e paga o que deve)"
type: runbook
module: Financeiro
page: /portal-cliente/{token}
component: resources/js/Pages/Financeiro/PortalCliente.tsx
status: rascunho
updated_at: 2026-10-27
version: 0.1
owner: W
---

# RUNBOOK — /portal-cliente/{token} (cliente vê e paga o que deve)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para tela
> **nova** e **pública**, sem Blade anterior. Charter:
> [`PortalCliente.charter.md`](../../../resources/js/Pages/Financeiro/PortalCliente.charter.md) · casos:
> [`PortalCliente.casos.md`](../../../resources/js/Pages/Financeiro/PortalCliente.casos.md) · SPEC: US-FIN-082.
> Reusa o link de pagamento ([RUNBOOK-pagamento-publico](RUNBOOK-pagamento-publico.md), US-FIN-081).

## 1. Estrutura

- `POST /financeiro/clientes/{contactId}/portal-link` (logado) → `UnificadoController@gerarLinkPortal` →
  `PortalDoCliente::gerar`. Devolve `{url, expira_em}`; o token sai só aí.
- `GET /portal-cliente/{token}` → `Public\PortalClientePublicoController@mostrar` →
  `Inertia::render('Financeiro/PortalCliente')`: títulos a receber em aberto (com a segunda via já emitida)
  e os últimos 20 pagos. Sem login, fora do AppShellV2.
- `POST /portal-cliente/{token}/{tituloId}/{pix|boleto}` → `PortalDoCliente::pagar`: abre um link de
  pagamento do título (sem fechar o do operador) e paga por ele — mesma emissão e mesma baixa da US-FIN-081.

## 2. Segurança (Tier 0)

- Token de 48 caracteres; no banco só o sha256. Desconhecido = 404 sem dizer por quê.
- O token resolve empresa **e** cliente; toda consulta filtra pelos dois. Título de outro cliente ou de
  outra empresa não aparece e não é pago (recusa com mensagem, nada emitido).
- A página mostra o nome do cliente (quem abriu o link é ele) e nada de documento, e-mail ou id interno.
- `throttle:30,1` por IP; `noindex`; `no-referrer`.
- `FINANCEIRO_PORTAL_CLIENTE_ENABLED` desligada = 404. Pagar exige também `FINANCEIRO_LINK_PAGAMENTO_ENABLED`.

## 3. Valor (regra mestre)

O portal não calcula: mostra `valor_aberto`/`valor_total` gravados e paga pelo serviço da US-FIN-081.

## 4. Fora desta versão

Botão "link do portal" na ficha do cliente, envio pelo WhatsApp/e-mail e PDF do boleto gerado aqui.
