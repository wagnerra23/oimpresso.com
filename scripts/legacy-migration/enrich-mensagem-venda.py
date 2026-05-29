#!/usr/bin/env python3
"""
enrich-mensagem-venda.py — Migra PESSOAS.MENSAGEM_PARA_VENDA (campo DISTINTO de
OBSERVACAO) para uma coluna dedicada contacts.mensagem_venda (TEXT, sem truncar).

Contexto: enrich-contacts-v2 colocou a mensagem em custom_field1 (varchar 191),
truncando textos >191 (16 casos, máx 522 chars) e sobrecarregando o campo.
Wagner confirmou: Observação ≠ Mensagem para Venda — devem ser campos separados.

Ações:
 1. ALTER TABLE contacts ADD COLUMN mensagem_venda TEXT NULL (idempotente).
 2. Popula mensagem_venda com o texto COMPLETO da origem (match por officeimpresso_codigo).
 3. Limpa custom_field1 onde ele == LEFT(mensagem,191) (remove a cópia truncada duplicada).
 4. obs_comercial (=OBSERVACAO) fica intacta.

Migration Laravel equivalente: ver database/migrations/..._add_mensagem_venda_to_contacts.php
"""
from __future__ import annotations
import argparse, os, sys
os.environ.setdefault("FIREBIRD_PY_DRIVER", "fdb")
sys.path.insert(0, os.path.dirname(__file__))
from lib.firebird_reader import firebird_connect  # noqa
import pymysql, pymysql.cursors  # noqa


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--alias", default="Martinho online")
    p.add_argument("--target-business", type=int, default=164)
    p.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    p.add_argument("--confirm", action="store_true")
    p.add_argument("--dry-run", action="store_true")
    args = p.parse_args()
    if not (args.confirm or args.dry_run):
        print("[ERRO] requer --confirm (ou --dry-run)", file=sys.stderr); return 2
    biz = args.target_business

    print("== enrich-mensagem-venda v1.0 ==")
    # 1) Lê MENSAGEM_PARA_VENDA da origem
    print("[1/4] Lendo MENSAGEM_PARA_VENDA Firebird...")
    msgs: dict[str, str] = {}
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb:
        cur = fb.cursor()
        cur.execute("SELECT CODIGO, MENSAGEM_PARA_VENDA FROM PESSOAS "
                    "WHERE MENSAGEM_PARA_VENDA IS NOT NULL AND CHAR_LENGTH(TRIM(MENSAGEM_PARA_VENDA))>0")
        for cod, msg in cur:
            cod = str(cod).strip() if cod is not None else ""
            if cod and msg and str(msg).strip():
                msgs[cod] = str(msg).strip()
        cur.close()
    print(f"  mensagens lidas: {len(msgs)}")

    con = pymysql.connect(host=os.environ.get("MYSQL_HOST", "127.0.0.1"),
                          port=int(os.environ.get("MYSQL_PORT", "3306")),
                          user=os.environ.get("MYSQL_USER", "root"),
                          password=os.environ.get("MYSQL_PASSWORD", ""),
                          database=os.environ.get("MYSQL_DATABASE", "oimpresso"),
                          charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor, autocommit=False)
    stats = {"col_criada": 0, "atualizados": 0, "custom1_limpos": 0, "sem_match": 0}
    try:
        # 2) Garante coluna (additive, nullable)
        with con.cursor() as cur:
            cur.execute("SELECT COUNT(*) n FROM information_schema.COLUMNS "
                        "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contacts' AND COLUMN_NAME='mensagem_venda'")
            existe = cur.fetchone()["n"] > 0
        if not existe:
            print("[2/4] Criando coluna contacts.mensagem_venda TEXT NULL...")
            if not args.dry_run:
                with con.cursor() as cur:
                    cur.execute("ALTER TABLE contacts ADD COLUMN mensagem_venda TEXT NULL AFTER obs_comercial")
                stats["col_criada"] = 1
        else:
            print("[2/4] Coluna mensagem_venda já existe.")

        # 3) Popula + limpa custom_field1 truncado
        print("[3/4] Populando mensagem_venda + limpando custom_field1 truncado...")
        if not args.dry_run:
            with con.cursor() as cur:
                for cod, msg in msgs.items():
                    cur.execute(
                        "UPDATE contacts SET mensagem_venda=%s, updated_at=NOW() "
                        "WHERE business_id=%s AND officeimpresso_codigo=%s AND deleted_at IS NULL",
                        (msg, biz, cod))
                    if cur.rowcount > 0:
                        stats["atualizados"] += 1
                        # limpa custom_field1 se for só a cópia (truncada) da mensagem
                        cur.execute(
                            "UPDATE contacts SET custom_field1=NULL "
                            "WHERE business_id=%s AND officeimpresso_codigo=%s AND deleted_at IS NULL "
                            "AND custom_field1 IS NOT NULL AND custom_field1=LEFT(%s,191)",
                            (biz, cod, msg))
                        stats["custom1_limpos"] += cur.rowcount
                    else:
                        stats["sem_match"] += 1
            con.commit()
        else:
            con.rollback()

        # 4) verificação FAN COM
        if not args.dry_run:
            with con.cursor() as cur:
                cur.execute("SELECT CHAR_LENGTH(mensagem_venda) ml, CHAR_LENGTH(obs_comercial) ol, custom_field1 "
                            "FROM contacts WHERE business_id=%s AND officeimpresso_codigo='651-1' AND deleted_at IS NULL", (biz,))
                print("[4/4] FAN COM → mensagem_venda len, obs len, custom_field1:", cur.fetchone())
    finally:
        con.close()

    print("\nResultado:")
    for k, v in stats.items():
        print(f"  {k:16} = {v}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
