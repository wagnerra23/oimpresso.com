"""
Sells Grade Avancada — heatmap UI usage extractor.

Roda 7 queries READ-ONLY contra banco Firebird OfficeImpresso pra qualificar
US-SELL-018..026 do PR #534 (ADR 0136). NAO mexe nada — so SELECT.

Output em JSON (raw) + Markdown anonimizado + Markdown com nomes (gitignored).

Refs:
- memory/decisions/0136-sells-grade-avancada-modo-toggle.md
- memory/requisitos/Sells/SPEC.md (US-SELL-015..026)
- memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md
- .claude/skills/officeimpresso-financial-snapshot/SKILL.md
"""
from __future__ import annotations
import argparse
import datetime as dt
import hashlib
import json
import sys
from pathlib import Path

import firebird.driver as fb

OUTPUT_DIR = Path("memory/research/2026-05-sells-grade-heatmap")

SCHEMA_FINGERPRINT_TABLES = ["VENDA", "VENDA_FINANCEIRO", "FINANCEIRO", "PESSOAS"]


def hash_label(text: str, prefix: str = "Cliente") -> str:
    if not text:
        return f"{prefix}_NULL"
    h = hashlib.sha1(text.encode("utf-8")).hexdigest()[:6].upper()
    return f"{prefix}_{h}"


def connect(host_alias: str) -> fb.Connection:
    return fb.connect(host_alias, user="SYSDBA", password="masterkey")


def schema_fingerprint(cur) -> dict:
    cur.execute(
        "SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
        "WHERE RDB$RELATION_NAME IN (?, ?, ?, ?)",
        SCHEMA_FINGERPRINT_TABLES,
    )
    found = sorted([row[0] for row in cur.fetchall()])
    expected = sorted(SCHEMA_FINGERPRINT_TABLES)
    return {"found": found, "expected": expected, "ok": found == expected}


def table_columns(cur, table: str) -> list[str]:
    cur.execute(
        "SELECT TRIM(RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS "
        "WHERE RDB$RELATION_NAME = ? ORDER BY RDB$FIELD_POSITION",
        [table.upper()],
    )
    return [row[0] for row in cur.fetchall()]


def q1_volume_24m(cur) -> dict:
    """Vendas count por ano-mes nos ultimos 24m."""
    try:
        cur.execute(
            "SELECT EXTRACT(YEAR FROM DT_EMISSAO), EXTRACT(MONTH FROM DT_EMISSAO), COUNT(*) "
            "FROM VENDA "
            "WHERE DT_EMISSAO >= DATEADD(YEAR, -2, CURRENT_DATE) "
            "GROUP BY 1, 2 ORDER BY 1, 2"
        )
        rows = cur.fetchall()
    except Exception:
        # Fallback se coluna se chama EMISSAO
        cur.execute(
            "SELECT EXTRACT(YEAR FROM EMISSAO), EXTRACT(MONTH FROM EMISSAO), COUNT(*) "
            "FROM VENDA "
            "WHERE EMISSAO >= DATEADD(YEAR, -2, CURRENT_DATE) "
            "GROUP BY 1, 2 ORDER BY 1, 2"
        )
        rows = cur.fetchall()
    total = sum(r[2] for r in rows)
    months = len(rows)
    return {
        "rows": [(int(r[0]), int(r[1]), int(r[2])) for r in rows],
        "total_24m": int(total),
        "months_with_data": months,
        "avg_per_month": round(total / months, 1) if months else 0,
    }


def q2_date_fields_filled(cur) -> dict:
    """% vendas com campos de data preenchidos (mostra quais o cliente USA)."""
    cols = set(table_columns(cur, "VENDA"))
    candidates = [
        "DT_EMISSAO", "EMISSAO",
        "DT_FATURAMENTO", "DATA_FATURAMENTO", "DT_FAT",
        "DT_COMPETENCIA", "COMPETENCIA",
        "DT_PROMETIDO", "PROMETIDO", "DT_PREVISTA",
        "DT_ENVIO_FATURAMENTO", "DT_ENV_FAT", "DT_ENVIO",
        "DT_ENTREGA", "DATA_ENTREGA",
        "DT_NF", "DATA_NF", "DT_EMISSAO_NF",
        "DT_ALTERACAO", "ULTIMA_ALTERACAO",
    ]
    present = [c for c in candidates if c in cols]
    # COUNT(*) total
    cur.execute("SELECT COUNT(*) FROM VENDA")
    total = cur.fetchone()[0]
    if total == 0:
        return {"total_vendas": 0, "campos": []}
    results = []
    for c in present:
        cur.execute(f"SELECT COUNT(*) FROM VENDA WHERE {c} IS NOT NULL")
        filled = cur.fetchone()[0]
        results.append({"campo": c, "filled": int(filled), "pct": round(100 * filled / total, 1)})
    return {"total_vendas": int(total), "campos": results}


