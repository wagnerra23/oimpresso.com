"""
financial_snapshot.py — OfficeImpresso (Firebird) → snapshot financeiro 12m/24m.

Roda as 8 queries canonicas do schema OFFICEIMPRESSO-FIREBIRD-SCHEMA.md contra
o banco Firebird de UM cliente legacy e gera 2 relatorios:

  - {OUT}/{slug}/03-financeiro-{DATA}.md             (anonimizado, commitavel)
  - {OUT}/{slug}/03-financeiro-{DATA}-COM-NOMES.md   (gitignored — PII real)

E um JSON bruto gitignored:
  - {OUT}/{slug}/raw-03-financeiro-{DATA}.json

Apenas SELECT — nunca INSERT/UPDATE/DELETE (restricao dura skill SKILL.md).

Refs:
- memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md (queries Q1-Q5 canonicas)
- .claude/skills/officeimpresso-financial-snapshot/SKILL.md (10 passos)
- memory/research/clientes-legacy-officeimpresso/_LGPD.md (anonimizacao sha1[:6])

Uso:
    python scripts/financial_snapshot.py \\
        --alias "192.168.0.55:D:\\DadosClientes\\Vargas\\Dados\\BANCO.FDB" \\
        --slug 02-vargas-recapagem

    python scripts/financial_snapshot.py --batch  # roda os 4 candidatos saudaveis
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import sys
from pathlib import Path
from typing import Any

import firebird.driver as fb

# Forca UTF-8 no stdout/stderr (Windows console default cp1252 crasha em emoji/acento)
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

# 4 candidatos saudaveis (Wagner 2026-05-11)
CANDIDATES = [
    ("02-vargas-recapagem", r"192.168.0.55:D:\DadosClientes\Vargas\Dados\BANCO.FDB", "Vargas"),
    ("03-extreme-grafica", r"192.168.0.55:D:\DadosClientes\Extreme\Dados\BANCO.FDB", "Extreme"),
    ("04-gold-comvis", r"192.168.0.55:D:\DadosClientes\Gold\Dados\BANCO.FDB", "Gold"),
    ("05-martinho-cacambas", r"192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB", "Martinho"),
]

OUTPUT_DIR = Path("memory/research/clientes-legacy-officeimpresso")

SCHEMA_FINGERPRINT = ["FINANCEIRO", "MENSALIDADE_FINANCEIRO", "CONTRATO", "PESSOAS"]


# ----------------------------------------------------------------------
# Anonimizacao canonica (LGPD §4)
# ----------------------------------------------------------------------

def anon(razao_social: str | None) -> str:
    if not razao_social:
        return "Cliente_NULL"
    h = hashlib.sha1(razao_social.encode("utf-8", errors="replace")).hexdigest()[:6].upper()
    return f"Cliente_{h}"


def fmt_brl(v: float | int | None) -> str:
    if v is None:
        return "R$ [redacted Tier 0]"
    return f"R$ {float(v):,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")


def fmt_int(v: int | None) -> str:
    return f"{int(v or 0):,}".replace(",", ".")


# ----------------------------------------------------------------------
# Conexao + schema fingerprint
# ----------------------------------------------------------------------

def connect(alias: str) -> fb.Connection:
    return fb.connect(alias, user="SYSDBA", password="masterkey", charset="WIN1252")


def schema_fingerprint(cur) -> dict:
    cur.execute(
        "SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
        "WHERE RDB$RELATION_NAME IN (?, ?, ?, ?)",
        SCHEMA_FINGERPRINT,
    )
    found = sorted([r[0] for r in cur.fetchall()])
    expected = sorted(SCHEMA_FINGERPRINT)
    return {"found": found, "expected": expected, "ok": found == expected}


# ----------------------------------------------------------------------
# 8 queries canonicas (do schema reference §3)
# ----------------------------------------------------------------------

def q1_sumario_12m(cur) -> dict:
    """Receita / Despesa / A Receber Vencidas / A Pagar Vencidas (12m)."""
    cur.execute(
        """
        SELECT
          (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
           WHERE TIPO='RECEBIDA' AND STATUS='ATIVO'
             AND DATAPAGTO BETWEEN DATEADD(YEAR, -1, CURRENT_DATE) AND CURRENT_DATE) AS REC_12M,
          (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
           WHERE TIPO='PAGA' AND STATUS='ATIVO'
             AND DATAPAGTO BETWEEN DATEADD(YEAR, -1, CURRENT_DATE) AND CURRENT_DATE) AS PAG_12M,
          (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
           WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
             AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS A_RECEBER_VENCIDAS,
          (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
           WHERE TIPO='A PAGAR' AND STATUS='ATIVO'
             AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS A_PAGAR_VENCIDAS
        FROM RDB$DATABASE
        """
    )
    rec, pag, ar_venc, ap_venc = cur.fetchone()
    return {
        "receita_12m": float(rec or 0),
        "despesa_12m": float(pag or 0),
        "a_receber_vencidas": float(ar_venc or 0),
        "a_pagar_vencidas": float(ap_venc or 0),
        "resultado_12m": float(rec or 0) - float(pag or 0),
    }


def q2_receita_mensal_24m(cur) -> list[dict]:
    """Receita recebida mensal nos ultimos 24 meses."""
    cur.execute(
        """
        SELECT EXTRACT(YEAR FROM DATAPAGTO) AS ANO,
               EXTRACT(MONTH FROM DATAPAGTO) AS MES,
               COUNT(*) AS N,
               SUM(VALOR) AS TOTAL
        FROM FINANCEIRO
        WHERE TIPO = 'RECEBIDA' AND STATUS = 'ATIVO'
          AND DATAPAGTO IS NOT NULL
          AND DATAPAGTO >= DATEADD(YEAR, -2, CURRENT_DATE)
        GROUP BY 1, 2
        ORDER BY 1, 2
        """
    )
    return [
        {"ano": int(r[0]), "mes": int(r[1]), "n": int(r[2]), "total": float(r[3] or 0)}
        for r in cur.fetchall()
    ]


def q3_top_clientes_12m(cur, limit: int = 30) -> list[dict]:
    """Top N clientes 12m por receita."""
    cur.execute(
        f"""
        SELECT FIRST {limit}
               RAZAOSOCIAL, COUNT(*), SUM(VALOR), MAX(DATAPAGTO)
        FROM FINANCEIRO
        WHERE TIPO='RECEBIDA' AND STATUS='ATIVO'
          AND DATAPAGTO BETWEEN DATEADD(YEAR, -1, CURRENT_DATE) AND CURRENT_DATE
          AND RAZAOSOCIAL IS NOT NULL
        GROUP BY RAZAOSOCIAL
        ORDER BY 3 DESC
        """
    )
    return [
        {"razao_social": r[0], "n": int(r[1]), "total": float(r[2] or 0), "ultimo_pgto": str(r[3]) if r[3] else None}
        for r in cur.fetchall()
    ]


def q4_inadimplencia(cur, limit: int = 20) -> list[dict]:
    """Clientes em atraso (A RECEBER vencidas) — top N por valor."""
    cur.execute(
        f"""
        SELECT FIRST {limit}
               RAZAOSOCIAL, COUNT(*), SUM(VALOR), MAX(VENCTO), MIN(VENCTO)
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
          AND RAZAOSOCIAL IS NOT NULL
        GROUP BY RAZAOSOCIAL
        ORDER BY 3 DESC
        """
    )
    return [
        {
            "razao_social": r[0],
            "n": int(r[1]),
            "total": float(r[2] or 0),
            "max_vencto": str(r[3]) if r[3] else None,
            "min_vencto": str(r[4]) if r[4] else None,
        }
        for r in cur.fetchall()
    ]


def q5_mrr_arr(cur) -> dict:
    """MRR atual (mes corrente em MENSALIDADE_FINANCEIRO) + ARR projetado (MRR * 12).

    Firebird 3.x nao tem LAST_DAY — usamos DATEADD(MONTH, 1, primeiro_dia) - 1 dia.
    """
    try:
        cur.execute(
            """
            SELECT COUNT(*), COALESCE(SUM(VALOR), 0)
            FROM MENSALIDADE_FINANCEIRO
            WHERE STATUS='ATIVO'
              AND DT_VENCTO >= DATEADD(DAY, 1-EXTRACT(DAY FROM CURRENT_DATE), CURRENT_DATE)
              AND DT_VENCTO <  DATEADD(MONTH, 1, DATEADD(DAY, 1-EXTRACT(DAY FROM CURRENT_DATE), CURRENT_DATE))
            """
        )
        n, mrr = cur.fetchone()
    except Exception as e:
        # Fallback Python-side: pega primeiro+ultimo dia do mes no host
        try:
            today = dt.date.today()
            first = today.replace(day=1)
            if first.month == 12:
                next_first = first.replace(year=first.year + 1, month=1)
            else:
                next_first = first.replace(month=first.month + 1)
            cur.execute(
                """
                SELECT COUNT(*), COALESCE(SUM(VALOR), 0)
                FROM MENSALIDADE_FINANCEIRO
                WHERE STATUS='ATIVO'
                  AND DT_VENCTO >= ?
                  AND DT_VENCTO <  ?
                """,
                [first, next_first],
            )
            n, mrr = cur.fetchone()
        except Exception as e2:
            return {"error": f"{e!r} / fallback: {e2!r}"[:200], "mrr": 0.0, "n_lancamentos": 0, "arr_projetado": 0.0}
    # Contratos ativos (alternativa de MRR contratado)
    try:
        cur.execute(
            "SELECT COUNT(*), COALESCE(SUM(VALOR),0) FROM CONTRATO WHERE ATIVO='S'"
        )
        n_contr, valor_contr = cur.fetchone()
    except Exception:
        n_contr, valor_contr = 0, 0
    return {
        "mrr": float(mrr or 0),
        "n_lancamentos": int(n or 0),
        "arr_projetado": float(mrr or 0) * 12,
        "contratos_ativos": int(n_contr or 0),
        "valor_contratado_ativo": float(valor_contr or 0),
    }


def q6_a_pagar_vencidas(cur, limit: int = 20) -> list[dict]:
    """Fornecedores com A PAGAR vencidas — top N por valor."""
    cur.execute(
        f"""
        SELECT FIRST {limit}
               RAZAOSOCIAL, COUNT(*), SUM(VALOR), MAX(VENCTO), MIN(VENCTO)
        FROM FINANCEIRO
        WHERE TIPO='A PAGAR' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
          AND RAZAOSOCIAL IS NOT NULL
        GROUP BY RAZAOSOCIAL
        ORDER BY 3 DESC
        """
    )
    return [
        {
            "razao_social": r[0],
            "n": int(r[1]),
            "total": float(r[2] or 0),
            "max_vencto": str(r[3]) if r[3] else None,
            "min_vencto": str(r[4]) if r[4] else None,
        }
        for r in cur.fetchall()
    ]


def q7_despesas_mensais_12m(cur) -> list[dict]:
    """Despesas pagas mensal nos ultimos 12 meses."""
    cur.execute(
        """
        SELECT EXTRACT(YEAR FROM DATAPAGTO) AS ANO,
               EXTRACT(MONTH FROM DATAPAGTO) AS MES,
               COUNT(*) AS N,
               SUM(VALOR) AS TOTAL
        FROM FINANCEIRO
        WHERE TIPO='PAGA' AND STATUS='ATIVO'
          AND DATAPAGTO IS NOT NULL
          AND DATAPAGTO >= DATEADD(YEAR, -1, CURRENT_DATE)
        GROUP BY 1, 2
        ORDER BY 1, 2
        """
    )
    return [
        {"ano": int(r[0]), "mes": int(r[1]), "n": int(r[2]), "total": float(r[3] or 0)}
        for r in cur.fetchall()
    ]


def q8_ticket_distribuicao(cur) -> dict:
    """Distribuicao do ticket (valor por lancamento recebido) 12m."""
    cur.execute(
        """
        SELECT VALOR FROM FINANCEIRO
        WHERE TIPO='RECEBIDA' AND STATUS='ATIVO'
          AND DATAPAGTO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE
          AND VALOR > 0
        """
    )
    valores = sorted([float(r[0]) for r in cur.fetchall()])
    n = len(valores)
    if n == 0:
        return {"n": 0, "ticket_medio": 0, "p50": 0, "p90": 0, "min": 0, "max": 0}
    soma = sum(valores)
    bins = {
        "ate_100": 0, "100_500": 0, "500_1000": 0,
        "1000_5000": 0, "5000_10000": 0, "10000_plus": 0,
    }
    for v in valores:
        if v <= 100: bins["ate_100"] += 1
        elif v <= 500: bins["100_500"] += 1
        elif v <= 1000: bins["500_1000"] += 1
        elif v <= 5000: bins["1000_5000"] += 1
        elif v <= 10000: bins["5000_10000"] += 1
        else: bins["10000_plus"] += 1
    return {
        "n": n,
        "ticket_medio": soma / n,
        "p50": valores[n // 2],
        "p90": valores[int(n * 0.9)],
        "p99": valores[int(n * 0.99)] if n >= 100 else valores[-1],
        "min": valores[0],
        "max": valores[-1],
        "distribuicao": bins,
    }


# ----------------------------------------------------------------------
# Coleta + flags
# ----------------------------------------------------------------------

def collect(alias: str, slug: str) -> dict:
    print(f"[{slug}] Conectando {alias} ...", file=sys.stderr)
    con = connect(alias)
    cur = con.cursor()

    fp = schema_fingerprint(cur)
    if not fp["ok"]:
        print(f"[{slug}] ERRO: schema fingerprint NOK. Found={fp['found']}", file=sys.stderr)
        con.close()
        raise RuntimeError(f"schema fingerprint NOK em {alias}")

    print(f"[{slug}] Q1 sumario 12m ...", file=sys.stderr)
    q1 = q1_sumario_12m(cur)
    print(f"[{slug}] Q2 receita mensal 24m ...", file=sys.stderr)
    q2 = q2_receita_mensal_24m(cur)
    print(f"[{slug}] Q3 top clientes 12m ...", file=sys.stderr)
    q3 = q3_top_clientes_12m(cur)
    print(f"[{slug}] Q4 inadimplencia ...", file=sys.stderr)
    q4 = q4_inadimplencia(cur)
    print(f"[{slug}] Q5 MRR/ARR ...", file=sys.stderr)
    q5 = q5_mrr_arr(cur)
    print(f"[{slug}] Q6 a pagar vencidas ...", file=sys.stderr)
    q6 = q6_a_pagar_vencidas(cur)
    print(f"[{slug}] Q7 despesas mensais 12m ...", file=sys.stderr)
    q7 = q7_despesas_mensais_12m(cur)
    print(f"[{slug}] Q8 ticket distribuicao ...", file=sys.stderr)
    q8 = q8_ticket_distribuicao(cur)

    con.close()

    flags = detect_flags(q1, q3, q4, q5)

    return {
        "slug": slug,
        "alias": alias,
        "collected_at": dt.datetime.now().isoformat(),
        "schema_fingerprint": fp,
        "q1_sumario_12m": q1,
        "q2_receita_mensal_24m": q2,
        "q3_top_clientes_12m": q3,
        "q4_inadimplencia": q4,
        "q5_mrr_arr": q5,
        "q6_a_pagar_vencidas": q6,
        "q7_despesas_mensais_12m": q7,
        "q8_ticket_distribuicao": q8,
        "flags": flags,
    }


def detect_flags(q1: dict, q3: list, q4: list, q5: dict) -> dict:
    flags = []
    if q1["resultado_12m"] < 0:
        flags.append({
            "tipo": "deficit_operacional",
            "severidade": "vermelho",
            "msg": f"Resultado 12m negativo: {fmt_brl(q1['resultado_12m'])}",
        })
    if q1["a_pagar_vencidas"] > q1["a_receber_vencidas"]:
        flags.append({
            "tipo": "pagar_maior_que_receber",
            "severidade": "amarelo",
            "msg": f"A pagar vencidas ({fmt_brl(q1['a_pagar_vencidas'])}) > A receber vencidas ({fmt_brl(q1['a_receber_vencidas'])})",
        })
    if q1["receita_12m"] > 0:
        pct_inadim = q1["a_receber_vencidas"] / q1["receita_12m"]
        if pct_inadim > 0.3:
            flags.append({
                "tipo": "inadimplencia_alta",
                "severidade": "vermelho",
                "msg": f"Inadimplencia (a receber vencidas) = {pct_inadim:.1%} da receita 12m",
            })
        elif pct_inadim > 0.15:
            flags.append({
                "tipo": "inadimplencia_moderada",
                "severidade": "amarelo",
                "msg": f"Inadimplencia (a receber vencidas) = {pct_inadim:.1%} da receita 12m",
            })
    if q3 and q1["receita_12m"] > 0:
        top1_pct = q3[0]["total"] / q1["receita_12m"]
        if top1_pct > 0.5:
            flags.append({
                "tipo": "concentracao_top_1",
                "severidade": "vermelho",
                "msg": f"Top 1 cliente concentra {top1_pct:.1%} da receita",
            })
        elif top1_pct > 0.3:
            flags.append({
                "tipo": "concentracao_top_1_moderada",
                "severidade": "amarelo",
                "msg": f"Top 1 cliente concentra {top1_pct:.1%} da receita",
            })
    if q5.get("mrr", 0) <= 0 and q5.get("contratos_ativos", 0) == 0:
        flags.append({
            "tipo": "sem_mrr",
            "severidade": "info",
            "msg": "Sem MRR no mes corrente nem contratos ativos — operacao puramente por OS/avulsa",
        })
    return {"items": flags, "total": len(flags)}


# ----------------------------------------------------------------------
# Render markdown
# ----------------------------------------------------------------------

def render_md(data: dict, *, anonimize: bool) -> str:
    slug = data["slug"]
    q1 = data["q1_sumario_12m"]
    q2 = data["q2_receita_mensal_24m"]
    q3 = data["q3_top_clientes_12m"]
    q4 = data["q4_inadimplencia"]
    q5 = data["q5_mrr_arr"]
    q6 = data["q6_a_pagar_vencidas"]
    q7 = data["q7_despesas_mensais_12m"]
    q8 = data["q8_ticket_distribuicao"]
    flags = data["flags"]

    md = []
    sufix = "anonimizado" if anonimize else "com nomes — gitignored"
    md.append(f"# Snapshot financeiro — `{slug}` ({sufix})")
    md.append("")
    md.append(f"> Coletado: {data['collected_at']}")
    if not anonimize:
        md.append(f"> Banco: `{data['alias']}`")
    md.append(f"> Schema fingerprint: {'OK (4/4 tabelas canonicas)' if data['schema_fingerprint']['ok'] else 'FAIL'}")
    md.append(f"> LGPD: anonimizacao via sha1(razao_social)[:6] ([_LGPD.md](../_LGPD.md))")
    md.append("")

    # Flags primeiro (visibilidade)
    md.append("## Flags detectadas")
    md.append("")
    if flags["total"] == 0:
        md.append("Nenhuma flag detectada. Saude financeira aparente boa.")
    else:
        md.append("| Severidade | Tipo | Mensagem |")
        md.append("|------------|------|----------|")
        for f in flags["items"]:
            sev = {"vermelho": "🔴", "amarelo": "🟡", "info": "ℹ️"}.get(f["severidade"], "·")
            md.append(f"| {sev} {f['severidade']} | `{f['tipo']}` | {f['msg']} |")
    md.append("")

    # Q1
    md.append("## Q1 · Sumario financeiro 12m")
    md.append("")
    md.append("| Metrica | Valor |")
    md.append("|---------|------:|")
    md.append(f"| Receita recebida 12m | **{fmt_brl(q1['receita_12m'])}** |")
    md.append(f"| Despesa paga 12m | {fmt_brl(q1['despesa_12m'])} |")
    md.append(f"| **Resultado 12m** | **{fmt_brl(q1['resultado_12m'])}** |")
    md.append(f"| A receber vencidas | {fmt_brl(q1['a_receber_vencidas'])} |")
    md.append(f"| A pagar vencidas | {fmt_brl(q1['a_pagar_vencidas'])} |")
    md.append("")

    # Q5 (MRR/ARR — destaque)
    md.append("## Q5 · MRR / ARR")
    md.append("")
    if "error" in q5:
        md.append(f"⚠️ Erro coletando MENSALIDADE_FINANCEIRO: `{q5['error']}`")
    md.append("| Metrica | Valor |")
    md.append("|---------|------:|")
    md.append(f"| MRR (mes corrente) | **{fmt_brl(q5.get('mrr', 0))}** |")
    md.append(f"| Lancamentos no mes | {fmt_int(q5.get('n_lancamentos', 0))} |")
    md.append(f"| ARR projetado (MRR × 12) | **{fmt_brl(q5.get('arr_projetado', 0))}** |")
    md.append(f"| Contratos ativos | {fmt_int(q5.get('contratos_ativos', 0))} |")
    md.append(f"| Valor contratado ativo | {fmt_brl(q5.get('valor_contratado_ativo', 0))} |")
    md.append("")

    # Q2 — receita mensal 24m
    md.append("## Q2 · Receita mensal 24m (recebida)")
    md.append("")
    if not q2:
        md.append("Sem dados.")
    else:
        md.append("| Ano | Mes | Lancamentos | Total |")
        md.append("|----:|----:|------------:|------:|")
        for row in q2[-24:]:
            md.append(f"| {row['ano']} | {row['mes']:02d} | {fmt_int(row['n'])} | {fmt_brl(row['total'])} |")
        meses = len(q2)
        total = sum(r["total"] for r in q2)
        media = total / meses if meses else 0
        md.append("")
        md.append(f"- Meses com receita: **{meses}** · media mensal: **{fmt_brl(media)}**")
    md.append("")

    # Q3 — top clientes 12m
    md.append("## Q3 · Top 30 clientes 12m (por receita)")
    md.append("")
    if not q3:
        md.append("Sem dados.")
    else:
        md.append("| # | Cliente | Lancamentos | Receita 12m | Ultimo pgto |")
        md.append("|--:|---------|------------:|------------:|:------------|")
        for i, c in enumerate(q3, 1):
            label = anon(c["razao_social"]) if anonimize else c["razao_social"]
            md.append(f"| {i} | {label} | {fmt_int(c['n'])} | {fmt_brl(c['total'])} | {c['ultimo_pgto'] or '-'} |")
        if q1["receita_12m"] > 0:
            top10_sum = sum(c["total"] for c in q3[:10])
            md.append("")
            md.append(f"- **Concentracao Top 10:** {top10_sum / q1['receita_12m']:.1%} da receita 12m")
    md.append("")

    # Q4 — inadimplencia
    md.append("## Q4 · Inadimplencia (top 20 clientes em atraso)")
    md.append("")
    if not q4:
        md.append("Nenhum cliente com A RECEBER vencidas.")
    else:
        md.append("| # | Cliente | Titulos | Total vencido | Atraso desde |")
        md.append("|--:|---------|--------:|--------------:|:-------------|")
        for i, c in enumerate(q4, 1):
            label = anon(c["razao_social"]) if anonimize else c["razao_social"]
            md.append(f"| {i} | {label} | {fmt_int(c['n'])} | {fmt_brl(c['total'])} | {c['min_vencto'] or '-'} |")
    md.append("")

    # Q6 — a pagar vencidas
    md.append("## Q6 · A pagar vencidas (top 20 fornecedores)")
    md.append("")
    if not q6:
        md.append("Nenhum fornecedor com A PAGAR vencido.")
    else:
        md.append("| # | Fornecedor | Titulos | Total vencido | Atraso desde |")
        md.append("|--:|------------|--------:|--------------:|:-------------|")
        for i, c in enumerate(q6, 1):
            label = anon(c["razao_social"]) if anonimize else c["razao_social"]
            md.append(f"| {i} | {label} | {fmt_int(c['n'])} | {fmt_brl(c['total'])} | {c['min_vencto'] or '-'} |")
    md.append("")

    # Q7 — despesas mensais 12m
    md.append("## Q7 · Despesas pagas mensal 12m")
    md.append("")
    if not q7:
        md.append("Sem dados.")
    else:
        md.append("| Ano | Mes | Lancamentos | Total |")
        md.append("|----:|----:|------------:|------:|")
        for row in q7:
            md.append(f"| {row['ano']} | {row['mes']:02d} | {fmt_int(row['n'])} | {fmt_brl(row['total'])} |")
    md.append("")

    # Q8 — ticket distribuicao
    md.append("## Q8 · Distribuicao do ticket (receita 12m)")
    md.append("")
    md.append("| Metrica | Valor |")
    md.append("|---------|------:|")
    md.append(f"| Lancamentos recebidos 12m | {fmt_int(q8['n'])} |")
    md.append(f"| Ticket medio | {fmt_brl(q8['ticket_medio'])} |")
    md.append(f"| Mediana (p50) | {fmt_brl(q8['p50'])} |")
    md.append(f"| p90 | {fmt_brl(q8['p90'])} |")
    md.append(f"| Maior ticket | {fmt_brl(q8['max'])} |")
    md.append("")
    if "distribuicao" in q8 and q8["distribuicao"]:
        md.append("| Faixa | Lancamentos |")
        md.append("|-------|------------:|")
        labels = [
            ("ate_100", "Ate R$ [redacted Tier 0]"),
            ("100_500", "R$ [redacted Tier 0]-500"),
            ("500_1000", "R$ [redacted Tier 0]-1k"),
            ("1000_5000", "R$ [redacted Tier 0]k-5k"),
            ("5000_10000", "R$ [redacted Tier 0]k-10k"),
            ("10000_plus", "> R$ [redacted Tier 0]k"),
        ]
        for key, label in labels:
            md.append(f"| {label} | {fmt_int(q8['distribuicao'].get(key, 0))} |")
    md.append("")

    md.append("---")
    md.append("")
    md.append(f"Gerado por `scripts/financial_snapshot.py` (skill `officeimpresso-financial-snapshot`). "
              f"Apenas SELECT — sem mutacao no banco.")
    return "\n".join(md) + "\n"


# ----------------------------------------------------------------------
# Output
# ----------------------------------------------------------------------

def write_outputs(data: dict, *, out_root: Path, today: str) -> tuple[Path, Path, Path]:
    slug_dir = out_root / data["slug"]
    slug_dir.mkdir(parents=True, exist_ok=True)
    anon_path = slug_dir / f"03-financeiro-{today}.md"
    nomes_path = slug_dir / f"03-financeiro-{today}-COM-NOMES.md"
    raw_path = slug_dir / f"raw-03-financeiro-{today}.json"

    anon_path.write_text(render_md(data, anonimize=True), encoding="utf-8")
    nomes_path.write_text(render_md(data, anonimize=False), encoding="utf-8")
    raw_path.write_text(json.dumps(data, ensure_ascii=False, indent=2, default=str), encoding="utf-8")

    return anon_path, nomes_path, raw_path


# ----------------------------------------------------------------------
# Main
# ----------------------------------------------------------------------

def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", help="Alias/path Firebird (ex: 192.168.0.55:D:\\X\\BANCO.FDB)")
    parser.add_argument("--slug", help="Slug pasta destino (ex: 02-vargas-recapagem)")
    parser.add_argument("--batch", action="store_true",
                        help="Roda os 4 candidatos saudaveis fixos no script")
    parser.add_argument("--out", default=str(OUTPUT_DIR),
                        help=f"Pasta raiz output (default: {OUTPUT_DIR})")
    parser.add_argument("--today", default=dt.date.today().strftime("%Y-%m-%d"),
                        help="Data pro nome do arquivo (default: hoje)")
    args = parser.parse_args()

    out_root = Path(args.out)
    today = args.today

    runs = []
    if args.batch:
        runs = [(slug, alias, label) for slug, alias, label in CANDIDATES]
    elif args.alias and args.slug:
        runs = [(args.slug, args.alias, args.slug)]
    else:
        parser.error("Use --batch ou (--alias + --slug)")
        return 1

    summary: list[dict] = []
    for slug, alias, label in runs:
        try:
            data = collect(alias, slug)
            anon_p, nomes_p, raw_p = write_outputs(data, out_root=out_root, today=today)
            print(f"[{slug}] ✓ {anon_p.relative_to(Path.cwd()) if anon_p.is_absolute() else anon_p}")
            print(f"[{slug}]   {nomes_p.relative_to(Path.cwd()) if nomes_p.is_absolute() else nomes_p}")
            print(f"[{slug}]   {raw_p.relative_to(Path.cwd()) if raw_p.is_absolute() else raw_p}")
            summary.append({
                "slug": slug,
                "label": label,
                "ok": True,
                "receita_12m": data["q1_sumario_12m"]["receita_12m"],
                "resultado_12m": data["q1_sumario_12m"]["resultado_12m"],
                "mrr": data["q5_mrr_arr"].get("mrr", 0),
                "arr_projetado": data["q5_mrr_arr"].get("arr_projetado", 0),
                "a_receber_vencidas": data["q1_sumario_12m"]["a_receber_vencidas"],
                "a_pagar_vencidas": data["q1_sumario_12m"]["a_pagar_vencidas"],
                "flags": data["flags"]["total"],
                "top1_label": (data["q3_top_clientes_12m"][0]["razao_social"]
                               if data["q3_top_clientes_12m"] else None),
                "top1_total": (data["q3_top_clientes_12m"][0]["total"]
                               if data["q3_top_clientes_12m"] else 0),
            })
        except Exception as e:
            print(f"[{slug}] ❌ ERRO: {e!r}", file=sys.stderr)
            summary.append({"slug": slug, "label": label, "ok": False, "error": str(e)[:200]})

    # Sumario CLI
    print("\n=== SUMARIO ===")
    for s in summary:
        if s.get("ok"):
            print(f"  {s['slug']:<26} receita_12m={fmt_brl(s['receita_12m'])} "
                  f"resultado={fmt_brl(s['resultado_12m'])} mrr={fmt_brl(s['mrr'])} flags={s['flags']}")
        else:
            print(f"  {s['slug']:<26} FAIL: {s.get('error', 'unknown')}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
