---
id: dominios-wr-comercial-updatesql
---

# `UpdateSQL.txt` — schema migrations Delphi

O **`UpdateSQL.txt` é o changelog DDL canônico** do legacy WR Comercial. Equivalente a Laravel migrations / Liquibase / Flyway, mas implementado direto em Delphi.

## Localização

- **Disco** (working copy SVN): `D:/Programas/WR Comercial/Resources/UpdateSQL.txt`
- **Embedado no .exe** como resource RCDATA: declarado em [`WR2Resource.rc:10`](D:/Programas/WR Comercial/WR2Resource.rc)
  ```
  UpdateSQL RCDATA "Resources\\UpdateSQL.txt"
  ```
- **Loader Pascal:** `Busca_SQL_Incial('UpdateSQL')` em [`wr_memoria.pas:103`](D:/Programas/WR Comercial/wr_memoria.pas) (note: typo "Incial" em vez de "Inicial" no código original)

## Formato

Sequência de **blocos** delimitados por linha-marker:

```sql
UPDATE 6;

ALTER TABLE NF_ENTRADA_PRODUTOS ADD VALOR_PRAZO DOUBLE PRECISION;
ALTER TABLE FORNECEDOR ADD PROXIMIDADE VARCHAR(50);

UPDATE 7;

ALTER TABLE NF_ENTRADA_PRODUTOS ALTER CST TO CODNF_CST;
...

UPDATE 1468;

CREATE TABLE PROCESSOS_VINCULOS (...);

UPDATE 1999;    --ainda nãofazer essa, vai jogando pra frente
```

**Regras** observadas no parser POC 1:
- Header: `^UPDATE\s+\d+\s*;\s*(--.*)?$` (case-insensitive — tolera comentário inline)
- Encoding: UTF-8 com BOM (Delphi RAD Studio default; abrir com `utf-8-sig` em Python)
- Comentários: `/* ... */` (multi-linha) e `-- ...` (linha)
- DDL Firebird: `ALTER TABLE`, `CREATE TABLE`, `CREATE INDEX`, `ALTER PROCEDURE`, `EXECUTE PROCEDURE`, etc.

## Estado atual (2026-05-09)

Validado por `scripts/legacy-migration/poc1-parser-updatesql.py`:

| Métrica | Valor |
|---|---|
| Tamanho | 2.168.319 bytes |
| Total de blocos `UPDATE N;` | **1.452** |
| Versão mínima | **6** |
| Versão máxima | **1999** (stub futuro) |
| Última versão real | **1468** |
| Linhas totais | 34.588 |
| DDL statements detectados | ~14.681 |

**Versões faltantes na sequência** (gaps históricos — devs pularam números):

```
75, 76, 77, 78, 108, 169, 694, 794, 919, 936, 1376, 1377
```

Provavelmente migrations canceladas. Não são bugs — são lacunas legítimas que o engine Delphi pula.

**Versão 1999** é stub — comentário `--ainda nãofazer essa, vai jogando pra frente`. Reservada pra próxima migration que ainda não decidiu o número.

## Banco zerado começa em 1308

Quando o Delphi cria banco do zero ([`wr_memoria.pas:608`](D:/Programas/WR Comercial/wr_memoria.pas)):

```pascal
IBScript1.Script.Text :=
  'UPDATE CONFIGURACOES SET CONFIG = ''VERSAO_BANCO'', VALOR = 1308, CODEMPRESA = 1, CODUSUARIO = 0;';
```

→ banco "novo" começa em v1308 (não v6). Updates anteriores são consolidados no script `BancoLocal.sql` (resource separado, também embedado).

## Engine de aplicação (não localizado no .pas)

O parser `Busca_SQL_Incial('UpdateSQL')` só carrega o conteúdo. **O engine que itera os blocos e aplica em ordem não foi localizado nos `.pas` inspecionados em 2026-05-09.** Suspeitas:

- Pode estar em **package compilado** (BPL) externo
- Pode estar no **executável separado "Editor de Registros de Bancos de Dados"** (não confundir com WR Comercial principal)
- Pode estar em `unitSQL` mencionada por Wagner mas não localizada por grep

Pra propósitos de migração one-shot, **não precisamos replicar o engine** — basta:
1. Parsear `UpdateSQL.txt` via POC 1
2. Reconstruir schema textual aplicando blocos `1..N` sequencialmente em estrutura in-memory (Fase 3 do plano)
3. Comparar com schema vivo do cliente (drift check) antes de extrair dados

## Parser canônico (Python)

Implementação em [`scripts/legacy-migration/poc1-parser-updatesql.py`](../../../scripts/legacy-migration/poc1-parser-updatesql.py):

```python
UPDATE_HEADER_RE = re.compile(r"^UPDATE\s+(\d+)\s*;\s*(--.*)?$", re.IGNORECASE)

def parse_updatesql(path: Path) -> dict[int, list[str]]:
    raw = path.read_text(encoding="utf-8-sig")  # tira BOM
    blocks: dict[int, list[str]] = {}
    current_version = None
    current_lines = []
    for line in raw.splitlines():
        m = UPDATE_HEADER_RE.match(line)
        if m:
            if current_version is not None:
                blocks[current_version] = current_lines
            current_version = int(m.group(1))
            current_lines = []
        elif current_version is not None:
            current_lines.append(line)
    if current_version is not None:
        blocks[current_version] = current_lines
    return blocks
```

Output JSON em `scripts/legacy-migration/output/updatesql-parsed.json` (~2.4MB, gitignored).

## Como o cliente usa em produção

Quando Delphi sobe num cliente já tem banco:
1. Lê `CONFIGURACOES.VALOR WHERE CONFIG='VERSAO_BANCO'` → versão atual `V`
2. Para cada bloco `UPDATE N;` com `N > V` no `UpdateSQL.txt` embedado, aplica DDL
3. Atualiza `CONFIGURACOES.VALOR` pra última versão aplicada

Versões dos clientes vão de **571** (mais antigo, GoldenPrint) a **1474** (mais novo, Zoom — observado no Editor de Registros 2026-05-09). Banco do Wagner (`servidor-crm:Banco`) está em **1466** — 2 updates atrás do `UpdateSQL.txt` no disco do Wagner (1468), normal pra cliente sem upgrade recente.
