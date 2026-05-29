"""
Enrich contacts v2 — preenche campos que a UI lê de verdade + mapeamentos.

CORRIGE v1: campo de endereço da UI é address_line_1 (NÃO rua).

Mapeia de PESSOAS (+ CIDADES + CONDICAO_PAGTO lookup):
  - address_line_1 ← ENDERECO       (CRÍTICO — UI EnderecoTab lê isto)
  - numero         ← NUMERO
  - address_line_2 ← COMPLEMENTO
  - zip_code       ← CEP
  - city           ← CIDADES.DESCRICAO via CODCIDADE
  - city_code      ← CODCIDADE (IBGE)
  - pgto_padrao    ← map CONDICAO_PAGTO.DESCRICAO → enum(pix|boleto|cartao|dinheiro|transferencia)
  - custom_field1  ← MENSAGEM_PARA_VENDA (não há campo dedicado no schema)
  - custom_field2  ← TABELA_PRECO/grupo literal (FABRICANTE não cabe no enum fixo)

Uso:
    python enrich-contacts-v2.py --alias "Martinho online" --target-business 164 --target prod --confirm
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
import pymysql, pymysql.cursors


def digits(s):
    return re.sub(r'\D', '', s or '')


def norm(s, n=None):
    if s is None: return None
    s = str(s).strip()
    if not s: return None
    return s[:n] if n else s


def map_pgto(desc):
    """CONDICAO_PAGTO.DESCRICAO Delphi → enum oimpresso."""
    if not desc:
        return None
    d = desc.upper()
    if 'PIX' in d: return 'pix'
    if 'BOLETO' in d: return 'boleto'
    if 'CART' in d: return 'cartao'
    if 'DINHEIRO' in d or 'ESPECIE' in d or 'VISTA' in d: return 'dinheiro'
    if 'TRANSF' in d or 'DEP' in d or 'TED' in d or 'DOC' in d: return 'transferencia'
    return 'boleto'  # default mais comum no B2B


def table_exists(con, name):
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 1 FROM {name}")
        cur.fetchone()
        return True
    except Exception:
        return False
    finally:
        cur.close()


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
    p.add_argument("--confirm", action="store_true")
    args = p.parse_args()
    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr); return 2

    print("== Enricher CONTACTS v2 (endereço correto + mapeamentos) ==")

    # 1. Lê PESSOAS + CIDADES + CONDICAO_PAGTO
    print("\n[1/2] Lendo PESSOAS Firebird (com lookups)...")
    pessoas = {}
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb:
        # Descobre nome da tabela de cidade
        cidade_tbl = None
        for cand in ("CIDADES", "CIDADE", "MUNICIPIO", "MUNICIPIOS"):
            if table_exists(fb, cand):
                cidade_tbl = cand
                break
        cond_tbl = None
        for cand in ("CONDICAO_PAGTO", "CONDICAOPAGTO", "CONDICAO_PAGAMENTO"):
            if table_exists(fb, cand):
                cond_tbl = cand
                break
        print(f"  cidade_tbl={cidade_tbl} cond_tbl={cond_tbl}")

        cid_join = f"LEFT JOIN {cidade_tbl} CID ON CID.CODIGO = P.CODCIDADE" if cidade_tbl else ""
        cid_sel = "CID.DESCRICAO AS CIDADE_NOME," if cidade_tbl else "CAST(NULL AS VARCHAR(1)) AS CIDADE_NOME,"
        cond_join = f"LEFT JOIN {cond_tbl} CP ON CP.CODIGO = P.CODCONDICAOPAGTO" if cond_tbl else ""
        cond_sel = "CP.DESCRICAO AS COND_DESC," if cond_tbl else "CAST(NULL AS VARCHAR(1)) AS COND_DESC,"

        sql = (
            f"SELECT P.CNPJCPF, P.ENDERECO, P.NUMERO, P.COMPLEMENTO, P.CEP, P.CODCIDADE, "
            f" {cid_sel} {cond_sel} P.MENSAGEM_PARA_VENDA, P.CODPESSOAS_GRUPO "
            f"FROM PESSOAS P {cid_join} {cond_join} "
            f"WHERE P.ATIVO='S' AND P.CNPJCPF IS NOT NULL"
        )
        cur = fb.cursor()
        cur.execute(sql)
        cols = [d[0] for d in cur.description]
        for row in cur:
            d = dict(zip(cols, row))
            k = digits(d.get("CNPJCPF"))
            if k:
                pessoas.setdefault(k, d)
        cur.close()
    print(f"  PESSOAS indexadas: {len(pessoas)}")

    # 2. UPDATE contacts
    print("\n[2/2] Atualizando contacts...")
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
                "SELECT id, tax_number FROM contacts WHERE business_id=%s AND deleted_at IS NULL "
                "AND tax_number IS NOT NULL AND tax_number<>''",
                (args.target_business,),
            )
            rows = cur.fetchall()
        print(f"  contacts: {len(rows)}")

        batch = 0
        for c in rows:
            pp = pessoas.get(digits(c['tax_number']))
            if not pp:
                stats["no_match"] += 1; continue
            stats["matched"] += 1
            try:
                with con.cursor() as w:
                    w.execute(
                        "UPDATE contacts SET "
                        " address_line_1=%s, numero=%s, address_line_2=%s, "
                        " zip_code=%s, city=%s, city_code=%s, "
                        " pgto_padrao=%s, "
                        " custom_field1=%s, custom_field2=%s, "
                        " updated_at=NOW() "
                        "WHERE id=%s",
                        (
                            norm(pp.get("ENDERECO"), 191),
                            norm(pp.get("NUMERO"), 30),
                            norm(pp.get("COMPLEMENTO"), 191),
                            norm(pp.get("CEP"), 20),
                            norm(pp.get("CIDADE_NOME"), 100),
                            norm(str(pp.get("CODCIDADE")) if pp.get("CODCIDADE") else None, 20),
                            map_pgto(pp.get("COND_DESC")),
                            norm(pp.get("MENSAGEM_PARA_VENDA"), 250),
                            norm(f"grupo_delphi={pp.get('CODPESSOAS_GRUPO')}", 100),
                            c['id'],
                        ),
                    )
                    if w.rowcount > 0:
                        stats["updated"] += 1
            except Exception as e:
                stats["errors"] += 1
                if stats["errors"] < 5:
                    print(f"  [err] id={c['id']}: {e}")
                continue
            batch += 1
            if batch >= 500:
                con.commit()
                print(f"  [batch] matched={stats['matched']} updated={stats['updated']}", flush=True)
                batch = 0
        con.commit()
        print("  [final] commit OK")
    except Exception as e:
        con.rollback(); print(f"[ERRO] {e}", file=sys.stderr); raise
    finally:
        con.close()

    print("\n== Relatório ==")
    for k, v in stats.items():
        print(f"  {k:12s}: {v}")
    print("[OK] Enricher v2 concluido")
    return 0


if __name__ == "__main__":
    sys.exit(main())
