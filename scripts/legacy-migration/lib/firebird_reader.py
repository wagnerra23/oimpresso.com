"""
Wrapper de leitura Firebird via firebird-driver.

Encapsula resolve do registry HKCU + connect + queries comuns. Reusa lógica
do POC 2 (poc2-firebird-connect.py) num módulo importável.
"""

from __future__ import annotations

import os
import sys
import winreg
from contextlib import contextmanager
from typing import Any

try:
    import firebird.driver as fb
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
