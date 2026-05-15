"""
Wrapper de leitura Firebird via firebird-driver.

Encapsula resolve do registry HKCU + connect + queries comuns. Reusa lógica
do POC 2 (poc2-firebird-connect.py) num módulo importável.

Helpers adicionados pra daemon dual-sync 2026-05-14:
  - read_chunk_with_retry: tenta query 3× com backoff exponencial (5s, 15s, 45s)
    cobrindo Firebird DatabaseError (sleep/conexão fechou/disconnect transitório)
  - has_column: detecta se col existe na tabela (DT_ALTERACAO ausente em rota legacy)
"""

from __future__ import annotations

import os
import sys
import time
import winreg
from contextlib import contextmanager
from typing import Any

try:
    import firebird.driver as fb
    from firebird.driver.types import DatabaseError as FbDatabaseError
except ImportError:
    print(
        "❌ firebird-driver não instalado. Rode: pip install firebird-driver",
        file=sys.stderr,
    )
    raise


def read_registry_alias(
    alias: str, app_title: str = "Office Comercial"
) -> tuple[str, str]:
    """Resolve alias do registry → (path, password placeholder)."""
    caminhos_key = rf"Software\Rocha\{app_title}\Banco\Caminhos"
    senhas_key = rf"Software\Rocha\{app_title}\Banco\Caminhos\Senhas"

    with winreg.OpenKey(winreg.HKEY_CURRENT_USER, caminhos_key) as k:
        path, _ = winreg.QueryValueEx(k, alias)

    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, senhas_key) as k:
            password, _ = winreg.QueryValueEx(k, path)
    except FileNotFoundError:
        password = ""

    return path, password


@contextmanager
def firebird_connect(
    alias: str,
    password_override: str | None = None,
    user: str = "SYSDBA",
    charset: str = "WIN1252",
    app_title: str = "Office Comercial",
):
    """Context manager pra conexão Firebird via alias do registry.

    password_override: se None, usa registry; recomenda-se passar 'masterkey'
                      pra builds com {$DEFINE WR2} (placeholder de 1 char no
                      registry — credencial real hardcoded em Principal.pas).

    Uso:
        with firebird_connect('ServidorWR2', password_override='masterkey') as con:
            cur = con.cursor()
            cur.execute("SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'")
            print(cur.fetchone())
    """
    path, registry_password = read_registry_alias(alias, app_title)
    password = password_override or registry_password or os.environ.get(
        "FIREBIRD_PASSWORD", ""
    )

    con = fb.connect(
        database=path,
        user=user,
        password=password,
        charset=charset,
    )
    try:
        yield con
    finally:
        con.close()


def get_versao_banco(con) -> int | None:
    """Lê CONFIGURACOES.VALOR WHERE CONFIG='VERSAO_BANCO'. Retorna None se vazio."""
    cur = con.cursor()
    cur.execute("SELECT VALOR FROM CONFIGURACOES WHERE CONFIG = 'VERSAO_BANCO'")
    row = cur.fetchone()
    cur.close()
    if row is None or row[0] is None:
        return None
    try:
        return int(row[0])
    except (ValueError, TypeError):
        return None


def list_user_tables(con) -> list[str]:
    """Lista nomes de tabelas usuárias (não-sistema, não-views) ordenadas."""
    cur = con.cursor()
    cur.execute(
        """
        SELECT TRIM(RDB$RELATION_NAME)
        FROM RDB$RELATIONS
        WHERE RDB$SYSTEM_FLAG = 0 AND RDB$VIEW_BLR IS NULL
        ORDER BY RDB$RELATION_NAME
        """
    )
    names = [row[0] for row in cur.fetchall()]
    cur.close()
    return names


