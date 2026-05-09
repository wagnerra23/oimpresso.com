---
module: nfe
target_version: 1468
tables_alive: 54
tables_dropped: 0
auto_generated: true
generated_at: 2026-05-09
---

# Módulo Delphi — `nfe`

Tabelas WR Comercial classificadas em `nfe` por heurística de prefixo. Schema reconstruído da versão alvo via `generate-baseline.py`.

## Tabelas vivas (54)

| Tabela | Colunas | Criada | Última mudança |
|---|---|---|---|
| [`NF_CEST`](tabelas/NF_CEST.md) | 5 | v959 | v959 |
| [`NF_CFOP`](tabelas/NF_CFOP.md) | 12 | v511 | v940 |
| [`NF_CNAE`](tabelas/NF_CNAE.md) | 8 | v942 | v1436 |
| [`NF_CST`](tabelas/NF_CST.md) | 13 | v946 | v1423 |
| [`NF_DADOSADICIONAIS`](tabelas/NF_DADOSADICIONAIS.md) | 2 | v1052 | v1052 |
| [`NF_ENTRADA`](tabelas/NF_ENTRADA.md) | 73 | v12 | v1434 |
| [`NF_ENTRADA_CENTRO_TRABALHO`](tabelas/NF_ENTRADA_CENTRO_TRABALHO.md) | 8 | v659 | v751 |
| [`NF_ENTRADA_DESPESA`](tabelas/NF_ENTRADA_DESPESA.md) | 6 | v976 | v978 |
| [`NF_ENTRADA_MANIFESTO`](tabelas/NF_ENTRADA_MANIFESTO.md) | 36 | v1021 | v1428 |
| [`NF_ENTRADA_MANIFESTO_NSU`](tabelas/NF_ENTRADA_MANIFESTO_NSU.md) | 24 | v1409 | v1416 |
| [`NF_ENTRADA_MANIFESTO_REQUISICAO`](tabelas/NF_ENTRADA_MANIFESTO_REQUISICAO.md) | 13 | v1409 | v1412 |
| [`NF_ENTRADA_PARCELAS`](tabelas/NF_ENTRADA_PARCELAS.md) | 2 | v34 | v1427 |
| [`NF_ENTRADA_PRODUTOS`](tabelas/NF_ENTRADA_PRODUTOS.md) | 119 | v6 | v1434 |
| [`NF_ENTRADA_PRODUTOS_AFETADOS`](tabelas/NF_ENTRADA_PRODUTOS_AFETADOS.md) | 10 | v725 | v887 |
| [`NF_ENTRADA_PRODUTOS_COMPOSICAO`](tabelas/NF_ENTRADA_PRODUTOS_COMPOSICAO.md) | 64 | v565 | v736 |
| [`NF_ENTRADA_PRODUTOS_CUSTO_AD`](tabelas/NF_ENTRADA_PRODUTOS_CUSTO_AD.md) | 8 | v596 | v854 |
| [`NF_ENTRADA_TABELA_PRECO`](tabelas/NF_ENTRADA_TABELA_PRECO.md) | 11 | v827 | v1371 |
| [`NF_ENTRADA_TIPO`](tabelas/NF_ENTRADA_TIPO.md) | 0 | v499 | v499 |
| [`NF_ENTRADA_VINCULOS`](tabelas/NF_ENTRADA_VINCULOS.md) | 5 | v1123 | v1165 |
| [`NF_ERROS`](tabelas/NF_ERROS.md) | 12 | v909 | v944 |
| [`NF_ICMS`](tabelas/NF_ICMS.md) | 1 | v1463 | v1463 |
| [`NF_NATUREZA_OPERACAO`](tabelas/NF_NATUREZA_OPERACAO.md) | 8 | v552 | v1137 |
| [`NF_NATUREZA_OPERACAO_PRODGRUPO`](tabelas/NF_NATUREZA_OPERACAO_PRODGRUPO.md) | 117 | v1135 | v1426 |
| [`NF_NBS`](tabelas/NF_NBS.md) | 2 | v1429 | v1429 |
| [`NF_NCM`](tabelas/NF_NCM.md) | 28 | v179 | v1438 |
| [`NF_NCM_ST_UF`](tabelas/NF_NCM_ST_UF.md) | 9 | v1463 | v1463 |
| [`NF_PROVEDOR`](tabelas/NF_PROVEDOR.md) | 39 | v909 | v968 |
| [`NF_REGIME_ESPECIAL_TRIBUTACAO`](tabelas/NF_REGIME_ESPECIAL_TRIBUTACAO.md) | 4 | v552 | v728 |
| [`NF_TIPO_PAGAMENTO`](tabelas/NF_TIPO_PAGAMENTO.md) | 2 | v1108 | v1108 |
| [`NOTA_FISCAL`](tabelas/NOTA_FISCAL.md) | 61 | v304 | v1443 |
| [`NOTA_FISCAL_ENTRADA`](tabelas/NOTA_FISCAL_ENTRADA.md) | 23 | v611 | v1211 |
| [`NOTA_FISCAL_EVENTOS`](tabelas/NOTA_FISCAL_EVENTOS.md) | 32 | v1440 | v1452 |
| [`NOTA_FISCAL_PRODUTO`](tabelas/NOTA_FISCAL_PRODUTO.md) | 273 | v807 | v807 |
| [`SINTEGRA`](tabelas/SINTEGRA.md) | 5 | v583 | v583 |
| [`SINTEGRA_CFOP_CONVERSAO`](tabelas/SINTEGRA_CFOP_CONVERSAO.md) | 7 | v1415 | v1415 |
| [`SINTEGRA_R10`](tabelas/SINTEGRA_R10.md) | 13 | v583 | v583 |
| [`SINTEGRA_R11`](tabelas/SINTEGRA_R11.md) | 9 | v583 | v583 |
| [`SINTEGRA_R50`](tabelas/SINTEGRA_R50.md) | 18 | v583 | v583 |
| [`SINTEGRA_R51`](tabelas/SINTEGRA_R51.md) | 14 | v583 | v583 |
| [`SINTEGRA_R53`](tabelas/SINTEGRA_R53.md) | 16 | v583 | v583 |
| [`SINTEGRA_R54`](tabelas/SINTEGRA_R54.md) | 17 | v583 | v1406 |
| [`SINTEGRA_R60A`](tabelas/SINTEGRA_R60A.md) | 6 | v735 | v735 |
| [`SINTEGRA_R60M`](tabelas/SINTEGRA_R60M.md) | 12 | v735 | v735 |
| [`SINTEGRA_R61`](tabelas/SINTEGRA_R61.md) | 14 | v1088 | v1088 |
| [`SINTEGRA_R70`](tabelas/SINTEGRA_R70.md) | 19 | v583 | v583 |
| [`SINTEGRA_R74`](tabelas/SINTEGRA_R74.md) | 10 | v583 | v1406 |
| [`SINTEGRA_R75`](tabelas/SINTEGRA_R75.md) | 12 | v583 | v1406 |
| [`SPED`](tabelas/SPED.md) | 8 | v882 | v882 |
| [`SPED_0150`](tabelas/SPED_0150.md) | 13 | v882 | v882 |
| [`SPED_0190`](tabelas/SPED_0190.md) | 3 | v882 | v882 |
| [`SPED_0200`](tabelas/SPED_0200.md) | 13 | v882 | v882 |
| [`SPED_C100`](tabelas/SPED_C100.md) | 30 | v882 | v882 |
| [`SPED_C170`](tabelas/SPED_C170.md) | 37 | v882 | v882 |
| [`SPED_C190`](tabelas/SPED_C190.md) | 13 | v882 | v882 |

