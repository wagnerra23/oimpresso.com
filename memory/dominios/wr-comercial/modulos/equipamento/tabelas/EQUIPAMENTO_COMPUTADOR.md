---
table: EQUIPAMENTO_COMPUTADOR
module: equipamento
created_at_version: 44
last_modified_version: 94
target_version: 1468
columns_count: 31
foreign_keys_count: 0
auto_generated: true
generated_at: 2026-05-09
generator: scripts/legacy-migration/generate-baseline.py
source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt
---

# `EQUIPAMENTO_COMPUTADOR`

> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..1468. Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. Notas humanas vão em `_notes.md` ao lado.

- **Módulo:** `equipamento` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)
- **Criada em:** UPDATE 44;
- **Última mudança:** UPDATE 94;
- **Total colunas (versão 1468):** 31

## Colunas (versão 1468)

| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |
|---|---|---|---|---|---|---|
| 1 | `CODIGO` | `VARCHAR(10)` | NOT NULL |  | v44 | v44 |
| 2 | `TIPODEACESSO` | `VARCHAR(50)` | NULL |  | v44 | v44 |
| 3 | `CONEXAO` | `VARCHAR(100)` | NULL |  | v44 | v44 |
| 4 | `USUARIO` | `VARCHAR(15)` | NULL |  | v44 | v44 |
| 5 | `SENHA` | `VARCHAR(15)` | NULL |  | v44 | v44 |
| 6 | `SISTEMA_OPERACIONAL` | `VARCHAR(50)` | NULL |  | v44 | v63 |
| 7 | `IP_INTERNO` | `VARCHAR(15)` | NULL |  | v44 | v44 |
| 8 | `ANTIVIRUS` | `VARCHAR(15)` | NULL |  | v44 | v44 |
| 9 | `PASTA_INSTALACAO` | `VARCHAR(255)` | NULL |  | v44 | v44 |
| 10 | `VERSAO_EXE` | `VARCHAR(15)` | NULL |  | v44 | v44 |
| 11 | `VERSAO_BANCO` | `VARCHAR(15)` | NULL |  | v44 | v44 |
| 12 | `DATA` | `TIMESTAMP` | NULL |  | v44 | v44 |
| 13 | `DT_ULTIMA_ASSISTENCIA` | `TIMESTAMP` | NULL |  | v44 | v44 |
| 14 | `HD` | `VARCHAR(50)` | NULL |  | v44 | v44 |
| 15 | `BACKUP_AUTOMATICO` | `VARCHAR(1)` | NULL |  | v44 | v44 |
| 16 | `PAF` | `VARCHAR(1)` | NULL |  | v44 | v44 |
| 17 | `PROCESSADOR` | `VARCHAR(50)` | NULL |  | v44 | v63 |
| 18 | `MEMORIA` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 19 | `VELOCIDADE_CONEXAO` | `VARCHAR(20)` | NULL |  | v44 | v44 |
| 20 | `IMPRESSORA_FISCAL` | `VARCHAR(50)` | NULL |  | v44 | v44 |
| 21 | `LEITOR_BARRAS` | `VARCHAR(50)` | NULL |  | v44 | v44 |
| 22 | `GERA_MENSALIDADE` | `VARCHAR(1)` | NULL |  | v68 | v68 |
| 23 | `HOSTNAME` | `VARCHAR(50)` | NULL |  | v69 | v69 |
| 24 | `LIBERADO` | `VARCHAR(1)` | NULL |  | v69 | v69 |
| 25 | `DT_VALIDADE` | `TIMESTAMP` | NULL |  | v69 | v69 |
| 26 | `SERIAL` | `VARCHAR(20)` | NULL |  | v69 | v69 |
| 27 | `CONTRA_SENHA` | `VARCHAR(20)` | NULL |  | v69 | v69 |
| 28 | `OCULTO` | `VARCHAR(1)` | NULL |  | v69 | v69 |
| 29 | `VALOR` | `DOUBLE PRECISION` | NULL |  | v69 | v69 |
| 30 | `MOTIVO` | `VARCHAR(500)` | NULL |  | v74 | v74 |
| 31 | `CAMINHO_BANCO` | `VARCHAR(255)` | NULL |  | v94 | v94 |

## Evolução

| UPDATE N; | Operação | Detalhe |
|---|---|---|
| 44 | CREATE | CREATE TABLE com 22 colunas |
| 47 | ADD_COL | + GERA_MENSALIDADE VARCHAR(1) |
| 63 | ALTER_TYPE | ~ SISTEMA_OPERACIONAL TYPE VARCHAR(50) |
| 63 | ALTER_TYPE | ~ PROCESSADOR TYPE VARCHAR(50) |
| 68 | ADD_COL | + GERA_MENSALIDADE VARCHAR(1) |
| 69 | ADD_COL | + HOSTNAME VARCHAR(50) |
| 69 | ADD_COL | + LIBERADO VARCHAR(1) |
| 69 | ADD_COL | + DT_VALIDADE TIMESTAMP |
| 69 | ADD_COL | + SERIAL VARCHAR(20) |
| 69 | ADD_COL | + CONTRA_SENHA VARCHAR(20) |
| 69 | ADD_COL | + OCULTO VARCHAR(1) |
| 69 | ADD_COL | + VALOR DOUBLE PRECISION |
| 74 | ADD_COL | + MOTIVO VARCHAR(500) |
| 94 | ADD_COL | + CAMINHO_BANCO VARCHAR(255) |

