# Produto — casos de uso (`.casos.md`)

Cada UC = um caminho que precisa funcionar no F3. `⌨` = tem atalho. `↗` = atravessa módulo.

## Lista e busca
- **UC-01** filtrar por tipo, categoria, unidade, imposto, marca, local, situação e não-para-venda; cada filtro ativo aparece como chip removível.
- **UC-02** ⌨ buscar por nome ou SKU com `/`; limpar pelo chip de busca.
- **UC-03** ordenar por produto, compra, venda, estoque, tipo, categoria, marca, SKU (asc/desc, `aria-sort`).
- **UC-04** paginar 10/25/50 mantendo filtro e ordenação.
- **UC-05** escolher colunas visíveis (12 disponíveis, persistente) e voltar ao padrão.
- **UC-06** alternar densidade compacto/confortável (persistente).
- **UC-07** selecionar com Shift+clique e agir em massa: edição em massa, adicionar/remover do local, desativar, excluir, etiquetas.
- **UC-08** exportar catálogo completo e exportar só o resultado filtrado.
- **UC-09** ver detalhe no drawer: dados, variações/composição, estoque por local, fiscal, prateleira.

## Cadastro
- **UC-10** ⌨ `N` abre produto novo; cadastro rápido pelo modal quando só se quer faturar agora.
- **UC-11** produto único: compra sem imposto → % lucro → venda → com imposto, recalculando ao vivo.
- **UC-12** produto variável: nome da variação + valores, sub-SKU gerado ao vivo, Enter abre a linha seguinte.
- **UC-13** composição: buscar itens, quantidade por item, total líquido e venda por margem.
- **UC-14** validação inline: nome obrigatório, SKU único, compra > 0, margem negativa avisada, NCM de 8 dígitos.
- **UC-15** rascunho autosalvo e recuperado depois de recarregar; aviso ao sair com alteração pendente.
- **UC-16** unidade secundária com multiplicador ("1 cx = 12 Un").
- **UC-17** prateleira por local; campos personalizados 1–7; validade em dias/meses; IMEI/série.
- **UC-18** salvar / salvar e adicionar outro / salvar e lançar estoque inicial / salvar e definir preços por grupo.

## Estoque e preço
- **UC-19** lançar estoque inicial por local com custo, validade (DatePicker) e lote.
- **UC-20** histórico por produto, local e variação, com período (PeriodBar) filtrando de verdade; totais de entrada/saída e cobertura sobre o alerta.
- **UC-21** preços por grupo de venda, fixo ou percentual, por variação.
- **UC-22** edição em massa: categoria, subcategoria, marca, imposto, locais e preços por variação.

## Destrutivo e impressão
- **UC-23** desativar pede confirmação e explica que o histórico fica.
- **UC-24** excluir pede confirmação e é recusado quando há movimento de estoque.
- **UC-25** etiquetas: N cópias, com ou sem preço, folha como prova de impressão (marcas de corte + tira CMYK), impressão em papel.

## Atravessa módulo ↗
- **UC-26** ↗ "Comprar este produto" abre Compras com o item no pedido novo.
- **UC-27** ↗ "Transferir entre locais" abre a transferência no Estoque.
- **UC-28** ↗ "Usar em uma OS" abre a OS com o produto no orçamento.
- **UC-29** ↗ composição gera OP no módulo de Produção.
- **UC-30** ↗ aba Fiscal abre o produto no módulo Fiscal.
- **UC-31** ↗ reposição: selecionar itens e gerar rascunho de compra em lote.
- **UC-32** ↗ chegando de outro módulo, a faixa de contexto diz de onde veio e devolve com o produto escolhido.

## Análises
- **UC-33** reposição por dias de cobertura, com sugestão de quantidade e custo.
- **UC-34** margem por produto, alertando abaixo de 25% e lucro ≤ 0.
- **UC-35** curva ABC por receita (Chart do DS), com % acumulado e classe.
- **UC-36** produtos sem giro, com dinheiro parado.
- **UC-37** duplicatas de nome/SKU (catálogo migrado do Firebird).

## A11y / dispositivo
- **UC-38** navegar a tela inteira por teclado: cabeçalhos ordenáveis focáveis, menu de ações por ↑/↓/↵/esc, tooltips no foco.
- **UC-39** no tablet do técnico, alvos ≥44px e nenhuma ação exclusiva de hover.
