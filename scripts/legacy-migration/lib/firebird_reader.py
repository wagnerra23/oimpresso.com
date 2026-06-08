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

# Driver Firebird — auto-detect priorizando compatibilidade com servidores FB 2.5
# (Martinho v1404 e maioria dos clientes WR Comercial legacy).
#
# Preferência: `fdb` (driver legacy oficial, suporta FB 2.0 → 4.0).
# Fallback: `firebird-driver` (API moderna, requer FB ≥3.0 server).
# Override via env var FIREBIRD_PY_DRIVER=fdb|firebird-driver
_DRIVER: str | None = None
fb = None  # type: ignore

# Driver Python preferido. Agora que firebird-driver 2.0.3 + fbclient.dll 3.0.8
# + firebird.conf custom (WireCrypt=Disabled) conectam, ele é o default.
# `fdb` permanece como fallback se firebird-driver não estiver instalado.
_preferred = os.environ.get("FIREBIRD_PY_DRIVER", "firebird-driver").lower()

# Path canônico da fbclient.dll (FB 3.0.8 oficial — confirmado Wagner 2026-05-26).
_FB_CLIENT_DLL = os.environ.get(
    "FIREBIRD_LIBRARY_PATH",
    r"C:\Program Files\Firebird\Firebird_3_0\fbclient.dll",
)

# Path pro firebird.conf custom (resolve incompat WireCrypt com servidor 3.0.12
# do servidor-crm — workaround Wagner 2026-05-26). Auto-aplica via env FIREBIRD
# se não estiver setado pelo caller.
_FB_CONFIG_DIR = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
    "fbclient-config",
)
if "FIREBIRD" not in os.environ and os.path.isfile(os.path.join(_FB_CONFIG_DIR, "firebird.conf")):
    os.environ["FIREBIRD"] = _FB_CONFIG_DIR

def _try_import_fdb():
    global fb, _DRIVER
    try:
        import fdb as _fb_legacy  # type: ignore
        fb = _fb_legacy
        _DRIVER = "fdb"
        return True
    except ImportError:
        return False

def _try_import_modern():
    global fb, _DRIVER
    try:
        import firebird.driver as _fb_modern  # type: ignore
        fb = _fb_modern
        _DRIVER = "firebird-driver"
        return True
    except ImportError:
        return False

if _preferred == "firebird-driver":
    _try_import_modern() or _try_import_fdb()
else:
    _try_import_fdb() or _try_import_modern()

if fb is None:
    print(
        "❌ Nenhum driver Firebird instalado. Rode:\n"
        "   pip install fdb               # FB 2.5 legacy (recomendado p/ WR Comercial)\n"
        "   pip install firebird-driver   # >=FB 3.0",
        file=sys.stderr,
    )
    raise ImportError("firebird driver missing")


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

    # firebird-driver: fb.connect(database=..., user=..., password=..., charset=...)
    # fdb:             fb.connect(dsn=...,      user=..., password=..., charset=...)
    #
    # NOTA: `fdb` é o driver de PRODUÇÃO deste pipeline (mais portável: suporta
    # FB 2.5/3.0/4.0 e protocolo wire legacy). `firebird-driver` foi tentado em
    # 2026-05-26 contra servidor-crm (FB 3.0.12) e falhou com
    # "Failed to establish a connection" — provável incompatibilidade do
    # protocolo wire_crypt/auth_plugin que a API moderna não permite configurar
    # via `driver_config.server_defaults` (campo ausente nas versões 1.10+).
    # Fica como TODO futuro investigar se versões mais novas expõem isso.
    if _DRIVER == "fdb":
        con = fb.connect(dsn=path, user=user, password=password, charset=charset)
    else:
        # firebird-driver: aponta pra fbclient.dll FB 3.0 oficial via
        # driver_config.fb_client_library (API canônica — substitui mexer no
        # PATH). Compatível com servidor 3.0.12 do servidor-crm.
        try:
            from firebird.driver import driver_config  # type: ignore
            if os.path.exists(_FB_CLIENT_DLL):
                driver_config.fb_client_library.value = _FB_CLIENT_DLL
        except Exception:
            pass
        con = fb.connect(database=path, user=user, password=password, charset=charset)
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
