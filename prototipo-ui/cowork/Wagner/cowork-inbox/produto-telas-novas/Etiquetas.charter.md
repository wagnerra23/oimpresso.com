# Product/Etiquetas — charter (F1 [CC], 2026-08-21)

**Norte.** A etiqueta é a única saída do catálogo que vira papel. A tela existe pra montar uma folha correta de primeira: quem etiqueta o estoque não volta pra conferir na tela, volta pra reimprimir — e reimpressão é papel perdido.

**Origem.** `resources/views/labels/show.blade.php` + `labels/partials/show_table_rows.blade.php` + `public/js/labels.js`. Rota do protótipo: `prod-etiquetas`.

## Regras
- **R1** Uma linha por produto na folha, com nº de etiquetas próprio. O produto entra por busca (nome ou SKU) ou vem selecionado do índice (BulkBar → Etiquetas).
- **R2** O preço impresso é o do **grupo de preço da linha**, não o preço de tabela global. Trocar o grupo muda a etiqueta daquele produto só.
- **R3** `Com imposto` / `Sem imposto` é escolha explícita da folha inteira (o `price_type` do blade). Não há default silencioso: o campo aparece sempre.
- **R4** Cada informação da etiqueta tem liga/desliga e corpo em pt. Campo desligado não imprime e o corpo dele fica desabilitado.
- **R5** O modelo de etiqueta define medida em mm e quantas cabem por folha; a prévia pagina por **folha real** e o botão diz quantas folhas vão sair.
- **R6** Lote, validade e data de embalagem só aparecem na etiqueta se preenchidos na linha — etiqueta não imprime rótulo vazio.
- **R7** A prévia é uma **prova de impressão**: marcas de corte, grid e tira CMYK (print-craft do DS), com a cota da largura da etiqueta.
- **R8** Sem produto na lista o estado é explicado (o que é a tela e de onde vêm os produtos), nunca uma folha em branco.

## Non-goals
- Não é editor de layout de etiqueta (posição de campo, logo, fonte por campo) — isso é configuração do negócio.
- Não é geração de código de barras real: o traço da prévia é representação; o `barcode_setting` do blade ainda é do backend.
- Não imprime etiqueta de OS nem de entrega — outra tela, outro dono.

## Achados do legado
- **A1** O blade tem os campos personalizados do produto na etiqueta (`custom_labels`), montados em loop de 4 em 4 colunas. Não portei: depende de saber quais campos existem no cliente piloto.
- **A2** O blade só pré-visualiza depois de clicar em **Preview**; aqui a prévia é contínua — menos clique, mesmo resultado.
- **A3** Havia **duas** etiquetas no protótipo (modal no índice + esta tela). O modal foi aposentado nesta leva; o índice agora manda pra cá.
