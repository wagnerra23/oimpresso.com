---
table: PARAMETROS
module: configuracao
created_at_version: 11
last_modified_version: 398
target_version: 1468
columns_count: 24
foreign_keys_count: 1
foreign_keys:
  CODCLIENTE_PADRAO: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PARAMETROS`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `configuracao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 11;
- **Última mudança:** UPDATE 398;
- **Total colunas (versão 1468):** 24

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODCLIENTE_PADRAO` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CASAS_DECIMAIS_FINANCEIRO` | `VARCHAR(30)` | NULL |  | v11 | v11 |
| 2 | `CASAS_DECIMAIS_QUANTIDADE` | `VARCHAR(30)` | NULL |  | v11 | v11 |
| 3 | `OPCOES` | `INTEGER` | NULL |  | v25 | v25 |
| 4 | `URL_SPC` | `VARCHAR(200)` | NULL |  | v27 | v27 |
| 5 | `URL_COBRANCA` | `VARCHAR(200)` | NULL |  | v27 | v27 |
| 6 | `SEPARADOR_GRADE` | `VARCHAR(1)` | NULL |  | v43 | v43 |
| 7 | `FOTOS_BANCO_IMAGENS` | `VARCHAR(300)` | NULL |  | v56 | v56 |
| 8 | `VENDA_PERGUNTA_COMPOSICAO` | `VARCHAR(1)` | NULL |  | v65 | v65 |
| 9 | `CODCLIENTE_PADRAO` | `VARCHAR(10)` | NULL | → `PESSOAS` | v66 | v66 |
| 10 | `EMAIL_AUTO_CLIENTE` | `VARCHAR(1), ADD EMAIL_AUTO_PRODUCAO VARCHAR(1), ADD TEXTO_EMAIL_CLIENTE VARCHAR(500), ADD TEXTO_EMAIL_PRODUCAO VARCHAR(500)` | NULL |  | v88 | v88 |
| 11 | `ASSUNTO_EMAIL_CLIENTE` | `VARCHAR(100), ADD ASSUNTO_EMAIL_PRODUCAO VARCHAR(100)` | NULL |  | v88 | v88 |
| 12 | `CODBARRAS_FANTASIA` | `VARCHAR(50)` | NULL |  | v89 | v89 |
| 13 | `VERSAO_MIN_OBRIGATORIA` | `VARCHAR(16)` | NULL |  | v90 | v90 |
| 14 | `CONTROLE_CAIXA` | `VARCHAR(1)` | NULL |  | v91 | v91 |
| 15 | `CODBARRAS_LOGO` | `VARCHAR(150)` | NULL |  | v92 | v92 |
| 16 | `NFE_SEM_DADOS_ADICIONAIS` | `VARCHAR(1)` | NULL |  | v96 | v96 |
| 17 | `DT_ATUALIZACAO` | `TIMESTAMP` | NULL |  | v101 | v101 |
| 18 | `VENDA_CALCULA_VOUTRO` | `VARCHAR(1)` | NULL |  | v103 | v103 |
| 19 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v104 | v104 |
| 20 | `LIMITE_DESCONTO` | `DOUBLE PRECISION` | NULL |  | v110 | v110 |
| 21 | `NOTAFISCAL_SERVICO` | `integer` | NULL |  | v211 | v211 |
| 22 | `NOTAFISCAL_SERVICO_HOMOLOGACAO` | `integer` | NULL |  | v243 | v243 |
| 23 | `NOTAFISCAL_CUPOM` | `INTEGER` | NULL |  | v398 | v398 |
| 24 | `NOTAFISCAL_CUPOM_HOMOLOGACAO` | `INTEGER` | NULL |  | v398 | v398 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 11 | DROP_COL | - CASAS_DECIMAIS |
| 11 | ADD_COL | + CASAS_DECIMAIS_FINANCEIRO VARCHAR(30) |
| 11 | ADD_COL | + CASAS_DECIMAIS_QUANTIDADE VARCHAR(30) |
| 25 | ADD_COL | + OPCOES INTEGER |
| 27 | ADD_COL | + URL_SPC VARCHAR(200) |
| 27 | ADD_COL | + URL_COBRANCA VARCHAR(200) |
| 43 | ADD_COL | + SEPARADOR_GRADE VARCHAR(1) |
| 56 | ADD_COL | + FOTOS_BANCO_IMAGENS VARCHAR(300) |
| 65 | ADD_COL | + VENDA_PERGUNTA_COMPOSICAO VARCHAR(1) |
| 66 | ADD_COL | + CODCLIENTE_PADRAO VARCHAR(10) |
| 88 | ADD_COL | + EMAIL_AUTO_CLIENTE VARCHAR(1), ADD EMAIL_AUTO_PRODUCAO VARCHAR(1), ADD TEXTO_EMAIL_CLIENTE VARCHAR(500), ADD TEXTO_EMAIL_PRODUCAO VARCHAR(500) |
| 88 | ADD_COL | + ASSUNTO_EMAIL_CLIENTE VARCHAR(100), ADD ASSUNTO_EMAIL_PRODUCAO VARCHAR(100) |
| 89 | ADD_COL | + CODBARRAS_FANTASIA VARCHAR(50) |
| 90 | ADD_COL | + VERSAO_MIN_OBRIGATORIA VARCHAR(16) |
| 91 | ADD_COL | + CONTROLE_CAIXA VARCHAR(1) |
| 92 | ADD_COL | + CODBARRAS_LOGO VARCHAR(150) |
| 96 | ADD_COL | + NFE_SEM_DADOS_ADICIONAIS VARCHAR(1) |
| 101 | ADD_COL | + DT_ATUALIZACAO TIMESTAMP |
| 103 | ADD_COL | + VENDA_CALCULA_VOUTRO VARCHAR(1) |
| 104 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 110 | ADD_COL | + LIMITE_DESCONTO DOUBLE PRECISION |
| 211 | ADD_COL | + NOTAFISCAL_SERVICO integer |
| 243 | ADD_COL | + NOTAFISCAL_SERVICO_HOMOLOGACAO integer |
| 398 | ADD_COL | + NOTAFISCAL_CUPOM INTEGER |
| 398 | ADD_COL | + NOTAFISCAL_CUPOM_HOMOLOGACAO INTEGER |

