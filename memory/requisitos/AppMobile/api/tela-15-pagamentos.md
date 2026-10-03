# App — Pagamentos (tela 15) — leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/pagamentos?status=todos|pendente|pago|vencido|cancelado&pagina=N` →
`{ itens:[{id, descricao, valor, vencimento, metodo, status, pago_em, link}],
contadores:{todos, pendente, pago, vencido, cancelado}, pagina, tem_mais }`, 20 por página, mais
recente primeiro.

- Fonte: `cobrancas` (PaymentGateway), a mesma tabela da tela web `/financeiro/cobranca`. Cobrança com
  `status = erro` (o gateway recusou, nunca virou cobrança) não aparece.
- Acesso: a regra do Financeiro ([§10.1](tela-06-financeiro.md)); sem ela, `403 sem_permissao`. A área `pagamentos` entra em
  `areas` (§6) com essa regra.
- `status`: `paga` → `pago`; `cancelada` → `cancelado`; `vencida`, ou ainda aberta com vencimento
  passado → `vencido`; o resto (`pending`, `emitida`) → `pendente`.
- `metodo`: cartão → `cartao`; PIX → `pix`; boleto com PIX embutido (bolepix) → `qualquer`; boleto → `boleto`.
- `valor` em reais (a tabela guarda centavos); `descricao` = "Pedido #<nº>" quando a cobrança é de uma
  venda, senão a descrição da cobrança, + " · <cliente>"; `link` = PDF do boleto (`null` sem ele);
  `pago_em` = data/hora do pagamento (ISO com fuso).
- **Escrita** (gerar, consultar, cancelar): ainda **não existe** — vem em PR próprio, pela regra mestre
  (dupla prova + antes→depois + ok do [W]).
