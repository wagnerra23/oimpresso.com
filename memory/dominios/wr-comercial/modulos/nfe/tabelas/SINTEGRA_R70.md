---
table: SINTEGRA_R70
module: nfe
created_at_version: 583
last_modified_version: 583
target_version: 1468
columns_count: 19
foreign_keys_count: 1
foreign_keys:
  CODSINTEGRA: SINTEGRA
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `SINTEGRA_R70`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `nfe` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 583;
- **Última mudança:** UPDATE 583;
- **Total colunas (versão 1468):** 19

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODSINTEGRA` | [`SINTEGRA`](../../nfe/tabelas/SINTEGRA.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v583 | v583 |
| 2 | `CODSINTEGRA` | `INTEGER` | NOT NULL | → `SINTEGRA` | v583 | v583 |
| 3 | `CNPJCPF` | `VARCHAR(14)` | NULL |  | v583 | v583 |
| 4 | `INSCRICAO_ESTADUAL` | `VARCHAR(14)` | NULL |  | v583 | v583 |
| 5 | `DT_EMISSAO` | `DATE` | NULL |  | v583 | v583 |
| 6 | `UF` | `VARCHAR(2)` | NULL |  | v583 | v583 |
| 7 | `MODELO` | `VARCHAR(2)` | NULL |  | v583 | v583 |
| 8 | `SERIE` | `VARCHAR(1)` | NULL |  | v583 | v583 |
| 9 | `SUBSERIE` | `VARCHAR(2)` | NULL |  | v583 | v583 |
| 10 | `NUMERO` | `VARCHAR(6)` | NULL |  | v583 | v583 |
| 11 | `CFOP` | `VARCHAR(4)` | NULL |  | v583 | v583 |
| 12 | `VALOR_TOTAL` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 13 | `ICMS_BC` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 14 | `ICMS_VALOR` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 15 | `ISENTAS` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 16 | `OUTRAS` | `DOUBLE PRECISION` | NULL |  | v583 | v583 |
| 17 | `CIF_FOB_OUTROS` | `VARCHAR(1)` | NULL |  | v583 | v583 |
| 18 | `CIF_FOB` | `VARCHAR(1)` | NULL |  | v583 | v583 |
| 19 | `SITUACAO` | `VARCHAR(1)` | NULL |  | v583 | v583 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 583 | CREATE | CREATE TABLE com 19 colunas |

