"""
Importer NF_ENTRADA_MANIFESTO (Delphi WR Comercial) → nfe_dfe_recebidos + nfe_dfe_eventos.

Manifesto do destinatário: as notas que os fornecedores emitiram contra cada CNPJ da empresa e o
que a empresa respondeu (ciência, confirmação, desconhecimento, operação não realizada).

Sabor Brasil (banco v1514, medido 2026-11-04): 453 registros de 3 CNPJs (EMPRESA 1, 2 e 4),
2019–2023; 20 com o resumo (resNFe) gravado. A empresa parou de usar em 2023 e o [F] decidiu
(2026-11-04) que o histórico vem junto: "tem que funcionar do modo correto".

Mapeamento:
- chave_44 = NF_CHAVE · nsu = NSU · cnpj_emitente / nome / valor / data / protocolo do registro;
- cnpj_destinatario = EMPRESA.CNPJCPF do CODEMPRESA (ADR ARQ-0007 · princípio 6, multi-CNPJ);
- status_manifestacao = o evento final, nesta ordem: confirmada > nao_realizada > desconhecida >
  ciencia > pendente; manifestado_em = DT_ALTERACAO quando houve evento;
- um nfe_dfe_eventos por evento que o Delphi marcou (status 'autorizado'; payload_json diz que
  veio do Delphi). Prazo de confirmação = emissão + 180 dias (NT 2014.002).

Idempotência: UNIQUE (business_id, chave_44) — re-rodar só completa o que está vazio; nota que o
oimpresso já puxou da SEFAZ não é sobrescrita. Eventos: UNIQUE (business_id, dfe_recebido_id,
tipo, nseq_evento).

Firebird só leitura (ADR 0019). Multi-tenant Tier 0 (ADR 0093): business_id em todo INSERT.
Registros de homologação (NF_AMBIENTE = 2) ficam fora.

Uso:
    python import-manifestos.py --alias SaborBrasil --target-business 199
    python import-manifestos.py --alias SaborBrasil --target-business 199 --target prod --confirm
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime, date, timedelta
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

from lib.firebird_reader import firebird_connect  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None

EVENTOS = [
    # (flag Delphi, código do evento, status final)
    ("TEM_CIENCIA", "210210", "ciencia"),
    ("TEM_CONFIRMADA", "210200", "confirmada"),
    ("TEM_DESCONHECIDA", "210220", "desconhecida"),
    ("TEM_NAO_REALIZADA", "210240", "nao_realizada"),
]
PRECEDENCIA = ["confirmada", "nao_realizada", "desconhecida", "ciencia"]


def so_digitos(v) -> str:
    return re.sub(r"\D", "", str(v or ""))


def sim(v) -> bool:
    return str(v or "").strip().upper() == "S"


def como_data(v):
    if v is None:
        return None
    return v if isinstance(v, datetime) else datetime.combine(v, datetime.min.time())


def mapear(r: dict, business_id: int, cnpj_empresa: dict[int, str]) -> tuple[dict, list[dict]]:
    marcados = [(cod, st) for flag, cod, st in EVENTOS if sim(r.get(flag))]
    status = next((s for s in PRECEDENCIA if s in {st for _, st in marcados}), "pendente")
    emissao = como_data(r.get("NF_DT_EMISSAO")) or como_data(r.get("NF_DT_RECEBIMENTO")) or datetime(1900, 1, 1)
    alterado = como_data(r.get("DT_ALTERACAO"))
    dfe = {
        "business_id": business_id,
        "chave_44": so_digitos(r.get("NF_CHAVE"))[:44],
        "nsu": int(r["NSU"]) if r.get("NSU") is not None else None,
        "cnpj_emitente": so_digitos(r.get("NF_CNPJCPF_EMITENTE"))[:14],
        "cnpj_destinatario": cnpj_empresa.get(int(r["CODEMPRESA"])) if r.get("CODEMPRESA") is not None else None,
        "nome_emitente": (str(r.get("NF_RAZAOSOCIAL_EMITENTE") or "").strip() or None),
        "valor_total": round(float(r.get("NF_TOTAL") or 0), 2),
        "num_protocolo": (str(r.get("NF_PROTOCOLO") or "").strip() or None),
        "data_emissao": emissao,
        "status_manifestacao": status,
        "manifestado_em": alterado if status != "pendente" else None,
        "prazo_confirmacao_em": (emissao + timedelta(days=180)).date(),
    }
    eventos = [{"tipo": cod, "created_at": alterado or emissao} for cod, _ in marcados]
    return dfe, eventos


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--alias", required=True)
    p.add_argument("--target-business", type=int, required=True)
    p.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    p.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    p.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    p.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    p.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    p.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    p.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    p.add_argument("--confirm", action="store_true")
    a = p.parse_args()

    if a.target == "prod" and not a.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr)
        return 2
    if a.target != "dry-run" and pymysql is None:
        print("[ERRO] pymysql não instalado", file=sys.stderr)
        return 2

    stats = {"lidos": 0, "homologacao": 0, "sem_chave": 0, "novos": 0, "completados": 0, "eventos": 0,
             "por_status": {}, "por_cnpj": {}}

    with firebird_connect(a.alias, password_override=a.firebird_password) as fb:
        cur = fb.cursor()
        cur.execute("SELECT CODIGO, CNPJCPF FROM EMPRESA")
        cnpj_empresa = {int(c): so_digitos(d)[:14] for c, d in cur.fetchall() if d}

        cur.execute(
            "SELECT CODEMPRESA, NF_CHAVE, NSU, NF_CNPJCPF_EMITENTE, NF_RAZAOSOCIAL_EMITENTE, NF_TOTAL,"
            " NF_PROTOCOLO, NF_DT_EMISSAO, NF_DT_RECEBIMENTO, NF_AMBIENTE, DT_ALTERACAO,"
            " TEM_CIENCIA, TEM_CONFIRMADA, TEM_DESCONHECIDA, TEM_NAO_REALIZADA"
            " FROM NF_ENTRADA_MANIFESTO ORDER BY CODIGO"
        )
        cols = [d[0] for d in cur.description]
        linhas = [dict(zip(cols, t)) for t in cur.fetchall()]

    con = None
    if a.target != "dry-run":
        con = pymysql.connect(host=a.mysql_host, port=a.mysql_port, user=a.mysql_user,
                              password=a.mysql_password, database=a.mysql_database, charset="utf8mb4",
                              cursorclass=pymysql.cursors.DictCursor, autocommit=False)
    try:
        for r in linhas:
            stats["lidos"] += 1
            if r.get("NF_AMBIENTE") is not None and int(r["NF_AMBIENTE"]) == 2:
                stats["homologacao"] += 1
                continue
            dfe, eventos = mapear(r, a.target_business, cnpj_empresa)
            if len(dfe["chave_44"]) != 44:
                stats["sem_chave"] += 1
                continue
            stats["por_status"][dfe["status_manifestacao"]] = stats["por_status"].get(dfe["status_manifestacao"], 0) + 1
            stats["por_cnpj"][dfe["cnpj_destinatario"] or "?"] = stats["por_cnpj"].get(dfe["cnpj_destinatario"] or "?", 0) + 1
            if con is None:
                stats["novos"] += 1
                stats["eventos"] += len(eventos)
                continue

            with con.cursor() as c:
                c.execute("SELECT id FROM nfe_dfe_recebidos WHERE business_id=%s AND chave_44=%s",
                          (a.target_business, dfe["chave_44"]))
                atual = c.fetchone()
                if atual is None:
                    campos = list(dfe.keys())
                    c.execute(
                        f"INSERT INTO nfe_dfe_recebidos ({', '.join(campos)}, origem, created_at, updated_at)"
                        f" VALUES ({', '.join(['%s'] * len(campos))}, 'nsu', NOW(), NOW())",
                        tuple(dfe[k] for k in campos),
                    )
                    dfe_id = c.lastrowid
                    stats["novos"] += 1
                else:
                    dfe_id = atual["id"]
                    # Só completa o que está vazio — o que veio da SEFAZ pelo oimpresso fica.
                    c.execute(
                        "UPDATE nfe_dfe_recebidos SET cnpj_destinatario = COALESCE(cnpj_destinatario, %s),"
                        " num_protocolo = COALESCE(num_protocolo, %s) WHERE id=%s AND business_id=%s",
                        (dfe["cnpj_destinatario"], dfe["num_protocolo"], dfe_id, a.target_business),
                    )
                    stats["completados"] += 1
                for ev in eventos:
                    c.execute(
                        "INSERT IGNORE INTO nfe_dfe_eventos (business_id, dfe_recebido_id, tipo, status,"
                        " payload_json, nseq_evento, created_at) VALUES (%s, %s, %s, 'autorizado', %s, 1, %s)",
                        (a.target_business, dfe_id, ev["tipo"],
                         json.dumps({"origem": "delphi", "tabela": "NF_ENTRADA_MANIFESTO"}), ev["created_at"]),
                    )
                    stats["eventos"] += c.rowcount
        if con is not None:
            con.commit()
    except Exception:
        if con is not None:
            con.rollback()
        raise
    finally:
        if con is not None:
            con.close()

    print(json.dumps(stats, ensure_ascii=False, indent=2, default=str))
    return 0


if __name__ == "__main__":
    sys.exit(main())
