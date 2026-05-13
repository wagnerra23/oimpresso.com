"""
Importer Python — VENDA_PRODUTO (Delphi WR Comercial) → transaction_sell_lines (UltimatePOS).

Migra as LINHAS de venda do legacy Firebird pro oimpresso. Roda DEPOIS de:
  1. import-empresas/contacts (PR #803 base) — bridge contacts.legacy_id
  2. importer vendas (transactions) — bridge transactions.ref_no = CODVENDA Delphi
  3. (opcional, futuro) products importer — bridge products.legacy_id = CODPRODUTO Delphi

Fonte canônica de TOTAL pelo legacy (descoberto em Controller_Venda.pas Horse REST):
  vp.TOTAL_RELATORIO  ← total real da linha (NÃO usar VENDA.TOTAL)

Schema adapter (RDB$RELATION_FIELDS):
  Martinho v1404 (361 cols) vs canônica v1474 — colunas detectadas em runtime.
  Mapeia só o set canônico mínimo p/ transaction_sell_lines:
    CODIGO, CODVENDA, CODPRODUTO, QTDE, VALOR_UNITARIO, TOTAL_RELATORIO,
    DESCONTO, ACRESCIMO, IPI_VALOR, ICMS_VALOR.

FK lookups (multi-tenant Tier 0 ADR 0093 — TODOS escopados por business_id):
  - transaction_id ← (business_id, ref_no=CODVENDA)  em `transactions`
  - product_id     ← (business_id, legacy_id=CODPRODUTO) em `products` (nullable se ausente)
  - variation_id   ← default_variation_id da products.id resolvida (NOT NULL no schema)

Idempotência: chave natural composta legacy = "{CODVENDA}_{CODIGO}"
  Persistida em NULL safe via SELECT (business_id, transaction_id, line_order).
  line_order = posição estável (CODIGO Firebird ascending dentro da venda).

3 modes:
  --target dry-run  → escreve INSERTs em output/dry-run-venda-produto-*.sql
  --target local    → escreve em MySQL Laragon local (smoke)
  --target prod     → escreve em Hostinger (exige --confirm)

PII redaction: produto_descricao, observação cliente truncados em JSON audit
(LGPD ADR 0093 — não vazar nomes/CPF de clientes legados pelo log).

Uso:
  python import-venda-produto.py --alias ServidorWR2 --target-business 164 --target dry-run
  python import-venda-produto.py --alias ServidorWR2 --target-business 164 --target local
  python import-venda-produto.py --alias ServidorWR2 --target-business 164 --target prod --confirm
  python import-venda-produto.py --alias ServidorWR2 --target-business 164 --target dry-run \\
      --start-date 2024-01-01 --end-date 2024-12-31

Refs:
  - D:\\Programas\\WR Comercial\\app\\Controller\\Controller_Venda.pas (fonte SQL TOTAL_RELATORIO)
  - scripts/legacy-migration/importer-martinho.py (pattern base)
  - scripts/legacy-migration/import-empresas.py (pattern dry-run SQL out)
  - database/migrations/2017_11_20_063603_create_transaction_sell_lines.php
  - ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL)
"""
from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import datetime
from decimal import Decimal
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))

try:
    from dotenv import load_dotenv
    load_dotenv()
    # Carrega .env.martinho se presente (segue pattern importer-martinho.py)
    load_dotenv(Path(__file__).parent / ".env.martinho")
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
ENTITY = "venda_produto"
BATCH_SIZE = 1000

# Set canônico mínimo p/ transaction_sell_lines (resto vai em metadata JSON).
# Cobre Martinho v1404 + canônica v1474 (intersection). Adapter omite ausentes.
VP_CANONICAL_FIELDS = [
    "CODIGO", "CODVENDA", "CODPRODUTO",
    "QTDE", "VALOR_UNITARIO", "TOTAL_RELATORIO",
    "DESCONTO", "ACRESCIMO",
    "IPI_VALOR", "ICMS_VALOR",
    # Opcionais (presentes em algumas versões):
    "DESCRICAO", "UNIDADE", "CFOP",
]


