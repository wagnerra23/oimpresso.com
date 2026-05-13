"""
probe-financeiro.py — Análise FINANCEIRO + FINANCEIRO_BOLETO (Delphi WR Comercial)

Script ANALÍTICO. ZERO escrita Firebird. ZERO escrita MySQL. ZERO git ops.
Lê schema + stats agregadas (counts, sums, distribuições por intervalo de vencimento)
e produz 2 arquivos: JSON raw + relatório markdown legível.

Decisão Wagner (memory/reference/migracao-officeimpresso-pattern.md §Fase 5):
**cleanup-first** — antes de importar FINANCEIRO pro oimpresso, flag candidates a
write-off (VENCTO > 365d + sem BOLETO + sem movimentação) pra cliente decidir.
Pré-requisito: US-OFICINA-005 (cleanup tools UI) — NÃO importar ainda.

Cliente piloto Martinho v1404: FINANCEIRO ~104k rows × 57 cols · 76.7% inadimplência.

Uso:
    python scripts/legacy-migration/probe-financeiro.py --alias MartinhoServidor
    python scripts/legacy-migration/probe-financeiro.py --alias ServidorWR2 --firebird-password masterkey

Args:
    --alias <X>                  alias HKCU\\Software\\Rocha\\Office Comercial\\Banco\\Caminhos (obrigatório)
    --firebird-password <Y>      default 'masterkey' (build WR2)
    --output-dir <path>          default scripts/legacy-migration/output

Saída:
    output/financeiro-analysis-<alias>-<ts>.json   — dados raw
    output/financeiro-analysis-<alias>-<ts>.md     — relatório legível Wagner

PII: PESSOA_RESPONSAVEL_CNPJ e qualquer doc fiscal são REDACTED no JSON
(só schemas + agregados saem; amostras de linhas individuais nunca expostas).
"""
from __future__ import annotations

import argparse
import json
import sys
from datetime import date, datetime
from decimal import Decimal
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

# Reusa wrapper canônico de leitura Firebird (firebird-driver + registry HKCU).
sys.path.insert(0, str(Path(__file__).parent))
from lib.firebird_reader import (  # noqa: E402
    firebird_connect,
    get_table_columns,
    list_user_tables,
    query,
)


# ─── Serializer JSON pra tipos Firebird ────────────────────────────────────────
def _json_default(obj):
    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    if isinstance(obj, Decimal):
        return float(obj)
    return str(obj)


# ─── PII redaction ─────────────────────────────────────────────────────────────
PII_FIELDS = {
    "CNPJ", "CPF", "PESSOA_RESPONSAVEL_CNPJ", "DOCUMENTO",
    "RG", "INSCRICAO_ESTADUAL", "INSCRICAO_MUNICIPAL",
    "EMAIL", "TELEFONE", "CELULAR", "ENDERECO",
}


def redact_pii(d: dict) -> dict:
    """Substitui campos PII por [REDACTED] preservando keys."""
    if not isinstance(d, dict):
        return d
    return {
        k: ("[REDACTED]" if any(p in str(k).upper() for p in PII_FIELDS) else v)
        for k, v in d.items()
    }


# ─── Schema introspection ──────────────────────────────────────────────────────
def get_schema(con, table_name: str) -> dict:
    """Schema resumido: nome, qtd cols, lista cols."""
    cols = get_table_columns(con, table_name)
    return {
        "table": table_name,
        "col_count": len(cols),
        "columns": [
            {"name": c["name"], "type_id": c["type_id"], "nullable": c["nullable"]}
            for c in cols
        ],
    }


