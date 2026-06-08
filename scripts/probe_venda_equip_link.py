"""Descobre como VENDA se liga a EQUIPAMENTO (tabela intermediaria ou FK inverso)."""
import json
import sys
import firebird.driver as fb

BANKS = [
    ("02-vargas", "192.168.0.55:D:\\DadosClientes\\Vargas\\Dados\\BANCO.FDB"),
    ("05-martinho", "192.168.0.55:D:\\DadosClientes\\MartinhoCacamba\\Dados\\BANCO.FDB"),
]


def go(cur):
    out = {}

    # 1. Tabelas com nome contendo VENDA e EQUIPAMENTO
    cur.execute(
        "SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
        "WHERE RDB$VIEW_BLR IS NULL AND RDB$SYSTEM_FLAG = 0 "
        "AND RDB$RELATION_NAME LIKE '%VENDA%' "
        "ORDER BY RDB$RELATION_NAME"
    )
    out["tabelas_venda"] = [r[0] for r in cur.fetchall()]

    # 2. EQUIPAMENTO tem CODVENDA?
    cur.execute(
        "SELECT TRIM(RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS "
        "WHERE RDB$RELATION_NAME = 'EQUIPAMENTO' "
        "AND (RDB$FIELD_NAME LIKE '%VENDA%' OR RDB$FIELD_NAME LIKE '%PESSOA%' OR RDB$FIELD_NAME LIKE '%CLIENTE%')"
    )
    out["equipamento_fks"] = [r[0] for r in cur.fetchall()]

    # 3. EQUIPAMENTO sample — primeiras 3 colunas relevantes
    try:
        cur.execute(
            "SELECT FIRST 3 e.CODIGO, e.PESSOA_CLIENTE_CODIGO, v.PLACA, v.CHASSI, v.PLACA2, v.CHASSI2 "
            "FROM EQUIPAMENTO e "
            "JOIN EQUIPAMENTO_VEICULO v ON v.CODIGO = e.CODIGO "
            "WHERE v.PLACA IS NOT NULL"
        )
        sample = cur.fetchall()
        out["sample_eq_veiculo"] = [list(r) for r in sample]
    except Exception as e:
        out["err_sample"] = str(e)[:80]

    # 4. VENDA tem CODPESSOA? E quantas vendas tem cliente com EQUIPAMENTO?
    try:
        cur.execute(
            "SELECT COUNT(DISTINCT v.CODIGO) "
            "FROM VENDA v "
            "JOIN EQUIPAMENTO e ON e.PESSOA_CLIENTE_CODIGO = v.CODPESSOA "
            "JOIN EQUIPAMENTO_VEICULO ev ON ev.CODIGO = e.CODIGO "
            "WHERE ev.PLACA IS NOT NULL AND TRIM(ev.PLACA) <> ''"
        )
        vendas_com_veic = int(cur.fetchone()[0])
        cur.execute("SELECT COUNT(*) FROM VENDA")
        total_vendas = int(cur.fetchone()[0])
        out["vendas_para_clientes_com_veiculo"] = {
            "total_vendas": total_vendas,
            "vendas_para_donos_de_placa": vendas_com_veic,
            "pct": round(100 * vendas_com_veic / total_vendas, 1) if total_vendas else 0,
        }
    except Exception as e:
        out["err_join"] = str(e)[:120]

    # 5. VENDA tem alguma coluna que aponta direto pra equipamento (qualquer nome)?
    cur.execute(
        "SELECT TRIM(RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS "
        "WHERE RDB$RELATION_NAME = 'VENDA' ORDER BY RDB$FIELD_POSITION"
    )
    all_v_cols = [r[0] for r in cur.fetchall()]
    out["venda_cols_que_referem_equip"] = [c for c in all_v_cols if "EQUIPAMENTO" in c]
    out["venda_cols_total"] = len(all_v_cols)

    return out


def main():
    results = {}
    for slug, alias in BANKS:
        try:
            con = fb.connect(alias, user="SYSDBA", password="masterkey")
            cur = con.cursor()
            results[slug] = go(cur)
            con.close()
        except Exception as e:
            results[slug] = {"error": str(e)[:120]}
    print(json.dumps(results, indent=2, default=str, ensure_ascii=False))


if __name__ == "__main__":
    main()
