#!/usr/bin/env python3
"""
enrich-gaps-cadastro.py — Fecha os gaps de cadastro de cliente detectados pela
auditoria audit-cadastro-cliente.py. Campos diretos + fiscais (com mapeamento).

Match por officeimpresso_codigo. UPDATE em lote por valor (minimiza round-trips
pelo túnel SSH — mesma técnica do enrich-tipos).

Campos:
  indicador_ie               <- TIPO_CONTRIBUINTE  (1/2/9 = padrão SEFAZ indIEDest)
  iss_retido                 <- ISS_RETIDO         (1=retido/2=nao; 0->null)
  regime                     <- CRT                (texto: 'Simples Nacional' etc)
  complemento                <- COMPLEMENTO        (texto direto)
  inscricao_municipal        <- INSC_MUNICIPAL     (texto direto)
  aniversario_mmdd           <- ANIVERSARIO        (formata MM-DD)
  limite_desconto_percentual <- LIMITE_DESCONTO    (decimal direto)
COALESCE não usado (colunas estavam vazias). Não toca campos já migrados.
"""
from __future__ import annotations
import os, sys, datetime
os.environ.setdefault("FIREBIRD_PY_DRIVER", "fdb")
sys.path.insert(0, os.path.dirname(__file__))
sys.stdout.reconfigure(encoding="utf-8")
from lib.firebird_reader import firebird_connect  # noqa
import pymysql, pymysql.cursors  # noqa

BIZ = 164


def fmt_mmdd(v):
    if v is None:
        return None
    if isinstance(v, (datetime.date, datetime.datetime)):
        return f"{v.month:02d}-{v.day:02d}"
    s = str(v).strip()
    # tenta YYYY-MM-DD ou DD/MM/YYYY
    import re
    m = re.search(r"(\d{4})-(\d{2})-(\d{2})", s)
    if m:
        return f"{m.group(2)}-{m.group(3)}"
    m = re.search(r"(\d{2})/(\d{2})", s)
    if m:
        return f"{m.group(2)}-{m.group(1)}"
    return None


def main():
    confirm = "--confirm" in sys.argv
    _ctx = firebird_connect("Martinho online", password_override="masterkey")
    con = _ctx.__enter__()
    cur = con.cursor()
    cur.execute(
        "SELECT CODIGO, TIPO_CONTRIBUINTE, ISS_RETIDO, CRT, COMPLEMENTO, "
        "INSC_MUNICIPAL, ANIVERSARIO, LIMITE_DESCONTO FROM PESSOAS"
    )
    cols = [d[0] for d in cur.description]
    rows = [dict(zip(cols, r)) for r in cur]
    _ctx.__exit__(None, None, None)
    print(f"PESSOAS lidas: {len(rows)}")

    # campo destino -> {valor: [codigos]}
    groups: dict[str, dict] = {c: {} for c in (
        "indicador_ie", "iss_retido", "regime", "complemento",
        "inscricao_municipal", "aniversario_mmdd", "limite_desconto_percentual")}

    def add(field, val, cod):
        if val is None:
            return
        groups[field].setdefault(val, []).append(cod)

    for r in rows:
        cod = str(r["CODIGO"]).strip() if r["CODIGO"] is not None else ""
        if not cod:
            continue
        # fiscais
        tc = r["TIPO_CONTRIBUINTE"]
        if tc is not None and int(tc) in (1, 2, 9):
            add("indicador_ie", int(tc), cod)
        iss = r["ISS_RETIDO"]
        if iss is not None and int(iss) in (1, 2):
            add("iss_retido", int(iss), cod)
        crt = (str(r["CRT"]).strip() if r["CRT"] is not None else "")
        if crt:
            add("regime", crt[:191], cod)
        # diretos
        comp = (str(r["COMPLEMENTO"]).strip() if r["COMPLEMENTO"] is not None else "")
        if comp:
            add("complemento", comp[:120], cod)
        im = (str(r["INSC_MUNICIPAL"]).strip() if r["INSC_MUNICIPAL"] is not None else "")
        if im and im != "0":
            add("inscricao_municipal", im[:50], cod)
        ani = fmt_mmdd(r["ANIVERSARIO"])
        if ani:
            add("aniversario_mmdd", ani, cod)
        ld = r["LIMITE_DESCONTO"]
        if ld is not None and float(ld) != 0:
            add("limite_desconto_percentual", round(float(ld), 2), cod)

    print("Grupos por campo:")
    for f, g in groups.items():
        print(f"  {f:28} valores distintos={len(g):5} registros={sum(len(v) for v in g.values())}")

    if not confirm:
        print("\n[dry-run] use --confirm para gravar.")
        return

    mcon = pymysql.connect(host="127.0.0.1", port=33069, user="u906587222_oimpresso",
                           password=os.environ["MYSQL_PASSWORD"], database="u906587222_oimpresso",
                           charset="utf8mb4", autocommit=False)
    stats = {}
    CHUNK = 800
    try:
        for field, g in groups.items():
            upd = 0
            for val, codes in g.items():
                for i in range(0, len(codes), CHUNK):
                    chunk = codes[i:i + CHUNK]
                    ph = ",".join(["%s"] * len(chunk))
                    with mcon.cursor() as wc:
                        wc.execute(
                            f"UPDATE contacts SET {field}=%s, updated_at=NOW() "
                            f"WHERE business_id=%s AND deleted_at IS NULL "
                            f"AND officeimpresso_codigo IN ({ph}) "
                            f"AND ({field} IS NULL OR {field}='')",
                            [val, BIZ] + chunk)
                        upd += wc.rowcount
            stats[field] = upd
        mcon.commit()
    finally:
        mcon.close()
    print("\nAtualizados (só onde estava vazio):")
    for f, n in stats.items():
        print(f"  {f:28} = {n}")


if __name__ == "__main__":
    main()
