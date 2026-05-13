"""
Fase 4 — Importer VENDA Delphi WR Comercial → transactions UltimatePOS.

Lê tabela VENDA do Firebird de um cliente OfficeImpresso e popula `transactions`
em business alvo do oimpresso (Laravel/MySQL). Pareada com Fase 3 (vehicles)
via FK lógica `vehicles.legacy_id` ← `VENDA.PLACA` (FK int pra
`EQUIPAMENTO_VEICULO.CODIGO`).

UPSERT idempotente via (business_id, ref_no) — `transactions.ref_no` preserva
`VENDA.CODIGO` Delphi (formato "12345-1" — sequência interna). Re-rodar é
no-op pra rows existentes, update pra mudanças, insert pra novas.

Adapter por versão Firebird (v1404 Martinho → v1474 Zoom canônica):
   - Lê cols presentes via `SELECT FIRST 1 *` em runtime
   - Cols ausentes vs canônica viram NULL no INSERT (não quebra)
   - Schema v1404 Martinho confirmado 2026-05-13: drift 0% vs canônica (todas 49 cols presentes)

Multi-tenant Tier 0 (ADR 0093): impõe `business_id` em todo INSERT/UPDATE.

PII redaction (audit JSON): CPFCNPJ, telefones, emails → [REDACTED].

Uso:
    # Dry-run (gera SQL preview + audit, não toca DB)
    python import-vendas.py --alias MartinhoServidor --target-business 164

    # Filtrar por período (recomendado pra clientes grandes — Martinho tem 46k vendas)
    python import-vendas.py --alias MartinhoServidor --target-business 164 \\
        --start-date 2025-05-13 --end-date 2026-05-13

    # Local Laragon (Herd dev)
    python import-vendas.py --alias MartinhoServidor --target-business 164 \\
        --start-date 2025-05-13 --end-date 2026-05-13 --target local

    # Hostinger prod (perigoso — exige --confirm)
    python import-vendas.py --alias MartinhoServidor --target-business 164 \\
        --start-date 2025-05-13 --end-date 2026-05-13 --target prod --confirm

Refs:
    - memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md §9.1-9.5
    - ADR 0093 — Multi-tenant Tier 0
    - ADR 0143 — FSM Pipeline LIVE (transactions.process_id/current_stage_id nullable OK)
    - .claude/agents/migracao-firebird-versoes.md (este agent)
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

# Colunas canônicas pra leitura (mapping v1474). Cols ausentes em versões antigas
# viram NULL automaticamente via COALESCE in-query.
COLS_CANONICAS = [
    "CODIGO",                        # ref_no (string "12345-1")
    "DT_EMISSAO",                    # transaction_date
    "DT_ALTERACAO",                  # updated_at hint
    "DT_FATURAMENTO",                # invoiced_at
    "FATURAMENTO_DT_ENVIO",          # invoice_sent_at
    "DT_COMPETENCIA",                # competence_date
    "PROJETO_DT_FIM",                # due_date
    "NF_DT_EMISSAO",                 # JOIN nfe_emissoes (skip aqui)
    "RAZAOSOCIAL",                   # contact name (denormalizado)
    "PESSOA_RESPONSAVEL_CODIGO",     # contact.legacy_id
    "TOTAL",                         # final_total
    "VDESC",                         # discount_amount
    "VOUTRO",                        # NOT in core; vai pra metadata
    "NF_VFRETE",                     # shipping_charges
    "VENDA_TIPO",                    # type (sell/purchase) derivado
    "STATUS",                        # status enum (ATIVO/INATIVO)
    "SITUACAO",                      # production_status — metadata
    "SITUACAOFINANCEIRA",            # payment_status derivado
    "IS_VENDA", "IS_NOTAFISCAL", "IS_ORCAMENTO", "IS_PDV",  # sub_type derivado
    "OBSERVACAO",                    # additional_notes
    "NOTAFISCAL",                    # invoice_no
    "CONDICAOPAGTO",                 # pay_term_type metadata
    "PLACA",                         # FK pra EQUIPAMENTO_VEICULO.CODIGO → vehicle_id (via service_order)
    "PEDIDO_COMPRA",                 # purchase_order
    "PESSOA_FUNCIONARIO_CODIGO",     # salesman legacy_id
    "PESSOA_REPRESENTANTE_CODIGO",   # representative legacy_id
    "TELEFONE", "CONTATO",           # contact info (denormalizado denphy)
    "CPF_CNPJ_RESPONSAVEL",          # PII — redacted audit (v1474 canônica nomenclatura)
    "RESPONSAVEL_CNPJCPF",           # PII — redacted audit (v1404 Martinho nomenclatura, alias)
]

# PII fields a redactar em audit JSON
PII_FIELDS = {"TELEFONE", "CPF_CNPJ_RESPONSAVEL", "MOTORISTA_DOCUMENTO", "EMAIL"}


def get_existing_cols(con, table: str) -> set[str]:
    """Detecta cols realmente presentes na versão (adapter)."""
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def build_adapted_select(cols_presentes: set[str], cols_canonicas: list[str]) -> str:
    """Gera SELECT com COALESCE(col, NULL) AS col pra cols ausentes."""
    parts = []
    for c in cols_canonicas:
        if c in cols_presentes:
            parts.append(f"P.{c}")
        else:
            parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
    return ", ".join(parts)


def redact_pii(value) -> str | None:
    """[REDACTED] para PII em audit JSON."""
    if value is None or value == "":
        return None
    return "[REDACTED]"


def normalize_str(raw) -> str | None:
    if raw is None:
        return None
    s = str(raw).strip()
    return s or None


def normalize_decimal(raw) -> float | None:
    if raw is None or raw == "":
        return None
    try:
        return float(raw)
    except (ValueError, TypeError):
        return None


def derive_type(venda_row: dict) -> str:
    """VENDA_TIPO Delphi → transactions.type ('sell' / 'purchase')."""
    # Delphi VENDA é sempre venda (transactions de compra ficam em COMPRA).
    # Se for orçamento, vai como sell mas com status='draft'.
    return "sell"


def derive_status(venda_row: dict) -> str:
    """VENDA.STATUS Delphi → transactions.status enum."""
    status = (venda_row.get("STATUS") or "").upper()
    is_orcamento = (venda_row.get("IS_ORCAMENTO") or "").upper() == "S"
    if is_orcamento:
        return "draft"
    if "INATIVO" in status or "CANCEL" in status:
        return "draft"  # ATIVO → 'final'; INATIVO/cancelado → 'draft' (preserva audit)
    return "final"


def derive_payment_status(venda_row: dict) -> str:
    """VENDA.SITUACAOFINANCEIRA Delphi → transactions.payment_status."""
    sf = (venda_row.get("SITUACAOFINANCEIRA") or "").upper()
    if "QUIT" in sf or "PAGO" in sf or "RECEB" in sf:
        return "paid"
    return "due"


def derive_subtype(venda_row: dict) -> str | None:
    """IS_PDV / IS_VENDA / IS_NOTAFISCAL / IS_ORCAMENTO → sub_type str."""
    if (venda_row.get("IS_PDV") or "").upper() == "S":
        return "pos"
    if (venda_row.get("IS_NOTAFISCAL") or "").upper() == "S":
        return "invoice"
    if (venda_row.get("IS_ORCAMENTO") or "").upper() == "S":
        return "quotation"
    return None


def map_venda_to_transaction(
    venda: dict,
    business_id: int,
    vehicle_lookup: dict[str, int],
    contact_lookup: dict[str, int],
) -> tuple[dict, dict]:
    """VENDA Delphi → linha em `transactions`.

    vehicle_lookup: {EQUIPAMENTO_VEICULO.CODIGO (str) → vehicles.id (int)}
                    pra resolver `service_orders.vehicle_id` em fase futura.
                    Por ora marcamos em metadata.

    contact_lookup: {CNPJ_normalized (str) → contacts.id (int)}
                    Resolve transactions.contact_id (NOT NULL FK) via
                    CNPJ extraído de VENDA.RESPONSAVEL_CNPJCPF.
                    Populado em prep step pelo import-contacts-from-venda.py.

    Retorna (data, audit).
    """
    legacy_id = str(venda["CODIGO"]).strip()
    placa_fk = normalize_str(venda.get("PLACA"))
    has_vehicle = placa_fk is not None and placa_fk in vehicle_lookup
    vehicle_id = vehicle_lookup.get(placa_fk) if has_vehicle else None

    # Resolve contact_id via CNPJ normalizado (RESPONSAVEL_CNPJCPF)
    cnpj_raw = venda.get("CPF_CNPJ_RESPONSAVEL") or venda.get("RESPONSAVEL_CNPJCPF")
    cnpj_norm = None
    if cnpj_raw:
        digits = re.sub(r"\D", "", str(cnpj_raw))
        if digits and len(digits) in (11, 14):
            cnpj_norm = digits
    contact_id = contact_lookup.get(cnpj_norm) if cnpj_norm else None

    razao = normalize_str(venda.get("RAZAOSOCIAL")) or f"Legacy {legacy_id}"

    data = {
        "business_id": business_id,
        "type": derive_type(venda),
        "status": derive_status(venda),
        "payment_status": derive_payment_status(venda),
        # contact_id é obrigatório (NOT NULL FK). Resolvido via lookup CNPJ
        # (import-contacts-from-venda.py popula contacts.legacy_id=CNPJ ANTES).
        # Dry-run: pode ficar None pra audit. Prod: skip se None.
        "contact_id": contact_id,
        "ref_no": legacy_id,
        "invoice_no": normalize_str(venda.get("NOTAFISCAL")),
        "transaction_date": venda.get("DT_EMISSAO"),
        "invoiced_at": venda.get("DT_FATURAMENTO"),
        "invoice_sent_at": venda.get("FATURAMENTO_DT_ENVIO"),
        "competence_date": venda.get("DT_COMPETENCIA"),
        "due_date": venda.get("PROJETO_DT_FIM"),
        "final_total": normalize_decimal(venda.get("TOTAL")) or 0,
        "total_before_tax": normalize_decimal(venda.get("TOTAL")) or 0,
        "discount_amount": normalize_decimal(venda.get("VDESC")) or 0,
        "shipping_charges": normalize_decimal(venda.get("NF_VFRETE")) or 0,
        "additional_notes": normalize_str(venda.get("OBSERVACAO")),
        "sub_type": derive_subtype(venda),
        "created_by": 1,  # Wagner system user — sobrescrito via --created-by
        # NOT NULL sem default no schema UltimatePOS — fallbacks seguros:
        "essentials_duration": 0,  # decimal NOT NULL — campo HR não-usado em Sells
    }
    # transaction_date NOT NULL — fallback se Delphi tiver null/zero date 1899
    td = data["transaction_date"]
    if td is None or (isinstance(td, str) and td.startswith("1899")) or (hasattr(td, "year") and td.year < 1990):
        data["transaction_date"] = datetime.utcnow()

    # Audit metadata
    pii_safe_delphi = {}
    for k in COLS_CANONICAS:
        v = venda.get(k)
        if k in PII_FIELDS:
            pii_safe_delphi[k] = redact_pii(v)
        else:
            pii_safe_delphi[k] = (str(v).strip() if v is not None else None)

    audit = {
        "legacy_source": LEGACY_SOURCE,
        "legacy_id": legacy_id,
        "has_vehicle": has_vehicle,
        "vehicle_id_resolved": vehicle_id,
        "placa_fk_delphi": placa_fk,
        "venda_tipo_delphi": normalize_str(venda.get("VENDA_TIPO")),
        "situacao_delphi": normalize_str(venda.get("SITUACAO")),
        "raw_delphi_pii_safe": pii_safe_delphi,
        "razaosocial_denormalized": razao,
        "cnpj_normalized_redacted": ("[REDACTED-" + cnpj_norm[:4] + "...]") if cnpj_norm else None,
        "contact_id_resolved": contact_id,
        "responsavel_codigo_legacy": normalize_str(venda.get("PESSOA_RESPONSAVEL_CODIGO")),
        "imported_at_iso": datetime.utcnow().isoformat() + "Z",
        "importer_version": IMPORTER_VERSION,
    }

    return data, audit


def sql_value(v) -> str:
    """Escape Python value pra literal SQL (apenas dry-run preview)."""
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, datetime):
        return "'" + v.strftime("%Y-%m-%d %H:%M:%S") + "'"
    s = str(v).replace("'", "''")
    return "'" + s + "'"


def emit_insert_sql(data: dict) -> str:
    cols = [c for c, v in data.items() if v is not None]
    vals = ", ".join(sql_value(data[c]) for c in cols)
    return (
        f"INSERT INTO transactions ({', '.join(cols)}, created_at, updated_at) "
        f"VALUES ({vals}, NOW(), NOW());"
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True, help="Alias Firebird HKCU (ex MartinhoServidor)")
    parser.add_argument("--target-business", type=int, required=True, help="business_id alvo")
    parser.add_argument(
        "--target",
        choices=["dry-run", "local", "prod"],
        default="dry-run",
    )
    parser.add_argument("--start-date", help="Filtro DT_EMISSAO >= YYYY-MM-DD")
    parser.add_argument("--end-date", help="Filtro DT_EMISSAO <= YYYY-MM-DD")
    parser.add_argument("--limit", type=int, default=0, help="Limite rows (0 = sem limite)")
    parser.add_argument("--batch-size", type=int, default=1000, help="Commits per N rows (local/prod)")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true", help="Obrigatório pra --target prod")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm explícito (segurança)", file=sys.stderr)
        return 2

    print(f"== Importer Vendas v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird       : {args.alias}")
    print(f"  business_id alvo     : {args.target_business}")
    print(f"  Target MySQL         : {args.target}")
    if args.start_date or args.end_date:
        print(f"  Filtro DT_EMISSAO    : [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]")
    if args.limit:
        print(f"  Limit rows           : {args.limit}")

    stats = {
        "lidos": 0,
        "inserts": 0,
        "updates": 0,
        "skipped_no_contact": 0,
        "com_contact_resolved": 0,
        "sem_contact_resolved": 0,
        "errors": 0,
        "com_vehicle": 0,
        "sem_vehicle": 0,
        "placa_orfa": 0,
    }
    dry_run_lines: list[str] = [
        f"-- Generated by import-vendas v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business={args.target_business}  alias={args.alias}",
        f"-- Filter: DT_EMISSAO [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]",
        "",
        "-- Idempotência: real importer faz SELECT por (business_id, ref_no) +",
        "-- UPDATE OU INSERT manual (transactions schema usa index, não unique).",
        "-- Este preview emite INSERT puro pra auditoria.",
        "",
        "-- contact_id resolvido via lookup CNPJ (RESPONSAVEL_CNPJCPF normalizado).",
        "-- Pré-req: rodar import-contacts-from-venda.py ANTES (popula contacts.legacy_id=CNPJ).",
        "-- Dry-run: pode mostrar contact_id NULL se nenhum contact pré-criado em biz.",
        "",
    ]
    audit_records: list[dict] = []
    sample_inserts: list[str] = []

    print("\n[Firebird] Conectando...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        # Adapter: cols presentes nesta versão
        cols_existentes_venda = get_existing_cols(fb_con, "VENDA")
        cols_ausentes = [c for c in COLS_CANONICAS if c not in cols_existentes_venda]
        print(f"[Adapter] VENDA cols presentes: {len(cols_existentes_venda)} · canônicas pedidas: {len(COLS_CANONICAS)} · ausentes: {len(cols_ausentes)}")
        if cols_ausentes:
            print(f"          Ausentes (viram NULL): {cols_ausentes}")

        # Carrega vehicle_lookup + contact_lookup do MySQL alvo.
        # Pra dry-run, vehicle_lookup é simulado via EQUIPAMENTO_VEICULO Firebird (sentinel),
        # contact_lookup também é simulado via DISTINCT CNPJ de VENDA.
        vehicle_lookup: dict[str, int] = {}
        contact_lookup: dict[str, int] = {}
        if args.target in ("local", "prod"):
            if pymysql is None:
                print("[ERRO] pymysql não instalado: pip install pymysql", file=sys.stderr)
                return 3
            tmp_con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
            )
            try:
                with tmp_con.cursor() as cur:
                    cur.execute(
                        "SELECT id, legacy_id FROM vehicles WHERE business_id=%s AND legacy_id IS NOT NULL",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        vehicle_lookup[str(r["legacy_id"])] = int(r["id"])
                    cur.execute(
                        "SELECT id, legacy_id FROM contacts WHERE business_id=%s AND legacy_id IS NOT NULL",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        contact_lookup[str(r["legacy_id"])] = int(r["id"])
            finally:
                tmp_con.close()
            print(f"[Vehicle lookup] {len(vehicle_lookup)} vehicles biz={args.target_business}")
            print(f"[Contact lookup] {len(contact_lookup)} contacts biz={args.target_business} (CNPJ → id)")
            if not contact_lookup:
                print("[WARN] Nenhum contact com legacy_id em biz alvo — rode import-contacts-from-venda.py ANTES.")
        else:
            # Dry-run: simula vehicle_lookup via EQUIPAMENTO_VEICULO + contact_lookup via DISTINCT CNPJ
            cur = fb_con.cursor()
            cur.execute("SELECT CODIGO FROM EQUIPAMENTO_VEICULO ORDER BY CODIGO")
            for r in cur.fetchall():
                vehicle_lookup[str(r[0]).strip()] = -1  # sentinel
            cur.execute(
                "SELECT DISTINCT RESPONSAVEL_CNPJCPF FROM VENDA WHERE RESPONSAVEL_CNPJCPF IS NOT NULL"
            )
            for r in cur.fetchall():
                if r[0]:
                    digits = re.sub(r"\D", "", str(r[0]))
                    if digits and len(digits) in (11, 14):
                        contact_lookup[digits] = -1  # sentinel
            cur.close()
            print(f"[Vehicle lookup] dry-run · {len(vehicle_lookup)} legacy_ids EQUIPAMENTO_VEICULO (sentinel)")
            print(f"[Contact lookup] dry-run · {len(contact_lookup)} CNPJ distinct VENDA (sentinel)")

        # Query principal VENDA com filtros
        where_parts = []
        params: list = []
        if args.start_date:
            where_parts.append("P.DT_EMISSAO >= ?")
            params.append(args.start_date)
        if args.end_date:
            where_parts.append("P.DT_EMISSAO <= ?")
            params.append(args.end_date + " 23:59:59")
        where_clause = ("WHERE " + " AND ".join(where_parts)) if where_parts else ""

        select_clause = build_adapted_select(cols_existentes_venda, COLS_CANONICAS)
        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        sql = f"""
            SELECT {first_clause} {select_clause}
            FROM VENDA P
            {where_clause}
            ORDER BY P.CODIGO
        """
        print(f"\n[Query VENDA] {sql.strip()[:200]}...")
        cur = fb_con.cursor()
        cur.execute(sql, tuple(params))
        col_names = [d[0] for d in cur.description]

        # MySQL writer
        con = None
        if args.target in ("local", "prod"):
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )

        try:
            batch_count = 0
            for row in cur:
                stats["lidos"] += 1
                venda = dict(zip(col_names, row))
                data, audit = map_venda_to_transaction(
                    venda, args.target_business, vehicle_lookup, contact_lookup
                )
                audit_records.append(audit)

                if audit["has_vehicle"]:
                    stats["com_vehicle"] += 1
                elif audit["placa_fk_delphi"]:
                    stats["placa_orfa"] += 1
                else:
                    stats["sem_vehicle"] += 1

                if audit["contact_id_resolved"] is not None:
                    stats["com_contact_resolved"] += 1
                else:
                    stats["sem_contact_resolved"] += 1

                if args.target == "dry-run":
                    sql_str = emit_insert_sql(data)
                    if len(sample_inserts) < 5:
                        sample_inserts.append(sql_str)
                    dry_run_lines.append(
                        f"-- VENDA {audit['legacy_id']} · placa={audit['placa_fk_delphi'] or 'SEM_PLACA'} · veh={'OK' if audit['has_vehicle'] else 'NULL'}"
                    )
                    dry_run_lines.append(sql_str)
                    dry_run_lines.append("")
                    stats["inserts"] += 1
                else:
                    # contact_id é NOT NULL FK em transactions — sem Fase 2 (empresas)
                    # pulamos. Wagner roda Fase 2 antes em prod.
                    if data["contact_id"] is None:
                        stats["skipped_no_contact"] += 1
                        if stats["skipped_no_contact"] <= 3:
                            print(f"  SKIP venda {audit['legacy_id']} — contact_id NULL (Fase 2 pendente)")
                        continue

                    assert con is not None
                    data_filtered = {k: v for k, v in data.items() if v is not None}
                    with con.cursor() as wcur:
                        wcur.execute(
                            "SELECT id FROM transactions WHERE business_id=%s AND ref_no=%s LIMIT 1",
                            (args.target_business, data["ref_no"]),
                        )
                        existing = wcur.fetchone()
                        if existing:
                            update_fields = {
                                k: v for k, v in data_filtered.items()
                                if k not in ("business_id", "type", "ref_no", "created_by")
                            }
                            set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                            wcur.execute(
                                f"UPDATE transactions SET {set_clause}, updated_at=NOW() WHERE id=%s",
                                (*update_fields.values(), existing["id"]),
                            )
                            stats["updates"] += 1
                        else:
                            cols = list(data_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO transactions ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(data_filtered.values()),
                            )
                            stats["inserts"] += 1
                    batch_count += 1
                    if batch_count >= args.batch_size:
                        con.commit()
                        print(f"  [batch] commited {batch_count} rows · total lidos: {stats['lidos']}")
                        batch_count = 0

                if stats["lidos"] % 1000 == 0:
                    print(f"  ... lidos {stats['lidos']}")

            if con and batch_count > 0:
                con.commit()
                print(f"  [batch final] commited {batch_count} rows")

        except Exception as e:
            if con:
                con.rollback()
                print("\n[ERRO] Rollback MySQL", file=sys.stderr)
            stats["errors"] += 1
            print(f"Exceção: {e!r}", file=sys.stderr)
            raise
        finally:
            cur.close()
            if con:
                con.close()

    # Output dry-run SQL
    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    biz_suffix = f"biz{args.target_business}"

    if args.target == "dry-run":
        sql_path = out / f"dry-run-vendas-{biz_suffix}-{ts}.sql"
        sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
        print(f"\n[SQL preview] {sql_path}")

    # Audit JSON (sempre)
    audit_path = out / f"audit-vendas-{biz_suffix}-{ts}.json"
    # Limita audit a 500 records pra não explodir arquivo
    records_sample = audit_records[:500] if len(audit_records) > 500 else audit_records
    audit_summary = {
        "importer_version": IMPORTER_VERSION,
        "alias": args.alias,
        "business_id": args.target_business,
        "target": args.target,
        "filter": {"start_date": args.start_date, "end_date": args.end_date, "limit": args.limit},
        "adapter": {
            "cols_canonicas_pedidas": len(COLS_CANONICAS),
            "cols_ausentes_v_canonica": cols_ausentes,
        },
        "stats": stats,
        "sample_inserts": sample_inserts,
        "records_total": len(audit_records),
        "records_sample_first_500": records_sample,
    }
    audit_path.write_text(
        json.dumps(audit_summary, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  VENDA lidos       : {stats['lidos']}")
    print(f"  com vehicle JOIN  : {stats['com_vehicle']}")
    print(f"  sem placa (NULL)  : {stats['sem_vehicle']}")
    print(f"  placa órfã        : {stats['placa_orfa']}")
    print(f"  Inserts dry/local : {stats['inserts']}")
    print(f"  Updates           : {stats['updates']}")
    print(f"  Skipped(no_contact): {stats['skipped_no_contact']}")
    print(f"  Errors            : {stats['errors']}")
    print(f"\nFinalizado (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
