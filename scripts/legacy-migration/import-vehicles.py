"""
US-OFICINA-002 — Importer EQUIPAMENTO_VEICULO Delphi → vehicles oimpresso.

Lê tabela EQUIPAMENTO_VEICULO do Firebird de um cliente Office Impresso e popula
`vehicles` em business alvo no oimpresso (Laravel/MySQL).

UPSERT idempotente via (business_id, legacy_id) — `legacy_id` preserva
`EQUIPAMENTO_VEICULO.CODIGO` Firebird. Re-rodar = no-op pra rows existentes,
update pra mudanças, insert pra novas.

Vehicle_type default 'cacamba_avulsa' (caso piloto Martinho); pra outras
verticais OficeImpresso (Vargas recapagem, oficina automotiva), passar
--vehicle-type explícito.

NÃO migra: legacy_source bridge separada — `legacy_id` direto na tabela
`vehicles` (campo já existe schema US-OFICINA-001 PR #556).

Multi-tenant Tier 0 (ADR 0093): impõe `business_id` em todo INSERT/UPDATE.

Uso:
    # Dry-run (gera SQL preview, não toca DB)
    python import-vehicles.py --alias MartinhoServidor --target-business 164

    # Local Laragon (Herd dev)
    python import-vehicles.py --alias MartinhoServidor --target-business 164 \\
                              --target local

    # Hostinger prod (perigoso — exige --confirm)
    python import-vehicles.py --alias MartinhoServidor --target-business 164 \\
                              --target prod --confirm

Refs:
    - US-OFICINA-002 (memory/requisitos/OficinaAuto/SPEC.md)
    - ADR 0137 — OficinaAuto qualificada
    - ADR 0093 — Multi-tenant Tier 0
    - memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md §9.4
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
DEFAULT_VEHICLE_TYPE = "cacamba_avulsa"
DEFAULT_CURRENT_STATUS = "disponivel"

# Placeholder pra rows sem placa preserva legacy_id (preserva auditoria).
# Wagner decide depois no app se completa manual ou deleta. NÃO atende
# constraint UNIQUE (plate em vehicles não é unique), só faz `plate` not-null.
PLACA_PLACEHOLDER_TEMPLATE = "S/N-{codigo}"


def query_veiculos_delphi(con) -> list[dict]:
    """Lê EQUIPAMENTO_VEICULO do Firebird. Sem filtro (Martinho usa todos)."""
    return query(
        con,
        """
        SELECT CODIGO, CHASSI, MOTOR, RENAVAN, ANO_MODELO, KM, PLACA,
               ANO_FABRICACAO, TIPO, ESPECIE, COMBUSTIVEL, CMOD,
               PLACA2, CHASSI2
        FROM EQUIPAMENTO_VEICULO
        ORDER BY CODIGO
        """,
    )


def normalize_str(raw) -> str | None:
    """Strip + lowercase empty → None. Preserva acentos."""
    if raw is None:
        return None
    s = str(raw).strip()
    return s or None


def normalize_int(raw) -> int | None:
    if raw is None or raw == "":
        return None
    try:
        return int(raw)
    except (ValueError, TypeError):
        return None


def map_veiculo_to_vehicle(
    veh: dict,
    business_id: int,
    vehicle_type: str,
) -> tuple[dict, dict]:
    """EQUIPAMENTO_VEICULO Delphi → linha em `vehicles`. Retorna (data, audit)."""
    legacy_id = str(veh["CODIGO"]).strip()
    placa = normalize_str(veh.get("PLACA"))
    has_placa = placa is not None

    data = {
        "business_id": business_id,
        "plate": placa or PLACA_PLACEHOLDER_TEMPLATE.format(codigo=legacy_id),
        "secondary_plate": normalize_str(veh.get("PLACA2")),
        "chassis": normalize_str(veh.get("CHASSI")),
        "secondary_chassis": normalize_str(veh.get("CHASSI2")),
        "manufacture_year": normalize_int(veh.get("ANO_FABRICACAO")),
        "model_year": normalize_int(veh.get("ANO_MODELO")),
        "renavam": normalize_str(veh.get("RENAVAN")),
        "vehicle_type": vehicle_type,
        "current_status": DEFAULT_CURRENT_STATUS,
        "engine": normalize_str(veh.get("MOTOR")),
        "mileage_at_entry": normalize_int(veh.get("KM")),
        "fuel_type": normalize_str(veh.get("COMBUSTIVEL")),
        "legacy_id": legacy_id,
    }

    # Notes — campo livre pra registro Delphi não mapeado direto.
    notes_parts = []
    tipo = normalize_str(veh.get("TIPO"))
    especie = normalize_str(veh.get("ESPECIE"))
    cmod = normalize_str(veh.get("CMOD"))
    if tipo:
        notes_parts.append(f"TIPO Delphi: {tipo}")
    if especie:
        notes_parts.append(f"ESPECIE: {especie}")
    if cmod:
        notes_parts.append(f"Modelo: {cmod}")
    if not has_placa:
        notes_parts.append(
            f"⚠️ Importado sem placa (Delphi CODIGO={legacy_id}). "
            "Completar cadastro no app."
        )
    if notes_parts:
        data["notes"] = " · ".join(notes_parts)

    audit = {
        "legacy_source": LEGACY_SOURCE,
        "legacy_id": legacy_id,
        "had_placa": has_placa,
        "raw_delphi": {
            k: (str(v).strip() if v is not None else None)
            for k, v in veh.items()
        },
        "imported_at_iso": datetime.utcnow().isoformat() + "Z",
        "importer_version": IMPORTER_VERSION,
    }

    return data, audit


def sql_value(v) -> str:
    """Escape Python value pra literal SQL (apenas pra dry-run preview)."""
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    return "'" + str(v).replace("'", "''") + "'"


def emit_upsert_sql(data: dict) -> str:
    """Gera INSERT pra dry-run preview.

    Real importer faz SELECT por (business_id, legacy_id) + UPDATE OU INSERT
    manualmente (sem depender de UNIQUE index — o schema US-OFICINA-001 tem
    apenas `index` em (business_id, legacy_id), não `unique`). Preview emite
    INSERT puro pra Wagner auditar valores antes de aplicar.
    """
    cols = list(data.keys())
    vals = ", ".join(sql_value(data[c]) for c in cols)
    return (
        f"INSERT INTO vehicles ({', '.join(cols)}, created_at, updated_at) "
        f"VALUES ({vals}, NOW(), NOW());"
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True, help="Alias Firebird HKCU (ex MartinhoServidor)")
    parser.add_argument("--target-business", type=int, required=True, help="business_id alvo no oimpresso")
    parser.add_argument(
        "--target",
        choices=["dry-run", "local", "prod"],
        default="dry-run",
        help="dry-run gera SQL preview · local/prod aplica DB",
    )
    parser.add_argument(
        "--vehicle-type",
        default=DEFAULT_VEHICLE_TYPE,
        help=f"Default vehicle_type (default: {DEFAULT_VEHICLE_TYPE})",
    )
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument(
        "--firebird-password",
        default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"),
    )
    parser.add_argument("--confirm", action="store_true", help="Obrigatório pra --target prod")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("❌ --target prod requer --confirm explícito (segurança)", file=sys.stderr)
        return 2

    print(f"🚀 Importer Vehicles v{IMPORTER_VERSION}")
    print(f"   Alias Firebird       : {args.alias}")
    print(f"   business_id alvo     : {args.target_business}")
    print(f"   vehicle_type default : {args.vehicle_type}")
    print(f"   Target MySQL         : {args.target}")
    if args.target in ("local", "prod"):
        print(f"   MySQL host           : {args.mysql_host}:{args.mysql_port}")
        print(f"   MySQL DB             : {args.mysql_database}")

    dry_run_lines: list[str] = [
        f"-- Generated by import-vehicles v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business={args.target_business}  alias={args.alias}",
        "",
        "-- Idempotência: real importer faz SELECT por (business_id, legacy_id) +",
        "-- UPDATE OU INSERT manual (schema US-OFICINA-001 tem `index` não `unique`).",
        "-- Este preview emite INSERT puro pra auditoria — re-run faria duplicata.",
        "",
    ]
    audit_records: list[dict] = []
    stats = {"inserts": 0, "updates": 0, "skipped": 0, "errors": 0, "no_placa": 0}

    print("\n🔌 Conectando Firebird...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        veiculos = query_veiculos_delphi(fb_con)
        print(f"   EQUIPAMENTO_VEICULO total: {len(veiculos)}")

        con = None
        if args.target in ("local", "prod"):
            if pymysql is None:
                print("❌ pymysql não instalado: pip install pymysql", file=sys.stderr)
                return 3
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )

        try:
            for i, veh in enumerate(veiculos, 1):
                data, audit = map_veiculo_to_vehicle(veh, args.target_business, args.vehicle_type)
                audit_records.append(audit)
                if not audit["had_placa"]:
                    stats["no_placa"] += 1

                print(
                    f"[{i:>3}/{len(veiculos)}] CODIGO={data['legacy_id']:>3} "
                    f"PLACA={data['plate']:<15} "
                    f"{'(placeholder)' if not audit['had_placa'] else ''}"
                )

                if args.target == "dry-run":
                    dry_run_lines.append(f"-- veiculo {data['legacy_id']} placa={data['plate']}")
                    dry_run_lines.append(emit_upsert_sql(data))
                    dry_run_lines.append("")
                    stats["inserts"] += 1
                else:
                    assert con is not None
                    data_filtered = {k: v for k, v in data.items() if v is not None}
                    with con.cursor() as cur:
                        cur.execute(
                            "SELECT id FROM vehicles WHERE business_id=%s AND legacy_id=%s LIMIT 1",
                            (args.target_business, data["legacy_id"]),
                        )
                        row = cur.fetchone()
                        if row:
                            update_fields = {
                                k: v for k, v in data_filtered.items()
                                if k not in ("business_id", "legacy_id")
                            }
                            set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                            cur.execute(
                                f"UPDATE vehicles SET {set_clause}, updated_at=NOW() WHERE id=%s",
                                (*update_fields.values(), row["id"]),
                            )
                            stats["updates"] += 1
                        else:
                            cols = list(data_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            cur.execute(
                                f"INSERT INTO vehicles ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(data_filtered.values()),
                            )
                            stats["inserts"] += 1

            if con:
                con.commit()
                print("\n✅ Commit MySQL OK")
        except Exception as e:
            if con:
                con.rollback()
                print("\n❌ Rollback MySQL", file=sys.stderr)
            stats["errors"] += 1
            print(f"Erro: {e!r}", file=sys.stderr)
            raise
        finally:
            if con:
                con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")

    if args.target == "dry-run":
        sql_path = out / f"dry-run-vehicles-biz{args.target_business}-{ts}.sql"
        sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
        print(f"\n💾 SQL preview salvo: {sql_path}")

    audit_path = out / f"audit-vehicles-biz{args.target_business}-{ts}.json"
    audit_path.write_text(
        json.dumps({
            "importer_version": IMPORTER_VERSION,
            "target": args.target,
            "business_id": args.target_business,
            "alias": args.alias,
            "vehicle_type_default": args.vehicle_type,
            "stats": stats,
            "records": audit_records,
        }, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    print(f"💾 Audit JSON salvo: {audit_path}")

    print("\n📊 Relatório:")
    print(f"   Total Firebird : {len(veiculos)}")
    print(f"   Inserts        : {stats['inserts']}")
    print(f"   Updates        : {stats['updates']}")
    print(f"   Sem placa      : {stats['no_placa']} (importados com placeholder S/N-CODIGO)")
    print(f"   Errors         : {stats['errors']}")
    print(f"✅ Vehicles concluído (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