# ─── Análise FINANCEIRO ────────────────────────────────────────────────────────
def analyze_financeiro(con) -> dict:
    """Stats canônicas FINANCEIRO.

    Colunas reais observadas (Martinho v1404, ver inspect-financeiro-venda.py):
      CODPEDIDO, CODIGO, CODEMPRESA, TIPO ('A RECEBER'/'A PAGAR'),
      STATUS ('QUITADO'/'EM ABERTO'/'VENCIDO'/...),
      VENCTO (date), VALOR (decimal), DATAPAGTO (date nullable),
      HISTORICO (text), EMISSAO (date), TIPOPAGTO, JUROS, DESCONTO, PARCELA

    Estratégia de queries — todas usam TRIM() pra colunas CHAR padded
    (Firebird preenche com espaços). COALESCE pra NULLs em agregados.
    """
    out: dict = {}

    # 1. Total geral
    rows = query(con, "SELECT COUNT(*) AS QTOTAL FROM FINANCEIRO")
    out["total_rows"] = rows[0]["QTOTAL"] if rows else 0

    # 2. Distribuição por TIPO (A RECEBER vs A PAGAR)
    out["por_tipo"] = query(con, """
        SELECT
            TRIM(COALESCE(TIPO, '(null)')) AS TIPO,
            COUNT(*)                       AS QTD,
            COALESCE(SUM(VALOR), 0)        AS TOTAL
        FROM FINANCEIRO
        GROUP BY TRIM(COALESCE(TIPO, '(null)'))
        ORDER BY QTD DESC
    """)

    # 3. Distribuição por STATUS
    out["por_status"] = query(con, """
        SELECT
            TRIM(COALESCE(STATUS, '(null)')) AS STATUS,
            COUNT(*)                         AS QTD,
            COALESCE(SUM(VALOR), 0)          AS TOTAL
        FROM FINANCEIRO
        GROUP BY TRIM(COALESCE(STATUS, '(null)'))
        ORDER BY QTD DESC
    """)

    # 4. Distribuição cruzada TIPO × STATUS (matrix curta)
    out["tipo_x_status"] = query(con, """
        SELECT
            TRIM(COALESCE(TIPO, '(null)'))   AS TIPO,
            TRIM(COALESCE(STATUS, '(null)')) AS STATUS,
            COUNT(*)                         AS QTD,
            COALESCE(SUM(VALOR), 0)          AS TOTAL
        FROM FINANCEIRO
        GROUP BY TRIM(COALESCE(TIPO, '(null)')), TRIM(COALESCE(STATUS, '(null)'))
        ORDER BY QTD DESC
    """)

    # 5. Buckets de vencimento — CASE WHEN sobre dias desde VENCTO
    #    Convenção: positivo = atrasado (passou), negativo = futuro.
    #    Usa CURRENT_DATE do Firebird.
    out["por_intervalo_vencimento"] = query(con, """
        SELECT
            CASE
                WHEN VENCTO IS NULL                                  THEN '0_sem_vencto'
                WHEN CURRENT_DATE - VENCTO < 0                       THEN '1_futuro'
                WHEN CURRENT_DATE - VENCTO <= 365                    THEN '2_atraso_0_12m'
                WHEN CURRENT_DATE - VENCTO <= 730                    THEN '3_atraso_12_24m'
                WHEN CURRENT_DATE - VENCTO <= 1095                   THEN '4_atraso_24_36m'
                ELSE '5_atraso_36m_plus'
            END                       AS BUCKET,
            COUNT(*)                  AS QTD,
            COALESCE(SUM(VALOR), 0)   AS TOTAL
        FROM FINANCEIRO
        GROUP BY 1
        ORDER BY 1
    """)

    # 6. Write-off candidates (regra Wagner cleanup-first):
    #    A RECEBER + VENCIDO > 365d + sem DATAPAGTO + sem JUROS/DESCONTO movimentação
    #    + sem boleto vinculado (LEFT JOIN FINANCEIRO_BOLETO)
    out["writeoff_candidates"] = query(con, """
        SELECT
            COUNT(*)                  AS QTD,
            COALESCE(SUM(F.VALOR), 0) AS TOTAL
        FROM FINANCEIRO F
        LEFT JOIN FINANCEIRO_BOLETO FB ON FB.CODFINANCEIRO = F.CODIGO
        WHERE TRIM(COALESCE(F.TIPO, '')) = 'A RECEBER'
          AND F.VENCTO IS NOT NULL
          AND CURRENT_DATE - F.VENCTO > 365
          AND F.DATAPAGTO IS NULL
          AND COALESCE(F.JUROS, 0) = 0
          AND COALESCE(F.DESCONTO, 0) = 0
          AND FB.CODIGO IS NULL
    """)

    # 7. Inadimplência canônica — A RECEBER em aberto, vencidos
    out["inadimplencia_a_receber"] = query(con, """
        SELECT
            COUNT(*)                  AS QTD,
            COALESCE(SUM(VALOR), 0)   AS TOTAL
        FROM FINANCEIRO
        WHERE TRIM(COALESCE(TIPO, ''))   = 'A RECEBER'
          AND DATAPAGTO IS NULL
          AND VENCTO IS NOT NULL
          AND CURRENT_DATE - VENCTO > 0
    """)

    # 8. A pagar em aberto (passivo)
    out["a_pagar_em_aberto"] = query(con, """
        SELECT
            COUNT(*)                  AS QTD,
            COALESCE(SUM(VALOR), 0)   AS TOTAL
        FROM FINANCEIRO
        WHERE TRIM(COALESCE(TIPO, '')) = 'A PAGAR'
          AND DATAPAGTO IS NULL
    """)

    # 9. Range temporal — primeira e última EMISSAO/VENCTO
    rows = query(con, """
        SELECT
            MIN(EMISSAO) AS EMISSAO_MIN, MAX(EMISSAO) AS EMISSAO_MAX,
            MIN(VENCTO)  AS VENCTO_MIN,  MAX(VENCTO)  AS VENCTO_MAX
        FROM FINANCEIRO
    """)
    out["range_temporal"] = rows[0] if rows else {}

    return out


