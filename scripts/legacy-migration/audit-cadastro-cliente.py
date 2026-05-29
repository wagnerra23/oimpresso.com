#!/usr/bin/env python3
"""
audit-cadastro-cliente.py — Inventário de completude do cadastro de cliente.
Para cada campo canônico da tela Pessoas (Delphi), mede:
  - % preenchido na ORIGEM (PESSOAS Firebird, Martinho)
  - se há coluna DESTINO em contacts (oimpresso) e % preenchido lá
  - veredito: MIGRADO / PARCIAL / NÃO MIGRADO / N/A (origem vazia)
Match por officeimpresso_codigo. Só conta contatos com officeimpresso_codigo.
"""
from __future__ import annotations
import os, sys
os.environ.setdefault("FIREBIRD_PY_DRIVER", "fdb")
sys.path.insert(0, os.path.dirname(__file__))
sys.stdout.reconfigure(encoding="utf-8")
from lib.firebird_reader import firebird_connect  # noqa
import pymysql, pymysql.cursors  # noqa

# (label, coluna PESSOAS, coluna contacts destino | None se sem destino)
CAMPOS = [
    ("Razão Social",          "RAZAOSOCIAL",            "name"),
    ("Fantasia/Apelido",      "FANTASIA",               "supplier_business_name"),
    ("CNPJ/CPF",              "CNPJCPF",                "tax_number"),
    ("Inscrição Estadual",    "INSCIDENT",              "ie"),
    ("Inscrição Municipal",   "INSCMUNICIPAL",          "inscricao_municipal"),
    ("Tipo Contribuinte",     "TIPO_CONTRIBUINTE",      "indicador_ie"),
    ("Regime (CRT)",          "CRT",                    "regime"),
    ("Contato",               "CONTATO",                "contato"),
    ("Telefone",              "FONE1",                  "landline"),
    ("Celular",               "FONE2",                  "mobile"),
    ("Email",                 "EMAIL",                  "email"),
    ("Site",                  "SITE",                   "website"),
    ("Endereço",              "ENDERECO",               "address_line_1"),
    ("Número",                "NUMERO",                 "numero"),
    ("Complemento",           "COMPLEMENTO",            "complemento"),
    ("Bairro",                "BAIRRO",                 "neighborhood"),
    ("CEP",                   "CEP",                    "zip_code"),
    ("Cidade",                "CIDADE",                 "city"),
    ("UF",                    "UF",                     "state"),
    ("Cond. Pagamento",       "CODCONDICAOPAGTO",       "pgto_padrao"),
    ("Tabela de Preço",       "CODPRODUTO_TABELA",      "customer_group_id"),
    ("Observação",            "OBSERVACAO",             "obs_comercial"),
    ("Mensagem p/ Venda",     "MENSAGEM_PARA_VENDA",    "mensagem_venda"),
    ("Limite de Crédito",     "LIMITE_CREDITO",         "credit_limit"),
    ("Limite de Desconto",    "LIMITE_DESCONTO",        "limite_desconto_percentual"),
    ("Bloqueado",             "BLOQUEADO",              "bloqueado"),
    ("Representante (FK)",    "PESSOA_REPRESENTANTE_CODIGO", "sales_rep_contact_id"),
    ("Matriz/Filial (FK)",    "PESSOA_ASSOCIADO_CODIGO", "parent_contact_id"),
    ("Aniversário",           "ANIVERSARIO",            "aniversario_mmdd"),
    ("ISS Retido",            "ISS_RETIDO",             "iss_retido"),
    ("Prioridade Produção",   "PRIORIDADE_PRODUCAO",    "prioridade_producao"),
]


def fb_has_col(cur, table, col):
    cur.execute(f"SELECT COUNT(*) FROM RDB$RELATION_FIELDS WHERE RDB$RELATION_NAME='{table}' AND TRIM(RDB$FIELD_NAME)='{col}'")
    return cur.fetchone()[0] > 0


