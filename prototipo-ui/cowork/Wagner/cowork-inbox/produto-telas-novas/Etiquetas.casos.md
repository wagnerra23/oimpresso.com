# Product/Etiquetas — casos de uso (F1 [CC], 2026-08-21)

Rastreabilidade: contrato `prototipo-ui/contrato/produto-etiquetas.contract.json`; charter `Etiquetas.charter.md`.

## UC-ETQ-01 — Etiquetar um produto recém-comprado
**Dado** que a Larissa recebeu 40 rolos de lona
**Quando** ela busca "lona", clica no resultado e digita 40 em Nº de etiquetas
**Então** a prévia mostra a folha 1 de 2 (modelo de 20 por folha) e o botão diz "Imprimir 2 folha(s)".

## UC-ETQ-02 — Vários produtos numa tacada
**Dado** que ela selecionou 5 produtos no índice
**Quando** clica em **Etiquetas** na barra de seleção
**Então** a tela abre com as 5 linhas já montadas, 8 etiquetas cada, e o índice não perde a seleção anterior de propósito (ela já saiu da lista).

## UC-ETQ-03 — Preço de atacado na etiqueta
**Dado** que o produto tem preço no grupo Atacado
**Quando** ela troca o grupo de preço daquela linha
**Então** só as etiquetas daquele produto mudam de valor; as demais linhas ficam como estavam.

## UC-ETQ-04 — Preço sem imposto
**Quando** ela escolhe "Sem imposto" em Preço a imprimir
**Então** todas as etiquetas da folha passam a mostrar o preço sem imposto, na mesma hora.

## UC-ETQ-05 — Etiqueta enxuta
**Quando** ela desmarca Nome do negócio e Variação
**Então** os dois campos saem da prévia e os corpos deles ficam desabilitados.

## UC-ETQ-06 — Lote e validade
**Dado** um insumo com lote L-2291 e validade 30/11/2026
**Quando** ela preenche lote e validade na linha e marca os dois campos
**Então** a etiqueta imprime "Lote L-2291" e "Val. 30/11/2026"; sem preencher, as linhas não aparecem.

## UC-ETQ-07 — Troca de modelo
**Quando** ela escolhe "Rolo térmico — 60 × 30 mm"
**Então** a prévia volta pra folha 1, muda pra 2 colunas, a cota mostra 60 mm e a contagem de folhas é recalculada.

## UC-ETQ-08 — Folha vazia
**Dado** que ela removeu todos os produtos da lista
**Então** a tabela dá lugar ao estado de primeira-vez (com o caminho pelo índice) e o botão Imprimir fica desabilitado.

## UC-ETQ-09 — Navegar entre folhas
**Dado** 60 etiquetas no modelo de 20 por folha
**Quando** ela clica em "Próxima folha"
**Então** a prévia mostra a folha 2 de 3 e o rodapé diz quantas etiquetas há nesta folha e no total.

## UC-ETQ-10 — Erro de carregamento
**Dado** o Tweak Estado = erro
**Então** a tela mostra o motivo ("o servidor recusou a leitura do catálogo, nada foi enviado") com ação de recarregar — nunca uma folha vazia sem explicação.
