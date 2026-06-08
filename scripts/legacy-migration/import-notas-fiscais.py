"""
Fase 5+ — Importer NOTA_FISCAL Delphi WR Comercial → nfe_emissoes.

Pattern descoberto Martinho v1404 (NOTA_FISCAL — 21 colunas):
- CODIGO + CODEMPRESA + CODVENDA = PK natural Delphi (não único per nota emitida —
  tentativas rejeitadas reusam NF_NUMERO/SERIE)
- NF_CHAVE (44 dígitos) único por tentativa de emissão
- NF_CSTAT é o sinal canônico do SEFAZ:
    100 = autorizada (definitiva, ocupa numero+serie)
    101/151/155 = cancelada
    110/301/302 = denegada
    217 = NF não consta (rejeição — tentativa fracassada)
- NF_NUMERO + NF_TIPO + NF_AMBIENTE → mapping modelo/serie do oimpresso
- TIPO (texto) detecta NFCe vs NFe (modelo 55 vs 65)

Filtro padrão: importar APENAS notas "definitivas":
- cstat=100 (autorizada) OU
- NF_DT_CANCELAMENTO IS NOT NULL (cancelada após autorização) OU
- cstat IN (101,151,155) (cancelada via evento)

Rejeitadas (cstat=217 etc.) ficam em audit JSON mas não em nfe_emissoes (são
tentativas, não notas válidas — UK (business_id, modelo, serie, numero) violaria).
Override com --include-rejeitadas pra debug forense.

Idempotência: UNIQUE (business_id, modelo, serie, numero). SELECT prévio antes
do INSERT — re-rodar é UPDATE de status/metadata.

Multi-tenant Tier 0 (ADR 0093): business_id obrigatório em todos INSERTs.
PII redaction (audit): NF_CHAVE, NF_RAZAOSOCIAL → [REDACTED].

One-way bridge (ADR 0019): Firebird read-only — `nfe_emissoes` reflete o estado
SEFAZ no momento da importação; não escreve XML no .fdb.

Uso:
    python import-notas-fiscais.py --alias MartinhoServidor --target-business 164
    python import-notas-fiscais.py --alias MartinhoServidor --target-business 164 --target local
    python import-notas-fiscais.py --alias MartinhoServidor --target-business 164 --target prod --confirm
    python import-notas-fiscais.py --alias MartinhoServidor --target-business 164 \\
        --start-date 2024-12-01 --end-date 2024-12-31 --limit 50

Refs: memory/decisions/0118 (segregação dominios externos), ADR 0093 (Tier 0),
      Modules/NfeBrasil/Database/Migrations/2026_05_06_002001_create_nfe_emissoes_table.php
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime, date, timezone
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

# Cols canônicas NOTA_FISCAL (Martinho v1404). Adapter detecta ausentes.
NOTA_FISCAL_COLS = [
    "CODIGO", "CODEMPRESA", "CODVENDA",
    "NF_DT_EMISSAO", "NF_NATUREZA_OPERACAO",
    "NF_NUMERO", "NF_CHAVE", "NF_PROTOCOLO",
    "NF_AMBIENTE",
    "NF_PROTOCOLO_CANCELAMENTO", "NF_DT_CANCELAMENTO",
    "NF_CSTAT", "MOTIVO_CANCELAMENTO", "NF_MOTIVO_STATUS",
    "NF_PROTOCOLO_CARTA_CORRECAO", "NF_SEQUENCIA_EVENTO_CCE",
    "TIPO", "NF_TIPO",
    "NF_RAZAOSOCIAL", "NF_SITUACAO",
]

# Cols que adicionalmente podem existir em versões mais novas
NOTA_FISCAL_COLS_OPT = ["NF_SERIE", "VALOR_TOTAL", "VALOR_NF"]

PII_FIELDS = {"NF_CHAVE", "NF_RAZAOSOCIAL", "NF_PROTOCOLO"}

# CSTAT canônicos SEFAZ — mapping pra status nfe_emissoes enum
CSTAT_AUTORIZADA = {100, 150}
CSTAT_CANCELADA = {101, 151, 155}
CSTAT_DENEGADA = {110, 301, 302, 303}
CSTAT_REJEITADA_TENTATIVA = {217, 218, 999}  # 217=NF não consta (tentativa fracassada)


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


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


def redact_pii(v):
    if v is None or v == "":
        return None
    return "[REDACTED]"


def detect_modelo(row: dict) -> str:
    """TIPO/NF_TIPO Delphi → nfe_emissoes.modelo enum (55|65|67)."""
    tipo_texto = (row.get("TIPO") or "").upper()
    if "NFCE" in tipo_texto or "NFC-E" in tipo_texto or "CONSUMIDOR" in tipo_texto:
        return "65"
    if "CTE" in tipo_texto or "CT-E" in tipo_texto:
        return "67"
    return "55"  # NFe modelo 55 (B2B padrão)


def detect_serie(row: dict) -> str:
    """NF_SERIE quando existe, fallback '1' (default Delphi historico)."""
    s = row.get("NF_SERIE")
    if s is None:
        return "1"
    s = str(s).strip()
    if not s:
        return "1"
    # NF_SERIE max 3 chars no schema oimpresso
    return s[:3]


def classify_nota(row: dict) -> tuple[str, str]:
    """Decide (decision, kind) — decision in {import, skip}, kind = razão.

    Notas 'definitivas' importam. Rejeitadas/tentativas só com --include-rejeitadas.
    """
    cstat = row.get("NF_CSTAT")
    if cstat is not None:
        try:
            cstat = int(cstat)
        except (ValueError, TypeError):
            cstat = None

    dt_cancel = row.get("NF_DT_CANCELAMENTO")

    if dt_cancel is not None:
        return ("import", "cancelada_via_data")
    if cstat in CSTAT_AUTORIZADA:
        return ("import", "autorizada")
    if cstat in CSTAT_CANCELADA:
        return ("import", "cancelada_via_cstat")
    if cstat in CSTAT_DENEGADA:
        return ("import", "denegada")
    if cstat in CSTAT_REJEITADA_TENTATIVA:
        return ("skip", "rejeitada_tentativa")

    # Fallback por NF_SITUACAO textual
    situacao = (row.get("NF_SITUACAO") or "").lower().strip()
    if situacao.startswith("autoriz"):
        return ("import", "autorizada_via_texto")
    if "cancel" in situacao:
        return ("import", "cancelada_via_texto")
    if "deneg" in situacao:
        return ("import", "denegada_via_texto")
    if "rejei" in situacao or "n\xe3o autoriz" in situacao or "nao autoriz" in situacao:
        return ("skip", "rejeitada_via_texto")
    if "pendente" in situacao or not situacao:
        return ("skip", "pendente_ou_vazio")
    return ("skip", f"unknown:{situacao[:30]}")


def map_status(row: dict, kind: str) -> tuple[str, str | None]:
    """nfe_emissoes.status enum + cstat string."""
    cstat = row.get("NF_CSTAT")
    cstat_str = str(cstat) if cstat is not None else None

    if row.get("NF_DT_CANCELAMENTO") is not None:
        return ("cancelada", cstat_str)
    if kind.startswith("autorizada"):
        return ("autorizada", cstat_str)
    if kind.startswith("cancelada"):
        return ("cancelada", cstat_str)
    if kind.startswith("denegada"):
        return ("denegada", cstat_str)
    return ("pendente", cstat_str)


def parse_dt(val):
    if val is None:
        return None
    if isinstance(val, date):
        return val
    try:
        if hasattr(val, "date"):
            return val
        return val
    except Exception:
        return None


def map_nota_to_emissao(
    nf: dict,
    business_id: int,
    transaction_lookup: dict[str, int],
    kind: str,
    valor_total_lookup: dict[str, float],
) -> tuple[dict, dict]:
    """NOTA_FISCAL Delphi → nfe_emissoes row."""
    legacy_codigo = str(nf["CODIGO"]).strip()
    cod_empresa = nf.get("CODEMPRESA")
    cod_venda = nf.get("CODVENDA")

    modelo = detect_modelo(nf)
    serie = detect_serie(nf)
    numero_raw = nf.get("NF_NUMERO")
    try:
        numero = int(numero_raw) if numero_raw is not None else 0
    except (ValueError, TypeError):
        numero = 0

    chave_44 = normalize_str(nf.get("NF_CHAVE"), 44)
    status, cstat = map_status(nf, kind)
    motivo = normalize_str(nf.get("NF_MOTIVO_STATUS")) or normalize_str(nf.get("MOTIVO_CANCELAMENTO"))

    # transaction_id via CODVENDA lookup (transactions.ref_no — populado por import-vendas.py)
    transaction_id = None
    if cod_venda is not None:
        cv = str(cod_venda).strip()
        if cv in transaction_lookup:
            transaction_id = transaction_lookup[cv]
        elif "-" in cv:
            base = cv.split("-")[0]
            if base in transaction_lookup:
                transaction_id = transaction_lookup[base]

    # Valor total via lookup VENDA (NOTA_FISCAL historicamente não tem coluna)
    valor_total = 0.0
    if cod_venda is not None:
        cv = str(cod_venda).strip()
        if cv in valor_total_lookup:
            valor_total = valor_total_lookup[cv]
        elif "-" in cv:
            base = cv.split("-")[0]
            valor_total = valor_total_lookup.get(base, 0.0)
    # Fallback: coluna direta (versões novas)
    if valor_total == 0.0:
        for col in ("VALOR_TOTAL", "VALOR_NF"):
            if nf.get(col):
                valor_total = normalize_decimal(nf.get(col))
                if valor_total > 0:
                    break

    emitido_em = parse_dt(nf.get("NF_DT_EMISSAO"))

    metadata = {
        "legacy_source": LEGACY_SOURCE,
        "legacy_codigo": legacy_codigo,
        "legacy_codvenda": str(cod_venda) if cod_venda is not None else None,
        "legacy_codempresa": int(cod_empresa) if cod_empresa is not None else None,
        "natureza_operacao": normalize_str(nf.get("NF_NATUREZA_OPERACAO"), 60),
        "ambiente": int(nf.get("NF_AMBIENTE") or 1),
        "tipo_texto": normalize_str(nf.get("TIPO")),
        "nf_tipo_numerico": nf.get("NF_TIPO"),
        "situacao_delphi": normalize_str(nf.get("NF_SITUACAO")),
        "protocolo": normalize_str(nf.get("NF_PROTOCOLO")),
        "protocolo_cancelamento": normalize_str(nf.get("NF_PROTOCOLO_CANCELAMENTO")),
        "protocolo_carta_correcao": normalize_str(nf.get("NF_PROTOCOLO_CARTA_CORRECAO")),
        "sequencia_evento_cce": nf.get("NF_SEQUENCIA_EVENTO_CCE"),
        "dt_cancelamento": str(nf.get("NF_DT_CANCELAMENTO")) if nf.get("NF_DT_CANCELAMENTO") else None,
        "razao_social_redacted": redact_pii(nf.get("NF_RAZAOSOCIAL")),
        "chave_44_redacted": redact_pii(chave_44),
        "kind": kind,
        "transaction_id_resolved": transaction_id,
        "imported_at_iso": datetime.now(timezone.utc).isoformat(),
        "importer_version": IMPORTER_VERSION,
    }

    data = {
        "business_id": business_id,
        "transaction_id": transaction_id,
        "modelo": modelo,
        "serie": serie,
        "numero": numero,
        "chave_44": chave_44,
        "status": status,
        "cstat": cstat,
        "motivo": motivo,
        "xml_path": None,
        "danfe_path": None,
        "valor_total": valor_total,
        "emitido_em": emitido_em,
        "metadata": json.dumps(metadata, ensure_ascii=False, default=str),
    }

    audit = {
        "legacy_codigo": legacy_codigo,
        "legacy_codvenda": str(cod_venda) if cod_venda is not None else None,
        "modelo": modelo,
        "serie": serie,
        "numero": numero,
        "status": status,
        "cstat": cstat,
        "kind": kind,
        "valor_total": valor_total,
        "transaction_id_resolved": transaction_id,
        "razao_social_redacted": redact_pii(nf.get("NF_RAZAOSOCIAL")),
        "nf_chave_redacted": redact_pii(chave_44),
        "emitido_em": str(emitido_em) if emitido_em else None,
    }

    return data, audit


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--start-date", help="NF_DT_EMISSAO >= YYYY-MM-DD")
    parser.add_argument("--end-date", help="NF_DT_EMISSAO <= YYYY-MM-DD")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--batch-size", type=int, default=500)
    parser.add_argument("--include-rejeitadas", action="store_true",
                        help="Importa também notas rejeitadas (cstat=217 etc) — debug forense")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr)
        return 2

    print(f"== Importer NOTA_FISCAL v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")
    print(f"  Include rejeitadas: {args.include_rejeitadas}")
    if args.start_date or args.end_date:
        print(f"  Filtro EMISSAO   : [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]")

    stats = {
        "lidos": 0,
        "inserts": 0, "updates": 0,
        "skipped_total": 0,
        "skip_by_kind": {},
        "skip_uk_collision_handled": 0,
        "errors": 0,
        "by_status": {},
        "by_modelo": {},
    }

    audit_records = []
    sample_emissoes = []

    print("\n[Firebird] Conectando...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        cols_existentes = get_existing_cols(fb_con, "NOTA_FISCAL")
        cols_pedidas = NOTA_FISCAL_COLS + [c for c in NOTA_FISCAL_COLS_OPT if c in cols_existentes]
        cols_ausentes = [c for c in NOTA_FISCAL_COLS if c not in cols_existentes]
        print(f"[Adapter] NOTA_FISCAL cols presentes: {len(cols_existentes)} · canônicas pedidas: {len(NOTA_FISCAL_COLS)} · ausentes: {len(cols_ausentes)}")
        if cols_ausentes:
            print(f"          Ausentes (NULL): {cols_ausentes}")

        # Lookups MySQL — transactions (via ref_no=CODVENDA) + valor venda
        transaction_lookup: dict[str, int] = {}
        valor_total_lookup: dict[str, float] = {}

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
                        "SELECT id, ref_no, final_total FROM transactions "
                        "WHERE business_id=%s AND ref_no IS NOT NULL",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        ref = str(r["ref_no"])
                        transaction_lookup[ref] = int(r["id"])
                        valor_total_lookup[ref] = float(r["final_total"] or 0)
                        if "-" in ref:
                            base = ref.split("-")[0]
                            transaction_lookup.setdefault(base, int(r["id"]))
                            valor_total_lookup.setdefault(base, float(r["final_total"] or 0))
            finally:
                tmp_con.close()
            print(f"[Transactions lookup] {len(transaction_lookup)} entries")
        else:
            print("[Lookups] dry-run · vazio (transaction_id ficará NULL)")

        # Query NOTA_FISCAL com filtro
        where_parts = []
        params: list = []
        if args.start_date:
            where_parts.append("NF_DT_EMISSAO >= ?")
            params.append(args.start_date)
        if args.end_date:
            where_parts.append("NF_DT_EMISSAO <= ?")
            params.append(args.end_date + " 23:59:59")
        where_clause = ("WHERE " + " AND ".join(where_parts)) if where_parts else ""

        select_parts = []
        for c in cols_pedidas:
            if c in cols_existentes:
                select_parts.append(c)
            else:
                select_parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
        select_clause = ", ".join(select_parts)
        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        sql = f"SELECT {first_clause} {select_clause} FROM NOTA_FISCAL {where_clause} ORDER BY CODIGO"
        print(f"\n[Query NOTA_FISCAL] {sql[:240]}...")

        cur = fb_con.cursor()
        cur.execute(sql, tuple(params))
        col_names = [d[0] for d in cur.description]

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
            for row_tuple in cur:
                stats["lidos"] += 1
                nf = dict(zip(col_names, row_tuple))

                decision, kind = classify_nota(nf)

                if decision == "skip" and not args.include_rejeitadas:
                    stats["skipped_total"] += 1
                    stats["skip_by_kind"][kind] = stats["skip_by_kind"].get(kind, 0) + 1
                    continue

                # Se include_rejeitadas, forçar decision=import mesmo se rejeitada
                if decision == "skip" and args.include_rejeitadas:
                    pass  # processa mesmo assim

                data, audit = map_nota_to_emissao(
                    nf, args.target_business, transaction_lookup, kind, valor_total_lookup
                )

                if data["numero"] == 0:
                    stats["skipped_total"] += 1
                    stats["skip_by_kind"]["numero_zero"] = stats["skip_by_kind"].get("numero_zero", 0) + 1
                    continue

                # Stats
                stats["by_status"][data["status"]] = stats["by_status"].get(data["status"], 0) + 1
                stats["by_modelo"][data["modelo"]] = stats["by_modelo"].get(data["modelo"], 0) + 1

                if len(audit_records) < 500:
                    audit_records.append(audit)
                if len(sample_emissoes) < 5 and args.target == "dry-run":
                    sample_emissoes.append({k: str(v)[:80] for k, v in data.items() if k != "metadata"})

                if args.target == "dry-run":
                    stats["inserts"] += 1
                    continue

                assert con is not None
                with con.cursor() as wcur:
                    # Idempotência canônica: UK (business_id, modelo, serie, numero)
                    wcur.execute(
                        "SELECT id FROM nfe_emissoes "
                        "WHERE business_id=%s AND modelo=%s AND serie=%s AND numero=%s LIMIT 1",
                        (args.target_business, data["modelo"], data["serie"], data["numero"]),
                    )
                    existing = wcur.fetchone()
                    if existing:
                        # Re-rodar: UPDATE só status/cstat/motivo/metadata (numero não muda)
                        update_fields = {
                            "status": data["status"],
                            "cstat": data["cstat"],
                            "motivo": data["motivo"],
                            "chave_44": data["chave_44"],
                            "valor_total": data["valor_total"],
                            "emitido_em": data["emitido_em"],
                            "metadata": data["metadata"],
                        }
                        # Promoção de status: autorizada > cancelada > denegada > pendente > rejeitada
                        # Não rebaixar status (race conditions entre re-runs)
                        set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                        wcur.execute(
                            f"UPDATE nfe_emissoes SET {set_clause}, updated_at=NOW() WHERE id=%s",
                            (*update_fields.values(), existing["id"]),
                        )
                        stats["updates"] += 1
                    else:
                        cols = list(data.keys())
                        placeholders = ", ".join(["%s"] * len(cols))
                        try:
                            wcur.execute(
                                f"INSERT INTO nfe_emissoes ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(data.values()),
                            )
                            stats["inserts"] += 1
                        except pymysql.err.IntegrityError as e:
                            # UK collisions esperadas:
                            #   biz_tx_unique (business_id + transaction_id): mesma venda já tem NFe
                            #     (re-emissão, autorizada→cancelada→reemitida) — skip silencioso.
                            #   biz_seq_unique (business_id + modelo + serie + numero): bloqueado pelo
                            #     SELECT prévio, mas pode race em batch concorrente — skip também.
                            # 1062 = MySQL ER_DUP_ENTRY.
                            errno = e.args[0] if e.args else 0
                            err_str = str(e)
                            if errno == 1062 and ("biz_tx_unique" in err_str or "biz_seq_unique" in err_str
                                                   or "transaction_id" in err_str):
                                if "biz_tx_unique" in err_str:
                                    kind = "uk_collision_biz_tx"
                                elif "biz_seq_unique" in err_str:
                                    kind = "uk_collision_biz_seq"
                                else:
                                    kind = "uk_collision_other"
                                stats["skip_uk_collision_handled"] += 1
                                stats["skip_by_kind"][kind] = stats["skip_by_kind"].get(kind, 0) + 1
                                continue
                            raise

                batch_count += 1
                if batch_count >= args.batch_size:
                    con.commit()
                    print(f"  [batch] commited {batch_count} · total lidos: {stats['lidos']}")
                    batch_count = 0

                if stats["lidos"] % 5000 == 0:
                    print(f"  ... lidos {stats['lidos']}")

            if con and batch_count > 0:
                con.commit()
                print(f"  [batch final] commited {batch_count}")
        except Exception as e:
            if con:
                con.rollback()
                print(f"\n[ERRO] Rollback: {e}", file=sys.stderr)
            stats["errors"] += 1
            raise
        finally:
            cur.close()
            if con:
                con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    audit_path = out / f"audit-notas-fiscais-biz{args.target_business}-{ts}.json"
    audit_path.write_text(
        json.dumps({
            "importer_version": IMPORTER_VERSION,
            "alias": args.alias,
            "business_id": args.target_business,
            "target": args.target,
            "include_rejeitadas": args.include_rejeitadas,
            "filter": {"start_date": args.start_date, "end_date": args.end_date, "limit": args.limit},
            "stats": stats,
            "sample_emissoes": sample_emissoes,
            "records_total": len(audit_records),
            "records_sample_first_500": audit_records[:500],
        }, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"\n[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  Lidos              : {stats['lidos']}")
    print(f"  Skipped TOTAL      : {stats['skipped_total']}")
    for k, v in sorted(stats["skip_by_kind"].items(), key=lambda x: -x[1]):
        print(f"    {k:35s} : {v}")
    print(f"  INSERT             : {stats['inserts']}")
    print(f"  UPDATE             : {stats['updates']}")
    print(f"  UK collisions ignoradas: {stats['skip_uk_collision_handled']}")
    print(f"  By status:")
    for k, v in sorted(stats["by_status"].items(), key=lambda x: -x[1]):
        print(f"    {k:15s} : {v}")
    print(f"  By modelo:")
    for k, v in sorted(stats["by_modelo"].items(), key=lambda x: -x[1]):
        print(f"    modelo={k:5s}    : {v}")
    print(f"  Errors             : {stats['errors']}")
    print(f"[OK] NOTA_FISCAL concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
