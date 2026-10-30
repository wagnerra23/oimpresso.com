"""
Extrai do Firebird (SÓ LEITURA) a tributação que o Delphi aplica de fato e gera o CSV aceito por
`php artisan nfe:importar-regras-csv` (ImportRegrasCsvService, colunas opcionais crt/reducao_base/mva).

Por que não a tabela REGRA_TRIBUTARIA: medido no Sabor Brasil (VERSAO_BANCO 1514, 2026-10-29), as 22
linhas dela são o modelo de fábrica — DT_ALTERACAO nula nas 22, 0 linhas em REGRA_TRIBUTARIA_IMPOSTO,
0 em PRODUTO_REGRA_TRIBUTARIA e 0 em GRUPO_REGRA_TRIBUTARIA. Nenhuma nota passou por elas. Quem decide
CST, CFOP, alíquota e redução é NF_NATUREZA_OPERACAO_PRODGRUPO (natureza de operação × grupo de
produto); o produto aponta o grupo, e a regra do oimpresso é por NCM.

Uso (o default é o que o Sabor Brasil emite hoje):
    python extract-regras-fiscais.py --alias Camargo --uf SP \
        --natureza 998:3 --natureza 999:1 \
        --out data/sabor-brasil-regras-fiscais.csv

`--natureza NAT:CRT` diz qual natureza vira regra de qual regime. É escolha fiscal, não técnica: o
relatório imprime as outras naturezas usadas desde 2024 para quem decide comparar.
"""

from __future__ import annotations

import argparse
import io
import csv
import sys
from collections import defaultdict
from pathlib import Path

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")

sys.path.insert(0, str(Path(__file__).resolve().parent / "lib"))
from firebird_reader import firebird_connect, query  # noqa: E402

CST_VALIDOS = {"00", "10", "20", "30", "40", "41", "50", "51", "60", "70", "90"}
CSOSN_VALIDOS = {"101", "102", "103", "201", "202", "203", "300", "400", "500", "900"}
COLUNAS = ["ncm", "uf_origem", "uf_destino", "cfop", "csosn", "cst", "aliquota_icms",
           "aliquota_pis", "aliquota_cofins", "aliquota_ipi", "crt", "reducao_base", "mva", "operacao"]


def frac(v) -> str:
    return "" if v in (None, 0) else f"{float(v) / 100:.4f}"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--alias", required=True)
    ap.add_argument("--password", default="masterkey")
    ap.add_argument("--uf", default="SP")
    ap.add_argument("--natureza", action="append", required=True, help="NAT:CRT[:operacao], ex 998:3 ou 992:3:bonificacao")
    ap.add_argument("--out", required=True)
    a = ap.parse_args()
    escolhidas = {int(n.split(":")[0]): (int(n.split(":")[1]), (n.split(":") + [""])[2]) for n in a.natureza}

    with firebird_connect(a.alias, password_override=a.password) as con:
        inertes = query(con, "SELECT (SELECT COUNT(*) FROM REGRA_TRIBUTARIA) R, "
                             "(SELECT COUNT(*) FROM REGRA_TRIBUTARIA WHERE DT_ALTERACAO IS NOT NULL) E, "
                             "(SELECT COUNT(*) FROM REGRA_TRIBUTARIA_IMPOSTO) I, "
                             "(SELECT COUNT(*) FROM PRODUTO_REGRA_TRIBUTARIA) P, "
                             "(SELECT COUNT(*) FROM GRUPO_REGRA_TRIBUTARIA) G FROM RDB$DATABASE")[0]
        print(f"REGRA_TRIBUTARIA: {inertes['R']} linhas · {inertes['E']} editadas · impostos {inertes['I']}"
              f" · vínculos produto {inertes['P']} · grupo {inertes['G']}  → não carregadas")
        usadas = query(con, "SELECT NF_CODNATUREZA_OPERACAO NAT, COUNT(*) N FROM VENDA "
                            "WHERE DT_EMISSAO >= '2024-01-01' AND NF_STATUS IN ('Confirmada','Autorizada') "
                            "GROUP BY 1 ORDER BY 2 DESC")
        print("naturezas com nota desde 2024:", ", ".join(f"{u['NAT']}={u['N']}" for u in usadas))

        ncms = defaultdict(set)
        for r in query(con, "SELECT TRIM(CODPRODUTO_GRUPO) G, TRIM(CODNF_NCM) NCM FROM PRODUTO "
                            "WHERE CODNF_NCM IS NOT NULL AND CHAR_LENGTH(TRIM(CODNF_NCM)) = 8"):
            ncms[r["NCM"]].add(r["G"])

        linhas, vistos = [], {}
        for nat, (crt, operacao) in escolhidas.items():
            for r in query(con, "SELECT TRIM(CODPRODUTO_GRUPO) G, TRIM(CODNF_CST) CST, TRIM(CODNF_CFOP) CFOP, "
                                "PREDBC, PICMS, CALCULA_ICMS, CALCULA_ICMS_ST, PMVAST, PIS_PPIS, COFINS_PCOFINS, IPI_PIPI FROM NF_NATUREZA_OPERACAO_PRODGRUPO "
                                "WHERE CODNF_NATUREZA_OPERACAO = ? AND ATIVO = 'S'", (nat,)):
                codigo, cfop = r["CST"] or "", r["CFOP"] or ""
                valido = (codigo in CST_VALIDOS) if crt == 3 else (codigo in CSOSN_VALIDOS)
                if not valido or len(cfop) != 4:
                    print(f"  pulado nat {nat} grupo {r['G']}: código {codigo or '-'} / CFOP {cfop or '-'} fora do regime {crt}")
                    continue
                for ncm in sorted(n for n, gs in ncms.items() if r["G"] in gs):
                    chave = (ncm, crt, operacao)
                    if chave in vistos:
                        print(f"  CONFLITO NCM {ncm} regime {crt}: grupo {vistos[chave]} e {r['G']} — mantido o 1º")
                        continue
                    vistos[chave] = r["G"]
                    st = r["CALCULA_ICMS_ST"] == "S"
                    sem_icms = r["CALCULA_ICMS"] == "N"  # o Delphi não calcula ICMS nesta natureza × grupo
                    linhas.append({
                        "ncm": ncm, "uf_origem": a.uf, "uf_destino": a.uf, "cfop": cfop,
                        "csosn": codigo if crt != 3 else "", "cst": codigo if crt == 3 else "",
                        "aliquota_icms": "0" if sem_icms else (frac(r["PICMS"]) or "0"), "aliquota_pis": frac(r["PIS_PPIS"]) or "0",
                        "aliquota_cofins": frac(r["COFINS_PCOFINS"]) or "0", "aliquota_ipi": frac(r["IPI_PIPI"]) or "0", "crt": str(crt), "reducao_base": "" if sem_icms else frac(r["PREDBC"]),
                        "mva": frac(r["PMVAST"]) if st else "", "operacao": operacao,
                    })

    with open(a.out, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=COLUNAS, lineterminator="\n")
        w.writeheader()
        w.writerows(sorted(linhas, key=lambda x: (x["crt"], x["ncm"])))
    print(f"{len(linhas)} regras → {a.out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
