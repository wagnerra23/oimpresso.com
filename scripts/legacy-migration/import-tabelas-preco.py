"""
Import tabelas de preço Delphi → oimpresso (DINÂMICO, business-scoped, zero hardcode).

Cada empresa cria suas próprias tabelas de preço (PRODUTO_TABELA). Este importer
lê os NOMES reais da origem e cria o modelo relacional do oimpresso:

  PRODUTO_TABELA (CODIGO, DESCRICAO)      → selling_price_groups (name, business_id)
                                          → customer_groups (name, selling_price_group_id)
  PRODUTO_TABELA_PRECO (produto × tabela) → variation_group_prices (variation_id, price_group_id, price)
  PESSOAS.CODPRODUTO_TABELA               → contacts.customer_group_id

Cadeia oimpresso: contacts.customer_group_id → customer_groups.selling_price_group_id
                  → selling_price_groups (tabela com preços por produto)

Multi-tenant Tier 0: tudo business_id scoped. "FABRICANTE" do Martinho (biz 164)
≠ "FABRICANTE" de outro cliente — FK isola. Generaliza pra qualquer cliente.

Uso:
    python import-tabelas-preco.py --alias "Martinho online" --target-business 164 --target prod --confirm
"""
from __future__ import annotations
import argparse, os, sys
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


def norm(s, n=None):
    if s is None: return None
    s = str(s).strip()
    if not s: return None
    return s[:n] if n else s