def now_str() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def trim(v) -> str | None:
    if v is None:
        return None
    s = str(v).strip()
    return s if s else None


def to_dec(v) -> float | None:
    if v is None:
        return None
    try:
        return float(v)
    except (ValueError, TypeError, ArithmeticError):
        return None


def truncate(v, max_len: int = 80) -> str | None:
    """Trunca string p/ logs/audit JSON sem vazar PII excessiva."""
    s = trim(v)
    if s is None:
        return None
    return (s[: max_len - 1] + "…") if len(s) > max_len else s


def fb_cols(con, table: str) -> list[str]:
    """Lista colunas reais da tabela Firebird (adapter por versão)."""
    rows = query(
        con,
        "SELECT TRIM(rf.RDB$FIELD_NAME) AS C FROM RDB$RELATION_FIELDS rf "
        "WHERE rf.RDB$RELATION_NAME = ? ORDER BY rf.RDB$FIELD_POSITION",
        (table,),
    )
    return [r.get("C") or list(r.values())[0] for r in rows]


def detect_available(con, table: str, wanted: list[str]) -> list[str]:
    """Retorna interseção de `wanted` com cols reais da `table`."""
    real = set(fb_cols(con, table))
    return [c for c in wanted if c in real]


def build_select_sql(
    available: list[str],
    start_date: str | None,
    end_date: str | None,
    limit: int | None,
) -> tuple[str, list]:
    """Monta SELECT vp.* JOIN venda v com filtro DT_EMISSAO opcional.

    Refs Controller_Venda.pas:
        SELECT vp.total_relatorio AS total, v.dt_emissao, c.descricao
        FROM venda_produto vp
        LEFT JOIN venda v ON v.codigo = vp.codvenda
        LEFT JOIN produto p ON p.codigo = vp.codproduto
        LEFT JOIN produto_categoria c ON p.codproduto_categoria = c.codigo
    """
    cols_qualified = ", ".join(f"vp.{c}" for c in available)
    limit_clause = f"FIRST {limit} " if limit else ""

    sql = (
        f"SELECT {limit_clause}{cols_qualified}, "
        f"v.DT_EMISSAO AS V_DT_EMISSAO, "
        f"TRIM(v.SITUACAO) AS V_SITUACAO "
        f"FROM VENDA_PRODUTO vp "
        f"LEFT JOIN VENDA v ON v.CODIGO = vp.CODVENDA "
    )
    where = []
    params: list = []
    if start_date:
        where.append("v.DT_EMISSAO >= ?")
        params.append(start_date)
    if end_date:
        where.append("v.DT_EMISSAO <= ?")
        params.append(end_date)
    if where:
        sql += "WHERE " + " AND ".join(where) + " "
    sql += "ORDER BY vp.CODVENDA, vp.CODIGO"
    return sql, params


