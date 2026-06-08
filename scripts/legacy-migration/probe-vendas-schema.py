"""
Probe Fase 0 + 0.5 — confirma VERSAO_BANCO + mapeia schema VENDA + VENDA_ITEM em qualquer alias HKCU.

Output:
  scripts/legacy-migration/output/drift-vendas-<alias>-<ts>.json

Uso:
  python probe-vendas-schema.py --alias MartinhoServidor
"""
from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))

from lib.firebird_reader import firebird_connect, get_versao_banco, query  # noqa: E402


# Colunas esperadas em VENDA (canônica v1474 do mapping TELA-LISTA-VENDAS.md §2 SQL base + §9.1-9.5)
CANONICAS_VENDA = {
    "CODIGO", "SEQUENCIA", "PESSOA_RESPONSAVEL_CODIGO", "RAZAOSOCIAL",
    "TOTAL", "PESSOA_RESPONSAVEL_SEQUENCIA",
    "DT_EMISSAO", "TELEFONE", "VENDA_TIPO", "DT_FATURAMENTO",
    "NOTAFISCAL", "CONDICAOPAGTO", "ATIVO", "RESPONSAVEL_UF",
    "STATUS", "PROJETO_DT_FIM", "CONTATO", "MOTORISTA_DOCUMENTO_NUMERO",
    "NF_DT_EMISSAO", "SITUACAOFINANCEIRA", "VENDA_ESTAGIO", "IS_PDV",
    "PEDIDO_REP", "CODVENDA", "SITUACAO", "IS_VENDA", "IS_NOTAFISCAL",
    "IS_ORCAMENTO", "DT_COMPETENCIA", "CODVENDA_VINCULADA",
    "FATURA_PREVISAO", "IS_FATURAMENTO_CANCELADO",
    "CODVENDA_PRE_VENDA", "OBSERVACAO",
    "PESSOA_FUNCIONARIO_CODIGO", "PESSOA_REPRESENTANTE_CODIGO",
    "PESSOA_AGENCIA_CODIGO", "VDESC", "VOUTRO", "NF_VFRETE",
    "CODIGOVENDA", "TOTAL_FATURA", "PEDIDO_COMPRA", "FATURAMENTO_DT_ENVIO",
    "DT_ALTERACAO", "PLACA", "PROJETO_DT_INICIO", "CODPROJETO",
    "CODVENDA_PRINCIPAL", "OPERACAO",
}

TABELAS_ALVO = ["VENDA", "VENDA_ITEM", "VENDA_PRODUTO", "EQUIPAMENTO_VEICULO", "FINANCEIRO", "FINANCEIRO_BOLETO"]


