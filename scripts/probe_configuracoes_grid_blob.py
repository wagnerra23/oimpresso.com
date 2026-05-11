"""
Probe GRID BLOB de CONFIGURACOES_GRID — quantas colunas cada cliente USA em
TFrame_ConsuVenda_Venda (Lista de Vendas — tela alvo US-SELL-027).

BLOB eh DFM Delphi binario (DevExpress cxGrid stream). Estrutura:
  TcxGridDBColumn × N        — cada coluna do grid
  Width × N                   — largura customizada
  Visible: True/False × N     — coluna visivel ou oculta
  GroupIndex                  — se agrupada (column → grupo)
  SortOrder + SortIndex       — sort persistido

Extraimos por contagem de markers ASCII no stream:
  - n_columns_total      = total de colunas declaradas
  - n_columns_visible    = visiveis (cliente "aprovou")
  - n_columns_hidden     = ocultas (cliente "rejeitou")
  - taxa_customizacao    = hidden / total (alta = cliente filtrou agressivo)

Output JSON estruturado por cliente + por usuario por form.

READ-ONLY ESTRITO.
"""
from __future__ import annotations
import json
import re
import sys
from collections import Counter
from pathlib import Path

import firebird.driver as fb

BANKS = [
    ("01-wr2",       "192.168.0.55:Banco"),
    ("02-vargas",    "192.168.0.55:D:\\DadosClientes\\Vargas\\Dados\\BANCO.FDB"),
    ("03-extreme",   "192.168.0.55:D:\\DadosClientes\\Extreme\\Dados\\BANCO.FDB"),
    ("04-gold",      "192.168.0.55:D:\\DadosClientes\\Gold\\Dados\\BANCO.FDB"),
    ("05-martinho",  "192.168.0.55:D:\\DadosClientes\\MartinhoCacamba\\Dados\\BANCO.FDB"),
]

OUT = Path("memory/research/clientes-legacy-officeimpresso/_MAPPING/raw-configuracoes-grid")
OUT.mkdir(parents=True, exist_ok=True)

# Marker bytes DFM:
# 'Visible' (7) + 0x02 0x09 (set marker) + 0x06 (string follows) + length + value
VISIBLE_TRUE = b"Visible\x02\x09\x06\x04True"
VISIBLE_FALSE = b"Visible\x02\x09\x06\x05False"
COLUMN_MARKER = b"TcxGridDBColumn"
BANDED_COLUMN_MARKER = b"TcxGridDBBandedColumn"
GROUPINDEX_NONE = b"GroupIndex\x02\x06\x02\xff"  # -1 = nao agrupado
SORT_NONE = b"SortOrder\x02\x09\x06\x06soNone"
SORT_ASC = b"SortOrder\x02\x09\x06\x05soAsc"
SORT_DESC = b"SortOrder\x02\x09\x06\x06soDesc"


def analyze_blob(blob_bytes: bytes) -> dict:
    """Conta markers no DFM stream."""
    n_col = blob_bytes.count(COLUMN_MARKER) + blob_bytes.count(BANDED_COLUMN_MARKER)
    n_visible = blob_bytes.count(VISIBLE_TRUE)
    n_hidden = blob_bytes.count(VISIBLE_FALSE)
    n_grouped = n_col - blob_bytes.count(GROUPINDEX_NONE)
    n_sort_asc = blob_bytes.count(SORT_ASC)
    n_sort_desc = blob_bytes.count(SORT_DESC)
    return {
        "size_bytes": len(blob_bytes),
        "n_columns": n_col,
        "n_visible": n_visible,
        "n_hidden": n_hidden,
        "n_grouped_columns": max(0, n_grouped),
        "n_sorted_asc": n_sort_asc,
        "n_sorted_desc": n_sort_desc,
        "pct_visible": round(100 * n_visible / n_col, 1) if n_col else 0,
        "pct_hidden": round(100 * n_hidden / n_col, 1) if n_col else 0,
    }


def analyze_bank(slug: str, alias: str) -> dict:
    print(f"[{slug}] Conectando...", file=sys.stderr)
    con = fb.connect(alias, user="SYSDBA", password="masterkey")
    cur = con.cursor()

    target_forms = [
        "TFrame_ConsuVenda_Venda",   # Lista de Vendas (Frame embutido)
        "TFrame_Venda_Venda",         # Cadastro de Vendas (tem grids tambem)
        "TConsuVenda",                # Standalone
        "TConsuVendaBase",            # Base
    ]
    out = {"slug": slug, "by_form": {}}

    for form in target_forms:
        cur.execute(
            "SELECT CODUSUARIO, GRID, DESCRICAO FROM CONFIGURACOES_GRID "
            "WHERE FORM = ? AND ATIVO = 'S' ORDER BY CODUSUARIO",
            [form],
        )
        rows = cur.fetchall()
        if not rows:
            continue

        per_row = []
        agg = Counter()
        sizes = []
        for cod_usu, blob, descr in rows:
            if blob is None:
                continue
            try:
                if hasattr(blob, "read"):
                    data = blob.read()
                else:
                    data = bytes(blob)
            except Exception:
                continue
            res = analyze_blob(data)
            res["usuario"] = cod_usu
            res["descricao_grid"] = descr
            per_row.append(res)
            sizes.append(res["size_bytes"])
            if res["n_columns"]:
                agg["total_n_columns"] += res["n_columns"]
                agg["total_n_visible"] += res["n_visible"]
                agg["total_n_hidden"] += res["n_hidden"]
                agg["total_n_grouped"] += res["n_grouped_columns"]
                agg["n_grids_with_grouping"] += (1 if res["n_grouped_columns"] > 0 else 0)
                agg["n_grids_with_sort"] += (1 if res["n_sorted_asc"] + res["n_sorted_desc"] > 0 else 0)

        if per_row:
            avg_cols = round(agg["total_n_columns"] / len(per_row), 1)
            avg_vis = round(agg["total_n_visible"] / len(per_row), 1)
            avg_hid = round(agg["total_n_hidden"] / len(per_row), 1)
            out["by_form"][form] = {
                "n_grids": len(per_row),
                "avg_n_columns": avg_cols,
                "avg_n_visible": avg_vis,
                "avg_n_hidden": avg_hid,
                "pct_grids_with_grouping": round(100 * agg["n_grids_with_grouping"] / len(per_row), 1),
                "pct_grids_with_sort": round(100 * agg["n_grids_with_sort"] / len(per_row), 1),
                "avg_size_bytes": round(sum(sizes) / len(sizes)),
                "max_size_bytes": max(sizes),
                "samples": per_row[:5],
            }

    con.close()
    print(f"[{slug}] OK", file=sys.stderr)
    return out


def main():
    out = {}
    for slug, alias in BANKS:
        try:
            out[slug] = analyze_bank(slug, alias)
        except Exception as e:
            out[slug] = {"slug": slug, "error": str(e)[:200]}
            print(f"[{slug}] FAIL {e}", file=sys.stderr)

    p = OUT / "raw-blob-analysis.json"
    p.write_text(json.dumps(out, indent=2, default=str, ensure_ascii=False), encoding="utf-8")
    print(f"  → {p}", file=sys.stderr)


if __name__ == "__main__":
    main()