def q3_situacao_distinct(cur) -> dict:
    """Status estruturado em 3 lugares: VENDA.SITUACAO (string), VENDA_SITUACAO, VENDA_ESTAGIO."""
    out = {"venda_situacao_inline": None, "venda_situacao_table": None, "venda_estagio": None}
    venda_cols = set(table_columns(cur, "VENDA"))

    # Inline VENDA.SITUACAO/STATUS
    candidates = ["SITUACAO", "STATUS", "STATUS_VENDA"]
    field = next((c for c in candidates if c in venda_cols), None)
    if field:
        cur.execute(f"SELECT COUNT(DISTINCT {field}) FROM VENDA")
        distinct = int(cur.fetchone()[0])
        cur.execute(
            f"SELECT FIRST 20 {field}, COUNT(*) FROM VENDA "
            f"WHERE {field} IS NOT NULL GROUP BY 1 ORDER BY 2 DESC"
        )
        top = [(row[0], int(row[1])) for row in cur.fetchall()]
        out["venda_situacao_inline"] = {"campo": field, "distinct": distinct, "top": top}

    # Tabela filha VENDA_SITUACAO (lookup)
    try:
        cur.execute("SELECT COUNT(*) FROM VENDA_SITUACAO")
        n = int(cur.fetchone()[0])
        cur.execute("SELECT FIRST 20 * FROM VENDA_SITUACAO")
        rows = cur.fetchall()
        sample_cols = [d[0] for d in cur.description]
        # tenta achar coluna com label legivel
        nome_col = next((c for c in sample_cols if any(k in c.upper() for k in ["NOME", "DESCRICAO", "LABEL", "DESCR"])), None)
        out["venda_situacao_table"] = {
            "total_linhas": n,
            "columns": sample_cols,
            "nome_col": nome_col,
            "sample": [tuple(str(x)[:40] if x else x for x in r) for r in rows[:10]],
        }
    except Exception:
        pass

    # Tabela VENDA_ESTAGIO — provavel FSM/funil
    try:
        cur.execute("SELECT COUNT(*) FROM VENDA_ESTAGIO")
        n = int(cur.fetchone()[0])
        cur.execute("SELECT FIRST 20 * FROM VENDA_ESTAGIO")
        rows = cur.fetchall()
        sample_cols = [d[0] for d in cur.description]
        nome_col = next((c for c in sample_cols if any(k in c.upper() for k in ["NOME", "DESCRICAO", "LABEL"])), None)
        out["venda_estagio"] = {
            "total_linhas": n,
            "columns": sample_cols,
            "nome_col": nome_col,
            "sample": [tuple(str(x)[:40] if x else x for x in r) for r in rows[:10]],
        }
    except Exception:
        pass

    return out