# ─── Análise FINANCEIRO_BOLETO ─────────────────────────────────────────────────
def analyze_boleto(con) -> dict:
    """Stats canônicas FINANCEIRO_BOLETO + HISTORICO.

    Schema é introspectado em runtime — colunas esperadas:
      CODIGO, CODFINANCEIRO, BOLETO_NOSSO_NR, STATUS (registrado/pago/cancelado/vencido)
    """
    out: dict = {}

    # 1. Total
    rows = query(con, "SELECT COUNT(*) AS QTOTAL FROM FINANCEIRO_BOLETO")
    out["total_rows"] = rows[0]["QTOTAL"] if rows else 0

    # 2. Distribuição por STATUS (boleto atual)
    out["por_status"] = query(con, """
        SELECT
            TRIM(COALESCE(STATUS, '(null)')) AS STATUS,
            COUNT(*)                         AS QTD
        FROM FINANCEIRO_BOLETO
        GROUP BY TRIM(COALESCE(STATUS, '(null)'))
        ORDER BY QTD DESC
    """)

    # 3. Coverage: FINANCEIRO A RECEBER COM boleto vs SEM boleto
    out["coverage_a_receber"] = query(con, """
        SELECT
            CASE WHEN FB.CODIGO IS NULL THEN 'sem_boleto' ELSE 'com_boleto' END AS COVERAGE,
            COUNT(*)                                                            AS QTD,
            COALESCE(SUM(F.VALOR), 0)                                           AS TOTAL
        FROM FINANCEIRO F
        LEFT JOIN FINANCEIRO_BOLETO FB ON FB.CODFINANCEIRO = F.CODIGO
        WHERE TRIM(COALESCE(F.TIPO, '')) = 'A RECEBER'
        GROUP BY 1
        ORDER BY 1
    """)

    # 4. Histórico de boletos (se tabela existir) — distribuição de eventos
    try:
        rows = query(con, """
            SELECT
                TRIM(COALESCE(STATUS, '(null)')) AS STATUS,
                COUNT(*)                         AS QTD
            FROM FINANCEIRO_BOLETO_HISTORICO
            GROUP BY TRIM(COALESCE(STATUS, '(null)'))
            ORDER BY QTD DESC
        """)
        out["historico_por_status"] = rows
        out["historico_disponivel"] = True
    except Exception as e:
        out["historico_disponivel"] = False
        out["historico_erro"] = str(e)[:200]

    return out


