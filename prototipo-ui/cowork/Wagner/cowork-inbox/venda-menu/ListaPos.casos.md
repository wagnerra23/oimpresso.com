# Casos — Lista de POS

- UC-POS-01 Dado 8 vendas de POS, quando abro a lista, então vejo as 8 com total, pago e saldo somados no rodapé.
- UC-POS-02 Dado filtro "Vencido", quando aplico, então só linhas `overdue` restam e o rodapé recalcula.
- UC-POS-03 Dado uma venda quitada, quando abro as ações, então "Adicionar pagamento" não é oferecido.
- UC-POS-04 Dado papel Balcão, quando abro as ações, então "Excluir" aparece dizendo que falta `sell.delete`.
- UC-POS-05 Dado período "Hoje", quando aplico, então só vendas do dia restam.
- UC-POS-06 Dado clique em "Ver detalhe", então abre o drawer com itens que somam exatamente o total da venda.
- UC-POS-07 Dado clique em "Imprimir recibo", então abre a folha com os 9 layouts do legado.
- UC-POS-08 Dado clique em "Devolver venda", então vou pra tela de devolução com a venda no contexto.
