---
table: FUNCIONARIO
module: cadastros
created_at_version: 11
last_modified_version: 758
target_version: 1468
columns_count: 5
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FUNCIONARIO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 11;
- **Última mudança:** UPDATE 758;
- **Total colunas (versão 1468):** 5

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `PROXIMIDADE` | `VARCHAR(50)` | NULL |  | v11 | v11 |
| 2 | `CRT` | `VARCHAR(50)` | NULL |  | v110 | v110 |
| 3 | `LIMITE_DESCONTO` | `DOUBLE PRECISION` | NULL |  | v126 | v126 |
| 4 | `DT_ALTERACAO` | `timestamp` | NULL |  | v174 | v174 |
| 5 | `COMISSAO_POR_VENDA` | `varchar(1)` | NULL |  | v186 | v186 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 11 | RENAME_COL | × CNPJ_CPF → CNPJCPF |
| 11 | ADD_COL | + PROXIMIDADE VARCHAR(50) |
| 110 | ADD_COL | + CRT VARCHAR(50) |
| 126 | ADD_COL | + LIMITE_DESCONTO DOUBLE PRECISION |
| 174 | ADD_COL | + DT_ALTERACAO timestamp |
| 186 | ADD_COL | + COMISSAO_POR_VENDA varchar(1) |
| 758 | DROP_COL | - CNPJ_CPF |