def map_vp_to_line(
    vp: dict,
    transaction_id: int,
    product_id: int | None,
    variation_id: int | None,
    line_order: int,
) -> tuple[dict, dict]:
    """VENDA_PRODUTO → linha em transaction_sell_lines. Retorna (data, metadata)."""
    qtde = to_dec(vp.get("QTDE")) or 0.0
    valor_unit = to_dec(vp.get("VALOR_UNITARIO")) or 0.0
    total_rel = to_dec(vp.get("TOTAL_RELATORIO"))
    desconto = to_dec(vp.get("DESCONTO")) or 0.0
    acrescimo = to_dec(vp.get("ACRESCIMO")) or 0.0
    ipi = to_dec(vp.get("IPI_VALOR")) or 0.0
    icms = to_dec(vp.get("ICMS_VALOR")) or 0.0

    # unit_price oimpresso = valor unitário EXCLUDING tax (best-effort)
    # unit_price_inc_tax  = preço unitário com tax incluso
    # Estratégia: VALOR_UNITARIO geralmente já vem c/ ICMS no Delphi WR.
    # Fallback: se TOTAL_RELATORIO presente e QTDE>0, derivar unit_price_inc_tax.
    unit_price_inc_tax = valor_unit
    if total_rel is not None and qtde > 0:
        unit_price_inc_tax = round(total_rel / qtde, 4)
    # Aproximação: unit_price (sem tax) = inc_tax - (icms/qtde)
    item_icms_per_unit = (icms / qtde) if qtde > 0 else 0.0
    unit_price = max(0.0, round(unit_price_inc_tax - item_icms_per_unit, 4))
    item_tax = round(item_icms_per_unit + (ipi / qtde if qtde > 0 else 0.0), 4)

    data = {
        "transaction_id": transaction_id,
        "product_id": product_id,  # nullable temporariamente
        "variation_id": variation_id,  # nullable temporariamente
        "quantity": qtde,
        "unit_price": unit_price,
        "unit_price_inc_tax": unit_price_inc_tax,
        "item_tax": item_tax,
        "discount_amount": desconto if desconto > 0 else None,
        "discount_type": "fixed" if desconto > 0 else None,
        "line_discount_amount": desconto if desconto > 0 else None,
        "line_discount_type": "fixed" if desconto > 0 else None,
    }

    # Metadata JSON pra rastreabilidade (line_order + composto legacy id).
    metadata = {
        "legacy_source": LEGACY_SOURCE,
        "entity": ENTITY,
        "codvenda": vp.get("CODVENDA"),
        "codigo": vp.get("CODIGO"),
        "legacy_composite_id": f"{vp.get('CODVENDA')}_{vp.get('CODIGO')}",
        "line_order": line_order,
        "codproduto": vp.get("CODPRODUTO"),
        "produto_descricao": truncate(vp.get("DESCRICAO"), 80),  # PII redacted
        "unidade": trim(vp.get("UNIDADE")),
        "cfop": trim(vp.get("CFOP")),
        "total_relatorio_origem": total_rel,
        "valor_unitario_origem": valor_unit,
        "ipi_valor_origem": ipi,
        "icms_valor_origem": icms,
        "acrescimo_origem": acrescimo,
        "venda_situacao": trim(vp.get("V_SITUACAO")),
        "venda_dt_emissao": str(vp.get("V_DT_EMISSAO")) if vp.get("V_DT_EMISSAO") else None,
        "import_meta": {
            "imported_at_iso": datetime.utcnow().isoformat() + "Z",
            "importer_version": IMPORTER_VERSION,
        },
    }
    return data, metadata


def load_transaction_map(cur, business_id: int) -> dict[str, int]:
    """Mapa ref_no → transactions.id pra business alvo.

    transactions.ref_no já populado pelo importer vendas (PR #812 plano).
    """
    cur.execute(
        "SELECT id, ref_no FROM transactions "
        "WHERE business_id=%s AND type='sell' AND ref_no IS NOT NULL",
        (business_id,),
    )
    return {row["ref_no"]: row["id"] for row in cur.fetchall()}


def load_product_map(cur, business_id: int) -> dict[str, tuple[int, int | None]]:
    """Mapa legacy_id → (products.id, default_variation_id).

    Retorna {} se products.legacy_id não existe ainda (fase futura).
    """
    try:
        cur.execute(
            "SELECT p.id, p.legacy_id, "
            "  (SELECT v.id FROM variations v "
            "   WHERE v.product_id=p.id ORDER BY v.id LIMIT 1) AS variation_id "
            "FROM products p "
            "WHERE p.business_id=%s AND p.legacy_id IS NOT NULL",
            (business_id,),
        )
        return {row["legacy_id"]: (row["id"], row["variation_id"]) for row in cur.fetchall()}
    except pymysql.Error as e:
        # products.legacy_id pode não existir ainda — degrade gracioso
        print(f"   ⚠️  products.legacy_id indisponível ({e.args[0]}) — product_id ficará NULL")
        return {}