def q4_agrupamento_implicito(cur) -> dict:
    """Vendas compartilhando CODFINANCEIRO_GRUPO ou similar."""
    # tenta VENDA_FINANCEIRO primeiro
    vf_cols = set(table_columns(cur, "VENDA_FINANCEIRO"))
    fin_cols = set(table_columns(cur, "FINANCEIRO"))
    candidates_vf = ["CODFINANCEIRO_GRUPO", "COD_GRUPO", "GRUPO"]
    candidates_fin = ["CODFINANCEIRO_GRUPO", "COD_GRUPO"]
    field_vf = next((c for c in candidates_vf if c in vf_cols), None)
    field_fin = next((c for c in candidates_fin if c in fin_cols), None)
    out = {"venda_financeiro": None, "financeiro": None}
    if field_vf:
        cur.execute(f"SELECT COUNT(*) FROM VENDA_FINANCEIRO WHERE {field_vf} IS NOT NULL")
        with_group = cur.fetchone()[0]
        cur.execute(f"SELECT COUNT(*) FROM VENDA_FINANCEIRO")
        total = cur.fetchone()[0]
        cur.execute(f"SELECT COUNT(DISTINCT {field_vf}) FROM VENDA_FINANCEIRO WHERE {field_vf} IS NOT NULL")
        distinct_groups = cur.fetchone()[0]
        out["venda_financeiro"] = {
            "campo": field_vf,
            "total": int(total),
            "with_group": int(with_group),
            "pct": round(100 * with_group / total, 1) if total else 0,
            "distinct_groups": int(distinct_groups),
            "avg_size": round(with_group / distinct_groups, 2) if distinct_groups else 0,
        }
    if field_fin:
        cur.execute(f"SELECT COUNT(*) FROM FINANCEIRO WHERE {field_fin} IS NOT NULL")
        with_group = cur.fetchone()[0]
        cur.execute(f"SELECT COUNT(*) FROM FINANCEIRO")
        total = cur.fetchone()[0]
        cur.execute(f"SELECT COUNT(DISTINCT {field_fin}) FROM FINANCEIRO WHERE {field_fin} IS NOT NULL")
        distinct_groups = cur.fetchone()[0]
        out["financeiro"] = {
            "campo": field_fin,
            "total": int(total),
            "with_group": int(with_group),
            "pct": round(100 * with_group / total, 1) if total else 0,
            "distinct_groups": int(distinct_groups),
            "avg_size": round(with_group / distinct_groups, 2) if distinct_groups else 0,
        }
    return out


def q5_itens_por_venda(cur) -> dict:
    """Media de itens (linhas filhas) por venda."""
    # Tentativa: tabela filha mais provavel se chama VENDA_ITEM, ITEM_VENDA, VENDA_PRODUTO
    candidates = ["VENDA_ITEM", "ITEM_VENDA", "VENDA_PRODUTO", "VENDA_ITENS", "ITENS_VENDA"]
    cur.execute(
        "SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
        "WHERE RDB$VIEW_BLR IS NULL AND RDB$SYSTEM_FLAG = 0"
    )
    all_tables = {row[0] for row in cur.fetchall()}
    table = next((t for t in candidates if t in all_tables), None)
    if not table:
        # busca por substring
        vendas_tables = [t for t in all_tables if "VENDA" in t and t != "VENDA"]
        return {"table": None, "vendas_related_tables": sorted(vendas_tables)[:10]}
    # tenta CODVENDA / VENDA_CODIGO / COD_VENDA
    cols = set(table_columns(cur, table))
    fk_candidates = ["CODVENDA", "VENDA_CODIGO", "COD_VENDA", "VENDA"]
    fk = next((c for c in fk_candidates if c in cols), None)
    if not fk:
        return {"table": table, "fk": None, "cols_sample": sorted(cols)[:15]}
    cur.execute(f"SELECT COUNT(*), COUNT(DISTINCT {fk}) FROM {table}")
    row = cur.fetchone()
    total_itens, total_vendas_com_item = int(row[0]), int(row[1])
    avg = round(total_itens / total_vendas_com_item, 2) if total_vendas_com_item else 0
    # Distribuicao: quantas vendas com 1 / 2-5 / 6-10 / 11+ itens
    cur.execute(
        f"SELECT {fk}, COUNT(*) AS N FROM {table} GROUP BY {fk}"
    )
    counts = [int(r[1]) for r in cur.fetchall()]
    bins = {"1_item": 0, "2_5": 0, "6_10": 0, "11_plus": 0}
    for n in counts:
        if n == 1:
            bins["1_item"] += 1
        elif n <= 5:
            bins["2_5"] += 1
        elif n <= 10:
            bins["6_10"] += 1
        else:
            bins["11_plus"] += 1
    return {
        "table": table, "fk": fk,
        "total_itens": total_itens,
        "vendas_com_itens": total_vendas_com_item,
        "media_itens_por_venda": avg,
        "distribuicao": bins,
    }


def q6_range_temporal(cur) -> dict:
    """MIN/MAX emissao + janelas (ultimos 7/30/90/365 dias)."""
    cols = set(table_columns(cur, "VENDA"))
    field = "DT_EMISSAO" if "DT_EMISSAO" in cols else "EMISSAO"
    if field not in cols:
        return {"campo": None}
    cur.execute(f"SELECT MIN({field}), MAX({field}) FROM VENDA")
    mn, mx = cur.fetchone()
    windows = {}
    for days, label in [(7, "ultimos_7d"), (30, "ultimos_30d"),
                         (90, "ultimos_90d"), (365, "ultimos_365d")]:
        cur.execute(
            f"SELECT COUNT(*) FROM VENDA WHERE {field} >= DATEADD(DAY, -{days}, CURRENT_DATE)"
        )
        windows[label] = int(cur.fetchone()[0])
    return {"campo": field, "min": str(mn), "max": str(mx), "windows": windows}


