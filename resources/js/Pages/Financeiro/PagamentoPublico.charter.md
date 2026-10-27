---
id: resources-js-pages-financeiro-pagamento-publico-charter
page: /pagar/{token}
component: resources/js/Pages/Financeiro/PagamentoPublico.tsx
runbook: memory/requisitos/Financeiro/RUNBOOK-pagamento-publico.md
related_us: [US-FIN-081]
related_prototype: n/a (tela pública fora do AppShellV2; o protótipo gerado no DS entra pelo PR #10055 em cowork/Wagner, financeiro-pagamento-publico.jsx, e a âncora passa a apontar para ele depois do merge)
status: draft
---

# Charter — Pages/Financeiro/PagamentoPublico.tsx

## Persona-alvo

Cliente da empresa (não é usuário do sistema), quase sempre no celular, abrindo o link de cobrança que
recebeu. Quer ver quanto deve e pagar sem criar conta.

## Objetivo desta página

1. **Conferir** — empresa, número do título, vencimento e o valor em aberto gravado.
2. **Pagar** — escolher Pix ou boleto; a página mostra o Pix copia e cola, a linha digitável e o link do PDF.
3. **Saber quando não dá** — título já pago, link vencido ou título indisponível mostram o motivo e não
   mostram os botões.

Contrato comportamental: [`PagamentoPublico.casos.md`](PagamentoPublico.casos.md).

## Non-Goals

- ❌ Mostrar documento, e-mail ou dado interno do cliente ou da empresa.
- ❌ Recalcular valor na página: o valor é o `valor_aberto` gravado.
- ❌ Login, cadastro ou PIN nesta versão.

## Fora desta versão

Cartão de crédito e o portal com todos os títulos do cliente (US-FIN-082).