def get_table_columns(con, table_name: str) -> list[dict[str, Any]]:
    """Lista colunas de uma tabela com tipo, tamanho, nullable.

    Retorna [{name, type, length, nullable}], ordenado pela posição original.
    """
    cur = con.cursor()
    cur.execute(
        """
        SELECT
            TRIM(rf.RDB$FIELD_NAME) AS name,
            f.RDB$FIELD_TYPE AS type_id,
            f.RDB$FIELD_SUB_TYPE AS subtype,
            f.RDB$FIELD_LENGTH AS length,
            COALESCE(rf.RDB$NULL_FLAG, 0) AS not_null,
            rf.RDB$FIELD_POSITION AS position
        FROM RDB$RELATION_FIELDS rf
        JOIN RDB$FIELDS f ON f.RDB$FIELD_NAME = rf.RDB$FIELD_SOURCE
        WHERE rf.RDB$RELATION_NAME = ?
        ORDER BY rf.RDB$FIELD_POSITION
        """,
        (table_name.upper(),),
    )
    cols = []
    for row in cur.fetchall():
        cols.append({
            "name": row[0],
            "type_id": row[1],
            "subtype": row[2],
            "length": row[3],
            "nullable": row[4] == 0,
            "position": row[5],
        })
    cur.close()
    return cols


def query(con, sql: str, params: tuple = ()) -> list[dict[str, Any]]:
    """Executa SELECT e retorna lista de dicts (col→valor)."""
    cur = con.cursor()
    cur.execute(sql, params)
    cols = [d[0] for d in cur.description] if cur.description else []
    rows = []
    for row in cur.fetchall():
        rows.append(dict(zip(cols, row)))
    cur.close()
    return rows


# ----------------------------------------------------------------------------
# Daemon dual-sync helpers (2026-05-14)
# ----------------------------------------------------------------------------

RETRY_BACKOFF_SECONDS = [5, 15, 45]  # exponencial: 5s, 15s, 45s entre tentativas


def has_column(con, table_name: str, column_name: str) -> bool:
    """Verifica se uma coluna existe na tabela Firebird (case-insensitive).

    Útil pra detectar DT_ALTERACAO ausente em versões antigas — daemon faz fallback
    FULL SYNC com warn em vez de quebrar.
    """
    try:
        cur = con.cursor()
        cur.execute(
            f"SELECT FIRST 1 * FROM {table_name}"
        )
        cols = {d[0].upper() for d in cur.description or []}
        cur.close()
        return column_name.upper() in cols
    except Exception:
        return False


def read_chunk_with_retry(
    con,
    sql: str,
    params: tuple = (),
    max_retries: int = 3,
    reconnect_callback=None,
) -> list[dict[str, Any]]:
    """Executa SELECT com retry exponencial em caso de DatabaseError.

    Args:
        con: conexão Firebird ativa
        sql: SELECT (com FIRST N pra chunk)
        params: parâmetros bound
        max_retries: 3 tentativas default (5s, 15s, 45s)
        reconnect_callback: callable() opcional pra reabrir conexão antes do retry
                            (recebe nada, retorna nova `con`). Default None = sem reconnect.

    Returns:
        list[dict] das rows lidas.

    Raises:
        DatabaseError se todas tentativas falharem.
    """
    last_exc: Exception | None = None
    for attempt in range(max_retries):
        try:
            return query(con, sql, params)
        except FbDatabaseError as e:
            last_exc = e
            if attempt == max_retries - 1:
                # última tentativa: propaga erro
                raise
            backoff = RETRY_BACKOFF_SECONDS[min(attempt, len(RETRY_BACKOFF_SECONDS) - 1)]
            print(
                f"[firebird retry] tentativa {attempt + 1}/{max_retries} falhou: {e!r}. "
                f"Aguardando {backoff}s antes de retry...",
                file=sys.stderr,
            )
            time.sleep(backoff)
            if reconnect_callback is not None:
                try:
                    con = reconnect_callback()
                except Exception as rec_exc:
                    print(
                        f"[firebird retry] reconnect callback falhou: {rec_exc!r}",
                        file=sys.stderr,
                    )
        except Exception:
            # erro não-Firebird: propaga sem retry
            raise
    # safety net (não deveria chegar aqui)
    if last_exc:
        raise last_exc
    return []