def q7_campos_automotivos(cur) -> dict:
    """PLACA/CHASSI vivem em EQUIPAMENTO_VEICULO (correcao Wagner 2026-05-11)."""
    out = {"main_table": "EQUIPAMENTO_VEICULO", "fields": {}, "total_veiculos": 0}
    try:
        cols = set(table_columns(cur, "EQUIPAMENTO_VEICULO"))
        if not cols:
            out["error"] = "EQUIPAMENTO_VEICULO nao existe"
            return out
        cur.execute("SELECT COUNT(*) FROM EQUIPAMENTO_VEICULO")
        total = int(cur.fetchone()[0])
        out["total_veiculos"] = total
        if total == 0:
            return out
        for c in ["PLACA", "PLACA2", "CHASSI", "CHASSI2", "ANO_MODELO", "ANO_FABRICACAO", "RENAVAN", "TIPO", "ESPECIE"]:
            if c not in cols:
                continue
            try:
                cur.execute(
                    f"SELECT COUNT(*) FROM EQUIPAMENTO_VEICULO "
                    f"WHERE {c} IS NOT NULL AND TRIM(CAST({c} AS VARCHAR(200))) <> ''"
                )
                filled = int(cur.fetchone()[0])
                out["fields"][c] = {
                    "filled": filled,
                    "pct": round(100 * filled / total, 1) if total else 0,
                }
            except Exception as e:
                out["fields"][c] = {"error": str(e)[:60]}
    except Exception as e:
        out["error"] = str(e)[:100]
    return out


def q8_pcp_estruturado(cur) -> dict:
    """Detecta PCP estruturado: VENDA_PRODUTO_ETAPA e VENDA_PRODUTO_CENTRO_TRABALHO."""
    out = {}
    for table in ["VENDA_PRODUTO_ETAPA", "VENDA_PRODUTO_CENTRO_TRABALHO"]:
        try:
            cur.execute(f"SELECT COUNT(*) FROM {table}")
            n = int(cur.fetchone()[0])
            cur.execute(
                "SELECT COUNT(DISTINCT v.CODVENDA) FROM " + table + " v"
            )
            distinct_vendas = int(cur.fetchone()[0])
            out[table] = {"total_linhas": n, "distinct_vendas": distinct_vendas}
        except Exception as e:
            out[table] = {"error": str(e)[:60]}
    return out


def q9_venda_obra(cur) -> dict:
    """Detecta uso de VENDA_OBRA (obra/instalacao fisica — relevante pra Modules/ComunicacaoVisual)."""
    try:
        cur.execute("SELECT COUNT(*) FROM VENDA_OBRA")
        n = int(cur.fetchone()[0])
        cur.execute("SELECT COUNT(DISTINCT CODVENDA) FROM VENDA_OBRA")
        distinct_v = int(cur.fetchone()[0])
        return {"total_linhas": n, "distinct_vendas_com_obra": distinct_v}
    except Exception as e:
        return {"error": str(e)[:60]}


def schema_dump_venda(cur) -> list[dict]:
    """Lista todas colunas de VENDA com tipo (descoberta de campos novos)."""
    cur.execute(
        "SELECT TRIM(f.RDB$FIELD_NAME), t.RDB$TYPE_NAME "
        "FROM RDB$RELATION_FIELDS f "
        "JOIN RDB$FIELDS src ON f.RDB$FIELD_SOURCE = src.RDB$FIELD_NAME "
        "JOIN RDB$TYPES t ON t.RDB$FIELD_NAME = 'RDB$FIELD_TYPE' AND t.RDB$TYPE = src.RDB$FIELD_TYPE "
        "WHERE f.RDB$RELATION_NAME = 'VENDA' "
        "ORDER BY f.RDB$FIELD_POSITION"
    )
    return [{"name": r[0], "type": r[1].strip()} for r in cur.fetchall()]


