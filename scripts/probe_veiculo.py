"""Investiga EQUIPAMENTO_VEICULO (PLACA/CHASSIS) + FK real em VENDA."""
import json
import sys
import firebird.driver as fb

BANKS = [
    ("01-wr2", "192.168.0.55:Banco"),
    ("02-vargas", "192.168.0.55:D:\\DadosClientes\\Vargas\\Dados\\BANCO.FDB"),
    ("03-extreme", "192.168.0.55:D:\\DadosClientes\\Extreme\\Dados\\BANCO.FDB"),
    ("04-gold", "192.168.0.55:D:\\DadosClientes\\Gold\\Dados\\BANCO.FDB"),
    ("05-martinho", "192.168.0.55:D:\\DadosClientes\\MartinhoCacamba\\Dados\\BANCO.FDB"),
]


def cols(cur, table):
    cur.execute(
        "SELECT TRIM(f.RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS f "
        "WHERE f.RDB$RELATION_NAME = ? ORDER BY f.RDB$FIELD_POSITION",
        [table.upper()],
    )
    return [r[0] for r in cur.fetchall()]


def analyze(cur):
    out = {}

    # EQUIPAMENTO_VEICULO
    eqv_cols = cols(cur, "EQUIPAMENTO_VEICULO")
    out["equipamento_veiculo_columns"] = eqv_cols
    if eqv_cols:
        try:
            cur.execute("SELECT COUNT(*) FROM EQUIPAMENTO_VEICULO")
            total_v = cur.fetchone()[0]
            out["total_veiculos"] = int(total_v)
            pii_check = [c for c in eqv_cols if any(k in c for k in
                ["PLACA", "CHASSIS", "CHASSI", "MARCA", "MODELO", "ANO", "RENAVAM", "COR"])]
            out["veiculo_fields_filled"] = {}
            for c in pii_check[:15]:
                try:
                    cur.execute(f"SELECT COUNT(*) FROM EQUIPAMENTO_VEICULO WHERE {c} IS NOT NULL AND TRIM(CAST({c} AS VARCHAR(200))) <> ''")
                    filled = int(cur.fetchone()[0])
                    out["veiculo_fields_filled"][c] = {
                        "filled": filled,
                        "pct": round(100 * filled / total_v, 1) if total_v else 0,
                    }
                except Exception as e:
                    out["veiculo_fields_filled"][c] = {"error": str(e)[:50]}
        except Exception as e:
            out["err_veiculo"] = str(e)[:80]

    # EQUIPAMENTO master + colunas
    eq_cols = cols(cur, "EQUIPAMENTO")
    out["equipamento_columns_pii"] = [c for c in eq_cols if any(k in c for k in
        ["PLACA", "CHASSIS", "CHASSI", "MARCA", "MODELO", "ANO", "RENAVAM", "TIPO"])]

    # VENDA — procurar FK pra EQUIPAMENTO de verdade (codigo, id)
    venda_cols = set(cols(cur, "VENDA"))
    fk_candidates = [c for c in venda_cols if any(p in c for p in
        ["CODEQUIPAMENTO", "COD_EQUIPAMENTO", "ID_EQUIPAMENTO", "EQUIPAMENTO_ID",
         "EQUIPAMENTO_CODIGO", "CODVEICULO", "COD_VEICULO"])]
    # tambem busca exato "EQUIPAMENTO" como nome de coluna (Delphi as vezes usa)
    if "EQUIPAMENTO" in venda_cols:
        fk_candidates.append("EQUIPAMENTO")
    out["venda_fk_candidates"] = sorted(set(fk_candidates))

    # tenta % de vendas com FK preenchido (1o candidato)
    if fk_candidates:
        fk = sorted(set(fk_candidates))[0]
        try:
            cur.execute("SELECT COUNT(*) FROM VENDA")
            total_v_count = int(cur.fetchone()[0])
            cur.execute(f"SELECT COUNT(*) FROM VENDA WHERE {fk} IS NOT NULL")
            with_eq = int(cur.fetchone()[0])
            out["vendas_com_equipamento"] = {
                "fk": fk,
                "total": total_v_count,
                "with_fk": with_eq,
                "pct": round(100 * with_eq / total_v_count, 1) if total_v_count else 0,
            }
        except Exception as e:
            out["err_venda_fk"] = str(e)[:80]

    return out


def main():
    results = {}
    for slug, alias in BANKS:
        try:
            con = fb.connect(alias, user="SYSDBA", password="masterkey")
            cur = con.cursor()
            results[slug] = analyze(cur)
            con.close()
            print(f"OK   {slug:20s}", file=sys.stderr)
        except Exception as e:
            results[slug] = {"error": str(e)[:100]}
            print(f"FAIL {slug:20s} {str(e)[:80]}", file=sys.stderr)

    print(json.dumps(results, indent=2, default=str, ensure_ascii=False))


if __name__ == "__main__":
    main()
