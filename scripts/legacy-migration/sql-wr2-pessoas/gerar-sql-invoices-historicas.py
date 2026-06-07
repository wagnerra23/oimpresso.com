"""
ETAPAS 2-3-4: gera 3 SQLs (por ano) inserindo rb_invoices históricas
a partir de fin_titulos jan/2024-jun/2026.

Status map:
  fin_titulos.status=quitado/parcial  →  rb_invoices.status=paid, pago_em=ultima_baixa
  fin_titulos.status=aberto + venc<hoje → overdue
  fin_titulos.status=aberto + venc>=hoje → open
  fin_titulos.status=cancelado → canceled

Input  : output/planos-mensalidade/invoices-base.tsv (~3389 linhas)
Output :
  output/planos-mensalidade/etapa2-invoices-2024.sql
  output/planos-mensalidade/etapa3-invoices-2025.sql
  output/planos-mensalidade/etapa4-invoices-2026.sql
"""
import csv, json, os
from datetime import date

ROOT = os.path.dirname(os.path.abspath(__file__))
TSV  = os.path.join(ROOT, "output", "planos-mensalidade", "invoices-base.tsv")
HOJE = date.today().strftime("%Y-%m-%d")

def mysql_escape(s):
    if s is None or s == "" or s == "NULL":
        return "NULL"
    return "'" + str(s).replace("\\", "\\\\").replace("'", "''") + "'"

# Carrega e separa por ano
por_ano = {"2024": [], "2025": [], "2026": []}
with open(TSV, "r", encoding="utf-8") as f:
    reader = csv.DictReader(f, delimiter="\t")
    for r in reader:
        titulo_id    = r["titulo_id"].strip()
        cliente_id   = r["cliente_id"].strip()
        sub_id       = r["subscription_id"].strip()
        vencimento   = r["vencimento"].strip()
        valor        = r["valor"].strip()
        status_t     = r["status_titulo"].strip()
        conta        = r["conta_bancaria_id"].strip()
        ultima_baixa = r["ultima_baixa"].strip()
        ano          = vencimento[:4]
        comp         = vencimento[:7]

        # Mapeamento de status
        if status_t in ("quitado", "parcial"):
            status = "paid"
            pago_em = f"{ultima_baixa} 00:00:00" if ultima_baixa and ultima_baixa != "NULL" else f"{vencimento} 00:00:00"
        elif status_t == "cancelado":
            status = "canceled"
            pago_em = None
        elif status_t == "aberto":
            status = "overdue" if vencimento < HOJE else "open"
            pago_em = None
        else:
            status = "open"
            pago_em = None

        # numero_documento UNIQUE per (biz, numero) — sufixo legacy_titulo_id pra evitar colisão
        # quando mesmo cliente tem >1 mensalidade no mesmo mês (parcelamento, retroativo, etc).
        numero_documento = f"RB-{sub_id}-{comp}-L{titulo_id}"

        meta = json.dumps({
            "source": "wr-comercial-retroatividade-2026-06-07",
            "legacy_titulo_id": int(titulo_id),
            "status_original": status_t,
            "us": "US-RB-003-retro",
        }, ensure_ascii=False)

        rows_data = {
            "business_id": 1,
            "subscription_id": sub_id,
            "contact_id": cliente_id,
            "numero_documento": numero_documento,
            "valor": valor,
            "status": status,
            "vencimento": vencimento,
            "pago_em": pago_em,
            "conta_bancaria_id": conta if conta and conta != "NULL" else "NULL",
            "metadata": meta,
        }
        if ano in por_ano:
            por_ano[ano].append(rows_data)

# Gera 1 SQL por ano
for ano, rows in por_ano.items():
    out = os.path.join(ROOT, "output", "planos-mensalidade", f"etapa{2 if ano=='2024' else 3 if ano=='2025' else 4}-invoices-{ano}.sql")
    sql = []
    sql.append("-- ============================================================")
    sql.append(f"-- ETAPA {2 if ano=='2024' else 3 if ano=='2025' else 4}: {len(rows)} rb_invoices historicas {ano}")
    sql.append("-- Gerado: 2026-06-07 (sessao Eliana - retroatividade RecurringBilling)")
    sql.append("-- ============================================================")
    sql.append("")
    sql.append("START TRANSACTION;")
    sql.append("")
    for r in rows:
        pago_em_val = mysql_escape(r["pago_em"]) if r["pago_em"] else "NULL"
        conta = r["conta_bancaria_id"]
        sql.append(
            "INSERT INTO rb_invoices ("
            "business_id, subscription_id, contact_id, numero_documento, valor, "
            "status, vencimento, pago_em, conta_bancaria_id, metadata, created_at, updated_at"
            ") VALUES ("
            f"{r['business_id']}, {r['subscription_id']}, {r['contact_id']}, "
            f"{mysql_escape(r['numero_documento'])}, {r['valor']}, "
            f"{mysql_escape(r['status'])}, {mysql_escape(r['vencimento'])}, {pago_em_val}, "
            f"{conta}, {mysql_escape(r['metadata'])}, NOW(), NOW());"
        )
    sql.append("")
    sql.append(f"-- Verificacao {ano}")
    sql.append(f"SELECT '=== Invoices {ano} biz=1 ===' AS x;")
    sql.append("SELECT status, COUNT(*) AS qtd, ROUND(SUM(valor),2) AS soma")
    sql.append(f"FROM rb_invoices WHERE business_id=1 AND YEAR(vencimento)={ano} GROUP BY status;")
    sql.append("")
    sql.append("COMMIT;")
    with open(out, "w", encoding="utf-8") as f:
        f.write("\n".join(sql))
    # Status distribution
    status_count = {}
    for r in rows:
        status_count[r["status"]] = status_count.get(r["status"], 0) + 1
    print(f"  {ano}: {len(rows)} invoices · {status_count} -> {out}")

print(f"\nTotal: {sum(len(rs) for rs in por_ano.values())} invoices")