def dec(v):
    try: return float(v) if v is not None else 0.0
    except (ValueError, TypeError): return 0.0


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--alias", required=True)
    p.add_argument("--target-business", type=int, required=True)
    p.add_argument("--target", choices=["local", "prod"], default="local")
    p.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    p.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    p.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    p.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    p.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    p.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    p.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    p.add_argument("--confirm", action="store_true")
    args = p.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr); return 2

    print(f"== Import Tabelas de Preço (dinâmico) biz={args.target_business} ==")

    stats = {"tabelas": 0, "spg_ins": 0, "cg_ins": 0, "precos_ins": 0,
             "contacts_linked": 0, "precos_skip_no_prod": 0}

    my = pymysql.connect(
        host=args.mysql_host, port=args.mysql_port,
        user=args.mysql_user, password=args.mysql_password,
        database=args.mysql_database, charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor, autocommit=False,
    )

    try:
        with firebird_connect(args.alias, password_override=args.firebird_password) as fb:
            # ===== 1. PRODUTO_TABELA → selling_price_groups + customer_groups =====
            print("\n[1/4] PRODUTO_TABELA → selling_price_groups + customer_groups")
            cur = fb.cursor()
            cur.execute("SELECT CODIGO, DESCRICAO FROM PRODUTO_TABELA WHERE ATIVO='S' ORDER BY CODIGO")
            tabelas = [(r[0], norm(r[1], 191) or f"Tabela {r[0]}") for r in cur.fetchall()]
            cur.close()
            stats["tabelas"] = len(tabelas)
            print(f"  Tabelas Delphi: {[(c, d) for c, d in tabelas]}")

            # CODPRODUTO_TABELA Delphi → {spg_id, cg_id}
            tabela_map = {}
            with my.cursor() as w:
                # lookups existentes
                w.execute("SELECT id, name FROM selling_price_groups WHERE business_id=%s AND deleted_at IS NULL",
                          (args.target_business,))
                spg_by_name = {r["name"].upper(): r["id"] for r in w.fetchall()}
                w.execute("SELECT id, name, selling_price_group_id FROM customer_groups WHERE business_id=%s",
                          (args.target_business,))
                cg_by_name = {r["name"].upper(): r for r in w.fetchall()}

                for cod, desc in tabelas:
                    key = desc.upper()
                    # selling_price_group
                    if key in spg_by_name:
                        spg_id = spg_by_name[key]
                    else:
                        w.execute(
                            "INSERT INTO selling_price_groups (name, business_id, created_at, updated_at) "
                            "VALUES (%s, %s, NOW(), NOW())", (desc, args.target_business))
                        spg_id = w.lastrowid
                        stats["spg_ins"] += 1
                    # customer_group apontando pro spg
                    if key in cg_by_name:
                        cg_id = cg_by_name[key]["id"]
                        # garante FK correta
                        w.execute("UPDATE customer_groups SET selling_price_group_id=%s WHERE id=%s",
                                  (spg_id, cg_id))
                    else:
                        w.execute(
                            "INSERT INTO customer_groups (business_id, name, amount, price_calculation_type, "
                            "selling_price_group_id, created_by, created_at, updated_at) "
                            "VALUES (%s, %s, 0, 'percentage', %s, %s, NOW(), NOW())",
                            (args.target_business, desc, spg_id, args.created_by))
                        cg_id = w.lastrowid
                        stats["cg_ins"] += 1
                    tabela_map[str(cod)] = {"spg": spg_id, "cg": cg_id}
            my.commit()
            print(f"  selling_price_groups INSERT: {stats['spg_ins']} · customer_groups INSERT: {stats['cg_ins']}")
            print(f"  tabela_map: {tabela_map}")

            # ===== 2. PRODUTO_TABELA_PRECO → variation_group_prices =====
            print("\n[2/4] PRODUTO_TABELA_PRECO → variation_group_prices")
            # lookup sku → variation_id
            with my.cursor() as w:
                w.execute(
                    "SELECT p.sku, v.id AS vid FROM products p "
                    "JOIN variations v ON v.product_id=p.id WHERE p.business_id=%s",
                    (args.target_business,))
                sku_to_var = {str(r["sku"]).strip(): r["vid"] for r in w.fetchall() if r["sku"]}
            print(f"  variations indexadas: {len(sku_to_var)}")

            cur = fb.cursor()
            cur.execute("SELECT CODPRODUTO_TABELA, CODPRODUTO, VALOR FROM PRODUTO_TABELA_PRECO WHERE VALOR > 0")
            batch = 0
            with my.cursor() as w:
                for r in cur:
                    cod_tab, cod_prod, valor = str(r[0]), str(r[1]).strip(), dec(r[2])
                    tm = tabela_map.get(cod_tab)
                    vid = sku_to_var.get(cod_prod)
                    if not tm or not vid or valor <= 0:
                        stats["precos_skip_no_prod"] += 1
                        continue
                    # idempotente: delete + insert
                    w.execute("DELETE FROM variation_group_prices WHERE variation_id=%s AND price_group_id=%s",
                              (vid, tm["spg"]))
                    w.execute(
                        "INSERT INTO variation_group_prices (variation_id, price_group_id, price_inc_tax, created_at, updated_at) "
                        "VALUES (%s, %s, %s, NOW(), NOW())", (vid, tm["spg"], valor))
                    stats["precos_ins"] += 1
                    batch += 1
                    if batch >= 1000:
                        my.commit(); batch = 0
                        print(f"  ... precos_ins {stats['precos_ins']}", flush=True)
            cur.close()
            my.commit()
            print(f"  variation_group_prices INSERT: {stats['precos_ins']} · skip(sem prod): {stats['precos_skip_no_prod']}")

            # ===== 3. PESSOAS.CODPRODUTO_TABELA → contacts.customer_group_id =====
            print("\n[3/4] PESSOAS.CODPRODUTO_TABELA → contacts.customer_group_id")
            import re
            cur = fb.cursor()
            cur.execute("SELECT CNPJCPF, CODPRODUTO_TABELA FROM PESSOAS "
                        "WHERE ATIVO='S' AND CODPRODUTO_TABELA IS NOT NULL AND CNPJCPF IS NOT NULL")
            cnpj_to_cg = {}
            for r in cur.fetchall():
                cnpj = re.sub(r'\D', '', r[0] or '')
                tm = tabela_map.get(str(r[1]))
                if cnpj and tm:
                    cnpj_to_cg[cnpj] = tm["cg"]
            cur.close()
            print(f"  PESSOAS com tabela definida: {len(cnpj_to_cg)}")

            with my.cursor() as w:
                w.execute("SELECT id, tax_number FROM contacts WHERE business_id=%s AND deleted_at IS NULL "
                          "AND tax_number IS NOT NULL AND tax_number<>''", (args.target_business,))
                rows = w.fetchall()
            batch = 0
            with my.cursor() as w:
                for c in rows:
                    cnpj = re.sub(r'\D', '', c["tax_number"] or '')
                    cg = cnpj_to_cg.get(cnpj)
                    if not cg:
                        continue
                    w.execute("UPDATE contacts SET customer_group_id=%s, updated_at=NOW() WHERE id=%s",
                              (cg, c["id"]))
                    if w.rowcount > 0:
                        stats["contacts_linked"] += 1
                    batch += 1
                    if batch >= 1000:
                        my.commit(); batch = 0
            my.commit()
            print(f"  contacts linkados a customer_group: {stats['contacts_linked']}")

            # ===== 4. Validação =====
            print("\n[4/4] Validação")
            with my.cursor() as w:
                w.execute("SELECT COUNT(*) c FROM selling_price_groups WHERE business_id=%s AND deleted_at IS NULL", (args.target_business,))
                print(f"  selling_price_groups total: {w.fetchone()['c']}")
                w.execute("SELECT COUNT(*) c FROM variation_group_prices vgp "
                          "JOIN selling_price_groups s ON s.id=vgp.price_group_id WHERE s.business_id=%s", (args.target_business,))
                print(f"  variation_group_prices total: {w.fetchone()['c']}")
                w.execute("SELECT COUNT(*) c FROM contacts WHERE business_id=%s AND customer_group_id IS NOT NULL AND deleted_at IS NULL", (args.target_business,))
                print(f"  contacts com customer_group: {w.fetchone()['c']}")
    except Exception as e:
        my.rollback()
        print(f"[ERRO] {e}", file=sys.stderr)
        raise
    finally:
        my.close()

    print("\n== Relatório ==")
    for k, v in stats.items():
        print(f"  {k:22s}: {v}")
    print("[OK] Tabelas de preço concluido")
    return 0


if __name__ == "__main__":
    sys.exit(main())
