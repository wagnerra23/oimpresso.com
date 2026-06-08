#!/usr/bin/env python3
"""
enrich-tipos.py — Migra a classificação de tipo de pessoa do WR Comercial (Delphi)
para os contatos do oimpresso, fielmente (ADR 0188 multi-type flags + ADR 0197 primary_role).

FONTE (Firebird PESSOAS): colunas dinâmicas IS_<TIPO> (S/N), uma por tipo do catálogo
PESSOAS_TIPO. Confirmado em Classes.Pessoas.pas:161-190 (a aba "Tipos" lê IS_<COD> de PESSOAS).

Catálogo Martinho:
  CLI=Cliente · COM=Comércio/Revenda · FIN=Consumidor Final · FAB=Fabricante-Indústria
  ISE=Isento · SST=Uso/Consumo s/ ST · USO=Uso/Consumo c/ ST · CPF=CPF · PES=Pessoa
  FOR=Fornecedor · FUN=Funcionário · REP=Representante

DESTINO (MySQL contacts):
  - is_customer/is_supplier/is_employee/is_representative  (4 flags ADR 0188)
  - primary_role (enum) + type (papel principal, sincronizado — invariante 5 do ADR 0188)
  - custom_field3 = classificação fiscal legível ("Fabricante-Indústria", etc.) — perfil
    fiscal do COMPRADOR, ortogonal ao papel; resolve a queixa "FAN COM marcado como cliente".
  - business.custom_labels.contact.custom_field_3 = rótulo visível na tela.

Match: contacts.officeimpresso_codigo == PESSOAS.CODIGO (cobre 9771/9794 biz=164).
Idempotente: UPDATE puro; custom_field3 via COALESCE(NULLIF(...)) não apaga valor já bom.
"""
from __future__ import annotations
import argparse, os, sys, json
os.environ.setdefault("FIREBIRD_PY_DRIVER", "fdb")
sys.path.insert(0, os.path.dirname(__file__))
from lib.firebird_reader import firebird_connect  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None

# Tipos que classificam um COMPRADOR (perfil fiscal) → todos implicam is_customer.
BUYER_TYPES = {
    "CLI": "Cliente",
    "COM": "Comércio/Revenda",
    "FIN": "Consumidor Final",
    "FAB": "Fabricante-Indústria",
    "ISE": "Isento",
    "SST": "Uso e Consumo (sem ST)",
    "USO": "Uso e Consumo (com ST)",
    "CPF": "CPF",
    "PES": "Pessoa",
}
# Tipos que são PAPEL relacional distinto.
ROLE_TYPES = {
    "FOR": ("is_supplier", "Fornecedor"),
    "FUN": ("is_employee", "Funcionário"),
    "REP": ("is_representative", "Representante"),
}
ALL_CODES = list(BUYER_TYPES.keys()) + list(ROLE_TYPES.keys())


def s(v) -> bool:
    return str(v).strip().upper() == "S" if v is not None else False


