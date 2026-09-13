# Product/Importacao + AtualizarPreco — casos de uso (F1 [CC], 2026-08-21)

Rastreabilidade: contratos `produto-importacao.contract.json` e `produto-atualizar-preco.contract.json`; charter `Importacao.charter.md`.

## UC-IMP-01 — Planilha boa
**Dado** um .csv com 120 linhas e 37 colunas
**Quando** o Wagner escolhe o arquivo
**Então** a conferência mostra "120 linha(s) sem problema aparente", as 5 primeiras linhas, e **Enviar planilha** fica habilitado.

## UC-IMP-02 — Coluna a menos
**Dado** um .csv onde 3 linhas têm 36 colunas
**Então** a tela lista linha por linha ("linha 14 — 36 coluna(s), o modelo tem 37"), diz que o servidor recusa a planilha inteira e mantém Enviar desabilitado.

## UC-IMP-03 — Obrigatório em branco
**Dado** uma linha sem Unidade
**Então** o erro aparece como "linha 27 — “Unidade” em branco" e a planilha é bloqueada.

## UC-IMP-04 — Arquivo .xlsx
**Quando** ele escolhe um .xlsx
**Então** a tela diz que a conferência linha a linha é do servidor, sugere salvar como .csv, e permite enviar.

## UC-IMP-05 — Baixar modelo
**Quando** ele clica em Baixar modelo
**Então** o aviso confirma o nome do arquivo modelo (no F3, baixa `import_products_csv_template.xls`).

## UC-IMP-06 — Instruções conferíveis
**Então** a tabela de instruções tem as 37 colunas numeradas, com obrigatório/opcional e os valores aceitos escritos (`single`/`variable`, `inclusive`/`exclusive`, `days`/`months`).

## UC-IMP-07 — Estoque inicial
**Dado** um .csv de 6 colunas com SKU inexistente
**Então** o navegador aceita (SKU existe é validação do servidor) e a instrução diz que o SKU precisa existir — a recusa vem do backend, nomeada.

## UC-PRC-01 — Ciclo completo de preço
**Quando** a Eliana clica em Exportar preços atuais, edita a coluna Atacado e devolve o arquivo
**Então** a tela mostra "N linha(s), 0 erro(s)" e **Aplicar preços** fica habilitado.

## UC-PRC-02 — Grupo inativo
**Dado** o grupo Funcionário desativado
**Então** ele não aparece nos chips de grupo da tela nem na exportação, e a instrução 4 diz onde ativar.

## UC-PRC-03 — Atalho pro cadastro
**Quando** ela clica em Gerenciar grupos
**Então** vai pra Cadastros de apoio → Grupos de preço, e a sidebar acompanha a tela.

## UC-PRC-04 — Preço na tela em vez de planilha
**Quando** ela clica em Editar preço na tela
**Então** abre Preços por grupo de venda (a tela do `add-selling-prices`) — planilha é pro lote, tela é pro caso único.
