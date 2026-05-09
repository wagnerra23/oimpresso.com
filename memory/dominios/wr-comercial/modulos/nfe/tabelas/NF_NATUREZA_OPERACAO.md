---
table: NF_NATUREZA_OPERACAO
module: nfe
created_at_version: 552
last_modified_version: 1137
target_version: 1468
columns_count: 8
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `NF_NATUREZA_OPERACAO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 552;
- **Última mudança:** UPDATE 1137;
- **Total colunas (versão 1468):** 8

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v552 | v552 |
| 2 | `DESCRICAO` | `VARCHAR(200)` | NULL |  | v552 | v662 |
| 3 | `TIPO_NF` | `VARCHAR(10)` | NULL |  | v661 | v737 |
| 4 | `NFSE_CODIGO` | `INTEGER` | NULL |  | v661 | v661 |
| 5 | `ATIVO` | `DOM_ATIVO` | NULL |  | v662 | v662 |
| 6 | `DT_ALTERACAO` | `TIMESTAMP` | NULL |  | v728 | v728 |
| 7 | `CONSUMIDOR_FINAL` | `VARCHAR(1), ADD ENTRADA_SAIDA VARCHAR(1), ADD OPERACAO VARCHAR(50)` | NULL |  | v944 | v944 |
| 8 | `TEM_TRIBUTACAO_PADRAO` | `VARCHAR(1)` | NULL |  | v1137 | v1137 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 552 | CREATE | CREATE TABLE com 2 colunas |
| 570 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(200) |
| 661 | ADD_COL | + TIPO_NF VARCHAR(5) |
| 661 | ADD_COL | + NFSE_CODIGO INTEGER |
| 662 | ADD_COL | + ATIVO DOM_ATIVO |
| 662 | ALTER_TYPE | ~ DESCRICAO TYPE VARCHAR(200) |
| 728 | ADD_COL | + DT_ALTERACAO TIMESTAMP |
| 737 | ALTER_TYPE | ~ TIPO_NF TYPE VARCHAR(10) |
| 944 | ADD_COL | + NF_FINALIDADE VARCHAR(1), ADD ENTRADA_SAIDA VARCHAR(1), ADD OPERACAO VARCHAR(50) |
| 944 | RENAME_COL | × NF_FINALIDADE → CONSUMIDOR_FINAL |
| 1137 | ADD_COL | + TEM_TRIBUTACAO_PADRAO VARCHAR(1) |