def classify(row: dict) -> dict:
    """row tem IS_<COD> (S/N). Retorna flags + primary_role + type + classif. fiscal."""
    is_supplier = s(row.get("IS_FOR"))
    is_employee = s(row.get("IS_FUN"))
    is_representative = s(row.get("IS_REP"))
    buyer_active = [c for c in BUYER_TYPES if s(row.get(f"IS_{c}"))]
    is_customer = bool(buyer_active)
    # Pegadinha ADR 0188: nunca todas flags 0. Sem nenhum papel → default cliente.
    if not (is_customer or is_supplier or is_employee or is_representative):
        is_customer = True

    # primary_role: prioridade cliente > fornecedor > representante > funcionário
    if is_customer:
        primary = "customer"
    elif is_supplier:
        primary = "supplier"
    elif is_representative:
        primary = "representative"
    elif is_employee:
        primary = "employee"
    else:
        primary = "customer"

    # Classificação fiscal legível (só perfis de comprador; ordem do catálogo)
    classif = ", ".join(BUYER_TYPES[c] for c in BUYER_TYPES if c in buyer_active)
    return {
        "is_customer": int(is_customer),
        "is_supplier": int(is_supplier),
        "is_employee": int(is_employee),
        "is_representative": int(is_representative),
        "primary_role": primary,
        "type": primary,
        "classif": classif or None,
    }


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--alias", default="Martinho online")
    p.add_argument("--target-business", type=int, default=164)
    p.add_argument("--target", choices=["local", "prod"], default="prod")
    p.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    p.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    p.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    p.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    p.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    p.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    p.add_argument("--label", default="Classificação (WR Comercial)")
    p.add_argument("--confirm", action="store_true")
    p.add_argument("--dry-run", action="store_true")
    args = p.parse_args()

    if args.target == "prod" and not (args.confirm or args.dry_run):
        print("[ERRO] --target prod requer --confirm (ou --dry-run)", file=sys.stderr)
        return 2
    if pymysql is None:
        print("[ERRO] pymysql não instalado", file=sys.stderr)
        return 3

    biz = args.target_business
    print("== enrich-tipos v1.0 ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {biz}")
    print(f"  Target / dry-run : {args.target} / {args.dry_run}")

    # 1) Lê PESSOAS Firebird → dict por CODIGO
    flagsel = ", ".join(f"IS_{c}" for c in ALL_CODES)
    print("\n[1/4] Lendo flags IS_* de PESSOAS Firebird...")
    pessoas: dict[str, dict] = {}
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb:
        cur = fb.cursor()
        cur.execute(f"SELECT CODIGO, {flagsel} FROM PESSOAS")
        cols = [d[0] for d in cur.description]
        for r in cur:
            d = dict(zip(cols, r))
            cod = (str(d.get("CODIGO")).strip() if d.get("CODIGO") is not None else "")
            if cod:
                pessoas[cod] = d
        cur.close()
    print(f"  PESSOAS lidas: {len(pessoas)}")

    # 2) Conecta MySQL e processa contacts por officeimpresso_codigo
    print("\n[2/4] Conectando MySQL e classificando contacts...")
    con = pymysql.connect(
        host=args.mysql_host, port=args.mysql_port, user=args.mysql_user,
        password=args.mysql_password, database=args.mysql_database,
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor, autocommit=False,
    )
    stats = {"matched": 0, "updated": 0, "no_match": 0, "errors": 0,
             "is_customer": 0, "is_supplier": 0, "is_employee": 0, "is_representative": 0}
    examples = []
    # Agrupa contatos por assinatura de classificação → 1 UPDATE por combo (lote)
    # em vez de 9771 round-trips pelo túnel SSH (lento + risco de lock).
    groups: dict[tuple, list[int]] = {}
    try:
        with con.cursor() as cur:
            cur.execute(
                "SELECT id, officeimpresso_codigo, name FROM contacts "
                "WHERE business_id=%s AND deleted_at IS NULL "
                "AND officeimpresso_codigo IS NOT NULL AND officeimpresso_codigo<>''",
                (biz,),
            )
            rows = cur.fetchall()
        print(f"  contacts com officeimpresso_codigo: {len(rows)}")

        for c in rows:
            cod = str(c["officeimpresso_codigo"]).strip()
            p = pessoas.get(cod)
            if not p:
                stats["no_match"] += 1
                continue
            stats["matched"] += 1
            cl = classify(p)
            for k in ("is_customer", "is_supplier", "is_employee", "is_representative"):
                stats[k] += cl[k]
            sig = (cl["is_customer"], cl["is_supplier"], cl["is_employee"],
                   cl["is_representative"], cl["primary_role"], cl["type"], cl["classif"])
            groups.setdefault(sig, []).append(c["id"])
            if args.dry_run and len(examples) < 12 and (cl["is_supplier"] or cl["is_employee"]
                                                        or cl["is_representative"] or cl["classif"]):
                examples.append((cod, c["name"][:30], cl["type"], cl["is_customer"],
                                 cl["is_supplier"], cl["is_employee"],
                                 cl["is_representative"], cl["classif"]))

        print(f"  combinações distintas: {len(groups)}")
        if not args.dry_run:
            CHUNK = 1000
            for sig, ids in groups.items():
                isc, iss, ise, isr, prole, ptype, classif = sig
                # classif None → não tocar custom_field3 (preserva valor de outras origens)
                if classif:
                    set_clause = ("is_customer=%s, is_supplier=%s, is_employee=%s, "
                                  "is_representative=%s, primary_role=%s, type=%s, "
                                  "custom_field3=%s, updated_at=NOW()")
                    head = [isc, iss, ise, isr, prole, ptype, classif]
                else:
                    set_clause = ("is_customer=%s, is_supplier=%s, is_employee=%s, "
                                  "is_representative=%s, primary_role=%s, type=%s, "
                                  "updated_at=NOW()")
                    head = [isc, iss, ise, isr, prole, ptype]
                for i in range(0, len(ids), CHUNK):
                    chunk = ids[i:i + CHUNK]
                    ph = ",".join(["%s"] * len(chunk))
                    try:
                        with con.cursor() as wcur:
                            wcur.execute(
                                f"UPDATE contacts SET {set_clause} WHERE id IN ({ph})",
                                head + chunk,
                            )
                            stats["updated"] += wcur.rowcount
                    except Exception as e:
                        stats["errors"] += 1
                        if stats["errors"] <= 5:
                            print(f"  [erro] sig={sig}: {e}")

        # 3) Rotula custom_field_3 no business.custom_labels (visível na tela)
        if not args.dry_run:
            print("\n[3/4] Rotulando custom_field_3 no business...")
            with con.cursor() as cur:
                cur.execute("SELECT custom_labels FROM business WHERE id=%s", (biz,))
                row = cur.fetchone()
                labels = {}
                if row and row.get("custom_labels"):
                    try:
                        labels = json.loads(row["custom_labels"])
                    except Exception:
                        labels = {}
                labels.setdefault("contact", {})
                if not labels["contact"].get("custom_field_3"):
                    labels["contact"]["custom_field_3"] = args.label
                    cur.execute("UPDATE business SET custom_labels=%s WHERE id=%s",
                                (json.dumps(labels, ensure_ascii=False), biz))
                    print(f"  rótulo custom_field_3 = {args.label!r}")
                else:
                    print(f"  custom_field_3 já rotulado: {labels['contact']['custom_field_3']!r}")

        if args.dry_run:
            con.rollback()
            print("\n[dry-run] Amostra (cod, nome, type, cust, sup, emp, rep, classif):")
            for e in examples:
                print("   ", e)
        else:
            con.commit()
    finally:
        con.close()

    print("\n[4/4] Resultado:")
    for k, v in stats.items():
        print(f"  {k:18} = {v}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
