---
table: PRODUCAO_MOVIMENTO
module: producao
created_at_version: 1074
last_modified_version: 1149
target_version: 1468
columns_count: 18
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `PRODUCAO_MOVIMENTO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `producao` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 1074;
- **Última mudança:** UPDATE 1149;
- **Total colunas (versão 1468):** 18

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | Adicionada em | Última mudança |
|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL | v1074 | v1074 |
| 2 | `CODPRODUCAO` | `INTEGER` | NULL | v1074 | v1074 |
| 3 | `CODPRODUCAO_PRODUTO` | `INTEGER` | NULL | v1074 | v1074 |
| 4 | `CODPRODUTO_MOVIMENTO` | `INTEGER` | NULL | v1074 | v1074 |
| 5 | `CODUSUARIO` | `INTEGER` | NULL | v1074 | v1074 |
| 6 | `CODVENDA` | `VARCHAR(10)` | NULL | v1074 | v1074 |
| 7 | `CODVENDA_PRODUTO` | `INTEGER` | NULL | v1074 | v1074 |
| 8 | `CODPRODUTO` | `VARCHAR(15)` | NULL | v1074 | v1074 |
| 9 | `CODCENTRO_TRABALHO` | `INTEGER` | NULL | v1074 | v1074 |
| 10 | `SITUACAO` | `VARCHAR(100)` | NULL | v1074 | v1074 |
| 11 | `PRODUCAO_ESTAGIO` | `VARCHAR(100)` | NULL | v1074 | v1074 |
| 12 | `PRODUCAO_MOTIVO` | `VARCHAR(100)` | NULL | v1074 | v1074 |
| 13 | `OBSERVACAO` | `VARCHAR(500)` | NULL | v1074 | v1074 |
| 14 | `DT_ALTERACAO` | `TIMESTAMP` | NULL | v1074 | v1074 |
| 15 | `CODUSUARIO_RESPONSAVEL` | `INTEGER` | NULL | v1074 | v1074 |
| 16 | `TIPO_USO` | `VARCHAR(20)` | NULL | v1075 | v1075 |
| 17 | `QUANT` | `FLOAT` | NULL | v1075 | v1075 |
| 18 | `PESSOA_FUNCIONARIO_CODIGO` | `VARCHAR(10)` | NULL | v1149 | v1149 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 1074 | CREATE | CREATE TABLE com 15 colunas |
| 1075 | ADD_COL | + TIPO_USO VARCHAR(20) |
| 1075 | ADD_COL | + QUANT FLOAT |
| 1149 | ADD_COL | + PESSOA_FUNCIONARIO_CODIGO VARCHAR(10) |

