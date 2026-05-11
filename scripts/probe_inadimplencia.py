"""
probe_inadimplencia.py — Investigacao adversarial Martinho Cacambas.

Pergunta: a inadimplencia 76,7% e' REAL ou USO RUIM DO SISTEMA?

Roda 10 queries especificas pra distinguir "cliente nao paga" vs
"cliente nao baixa o sistema":

- Q-INV-1..4 → sinais de inadimplencia REAL
- Q-UR-1..6  → sinais de uso ruim do sistema

Output: dict JSON.

Apenas SELECT — nunca INSERT/UPDATE/DELETE.

Refs:
- scripts/financial_snapshot.py (padrao 8 queries canonicas)
- memory/research/clientes-legacy-officeimpresso/_LGPD.md (anonimizacao)
- memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/03-financeiro-2026-05-11.md
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import sys
from pathlib import Path

import firebird.driver as fb

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")


ALIAS = r"192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB"
SLUG = "05-martinho-cacambas"
OUT_DIR = Path("memory/research/clientes-legacy-officeimpresso") / SLUG


def anon(s):
    if not s:
        return "Cliente_NULL"
    h = hashlib.sha1(s.encode("utf-8", errors="replace")).hexdigest()[:6].upper()
    return f"Cliente_{h}"


def connect():
    return fb.connect(ALIAS, user="SYSDBA", password="masterkey", charset="WIN1252")


# ----------------------------------------------------------------------
# Q-INV (inadimplencia REAL)
# ----------------------------------------------------------------------

def q_inv_1_aging(cur):
    cur.execute(
        """
        SELECT
          COUNT(*),
          AVG(DATEDIFF(DAY, VENCTO, CURRENT_DATE)),
          MIN(VENCTO),
          MAX(VENCTO),
          SUM(VALOR)
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
        """
    )
    n, avg_days, min_v, max_v, soma = cur.fetchone()
    cur.execute(
        """
        SELECT
          DATEDIFF(DAY, VENCTO, CURRENT_DATE) AS DIAS_ATRASO,
          VALOR
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
        """
    )
    buckets = {
        "0_30": {"n": 0, "valor": 0.0},
        "30_60": {"n": 0, "valor": 0.0},
        "60_180": {"n": 0, "valor": 0.0},
        "180_365": {"n": 0, "valor": 0.0},
        "365_plus": {"n": 0, "valor": 0.0},
    }
    for dias, valor in cur.fetchall():
        d = int(dias or 0)
        v = float(valor or 0)
        if d <= 30:
            buckets["0_30"]["n"] += 1
            buckets["0_30"]["valor"] += v
        elif d <= 60:
            buckets["30_60"]["n"] += 1
            buckets["30_60"]["valor"] += v
        elif d <= 180:
            buckets["60_180"]["n"] += 1
            buckets["60_180"]["valor"] += v
        elif d <= 365:
            buckets["180_365"]["n"] += 1
            buckets["180_365"]["valor"] += v
        else:
            buckets["365_plus"]["n"] += 1
            buckets["365_plus"]["valor"] += v
    return {
        "n_titulos": int(n or 0),
        "avg_dias_atraso": float(avg_days or 0),
        "min_vencto": str(min_v),
        "max_vencto": str(max_v),
        "total_vencido": float(soma or 0),
        "buckets": buckets,
    }


def q_inv_2_distribuicao_inadim(cur):
    cur.execute(
        """
        SELECT RAZAOSOCIAL, COUNT(*), SUM(VALOR)
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
          AND RAZAOSOCIAL IS NOT NULL
        GROUP BY RAZAOSOCIAL
        ORDER BY 3 DESC
        """
    )
    rows = [(r[0], int(r[1]), float(r[2] or 0)) for r in cur.fetchall()]
    total = sum(r[2] for r in rows)
    if total == 0:
        return {"n_clientes": 0, "total": 0, "top1_pct": 0, "top5_pct": 0, "top20_pct": 0, "top20": []}
    top1 = sum(r[2] for r in rows[:1])
    top5 = sum(r[2] for r in rows[:5])
    top20 = sum(r[2] for r in rows[:20])
    return {
        "n_clientes": len(rows),
        "total": total,
        "top1_pct": top1 / total,
        "top5_pct": top5 / total,
        "top20_pct": top20 / total,
        "top20": [
            {"razao_social": r[0], "n": r[1], "total": r[2]}
            for r in rows[:20]
        ],
    }


def q_inv_3_taxa_baixa(cur):
    cur.execute(
        """
        SELECT
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO IN ('A RECEBER','RECEBIDA')
              AND EMISSAO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE
              AND STATUS LIKE 'ATIVO%') AS EMITIDOS,
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO='RECEBIDA'
              AND EMISSAO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE
              AND STATUS LIKE 'ATIVO%'
              AND DATAPAGTO IS NOT NULL) AS RECEBIDAS,
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
              AND EMISSAO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE
              AND DATAPAGTO IS NULL) AS PENDENTES,
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND STATUS='INATIVO CANCELADA'
              AND EMISSAO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE) AS CANCELADAS
        FROM RDB$DATABASE
        """
    )
    emit, receb, pend, canc = cur.fetchone()
    emit, receb, pend, canc = int(emit or 0), int(receb or 0), int(pend or 0), int(canc or 0)
    return {
        "emitidos_12m": emit,
        "recebidas_12m": receb,
        "pendentes_12m": pend,
        "canceladas_12m": canc,
        "taxa_baixa": (receb / emit) if emit else 0,
    }


def q_inv_4_boletos(cur):
    cur.execute(
        """
        SELECT
          COUNT(*) AS TOTAL,
          SUM(CASE WHEN f.TIPO='RECEBIDA' THEN 1 ELSE 0 END) AS PAGOS,
          SUM(CASE WHEN f.TIPO='A RECEBER' AND f.STATUS='ATIVO' THEN 1 ELSE 0 END) AS ABERTOS,
          SUM(CASE WHEN f.STATUS LIKE 'INATIVO%' THEN 1 ELSE 0 END) AS CANCELADOS,
          SUM(CASE WHEN f.TIPO='RECEBIDA' THEN f.VALOR ELSE 0 END) AS VALOR_PAGO,
          SUM(CASE WHEN f.TIPO='A RECEBER' AND f.STATUS='ATIVO'
                   AND f.VENCTO < CURRENT_DATE AND f.DATAPAGTO IS NULL
                   THEN f.VALOR ELSE 0 END) AS VALOR_VENCIDO
        FROM BOLETOS b
        JOIN FINANCEIRO f ON f.CODIGO = b.CODFINANCEIRO
        WHERE b.ATIVO = 'S'
          AND f.EMISSAO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE
        """
    )
    row = cur.fetchone()
    total, pagos, abertos, canc, val_pago, val_venc = row
    total = int(total or 0)
    pagos = int(pagos or 0)
    return {
        "boletos_emitidos_12m": total,
        "boletos_pagos": pagos,
        "boletos_abertos": int(abertos or 0),
        "boletos_cancelados": int(canc or 0),
        "valor_pago": float(val_pago or 0),
        "valor_vencido": float(val_venc or 0),
        "taxa_baixa_boleto": (pagos / total) if total else 0,
    }


# ----------------------------------------------------------------------
# Q-UR (uso ruim do sistema)
# ----------------------------------------------------------------------

def q_ur_1_motivo_exclusao(cur):
    cur.execute(
        """
        SELECT
          (SELECT COUNT(*) FROM FINANCEIRO) AS TOTAL,
          (SELECT COUNT(*) FROM FINANCEIRO WHERE DT_EXCLUSAO IS NOT NULL OR MOTIVO_EXCLUSAO IS NOT NULL) AS EXCLUIDOS,
          (SELECT COUNT(*) FROM FINANCEIRO WHERE STATUS LIKE 'INATIVO%') AS STATUS_INATIVO
        FROM RDB$DATABASE
        """
    )
    total, excluidos, inativos = cur.fetchone()
    cur.execute(
        """
        SELECT FIRST 15
          MOTIVO_EXCLUSAO, COUNT(*)
        FROM FINANCEIRO
        WHERE MOTIVO_EXCLUSAO IS NOT NULL AND MOTIVO_EXCLUSAO <> ''
        GROUP BY MOTIVO_EXCLUSAO
        ORDER BY 2 DESC
        """
    )
    motivos = [(r[0], int(r[1])) for r in cur.fetchall()]
    return {
        "total_financeiro": int(total or 0),
        "total_excluidos": int(excluidos or 0),
        "total_inativos": int(inativos or 0),
        "pct_excluidos": (int(excluidos or 0) / int(total or 1)) if total else 0,
        "pct_inativos": (int(inativos or 0) / int(total or 1)) if total else 0,
        "top_motivos": [{"motivo": m, "n": n} for m, n in motivos],
    }


def q_ur_2_excluidos_que_aparecem(cur):
    cur.execute(
        """
        SELECT
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND DT_EXCLUSAO IS NOT NULL
              AND STATUS='ATIVO') AS EXCL_ATIVOS,
          (SELECT SUM(VALOR) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND DT_EXCLUSAO IS NOT NULL
              AND STATUS='ATIVO') AS EXCL_ATIVOS_VALOR,
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
              AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
              AND DT_EXCLUSAO IS NOT NULL) AS VENCIDOS_EXCL,
          (SELECT SUM(VALOR) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
              AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
              AND DT_EXCLUSAO IS NOT NULL) AS VENCIDOS_EXCL_VALOR
        FROM RDB$DATABASE
        """
    )
    a, b, c, d = cur.fetchone()
    return {
        "excluidos_mas_ativos": int(a or 0),
        "valor_excluidos_mas_ativos": float(b or 0),
        "vencidos_e_excluidos": int(c or 0),
        "valor_vencidos_e_excluidos": float(d or 0),
    }


def q_ur_3_previsao_status(cur):
    cur.execute(
        """
        SELECT STATUS, COUNT(*), SUM(VALOR)
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
        GROUP BY STATUS
        ORDER BY 3 DESC NULLS LAST
        """
    )
    rows = [(r[0], int(r[1]), float(r[2] or 0)) for r in cur.fetchall()]
    cur.execute(
        """
        SELECT
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND LANCAMENTO_FUTURO='S') AS LF_S,
          (SELECT SUM(VALOR) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND LANCAMENTO_FUTURO='S'
              AND STATUS LIKE 'ATIVO%'
              AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS LF_S_VENC,
          (SELECT COUNT(*) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND STATUS='ATIVO PREVISAO') AS PREV_STATUS,
          (SELECT SUM(VALOR) FROM FINANCEIRO
            WHERE TIPO='A RECEBER' AND STATUS='ATIVO PREVISAO'
              AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS PREV_STATUS_VENC
        FROM RDB$DATABASE
        """
    )
    lf, lfv, ps, psv = cur.fetchone()
    return {
        "por_status": [{"status": r[0], "n": r[1], "valor": r[2]} for r in rows],
        "lancamento_futuro_total": int(lf or 0),
        "lancamento_futuro_vencidas_valor": float(lfv or 0),
        "status_previsao_total": int(ps or 0),
        "status_previsao_vencidas_valor": float(psv or 0),
    }


def q_ur_4_venda_vs_financeiro(cur):
    cur.execute(
        """
        SELECT
          v.CODIGO, v.TOTAL, COALESCE(SUM(vf.VALOR),0), v.DT_EMISSAO
        FROM VENDA v
        LEFT JOIN VENDA_FINANCEIRO vf ON vf.CODVENDA = v.CODIGO
          AND vf.STATUS LIKE 'ATIVO%'
        WHERE v.DT_EMISSAO >= DATEADD(YEAR,-1,CURRENT_DATE)
          AND v.TOTAL > 0
          AND v.ATIVO='S'
        GROUP BY v.CODIGO, v.TOTAL, v.DT_EMISSAO
        """
    )
    n_total = 0
    n_match = 0
    n_diff = 0
    n_sem_financeiro = 0
    valor_total = 0.0
    valor_diff = 0.0
    valor_sem_financeiro = 0.0
    for cod, total, soma_f, _ in cur.fetchall():
        total = float(total or 0)
        soma_f = float(soma_f or 0)
        n_total += 1
        valor_total += total
        if soma_f == 0:
            n_sem_financeiro += 1
            valor_sem_financeiro += total
        elif abs(total - soma_f) < 0.01:
            n_match += 1
        else:
            n_diff += 1
            valor_diff += abs(total - soma_f)
    return {
        "n_vendas_12m": n_total,
        "valor_total": valor_total,
        "n_match_exato": n_match,
        "n_com_diff": n_diff,
        "n_sem_financeiro": n_sem_financeiro,
        "valor_sem_financeiro": valor_sem_financeiro,
        "valor_diff": valor_diff,
        "pct_sem_financeiro": (n_sem_financeiro / n_total) if n_total else 0,
        "pct_diff": (n_diff / n_total) if n_total else 0,
    }


def q_ur_5_razao_social_divergente(cur):
    cur.execute(
        """
        SELECT
          COUNT(*),
          SUM(VALOR)
        FROM FINANCEIRO f
        WHERE f.TIPO='A RECEBER' AND f.STATUS='ATIVO'
          AND f.VENCTO < CURRENT_DATE AND f.DATAPAGTO IS NULL
          AND f.RAZAOSOCIAL IS NOT NULL
          AND NOT EXISTS (
            SELECT 1 FROM PESSOAS p
            WHERE UPPER(TRIM(p.RAZAOSOCIAL)) = UPPER(TRIM(f.RAZAOSOCIAL))
          )
        """
    )
    n, soma = cur.fetchone()
    cur.execute(
        """
        SELECT COUNT(*), SUM(VALOR)
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
        """
    )
    total_n, total_v = cur.fetchone()
    return {
        "n_orphan": int(n or 0),
        "valor_orphan": float(soma or 0),
        "total_n": int(total_n or 0),
        "total_valor": float(total_v or 0),
        "pct_orphan": (int(n or 0) / int(total_n or 1)) if total_n else 0,
        "pct_valor_orphan": (float(soma or 0) / float(total_v or 1)) if total_v else 0,
    }


def q_ur_6_boleto_coverage(cur):
    # Versao simplificada — agrega 2 contas em uma query mais leve
    cur.execute(
        """
        SELECT
          COUNT(*) AS AR_VENC,
          SUM(CASE WHEN EXISTS (SELECT 1 FROM BOLETOS b
                                WHERE b.CODFINANCEIRO=f.CODIGO AND b.ATIVO='S')
                   THEN 1 ELSE 0 END) AS AR_VENC_BOLETO
        FROM FINANCEIRO f
        WHERE f.TIPO='A RECEBER' AND f.STATUS='ATIVO'
          AND f.VENCTO < CURRENT_DATE AND f.DATAPAGTO IS NULL
        """
    )
    av, avb = cur.fetchone()
    cur.execute(
        """
        SELECT
          COUNT(*) AS AR_TOT,
          SUM(CASE WHEN EXISTS (SELECT 1 FROM BOLETOS b
                                WHERE b.CODFINANCEIRO=f.CODIGO AND b.ATIVO='S')
                   THEN 1 ELSE 0 END) AS AR_TOT_BOLETO
        FROM FINANCEIRO f
        WHERE f.TIPO IN ('A RECEBER','RECEBIDA') AND f.STATUS LIKE 'ATIVO%'
          AND f.EMISSAO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE
        """
    )
    at, atb = cur.fetchone()
    return {
        "ar_vencidas_total": int(av or 0),
        "ar_vencidas_com_boleto": int(avb or 0),
        "ar_vencidas_pct_boleto": (int(avb or 0) / int(av or 1)) if av else 0,
        "ar_total_12m": int(at or 0),
        "ar_total_12m_com_boleto": int(atb or 0),
        "ar_total_12m_pct_boleto": (int(atb or 0) / int(at or 1)) if at else 0,
    }


def q_extra_tipopagto(cur):
    cur.execute(
        """
        SELECT TIPOPAGTO, COUNT(*), SUM(VALOR)
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
        GROUP BY TIPOPAGTO
        ORDER BY 3 DESC NULLS LAST
        """
    )
    return [
        {"tipopagto": r[0] or "(NULL)", "n": int(r[1]), "valor": float(r[2] or 0)}
        for r in cur.fetchall()
    ]


def q_extra_emissao_distribuicao(cur):
    cur.execute(
        """
        SELECT
          EXTRACT(YEAR FROM EMISSAO),
          COUNT(*),
          SUM(VALOR)
        FROM FINANCEIRO
        WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
          AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
          AND EMISSAO IS NOT NULL
        GROUP BY 1
        ORDER BY 1
        """
    )
    rows = [
        {"ano_emissao": int(r[0]), "n": int(r[1]), "valor": float(r[2] or 0)}
        for r in cur.fetchall()
    ]
    return {"por_ano_emissao": rows}


# ----------------------------------------------------------------------
# Collect
# ----------------------------------------------------------------------

def collect():
    print(f"[probe] Conectando {ALIAS} ...", file=sys.stderr)
    con = connect()
    cur = con.cursor()
    data = {
        "collected_at": dt.datetime.now().isoformat(),
        "alias": ALIAS,
    }
    print("[probe] Q-INV-1 aging ...", file=sys.stderr)
    data["q_inv_1_aging"] = q_inv_1_aging(cur)
    print("[probe] Q-INV-2 distribuicao_inadim ...", file=sys.stderr)
    data["q_inv_2_distribuicao"] = q_inv_2_distribuicao_inadim(cur)
    print("[probe] Q-INV-3 taxa_baixa ...", file=sys.stderr)
    data["q_inv_3_taxa_baixa"] = q_inv_3_taxa_baixa(cur)
    print("[probe] Q-INV-4 boletos ...", file=sys.stderr)
    data["q_inv_4_boletos"] = q_inv_4_boletos(cur)
    print("[probe] Q-UR-1 motivo_exclusao ...", file=sys.stderr)
    data["q_ur_1_motivo_exclusao"] = q_ur_1_motivo_exclusao(cur)
    print("[probe] Q-UR-2 excluidos_que_aparecem ...", file=sys.stderr)
    data["q_ur_2_excluidos_ativos"] = q_ur_2_excluidos_que_aparecem(cur)
    print("[probe] Q-UR-3 previsao_status ...", file=sys.stderr)
    data["q_ur_3_previsao"] = q_ur_3_previsao_status(cur)
    print("[probe] Q-UR-4 venda_vs_financeiro ...", file=sys.stderr)
    data["q_ur_4_venda_vs_financeiro"] = q_ur_4_venda_vs_financeiro(cur)
    print("[probe] Q-UR-5 razao_social_divergente ...", file=sys.stderr)
    data["q_ur_5_razao_orphan"] = q_ur_5_razao_social_divergente(cur)
    print("[probe] Q-UR-6 boleto_coverage ...", file=sys.stderr)
    data["q_ur_6_boleto_coverage"] = q_ur_6_boleto_coverage(cur)
    print("[probe] Q-extra tipopagto ...", file=sys.stderr)
    data["q_extra_tipopagto"] = q_extra_tipopagto(cur)
    print("[probe] Q-extra emissao_distribuicao ...", file=sys.stderr)
    data["q_extra_emissao_dist"] = q_extra_emissao_distribuicao(cur)
    con.close()
    return data


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--out", default=str(OUT_DIR))
    parser.add_argument("--today", default=dt.date.today().strftime("%Y-%m-%d"))
    args = parser.parse_args()
    out_dir = Path(args.out)
    out_dir.mkdir(parents=True, exist_ok=True)
    data = collect()
    raw_path = out_dir / f"raw-04-inadimplencia-{args.today}.json"
    raw_path.write_text(json.dumps(data, ensure_ascii=False, indent=2, default=str), encoding="utf-8")
    print(f"[probe] raw: {raw_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
