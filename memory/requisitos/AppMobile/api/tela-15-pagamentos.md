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
- **Escrita** (gerar, consultar, cancelar): [§10.6](tela-15-pagamentos.md).

## App — Pagamentos (tela 15) — escrita (mexe em valor: regra mestre)

Decisões [W] 2026-10-02: **valor = saldo em aberto** do documento; **credencial = a padrão** do business.

- `GET /api/app/pagamentos/referencias` → `{ itens:[{tipo:"pedido"|"orcamento", id, rotulo, cliente, valor}] }`:
  pedidos (vendas finais) e orçamentos (rascunhos) que o usuário vê na lista de Pedidos (§2), com
  cliente e saldo em aberto > 0, até 30 de cada, mais recentes primeiro. `valor` = o saldo, só para exibir.
- `POST /api/app/pagamentos { referencia:{tipo, id}, metodo, vencimento_dias: 3|7|15 }` → `201` com o
  item no formato da lista ([§10.5](tela-15-pagamentos.md)). **O app nunca manda valor** (se mandar, é ignorado).
  - Valor = **saldo em aberto** = `final_total` − pago, com pago = `TransactionUtil::getTotalPaid`
    (devolução ao cliente subtrai do pago). Saldo zero → 422.
  - `metodo`: `boleto` e `qualquer` → boleto (o banco pode embutir PIX); `pix` → PIX com vencimento
    (`cobv`); `cartao` → 422 (cartão exige token e não sai do app).
  - Conta/credencial: `ContaBancaria::padraoParaCobranca` — a primeira conta do business com
    credencial de gateway (a mesma regra da emissão pela venda na web).
  - `422 {erro:"validacao", campos}` · `404 nao_encontrado` (documento que o usuário não vê ou de
    outra empresa) · `409 ja_existe` com `item` (cobrança em aberto e no prazo, **ou já paga** — o
    pagamento no gateway não é lançado na venda, então cobrar de novo seria em dobro) ·
    `503 sem_configuracao` (sem conta com gateway) · `503 provedor_indisponivel` (o banco falhou;
    a tentativa fica registrada como `erro`, fora da lista, e não bloqueia tentar de novo).
- `POST /api/app/pagamentos/{id}/consultar` → item atualizado. Se o banco diz paga, aplica a mesma
  reconciliação do webhook (`ReconciliarCobrancaService::marcarPaga`); senão continua como estava.
- `POST /api/app/pagamentos/{id}/cancelar` → item atualizado (cancelada no banco) · `409 nao_cancelavel`
  se já paga · cancelada de novo devolve o item sem chamar o banco.
- Acesso: a regra do Financeiro ([§10.1](tela-06-financeiro.md)) + o documento visível ao usuário. Cobrança/documento de outra
  empresa → 404. Limite: 20 gerações/min, 30 consultas/min.
