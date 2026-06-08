"""
Fase 5b — Importer Python: EMPRESA Delphi WR Comercial → contacts UltimatePOS.

Migra as 4 entidades jurídicas/físicas (WR Comercial, Wagner PF, EL Tecnologia, WR2)
como contacts type='both' (cliente+fornecedor) na business alvo do oimpresso.

NÃO migra: CERTIFICADO digital (PKCS#12), CERTIFICADO_SENHA, NFE_NUMSERIE, NFCE_*_CSC,
WEB_SERVICE_SENHA, NFSE_SENHA, NFSE_WSCHAVEAUTORIZ, APP_SENHA — segredos.

Bridge: usa accounts_legacy_map pattern mas em tabela `contacts_legacy_map` se existir,
caso contrário UPSERT direto em `contacts` usando (business_id, cpf_cnpj) como chave.

Uso:
    python import-empresas.py --alias ServidorWR2 --target-business 1
    python import-empresas.py --alias ServidorWR2 --target-business 1 --target local
    python import-empresas.py --alias ServidorWR2 --target-business 1 --target prod --confirm
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime
from pathlib import Path

# Força UTF-8 stdout no Windows
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

# Campos sensíveis EMPRESA — NÃO migrar (LGPD + segredos bancários)
EMPRESA_SECRET_FIELDS = {
    "CERTIFICADO", "CERTIFICADO_SENHA", "TEM_CERTIFICADO",
    "WEB_SERVICE_SENHA", "NFSE_SENHA", "NFSE_WSCHAVEAUTORIZ",
    "NFCE_PRODUCAO_CSC", "NFCE_HOMOLOGACAO_CSC",
    "APP_SENHA", "NFE_NUMSERIE",
}


def query_empresas_delphi(con) -> list[dict]:
    """Lê EMPRESA do Firebird, mascarando segredos. Retorna lista de dicts.

    Filtra ATIVO='S' por default (mas todas as 4 EMPRESAS WR já são ativas).
    """
    rows = query(con, "SELECT * FROM EMPRESA WHERE ATIVO = 'S' ORDER BY CODIGO")
    cleaned = []
    for r in rows:
        clean = {k: v for k, v in r.items() if k not in EMPRESA_SECRET_FIELDS}
        cleaned.append(clean)
    return cleaned


def normalize_cpf_cnpj(raw: str | None) -> str | None:
    """Remove pontuação CPF/CNPJ. Retorna None se vazio."""
    if not raw:
        return None
    digits = re.sub(r"\D", "", raw)
    return digits or None


def map_empresa_to_contact(emp: dict, business_id: int) -> tuple[dict, dict]:
    """EMPRESA Delphi → linha em `contacts`. Retorna (data, metadata)."""
    legacy_id = str(emp["CODIGO"])
    cpf_cnpj = normalize_cpf_cnpj(emp.get("CNPJCPF"))
    razao = (emp.get("RAZAOSOCIAL") or "").strip()
    fantasia = (emp.get("FANTASIA") or "").strip()

    contact_data = {
        "business_id": business_id,
        "type": "both",  # cliente + fornecedor (entidade própria Wagner)
        "contact_type": "person" if emp.get("TIPO") == "F" else "business",
        "name": razao or fantasia or f"Legacy {legacy_id}",
        "supplier_business_name": fantasia or razao,
        "cpf_cnpj": cpf_cnpj,
        "tax_number": cpf_cnpj,  # legado preenche os dois
        "ie_rg": (emp.get("INSCIDENT") or emp.get("IEST") or None),
        "rua": (emp.get("ENDERECO") or None),
        "numero": (emp.get("NUMERO") or None),
        "bairro": (emp.get("BAIRRO") or None),
        "city": (emp.get("CIDADE") or None),
        "state": (emp.get("UF") or None),
        "country": (emp.get("PAIS") or "BRASIL"),
        "zip_code": (emp.get("CEP") or None),
        "mobile": (emp.get("FONE1") or None),
        "landline": (emp.get("FONE2") or None),
        "email": (emp.get("EMAIL") or None),
        "regime": (emp.get("REGIME") or None),
        "contact_status": "active",
    }

    metadata = {
        "delphi_legacy": {
            "codigo": emp.get("CODIGO"),
            "tipo": emp.get("TIPO"),
            "regime": emp.get("REGIME"),
            "crt": emp.get("CRT"),
            "cnae": emp.get("CNAE"),
            "im": emp.get("IM"),
            "iest": emp.get("IEST"),
            "emite_nfe": emp.get("EMITE_NFE"),
            "emite_nfce": emp.get("EMITE_NFCE"),
            "emite_nfse": emp.get("EMITE_NFSE"),
            "emite_sat": emp.get("EMITE_SAT"),
            "contador_nome": emp.get("CONTADOR_NOME"),
            "contador_cnpj": emp.get("CONTADOR_CNPJ"),
            "contador_email": emp.get("CONTADOR_EMAIL"),
            "modulo": emp.get("MODULO"),
            "dt_cadastro": str(emp.get("DT_CADASTRO")) if emp.get("DT_CADASTRO") else None,
            "dt_alteracao": str(emp.get("DT_ALTERACAO")) if emp.get("DT_ALTERACAO") else None,
        },
        "credenciais_warning": (
            "EMPRESA continha campos sensíveis (CERTIFICADO, CERTIFICADO_SENHA, "
            "NFCE_CSC, WEB_SERVICE_SENHA, NFSE_SENHA, APP_SENHA, NFE_NUMSERIE) — "
            "NÃO migrados. Decidir Vaultwarden integration por ADR."
        ),
        "import_meta": {
            "imported_at_iso": datetime.utcnow().isoformat() + "Z",
            "importer_version": IMPORTER_VERSION,
            "legacy_source": LEGACY_SOURCE,
            "legacy_id": legacy_id,
        },
    }

    return contact_data, metadata


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("❌ --target prod requer --confirm explícito (segurança)", file=sys.stderr)
        return 2

    print(f"🚀 Importer Empresas v{IMPORTER_VERSION}")
    print(f"   Alias Firebird       : {args.alias}")
    print(f"   business_id alvo     : {args.target_business}")
    print(f"   Target MySQL         : {args.target}")

    dry_run_lines: list[str] = [
        f"-- Generated by import-empresas v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}",
        "",
    ]
    stats = {"inserts": 0, "updates": 0, "errors": 0}

    # 1) Firebird
    print(f"\n🔌 Conectando Firebird...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        empresas = query_empresas_delphi(fb_con)
        print(f"   EMPRESAS ATIVAS='S': {len(empresas)}")

        # 2) MySQL writer
        con = None
        if args.target in ("local", "prod"):
            if pymysql is None:
                print("❌ pymysql não instalado", file=sys.stderr)
                return 3
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )

        try:
            for i, emp in enumerate(empresas, 1):
                data, metadata = map_empresa_to_contact(emp, args.target_business)
                cpf_cnpj = data.get("cpf_cnpj")
                print(f"\n[{i}/{len(empresas)}] EMPRESA {emp['CODIGO']} → {data['name'][:60]}")

                if not cpf_cnpj:
                    print(f"   ⚠️  Sem CNPJ/CPF — pulando (não dá pra dedupe)")
                    stats["errors"] += 1
                    continue

                # UPSERT via (business_id, cpf_cnpj) — chave natural dedupe
                if args.target == "dry-run":
                    cols = ", ".join(data.keys())
                    vals = ", ".join(
                        "NULL" if v is None else
                        f"'{str(v).replace(chr(39), chr(39)*2)}'"
                        for v in data.values()
                    )
                    dry_run_lines.append(
                        f"-- EMPRESA {emp['CODIGO']} → contact (UPSERT por cpf_cnpj={cpf_cnpj})"
                    )
                    dry_run_lines.append(
                        f"INSERT INTO contacts ({cols}, created_at, updated_at) "
                        f"VALUES ({vals}, NOW(), NOW()) "
                        f"ON DUPLICATE KEY UPDATE "
                        f"name=VALUES(name), supplier_business_name=VALUES(supplier_business_name), "
                        f"city=VALUES(city), state=VALUES(state), zip_code=VALUES(zip_code), "
                        f"mobile=VALUES(mobile), email=VALUES(email), updated_at=NOW();"
                    )
                    dry_run_lines.append(f"-- metadata: {json.dumps(metadata)[:200]}...")
                    dry_run_lines.append("")
                    stats["inserts"] += 1
                else:
                    assert con is not None
                    # Filtra Nones — MySQL contacts tem várias colunas NOT NULL sem default (mobile, etc)
                    # deixar None viraria NULL e quebraria constraint. None → MySQL usa o default da coluna.
                    data_filtered = {k: v for k, v in data.items() if v is not None}
                    with con.cursor() as cur:
                        cur.execute(
                            "SELECT id FROM contacts WHERE business_id=%s AND cpf_cnpj=%s LIMIT 1",
                            (args.target_business, cpf_cnpj),
                        )
                        row = cur.fetchone()
                        if row:
                            update_fields = {
                                k: v for k, v in data_filtered.items()
                                if k not in ("business_id", "type", "contact_type")
                            }
                            set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                            cur.execute(
                                f"UPDATE contacts SET {set_clause}, updated_at=NOW() WHERE id=%s",
                                (*update_fields.values(), row["id"]),
                            )
                            stats["updates"] += 1
                            print(f"   ✏️  UPDATED contact id={row['id']}")
                        else:
                            cols = list(data_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            cur.execute(
                                f"INSERT INTO contacts ({', '.join(cols)}, created_by, created_at, updated_at) "
                                f"VALUES ({placeholders}, %s, NOW(), NOW())",
                                (*data_filtered.values(), args.created_by),
                            )
                            stats["inserts"] += 1
                            print(f"   ✅ INSERTED contact id={cur.lastrowid}")

            if con:
                con.commit()
        except Exception as e:
            if con:
                con.rollback()
            stats["errors"] += 1
            print(f"❌ Erro: {e!r}", file=sys.stderr)
            raise
        finally:
            if con:
                con.close()

        # Salva dry-run SQL
        if args.target == "dry-run":
            out = Path(args.output_dir)
            out.mkdir(parents=True, exist_ok=True)
            ts = datetime.now().strftime("%Y%m%d-%H%M%S")
            path = out / f"dry-run-empresas-{ts}.sql"
            path.write_text("\n".join(dry_run_lines), encoding="utf-8")
            print(f"\n💾 SQL salvo: {path}")

    print(f"\n📊 Relatório:")
    print(f"   Inserts: {stats['inserts']}  Updates: {stats['updates']}  Erros: {stats['errors']}")
    print(f"✅ Empresas concluído (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
