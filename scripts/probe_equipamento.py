"""Investiga tabela EQUIPAMENTO e seu JOIN com VENDA nos 4+1 bancos."""
import json
import sys
import firebird.driver as fb

BANKS = [
    ("01-wr2", "192.168.0.55:Banco"),
    ("02-vargas", "192.168.0.55:D:\\DadosClientes\\Vargas\\Dados\\BANCO.FDB"),
    ("03-extreme", "192.168.0.55:D:\\DadosClientes\\Extreme\\Dados\\BANCO.FDB"),
    ("04-gold", "192.168.0.55:D:\\DadosClientes\\Gold\\Dados\\BANCO.FDB"),
    ("05-martinho", "192.168.0.55:D:\\DadosClientes\\Martinho\\Dados\\BANCO.FDB"),
    ("05b-martinho-alt", "192.168.0.55:D:\\DadosClientes\\MartinhoCacamba\\BANCO.FDB"),
    ("05c-martinho-alt2", "192.168.0.55:D:\\DadosClientes\\MartinhoCacamba\\Dados\\BANCO.FDB"),
]


def list_equip_tables(cur):
    cur.execute(
        "SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
        "WHERE RDB$VIEW_BLR IS NULL AND RDB$SYSTEM_FLAG = 0 "
        "AND (RDB$RELATION_NAME LIKE '%EQUIPAMENTO%' OR RDB$RELATION_NAME LIKE '%VEICUL%') "
        "ORDER BY RDB$RELATION_NAME"
    )
    return [r[0] for r in cur.fetchall()]


def columns(cur, table):
    cur.execute(
        "SELECT TRIM(f.RDB$FIELD_NAME) "
        "FROM RDB$RELATION_FIELDS f "
        "WHERE f.RDB$RELATION_NAME = ? ORDER BY f.RDB$FIELD_POSITION",
        [table.upper()],
    )
    return [r[0] for r in cur.fetchall()]


def fk_in_venda(cur):
    """Procura colunas de FK em VENDA apontando pra EQUIPAMENTO/VEICULO."""
    cols = set(columns(cur, "VENDA"))
    return sorted([c for c in cols if "EQUIPAMENTO" in c or "VEICUL" in c])


def equipamento_usage(cur):
    """% de vendas com FK pra equipamento + campos PLACA/CHASSIS preenchidos via JOIN."""
    fks_in_venda = fk_in_venda(cur)
    equip_tables = list_equip_tables(cur)
    if not equip_tables:
        return {"equip_tables": [], "fks_in_venda": fks_in_venda}

    main_table = "EQUIPAMENTO" if "EQUIPAMENTO" in equip_tables else equip_tables[0]
    equip_cols = columns(cur, main_table)
    pii_cols = [c for c in equip_cols if any(
        k in c for k in ["PLACA", "CHASSIS", "CHASSI", "MARCA", "MODELO", "ANO", "RENAVAM"]
    )]

    out = {
        "equip_tables": equip_tables,
        "fks_in_venda": fks_in_venda,
        "main_table": main_table,
        "main_columns_pii": pii_cols,
        "fields_filled": {},
    }

    # Tenta ver % preenchimento dos campos auto na tabela EQUIPAMENTO em si
    try:
        cur.execute(f"SELECT COUNT(*) FROM {main_table}")
        total_eq = cur.fetchone()[0]
        out["total_equipamentos"] = int(total_eq)
        for c in pii_cols:
            cur.execute(f"SELECT COUNT(*) FROM {main_table} WHERE {c} IS NOT NULL AND TRIM({c}) <> ''")
            filled = cur.fetchone()[0]
            out["fields_filled"][c] = {
                "filled": int(filled),
                "pct": round(100 * filled / total_eq, 1) if total_eq else 0,
            }
    except Exception as e:
        out["error_main_table"] = str(e)[:80]

    # Tenta % de vendas com FK pra equipamento preenchida
    if fks_in_venda:
        fk = fks_in_venda[0]
        cur.execute(f"SELECT COUNT(*) FROM VENDA")
        total_v = cur.fetchone()[0]
        cur.execute(f"SELECT COUNT(*) FROM VENDA WHERE {fk} IS NOT NULL")
        with_eq = cur.fetchone()[0]
        out["venda_with_equipamento"] = {
            "fk_column": fk,
            "total_vendas": int(total_v),
            "with_fk": int(with_eq),
            "pct": round(100 * with_eq / total_v, 1) if total_v else 0,
        }

    return out


def main():
    results = {}
    for slug, alias in BANKS:
        try:
            con = fb.connect(alias, user="SYSDBA", password="masterkey")
            cur = con.cursor()
            results[slug] = equipamento_usage(cur)
            con.close()
            print(f"OK   {slug:20s}", file=sys.stderr)
        except Exception as e:
            results[slug] = {"error": str(e)[:80]}
            print(f"FAIL {slug:20s} {str(e)[:60]}", file=sys.stderr)

    print(json.dumps(results, indent=2, default=str, ensure_ascii=False))


if __name__ == "__main__":
    main()