def my_has_col(mcur, col):
    mcur.execute("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contacts' AND COLUMN_NAME=%s", (col,))
    return mcur.fetchone()["n"] > 0


def main():
    biz = 164
    _ctx = firebird_connect("Martinho online", password_override="masterkey")
    con = _ctx.__enter__()
    cur = con.cursor()
    cur.execute("SELECT COUNT(*) FROM PESSOAS")
    total_pessoas = cur.fetchone()[0]

    # origem: % preenchido por campo (contagem em cascata, cursor fresco por tentativa)
    def count_filled(fb_col):
        for where in (
            f"{fb_col} IS NOT NULL AND TRIM({fb_col}) <> ''",          # VARCHAR
            f"{fb_col} IS NOT NULL AND CHAR_LENGTH({fb_col}) > 0",     # BLOB text
            f"{fb_col} IS NOT NULL",                                    # numérico/data
        ):
            c2 = con.cursor()
            try:
                c2.execute(f"SELECT COUNT(*) FROM PESSOAS WHERE {where}")
                return c2.fetchone()[0]
            except Exception:
                continue
            finally:
                c2.close()
        return -1

    origem = {}
    for label, fb_col, _ in CAMPOS:
        if not fb_has_col(cur, "PESSOAS", fb_col):
            origem[label] = ("(coluna ausente)", 0)
            continue
        origem[label] = ("ok", count_filled(fb_col))
    _ctx.__exit__(None, None, None)

    mcon = pymysql.connect(host="127.0.0.1", port=33069, user="u906587222_oimpresso",
                           password=os.environ["MYSQL_PASSWORD"], database="u906587222_oimpresso",
                           charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor)
    mcur = mcon.cursor()
    mcur.execute("SELECT COUNT(*) n FROM contacts WHERE business_id=%s AND deleted_at IS NULL AND officeimpresso_codigo IS NOT NULL AND officeimpresso_codigo<>''", (biz,))
    total_contacts = mcur.fetchone()["n"]

    print(f"PESSOAS (origem): {total_pessoas}  |  contacts c/ codigo (destino): {total_contacts}\n")
    print(f"{'CAMPO':24}{'ORIGEM':>10}{'DESTINO':>10}{'COL?':>6}  VEREDITO")
    print("-" * 78)
    for label, fb_col, my_col in CAMPOS:
        ostatus, ocount = origem[label]
        if ostatus != "ok":
            print(f"{label:24}{'—':>10}{'':>10}{'':>6}  origem sem coluna")
            continue
        if my_col is None or not my_has_col(mcur, my_col):
            col_ok = "NÃO"
            dcount = 0
        else:
            col_ok = "sim"
            if my_col == "customer_group_id":
                mcur.execute(f"SELECT COUNT(*) n FROM contacts WHERE business_id=%s AND deleted_at IS NULL AND officeimpresso_codigo IS NOT NULL AND officeimpresso_codigo<>'' AND {my_col} IS NOT NULL", (biz,))
            else:
                mcur.execute(f"SELECT COUNT(*) n FROM contacts WHERE business_id=%s AND deleted_at IS NULL AND officeimpresso_codigo IS NOT NULL AND officeimpresso_codigo<>'' AND {my_col} IS NOT NULL AND {my_col}<>''", (biz,))
            dcount = mcur.fetchone()["n"]

        # veredito
        if ocount == 0:
            verdict = "N/A (origem vazia)"
        elif col_ok == "NÃO":
            verdict = "❌ SEM COLUNA destino"
        elif dcount == 0:
            verdict = "❌ NÃO MIGRADO"
        elif dcount >= ocount * 0.85:
            verdict = "✅ MIGRADO"
        else:
            verdict = f"🟡 PARCIAL ({dcount}/{ocount})"
        print(f"{label:24}{ocount:>10}{dcount:>10}{col_ok:>6}  {verdict}")
    mcon.close()
    print("\n(ORIGEM = nº de PESSOAS com o campo preenchido; DESTINO = nº de contacts c/ código e campo preenchido)")


if __name__ == "__main__":
    main()
