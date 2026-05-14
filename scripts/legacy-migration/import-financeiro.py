"""
Fase 5 — Importer FINANCEIRO Delphi WR Comercial → fin_titulos + fin_titulo_baixas.

Pattern descoberto Martinho v1404:
- FINANCEIRO (57 cols) tem A_RECEBER + A_PAGAR misturados (filtra TIPO)
- DATAPAGTO IS NULL = título em aberto → fin_titulos.status='aberto'
- DATAPAGTO != NULL = pago → fin_titulos.status='quitado' + fin_titulo_baixas row
- VALOR = valor_total (positivo)
- VENCTO = vencimento, EMISSAO = emissao
- CODPEDIDO → lookup transactions.ref_no=CODPEDIDO → origem_id

UPSERT idempotente via (business_id, legacy_id=FINANCEIRO.CODIGO).

Write-off detection (cleanup heuristic Wagner):
- TIPO='A RECEBER' AND VENCTO < (NOW - 365d) AND DATAPAGTO IS NULL
  AND BOLETO_NOSSO_NR IS NULL AND JUROS=0 AND DESCONTO=0
→ flag em metadata.is_write_off_candidate=true (UI pode filtrar)

Multi-tenant Tier 0 (ADR 0093): business_id obrigatório em TODOS INSERTs.
PII redaction (audit): RAZAOSOCIAL, DOCUMENTO, NOTAFISCAL → [REDACTED].

Uso:
    python import-financeiro.py --alias MartinhoServidor --target-business 164
    python import-financeiro.py --alias MartinhoServidor --target-business 164 --target local
    python import-financeiro.py --alias MartinhoServidor --target-business 164 --target prod --confirm

Refs: memory/reference/migracao-officeimpresso-pattern.md §Fase 5
      memory/research/relatorios-jana/01-inadimplencia.md
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

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore

IMPORTER_VERSION = "0.1.0"
LEGACY_SOURCE = "wr-comercial-delphi"

# Cols canônicas FINANCEIRO (Martinho v1404). Adapter detecta ausentes.
FINANCEIRO_COLS = [
    "CODIGO", "CODPEDIDO", "CODEMPRESA", "RAZAOSOCIAL", "DOCUMENTO",
    "NOTAFISCAL", "HISTORICO", "EMISSAO", "VENCTO", "DATAPAGTO",
    "VALOR", "JUROS", "DESCONTO", "CODPLANOCONTAS", "TIPOPAGTO",
    "CONDICAOPAGTO", "PARCELA", "TIPO", "STATUS",
    "BOLETO_NOSSO_NR", "DT_COMPETENCIA", "CODCONTA", "ATIVO",
    "PESSOA_RESPONSAVEL_CODIGO",
]

PII_FIELDS = {"RAZAOSOCIAL", "DOCUMENTO", "NOTAFISCAL", "BOLETO_NOSSO_NR"}

WRITE_OFF_AGE_DAYS = 365  # >365d vencido sem ação = candidate


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def normalize_str(raw, max_len=None) -> str | None:
    if raw is None: return None
    s = str(raw).strip()
    if not s: return None
    if max_len and len(s) > max_len: s = s[:max_len]
    return s


def normalize_decimal(raw) -> float:
    if raw is None or raw == "": return 0.0
    try: return float(raw)
    except (ValueError, TypeError): return 0.0


def redact_pii(v):
    if v is None or v == "": return None
    return "[REDACTED]"


def parse_parcela(raw) -> tuple[int | None, int | None]:
    """'1/3' → (1, 3) · '5' → (5, None) · None → (None, None)."""
    if not raw: return None, None
    s = str(raw).strip()
    if "/" in s:
        try:
            a, b = s.split("/", 1)
            return int(a.strip()), int(b.strip())
        except (ValueError, TypeError):
            return None, None
    try: return int(s), None
    except (ValueError, TypeError): return None, None


def map_tipo(raw: str) -> str:
    """FINANCEIRO.TIPO Delphi → fin_titulos.tipo enum."""
    s = (raw or "").upper().strip()
    if "RECEBER" in s: return "receber"
    return "pagar"


def map_status(raw_status, datapagto) -> str:
    """FINANCEIRO.STATUS + DATAPAGTO → fin_titulos.status enum."""
    if datapagto is not None:
        return "quitado"
    s = (raw_status or "").upper().strip()
    if "CANCEL" in s: return "cancelado"
    if "PARCIAL" in s: return "parcial"
    return "aberto"  # default


def is_write_off_candidate(row: dict) -> bool:
    """Cleanup heuristic Wagner: >365d vencido sem boleto sem mov."""
    tipo = (row.get("TIPO") or "").upper()
    if "RECEBER" not in tipo:
        return False
    if row.get("DATAPAGTO") is not None:
        return False
    vencto = row.get("VENCTO")
    if vencto is None:
        return False
    try:
        dias = (date.today() - (vencto if isinstance(vencto, date) else vencto.date())).days
    except (AttributeError, TypeError):
        return False
    if dias < WRITE_OFF_AGE_DAYS:
        return False
    if row.get("BOLETO_NOSSO_NR"):
        return False
    if normalize_decimal(row.get("JUROS")) > 0:
        return False
    if normalize_decimal(row.get("DESCONTO")) > 0:
        return False
    return True


def map_financeiro_to_titulo(
    fin: dict,
    business_id: int,
    transaction_lookup: dict[str, int],
    contact_lookup: dict[str, int],
    created_by: int,
) -> tuple[dict, dict]:
    """FINANCEIRO Delphi → fin_titulos row."""
    legacy_id = str(fin["CODIGO"]).strip()
    valor = normalize_decimal(fin.get("VALOR"))
    datapagto = fin.get("DATAPAGTO")

    # Lookup transactions via ref_no = CODPEDIDO Delphi.
    # IMPORTANTE: fin_titulos tem UK (business_id, origem, origem_id, parcela_numero).
    # Múltiplos FINANCEIRO Delphi apontam pra mesma venda (parcelas) com parcela_numero
    # NULL ou colidente → UK trigger. Solução: sempre origem='manual' + origem_id=NULL,
    # link Delphi guardado em metadata.delphi_codpedido + metadata.transaction_id_resolved.
    cod_pedido = fin.get("CODPEDIDO")
    origem_id = None
    origem = "manual"
    transaction_id_resolved = None
    if cod_pedido is not None:
        cp = str(cod_pedido).strip()
        if cp in transaction_lookup:
            transaction_id_resolved = transaction_lookup[cp]

    # Lookup cliente via PESSOA_RESPONSAVEL_CODIGO (não tem CNPJ direto em FINANCEIRO)
    # Por ora cliente_id=NULL + cliente_descricao=RAZAOSOCIAL
    razao = normalize_str(fin.get("RAZAOSOCIAL"), 255)

    parcela_num, parcela_total = parse_parcela(fin.get("PARCELA"))

    tipo = map_tipo(fin.get("TIPO"))
    status = map_status(fin.get("STATUS"), datapagto)

    # Valor aberto = total - se pago
    valor_aberto = 0.0 if datapagto else valor

    emissao = fin.get("EMISSAO")
    vencto = fin.get("VENCTO")
    if vencto is None:
        vencto = emissao or date.today()
    if emissao is None:
        emissao = vencto

    # Competência (formato YYYY-MM)
    dt_comp = fin.get("DT_COMPETENCIA")
    competencia = None
    if dt_comp:
        try:
            competencia = (dt_comp if isinstance(dt_comp, date) else dt_comp.date()).strftime("%Y-%m")
        except (AttributeError, TypeError):
            pass
    if not competencia:
        try:
            competencia = (vencto if isinstance(vencto, date) else vencto.date()).strftime("%Y-%m")
        except (AttributeError, TypeError):
            competencia = "1900-01"

    # Number único per business: legacy_<CODIGO>
    numero = f"LEG-{legacy_id}"[:20]

    write_off = is_write_off_candidate(fin)

    data = {
        "business_id": business_id,
        "numero": numero,
        "legacy_id": legacy_id,
        "tipo": tipo,
        "status": status,
        "cliente_id": None,
        "cliente_descricao": razao,
        "valor_total": valor,
        "valor_aberto": valor_aberto,
        "moeda": "BRL",
        "emissao": emissao if isinstance(emissao, date) else (emissao.date() if emissao else date.today()),
        "vencimento": vencto if isinstance(vencto, date) else (vencto.date() if vencto else date.today()),
        "competencia_mes": competencia,
        "origem": origem,
        "origem_id": origem_id,
        "parcela_numero": parcela_num,
        "parcela_total": parcela_total,
        "titulo_pai_id": None,
        "plano_conta_id": None,
        "categoria_id": None,
        "observacoes": normalize_str(fin.get("HISTORICO"), 500),
        "metadata": json.dumps({
            "legacy_source": LEGACY_SOURCE,
            "legacy_id": legacy_id,
            "delphi_status_raw": normalize_str(fin.get("STATUS")),
            "delphi_tipo_raw": normalize_str(fin.get("TIPO")),
            "delphi_codpedido": cod_pedido,
            "transaction_id_resolved": transaction_id_resolved,
            "delphi_notafiscal_redacted": redact_pii(fin.get("NOTAFISCAL")),
            "delphi_boleto_nosso_nr_redacted": redact_pii(fin.get("BOLETO_NOSSO_NR")),
            "delphi_documento_redacted": redact_pii(fin.get("DOCUMENTO")),
            "delphi_juros": normalize_decimal(fin.get("JUROS")),
            "delphi_desconto": normalize_decimal(fin.get("DESCONTO")),
            "delphi_tipopagto": normalize_str(fin.get("TIPOPAGTO")),
            "delphi_condicaopagto": normalize_str(fin.get("CONDICAOPAGTO")),
            "is_write_off_candidate": write_off,
            "imported_at_iso": datetime.utcnow().isoformat() + "Z",
            "importer_version": IMPORTER_VERSION,
        }, ensure_ascii=False),
        "created_by": created_by,
    }

    audit = {
        "legacy_id": legacy_id,
        "tipo": tipo,
        "status": status,
        "is_write_off_candidate": write_off,
        "origem": origem,
        "origem_id_resolved": origem_id,
        "vencto": str(vencto) if vencto else None,
        "datapagto": str(datapagto) if datapagto else None,
        "valor": valor,
    }

    return data, audit


def map_baixa(fin: dict, titulo_id: int, business_id: int, conta_default: int, created_by: int) -> dict | None:
    """Se DATAPAGTO != NULL, gera fin_titulo_baixas row."""
    datapagto = fin.get("DATAPAGTO")
    if datapagto is None:
        return None
    valor = normalize_decimal(fin.get("VALOR"))
    juros = normalize_decimal(fin.get("JUROS"))
    desconto = normalize_decimal(fin.get("DESCONTO"))
    if valor == 0:
        return None
    legacy_id = str(fin["CODIGO"]).strip()
    return {
        "business_id": business_id,
        "titulo_id": titulo_id,
        "conta_bancaria_id": conta_default,
        "valor_baixa": valor,
        "juros": juros,
        "multa": 0,
        "desconto": desconto,
        "data_baixa": datapagto if isinstance(datapagto, date) else datapagto.date(),
        "meio_pagamento": "outro",  # Delphi não distingue
        "idempotency_key": f"leg-{business_id}-{legacy_id}"[:36],
        "observacoes": f"Importado de FINANCEIRO Delphi CODIGO={legacy_id}",
        "created_by": created_by,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--start-date", help="EMISSAO >= YYYY-MM-DD")
    parser.add_argument("--end-date", help="EMISSAO <= YYYY-MM-DD")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--batch-size", type=int, default=500)
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    parser.add_argument("--skip-baixas", action="store_true", help="Importa só fin_titulos (sem baixas)")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr)
        return 2

    print(f"== Importer FINANCEIRO v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird: {args.alias}")
    print(f"  business_id alvo: {args.target_business}")
    print(f"  Target MySQL: {args.target}")
    if args.start_date or args.end_date:
        print(f"  Filtro EMISSAO: [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]")

    stats = {
        "lidos": 0, "inserts_titulos": 0, "updates_titulos": 0,
        "inserts_baixas": 0, "skipped": 0, "errors": 0,
        "write_off_candidates": 0,
        "a_receber": 0, "a_pagar": 0,
        "quitados": 0, "abertos": 0, "cancelados": 0, "parciais": 0,
    }

    audit_records = []
    sample_titulos = []
    sample_baixas = []

    print("\n[Firebird] Conectando...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        cols_existentes = get_existing_cols(fb_con, "FINANCEIRO")
        cols_ausentes = [c for c in FINANCEIRO_COLS if c not in cols_existentes]
        print(f"[Adapter] FINANCEIRO cols presentes: {len(cols_existentes)} · canônicas pedidas: {len(FINANCEIRO_COLS)} · ausentes: {len(cols_ausentes)}")
        if cols_ausentes:
            print(f"          Ausentes (NULL): {cols_ausentes}")

        # Lookup transactions biz=N pra resolver origem_id via ref_no=CODPEDIDO
        transaction_lookup: dict[str, int] = {}
        contact_lookup: dict[str, int] = {}
        conta_default = 1  # primeira conta bancária biz alvo

        if args.target in ("local", "prod"):
            if pymysql is None:
                print("[ERRO] pymysql não instalado", file=sys.stderr)
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
                        "SELECT id, ref_no FROM transactions WHERE business_id=%s AND ref_no IS NOT NULL",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        # ref_no Delphi é "CODIGO-1" ou "CODIGO" — guardar ambos formatos
                        ref = str(r["ref_no"])
                        transaction_lookup[ref] = int(r["id"])
                        # Strip -N suffix
                        if "-" in ref:
                            transaction_lookup[ref.split("-")[0]] = int(r["id"])
                    cur.execute(
                        "SELECT id, legacy_id FROM contacts WHERE business_id=%s AND legacy_id IS NOT NULL",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        contact_lookup[str(r["legacy_id"])] = int(r["id"])
                    # Conta bancária default (primeira ativa)
                    cur.execute(
                        "SELECT id FROM fin_contas_bancarias WHERE business_id=%s LIMIT 1",
                        (args.target_business,),
                    )
                    row = cur.fetchone()
                    if row:
                        conta_default = int(row["id"])
            finally:
                tmp_con.close()
            print(f"[Transactions lookup] {len(transaction_lookup)} entries")
            print(f"[Contacts lookup] {len(contact_lookup)} entries")
            print(f"[Conta bancária default] id={conta_default}")
        else:
            print("[Lookups] dry-run · vazio (origem_id ficará NULL)")

        # Query FINANCEIRO com filtro
        where_parts = []
        params: list = []
        if args.start_date:
            where_parts.append("EMISSAO >= ?")
            params.append(args.start_date)
        if args.end_date:
            where_parts.append("EMISSAO <= ?")
            params.append(args.end_date + " 23:59:59")
        where_clause = ("WHERE " + " AND ".join(where_parts)) if where_parts else ""

        # SELECT adaptado
        select_parts = []
        for c in FINANCEIRO_COLS:
            if c in cols_existentes:
                select_parts.append(c)
            else:
                select_parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
        select_clause = ", ".join(select_parts)
        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        sql = f"SELECT {first_clause} {select_clause} FROM FINANCEIRO {where_clause} ORDER BY CODIGO"
        print(f"\n[Query FINANCEIRO] {sql[:200]}...")

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
            for row_tuple in cur:
                stats["lidos"] += 1
                fin = dict(zip(col_names, row_tuple))

                # Filter inactive
                ativo = (fin.get("ATIVO") or "S").upper()
                if ativo == "N":
                    stats["skipped"] += 1
                    continue

                # Filter valor=0
                if normalize_decimal(fin.get("VALOR")) == 0:
                    stats["skipped"] += 1
                    continue

                data, audit = map_financeiro_to_titulo(
                    fin, args.target_business, transaction_lookup, contact_lookup, args.created_by
                )

                # Update stats
                if data["tipo"] == "receber": stats["a_receber"] += 1
                else: stats["a_pagar"] += 1
                if data["status"] == "quitado": stats["quitados"] += 1
                elif data["status"] == "aberto": stats["abertos"] += 1
                elif data["status"] == "cancelado": stats["cancelados"] += 1
                elif data["status"] == "parcial": stats["parciais"] += 1
                if audit["is_write_off_candidate"]: stats["write_off_candidates"] += 1

                if len(audit_records) < 500: audit_records.append(audit)
                if len(sample_titulos) < 5 and args.target == "dry-run":
                    sample_titulos.append({k: str(v)[:80] for k, v in data.items() if k != "metadata"})

                if args.target == "dry-run":
                    stats["inserts_titulos"] += 1
                else:
                    assert con is not None
                    with con.cursor() as wcur:
                        wcur.execute(
                            "SELECT id FROM fin_titulos WHERE business_id=%s AND legacy_id=%s LIMIT 1",
                            (args.target_business, data["legacy_id"]),
                        )
                        existing = wcur.fetchone()
                        if existing:
                            titulo_id = int(existing["id"])
                            update_fields = {
                                k: v for k, v in data.items()
                                if k not in ("business_id", "legacy_id", "numero", "tipo", "origem", "created_by")
                            }
                            set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                            wcur.execute(
                                f"UPDATE fin_titulos SET {set_clause}, updated_at=NOW() WHERE id=%s",
                                (*update_fields.values(), titulo_id),
                            )
                            stats["updates_titulos"] += 1
                        else:
                            cols = list(data.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO fin_titulos ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(data.values()),
                            )
                            titulo_id = wcur.lastrowid
                            stats["inserts_titulos"] += 1

                        # Baixa (se pago)
                        if not args.skip_baixas:
                            baixa = map_baixa(fin, titulo_id, args.target_business, conta_default, args.created_by)
                            if baixa:
                                # Idempotência via idempotency_key
                                wcur.execute(
                                    "SELECT id FROM fin_titulo_baixas WHERE idempotency_key=%s LIMIT 1",
                                    (baixa["idempotency_key"],),
                                )
                                if not wcur.fetchone():
                                    cols_b = list(baixa.keys())
                                    placeholders_b = ", ".join(["%s"] * len(cols_b))
                                    wcur.execute(
                                        f"INSERT INTO fin_titulo_baixas ({', '.join(cols_b)}) "
                                        f"VALUES ({placeholders_b})",
                                        tuple(baixa.values()),
                                    )
                                    stats["inserts_baixas"] += 1
                                    if len(sample_baixas) < 3:
                                        sample_baixas.append({k: str(v)[:60] for k, v in baixa.items()})

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
            if con: con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    audit_path = out / f"audit-financeiro-biz{args.target_business}-{ts}.json"
    audit_path.write_text(
        json.dumps({
            "importer_version": IMPORTER_VERSION,
            "alias": args.alias,
            "business_id": args.target_business,
            "target": args.target,
            "stats": stats,
            "sample_titulos": sample_titulos,
            "sample_baixas": sample_baixas,
            "records_total": len(audit_records),
            "records_sample_first_500": audit_records[:500],
        }, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"\n[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  Lidos              : {stats['lidos']}")
    print(f"  Skipped            : {stats['skipped']}")
    print(f"  Titulos INSERT     : {stats['inserts_titulos']}")
    print(f"  Titulos UPDATE     : {stats['updates_titulos']}")
    print(f"  Baixas INSERT      : {stats['inserts_baixas']}")
    print(f"  A receber          : {stats['a_receber']}")
    print(f"  A pagar            : {stats['a_pagar']}")
    print(f"  Status quitado     : {stats['quitados']}")
    print(f"  Status aberto      : {stats['abertos']}")
    print(f"  Status cancelado   : {stats['cancelados']}")
    print(f"  Write-off candidatos: {stats['write_off_candidates']}")
    print(f"  Errors             : {stats['errors']}")
    print(f"[OK] FINANCEIRO concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
