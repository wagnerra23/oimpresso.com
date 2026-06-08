#!/usr/bin/env python3
"""Probe schema dos tipos de pessoa nos 2 lados (Firebird PESSOAS.IS_* + MySQL contacts flags)."""
from __future__ import annotations
import os, re, sys
os.environ.setdefault("FIREBIRD_PY_DRIVER", "fdb")  # driver de produção (FB 3.0.12 servidor-crm)
sys.path.insert(0, os.path.dirname(__file__))
from lib.firebird_reader import firebird_connect, query  # noqa
import pymysql

# ---- Firebird: colunas IS_* / SEQUENCIA_* em PESSOAS ----
ALIAS = os.environ.get("FB_ALIAS", "Martinho online")
_ctx = firebird_connect(ALIAS, password_override="masterkey")
con = _ctx.__enter__()
cur = con.cursor()
cur.execute(
    "SELECT TRIM(rf.RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS rf "
    "WHERE rf.RDB$RELATION_NAME='PESSOAS' "
    "AND (rf.RDB$FIELD_NAME LIKE 'IS\\_%' ESCAPE '\\' OR rf.RDB$FIELD_NAME LIKE 'SEQUENCIA\\_%' ESCAPE '\\') "
    "ORDER BY rf.RDB$FIELD_NAME"
)
cols = [r[0] for r in cur.fetchall()]
is_cols = sorted([c for c in cols if c.startswith("IS_")])
print("=== PESSOAS IS_* cols ===")
print(is_cols)

# catálogo de tipos
cur.execute("SELECT TRIM(CODIGO), TRIM(DESCRICAO) FROM PESSOAS_TIPO ORDER BY CODIGO")
print("=== Catálogo PESSOAS_TIPO ===")
catalogo = cur.fetchall()
for c in catalogo:
    print(f"  {c[0]:6} {c[1]}")

# distribuição de flags na base inteira
sel_cnt = ", ".join([f"SUM(CASE WHEN {c}='S' THEN 1 ELSE 0 END) AS {c}" for c in is_cols])
cur.execute(f"SELECT COUNT(*) AS TOTAL, {sel_cnt} FROM PESSOAS")
row = cur.fetchone()
desc = [d[0] for d in cur.description]
print("=== Distribuição IS_* (toda PESSOAS) ===")
for name, val in zip(desc, row):
    print(f"  {name:18} = {val}")

# amostra FAN COM 651-1
flagsel = ", ".join(is_cols)
cur.execute(f"SELECT CODIGO, RAZAOSOCIAL, FANTASIA, {flagsel} FROM PESSOAS WHERE CODIGO='651-1'")
r = cur.fetchone()
if r:
    d = [x[0] for x in cur.description]
    print("=== FAN COM (651-1) flags ===")
    for name, val in zip(d, r):
        print(f"  {name:18} = {val!r}")
_ctx.__exit__(None, None, None)

# ---- MySQL: flags ADR 0188 em contacts ----
pw = open("/tmp/_dbpass.txt").read().strip()
mcon = pymysql.connect(host="127.0.0.1", port=33069, user="u906587222_oimpresso",
                       password=pw, database="u906587222_oimpresso", charset="utf8mb4")
mcur = mcon.cursor()
mcur.execute(
    "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT FROM information_schema.COLUMNS "
    "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contacts' "
    "AND COLUMN_NAME IN ('is_customer','is_supplier','is_employee','is_representative','type','primary_role','officeimpresso_codigo')"
)
print("=== contacts flags (ADR 0188) ===")
for r in mcur.fetchall():
    print(f"  {r}")
mcur.execute(
    "SELECT SUM(is_customer), SUM(is_supplier), SUM(is_employee), SUM(is_representative), "
    "GROUP_CONCAT(DISTINCT type), COUNT(*) "
    "FROM contacts WHERE business_id=164 AND deleted_at IS NULL"
)
print("=== distribuição atual biz=164 (customers, suppliers, employees, reps, types, total) ===")
print(" ", mcur.fetchone())
mcon.close()
print("PROBE OK")