def tabelas_producao_candidatas(cur) -> list[str]:
    """Busca tabelas com substring relacionada a producao/funil/etapa/status."""
    cur.execute(
        "SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
        "WHERE RDB$VIEW_BLR IS NULL AND RDB$SYSTEM_FLAG = 0 "
        "ORDER BY RDB$RELATION_NAME"
    )
    all_tables = [row[0] for row in cur.fetchall()]
    patterns = ["PRODU", "FUNIL", "ETAPA", "STATUS", "FLUXO", "WORKFLOW", "FASE", "STAGE", "PCP"]
    matches = [t for t in all_tables if any(p in t for p in patterns)]
    return matches[:30]


def collect_all(host_alias: str) -> dict:
    print(f"[ 1/11] Conectando {host_alias}...", file=sys.stderr)
    con = connect(host_alias)
    cur = con.cursor()

    print("[ 2/11] Schema fingerprint...", file=sys.stderr)
    fp = schema_fingerprint(cur)
    if not fp["ok"]:
        print(f"ERRO: schema fingerprint NOK. Found: {fp['found']}", file=sys.stderr)
        con.close()
        sys.exit(2)

    print("[ 3/11] Schema dump VENDA (descoberta de campos)...", file=sys.stderr)
    venda_cols = schema_dump_venda(cur)
    print("[ 4/11] Tabelas producao candidatas...", file=sys.stderr)
    tab_prod = tabelas_producao_candidatas(cur)

    print("[ 5/11] Q1 volume 24m...", file=sys.stderr)
    q1 = q1_volume_24m(cur)
    print("[ 6/11] Q2 campos data preenchidos...", file=sys.stderr)
    q2 = q2_date_fields_filled(cur)
    print("[ 7/11] Q3 SITUACAO distinct...", file=sys.stderr)
    q3 = q3_situacao_distinct(cur)
    print("[ 8/11] Q4 agrupamento implicito...", file=sys.stderr)
    q4 = q4_agrupamento_implicito(cur)
    print("[ 9/11] Q5 itens por venda...", file=sys.stderr)
    q5 = q5_itens_por_venda(cur)
    print("[10/11] Q6 range temporal...", file=sys.stderr)
    q6 = q6_range_temporal(cur)
    print("[11/13] Q7 campos automotivos (EQUIPAMENTO_VEICULO)...", file=sys.stderr)
    q7 = q7_campos_automotivos(cur)
    print("[12/13] Q8 PCP estruturado (etapa/centro_trabalho)...", file=sys.stderr)
    q8 = q8_pcp_estruturado(cur)
    print("[13/13] Q9 VENDA_OBRA (obra/instalacao)...", file=sys.stderr)
    q9 = q9_venda_obra(cur)

    con.close()
    return {
        "host_alias": host_alias,
        "collected_at": dt.datetime.now().isoformat(),
        "schema_fingerprint": fp,
        "venda_columns": venda_cols,
        "producao_candidates": tab_prod,
        "q1_volume_24m": q1,
        "q2_date_fields": q2,
        "q3_situacao": q3,
        "q4_agrupamento": q4,
        "q5_itens": q5,
        "q6_range_temporal": q6,
        "q7_automotivos": q7,
        "q8_pcp": q8,
        "q9_obra": q9,
    }


