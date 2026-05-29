"""
Enrich contacts a partir de PESSOAS Firebird (campos completos).

import-contacts-from-venda.py original extraía só dados INLINE de VENDA
(parcial). Esta enriquecimento pega tudo de PESSOAS direto:

- inscricao_estadual ← INSCIDENT
- contato ← CONTATO
- fantasia + nome_fantasia + supplier_business_name ← FANTASIA
- rua ← ENDERECO
- neighborhood ← BAIRRO
- mobile ← FONE1
- tel2 ← FONE2
- email canonical ← EMAIL (sobrescreve concatenações incorretas)
- obs_comercial ← OBSERVACAO (se existir)
- officeimpresso_codigo ← CODIGO Delphi
- pgto_padrao (best-effort)

Match contact ↔ PESSOAS via tax_number = digits-only(CNPJCPF).

Uso:
    python enrich-contacts.py --alias "Martinho online" --target-business 164 --target prod --confirm
"""
from __future__ import annotations
import argparse, os, re, sys
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
try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore


def digits(s):
    return re.sub(r'\D', '', s or '')


def norm(s, max_len=None):
    if s is None: return None
    s = str(s).strip()
    if not s: return None
    if max_len: s = s[:max_len]
    return s


def has_col(con, table, col):
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        cols = {d[0] for d in cur.description or []}
        return col in cols
    finally:
        cur.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["local", "prod"], default="local")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--confirm", action="store_true")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr); return 2

    print(f"== Enricher CONTACTS v0.1.0 ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")

    if pymysql is None:
        print("[ERRO] pymysql não instalado", file=sys.stderr); return 3

    # 1. Lê PESSOAS Firebird → dict CNPJ_digits → fields
    print("\n[1/3] Lendo PESSOAS Firebird...")
    pessoas_by_cnpj = {}
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        has_obs = has_col(fb_con, "PESSOAS", "OBSERVACAO")
        sel = ("SELECT CODIGO, RAZAOSOCIAL, FANTASIA, CNPJCPF, INSCIDENT, CONTATO, "
               "ENDERECO, BAIRRO, CEP, UF, FONE1, FONE2, EMAIL, ATIVO, CODCONDICAOPAGTO" +
               (", OBSERVACAO" if has_obs else "") +
               " FROM PESSOAS WHERE ATIVO='S' AND CNPJCPF IS NOT NULL")
        cur = fb_con.cursor()
        cur.execute(sel)
        cols = [d[0] for d in cur.description]
        for row in cur:
            d = dict(zip(cols, row))
            cnpj = digits(d.get("CNPJCPF"))
            if not cnpj:
                continue
            # Se houver múltiplas pessoas com mesmo CNPJ (raro), keep a primeira
            pessoas_by_cnpj.setdefault(cnpj, d)
        cur.close()
    print(f"  PESSOAS ATIVO indexadas por CNPJ: {len(pessoas_by_cnpj)}")

    # 2. UPDATE contacts MySQL em batches
    print("\n[2/3] Conectando MySQL e enriquecendo contacts...")
    con = pymysql.connect(
        host=args.mysql_host, port=args.mysql_port,
        user=args.mysql_user, password=args.mysql_password,
        database=args.mysql_database, charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor, autocommit=False,
    )

    stats = {"matched": 0, "updated": 0, "no_match": 0, "errors": 0}
    try:
        with con.cursor() as cur:
            cur.execute(
                "SELECT id, tax_number FROM contacts "
                "WHERE business_id=%s AND deleted_at IS NULL "
                "AND tax_number IS NOT NULL AND tax_number <> ''",
                (args.target_business,),
            )
            all_contacts = cur.fetchall()
        print(f"  contacts pra processar: {len(all_contacts)}")

        batch_count = 0
        for c in all_contacts:
            cnpj = digits(c['tax_number'])
            p = pessoas_by_cnpj.get(cnpj)
            if not p:
                stats["no_match"] += 1
                continue
            stats["matched"] += 1

            # Tenta UPDATE com todos os campos importantes
            try:
                with con.cursor() as wcur:
                    wcur.execute(
                        "UPDATE contacts SET "
                        # Anti-fantasma: NULLIF+COALESCE → nome vazio nunca sobrescreve nome existente
                        " name=COALESCE(NULLIF(%s,''), name), supplier_business_name=%s, fantasia=%s, nome_fantasia=%s, "
                        " inscricao_estadual=%s, ie=%s, contato=%s, "
                        " rua=%s, neighborhood=%s, "
                        " mobile=%s, tel2=%s, landline=%s, "
                        " email=%s, "
                        " state=%s, "
                        " obs_comercial=%s, "
                        " officeimpresso_codigo=%s, "
                        " contact_status='active', "
                        " updated_at=NOW() "
                        "WHERE id=%s",
                        (
                            # name: cadeia anti-fantasma RAZAOSOCIAL → FANTASIA → CONTATO
                            norm(p.get("RAZAOSOCIAL"), 191) or norm(p.get("FANTASIA"), 191) or norm(p.get("CONTATO"), 191),
                            norm(p.get("FANTASIA"), 191),
                            norm(p.get("FANTASIA"), 191),
                            norm(p.get("FANTASIA"), 191),
                            norm(p.get("INSCIDENT"), 50),
                            norm(p.get("INSCIDENT"), 50),
                            norm(p.get("CONTATO"), 191),
                            norm(p.get("ENDERECO"), 191),
                            norm(p.get("BAIRRO"), 191),
                            norm(p.get("FONE1"), 50),
                            norm(p.get("FONE2"), 50),
                            norm(p.get("FONE2"), 50),
                            norm(p.get("EMAIL"), 191),  # SOBRESCREVE com canonical
                            norm(p.get("UF"), 2),
                            norm(p.get("OBSERVACAO"), 500) if "OBSERVACAO" in p else None,
                            norm(str(p.get("CODIGO")), 50),
                            c['id'],
                        ),
                    )
                    if wcur.rowcount > 0:
                        stats["updated"] += 1
            except Exception as e:
                stats["errors"] += 1
                if stats["errors"] < 5:
                    print(f"  [err] id={c['id']} cnpj={cnpj}: {e}")
                continue

            batch_count += 1
            if batch_count >= 500:
                con.commit()
                print(f"  [batch] matched={stats['matched']} updated={stats['updated']}", flush=True)
                batch_count = 0
        con.commit()
        print(f"  [final] commit OK")
    except Exception as e:
        con.rollback()
        print(f"[ERRO] {e}", file=sys.stderr)
        raise
    finally:
        con.close()

    print("\n== Relatório ==")
    for k, v in stats.items():
        print(f"  {k:15s}: {v}")
    print(f"[OK] Enricher CONTACTS concluido")
    return 0


if __name__ == "__main__":
    sys.exit(main())