def load_existing_lines(cur, business_id: int, transaction_ids: list[int]) -> set:
    """Idempotência: set de (transaction_id, line_order) já presentes.

    SELECT escopado a transaction_ids da business — multi-tenant safe.
    """
    if not transaction_ids:
        return set()
    placeholders = ",".join(["%s"] * len(transaction_ids))
    cur.execute(
        f"SELECT tsl.transaction_id, "
        f"       JSON_EXTRACT(t.additional_notes, '$.line_orders') AS lo "
        f"FROM transaction_sell_lines tsl "
        f"INNER JOIN transactions t ON t.id=tsl.transaction_id "
        f"WHERE t.business_id=%s AND tsl.transaction_id IN ({placeholders})",
        [business_id] + transaction_ids,
    )
    # Use SELECT mais simples: contagem por transaction_id como sinal
    cur.execute(
        f"SELECT transaction_id, COUNT(*) AS n FROM transaction_sell_lines "
        f"WHERE transaction_id IN ({placeholders}) GROUP BY transaction_id",
        transaction_ids,
    )
    return {row["transaction_id"]: row["n"] for row in cur.fetchall()}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True, help="Alias Firebird (.env)")
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--start-date", default=None, help="Filtro VENDA.DT_EMISSAO >= YYYY-MM-DD")
    parser.add_argument("--end-date", default=None, help="Filtro VENDA.DT_EMISSAO <= YYYY-MM-DD")
    parser.add_argument("--limit", type=int, default=None, help="Limite linhas (smoke)")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--confirm", action="store_true", help="Obrigatório p/ --target prod")
    parser.add_argument("--output-dir", default="output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("❌ --target prod requer --confirm explícito (segurança ADR 0093)", file=sys.stderr)
        return 2

    print(f"🚀 Importer VENDA_PRODUTO v{IMPORTER_VERSION}")
    print(f"   Alias Firebird       : {args.alias}")
    print(f"   business_id alvo     : {args.target_business}")
    print(f"   Target MySQL         : {args.target}")
    print(f"   Filtro DT_EMISSAO    : {args.start_date or '—'} → {args.end_date or '—'}")
    if args.limit:
        print(f"   --limit              : {args.limit} (smoke mode)")

    stats = {
        "fb_rows": 0,
        "inserts": 0,
        "skipped_no_transaction": 0,
        "skipped_no_product": 0,
        "skipped_existing": 0,
        "errors": 0,
    }

    dry_run_lines: list[str] = [
        f"-- Generated by import-venda-produto v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business_id: {args.target_business}",
        f"-- DT_EMISSAO filter: {args.start_date or '—'} → {args.end_date or '—'}",
        "",
    ]

    # 1) Firebird connect + schema adapter
    print(f"\n🔌 Conectando Firebird...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        available = detect_available(fb_con, "VENDA_PRODUTO", VP_CANONICAL_FIELDS)
        missing = sorted(set(VP_CANONICAL_FIELDS) - set(available))
        print(f"   Cols VENDA_PRODUTO detectadas: {len(available)} / {len(VP_CANONICAL_FIELDS)}")
        if missing:
            print(f"   ⚠️  Cols canônicas AUSENTES (drift versão): {', '.join(missing)}")

        # Validações mínimas (sem essas, importer não tem como mapear)
        required = {"CODIGO", "CODVENDA", "CODPRODUTO", "QTDE"}
        if not required.issubset(set(available)):
            faltantes = required - set(available)
            print(f"❌ Cols obrigatórias ausentes em VENDA_PRODUTO: {faltantes}", file=sys.stderr)
            return 3
        if "TOTAL_RELATORIO" not in available:
            print("   ⚠️  TOTAL_RELATORIO ausente — fallback p/ VALOR_UNITARIO*QTDE")

        sql, params = build_select_sql(available, args.start_date, args.end_date, args.limit)
        print(f"\n📥 Query Firebird (preview): {sql[:200]}...")
        rows = query(fb_con, sql, params)
        stats["fb_rows"] = len(rows)
        print(f"   Linhas VENDA_PRODUTO lidas: {stats['fb_rows']}")

        if not rows:
            print("⚠️  Nenhuma linha — abortando")
            return 0

        # 2) MySQL writer setup
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

        # 3) Carregar mapas FK (multi-tenant Tier 0 — escopo business_id sempre)
        trans_map: dict[str, int] = {}
        prod_map: dict[str, tuple[int, int | None]] = {}
        existing_counts: dict[int, int] = {}

        if con is not None:
            with con.cursor() as cur:
                trans_map = load_transaction_map(cur, args.target_business)
                prod_map = load_product_map(cur, args.target_business)
                print(f"   Mapa transactions (ref_no): {len(trans_map)} entradas")
                print(f"   Mapa products (legacy_id):  {len(prod_map)} entradas")
                # Pre-load contagens existentes pra dedupe
                if trans_map:
                    tids = list(trans_map.values())
                    cur.execute(
                        f"SELECT transaction_id, COUNT(*) AS n FROM transaction_sell_lines "
                        f"WHERE transaction_id IN ({','.join(['%s']*len(tids))}) "
                        f"GROUP BY transaction_id",
                        tids,
                    )
                    existing_counts = {row["transaction_id"]: row["n"] for row in cur.fetchall()}
                    already = sum(existing_counts.values())
                    print(f"   Lines já presentes pra business: {already}")

        # Pré-requisito Tier 0 absoluto: sem transactions, nada a fazer
        if args.target in ("local", "prod") and not trans_map:
            print(
                f"\n❌ Sem transactions populated pra business_id={args.target_business}. "
                "Rodar importer-vendas ANTES (transaction_id é NOT NULL).",
                file=sys.stderr,
            )
            return 4

        # 4) Loop principal
        print(f"\n⚙️  Processando {len(rows)} linhas (batch={BATCH_SIZE})...")
        line_order_per_trans: dict[int, int] = {}
        insert_batch: list[tuple] = []
        audit_records: list[dict] = []

        try:
            for idx, vp in enumerate(rows, start=1):
                codvenda = str(vp.get("CODVENDA") or "").strip()
                codigo = str(vp.get("CODIGO") or "").strip()
                codproduto = str(vp.get("CODPRODUTO") or "").strip() if vp.get("CODPRODUTO") else None

                if not codvenda or not codigo:
                    stats["errors"] += 1
                    continue

                # FK lookup transaction_id (NOT NULL — skip se ausente)
                trans_id = trans_map.get(codvenda) if trans_map else None
                if args.target in ("local", "prod") and trans_id is None:
                    stats["skipped_no_transaction"] += 1
                    continue

                # FK lookup product_id (nullable — degrade gracioso)
                prod_tuple = prod_map.get(codproduto) if (codproduto and prod_map) else None
                product_id = prod_tuple[0] if prod_tuple else None
                variation_id = prod_tuple[1] if prod_tuple else None
                if product_id is None:
                    stats["skipped_no_product"] += 1
                    # Em dry-run, mantém pra ver shape. Em local/prod, schema requer NOT NULL
                    # em product_id+variation_id (migration 2017 original) → skip.
                    if args.target in ("local", "prod"):
                        continue

                # line_order estável (incremental por transação)
                line_order_per_trans.setdefault(trans_id or 0, 0)
                line_order_per_trans[trans_id or 0] += 1
                line_order = line_order_per_trans[trans_id or 0]

                # Dedupe: se essa transaction já tem N lines >= line_order, pula
                # (assume re-run com mesma ordem CODIGO ascending — idempotente)
                if existing_counts.get(trans_id, 0) >= line_order:
                    stats["skipped_existing"] += 1
                    continue

                data, metadata = map_vp_to_line(
                    vp, trans_id or 0, product_id, variation_id, line_order
                )

                # Defaults p/ NOT NULL cols sem default no schema
                data["quantity"] = data["quantity"] or 0
                data["item_tax"] = data["item_tax"] or 0

                if args.target == "dry-run":
                    cols = [k for k, v in data.items() if v is not None]
                    vals = [data[k] for k in cols]
                    vals_sql = ", ".join(
                        f"'{str(v).replace(chr(39), chr(39)*2)}'" if isinstance(v, str)
                        else "NULL" if v is None
                        else str(v)
                        for v in vals
                    )
                    dry_run_lines.append(
                        f"-- VP {codvenda}_{codigo} → transaction_id={trans_id or 'NULL'} "
                        f"product_id={product_id or 'NULL'} line_order={line_order}"
                    )
                    dry_run_lines.append(
                        f"INSERT INTO transaction_sell_lines ({', '.join(cols)}, created_at, updated_at) "
                        f"VALUES ({vals_sql}, NOW(), NOW());"
                    )
                    # Audit JSON record (PII redacted via truncate em metadata)
                    audit_records.append({
                        "legacy_id": metadata["legacy_composite_id"],
                        "transaction_id": trans_id,
                        "product_id": product_id,
                        "line_order": line_order,
                        "metadata": metadata,
                    })
                    stats["inserts"] += 1
                    # Sample de 5 INSERTs visíveis no stdout
                    if stats["inserts"] <= 5:
                        print(f"   [sample {stats['inserts']}] {dry_run_lines[-1][:140]}...")
                else:
                    assert con is not None
                    # Filtra None pra deixar MySQL aplicar defaults onde aplicável
                    data_filtered = {k: v for k, v in data.items() if v is not None}
                    cols = list(data_filtered.keys())
                    placeholders = ", ".join(["%s"] * len(cols))
                    insert_batch.append(
                        (cols, list(data_filtered.values()))
                    )
                    stats["inserts"] += 1

                # Batch flush a cada BATCH_SIZE
                if args.target in ("local", "prod") and len(insert_batch) >= BATCH_SIZE:
                    _flush_batch(con, insert_batch)
                    insert_batch.clear()
                    con.commit()
                    print(
                        f"   [progress] {idx}/{len(rows)} "
                        f"inseridos={stats['inserts']} "
                        f"skip-trans={stats['skipped_no_transaction']} "
                        f"skip-prod={stats['skipped_no_product']} "
                        f"skip-exist={stats['skipped_existing']}",
                        flush=True,
                    )
                elif idx % BATCH_SIZE == 0:
                    print(
                        f"   [progress] {idx}/{len(rows)} processados "
                        f"(inserts={stats['inserts']} "
                        f"skip-trans={stats['skipped_no_transaction']} "
                        f"skip-prod={stats['skipped_no_product']})",
                        flush=True,
                    )

            # Flush final
            if args.target in ("local", "prod") and insert_batch:
                _flush_batch(con, insert_batch)
                insert_batch.clear()
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

        # 5) Persist dry-run SQL + audit JSON
        if args.target == "dry-run":
            out = Path(args.output_dir)
            out.mkdir(parents=True, exist_ok=True)
            ts = datetime.now().strftime("%Y%m%d-%H%M%S")
            sql_path = out / f"dry-run-venda-produto-{ts}.sql"
            sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
            audit_path = out / f"dry-run-venda-produto-{ts}.audit.json"
            audit_path.write_text(
                json.dumps(audit_records[:200], ensure_ascii=False, indent=2, default=str),
                encoding="utf-8",
            )
            print(f"\n💾 SQL salvo:   {sql_path}")
            print(f"💾 Audit salvo: {audit_path} (top 200 records, PII redacted)")

    print(f"\n📊 Relatório:")
    print(f"   FB rows lidas       : {stats['fb_rows']}")
    print(f"   Inserts             : {stats['inserts']}")
    print(f"   Skip (no trans)     : {stats['skipped_no_transaction']}")
    print(f"   Skip (no product)   : {stats['skipped_no_product']}")
    print(f"   Skip (existing)     : {stats['skipped_existing']}")
    print(f"   Erros               : {stats['errors']}")
    print(f"\n✅ Concluído (v{IMPORTER_VERSION}, target={args.target})")
    return 0


def _flush_batch(con, batch: list[tuple]) -> None:
    """Executemany pra batch de (cols, vals)."""
    if not batch:
        return
    # Agrupa por shape (mesmo set de cols) — INSERT executemany exige uniformidade
    by_shape: dict[tuple, list[list]] = {}
    for cols, vals in batch:
        by_shape.setdefault(tuple(cols), []).append(vals)
    with con.cursor() as cur:
        for cols_tuple, vals_list in by_shape.items():
            cols = list(cols_tuple)
            placeholders = ", ".join(["%s"] * len(cols))
            cur.executemany(
                f"INSERT INTO transaction_sell_lines "
                f"({', '.join(cols)}, created_at, updated_at) "
                f"VALUES ({placeholders}, NOW(), NOW())",
                vals_list,
            )


if __name__ == "__main__":
    sys.exit(main())