# ─── Renderização markdown ─────────────────────────────────────────────────────
def render_markdown(data: dict, alias: str, ts: str) -> str:
    lines = [
        f"# Análise FINANCEIRO — `{alias}` ({ts})",
        "",
        "> Análise pré-migração Delphi WR Comercial → oimpresso Laravel.",
        "> Decisão cleanup-first ([migracao-officeimpresso-pattern.md §Fase 5]"
        "(../../memory/reference/migracao-officeimpresso-pattern.md)).",
        "> **NÃO IMPORTAR** até US-OFICINA-005 (cleanup tools UI) existir.",
        "",
        "## 1. Schema",
        "",
    ]
    for t in ("FINANCEIRO", "FINANCEIRO_BOLETO", "FINANCEIRO_BOLETO_HISTORICO"):
        sch = data["schemas"].get(t)
        if sch:
            lines.append(f"- **{t}**: {sch['col_count']} colunas")
        else:
            lines.append(f"- **{t}**: (tabela não encontrada)")
    lines.append("")

    # ── FINANCEIRO ──
    f = data["financeiro"]
    lines += [
        "## 2. FINANCEIRO — agregados",
        "",
        f"**Total rows:** {f['total_rows']:,}",
        "",
        "### Por TIPO",
        "",
        "| TIPO | QTD | TOTAL R$ |",
        "|---|---:|---:|",
    ]
    for r in f["por_tipo"]:
        lines.append(f"| {r['TIPO']} | {r['QTD']:,} | {float(r['TOTAL'] or 0):,.2f} |")

    lines += [
        "",
        "### Por STATUS",
        "",
        "| STATUS | QTD | TOTAL R$ |",
        "|---|---:|---:|",
    ]
    for r in f["por_status"]:
        lines.append(f"| {r['STATUS']} | {r['QTD']:,} | {float(r['TOTAL'] or 0):,.2f} |")

    lines += [
        "",
        "### Buckets de vencimento (atraso desde CURRENT_DATE)",
        "",
        "| Bucket | QTD | TOTAL R$ |",
        "|---|---:|---:|",
    ]
    for r in f["por_intervalo_vencimento"]:
        lines.append(f"| {r['BUCKET']} | {r['QTD']:,} | {float(r['TOTAL'] or 0):,.2f} |")

    woc = f["writeoff_candidates"][0] if f["writeoff_candidates"] else {"QTD": 0, "TOTAL": 0}
    inad = f["inadimplencia_a_receber"][0] if f["inadimplencia_a_receber"] else {"QTD": 0, "TOTAL": 0}
    apagar = f["a_pagar_em_aberto"][0] if f["a_pagar_em_aberto"] else {"QTD": 0, "TOTAL": 0}

    lines += [
        "",
        "### Decisões cleanup-first",
        "",
        f"- **Write-off candidates** (A RECEBER · VENCTO > 365d · sem DATAPAGTO · "
        f"sem JUROS/DESCONTO · sem BOLETO): "
        f"**{woc['QTD']:,} linhas · R$ {float(woc['TOTAL'] or 0):,.2f}**",
        "  > Não importar. Flag pra cliente decidir (write-off ou cobrança real).",
        f"- **Inadimplência A RECEBER em aberto vencida** (qualquer atraso): "
        f"**{inad['QTD']:,} linhas · R$ {float(inad['TOTAL'] or 0):,.2f}**",
        f"- **A PAGAR em aberto**: "
        f"**{apagar['QTD']:,} linhas · R$ {float(apagar['TOTAL'] or 0):,.2f}**",
        "",
    ]

    # ── BOLETO ──
    b = data["boleto"]
    lines += [
        "## 3. FINANCEIRO_BOLETO — agregados",
        "",
        f"**Total rows:** {b['total_rows']:,}",
        "",
        "### Por STATUS",
        "",
        "| STATUS | QTD |",
        "|---|---:|",
    ]
    for r in b["por_status"]:
        lines.append(f"| {r['STATUS']} | {r['QTD']:,} |")

    lines += [
        "",
        "### Coverage A RECEBER × boleto",
        "",
        "| Coverage | QTD | TOTAL R$ |",
        "|---|---:|---:|",
    ]
    for r in b["coverage_a_receber"]:
        lines.append(f"| {r['COVERAGE']} | {r['QTD']:,} | {float(r['TOTAL'] or 0):,.2f} |")

    if b.get("historico_disponivel"):
        lines += [
            "",
            "### FINANCEIRO_BOLETO_HISTORICO — por STATUS",
            "",
            "| STATUS | QTD |",
            "|---|---:|",
        ]
        for r in b["historico_por_status"]:
            lines.append(f"| {r['STATUS']} | {r['QTD']:,} |")
    else:
        lines += [
            "",
            "> `FINANCEIRO_BOLETO_HISTORICO` indisponível: "
            f"{b.get('historico_erro', '(razão desconhecida)')}",
        ]

    lines += [
        "",
        "## 4. Próximos passos",
        "",
        "1. Wagner revisa números (inadimplência, write-off candidates).",
        "2. Aguardar **US-OFICINA-005** (cleanup tools UI) ser entregue.",
        "3. Cliente decide write-off em batch via UI antes da migração.",
        "4. Depois disso → criar agente `migracao-financeiro` (importer real Firebird→MySQL).",
        "",
        f"_Gerado por `scripts/legacy-migration/probe-financeiro.py` em {ts}._",
        "",
    ]
    return "\n".join(lines)


