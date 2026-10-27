---
id: resources-js-pages-financeiro-portal-cliente-charter
page: /portal-cliente/{token}
component: resources/js/Pages/Financeiro/PortalCliente.tsx
runbook: memory/requisitos/Financeiro/RUNBOOK-portal-cliente.md
related_us: [US-FIN-082]
related_prototype: prototipo-ui/cowork/Wagner/financeiro-pagamento-publico.jsx (FinPagamentoPublicoPage)
status: draft
---

# Charter — Pages/Financeiro/PortalCliente.tsx

## Persona-alvo

Cliente da empresa (não é usuário do sistema), no celular ou no computador, abrindo o link que recebeu.
Quer saber o que deve, pegar a segunda via e pagar sem criar conta.

## Objetivo desta página

1. **Ver o que está em aberto** — cada título com número, vencimento e valor em aberto gravado.
2. **Segunda via** — o Pix copia e cola, a linha digitável e o PDF já emitidos aparecem no título.
3. **Pagar** — título sem cobrança ganha os botões Pix e boleto.
4. **Histórico** — os últimos títulos pagos.
5. **Saber quando não dá** — link vencido mostra o motivo e nenhum título.

Contrato comportamental: [`PortalCliente.casos.md`](PortalCliente.casos.md).

## Non-Goals

- ❌ Mostrar títulos de outro cliente, documento, e-mail ou dado interno.
- ❌ Recalcular valor na página.
- ❌ Login, cadastro ou PIN nesta versão.
