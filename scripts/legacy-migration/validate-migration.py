"""
Validate-migration — teste massivo comparando todos clientes Firebird vs oimpresso.

Para cada campo: % fidelidade + amostra de divergências (top 20).
Saída: stdout + CSV opcional em output/.

Uso:
    python validate-migration.py --alias "Martinho online" --target-business 164
"""
from __future__ import annotations
import argparse, csv, os, re, sys
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

from lib.firebird_reader import firebird_connect  # noqa: E402
import pymysql, pymysql.cursors


def digits(s):
    return re.sub(r'\D', '', s or '')


def norm(s):
    if s is None: return ""
    return str(s).strip().upper()


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--alias", required=True)
    p.add_argument("--target-business", type=int, required=True)
    p.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    p.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    p.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    p.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    p.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    p.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    p.add_argument("--csv", help="Output CSV path with all gaps")
    args = p.parse_args()

    print(f"== Validate migration biz={args.target_business} ==")

    # 1. Read all PESSOAS ativas Firebird → dict cnpj → fields
    print("\n[1/3] Lendo PESSOAS Firebird...")
    pessoas = {}
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb:
        cur = fb.cursor()
        cur.execute(
            "SELECT CODIGO, RAZAOSOCIAL, FANTASIA, CNPJCPF, INSCIDENT, CONTATO, "
            "ENDERECO, BAIRRO, UF, FONE1, EMAIL "
            "FROM PESSOAS WHERE ATIVO='S' AND CNPJCPF IS NOT NULL"
        )
        cols = [d[0] for d in cur.description]
        for row in cur:
            d = dict(zip(cols, row))
            k = digits(d["CNPJCPF"])
            if k:
                pessoas.setdefault(k, d)
        cur.close()
    print(f"  PESSOAS ATIVAS com CNPJ: {len(pessoas)}")

    # 2. Read all contacts oimpresso
    print("\n[2/3] Lendo contacts oimpresso...")
    my = pymysql.connect(
        host=args.mysql_host, port=args.mysql_port,
        user=args.mysql_user, password=args.mysql_password,
        database=args.mysql_database, charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )
    contacts = {}
    with my.cursor() as cur:
        cur.execute(
            "SELECT id, tax_number, name, fantasia, inscricao_estadual, contato, "
            "rua, neighborhood, state, mobile, email "
            "FROM contacts WHERE business_id=%s AND deleted_at IS NULL "
            "AND tax_number IS NOT NULL AND tax_number<>''",
            (args.target_business,),
        )
        for r in cur.fetchall():
            k = digits(r["tax_number"])
            if k:
                contacts.setdefault(k, r)
    my.close()
    print(f"  contacts ativos com tax_number: {len(contacts)}")

    # 3. Compare field by field
    print("\n[3/3] Comparando...")
    field_map = [
        ("name", "RAZAOSOCIAL"),
        ("fantasia", "FANTASIA"),
        ("inscricao_estadual", "INSCIDENT"),
        ("contato", "CONTATO"),
        ("rua", "ENDERECO"),
        ("neighborhood", "BAIRRO"),
        ("state", "UF"),
        ("mobile", "FONE1"),
        ("email", "EMAIL"),
    ]

    in_both = set(pessoas) & set(contacts)
    only_fb = set(pessoas) - set(contacts)
    only_my = set(contacts) - set(pessoas)
    print(f"  CNPJs em AMBOS         : {len(in_both)}")
    print(f"  CNPJs SÓ Firebird (faltam migrar): {len(only_fb)}")
    print(f"  CNPJs SÓ oimpresso (não estão FB ATIVO): {len(only_my)}")

    results = {fb_field: {"match": 0, "diff": 0, "both_empty": 0, "fb_empty_my_set": 0, "fb_set_my_empty": 0}
                for _, fb_field in field_map}
    gaps = []

    for cnpj in in_both:
        p = pessoas[cnpj]
        c = contacts[cnpj]
        for my_field, fb_field in field_map:
            v_fb = norm(p.get(fb_field))
            v_my = norm(c.get(my_field))
            r = results[fb_field]
            if not v_fb and not v_my:
                r["both_empty"] += 1
            elif v_fb == v_my:
                r["match"] += 1
            elif not v_fb and v_my:
                r["fb_empty_my_set"] += 1
            elif v_fb and not v_my:
                r["fb_set_my_empty"] += 1
                if len(gaps) < 1000:
                    gaps.append({"cnpj": cnpj, "field": fb_field, "type": "fb_set_my_empty",
                                 "firebird": (p.get(fb_field) or '')[:100],
                                 "oimpresso": "", "name": (p.get("RAZAOSOCIAL") or '')[:60]})
            else:
                r["diff"] += 1
                if len(gaps) < 1000:
                    gaps.append({"cnpj": cnpj, "field": fb_field, "type": "diff",
                                 "firebird": str(p.get(fb_field) or '')[:100],
                                 "oimpresso": str(c.get(my_field) or '')[:100],
                                 "name": (p.get("RAZAOSOCIAL") or '')[:60]})

    # Print report
    print(f"\n{'='*70}")
    print(f"{'Campo (Delphi)':<20} {'Match%':>8} {'Match':>8} {'Diff':>7} {'FB→Vazio':>10} {'Vazio→OI':>10}")
    print(f"{'='*70}")
    for _, fb_field in field_map:
        r = results[fb_field]
        total = r["match"] + r["diff"] + r["fb_set_my_empty"] + r["fb_empty_my_set"]
        pct = 100.0 * r["match"] / total if total else 0
        print(f"{fb_field:<20} {pct:>7.1f}% {r['match']:>8} {r['diff']:>7} {r['fb_set_my_empty']:>10} {r['fb_empty_my_set']:>10}")
    print(f"{'='*70}")

    # Save CSV
    out_path = args.csv or f"scripts/legacy-migration/output/validation-gaps-biz{args.target_business}.csv"
    Path(os.path.dirname(out_path)).mkdir(parents=True, exist_ok=True)
    with open(out_path, "w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=["cnpj","name","field","type","firebird","oimpresso"])
        w.writeheader()
        w.writerows(gaps)
    print(f"\n[CSV] {out_path}  ({len(gaps)} gaps sample)")

    print(f"\n[OK] Validate concluido")
    return 0


if __name__ == "__main__":
    sys.exit(main())
