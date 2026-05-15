"""
Fase 2-bis — Importer CONTACTS (fornecedores) extraídos de NOTA_FISCAL_ENTRADA
Delphi WR Comercial → `contacts` UltimatePOS (type='supplier' ou 'both').

Por que existe (gap detectado 2026-05-14):
- `import-contacts-from-venda.py` extrai apenas CLIENTES inline de VENDA
  (RESPONSAVEL_*) — ZERO fornecedores são migrados por aquele importer.
- `import-compras.py` precisa de `contact_id` (NOT NULL FK em transactions)
  pra cada NFe entrada. Sem esse importer rodar antes, 15.617 NFs Martinho
  ficariam órfãs com `contact_id=NULL` → skip massivo + dados incompletos.

Lógica:
- Read DISTINCT (NF_CNPJCPF_EMITENTE, NF_RAZAOSOCIAL_EMITENTE) de
  NOTA_FISCAL_ENTRADA Firebird.
- Filtros canônicos: CNPJ/RAZAO NOT NULL · ATIVO='S'.
- Normalize CNPJ (digits only · valida len 11 ou 14).
- UPSERT idempotente em `contacts` UltimatePOS:
   * existe E type=customer → UPDATE type=both (cliente que também é fornecedor)
   * existe E type IN (supplier, both) → no-op (idempotente)
   * NÃO existe → INSERT type=supplier
- Chave natural: (business_id, legacy_id=CNPJ_normalizado).

Pareado com `import-compras.py` — Wagner roda PRIMEIRO este, DEPOIS compras.
A ordem canônica do daemon dual-sync ficou:
  contacts (clientes via VENDA) →
  contacts-fornecedores-nfe (fornecedores via NFE) →
  compras (NFE + lines · contact_id resolvido).

Multi-tenant Tier 0 (ADR 0093): business_id obrigatório em todo INSERT/UPDATE.
Cinto-suspensório: UPDATE sempre com `AND business_id=%s` em SET (lição
incidente ROTA LIVRE 14/maio — memory/reference/feedback-importer-cross-business-bug.md).

PII redaction (audit JSON):
  NF_CNPJCPF_EMITENTE → [REDACTED-XXXX...] (4 primeiros dígitos)
  NF_RAZAOSOCIAL_EMITENTE → [REDACTED]

Uso:
    # Dry-run (gera SQL preview + audit, não toca DB)
    python import-contacts-from-nfe.py --alias MartinhoServidor --target-business 164

    # Local Laragon (Herd dev)
    python import-contacts-from-nfe.py --alias MartinhoServidor --target-business 164 --target local

    # Hostinger prod (perigoso — exige --confirm)
    python import-contacts-from-nfe.py --alias MartinhoServidor --target-business 164 --target prod --confirm

    # Daemon dual-sync — delta-only
    python import-contacts-from-nfe.py --alias MartinhoServidor --target-business 164 \\
        --delta-since-last-sync --sync-type contacts-fornecedores-nfe

Refs:
    - import-contacts-from-venda.py — pattern PESSOAS inline VENDA (cliente)
    - import-compras.py — consumidor desse lookup (resolve contact_id)
    - memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md §6.1
    - memory/reference/feedback-importer-cross-business-bug.md
    - ADR 0093 — Multi-tenant Tier 0
    - ADR 0101 — Pest biz=1 nunca cliente
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

from lib.firebird_reader import firebird_connect, has_column  # noqa: E402
from lib import sync_checkpoint as sc  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore

IMPORTER_VERSION = "0.1.0"  # primeira versão — fornecedores via NFe entrada
LEGACY_SOURCE = "wr-comercial-delphi"
SYNC_TYPE_DEFAULT = "contacts-fornecedores-nfe"

# Colunas canônicas pra extrair fornecedor de NOTA_FISCAL_ENTRADA.
# Cols ausentes em outras versões viram NULL (adapter).
COLS_FORNECEDOR_NFE = [
    "NF_CNPJCPF_EMITENTE",       # PII — chave dedup principal
    "NF_RAZAOSOCIAL_EMITENTE",   # PII — nome
    "ATIVO",                     # filtro
]

PII_FIELDS = {"NF_CNPJCPF_EMITENTE", "NF_RAZAOSOCIAL_EMITENTE"}


def normalize_cpf_cnpj(raw) -> str | None:
    """Remove pontuação CPF/CNPJ. Retorna None se vazio ou len inválido."""
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


def redact_cnpj_prefix(cnpj: str | None) -> str | None:
    """CNPJ → [REDACTED-XXXX...] (preserva 4 dígitos pra audit cruzado)."""
    if not cnpj:
        return None
    return f"[REDACTED-{cnpj[:4]}...]"


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def derive_contact_type(cnpj_digits: str) -> str:
    """11 dígitos = CPF (pessoa física) · 14 dígitos = CNPJ (jurídica)."""
    return "person" if len(cnpj_digits) == 11 else "business"


def map_nfe_to_supplier_contact(
    row: dict, business_id: int
) -> tuple[dict, dict] | None:
    """Distinct NFE row → linha em `contacts` (type=supplier). Retorna None se sem CNPJ.

    Retorna (data, audit).
    """
    cnpj = normalize_cpf_cnpj(row.get("NF_CNPJCPF_EMITENTE"))
    if not cnpj:
        return None  # sem CNPJ não dá pra dedup → skip

    razao = normalize_str(row.get("NF_RAZAOSOCIAL_EMITENTE"))
    contact_type = derive_contact_type(cnpj)

    # Schema contacts UltimatePOS: varchar tamanhos justos — truncar pra evitar overflow.
    # cpf_cnpj(20), name/email/landline(191), mobile(191) NOT NULL.
    data = {
        "business_id": business_id,
        "type": "supplier",  # fornecedor — pode promover pra both se já existir como customer
        "contact_type": contact_type,
        "name": normalize_str(razao, 191) or f"Legacy Fornecedor CNPJ {cnpj[:6]}...",
        "supplier_business_name": normalize_str(razao, 191),
        "cpf_cnpj": cnpj[:20],
        "tax_number": cnpj[:20],
        "country": "BRASIL",
        # mobile é NOT NULL no schema UltimatePOS — fallback '' se vazio
        "mobile": "",
        "contact_status": "active",
        "legacy_id": cnpj[:32],  # CNPJ é chave natural dedup
    }

    # Audit metadata
    pii_safe = {}
    for k in COLS_FORNECEDOR_NFE:
        v = row.get(k)
        if k in PII_FIELDS:
            pii_safe[k] = redact_pii(v)
        else:
            pii_safe[k] = (str(v).strip() if v is not None else None)

    audit = {
        "legacy_source": LEGACY_SOURCE,
        "legacy_id_redacted": redact_cnpj_prefix(cnpj),
        "razaosocial_redacted": redact_pii(razao),
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
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    parser.add_argument(
        "--delta-since-last-sync",
        action="store_true",
        help="Daemon dual-sync — lê apenas rows com DT_ALTERACAO > sync_checkpoint.last_sync_at",
    )
    parser.add_argument(
        "--sync-type",
        default=SYNC_TYPE_DEFAULT,
        help=f"Identificador sync_checkpoint (default '{SYNC_TYPE_DEFAULT}')",
    )
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm explicito (seguranca)", file=sys.stderr)
        return 2

    print(f"== Importer Contacts FORNECEDORES (extraidos NFe entrada) v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")
    print(f"  sync_type        : {args.sync_type}")

    stats = {
        "nfe_distinct_lidos": 0,
        "skipped_no_cnpj": 0,
        "inserts": 0,                       # type=supplier (NÃO existia)
        "updates_supplier_existed": 0,      # idempotência — já era supplier/both
        "updates_promoted_to_both": 0,      # tinha type=customer → vira both
        "skipped_cross_business_guard": 0,  # cinto-suspensório SQL barrou
        "errors": 0,
    }
    dry_run_lines = [
        f"-- Generated by import-contacts-from-nfe v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business={args.target_business}  alias={args.alias}",
        "",
        "-- Dedup: SELECT DISTINCT por NF_CNPJCPF_EMITENTE (normalizado digits-only).",
        "-- Idempotência: SELECT por (business_id, legacy_id=CNPJ) → UPDATE OU INSERT.",
        "-- Type promotion: se contact existe com type=customer → vira both (cliente+fornecedor).",
        "-- Cinto-suspensório SQL: UPDATE qualificado com AND business_id (ADR 0093).",
        "",
    ]
    audit_records = []
    sample_inserts = []

    # Delta-since-last-sync — lê checkpoint MySQL ANTES de Firebird
    delta_since: datetime | None = None
    delta_active = False
    if args.delta_since_last_sync and args.target in ("local", "prod"):
        if pymysql is None:
            print("[ERRO] pymysql necessário pra --delta-since-last-sync", file=sys.stderr)
            return 3
        sc_con = pymysql.connect(
            host=args.mysql_host, port=args.mysql_port,
            user=args.mysql_user, password=args.mysql_password,
            database=args.mysql_database, charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )
        try:
            delta_since = sc.read_last_sync_at(sc_con, args.target_business, args.sync_type)
            sc.mark_running(sc_con, args.target_business, args.sync_type)
            sc_con.commit()
        finally:
            sc_con.close()
        delta_active = delta_since is not None
        if delta_active:
            print(f"[delta] last_sync_at = {delta_since} — filtrando DT_ALTERACAO > este valor")
        else:
            print("[delta] checkpoint inexistente — FULL SYNC (próxima rodada será delta)")

    print("\n[Firebird] Conectando...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        cols_existentes_nfe = get_existing_cols(fb_con, "NOTA_FISCAL_ENTRADA")
        cols_ausentes = [c for c in COLS_FORNECEDOR_NFE if c not in cols_existentes_nfe]
        print(
            f"[Adapter] NOTA_FISCAL_ENTRADA cols presentes: {len(cols_existentes_nfe)} · "
            f"canônicas pedidas: {len(COLS_FORNECEDOR_NFE)} · ausentes: {len(cols_ausentes)}"
        )
        if cols_ausentes:
            print(f"          Ausentes (NULL): {cols_ausentes}")

        # Delta filter — DT_ALTERACAO ausente em algumas versões → fallback FULL SYNC
        has_dt_alteracao = "DT_ALTERACAO" in cols_existentes_nfe
        if delta_active and not has_dt_alteracao:
            print(
                "[delta WARN] NOTA_FISCAL_ENTRADA sem DT_ALTERACAO — fallback FULL SYNC",
                file=sys.stderr,
            )
            delta_active = False

        # WHERE pieces
        where_parts = [
            "NF_CNPJCPF_EMITENTE IS NOT NULL",
            "TRIM(NF_CNPJCPF_EMITENTE) <> ''",
            "NF_RAZAOSOCIAL_EMITENTE IS NOT NULL",
            "TRIM(NF_RAZAOSOCIAL_EMITENTE) <> ''",
        ]
        params: list = []
        if "ATIVO" in cols_existentes_nfe:
            where_parts.append("ATIVO = 'S'")
        if delta_active and delta_since is not None:
            where_parts.append("DT_ALTERACAO > ?")
            params.append(sc.format_firebird_timestamp(delta_since))
        where_clause = "WHERE " + " AND ".join(where_parts)

        # Cols pra SELECT (NF_CNPJCPF_EMITENTE + NF_RAZAOSOCIAL_EMITENTE + ATIVO se presente)
        select_cols = ["NF_CNPJCPF_EMITENTE", "NF_RAZAOSOCIAL_EMITENTE"]
        if "ATIVO" in cols_existentes_nfe:
            select_cols.append("ATIVO")

        sql = f"""
            SELECT DISTINCT {', '.join(select_cols)}
            FROM NOTA_FISCAL_ENTRADA
            {where_clause}
            ORDER BY NF_CNPJCPF_EMITENTE, NF_RAZAOSOCIAL_EMITENTE
        """
        print(f"\n[Query DISTINCT NOTA_FISCAL_ENTRADA] {sql.strip()[:200]}...")

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
                stats["nfe_distinct_lidos"] += 1
                row = dict(zip(col_names, row_tuple))
                result = map_nfe_to_supplier_contact(row, args.target_business)
                if result is None:
                    stats["skipped_no_cnpj"] += 1
                    continue
                data, audit = result
                audit_records.append(audit)

                if args.target == "dry-run":
                    s = emit_insert_sql(data)
                    if len(sample_inserts) < 5:
                        sample_inserts.append(s)
                    dry_run_lines.append(
                        f"-- fornecedor CNPJ={redact_cnpj_prefix(data['legacy_id'])} "
                        f"razao=[REDACTED]"
                    )
                    dry_run_lines.append(s)
                    dry_run_lines.append("")
                    stats["inserts"] += 1
                else:
                    assert con is not None
                    data_filtered = {k: v for k, v in data.items() if v is not None}
                    with con.cursor() as wcur:
                        # Lookup existente por (business_id, legacy_id) — cinto-suspensório
                        # já no SELECT (filtro business_id explícito previne cross-tenant).
                        wcur.execute(
                            "SELECT id, type FROM contacts "
                            "WHERE business_id=%s AND legacy_id=%s LIMIT 1",
                            (args.target_business, data["legacy_id"]),
                        )
                        existing = wcur.fetchone()

                        if existing:
                            existing_id = int(existing["id"])
                            existing_type = (existing["type"] or "").lower()

                            if existing_type == "customer":
                                # Cliente que também é fornecedor — promove pra both.
                                # Cinto-suspensório SQL Tier 0 (ADR 0093 · lição ROTA LIVRE):
                                # UPDATE qualificado com AND business_id=%s previne
                                # contamination cross-tenant mesmo se id colidir.
                                wcur.execute(
                                    "UPDATE contacts "
                                    "SET type='both', "
                                    "    supplier_business_name=COALESCE(supplier_business_name, %s), "
                                    "    updated_at=NOW() "
                                    "WHERE id=%s AND business_id=%s",
                                    (
                                        data_filtered.get("supplier_business_name"),
                                        existing_id,
                                        args.target_business,
                                    ),
                                )
                                if wcur.rowcount == 0:
                                    # Defesa final: UPDATE não pegou (id pertence outro biz).
                                    stats["skipped_cross_business_guard"] += 1
                                    if stats["skipped_cross_business_guard"] <= 3:
                                        print(
                                            f"  BLOCKED cross-business: contact id={existing_id} "
                                            f"NÃO está em biz={args.target_business}",
                                            file=sys.stderr,
                                        )
                                    continue
                                stats["updates_promoted_to_both"] += 1

                            elif existing_type in ("supplier", "both"):
                                # Já é fornecedor — no-op idempotente
                                stats["updates_supplier_existed"] += 1

                            else:
                                # Type estranho (lead/prospect/etc) — não mexe sem ADR
                                stats["updates_supplier_existed"] += 1

                        else:
                            # INSERT novo fornecedor
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
                # sync_checkpoint mark_success
                if args.delta_since_last_sync:
                    try:
                        sc.mark_success(
                            con,
                            args.target_business,
                            args.sync_type,
                            rows_processed=stats["inserts"]
                            + stats["updates_promoted_to_both"]
                            + stats["updates_supplier_existed"],
                        )
                        con.commit()
                    except Exception as e:
                        print(f"[sync_checkpoint] WARN mark_success falhou: {e!r}", file=sys.stderr)
        except Exception as e:
            if con:
                con.rollback()
                print("\n[ERRO] Rollback MySQL", file=sys.stderr)
                # sync_checkpoint mark_failed
                if args.delta_since_last_sync:
                    try:
                        sc.mark_failed(con, args.target_business, args.sync_type, error_msg=repr(e))
                        con.commit()
                    except Exception as e2:
                        print(f"[sync_checkpoint] WARN mark_failed falhou: {e2!r}", file=sys.stderr)
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
        sql_path = out / f"dry-run-contacts-from-nfe-{biz_suffix}-{ts}.sql"
        sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
        print(f"\n[SQL preview] {sql_path}")

    audit_path = out / f"audit-contacts-from-nfe-{biz_suffix}-{ts}.json"
    audit_summary = {
        "importer_version": IMPORTER_VERSION,
        "alias": args.alias,
        "business_id": args.target_business,
        "target": args.target,
        "sync_type": args.sync_type,
        "delta_active": delta_active,
        "delta_since": str(delta_since) if delta_since else None,
        "adapter": {
            "cols_canonicas_pedidas": len(COLS_FORNECEDOR_NFE),
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
    print(f"  NFE distinct lidos        : {stats['nfe_distinct_lidos']}")
    print(f"  Skipped (sem CNPJ valido) : {stats['skipped_no_cnpj']}")
    print(f"  Contacts INSERT supplier  : {stats['inserts']}")
    print(f"  Contacts UPDATE promoted  : {stats['updates_promoted_to_both']}  (customer → both)")
    print(f"  Contacts no-op existed    : {stats['updates_supplier_existed']}  (já supplier/both)")
    print(f"  Skipped cross-business    : {stats['skipped_cross_business_guard']}")
    print(f"  Errors                    : {stats['errors']}")
    if stats["skipped_cross_business_guard"] > 0:
        print(
            f"\n[ALERT] {stats['skipped_cross_business_guard']} tentativas cross-business "
            f"barradas pelo cinto-suspensório. Investigar.",
            file=sys.stderr,
        )
    print(f"[OK] Contacts fornecedores (NFe) concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
