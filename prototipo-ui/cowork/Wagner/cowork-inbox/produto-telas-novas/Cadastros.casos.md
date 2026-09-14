# Product/Cadastros — casos de uso (F1 [CC], 2026-08-21)

Rastreabilidade: contrato `produto-cadastros.contract.json`; charter `Cadastros.charter.md`.

## UC-CAD-01 — Unidade que faltou no cadastro
**Dado** que o cadastro de produto não tem "Metro cúbico"
**Quando** a Larissa abre Cadastros de apoio → Unidades, clica em Nova unidade, digita nome e símbolo e aperta ↵
**Então** a unidade é salva, o contador da aba vira 7 e o aviso confirma pelo nome.

## UC-CAD-02 — Caixa com mil peças
**Quando** ela marca "Cadastrar como múltiplo de uma unidade base", digita 1000 e escolhe Unidade
**Então** a linha passa a mostrar `1 cx = 1000 Un`.

## UC-CAD-03 — Excluir unidade em uso é recusado
**Dado** que 5 produtos usam m²
**Quando** ela clica em Excluir naquela linha
**Então** a confirmação diz "o servidor recusa esta exclusão — 5 produto(s) usam este registro", aponta a Edição em massa e **não** oferece o botão Excluir.

## UC-CAD-04 — Excluir unidade livre
**Dado** que nenhum produto usa "Quilograma"
**Então** a confirmação diz que sai limpo e o Excluir aparece; depois de confirmar, o contador da aba cai.

## UC-CAD-05 — Ver quem usa
**Quando** ela clica no número 5 da linha m²
**Então** o índice de produtos abre já filtrado por unidade = m², e a sidebar marca "Todos os produtos".

## UC-CAD-06 — Buscar com o teclado
**Quando** ela aperta `/` em qualquer aba
**Então** o cursor vai pra busca daquela aba; `⌘K` continua trocando de tela do módulo.

## UC-CAD-07 — Busca sem resultado
**Quando** ela busca "xyz" em Marcas
**Então** aparece "Nada com esse termo", dizendo quantas marcas existem, com ação de limpar a busca.

## UC-CAD-08 — Novo modelo de variação
**Quando** ela cria a variação "Gramatura" com 3 valores (um por campo, botão Adicionar valor)
**Então** os valores viram chips na linha e o modelo passa a ser oferecido no produto variável.

## UC-CAD-09 — Desativar grupo em vez de excluir
**Dado** o grupo Convênio em uso
**Quando** ela clica em Desativar
**Então** a linha fica esmaecida com selo Inativo, os preços continuam gravados e o grupo sai da exportação de preços.

## UC-CAD-10 — Subcategoria
**Quando** ela cria "Placas ACM" marcando "Cadastrar como subcategoria" e escolhendo Comunicação visual
**Então** a linha aparece indentada com `↳` e "em Comunicação visual".

## UC-CAD-11 — Excluir categoria pai
**Quando** ela exclui "Comunicação visual" (sem produto)
**Então** a confirmação avisa que as subcategorias vão junto, e depois de confirmar Lonas/Fachadas/Placas somem também.

## UC-CAD-12 — Marca de aparelho da Oficina
**Quando** ela marca "Usar como marca de aparelho na Oficina" em 3M
**Então** a coluna "Usa na Oficina" vira Sim — mesma marca do catálogo, sem tabela paralela.

## UC-CAD-13 — Garantia com prazo
**Quando** ela cria "24 meses" com duração 24 e unidade Meses
**Então** a coluna Duração mostra `24 meses` e o prazo passa a ser oferecido no cadastro de produto.

## UC-CAD-14 — Primeira vez
**Dado** o Tweak Estado = vazio
**Então** cada aba explica pra que serve aquele cadastro e oferece o botão de criar o primeiro — nunca uma tabela vazia muda.

## UC-CAD-15 — Carregando e erro
**Dado** o Tweak Estado = carregando
**Então** aparece o esqueleto de 5 linhas; com Estado = erro, aparece o motivo ("nada foi alterado") e a ação de recarregar.

## UC-CAD-16 — Densidade do balcão
**Dado** o Tweak Densidade = compacto
**Então** a tela adensa (linha de tabela em 4px de padding) sem trocar de layout — 1280px sem rolagem horizontal.
