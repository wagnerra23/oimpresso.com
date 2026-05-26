"""
Fase 2-alt — Importer CONTACTS extraídos inline de VENDA Delphi WR Comercial.

Para clientes legacy com CRM órfão (caso Martinho v1404):
- VENDA tem dados de cliente INLINE em RAZAOSOCIAL/RESPONSAVEL_* (texto)
- Tabela CLIENTES existe mas não é populada via FK CODCLIENTE_SITE
- Não dá pra usar import-empresas.py (que lê tabela EMPRESA — entidades próprias)

Este importer extrai `SELECT DISTINCT` clientes únicos de VENDA por
`RESPONSAVEL_CNPJCPF` (chave natural de dedup) + popula `contacts` em
business alvo.

UPSERT idempotente via (business_id, legacy_id=CNPJ_normalized). Re-rodar
é no-op pra rows existentes, update pra mudanças, insert pra novos.

Pareado com import-vendas.py:
- Rodar PRIMEIRO este (Fase 2-alt) — popula contacts.legacy_id=CNPJ
- Rodar import-vendas.py depois — resolve transactions.contact_id via
  lookup CNPJ → contacts.id

Multi-tenant Tier 0 (ADR 0093): impõe business_id em todo INSERT/UPDATE.

PII redaction (audit JSON): CPFCNPJ, telefones, emails → [REDACTED].

Uso:
    # Dry-run (gera SQL preview + audit, não toca DB)
    python import-contacts-from-venda.py --alias MartinhoServidor --target-business 164

    # Local Laragon (Herd dev)
    python import-contacts-from-venda.py --alias MartinhoServidor --target-business 164 \\
        --target local

    # Hostinger prod (perigoso — exige --confirm)
    python import-contacts-from-venda.py --alias MartinhoServidor --target-business 164 \\
        --target prod --confirm

Refs:
    - memory/reference/migracao-officeimpresso-pattern.md §FK convention drift
    - memory/research/clientes-legacy-officeimpresso/_MAPPING/relacionamentos-fk-firebird.sql
    - ADR 0093 — Multi-tenant Tier 0
    - .claude/agents/migracao-firebird-versoes.md
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

from lib.firebird_reader import firebird_connect, query  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore

IMPORTER_VERSION = "0.1.0"
LEGACY_SOURCE = "wr-comercial-delphi"

# Colunas inline canônicas em VENDA pra extrair cliente (Martinho v1404)
# Cols ausentes em outras versões viram NULL (adapter)
COLS_CLIENTE_INLINE = [
    "RAZAOSOCIAL",                 # nome — chave secundária
    "RESPONSAVEL_CNPJCPF",         # PII — chave dedup principal
    "RESPONSAVEL_INSCIDENT",       # IE
    "RESPONSAVEL_TIPO",            # F/J
    "RESPONSAVEL_ENDERECO",
    "RESPONSAVEL_NUMERO",
    "RESPONSAVEL_BAIRRO",
    "RESPONSAVEL_CEP",
    "RESPONSAVEL_CIDADE",
    "RESPONSAVEL_UF",
    "RESPONSAVEL_EMAIL",
    "TELEFONE",
    "CONTATO",
]

PII_FIELDS = {"RESPONSAVEL_CNPJCPF", "TELEFONE", "RESPONSAVEL_EMAIL"}


def normalize_cpf_cnpj(raw) -> str | None:
    """Remove pontuação CPF/CNPJ. Retorna None se vazio."""
    if not raw:
        return None
    s = str(raw).strip()
    digits = re.sub(r"\D", "", s)
    if not digits or len(digits) not in (11, 14):
        return None
    return digits


def normalize_str(raw, max_len: int | None = None) -> str | None:
    if raw is None:
        return None
    s = str(raw).strip()
    if not s:
        return None
    if max_len is not None and len(s) > max_len:
        s = s[:max_len]
    return s


def redact_pii(value) -> str | None:
    if value is None or value == "":
        return None
    return "[REDACTED]"


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def build_adapted_distinct_select(cols_presentes: set[str]) -> tuple[str, list[str]]:
    """Gera SELECT DISTINCT adaptado — cols ausentes viram NULL."""
    select_parts = []
    used_cols = []
    for c in COLS_CLIENTE_INLINE:
        if c in cols_presentes:
            select_parts.append(c)
            used_cols.append(c)
        else:
            select_parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
            used_cols.append(c)
    return ", ".join(select_parts), used_cols


def derive_contact_type(tipo_responsavel: str | None) -> str:
    """RESPONSAVEL_TIPO Delphi (F/J) → contact.contact_type."""
    t = (tipo_responsavel or "").upper().strip()
    if t == "F":
        return "person"
    return "business"  # default J + null + outros


def map_venda_to_contact(
    row: dict, business_id: int
) -> tuple[dict, dict] | None:
    """Distinct VENDA row → linha em `contacts`. Retorna None se sem CNPJ.

    Retorna (data, audit).
    """
    cnpj = normalize_cpf_cnpj(row.get("RESPONSAVEL_CNPJCPF"))
    if not cnpj:
        return None  # sem CNPJ não dá pra dedup → skip

    razao = normalize_str(row.get("RAZAOSOCIAL"))
    contact_type = derive_contact_type(row.get("RESPONSAVEL_TIPO"))

    # Schema contacts UltimatePOS: varchar tamanhos justos — truncar pra evitar overflow.
    # cpf_cnpj(20), ie_rg(18), rua(80), numero(10), bairro(40), zip_code(20),
    # city/state(191), name/email/landline(191), mobile(191) NOT NULL.
    data = {
        "business_id": business_id,
        "type": "customer",  # cliente (não fornecedor) — Martinho oficina sub-vertical 4 mecânica pesada caminhão basculante (ADR 0194; pré-correção dizia "oficina/locação")
        "contact_type": contact_type,
        "name": normalize_str(razao, 191) or f"Legacy CNPJ {cnpj[:6]}...",
        "supplier_business_name": normalize_str(razao, 191),
        "cpf_cnpj": cnpj[:20],
        "tax_number": cnpj[:20],
        "ie_rg": normalize_str(row.get("RESPONSAVEL_INSCIDENT"), 18),
        "rua": normalize_str(row.get("RESPONSAVEL_ENDERECO"), 80),
        "numero": normalize_str(row.get("RESPONSAVEL_NUMERO"), 10),
        "bairro": normalize_str(row.get("RESPONSAVEL_BAIRRO"), 40),
        "zip_code": normalize_str(row.get("RESPONSAVEL_CEP"), 20),
        "city": normalize_str(row.get("RESPONSAVEL_CIDADE"), 191),
        "state": normalize_str(row.get("RESPONSAVEL_UF"), 191),
        "country": "BRASIL",
        "email": normalize_str(row.get("RESPONSAVEL_EMAIL"), 191),
        # mobile é NOT NULL — fallback '' se vazio
        "mobile": normalize_str(row.get("TELEFONE"), 191) or "",
        "contact_status": "active",
        "legacy_id": cnpj[:32],  # CNPJ é chave natural dedup
    }

    # Audit metadata
    pii_safe = {}
    for k in COLS_CLIENTE_INLINE:
        v = row.get(k)
        if k in PII_FIELDS:
            pii_safe[k] = redact_pii(v)
        else:
            pii_safe[k] = (str(v).strip() if v is not None else None)

    audit = {
        "legacy_source": LEGACY_SOURCE,
        "legacy_id": cnpj,
        "razaosocial_denormalized": razao,
        "contact_type_inferido": contact_type,
        "raw_delphi_pii_safe": pii_safe,
        "imported_at_iso": datetime.utcnow().isoformat() + "Z",
        "importer_version": IMPORTER_VERSION,
    }

    return data, audit


def sql_value(v) -> str:
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    s = str(v).replace("'", "''")
    return "'" + s + "'"


def emit_insert_sql(data: dict) -> str:
    cols = [c for c, v in data.items() if v is not None]
    vals = ", ".join(sql_value(data[c]) for c in cols)
    return (
        f"INSERT INTO contacts ({', '.join(cols)}, created_at, updated_at) "
        f"VALUES ({vals}, NOW(), NOW());"
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument(
        "--target", choices=["dry-run", "local", "prod"], default="dry-run"
    )
    parser.add_argument("--start-date", help="Filtro VENDA.DT_EMISSAO >= YYYY-MM-DD")
    parser.add_argument("--end-date", help="Filtro VENDA.DT_EMISSAO <= YYYY-MM-DD")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm explicito (seguranca)", file=sys.stderr)
        return 2

    print(f"== Importer Contacts (extraidos inline de VENDA) v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")
    if args.start_date or args.end_date:
        print(f"  Filtro DT_EMISSAO: [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]")

    stats = {
        "venda_lidos_distinct": 0,
        "skipped_no_cnpj": 0,
        "inserts": 0,
        "updates": 0,
        "errors": 0,
    }
    dry_run_lines = [
        f"-- Generated by import-contacts-from-venda v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business={args.target_business}  alias={args.alias}",
        f"-- Filter: VENDA.DT_EMISSAO [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]",
        "",
        "-- Dedup: SELECT DISTINCT por RESPONSAVEL_CNPJCPF (normalizado digits-only).",
        "-- Idempotência: SELECT por (business_id, legacy_id=CNPJ) → UPDATE OU INSERT.",
        "",
    ]
    audit_records = []
    sample_inserts = []

    print("\n[Firebird] Conectando...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        cols_existentes_venda = get_existing_cols(fb_con, "VENDA")
        cols_ausentes = [c for c in COLS_CLIENTE_INLINE if c not in cols_existentes_venda]
        print(f"[Adapter] VENDA cols presentes: {len(cols_existentes_venda)} · cliente-inline canônicas pedidas: {len(COLS_CLIENTE_INLINE)} · ausentes: {len(cols_ausentes)}")
        if cols_ausentes:
            print(f"          Ausentes (NULL): {cols_ausentes}")

        select_clause, _ = build_adapted_distinct_select(cols_existentes_venda)

        where_parts = ["RESPONSAVEL_CNPJCPF IS NOT NULL", "TRIM(RESPONSAVEL_CNPJCPF) <> ''"]
        params: list = []
        if args.start_date:
            where_parts.append("DT_EMISSAO >= ?")
            params.append(args.start_date)
        if args.end_date:
            where_parts.append("DT_EMISSAO <= ?")
            params.append(args.end_date + " 23:59:59")
        where_clause = "WHERE " + " AND ".join(where_parts)

        sql = f"""
            SELECT DISTINCT {select_clause}
            FROM VENDA
            {where_clause}
            ORDER BY RESPONSAVEL_CNPJCPF, RAZAOSOCIAL
        """
        print(f"\n[Query DISTINCT VENDA] {sql.strip()[:200]}...")

        cur = fb_con.cursor()
        cur.execute(sql, tuple(params))
        col_names = [d[0] for d in cur.description]

        # MySQL writer
        con = None
        if args.target in ("local", "prod"):
            if pymysql is None:
                print("[ERRO] pymysql nao instalado: pip install pymysql", file=sys.stderr)
                return 3
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )

        try:
            for row_tuple in cur:
                stats["venda_lidos_distinct"] += 1
                row = dict(zip(col_names, row_tuple))
                result = map_venda_to_contact(row, args.target_business)
                if result is None:
                    stats["skipped_no_cnpj"] += 1
                    continue
                data, audit = result
                audit_records.append(audit)

                if args.target == "dry-run":
                    s = emit_insert_sql(data)
                    if len(sample_inserts) < 5:
                        sample_inserts.append(s)
                    dry_run_lines.append(f"-- contact CNPJ=[REDACTED-{data['legacy_id'][:4]}...] razao=\"{(data['name'] or '')[:40]}\"")
                    dry_run_lines.append(s)
                    dry_run_lines.append("")
                    stats["inserts"] += 1
                else:
                    assert con is not None
                    data_filtered = {k: v for k, v in data.items() if v is not None}
                    with con.cursor() as wcur:
                        wcur.execute(
                            "SELECT id FROM contacts WHERE business_id=%s AND legacy_id=%s LIMIT 1",
                            (args.target_business, data["legacy_id"]),
                        )
                        existing = wcur.fetchone()
                        if existing:
                            update_fields = {
                                k: v for k, v in data_filtered.items()
                                if k not in ("business_id", "legacy_id", "type", "contact_type")
                            }
                            set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                            wcur.execute(
                                f"UPDATE contacts SET {set_clause}, updated_at=NOW() WHERE id=%s",
                                (*update_fields.values(), existing["id"]),
                            )
                            stats["updates"] += 1
                        else:
                            cols = list(data_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO contacts ({', '.join(cols)}, created_by, created_at, updated_at) "
                                f"VALUES ({placeholders}, %s, NOW(), NOW())",
                                (*data_filtered.values(), args.created_by),
                            )
                            stats["inserts"] += 1
            if con:
                con.commit()
                print("\n[OK] Commit MySQL")
        except Exception as e:
            if con:
                con.rollback()
                print("\n[ERRO] Rollback MySQL", file=sys.stderr)
            stats["errors"] += 1
            print(f"Excecao: {e!r}", file=sys.stderr)
            raise
        finally:
            cur.close()
            if con:
                con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    biz_suffix = f"biz{args.target_business}"

    if args.target == "dry-run":
        sql_path = out / f"dry-run-contacts-from-venda-{biz_suffix}-{ts}.sql"
        sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
        print(f"\n[SQL preview] {sql_path}")

    audit_path = out / f"audit-contacts-from-venda-{biz_suffix}-{ts}.json"
    audit_summary = {
        "importer_version": IMPORTER_VERSION,
        "alias": args.alias,
        "business_id": args.target_business,
        "target": args.target,
        "filter": {"start_date": args.start_date, "end_date": args.end_date},
        "adapter": {
            "cols_canonicas_pedidas": len(COLS_CLIENTE_INLINE),
            "cols_ausentes": cols_ausentes,
        },
        "stats": stats,
        "sample_inserts": sample_inserts,
        "records_total": len(audit_records),
        "records_sample_first_500": audit_records[:500],
    }
    audit_path.write_text(
        json.dumps(audit_summary, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  VENDA distinct (com CNPJ): {stats['venda_lidos_distinct']}")
    print(f"  Skipped (sem CNPJ valido): {stats['skipped_no_cnpj']}")
    print(f"  Contacts INSERT          : {stats['inserts']}")
    print(f"  Contacts UPDATE          : {stats['updates']}")
    print(f"  Errors                   : {stats['errors']}")
    print(f"[OK] Contacts concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
