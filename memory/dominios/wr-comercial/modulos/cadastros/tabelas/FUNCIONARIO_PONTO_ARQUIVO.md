---
table: FUNCIONARIO_PONTO_ARQUIVO
module: cadastros
created_at_version: 329
last_modified_version: 332
target_version: 1468
columns_count: 25
foreign_keys_count: 1
foreign_keys:
  CODPESSOA_FUNCIONARIO: PESSOAS
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `FUNCIONARIO_PONTO_ARQUIVO`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `cadastros` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 329;
- **Última mudança:** UPDATE 332;
- **Total colunas (versão 1468):** 25

## Foreign Keys (inferidas)

> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`.

| Coluna | → Tabela alvo |
|---|---|
| `CODPESSOA_FUNCIONARIO` | [`PESSOAS`](../../cadastros/tabelas/PESSOAS.md) |

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `INTEGER` | NOT NULL |  | v329 | v329 |
| 2 | `IDENTIFICADOR` | `VARCHAR(10)` | NOT NULL |  | v329 | v329 |
| 3 | `CODPESSOA_FUNCIONARIO` | `VARCHAR(10)` | NULL | → `PESSOAS` | v329 | v329 |
| 4 | `NSR` | `VARCHAR(9)` | NULL |  | v329 | v329 |
| 5 | `TIPO_REGISTRO` | `INTEGER` | NULL |  | v329 | v329 |
| 6 | `TIPO_IDENTICADOR_EMPREGADOR` | `INTEGER` | NULL |  | v329 | v329 |
| 7 | `CNPJ_CPF_EMPREGADOR` | `VARCHAR(14)` | NULL |  | v329 | v329 |
| 8 | `CEI_EMPREGADOR` | `VARCHAR(12)` | NULL |  | v329 | v329 |
| 9 | `SERIAL_REP` | `VARCHAR(30)` | NULL |  | v329 | v329 |
| 10 | `DATA_INICIAL` | `TIMESTAMP` | NULL |  | v329 | v329 |
| 11 | `DATA_FINAL` | `TIMESTAMP` | NULL |  | v329 | v329 |
| 12 | `DATA_GERACAO_ARQUIVO` | `TIMESTAMP` | NULL |  | v329 | v329 |
| 13 | `DATA_HORARIO_MARCACAO` | `TIMESTAMP` | NULL |  | v329 | v329 |
| 14 | `RAZAOSOCIAL_EMPREGADOR` | `VARCHAR(150)` | NULL |  | v329 | v329 |
| 15 | `LOCAL_PRESTACAO_SERVICO` | `VARCHAR(100)` | NULL |  | v329 | v329 |
| 16 | `PIS` | `VARCHAR(12)` | NULL |  | v329 | v329 |
| 17 | `TIPO_OPERACAO` | `VARCHAR(1)` | NULL |  | v329 | v329 |
| 18 | `QTD_REGISTRO_2` | `INTEGER` | NULL |  | v329 | v329 |
| 19 | `QTD_REGISTRO_3` | `INTEGER` | NULL |  | v329 | v329 |
| 20 | `QTD_REGISTRO_4` | `INTEGER` | NULL |  | v329 | v329 |
| 21 | `QTD_REGISTRO_5` | `INTEGER` | NULL |  | v329 | v329 |
| 22 | `NOME_FUNCIONARIO` | `VARCHAR(52)` | NULL |  | v329 | v329 |
| 23 | `PONTO_GERADO` | `VARCHAR(1)` | NULL |  | v329 | v329 |
| 24 | `MOTIVO` | `VARCHAR(500)` | NULL |  | v332 | v332 |
| 25 | `OCORRENCIA` | `VARCHAR(5)` | NULL |  | v332 | v332 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 329 | CREATE | CREATE TABLE com 23 colunas |
| 332 | ADD_COL | + MOTIVO VARCHAR(500) |
| 332 | ADD_COL | + OCORRENCIA VARCHAR(5) |