def get_simple_columns(con, table_name: str) -> list[str]:
    """Lista nomes de cols. Usa SELECT * FROM tab WHERE 1=0 como atalho universal."""
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table_name}")
        if cur.description is None:
            return []
        return [d[0] for d in cur.description]
    finally:
        cur.close()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--firebird-password", default="masterkey")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = parser.parse_args()

    print(f"Probe schema VENDA Firebird (alias={args.alias})")

    with firebird_connect(args.alias, password_override=args.firebird_password) as con:
        versao = get_versao_banco(con)
        print(f"  VERSAO_BANCO: {versao}")

        cur = con.cursor()
        cur.execute("SELECT COUNT(*) FROM RDB$RELATIONS WHERE RDB$SYSTEM_FLAG=0 AND RDB$VIEW_BLR IS NULL")
        tabelas_total = cur.fetchone()[0]
        print(f"  Tabelas usuárias: {tabelas_total}")
        cur.close()

        report: dict = {
            "alias": args.alias,
            "versao_banco": versao,
            "tabelas_usuarias_total": tabelas_total,
            "probed_at_iso": datetime.utcnow().isoformat() + "Z",
            "tabelas": {},
        }

        for tab in TABELAS_ALVO:
            try:
                col_names_list = get_simple_columns(con, tab)
            except Exception as e:
                report["tabelas"][tab] = {"erro": repr(e), "existe": False}
                print(f"  [{tab}] AUSENTE ({type(e).__name__})")
                continue
            col_names = set(col_names_list)
            entry = {
                "existe": True,
                "total_cols": len(col_names_list),
                "cols": col_names_list,
            }
            if tab == "VENDA":
                ausentes = sorted(CANONICAS_VENDA - col_names)
                extras = sorted(col_names - CANONICAS_VENDA)
                entry["canonicas_total"] = len(CANONICAS_VENDA)
                entry["canonicas_presentes"] = len(CANONICAS_VENDA & col_names)
                entry["canonicas_ausentes"] = ausentes
                entry["extras_vs_canonica_count"] = len(extras)
                entry["extras_vs_canonica_sample"] = extras[:30]
                entry["drift_pct"] = round(len(ausentes) / len(CANONICAS_VENDA) * 100, 1)
            try:
                cur = con.cursor()
                cur.execute(f"SELECT COUNT(*) FROM {tab}")
                entry["row_count"] = cur.fetchone()[0]
                cur.close()
            except Exception as e:
                entry["row_count_error"] = repr(e)
            report["tabelas"][tab] = entry
            extra_info = ""
            if tab == "VENDA":
                extra_info = f" · drift {entry['drift_pct']}% ({len(entry['canonicas_ausentes'])} ausentes vs canônica)"
            print(f"  [{tab}] {entry['total_cols']} cols · {entry.get('row_count', '?')} rows{extra_info}")

        try:
            cur = con.cursor()
            cur.execute("SELECT MIN(DT_EMISSAO), MAX(DT_EMISSAO) FROM VENDA")
            r = cur.fetchone()
            cur.execute("SELECT COUNT(*) FROM VENDA WHERE DT_EMISSAO >= '2025-05-13'")
            ano = cur.fetchone()[0]
            cur.close()
            report["venda_dt_emissao_min"] = str(r[0]) if r and r[0] else None
            report["venda_dt_emissao_max"] = str(r[1]) if r and r[1] else None
            report["venda_count_ultimos_12m"] = ano
            print(f"  VENDA DT_EMISSAO range: {r[0]} → {r[1]} · últimos 12m: {ano}")
        except Exception as e:
            print(f"  VENDA dt range probe falhou: {e!r}")

        try:
            cur = con.cursor()
            cur.execute("SELECT FIRST 3 CODIGO, PLACA FROM EQUIPAMENTO_VEICULO ORDER BY CODIGO")
            sample_ev = [(r[0], r[1]) for r in cur.fetchall()]
            cur.close()
            report["equipamento_veiculo_sample"] = sample_ev
            print(f"  EQUIPAMENTO_VEICULO sample: {sample_ev}")
        except Exception as e:
            print(f"  EV sample probe falhou: {e!r}")

        try:
            cur = con.cursor()
            cur.execute("SELECT FIRST 5 CODIGO, PLACA FROM VENDA WHERE PLACA IS NOT NULL AND PLACA <> '' ORDER BY CODIGO")
            sample_v = [(r[0], r[1]) for r in cur.fetchall()]
            cur.close()
            report["venda_placa_sample_naonula"] = sample_v
            print(f"  VENDA.PLACA sample (não-nula): {sample_v}")
        except Exception as e:
            print(f"  VENDA.PLACA probe falhou: {e!r}")

        # Quantas vendas Martinho realmente têm vehicle_id resolvível?
        try:
            cur = con.cursor()
            cur.execute("""
                SELECT COUNT(*) FROM VENDA P
                LEFT JOIN EQUIPAMENTO_VEICULO EV ON EV.CODIGO = P.PLACA
                WHERE P.PLACA IS NOT NULL AND P.PLACA <> '' AND EV.CODIGO IS NOT NULL
            """)
            com_veh = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM VENDA WHERE PLACA IS NULL OR PLACA = ''")
            sem_placa = cur.fetchone()[0]
            cur.execute("""
                SELECT COUNT(*) FROM VENDA P
                LEFT JOIN EQUIPAMENTO_VEICULO EV ON EV.CODIGO = P.PLACA
                WHERE P.PLACA IS NOT NULL AND P.PLACA <> '' AND EV.CODIGO IS NULL
            """)
            placa_orfa = cur.fetchone()[0]
            cur.close()
            report["venda_join_stats"] = {
                "com_veiculo_resolvido": com_veh,
                "sem_placa_NULL_ou_vazia": sem_placa,
                "placa_invalida_sem_join": placa_orfa,
            }
            print(f"  JOIN stats — com_vehicle={com_veh} · sem_placa={sem_placa} · órfã={placa_orfa}")
        except Exception as e:
            print(f"  JOIN probe falhou: {e!r}")

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    path = out / f"drift-vendas-{args.alias}-{ts}.json"
    path.write_text(json.dumps(report, ensure_ascii=False, indent=2, default=str), encoding="utf-8")
    print(f"\nDrift report salvo: {path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
