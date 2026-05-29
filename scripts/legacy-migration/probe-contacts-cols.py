#!/usr/bin/env python3
"""Probe focado: colunas de classificação/role em contacts + uso atual biz=164."""
import os, pymysql
pw = os.environ["MYSQL_PASSWORD"]
con = pymysql.connect(host="127.0.0.1", port=33069, user="u906587222_oimpresso",
                      password=pw, database="u906587222_oimpresso", charset="utf8mb4")
cur = con.cursor()

print("=== Colunas de tipo/classificação/custom em contacts ===")
cur.execute(
    "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT "
    "FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contacts' "
    "AND (COLUMN_NAME IN ('type','primary_role','is_customer','is_supplier','is_employee',"
    "'is_representative','situacao','indicador_ie','regime','customer_group_id','contact_type',"
    "'sub_status','contact_status','bloqueado') "
    "OR COLUMN_NAME LIKE 'custom_field%') ORDER BY ORDINAL_POSITION"
)
for r in cur.fetchall():
    print(f"  {r[0]:22} {r[1]:28} null={r[2]} def={r[3]}")

print("=== Distribuição type/primary_role/flags biz=164 (vivos) ===")
cur.execute(
    "SELECT type, primary_role, is_customer, is_supplier, is_employee, is_representative, COUNT(*) "
    "FROM contacts WHERE business_id=164 AND deleted_at IS NULL "
    "GROUP BY type, primary_role, is_customer, is_supplier, is_employee, is_representative "
    "ORDER BY COUNT(*) DESC LIMIT 20"
)
print("  type | primary_role | cust sup emp rep | qtd")
for r in cur.fetchall():
    print(f"  {str(r[0]):10} {str(r[1]):14} {r[2]} {r[3]} {r[4]} {r[5]}  | {r[6]}")

print("=== custom_field1..10 preenchidos? (amostra biz=164) ===")
for i in range(1, 11):
    col = f"custom_field{i}"
    try:
        cur.execute(f"SELECT COUNT(*), COUNT(DISTINCT {col}) FROM contacts WHERE business_id=164 AND deleted_at IS NULL AND {col} IS NOT NULL AND {col}<>''")
        c, d = cur.fetchone()
        cur.execute(f"SELECT {col} FROM contacts WHERE business_id=164 AND deleted_at IS NULL AND {col} IS NOT NULL AND {col}<>'' LIMIT 1")
        s = cur.fetchone()
        print(f"  {col:16} preenchidos={c:5} distintos={d:5} ex={s[0] if s else None!r}")
    except Exception as e:
        print(f"  {col:16} ERRO {e}")

print("=== FAN COM (651-1) atual no oimpresso ===")
cur.execute(
    "SELECT id, name, type, primary_role, is_customer, is_supplier, customer_group_id, "
    "officeimpresso_codigo FROM contacts WHERE business_id=164 AND deleted_at IS NULL "
    "AND officeimpresso_codigo='651-1'"
)
for r in cur.fetchall():
    print("  ", r)
con.close()
print("PROBE OK")