# ─── Main ──────────────────────────────────────────────────────────────────────
def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True, help="Alias HKCU Office Comercial")
    parser.add_argument("--firebird-password", default="masterkey",
                        help="Senha Firebird (default 'masterkey' build WR2)")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output",
                        help="Diretório de saída")
    args = parser.parse_args()

    out_dir = Path(args.output_dir)
    out_dir.mkdir(parents=True, exist_ok=True)

    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    alias_slug = args.alias.replace("\\", "_").replace("/", "_")
    json_path = out_dir / f"financeiro-analysis-{alias_slug}-{ts}.json"
    md_path = out_dir / f"financeiro-analysis-{alias_slug}-{ts}.md"

    print(f"[probe-financeiro] alias={args.alias}")
    print(f"[probe-financeiro] output_dir={out_dir.resolve()}")

    with firebird_connect(args.alias, password_override=args.firebird_password) as con:
        print("[probe-financeiro] conectado ao Firebird, introspectando schema...")

        # Schema check — quais tabelas existem
        all_tables = set(list_user_tables(con))
        schemas: dict = {}
        for t in ("FINANCEIRO", "FINANCEIRO_BOLETO", "FINANCEIRO_BOLETO_HISTORICO"):
            if t in all_tables:
                schemas[t] = get_schema(con, t)
                print(f"[probe-financeiro] schema OK: {t} ({schemas[t]['col_count']} cols)")
            else:
                print(f"[probe-financeiro] AVISO: tabela {t} ausente")

        if "FINANCEIRO" not in schemas:
            print("[probe-financeiro] ERRO: FINANCEIRO não existe — abortando", file=sys.stderr)
            return 2

        print("[probe-financeiro] rodando agregados FINANCEIRO...")
        financeiro_stats = analyze_financeiro(con)

        boleto_stats: dict = {}
        if "FINANCEIRO_BOLETO" in schemas:
            print("[probe-financeiro] rodando agregados FINANCEIRO_BOLETO...")
            boleto_stats = analyze_boleto(con)

    # Monta payload final (com PII redaction defensiva nos schemas/agregados)
    payload = {
        "meta": {
            "alias": args.alias,
            "timestamp": ts,
            "generated_at": datetime.now().isoformat(),
            "script": "scripts/legacy-migration/probe-financeiro.py",
            "note": "ANÁLISE — não importa. cleanup-first conforme decisão Wagner.",
        },
        "schemas": {
            t: {
                **s,
                "columns": [redact_pii(c) for c in s["columns"]],
            }
            for t, s in schemas.items()
        },
        "financeiro": financeiro_stats,
        "boleto": boleto_stats,
    }

    json_path.write_text(
        json.dumps(payload, indent=2, default=_json_default, ensure_ascii=False),
        encoding="utf-8",
    )
    print(f"[probe-financeiro] JSON: {json_path}")

    md_path.write_text(render_markdown(payload, args.alias, ts), encoding="utf-8")
    print(f"[probe-financeiro] MD:   {md_path}")
    print("[probe-financeiro] done")
    return 0


if __name__ == "__main__":
    sys.exit(main())
