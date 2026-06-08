"""
Fase 7 — Importer NOTA_FISCAL_ENTRADA + NF_ENTRADA_PRODUTOS Delphi WR Comercial
→ transactions(type='purchase') + purchase_lines UltimatePOS.

Lê NOTAS DE ENTRADA do Firebird (Martinho v1404: 15.617 notas + N itens cada)
e popula `transactions` (cabeçalho, type='purchase') + `purchase_lines` (itens)
em business alvo do oimpresso (Laravel/MySQL).

UPSERT idempotente via (business_id, ref_no=NOTA_FISCAL_ENTRADA.CODIGO).
purchase_lines re-criadas (DELETE + INSERT) por transaction_id pra
preservar atomicidade quando NF tem itens corrigidos legacy.

Lookups necessários (resolvidos via MySQL alvo, biz=N):
   - contact_id   : via contacts.cpf_cnpj = NF_CNPJCPF_EMITENTE normalizado
                    fallback: contacts.name = NF_RAZAOSOCIAL_EMITENTE (heurístico)
                    skip: se nenhum match → skip + log (Wagner roda import-contacts-fornecedores antes)
   - product_id   : via products.officeimpresso_codigo = CODPRODUTO (do NF_ENTRADA_PRODUTOS)
                    skip linha: se sem match → log warn
   - variation_id : via variations.product_id = ^ AND product_variation_id = ^

Multi-tenant Tier 0 (ADR 0093): impõe `business_id` em todos INSERT/UPDATE.

PII redaction (audit): NF_CNPJCPF_EMITENTE → [REDACTED-XXXX...] (4 primeiros digitos)
                       NF_CHAVE → [REDACTED] (chave NFe 44 dígitos preserva ref no metadata)
                       RAZAOSOCIAL → [REDACTED]

Adapter por versão Firebird (v1404 Martinho → v1474 Zoom canônica):
   - Lê cols presentes via `SELECT FIRST 1 *` em runtime
   - Cols ausentes vs canônica viram NULL (não quebra)

Uso:
    # Dry-run (gera SQL preview + audit, não toca DB)
    python import-compras.py --alias MartinhoServidor --target-business 164

    # Filtrar por período (recomendado pra clientes grandes)
    python import-compras.py --alias MartinhoServidor --target-business 164 \\
        --start-date 2024-01-01 --end-date 2026-05-14

    # Local Laragon (Herd dev)
    python import-compras.py --alias MartinhoServidor --target-business 164 --target local

    # Hostinger prod (perigoso — exige --confirm)
    python import-compras.py --alias MartinhoServidor --target-business 164 --target prod --confirm

Refs:
    - memory/reference/migracao-officeimpresso-pattern.md §Fase 7
    - ADR 0093 — Multi-tenant Tier 0
    - import-produtos.py (pré-req — popula products.officeimpresso_codigo)
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime, date
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
from lib import sync_checkpoint as sc  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore

IMPORTER_VERSION = "0.2.0"  # bump: --delta-since-last-sync
LEGACY_SOURCE = "wr-comercial-delphi"
SYNC_TYPE_DEFAULT = "compras"

# Cols canônicas NOTA_FISCAL_ENTRADA (23 cols Martinho v1404).
NFE_COLS = [
    "CODIGO",                    # → transactions.ref_no (chave UPSERT)
    "NF_NUMERO",                 # → transactions.invoice_no
    "NF_CHAVE",                  # PII redact → metadata
    "NF_CNPJCPF_EMITENTE",       # PII redact + lookup contact
    "NF_RAZAOSOCIAL_EMITENTE",   # PII redact + fallback contact lookup
    "NF_DT_EMISSAO",             # → transactions.transaction_date
    "DT_RECEBIMENTO",            # → transactions.created_at hint
    "NF_TOTAL",                  # → transactions.final_total
    "NF_VICMS", "NF_VIPI",       # impostos
    "NF_VFRETE",                 # → transactions.shipping_charges
    "NF_VDESCONTO",              # → transactions.discount_amount
    "NF_SITUACAO", "SITUACAO",   # status enum (ATIVO/INATIVO/etc)
    "ATIVO",                     # filtro
    "OBSERVACAO",                # → transactions.additional_notes
    "DT_ALTERACAO",              # audit
    "CODEMPRESA",                # audit
    "OIMPRESSO_CODIGO",          # metadata bridge
    "OIMPRESSO_DT_ALTERACAO",    # metadata bridge
    "PESSOA_FUNCIONARIO_CODIGO", # audit (usuário Delphi)
]

# Cols canônicas NF_ENTRADA_PRODUTOS (330 cols Martinho v1404 — pegamos só relevantes).
NFEP_COLS = [
    "CODIGO",                    # PK
    "CODNF_ENTRADA",             # FK → NOTA_FISCAL_ENTRADA.CODIGO
    "CODPRODUTO",                # FK → PRODUTO.CODIGO (lookup officeimpresso_codigo)
    "QUANT",                     # → purchase_lines.quantity
    "VALOR_COMPRA",              # → purchase_lines.purchase_price
    "TOTAL_COMPRA",              # → calc auxiliar
    "NF_VICMS", "NF_VIPI",       # impostos
    "NF_VDESCONTO",              # → purchase_lines.discount_amount
    "NF_CFOP",                   # metadata
    "DESCRICAO",                 # metadata fallback (caso CODPRODUTO ausente)
    "NF_CSTICMS",                # metadata
]

PII_FIELDS_NFE = {"NF_CNPJCPF_EMITENTE", "NF_CHAVE", "NF_RAZAOSOCIAL_EMITENTE"}


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def build_adapted_select(cols_presentes: set[str], cols_canonicas: list[str], alias: str = "P") -> str:
    parts = []
    for c in cols_canonicas:
        if c in cols_presentes:
            parts.append(f"{alias}.{c}")
        else:
            parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
    return ", ".join(parts)


def normalize_str(raw, max_len=None) -> str | None:
    if raw is None:
        return None
    s = str(raw).strip()
    if not s:
        return None
    if max_len and len(s) > max_len:
        s = s[:max_len]
    return s


def normalize_decimal(raw) -> float:
    if raw is None or raw == "":
        return 0.0
    try:
        return float(raw)
    except (ValueError, TypeError):
        return 0.0


def normalize_cpf_cnpj(raw) -> str | None:
    if not raw:
        return None
    digits = re.sub(r"\D", "", str(raw))
    if digits and len(digits) in (11, 14):
        return digits
    return None


def redact_pii(v):
    if v is None or v == "":
        return None
    return "[REDACTED]"


def redact_cnpj_prefix(cnpj: str | None) -> str | None:
    """CNPJ → [REDACTED-XXXX...] (preserva 4 dígitos pra audit)."""
    if not cnpj:
        return None
    return f"[REDACTED-{cnpj[:4]}...]"


def derive_status(nfe: dict) -> str:
    """NF_SITUACAO / SITUACAO Delphi → transactions.status."""
    situ = (nfe.get("NF_SITUACAO") or nfe.get("SITUACAO") or "").upper()
    if "CANCEL" in situ or "INATIVO" in situ:
        return "draft"
    return "received"  # purchase status canônico UltimatePOS


def derive_payment_status(nfe: dict) -> str:
    """Compras Delphi não trazem payment_status direto — default 'due'.
    Pareamento com FINANCEIRO ficará pra cruzamento posterior (script futuro).
    """
    return "due"


def map_nfe_to_transaction(
    nfe: dict,
    business_id: int,
    contact_lookup_cnpj: dict[str, int],
    contact_lookup_name: dict[str, int],
    created_by: int,
    default_location_id: int,
) -> tuple[dict, dict]:
    """NOTA_FISCAL_ENTRADA Delphi → transactions(type='purchase') row."""
    legacy_id = str(nfe["CODIGO"]).strip()
    cnpj_norm = normalize_cpf_cnpj(nfe.get("NF_CNPJCPF_EMITENTE"))
    razao = normalize_str(nfe.get("NF_RAZAOSOCIAL_EMITENTE"), 255)

    # Lookup contact_id: prio CNPJ, fallback razao social
    contact_id = None
    contact_match_kind = "none"
    if cnpj_norm and cnpj_norm in contact_lookup_cnpj:
        contact_id = contact_lookup_cnpj[cnpj_norm]
        contact_match_kind = "cnpj"
    elif razao:
        razao_key = razao.upper().strip()
        if razao_key in contact_lookup_name:
            contact_id = contact_lookup_name[razao_key]
            contact_match_kind = "name_fallback"

    final_total = normalize_decimal(nfe.get("NF_TOTAL"))
    discount = normalize_decimal(nfe.get("NF_VDESCONTO"))
    frete = normalize_decimal(nfe.get("NF_VFRETE"))

    transaction_date = nfe.get("NF_DT_EMISSAO") or nfe.get("DT_RECEBIMENTO")
    if transaction_date is None or (
        isinstance(transaction_date, str) and transaction_date.startswith("1899")
    ) or (hasattr(transaction_date, "year") and transaction_date.year < 1990):
        transaction_date = datetime.utcnow()

    data = {
        "business_id": business_id,
        "location_id": default_location_id,
        "type": "purchase",
        "status": derive_status(nfe),
        "payment_status": derive_payment_status(nfe),
        "contact_id": contact_id,
        "ref_no": legacy_id,
        "invoice_no": normalize_str(nfe.get("NF_NUMERO"), 191),
        "transaction_date": transaction_date,
        "final_total": final_total,
        "total_before_tax": final_total,
        "discount_amount": discount,
        "shipping_charges": frete,
        "additional_notes": normalize_str(nfe.get("OBSERVACAO"), 1000),
        "created_by": created_by,
        # NOT NULL no schema UltimatePOS — fallbacks seguros
        "essentials_duration": 0,
    }

    audit = {
        "legacy_source": LEGACY_SOURCE,
        "legacy_id": legacy_id,
        "nf_numero": normalize_str(nfe.get("NF_NUMERO")),
        "nf_chave_redacted": redact_pii(nfe.get("NF_CHAVE")),
        "cnpj_emitente_redacted": redact_cnpj_prefix(cnpj_norm),
        "razao_emitente_redacted": redact_pii(razao),
        "contact_id_resolved": contact_id,
        "contact_match_kind": contact_match_kind,
        "final_total": final_total,
        "nf_situacao_delphi": normalize_str(nfe.get("NF_SITUACAO")),
        "situacao_delphi": normalize_str(nfe.get("SITUACAO")),
        "ativo_delphi": normalize_str(nfe.get("ATIVO")),
        "transaction_date_resolved": str(transaction_date) if transaction_date else None,
        "imported_at_iso": datetime.utcnow().isoformat() + "Z",
        "importer_version": IMPORTER_VERSION,
    }
    return data, audit


def map_nfep_to_purchase_line(
    nfep: dict,
    transaction_id: int,
    product_lookup: dict[str, dict],
) -> tuple[dict | None, dict]:
    """NF_ENTRADA_PRODUTOS Delphi → purchase_lines row.

    Retorna (data, audit). data=None se produto não encontrado (skip linha).
    """
    cod_produto = normalize_str(nfep.get("CODPRODUTO"))
    quant = normalize_decimal(nfep.get("QUANT"))
    valor_compra = normalize_decimal(nfep.get("VALOR_COMPRA"))
    item_tax = normalize_decimal(nfep.get("NF_VICMS")) + normalize_decimal(nfep.get("NF_VIPI"))

    audit = {
        "cod_produto_legacy": cod_produto,
        "quant": quant,
        "valor_compra": valor_compra,
        "item_tax": item_tax,
        "descricao_fallback": normalize_str(nfep.get("DESCRICAO"), 100),
    }

    if not cod_produto:
        audit["status"] = "skipped_sem_codproduto"
        return None, audit

    lookup = product_lookup.get(cod_produto)
    if not lookup:
        audit["status"] = "skipped_produto_nao_encontrado"
        return None, audit

    if quant <= 0:
        audit["status"] = "skipped_quant_zero"
        return None, audit

    data = {
        "transaction_id": transaction_id,
        "product_id": lookup["product_id"],
        "variation_id": lookup["variation_id"],
        "quantity": quant,
        "purchase_price": valor_compra,
        "purchase_price_inc_tax": valor_compra,  # Delphi não separa — exclusive default
        "item_tax": item_tax,
    }
    audit["status"] = "ok"
    audit["product_id_resolved"] = lookup["product_id"]
    audit["variation_id_resolved"] = lookup["variation_id"]
    return data, audit


def sql_value(v) -> str:
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, datetime):
        return "'" + v.strftime("%Y-%m-%d %H:%M:%S") + "'"
    s = str(v).replace("'", "''")
    return "'" + s + "'"


def emit_insert_sql(table: str, data: dict) -> str:
    cols = [c for c, v in data.items() if v is not None]
    vals = ", ".join(sql_value(data[c]) for c in cols)
    return (
        f"INSERT INTO {table} ({', '.join(cols)}, created_at, updated_at) "
        f"VALUES ({vals}, NOW(), NOW());"
    )


def get_writable_cols(con, table: str) -> set[str]:
    with con.cursor() as cur:
        cur.execute(f"SELECT * FROM {table} LIMIT 0")
        return {d[0] for d in cur.description}


def filter_to_schema(data: dict, cols_reais: set[str]) -> dict:
    return {k: v for k, v in data.items() if k in cols_reais}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--start-date", help="NF_DT_EMISSAO >= YYYY-MM-DD")
    parser.add_argument("--end-date", help="NF_DT_EMISSAO <= YYYY-MM-DD")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--batch-size", type=int, default=300)
    parser.add_argument("--location-default", type=int, default=1, help="location_id alvo (NOT NULL)")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    parser.add_argument("--only-ativo", action="store_true", help="Filtra ATIVO='S'")
    parser.add_argument("--skip-lines", action="store_true", help="Importa só cabeçalhos (sem purchase_lines)")
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
        print("[ERRO] --target prod requer --confirm explícito", file=sys.stderr)
        return 2

    print(f"== Importer COMPRAS v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird       : {args.alias}")
    print(f"  business_id alvo     : {args.target_business}")
    print(f"  Target MySQL         : {args.target}")
    print(f"  location_id default  : {args.location_default}")
    if args.start_date or args.end_date:
        print(f"  Filtro NF_DT_EMISSAO : [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]")
    if args.limit:
        print(f"  Limit rows           : {args.limit}")
    if args.skip_lines:
        print(f"  Skip purchase_lines  : SIM (só cabeçalhos)")

    stats = {
        "nfe_lidas": 0,
        "nfe_inserts": 0,
        "nfe_updates": 0,
        "nfe_skipped_no_contact": 0,
        "nfe_skipped_inativo": 0,
        "nfe_com_contact_cnpj": 0,
        "nfe_com_contact_name": 0,
        "nfe_sem_contact": 0,
        "nfep_lidos": 0,
        "nfep_inserts": 0,
        "nfep_skipped_sem_produto": 0,
        "nfep_skipped_quant_zero": 0,
        "errors": 0,
    }

    audit_records_nfe: list[dict] = []
    audit_records_nfep: list[dict] = []
    sample_inserts_nfe: list[str] = []
    sample_inserts_nfep: list[str] = []

    dry_run_lines: list[str] = [
        f"-- Generated by import-compras v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business={args.target_business}  alias={args.alias}",
        f"-- Filter: NF_DT_EMISSAO [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]",
        "",
        "-- Idempotência: SELECT por (business_id, ref_no=CODIGO) + UPDATE OU INSERT.",
        "-- purchase_lines: DELETE + INSERT por transaction_id (atomicidade).",
        "-- Lookups: contact_id via CNPJ→name; product_id via officeimpresso_codigo.",
        "",
    ]

    # Carrega lookups MySQL (somente local/prod)
    contact_lookup_cnpj: dict[str, int] = {}
    contact_lookup_name: dict[str, int] = {}
    product_lookup: dict[str, dict] = {}

    if args.target in ("local", "prod"):
        if pymysql is None:
            print("[ERRO] pymysql não instalado", file=sys.stderr)
            return 3
        print("\n[MySQL] Carregando lookups (contacts + products)...")
        lookup_con = pymysql.connect(
            host=args.mysql_host, port=args.mysql_port,
            user=args.mysql_user, password=args.mysql_password,
            database=args.mysql_database, charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )
        try:
            with lookup_con.cursor() as cur:
                cur.execute(
                    "SELECT id, cpf_cnpj, name FROM contacts WHERE business_id=%s",
                    (args.target_business,),
                )
                for r in cur.fetchall():
                    if r["cpf_cnpj"]:
                        digits = re.sub(r"\D", "", str(r["cpf_cnpj"]))
                        if digits:
                            contact_lookup_cnpj[digits] = int(r["id"])
                    if r["name"]:
                        contact_lookup_name[str(r["name"]).upper().strip()] = int(r["id"])

                cur.execute(
                    """
                    SELECT p.id AS product_id, p.officeimpresso_codigo,
                           v.id AS variation_id, v.product_variation_id
                    FROM products p
                    INNER JOIN variations v ON v.product_id = p.id
                    WHERE p.business_id = %s
                      AND p.officeimpresso_codigo IS NOT NULL
                      AND p.deleted_at IS NULL
                    """,
                    (args.target_business,),
                )
                for r in cur.fetchall():
                    product_lookup[str(r["officeimpresso_codigo"])] = {
                        "product_id": int(r["product_id"]),
                        "variation_id": int(r["variation_id"]),
                        "product_variation_id": int(r["product_variation_id"]),
                    }
        finally:
            lookup_con.close()
        print(f"[Contact lookup CNPJ] {len(contact_lookup_cnpj)} entries")
        print(f"[Contact lookup NAME] {len(contact_lookup_name)} entries")
        print(f"[Product lookup]      {len(product_lookup)} produtos")
        if not product_lookup:
            print("[WARN] Nenhum product com officeimpresso_codigo — rode import-produtos.py ANTES!")
        if not contact_lookup_cnpj and not contact_lookup_name:
            print("[WARN] Nenhum contact em biz — fornecedores precisam estar criados antes!")
    else:
        print("[Lookups] dry-run · simulados vazios (contact_id e product_id ficarão NULL no preview)")

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
        # Adapter NFE
        cols_existentes_nfe = get_existing_cols(fb_con, "NOTA_FISCAL_ENTRADA")
        cols_ausentes_nfe = [c for c in NFE_COLS if c not in cols_existentes_nfe]
        print(f"[Adapter] NOTA_FISCAL_ENTRADA cols presentes: {len(cols_existentes_nfe)} · ausentes: {len(cols_ausentes_nfe)}")
        if cols_ausentes_nfe:
            print(f"          Ausentes (NULL): {cols_ausentes_nfe}")

        # Delta filter — DT_ALTERACAO ausente em algumas versões → fallback FULL SYNC com warn
        has_dt_alteracao_nfe = "DT_ALTERACAO" in cols_existentes_nfe
        if delta_active and not has_dt_alteracao_nfe:
            print(
                "[delta WARN] NOTA_FISCAL_ENTRADA não tem DT_ALTERACAO — fallback FULL SYNC",
                file=sys.stderr,
            )
            delta_active = False

        # Adapter NFEP
        cols_existentes_nfep = get_existing_cols(fb_con, "NF_ENTRADA_PRODUTOS")
        cols_ausentes_nfep = [c for c in NFEP_COLS if c not in cols_existentes_nfep]
        print(f"[Adapter] NF_ENTRADA_PRODUTOS cols presentes: {len(cols_existentes_nfep)} · ausentes: {len(cols_ausentes_nfep)}")
        if cols_ausentes_nfep:
            print(f"          Ausentes (NULL): {cols_ausentes_nfep}")

        # Query NFE
        where_parts_nfe = []
        params_nfe: list = []
        if args.start_date and "NF_DT_EMISSAO" in cols_existentes_nfe:
            where_parts_nfe.append("P.NF_DT_EMISSAO >= ?")
            params_nfe.append(args.start_date)
        if args.end_date and "NF_DT_EMISSAO" in cols_existentes_nfe:
            where_parts_nfe.append("P.NF_DT_EMISSAO <= ?")
            params_nfe.append(args.end_date + " 23:59:59")
        if args.only_ativo and "ATIVO" in cols_existentes_nfe:
            where_parts_nfe.append("P.ATIVO = 'S'")
        if delta_active and delta_since is not None:
            where_parts_nfe.append("P.DT_ALTERACAO > ?")
            params_nfe.append(sc.format_firebird_timestamp(delta_since))
        where_clause_nfe = ("WHERE " + " AND ".join(where_parts_nfe)) if where_parts_nfe else ""

        select_nfe = build_adapted_select(cols_existentes_nfe, NFE_COLS, alias="P")
        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        sql_nfe = f"""
            SELECT {first_clause} {select_nfe}
            FROM NOTA_FISCAL_ENTRADA P
            {where_clause_nfe}
            ORDER BY P.CODIGO
        """
        print(f"\n[Query NOTA_FISCAL_ENTRADA] {sql_nfe.strip()[:200]}...")
        cur_nfe = fb_con.cursor()
        cur_nfe.execute(sql_nfe, tuple(params_nfe))
        col_names_nfe = [d[0] for d in cur_nfe.description]

        # MySQL writer
        con = None
        cols_reais_tx: set[str] = set()
        cols_reais_pl: set[str] = set()
        if args.target in ("local", "prod"):
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )
            cols_reais_tx = get_writable_cols(con, "transactions")
            cols_reais_pl = get_writable_cols(con, "purchase_lines")
            print(f"[Schema] transactions cols reais: {len(cols_reais_tx)}")
            print(f"[Schema] purchase_lines cols reais: {len(cols_reais_pl)}")

        try:
            batch_count = 0
            for row_nfe in cur_nfe:
                stats["nfe_lidas"] += 1
                nfe = dict(zip(col_names_nfe, row_nfe))

                ativo = (nfe.get("ATIVO") or "S").upper()
                if ativo == "N":
                    stats["nfe_skipped_inativo"] += 1
                    continue

                tx_data, tx_audit = map_nfe_to_transaction(
                    nfe, args.target_business, contact_lookup_cnpj, contact_lookup_name,
                    args.created_by, args.location_default,
                )

                if tx_audit["contact_match_kind"] == "cnpj":
                    stats["nfe_com_contact_cnpj"] += 1
                elif tx_audit["contact_match_kind"] == "name_fallback":
                    stats["nfe_com_contact_name"] += 1
                else:
                    stats["nfe_sem_contact"] += 1

                if len(audit_records_nfe) < 500:
                    audit_records_nfe.append(tx_audit)

                if args.target == "dry-run":
                    sql_str = emit_insert_sql("transactions", tx_data)
                    if len(sample_inserts_nfe) < 5:
                        sample_inserts_nfe.append(sql_str)
                    dry_run_lines.append(
                        f"-- NFE {tx_audit['legacy_id']} · "
                        f"contact_match={tx_audit['contact_match_kind']} · "
                        f"total={tx_audit['final_total']}"
                    )
                    dry_run_lines.append(sql_str)
                    dry_run_lines.append("")
                    stats["nfe_inserts"] += 1

                    # Em dry-run, contar quantos itens existem (consulta paralela rápida)
                    if not args.skip_lines:
                        cur_items_count = fb_con.cursor()
                        try:
                            cur_items_count.execute(
                                "SELECT COUNT(*) FROM NF_ENTRADA_PRODUTOS WHERE CODNF_ENTRADA = ?",
                                (nfe["CODIGO"],),
                            )
                            items_count = cur_items_count.fetchone()[0] or 0
                            stats["nfep_lidos"] += items_count
                            # Estimativa: assume todos linkam (best case)
                            stats["nfep_inserts"] += items_count
                        finally:
                            cur_items_count.close()
                else:
                    # contact_id é NOT NULL FK em transactions — skip se NULL
                    if tx_data["contact_id"] is None:
                        stats["nfe_skipped_no_contact"] += 1
                        if stats["nfe_skipped_no_contact"] <= 3:
                            print(f"  SKIP NFE {tx_audit['legacy_id']} — contact NULL (fornecedor não existe em biz)")
                        continue

                    assert con is not None
                    tx_filtered = filter_to_schema(tx_data, cols_reais_tx)
                    tx_filtered = {k: v for k, v in tx_filtered.items() if v is not None}

                    with con.cursor() as wcur:
                        # UPSERT transactions
                        wcur.execute(
                            "SELECT id FROM transactions WHERE business_id=%s AND ref_no=%s AND type='purchase' LIMIT 1",
                            (args.target_business, tx_data["ref_no"]),
                        )
                        existing = wcur.fetchone()
                        if existing:
                            transaction_id = int(existing["id"])
                            update_fields = {
                                k: v for k, v in tx_filtered.items()
                                if k not in ("business_id", "type", "ref_no", "created_by")
                            }
                            if update_fields:
                                # CINTO+SUSPENSÓRIO Tier 0 (ADR 0093): AND business_id=%s
                                # previne cross-business contamination (incidente 2026-05-14 ROTA LIVRE).
                                set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                                wcur.execute(
                                    f"UPDATE transactions SET {set_clause}, updated_at=NOW() "
                                    f"WHERE id=%s AND business_id=%s",
                                    (*update_fields.values(), transaction_id, args.target_business),
                                )
                                if wcur.rowcount == 0:
                                    stats["skipped_cross_business_guard"] = stats.get("skipped_cross_business_guard", 0) + 1
                                    continue
                            stats["nfe_updates"] += 1
                        else:
                            cols = list(tx_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO transactions ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(tx_filtered.values()),
                            )
                            transaction_id = wcur.lastrowid
                            stats["nfe_inserts"] += 1

                        # purchase_lines: DELETE + INSERT (atomicidade idempotente)
                        if not args.skip_lines:
                            wcur.execute(
                                "DELETE FROM purchase_lines WHERE transaction_id=%s",
                                (transaction_id,),
                            )

                            # Lê itens NF_ENTRADA_PRODUTOS
                            select_nfep = build_adapted_select(cols_existentes_nfep, NFEP_COLS, alias="I")
                            sql_items = f"""
                                SELECT {select_nfep}
                                FROM NF_ENTRADA_PRODUTOS I
                                WHERE I.CODNF_ENTRADA = ?
                            """
                            cur_items = fb_con.cursor()
                            try:
                                cur_items.execute(sql_items, (nfe["CODIGO"],))
                                col_names_nfep = [d[0] for d in cur_items.description]
                                for row_item in cur_items:
                                    stats["nfep_lidos"] += 1
                                    nfep = dict(zip(col_names_nfep, row_item))
                                    line_data, line_audit = map_nfep_to_purchase_line(
                                        nfep, transaction_id, product_lookup,
                                    )
                                    if line_audit["status"] == "skipped_sem_codproduto":
                                        continue
                                    if line_audit["status"] == "skipped_produto_nao_encontrado":
                                        stats["nfep_skipped_sem_produto"] += 1
                                        continue
                                    if line_audit["status"] == "skipped_quant_zero":
                                        stats["nfep_skipped_quant_zero"] += 1
                                        continue

                                    line_filtered = filter_to_schema(line_data, cols_reais_pl)
                                    line_filtered = {k: v for k, v in line_filtered.items() if v is not None}
                                    cols_pl = list(line_filtered.keys())
                                    placeholders_pl = ", ".join(["%s"] * len(cols_pl))
                                    wcur.execute(
                                        f"INSERT INTO purchase_lines ({', '.join(cols_pl)}, created_at, updated_at) "
                                        f"VALUES ({placeholders_pl}, NOW(), NOW())",
                                        tuple(line_filtered.values()),
                                    )
                                    stats["nfep_inserts"] += 1
                                    if len(audit_records_nfep) < 500:
                                        audit_records_nfep.append(line_audit)
                            finally:
                                cur_items.close()

                    batch_count += 1
                    if batch_count >= args.batch_size:
                        con.commit()
                        print(f"  [batch] commited {batch_count} NFE · total lidas: {stats['nfe_lidas']}")
                        batch_count = 0

                if stats["nfe_lidas"] % 500 == 0:
                    print(f"  ... NFE lidas {stats['nfe_lidas']}")

            if con and batch_count > 0:
                con.commit()
                print(f"  [batch final] commited {batch_count}")
            if con and args.delta_since_last_sync:
                try:
                    sc.mark_success(
                        con,
                        args.target_business,
                        args.sync_type,
                        rows_processed=stats["nfe_inserts"] + stats["nfe_updates"],
                    )
                    con.commit()
                except Exception as e:
                    print(f"[sync_checkpoint] WARN mark_success: {e!r}", file=sys.stderr)

        except Exception as e:
            if con:
                con.rollback()
                print(f"\n[ERRO] Rollback: {e}", file=sys.stderr)
                if args.delta_since_last_sync:
                    try:
                        sc.mark_failed(con, args.target_business, args.sync_type, error_msg=repr(e))
                        con.commit()
                    except Exception as e2:
                        print(f"[sync_checkpoint] WARN mark_failed: {e2!r}", file=sys.stderr)
            stats["errors"] += 1
            raise
        finally:
            cur_nfe.close()
            if con:
                con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    biz_suffix = f"biz{args.target_business}"

    if args.target == "dry-run":
        sql_path = out / f"dry-run-compras-{biz_suffix}-{ts}.sql"
        sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
        print(f"\n[SQL preview] {sql_path}")

    audit_path = out / f"audit-compras-{biz_suffix}-{ts}.json"
    audit_summary = {
        "importer_version": IMPORTER_VERSION,
        "alias": args.alias,
        "business_id": args.target_business,
        "target": args.target,
        "filter": {
            "start_date": args.start_date, "end_date": args.end_date,
            "limit": args.limit, "only_ativo": args.only_ativo,
            "skip_lines": args.skip_lines,
        },
        "adapter": {
            "nfe_cols_canonicas_pedidas": len(NFE_COLS),
            "nfe_cols_ausentes": cols_ausentes_nfe,
            "nfep_cols_canonicas_pedidas": len(NFEP_COLS),
            "nfep_cols_ausentes": cols_ausentes_nfep,
        },
        "stats": stats,
        "sample_inserts_nfe": sample_inserts_nfe,
        "sample_inserts_nfep": sample_inserts_nfep,
        "records_nfe_total": len(audit_records_nfe),
        "records_nfe_sample_first_500": audit_records_nfe[:500],
        "records_nfep_total": len(audit_records_nfep),
        "records_nfep_sample_first_500": audit_records_nfep[:500],
    }
    audit_path.write_text(
        json.dumps(audit_summary, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  NFE lidas               : {stats['nfe_lidas']}")
    print(f"    com contact (CNPJ)    : {stats['nfe_com_contact_cnpj']}")
    print(f"    com contact (NAME)    : {stats['nfe_com_contact_name']}")
    print(f"    sem contact           : {stats['nfe_sem_contact']}")
    print(f"  NFE INSERT              : {stats['nfe_inserts']}")
    print(f"  NFE UPDATE              : {stats['nfe_updates']}")
    print(f"  NFE skipped (no contact): {stats['nfe_skipped_no_contact']}")
    print(f"  NFE skipped (inativo)   : {stats['nfe_skipped_inativo']}")
    print(f"  NF_ENTRADA_PRODUTOS lid : {stats['nfep_lidos']}")
    print(f"  purchase_lines INSERT   : {stats['nfep_inserts']}")
    print(f"  skipped (sem produto)   : {stats['nfep_skipped_sem_produto']}")
    print(f"  skipped (quant zero)    : {stats['nfep_skipped_quant_zero']}")
    print(f"  Errors                  : {stats['errors']}")
    print(f"\n[OK] Compras concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