def render_md(data: dict, *, anonimize: bool, slug: str) -> str:
    q1 = data["q1_volume_24m"]
    q2 = data["q2_date_fields"]
    q3 = data["q3_situacao"]
    q4 = data["q4_agrupamento"]
    q5 = data["q5_itens"]
    q6 = data["q6_range_temporal"]
    q7 = data["q7_automotivos"]

    md = []
    md.append(f"# Heatmap UI Vendas — `{slug}`{' (anonimizado)' if anonimize else ' (com nomes)'}")
    md.append("")
    md.append(f"> Coletado: {data['collected_at']}")
    md.append(f"> Banco: `{data['host_alias']}` · Schema fingerprint: {'OK' if data['schema_fingerprint']['ok'] else 'FAIL'}")
    md.append(f"> Refs: [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md) · [Sells/SPEC.md](../../requisitos/Sells/SPEC.md) US-SELL-015..026")
    md.append("")

    # Q1
    md.append("## Q1 · Volume de vendas 24 meses")
    md.append("")
    md.append(f"- Total: **{q1['total_24m']:,}** vendas em **{q1['months_with_data']}** meses")
    md.append(f"- Media: **{q1['avg_per_month']}** vendas/mes")
    md.append("")
    md.append("| Ano | Mes | Vendas |")
    md.append("|----:|----:|-------:|")
    for ano, mes, n in q1["rows"][-12:]:  # ultimos 12 meses
        md.append(f"| {ano} | {mes:02d} | {n:,} |")
    md.append("")
    md.append("**Implicacao:** se total_24m > 5000 → paginacao Inertia 25/pag eh suficiente; se > 50k → precisa virtual scroll (libs tipo @tanstack/react-virtual).")
    md.append("")

    # Q2
    md.append("## Q2 · Campos de data preenchidos (US-SELL-018 + US-SELL-021)")
    md.append("")
    md.append(f"Total vendas: {q2['total_vendas']:,}")
    md.append("")
    md.append("| Campo | Preenchidas | % |")
    md.append("|-------|-----------:|--:|")
    for c in q2["campos"]:
        md.append(f"| `{c['campo']}` | {c['filled']:,} | **{c['pct']}%** |")
    md.append("")
    md.append("**Regra de qualificacao:**")
    md.append("- Campo >70% preenchido → cliente USA → mantem US-SELL-018/021 P1")
    md.append("- Campo 30-70% → considerar como opcional na UI")
    md.append("- Campo <30% → rebaixar pra P3 ou esconder por default na Grade")
    md.append("")

    # Q3 — agora 3 sub-secoes
    md.append("## Q3 · Status estruturado (US-SELL-020 + US-SELL-023)")
    md.append("")
    inline = q3.get("venda_situacao_inline")
    if inline:
        md.append(f"### Inline VENDA.{inline['campo']}")
        md.append(f"- Valores distintos: **{inline['distinct']}**")
        md.append("")
        md.append("| Situacao | Vendas |")
        md.append("|----------|-------:|")
        for sit, n in inline["top"]:
            label = sit if not anonimize else f"_redacted_{hashlib.sha1(str(sit).encode()).hexdigest()[:4]}_"
            md.append(f"| {label} | {n:,} |")
        md.append("")
    tab = q3.get("venda_situacao_table")
    if tab:
        md.append(f"### Tabela VENDA_SITUACAO (lookup)")
        md.append(f"- Linhas: **{tab['total_linhas']}** · cols: `{', '.join(tab['columns'][:6])}{'...' if len(tab['columns'])>6 else ''}`")
        md.append("")
    est = q3.get("venda_estagio")
    if est:
        md.append(f"### Tabela VENDA_ESTAGIO (FSM funil)")
        md.append(f"- Linhas: **{est['total_linhas']}** · cols: `{', '.join(est['columns'][:6])}{'...' if len(est['columns'])>6 else ''}`")
        md.append("")
    md.append("**Regra de qualificacao revisada (Wagner 2026-05-11):**")
    md.append("- VENDA_ESTAGIO populado (>0 linhas) → cliente USA FSM/funil de venda → US-SELL-023 P1")
    md.append("- VENDA_SITUACAO lookup populado → US-SELL-020 P1 (3 badges separados)")
    md.append("- Nenhum dos dois → status inexistente → US-SELL-020/023 P3")
    md.append("")

    # Q4
    md.append("## Q4 · Agrupamento implicito (US-SELL-019 + US-SELL-024)")
    md.append("")
    for table_key, label in [("venda_financeiro", "VENDA_FINANCEIRO"), ("financeiro", "FINANCEIRO")]:
        info = q4[table_key]
        if not info:
            md.append(f"- `{label}`: campo de agrupamento NAO encontrado")
            continue
        md.append(f"### {label}")
        md.append(f"- Campo agrupador: `{info['campo']}`")
        md.append(f"- Total linhas: {info['total']:,}")
        md.append(f"- Com agrupamento: {info['with_group']:,} ({info['pct']}%)")
        md.append(f"- Grupos distintos: {info['distinct_groups']:,}")
        md.append(f"- Tamanho medio do grupo: **{info['avg_size']}** linhas")
        md.append("")
    md.append("**Regra de qualificacao:**")
    md.append("- pct > 30% E avg_size > 2 → cliente USA agrupamento → US-SELL-019 P1 + US-SELL-024 P2")
    md.append("- pct < 10% → praticamente nao usa → US-SELL-019 vira P3, US-SELL-024 cancela")
    md.append("")

    # Q5
    md.append("## Q5 · Itens por venda (US-SELL-022 sub-linha produtos)")
    md.append("")
    if q5.get("table"):
        md.append(f"- Tabela: `{q5['table']}` FK `{q5.get('fk','?')}`")
        if q5.get("media_itens_por_venda") is not None:
            md.append(f"- Total itens: {q5.get('total_itens',0):,}")
            md.append(f"- Vendas com itens: {q5.get('vendas_com_itens',0):,}")
            md.append(f"- **Media: {q5['media_itens_por_venda']} itens/venda**")
            md.append("")
            md.append("| Faixa | Vendas |")
            md.append("|-------|-------:|")
            for k, v in q5["distribuicao"].items():
                md.append(f"| {k.replace('_', ' ')} | {v:,} |")
        else:
            md.append(f"- Sem FK clara — colunas sample: `{', '.join(q5.get('cols_sample',[])[:10])}`")
    else:
        md.append(f"- Tabela filha NAO localizada. Candidatos VENDA-related: `{q5.get('vendas_related_tables', [])}`")
    md.append("")
    md.append("**Regra de qualificacao:**")
    md.append("- media > 3 itens/venda OU pct(11+) > 15% → sub-linha vale → US-SELL-022 P2")
    md.append("- media = 1 → toda venda tem 1 item so → sub-linha eh ruido → US-SELL-022 cancela")
    md.append("")

    # Q6
    md.append("## Q6 · Range temporal (US-SELL-018 presets Dia/Semana/Mes/Ano)")
    md.append("")
    if q6.get("campo"):
        md.append(f"- Campo: `{q6['campo']}`")
        md.append(f"- Periodo: {q6['min']} → {q6['max']}")
        md.append("")
        md.append("| Janela | Vendas |")
        md.append("|--------|-------:|")
        for k, v in q6["windows"].items():
            md.append(f"| {k.replace('_', ' ')} | {v:,} |")
    md.append("")
    md.append("**Regra de qualificacao:**")
    md.append("- Se ultimos_30d > ultimos_7d * 3 → cliente trabalha em ciclo mensal → preset Mes prioritario")
    md.append("- Se ultimos_365d > ultimos_30d * 11 → cliente olha historicos longos → preset Ano importante")
    md.append("")

    # Descoberta de schema
    md.append("## Schema dump · VENDA — colunas relacionadas a UI Grade")
    md.append("")
    venda_cols = data.get("venda_columns", [])
    relevant = [c for c in venda_cols if any(
        k in c["name"].upper() for k in [
            "DT_", "DATA", "PROMET", "PREVIS", "ENTREGA",
            "STATUS", "SITUACAO", "ETAPA", "FUNIL",
            "GRUPO", "AGRUP", "LOTE",
        ])]
    md.append(f"Total colunas em VENDA: **{len(venda_cols)}**. Relevantes pra UI Grade ({len(relevant)}):")
    md.append("")
    md.append("| Coluna | Tipo |")
    md.append("|--------|------|")
    for c in relevant[:40]:
        md.append(f"| `{c['name']}` | {c['type']} |")
    md.append("")
    md.append(f"Tabelas candidatas a producao: `{', '.join(data.get('producao_candidates', []))}`")
    md.append("")

    # Q7 — EQUIPAMENTO_VEICULO (corrigido Wagner 2026-05-11)
    md.append("## Q7 · Veiculos cadastrados (EQUIPAMENTO_VEICULO — corrigido)")
    md.append("")
    md.append(f"Tabela: `{q7.get('main_table', 'EQUIPAMENTO_VEICULO')}` · Total veiculos cadastrados: **{q7.get('total_veiculos', 0):,}**")
    md.append("")
    if q7.get("fields"):
        md.append("| Campo | Preenchidos | % |")
        md.append("|-------|------------:|--:|")
        for field, info in q7["fields"].items():
            if "error" in info:
                md.append(f"| `{field}` | (erro) | — |")
            else:
                md.append(f"| `{field}` | {info['filled']:,} | **{info['pct']}%** |")
        md.append("")
    md.append("**Regra revisada:**")
    md.append("- total_veiculos = 0 → grafica pura → esconde colunas auto na Grade")
    md.append("- total_veiculos > 0 E PLACA > 30% → cliente USA veiculo → mostra colunas PLACA + dependent (PLACA2/CHASSI conforme uso)")
    md.append("- PLACA2 > 10% → cliente trabalha com cavalo+reboque/multiplas placas (Vargas) → mostra PLACA2 tambem")
    md.append("")

    # Q8 — PCP estruturado
    q8 = data.get("q8_pcp", {})
    md.append("## Q8 · PCP estruturado (US-SELL-023 sinal real)")
    md.append("")
    for table, info in q8.items():
        if "error" in info:
            md.append(f"- `{table}`: nao existe / erro")
        else:
            md.append(f"- `{table}`: **{info['total_linhas']:,}** linhas · **{info['distinct_vendas']:,}** vendas distintas com etapa/centro")
    md.append("")
    md.append("**Regra:** linhas > 100 em qualquer das duas tabelas → cliente USA PCP estruturado → US-SELL-023 P1")
    md.append("")

    # Q9 — VENDA_OBRA
    q9 = data.get("q9_obra", {})
    md.append("## Q9 · VENDA_OBRA (relevante pra Modules/ComunicacaoVisual)")
    md.append("")
    if "error" in q9:
        md.append("- VENDA_OBRA nao existe ou erro")
    else:
        md.append(f"- Linhas: **{q9.get('total_linhas', 0):,}** · Vendas com obra: **{q9.get('distinct_vendas_com_obra', 0):,}**")
    md.append("")
    md.append("**Regra:** vendas_com_obra > 0 → cliente tem instalacao fisica (gestao de obra) → relevante pra Modules/ComunicacaoVisual; possivel coluna 'Obra' na Grade Avancada")
    md.append("")

    # Resumo decisional
    md.append("---")
    md.append("")
    md.append("## Resumo decisional preliminar (sera consolidado em HEATMAP-CONSOLIDADO.md apos 3 clientes)")
    md.append("")
    md.append("| US | Status inicial | Sinal deste cliente |")
    md.append("|----|----------------|---------------------|")
    md.append(f"| US-SELL-018 (filtros multi-data) | a definir | Q2 mostra {len([c for c in q2['campos'] if c['pct']>30])}/{ len(q2['campos']) } campos de data com uso >30% |")
    md.append(f"| US-SELL-019 (agrupamento) | a definir | Q4 pct agrupamento: VF={q4.get('venda_financeiro',{}).get('pct','-') if q4.get('venda_financeiro') else '-'}% / FIN={q4.get('financeiro',{}).get('pct','-') if q4.get('financeiro') else '-'}% |")
    md.append(f"| US-SELL-020 (status badges) | a definir | Q3 distinct: {q3.get('distinct',0)} → {'separar' if q3.get('distinct',0)>5 else 'unico'} |")
    md.append(f"| US-SELL-021 (qual data) | a definir | Q2 mesmo sinal de 018 |")
    md.append(f"| US-SELL-022 (sub-linha produtos) | a definir | Q5 media itens/venda: {q5.get('media_itens_por_venda', '-')} |")
    md.append(f"| US-SELL-024 (is_grouped explicito) | a definir | Q4 mesmo sinal de 019 |")
    md.append("")
    md.append("> Final decisional depende de cruzar com mais 2 clientes (Vargas/Extreme/Gold) — single sample = ruido.")
    return "\n".join(md)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--alias", required=True, help="Ex: 192.168.0.55:Banco")
    ap.add_argument("--slug", required=True, help="Slug pra nome arquivo (ex: 01-wr2)")
    args = ap.parse_args()

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    data = collect_all(args.alias)

    # Raw JSON (gitignored)
    raw_path = OUTPUT_DIR / f"raw-{args.slug}.json"
    raw_path.write_text(json.dumps(data, indent=2, default=str), encoding="utf-8")
    print(f"  raw  → {raw_path}", file=sys.stderr)

    # Markdown com nomes (gitignored)
    com_path = OUTPUT_DIR / f"{args.slug}-grade-usage-COM-NOMES.md"
    com_path.write_text(render_md(data, anonimize=False, slug=args.slug), encoding="utf-8")
    print(f"  com  → {com_path}", file=sys.stderr)

    # Markdown anonimizado (commitavel)
    anon_path = OUTPUT_DIR / f"{args.slug}-grade-usage-anonimizada.md"
    anon_path.write_text(render_md(data, anonimize=True, slug=args.slug), encoding="utf-8")
    print(f"  anon → {anon_path}", file=sys.stderr)

    print("OK", file=sys.stderr)


if __name__ == "__main__":
    main()
