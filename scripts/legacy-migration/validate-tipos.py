#!/usr/bin/env python3
"""
validate-tipos.py — Teste de fidelidade da migração de tipos.
Compara, para CADA contato migrado, as flags IS_* do Firebird PESSOAS vs
is_customer/is_supplier/is_employee/is_representative no oimpresso.
Reporta divergências (deveria ser ZERO). Também valida custom_field3 (classif fiscal).
"""
from __future__ import annotations
import argparse, os, sys
os.environ.setdefault("FIREBIRD_PY_DRIVER", "fdb")
sys.path.insert(0, os.path.dirname(__file__))
from lib.firebird_reader import firebird_connect  # noqa
import pymysql, pymysql.cursors  # noqa


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--alias", default="Martinho online")
    p.add_argument("--target-business", type=int, default=164)
    p.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    args = p.parse_args()
    biz = args.target_business

    # importa a MESMA lógica de classificação do enricher (single source of truth)
    sys.path.insert(0, os.path.dirname(__file__))
    import importlib.util
    spec = importlib.util.spec_from_file_location(
        "enrich_tipos", os.path.join(os.path.dirname(__file__), "enrich-tipos.py"))
    et = importlib.util.module_from_spec(spec); spec.loader.exec_module(et)  # type: ignore

    flagsel = ", ".join(f"IS_{c}" for c in et.ALL_CODES)
    print("[1/2] Lendo PESSOAS Firebird...")
    pessoas = {}
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb:
        cur = fb.cursor(); cur.execute(f"SELECT CODIGO, {flagsel} FROM PESSOAS")
        cols = [d[0] for d in cur.description]
        for r in cur:
            d = dict(zip(cols, r))
            cod = str(d.get("CODIGO")).strip() if d.get("CODIGO") is not None else ""
            if cod:
                pessoas[cod] = d
        cur.close()
    print(f"  PESSOAS: {len(pessoas)}")

    con = pymysql.connect(host=os.environ.get("MYSQL_HOST", "127.0.0.1"),
                          port=int(os.environ.get("MYSQL_PORT", "3306")),
                          user=os.environ.get("MYSQL_USER", "root"),
                          password=os.environ.get("MYSQL_PASSWORD", ""),
                          database=os.environ.get("MYSQL_DATABASE", "oimpresso"),
                          charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor)
    print("[2/2] Comparando contacts...")
    with con.cursor() as cur:
        cur.execute(
            "SELECT id, officeimpresso_codigo, name, is_customer, is_supplier, is_employee, "
            "is_representative, primary_role, type, custom_field3 FROM contacts "
            "WHERE business_id=%s AND deleted_at IS NULL "
            "AND officeimpresso_codigo IS NOT NULL AND officeimpresso_codigo<>''", (biz,))
        rows = cur.fetchall()
    con.close()

    checked = mism = no_src = 0
    diffs = []
    FIELDS = ("is_customer", "is_supplier", "is_employee", "is_representative")
    for c in rows:
        cod = str(c["officeimpresso_codigo"]).strip()
        src = pessoas.get(cod)
        if not src:
            no_src += 1
            continue
        checked += 1
        expect = et.classify(src)
        bad = [f for f in FIELDS if int(c[f]) != int(expect[f])]
        if c["primary_role"] != expect["primary_role"] or c["type"] != expect["type"]:
            bad.append("role/type")
        if (c["custom_field3"] or None) != expect["classif"]:
            bad.append("classif")
        if bad:
            mism += 1
            if len(diffs) < 20:
                diffs.append((cod, c["name"][:24], bad,
                              {f: int(c[f]) for f in FIELDS}, c["custom_field3"],
                              {f: expect[f] for f in FIELDS}, expect["classif"]))

    print("\n=== RESULTADO ===")
    print(f"  contacts c/ codigo : {len(rows)}")
    print(f"  validados (c/ fonte): {checked}")
    print(f"  sem fonte Firebird : {no_src}")
    print(f"  DIVERGÊNCIAS       : {mism}")
    for d in diffs:
        print("   ", d)
    print("\n", "[OK] FIDELIDADE 100%" if mism == 0 else f"[FALHA] {mism} divergencias")
    return 0 if mism == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
