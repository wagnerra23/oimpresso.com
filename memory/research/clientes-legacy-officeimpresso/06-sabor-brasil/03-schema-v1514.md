---
slug: 06-sabor-brasil-schema-v1514
cliente: Cliente_E360F9
business_id_oimpresso: 199
date: 2026-10-29
controlador: Wagner
---

# Schema Firebird 1514 do Sabor Brasil × o que os importadores leem

> Etapa 0.1 do plano de migração (perfil e plano chegam no PR #10156, `01-perfil.md` e `02-plano-migracao.md` desta pasta).
>
> **LGPD:** só estrutura e contagem de linhas. Sem valor em R$, sem CNPJ/CPF, sem nome de cliente final.
>
> **Como foi medido (2026-10-29, só SELECT):** para cada importador, a lista de colunas que ele pede ao Firebird (as listas canônicas do próprio arquivo, mais as colunas dos `WHERE` e `JOIN`) foi cruzada com `RDB$RELATION_FIELDS` em três bancos:
>
> | Apelido HKCU | `VERSAO_BANCO` | Papel |
> |---|---:|---|
> | `Camargo` (Sabor Brasil) | **1514** | alvo |
> | `Produart` | 1474 | topo do intervalo que os importadores conhecem |
> | `Vargas` | 1507 | a versão mais próxima de 1514 que estava acessível |
>
> O `UpdateSQL.txt` local (`D:/Programas/WR Comercial/Resources/UpdateSQL.txt`) para no bloco 1477. O DDL de 1478 a 1514 não está disponível, então coluna **renomeada** aqui é uma inferência por contagem de linhas preenchidas, nunca um `ALTER ... TO` lido.

## Resultado em uma linha

**A versão 1514 não quebra nenhum importador.** Os 10 importadores com `--dry-run` rodaram até o fim contra `Camargo`, sem erro, e as 3 consultas Firebird do `import-tabelas-preco.py` também rodaram (ele não tem dry-run). Tirando `PRODUTO.CUSTO_FABR`, que nenhum importador usa, toda coluna pedida que falta na 1514 **já faltava na 1474**: são lacunas antigas dos importadores, e o adapter delas (`SELECT FIRST 1 *` + `NULL` no lugar) já existia. A única quebra real era do leitor (`lib/firebird_reader.py`), e ela não depende da versão (seção "Ajustes no leitor").

## Por importador

"Ausente" = o importador pede e a tabela não tem na 1514. "Também na 1474" diz se a lacuna é anterior a esta versão.

| Importador | Tabela (linhas na 1514) | Ausentes na 1514 | Também na 1474? | Efeito hoje |
|---|---|---|---|---|
| `import-empresas.py` | `EMPRESA` (6) | — | — | lê por `SELECT *`; 4 empresas com `ATIVO='S'` |
| `import-produtos.py` | `PRODUTO` (141) | `CUSTO_FABR`, `CUSTO_LOJA`, `ESTOQUE`, `VALOR_PRAZO` | `CUSTO_FABR` **não** (sumiu na 1514); os outros 3 sim | `CUSTO_FABR` e `CUSTO_LOJA` não são lidos no mapeamento. Sem `ESTOQUE`, todo produto sai com `enable_stock = 0` |
| `import-contacts-from-venda.py` | `VENDA` (4.089) | — | — | quebrava no import (ver abaixo) |
| `import-contacts-from-nfe.py` | `NOTA_FISCAL_ENTRADA` (957) | `DT_ALTERACAO` | sim | só o modo delta perde o filtro; quebrava no import (ver abaixo) |
| `import-tabelas-preco.py` | `PRODUTO_TABELA` (74) · `PRODUTO_TABELA_PRECO` (707) · `PESSOAS` (434) | — | — | as 3 consultas rodam: 74 tabelas, 706 preços com valor, 154 clientes vinculados |
| `import-vendas.py` | `VENDA` (4.089) · `EQUIPAMENTO_VEICULO` (0) | `CPF_CNPJ_RESPONSAVEL` | sim | vira `NULL`; o documento do cliente vem de `RESPONSAVEL_CNPJCPF`, que existe |
| `import-venda-itens.py` | `VENDA_PRODUTO` (17.014) · `VENDA` | `ACRESCIMO_DESCONTO`, `VICMS`, `VICMSST`, `IPI_VIPI` | sim | `item_tax` sai **zero** em todo item (achado 1) |
| `import-notas-fiscais.py` | `NOTA_FISCAL` (2.745) | `NF_SEQUENCIA_EVENTO_CCE`, e as opcionais `NF_SERIE`, `VALOR_NF`, `VALOR_TOTAL` | sim | não quebra (as opcionais já eram filtradas). Mas sem `NF_SERIE` a série cai no padrão `'1'`, e a tabela tem `SERIE`; sem `VALOR_TOTAL`/`VALOR_NF`, o total não vem, e a tabela tem `VALOR` e `TOTAL` (achado 4) |
| `import-financeiro.py` | `FINANCEIRO` (3.226) | — | — | todas as 24 colunas presentes |
| `import-estoque.py` | `PRODUTO_ESTOQUE` (211) | `CODIGO`, `OIMPRESSO_CODIGO`, `OIMPRESSO_DT_ALTERACAO` | sim | as 3 são de controle de sincronização; saldo lido de `ESTOQUE`, que existe |
| `import-compras.py` | `NOTA_FISCAL_ENTRADA` (957) · `NF_ENTRADA_PRODUTOS` (54) | cabeçalho: `DT_ALTERACAO`, `NF_VDESCONTO`, `NF_VFRETE`, `NF_VICMS`, `NF_VIPI`, `OBSERVACAO`, `OIMPRESSO_CODIGO`, `OIMPRESSO_DT_ALTERACAO`, `PESSOA_FUNCIONARIO_CODIGO` · itens: `NF_CFOP`, `NF_CSTICMS`, `NF_VDESCONTO`, `NF_VIPI` | sim | lê a tabela de cabeçalho errada (achado 2) |

## Colunas que sumiram, apareceram ou mudaram de nome (1514 × 1474)

Só nas tabelas que os importadores leem, mais `BOLETOS` e `COMISSAO_FINANCEIRO`, que o plano cita. Nas colunas renomeadas, a evidência é a contagem de linhas preenchidas (diferente de nulo e de zero), não o valor.

| Tabela | Sumiram | Novas | Renomeada? |
|---|---|---|---|
| `PRODUTO` | `CUSTO_FABR`, `COMPRA_QUANT` | `VPRAZO`, `VATACADO`, `VMINIMO_VENDA`, `MODO_VALOR_MINIMO_VENDA`, `VCUSTO_VENDA_MINIMO`, `VCOMPRA_TOTAL`, `VPOR_PECA`, `COMPOSICAO`, `GRADE`, `TABELADEPRECO`, `FLUXO` | `CUSTO_FABR` → `CUSTO`, provável: na 1474 as duas têm o mesmo número de linhas preenchidas (1.106 × 1.105 de 1.747). As novas `VPRAZO` e `VATACADO` têm **0** linhas preenchidas na 1514: não substituem nada ainda |
| `VENDA_PRODUTO` | `CODPRODUTO_TIPO` | `VALOR_PRAZO`, `VALOR_ATACADO`, `VALOR_MINIMO_VENDA`, `VALOR_POR_PECA`, `CUSTO_VENDA_MINIMO`, `TIPO_DESCONTO`, `CALC_PDESC`, `CALC_POUTRO`, `CALC_VOUTRO`, `PERC_ACRESC_PRAZO`, `PERC_DESC_ATACADO`, `TABELA_PRECO_FIXO`, `TABELA_PRECO_POR_QTDADEPECA`, `COMPOSICAO` | não. `VALOR_PRAZO` agora existe (o importador já a pedia), mas tem 0 linhas preenchidas; `CALC_PDESC` tem 631 |
| `NF_ENTRADA_PRODUTOS` | `CUSTO_LOJA`, `PERC_DESC_ATACADO`, `CODCENTRO_TRABALHO` | `VALOR_PRAZO`, `VALOR_ATACADO`, `PCUSTO`, `PFRETE`, `VCOMPRA_TOTAL`, `VCUSTO_ANTERIOR`, `VCUSTO_VENDA_MINIMO`, `VCUSTO_VENDA_TOTAL`, `VPOR_PECA`, `ESTOQUE_ANTERIOR`, `CALC_PDESC`, `CALC_POUTRO`, `CALC_VENDA_PEXTRA`, `APROVAR_CONVERSAO`, `COMPOSICAO` | não medido (o importador não usa nenhuma delas) |
| `FINANCEIRO` | — | `COBRANCA_ID_TITULO`, `COBRANCA_STATUS`, `COBRANCA_QRPIX`, `COBRANCA_PROVIDER`, `COBRANCA_CODCONTA`, `COBRANCA_VENCIMENTO`, `BAIXA_ORIGEM`, `BOLETO_ID_BANCO` | não. As três de cobrança medidas têm 0 linhas preenchidas: o boleto deste cliente ainda vive em `BOLETOS` |
| `VENDA` | — | `CODCONTRATO`, `MDF_TEM_FRETE_CONTRATADO`, `MDF_PERCURSO_UFS` | não |
| `PESSOAS` | `IS_TRA`, `SEQUENCIA_TRA` | `RNTRC` | não |
| `EMPRESA`, `NOTA_FISCAL`, `NOTA_FISCAL_ENTRADA`, `PRODUTO_ESTOQUE`, `PRODUTO_TABELA`, `PRODUTO_TABELA_PRECO`, `EQUIPAMENTO_VEICULO`, `BOLETOS` (1.650), `COMISSAO_FINANCEIRO` (1.643) | — | — | iguais à 1474 |

## Ajustes no leitor (`lib/firebird_reader.py`)

Nenhuma diferença da 1514 pediu adaptação por versão: os importadores já detectam as colunas presentes em tempo de execução. O que mudou no leitor:

1. **`has_column()` e `get_column_names()`.** `import-contacts-from-venda.py` e `import-contacts-from-nfe.py` importam `has_column` do leitor, que não a definia. Os dois saíam com `ImportError` antes de abrir conexão, em **qualquer** versão. Controle: com o leitor antigo, `--help` dos dois sai `rc=1` com `cannot import name 'has_column'`; com o novo, `rc=0`, e o dry-run de cada um roda até o fim.
2. **`get_table_columns()`.** Usava `AS position`, palavra reservada no Firebird 3; a consulta falhava com `Token unknown`. Nenhum importador a chama hoje; foi corrigida porque esta medição precisou dela.

## Achados que não são da versão 1514 (para decidir antes da Etapa 4)

Nenhum foi corrigido aqui: mudam valor ou estoque importado e seguem a regra mestre (dupla prova, antes → depois, aprovação [W]).

1. **Imposto do item de venda sai zero.** `import-venda-itens.py` soma `VICMS + VICMSST + IPI_VIPI` em `item_tax`, mas essas colunas não existem em `VENDA_PRODUTO` desde pelo menos a 1474. O imposto está em `NF_VICMS` (8.580 itens preenchidos), `NF_VICMSST` (1.644) e `NF_IPI_VIPI` (0). Para um cliente que vende com ICMS-ST, todo item chega com imposto zero.
2. **Compras leem a tabela de cabeçalho errada.** `import-compras.py` lê o cabeçalho de `NOTA_FISCAL_ENTRADA` (957 linhas, notas recebidas pelo manifesto) e os itens de `NF_ENTRADA_PRODUTOS` por `CODNF_ENTRADA`. Os 54 itens ligam todos a `NF_ENTRADA` (23 notas) e nenhum a `NOTA_FISCAL_ENTRADA`. No Produart (1474), os 3.103 itens também ligam todos a `NF_ENTRADA`. O dry-run mostra o efeito: 957 compras sem nenhum item, e as 23 compras de verdade ficam de fora.
3. **Produto sem controle de estoque.** Sem `PRODUTO.ESTOQUE`, `import-produtos.py` grava `enable_stock = 0` em todos; `import-estoque.py` depois grava o saldo de 107 produtos, que não aparece em tela de estoque se o produto não controla estoque.
4. **Série e total da NF-e emitida.** `import-notas-fiscais.py` procura `NF_SERIE`, `VALOR_TOTAL` e `VALOR_NF`, que não existem em `NOTA_FISCAL` (nem na 1474); a tabela tem `SERIE`, `VALOR` e `TOTAL`. Qual delas é o total da nota precisa de conferência contra o XML antes de mapear.
5. **`import-venda-itens.py` não prova nada em dry-run.** Sem MySQL ele não acha a venda correspondente e pula todos os itens. A prova de que os itens chegam (o Martinho perdeu 92%) só sai rodando contra um MySQL local com as vendas já importadas.

## Dry-run contra `Camargo` (2026-10-29, `--target dry-run --target-business 199`)

| Importador | Saída | Linhas lidas | Resultado |
|---|---:|---:|---|
| `import-empresas.py` | `rc=0` | 4 | 4 inserts, 0 erros |
| `import-produtos.py` | `rc=0` | 106 (só `ATIVO='S'`) | 106 inserts, 0 erros |
| `import-contacts-from-venda.py` | `rc=0` | — | 455 inserts, 0 erros |
| `import-contacts-from-nfe.py` | `rc=0` | 16 fornecedores distintos | 16 inserts, 0 erros |
| `import-tabelas-preco.py` | sem dry-run; as 3 consultas Firebird rodam | 74 · 706 · 154 | 0 erros |
| `import-vendas.py` | `rc=0` | 4.089 | 4.089 inserts, 0 erros |
| `import-venda-itens.py` | `rc=0` | 17.013 (o `JOIN` com `VENDA` deixa 1 dos 17.014 de fora) | todos pulados sem venda no MySQL (achado 5), 0 erros |
| `import-notas-fiscais.py` | `rc=0` | 2.745 | 2.143 inserts, 602 pulados, 0 erros |
| `import-financeiro.py` | `rc=0` | 3.226 | 3.095 títulos, 131 pulados (96 de venda cancelada), 0 erros |
| `import-estoque.py` | `rc=0` | 211 | 107 saldos, 0 erros |
| `import-compras.py` | `rc=0` | 957 | 957 compras, 0 itens (achado 2), 0 erros |

O `import-tabelas-preco.py` não tem modo dry-run (grava no MySQL direto) e outra sessão está ampliando esse arquivo, então ele não foi tocado. Ele foi exercitado executando, só leitura, as 3 consultas Firebird extraídas do próprio arquivo.
