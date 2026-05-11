"""
Probe CONFIGURACOES_GRID — descoberta de schema dinamico US-SELL-027.

Tabela CONFIGURACOES_GRID no Delphi WR Comercial guarda a configuracao de colunas
do USUARIO em cada tela (o que ele CONFIGUROU pra ver, nao o que o sistema OFERECE).

Sinal OURO pra US-SELL-027 (PR #534): em vez de assumir colunas A/B/C, leio o que
cada cliente USA na tela `Caption := 'Venda'`.

Confirmado em D:/Programas/WR Comercial/app/Controller/Controller.Configuracoes_Grid.pas:
  Tabela := 'CONFIGURACOES_GRID';
  SQLInit.Text := 'select c.* from CONFIGURACOES_GRID C';
  GetFiltroProNome('Retirar filtros').SQL := '(C.ATIVO = ''S'')';

READ-ONLY ESTRITO — somente SELECT, nunca INSERT/UPDATE/DELETE.
Padrao identico ao scripts/sells_grade_heatmap.py.

Refs:
- memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md
- memory/requisitos/Sells/SPEC.md (US-SELL-027 schema discovery)
- PR #534 (Grade Avancada toggle Lista/Grade — ADR 0136)
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import sys
from pathlib import Path

import firebird.driver as fb

BANKS = [
    ("01-wr2",       "192.168.0.55:Banco"),
    ("02-vargas",    "192.168.0.55:D:\\DadosClientes\\Vargas\\Dados\\BANCO.FDB"),
    ("03-extreme",   "192.168.0.55:D:\\DadosClientes\\Extreme\\Dados\\BANCO.FDB"),
    ("04-gold",      "192.168.0.55:D:\\DadosClientes\\Gold\\Dados\\BANCO.FDB"),
    ("05-martinho",  "192.168.0.55:D:\\DadosClientes\\MartinhoCacamba\\Dados\\BANCO.FDB"),
]

# Pasta canonica do mapping (igual ao TELA-LISTA-VENDAS.md vizinho)
OUTPUT_DIR = Path("memory/research/clientes-legacy-officeimpresso/_MAPPING")
RAW_DIR = OUTPUT_DIR / "raw-configuracoes-grid"  # gitignored (raw-*.json)


def connect(alias: str) -> fb.Connection:
    return fb.connect(alias, user="SYSDBA", password="masterkey")


def schema_dump(cur, table: str) -> list[dict]:
    """Lista todas colunas + tipo Firebird."""
    cur.execute(
        "SELECT TRIM(f.RDB$FIELD_NAME), t.RDB$TYPE_NAME, src.RDB$FIELD_LENGTH "
        "FROM RDB$RELATION_FIELDS f "
        "JOIN RDB$FIELDS src ON f.RDB$FIELD_SOURCE = src.RDB$FIELD_NAME "
        "JOIN RDB$TYPES t ON t.RDB$FIELD_NAME = 'RDB$FIELD_TYPE' AND t.RDB$TYPE = src.RDB$FIELD_TYPE "
        "WHERE f.RDB$RELATION_NAME = ? "
        "ORDER BY f.RDB$FIELD_POSITION",
        [table.upper()],
    )
    return [{"name": r[0], "type": r[1].strip(), "length": r[2]} for r in cur.fetchall()]


def table_exists(cur, table: str) -> bool:
    cur.execute(
        "SELECT COUNT(*) FROM RDB$RELATIONS "
        "WHERE RDB$RELATION_NAME = ? AND RDB$SYSTEM_FLAG = 0",
        [table.upper()],
    )
    return cur.fetchone()[0] > 0


def find_columns(cur, table: str, *candidates: str) -> str | None:
    """Retorna primeiro nome de coluna que existe (case insensitive)."""
    cur.execute(
        "SELECT TRIM(RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS "
        "WHERE RDB$RELATION_NAME = ?",
        [table.upper()],
    )
    existing = {r[0].upper() for r in cur.fetchall()}
    for c in candidates:
        if c.upper() in existing:
            return c.upper()
    return None


def count_total(cur) -> int:
    cur.execute("SELECT COUNT(*) FROM CONFIGURACOES_GRID")
    return int(cur.fetchone()[0])


def count_ativo(cur) -> dict:
    """Conta ATIVO=S vs N (filtro padrao da tela)."""
    try:
        cur.execute(
            "SELECT TRIM(ATIVO), COUNT(*) FROM CONFIGURACOES_GRID "
            "GROUP BY ATIVO ORDER BY 2 DESC"
        )
        return {str(r[0]): int(r[1]) for r in cur.fetchall()}
    except Exception as e:
        return {"error": str(e)[:80]}


def top_tabelas(cur, tabela_field: str, limit: int = 20) -> list[tuple]:
    """Top N tabelas alvo mais configuradas."""
    try:
        cur.execute(
            f"SELECT FIRST {limit} TRIM({tabela_field}), COUNT(*) "
            f"FROM CONFIGURACOES_GRID "
            f"WHERE {tabela_field} IS NOT NULL "
            f"GROUP BY {tabela_field} ORDER BY 2 DESC"
        )
        return [(str(r[0]), int(r[1])) for r in cur.fetchall()]
    except Exception as e:
        return [("ERROR", str(e)[:80])]


def configs_for_venda(cur, tabela_field: str, sample_cols: list[str]) -> dict:
    """Lista configs da tela Venda: quais colunas o cliente customizou."""
    # Detecta valor exato (pode ser 'VENDA', 'TVENDA', 'Venda' etc)
    cur.execute(
        f"SELECT DISTINCT TRIM({tabela_field}) FROM CONFIGURACOES_GRID "
        f"WHERE UPPER({tabela_field}) LIKE '%VENDA%'"
    )
    venda_keys = sorted({r[0] for r in cur.fetchall() if r[0]})

    out = {"venda_keys_found": venda_keys, "by_key": {}}

    for vk in venda_keys[:10]:
        cur.execute(
            f"SELECT COUNT(*) FROM CONFIGURACOES_GRID WHERE TRIM({tabela_field}) = ?",
            [vk],
        )
        total = int(cur.fetchone()[0])

        # Pega sample com colunas relevantes — limit 50
        sel_cols = [c for c in sample_cols if c]
        if not sel_cols:
            sel_cols = ["*"]
        # tenta select com colunas conhecidas — fallback "*"
        try:
            cur.execute(
                f"SELECT FIRST 200 {','.join(sel_cols)} FROM CONFIGURACOES_GRID "
                f"WHERE TRIM({tabela_field}) = ? ORDER BY 1",
                [vk],
            )
        except Exception:
            cur.execute(
                f"SELECT FIRST 200 * FROM CONFIGURACOES_GRID "
                f"WHERE TRIM({tabela_field}) = ? ",
                [vk],
            )
        col_names = [d[0] for d in cur.description]
        rows = cur.fetchall()
        sample = [
            {col_names[i]: (str(v)[:120] if v is not None else None) for i, v in enumerate(r)}
            for r in rows[:30]
        ]
        out["by_key"][vk] = {
            "total_linhas": total,
            "columns_returned": col_names,
            "sample_rows": sample,
        }
    return out


def usuario_distribution(cur, tabela_field: str, usuario_field: str | None) -> dict:
    """Quantos usuarios distintos configuraram cada tabela (sinal de uso real)."""
    if not usuario_field:
        return {"error": "no usuario field found"}
    try:
        cur.execute(
            f"SELECT FIRST 20 TRIM({tabela_field}), "
            f"COUNT(*) AS N, COUNT(DISTINCT {usuario_field}) AS USERS "
            f"FROM CONFIGURACOES_GRID "
            f"WHERE {tabela_field} IS NOT NULL "
            f"GROUP BY {tabela_field} ORDER BY 2 DESC"
        )
        return {
            "by_tabela": [
                {"tabela": str(r[0]), "configs": int(r[1]), "usuarios": int(r[2])}
                for r in cur.fetchall()
            ]
        }
    except Exception as e:
        return {"error": str(e)[:80]}


def collect_all(slug: str, alias: str) -> dict:
    print(f"[{slug}] Conectando {alias}...", file=sys.stderr)
    con = connect(alias)
    cur = con.cursor()

    result: dict = {
        "slug": slug,
        "host_alias": alias,
        "collected_at": dt.datetime.now().isoformat(),
    }

    # 1) Tabela existe?
    if not table_exists(cur, "CONFIGURACOES_GRID"):
        result["error"] = "CONFIGURACOES_GRID nao existe nesse banco"
        con.close()
        return result

    # 2) Schema dump
    schema = schema_dump(cur, "CONFIGURACOES_GRID")
    result["schema"] = schema
    col_names = [c["name"] for c in schema]
    result["columns_count"] = len(schema)

    # 3) Total + ATIVO breakdown
    result["total"] = count_total(cur)
    result["by_ativo"] = count_ativo(cur)

    # 4) Detecta campo "tabela alvo" (qual tela essa config aplica)
    tabela_field = find_columns(
        cur, "CONFIGURACOES_GRID",
        "TABELA", "TABELA_REFERENCIA", "TABELA_ALVO", "PATH", "MODULO",
        "TELA", "FORMULARIO", "FORM", "NOME_TELA", "NOMETELA",
        "CONTROLLER", "GRID",
    )
    result["tabela_field"] = tabela_field

    # 5) Detecta campo usuario
    usuario_field = find_columns(
        cur, "CONFIGURACOES_GRID",
        "USUARIO_CODIGO", "USUARIO", "COD_USUARIO", "CODUSUARIO",
        "PESSOA_FUNCIONARIO_CODIGO", "FUNCIONARIO", "USER_CODE",
    )
    result["usuario_field"] = usuario_field

    # 6) Detecta campos de coluna grid
    coluna_field = find_columns(
        cur, "CONFIGURACOES_GRID",
        "COLUNA", "NOME_CAMPO", "CAMPO", "FIELD_NAME",
        "COLUMN_NAME", "NOMECAMPO",
    )
    result["coluna_field"] = coluna_field

    largura_field = find_columns(
        cur, "CONFIGURACOES_GRID",
        "LARGURA", "WIDTH", "TAMANHO",
    )
    result["largura_field"] = largura_field

    ordem_field = find_columns(
        cur, "CONFIGURACOES_GRID",
        "ORDEM", "POSICAO", "INDEX", "INDICE", "ORDER",
    )
    result["ordem_field"] = ordem_field

    visivel_field = find_columns(
        cur, "CONFIGURACOES_GRID",
        "VISIVEL", "VISIBLE", "EXIBE", "MOSTRA",
    )
    result["visivel_field"] = visivel_field

    # 7) Top tabelas alvo
    if tabela_field:
        print(f"[{slug}]   top tabelas via {tabela_field}", file=sys.stderr)
        result["top_tabelas"] = top_tabelas(cur, tabela_field, limit=30)
        result["usuario_distribution"] = usuario_distribution(cur, tabela_field, usuario_field)
    else:
        result["top_tabelas"] = []
        result["usuario_distribution"] = {"error": "no tabela_field"}

    # 8) Configs especificas pra Venda
    if tabela_field:
        relevant_cols = list(filter(None, [
            "CODIGO", tabela_field, usuario_field, coluna_field,
            largura_field, ordem_field, visivel_field,
        ]))
        # dedup preservando ordem
        seen = set()
        relevant_cols = [c for c in relevant_cols if not (c in seen or seen.add(c))]
        print(f"[{slug}]   configs Venda", file=sys.stderr)
        result["configs_venda"] = configs_for_venda(cur, tabela_field, relevant_cols)
    else:
        result["configs_venda"] = {"error": "no tabela_field"}

    # 9) Sample geral 5 linhas (qualquer config)
    try:
        cur.execute("SELECT FIRST 5 * FROM CONFIGURACOES_GRID")
        col_names_s = [d[0] for d in cur.description]
        rows = cur.fetchall()
        result["sample_first_5"] = [
            {col_names_s[i]: (str(v)[:120] if v is not None else None) for i, v in enumerate(r)}
            for r in rows
        ]
    except Exception as e:
        result["sample_first_5"] = {"error": str(e)[:80]}

    con.close()
    print(f"[{slug}] OK ({result['total']} linhas, {result['columns_count']} colunas)", file=sys.stderr)
    return result


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--only", help="Roda so 1 slug (debug)", default=None)
    args = ap.parse_args()

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    RAW_DIR.mkdir(parents=True, exist_ok=True)

    all_results: dict = {}
    for slug, alias in BANKS:
        if args.only and slug != args.only:
            continue
        try:
            data = collect_all(slug, alias)
        except Exception as e:
            data = {"slug": slug, "host_alias": alias, "error": str(e)[:200]}
            print(f"[{slug}] FAIL {str(e)[:120]}", file=sys.stderr)
        all_results[slug] = data

        # Raw JSON por cliente (gitignored — pasta raw-*)
        raw_path = RAW_DIR / f"raw-{slug}.json"
        raw_path.write_text(json.dumps(data, indent=2, default=str, ensure_ascii=False),
                            encoding="utf-8")
        print(f"  raw  → {raw_path}", file=sys.stderr)

    # Consolida em 1 JSON (gitignored)
    consolidated = RAW_DIR / "raw-all.json"
    consolidated.write_text(json.dumps(all_results, indent=2, default=str, ensure_ascii=False),
                            encoding="utf-8")
    print(f"  ALL  → {consolidated}", file=sys.stderr)

    print("DONE", file=sys.stderr)


if __name__ == "__main__":
    main()
